<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Seeds a large CAT-manager / statistics profile for the read-endpoint load
 * tests (JMeter / k6).
 *
 * It creates one CAT context, a small scale tree and a configurable number of
 * person-parameter rows (many users x scales) so that the CAT manager and
 * statistics pages have to aggregate over a realistic amount of data. Nothing
 * here depends on the PHPUnit/Behat data generators, so it runs against a plain
 * installed site.
 *
 * It prints `export KEY='value'` lines (BASE_URL, SCALEID, CONTEXTID, COURSEID)
 * that the load workflows read to parameterise the load plan. Authentication
 * uses the admin account created by admin/cli/install.php.
 *
 * Usage (from the Moodle root):
 *   php local/catquiz/tests/load/seed_large.php [--users=2000] [--scales=8]
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params(
    ['users' => 2000, 'scales' => 8, 'items' => 20000, 'help' => false],
    ['h' => 'help']
);

if ($options['help']) {
    cli_writeln("Seed a large CAT manager / statistics profile.\n");
    cli_writeln("  --users=N   Number of distinct persons (default 2000).");
    cli_writeln("  --scales=N  Number of subscales under the root (default 8).");
    cli_writeln("  --items=N   Questions with item parameters (default 20000).");
    cli_writeln("              Without these the manager and the statistics pages are");
    cli_writeln("              measured against an empty item pool, which is not what");
    cli_writeln("              the load tests are meant to find out.");
    exit(0);
}

$numusers = max(1, (int) $options['users']);
$numscales = max(1, (int) $options['scales']);
$now = time();

// 1. A CAT context that scopes the profile.
$contextid = $DB->insert_record('local_catquiz_catcontext', (object) [
    'parentid' => 0,
    'name' => 'Load test context',
    'description' => '',
    'descriptionformat' => FORMAT_PLAIN,
    'starttimestamp' => 0,
    'endtimestamp' => 0,
    'json' => json_encode(new stdClass()),
    'usermodified' => 2,
    'timecreated' => $now,
    'timemodified' => $now,
]);

// 2. A root scale plus a few subscales, all bound to the context above.
$rootid = $DB->insert_record('local_catquiz_catscales', (object) [
    'parentid' => 0,
    'label' => 'LOAD-root',
    'name' => 'Load test scale',
    'description' => '',
    'contextid' => $contextid,
    'minscalevalue' => -5,
    'maxscalevalue' => 5,
    'timecreated' => $now,
    'timemodified' => $now,
]);

$scaleids = [(int) $rootid];
for ($s = 1; $s <= $numscales; $s++) {
    $scaleids[] = (int) $DB->insert_record('local_catquiz_catscales', (object) [
        'parentid' => $rootid,
        'label' => 'LOAD-sub-' . $s,
        'name' => 'Load subscale ' . $s,
        'description' => '',
        'contextid' => $contextid,
        'minscalevalue' => -5,
        'maxscalevalue' => 5,
        'timecreated' => $now,
        'timemodified' => $now,
    ]);
}

// 3. Bulk person parameters: one finite ability per (user, scale). insert_records
// batches the write so a few hundred thousand rows stay fast.
$batch = [];
$flush = function () use (&$batch, $DB) {
    if ($batch) {
        $DB->insert_records('local_catquiz_personparams', $batch);
        $batch = [];
    }
};
$total = 0;
for ($u = 1; $u <= $numusers; $u++) {
    $userid = 1000000 + $u; // Synthetic ids; the pages aggregate, they don't join users.
    foreach ($scaleids as $scaleid) {
        $batch[] = (object) [
            'userid' => $userid,
            'catscaleid' => $scaleid,
            'contextid' => $contextid,
            'ability' => round(mt_rand(-400, 400) / 100, 4),
            'status' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $total++;
        if (count($batch) >= 500) {
            $flush();
        }
    }
}
$flush();

cli_writeln("# seeded $numusers users x " . count($scaleids) . " scales = $total person params", STDERR);

// 5. Make sure the admin account has a known password so the load plan can log
// in. The value comes from the LOAD_ADMIN_PASS env var (default Admin!23).
// 4. Questions and item parameters.
//
// Without them the load plans measure the manager and the statistics pages against
// an empty item pool - rendering, sessions and concurrency, but none of the queries
// that grow with the pool. A JMeter run reported 60 ms for the CAT manager that way,
// while the same page over 50.000 items needs an order of magnitude more.
$numitems = max(0, (int) $options['items']);

if ($numitems > 0) {
    $now = time();

    // One question category is enough: the pool is selected by scale and context, not
    // by category, so more categories would add rows without adding realism.
    $categoryid = (int) $DB->insert_record('question_categories', (object) [
        'name' => 'Load test questions',
        'contextid' => \context_system::instance()->id,
        'info' => '',
        'infoformat' => FORMAT_HTML,
        'stamp' => 'loadtest' . $now,
        'parent' => 0,
        'sortorder' => 0,
    ]);

    $questionbatch = [];
    $questionids = [];
    $flushquestions = function () use (&$questionbatch, &$questionids, $DB, $categoryid) {
        if (empty($questionbatch)) {
            return;
        }
        foreach ($questionbatch as $record) {
            $questionid = (int) $DB->insert_record('question', $record);
            $entryid = (int) $DB->insert_record('question_bank_entries', (object) [
                'questioncategoryid' => $categoryid,
                'idnumber' => null,
                'ownerid' => 2,
            ]);
            $DB->insert_record('question_versions', (object) [
                'questionbankentryid' => $entryid,
                'version' => 1,
                'questionid' => $questionid,
                'status' => 'ready',
            ]);
            $questionids[] = $questionid;
        }
        $questionbatch = [];
    };

    for ($i = 1; $i <= $numitems; $i++) {
        $questionbatch[] = (object) [
            'name' => 'Load item ' . $i,
            'questiontext' => 'Load test question ' . $i,
            'questiontextformat' => FORMAT_HTML,
            'qtype' => 'truefalse',
            'generalfeedback' => '',
            'generalfeedbackformat' => FORMAT_HTML,
            'timecreated' => $now,
            'timemodified' => $now,
            'createdby' => 2,
            'modifiedby' => 2,
        ];
        if (count($questionbatch) >= 500) {
            $flushquestions();
        }
    }
    $flushquestions();

    // Item and parameter rows, spread across the subscales so that a scale tree walk
    // has something to walk.
    $itembatch = [];
    $flushitems = function () use (&$itembatch, $DB) {
        if (empty($itembatch)) {
            return;
        }
        foreach ($itembatch as $pair) {
            $itemid = (int) $DB->insert_record('local_catquiz_items', $pair[0]);
            $pair[1]->itemid = $itemid;
            $paramid = (int) $DB->insert_record('local_catquiz_itemparams', $pair[1]);
            $DB->set_field('local_catquiz_items', 'activeparamid', $paramid, ['id' => $itemid]);
        }
        $itembatch = [];
    };

    foreach ($questionids as $index => $questionid) {
        $scaleid = $scaleids[$index % count($scaleids)];

        // Difficulties spread over a realistic range rather than a constant: a pool in
        // which every item has the same difficulty makes the selection degenerate and
        // would measure a case that does not occur.
        $difficulty = round(-3.0 + (6.0 * ($index % 100) / 100), 4);

        $itembatch[] = [
            (object) [
                'componentid' => $questionid,
                'componentname' => 'question',
                'catscaleid' => $scaleid,
                'contextid' => $contextid,
                'activeparamid' => 0,
                'status' => 4,
            ],
            (object) [
                'itemid' => 0,
                'componentname' => 'question',
                'contextid' => $contextid,
                'model' => 'raschbirnbaum',
                'difficulty' => $difficulty,
                'discrimination' => 1.0,
                'guessing' => 0.0,
                'usable' => 1,
                'status' => 4,
                'timecreated' => $now,
                'timemodified' => $now,
            ],
        ];

        if (count($itembatch) >= 500) {
            $flushitems();
        }
    }
    $flushitems();

    cli_writeln("# seeded $numitems questions with item parameters", STDERR);
}

$adminpass = getenv('LOAD_ADMIN_PASS') ?: 'Admin!23';
$admin = get_admin();
if ($admin) {
    update_internal_user_password($admin, $adminpass);
}

// 6. Emit the parameters the load plan needs. BASE_URL comes from $CFG->wwwroot.
cli_writeln("export BASE_URL='" . addslashes($CFG->wwwroot) . "'");
cli_writeln("export ADMIN_USER='" . addslashes($admin->username ?? 'admin') . "'");
cli_writeln("export ADMIN_PASS='" . addslashes($adminpass) . "'");
cli_writeln("export CONTEXTID='" . $contextid . "'");
cli_writeln("export SCALEID='" . $rootid . "'");
cli_writeln("export COURSEID='1'");
