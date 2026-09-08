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
 * Builds a large fixture for load and query measurements.
 *
 * Defaults mirror the agreed target: 50k questions, 15k of them CAT items with
 * parameters, 25k users and 250k attempts with 25 answered questions each - which
 * means 6.25 million question attempt steps. That last number dominates everything
 * else, so it is the one to reduce first when a run has to fit into a time budget.
 *
 * Idempotent by marker: a second run adds nothing. Use --reset to start over.
 *
 *   php local/catquiz/cli/loadtest_seed.php --attempts=250000 --steps=25
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options] = cli_get_params(
    [
        'help' => false,
        'questions' => 50000,
        'items' => 15000,
        'users' => 25000,
        'attempts' => 250000,
        'steps' => 25,
        'batch' => 5000,
        'reset' => false,
    ],
    ['h' => 'help']
);

if ($options['help']) {
    cli_writeln("Builds a large fixture for load and query measurements.

  --questions=N   Size of the question bank (default 50000).
  --items=N       CAT items with parameters (default 15000).
  --users=N       Users taking attempts (default 25000).
  --attempts=N    Attempts (default 250000).
  --steps=N       Answered questions per attempt (default 25).
  --batch=N       Rows per insert batch (default 5000).
  --reset         Remove a previous fixture first.
");
    exit(0);
}

global $DB, $CFG;

$marker = 'Loadtest scale';
$now = time();
$batch = max(500, (int) $options['batch']);

/**
 * Inserts rows in batches, reporting progress.
 *
 * @param string $table
 * @param callable $builder Returns the row for an index, or null to skip.
 * @param int $count
 * @param int $batch
 * @param string $label
 * @return void
 */
function loadtest_bulk(string $table, callable $builder, int $count, int $batch, string $label): void {
    global $DB;

    $rows = [];
    $done = 0;
    for ($i = 0; $i < $count; $i++) {
        $row = $builder($i);
        if ($row === null) {
            continue;
        }
        $rows[] = $row;
        if (count($rows) >= $batch) {
            $DB->insert_records($table, $rows);
            $done += count($rows);
            $rows = [];
            cli_writeln(sprintf('  %s: %d / %d', $label, $done, $count));
        }
    }
    if ($rows) {
        $DB->insert_records($table, $rows);
        $done += count($rows);
    }
    cli_writeln(sprintf('  %s: %d done', $label, $done));
}

if ($options['reset']) {
    cli_writeln('Removing previous fixture ...');
    $scaleids = $DB->get_fieldset_select('local_catquiz_catscales', 'id', 'name = :n', ['n' => $marker]);
    if ($scaleids) {
        [$insql, $inparams] = $DB->get_in_or_equal($scaleids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_catquiz_itemparams', "itemid IN (
            SELECT id FROM {local_catquiz_items} WHERE catscaleid $insql)", $inparams);
        $DB->delete_records_select('local_catquiz_items', "catscaleid $insql", $inparams);
        $DB->delete_records_select('local_catquiz_attempts', "scaleid $insql", $inparams);
        $DB->delete_records_select('local_catquiz_catscales', "id $insql", $inparams);
    }
    $DB->delete_records_select('question', 'name LIKE :p', ['p' => 'Loadtest %']);
    $DB->delete_records_select('user', 'username LIKE :p', ['p' => 'loadtest%']);
    cli_writeln('Removed.');
}

if ($DB->record_exists('local_catquiz_catscales', ['name' => $marker])) {
    cli_writeln('Fixture already present - nothing to do. Use --reset to rebuild.');
    exit(0);
}

$start = microtime(true);

$contextid = (int) $DB->insert_record('local_catquiz_catcontext', (object) [
    'name' => 'Loadtest context',
    'description' => '',
    'descriptionformat' => FORMAT_HTML,
    'starttimestamp' => $now - 86400,
    'endtimestamp' => $now + 86400,
    'timecreated' => $now,
    'timemodified' => $now,
    'usermodified' => 0,
]);
$scaleid = (int) $DB->insert_record('local_catquiz_catscales', (object) [
    'parentid' => 0,
    'name' => $marker,
    'contextid' => $contextid,
    'timecreated' => $now,
    'timemodified' => $now,
]);
$categoryid = (int) $DB->insert_record('question_categories', (object) [
    'name' => 'Loadtest category',
    'contextid' => context_system::instance()->id,
    'info' => '',
    'infoformat' => FORMAT_HTML,
    'stamp' => make_unique_id_code(),
    'parent' => 0,
    'sortorder' => 0,
]);

cli_writeln('Questions ...');
loadtest_bulk('question', function (int $i) use ($now) {
    return (object) [
        'name' => "Loadtest question $i",
        'questiontext' => "<p>Body of loadtest question $i with filler text.</p>",
        'questiontextformat' => FORMAT_HTML,
        'qtype' => 'truefalse',
        'generalfeedback' => '',
        'generalfeedbackformat' => FORMAT_HTML,
        'timecreated' => $now,
        'timemodified' => $now,
        'createdby' => 2,
        'modifiedby' => 2,
    ];
}, (int) $options['questions'], $batch, 'questions');

$questionids = $DB->get_fieldset_select('question', 'id', 'name LIKE :p', ['p' => 'Loadtest %']);

cli_writeln('Bank entries and versions ...');
loadtest_bulk('question_bank_entries', function () use ($categoryid) {
    return (object) ['questioncategoryid' => $categoryid, 'idnumber' => null, 'ownerid' => 2];
}, count($questionids), $batch, 'entries');

$entryids = $DB->get_fieldset_sql(
    'SELECT id FROM {question_bank_entries} WHERE questioncategoryid = :c ORDER BY id',
    ['c' => $categoryid]
);
loadtest_bulk('question_versions', function (int $i) use ($questionids, $entryids) {
    if (!isset($entryids[$i], $questionids[$i])) {
        return null;
    }
    return (object) [
        'questionbankentryid' => $entryids[$i],
        'version' => 1,
        'questionid' => $questionids[$i],
        'status' => 'ready',
    ];
}, count($questionids), $batch, 'versions');

cli_writeln('CAT items ...');
loadtest_bulk('local_catquiz_items', function (int $i) use ($questionids, $scaleid, $contextid) {
    if (!isset($questionids[$i])) {
        return null;
    }
    return (object) [
        'componentid' => $questionids[$i],
        'componentname' => 'question',
        'catscaleid' => $scaleid,
        'contextid' => $contextid,
        'activeparamid' => 0,
        'status' => 0,
    ];
}, (int) $options['items'], $batch, 'items');

$itemids = $DB->get_fieldset_select('local_catquiz_items', 'id', 'catscaleid = :s', ['s' => $scaleid]);

cli_writeln('Item parameters ...');
loadtest_bulk('local_catquiz_itemparams', function (int $i) use ($itemids, $contextid, $now) {
    if (!isset($itemids[$i])) {
        return null;
    }
    // Every twentieth is unusable, so the #54 filter has something to find.
    $unusable = ($i % 20) === 0;
    return (object) [
        'itemid' => $itemids[$i],
        'componentname' => 'question',
        'contextid' => $contextid,
        'model' => $unusable ? 'raschbirnbaum' : 'rasch',
        'difficulty' => 0.5,
        'discrimination' => $unusable ? 0.0 : 1.0,
        'usable' => $unusable ? 0 : 1,
        'status' => 4,
        'timecreated' => $now,
        'timemodified' => $now,
    ];
}, count($itemids), $batch, 'itemparams');

// The planner still has statistics from before the bulk insert - to it both tables
// look empty. It then plans the correlated subquery below as a nested loop over
// sequential scans, which is quadratic at a quarter of a million rows: the step ran
// for over half an hour without finishing. Refreshing the statistics first is what
// lets the existing index on itemparams.itemid be used at all.
cli_writeln('Refreshing statistics before linking ...');
if ($DB->get_dbfamily() === 'postgres') {
    $DB->execute('ANALYZE {local_catquiz_items}');
    $DB->execute('ANALYZE {local_catquiz_itemparams}');
} else if ($DB->get_dbfamily() === 'mysql') {
    $DB->execute('ANALYZE TABLE {local_catquiz_items}');
    $DB->execute('ANALYZE TABLE {local_catquiz_itemparams}');
}

cli_writeln('Linking active parameters ...');
$DB->execute(
    'UPDATE {local_catquiz_items} lci
                 SET activeparamid = (SELECT MIN(p.id) FROM {local_catquiz_itemparams} p
                                       WHERE p.itemid = lci.id)
               WHERE lci.catscaleid = :s
                 AND EXISTS (SELECT 1 FROM {local_catquiz_itemparams} p2 WHERE p2.itemid = lci.id)',
    ['s' => $scaleid]
);

cli_writeln('Users ...');
loadtest_bulk('user', function (int $i) use ($now, $CFG) {
    return (object) [
        'auth' => 'manual',
        'confirmed' => 1,
        'mnethostid' => $CFG->mnet_localhost_id,
        'username' => 'loadtest' . $i,
        'password' => 'not-a-real-hash',
        'firstname' => 'Load',
        'lastname' => 'Tester ' . $i,
        'email' => 'loadtest' . $i . '@example.invalid',
        'timecreated' => $now,
        'timemodified' => $now,
    ];
}, (int) $options['users'], $batch, 'users');

$userids = $DB->get_fieldset_select('user', 'id', 'username LIKE :p', ['p' => 'loadtest%']);
$usercount = max(1, count($userids));

cli_writeln('Question usages, attempts and steps ...');
$attempts = (int) $options['attempts'];
$steps = (int) $options['steps'];
$systemcontextid = context_system::instance()->id;

for ($offset = 0; $offset < $attempts; $offset += $batch) {
    $upto = min($batch, $attempts - $offset);

    $usages = [];
    for ($i = 0; $i < $upto; $i++) {
        $usages[] = (object) [
            'contextid' => $systemcontextid,
            'component' => 'mod_adaptivequiz',
            'preferredbehaviour' => 'deferredfeedback',
        ];
    }
    // The get_fieldset_sql() helper takes no limit arguments - passing them is silently ignored
    // and the query returns every row ever inserted. Remembering the highest id
    // before the batch and selecting beyond it is exact and cheap.
    $beforeusage = (int) $DB->get_field_sql('SELECT COALESCE(MAX(id), 0) FROM {question_usages}');
    $DB->insert_records('question_usages', $usages);
    $usageids = $DB->get_fieldset_select('question_usages', 'id', 'id > :b ORDER BY id', ['b' => $beforeusage]);

    $aqrows = [];
    foreach ($usageids as $k => $usageid) {
        $aqrows[] = (object) [
            'instance' => 1,
            'userid' => $userids[($offset + $k) % $usercount],
            'uniqueid' => $usageid,
            'attemptstate' => 'complete',
            'attemptstopcriteria' => '',
            'questionsattempted' => $steps,
            'standarderror' => 0.4,
            'measure' => 0.0,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
    }
    $beforeaq = (int) $DB->get_field_sql('SELECT COALESCE(MAX(id), 0) FROM {adaptivequiz_attempt}');
    $DB->insert_records('adaptivequiz_attempt', $aqrows);
    $aqids = $DB->get_fieldset_select('adaptivequiz_attempt', 'id', 'id > :b ORDER BY id', ['b' => $beforeaq]);

    $catrows = [];
    foreach ($aqids as $k => $aqid) {
        $catrows[] = (object) [
            'userid' => $userids[($offset + $k) % $usercount],
            'scaleid' => $scaleid,
            'contextid' => $contextid,
            'courseid' => 1,
            'attemptid' => $aqid,
            'component' => 'mod_adaptivequiz',
            'instanceid' => 1,
            'status' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
            'endtime' => $now,
        ];
    }
    $DB->insert_records('local_catquiz_attempts', $catrows);

    // The steps dominate the total row count: attempts x steps.
    $qarows = [];
    foreach ($usageids as $k => $usageid) {
        for ($sslot = 1; $sslot <= $steps; $sslot++) {
            $qarows[] = (object) [
                'questionusageid' => $usageid,
                'slot' => $sslot,
                'behaviour' => 'deferredfeedback',
                'questionid' => $itemids ? $questionids[($offset + $k + $sslot) % count($questionids)] : 1,
                'variant' => 1,
                'maxmark' => 1.0,
                'minfraction' => 0.0,
                'maxfraction' => 1.0,
                'flagged' => 0,
                'questionsummary' => '',
                'timemodified' => $now,
            ];
        }
    }
    // One insert per batch of attempts would mean batch x steps rows in a single
    // call - 125,000 at the default sizes, which the driver does not survive. The
    // step rows are chunked to the same batch size as everything else.
    $beforeqa = (int) $DB->get_field_sql('SELECT COALESCE(MAX(id), 0) FROM {question_attempts}');
    foreach (array_chunk($qarows, $batch) as $chunk) {
        $DB->insert_records('question_attempts', $chunk);
    }

    $qaids = $DB->get_fieldset_select('question_attempts', 'id', 'id > :b ORDER BY id', ['b' => $beforeqa]);
    $steprows = [];
    foreach ($qaids as $qaid) {
        $steprows[] = (object) [
            'questionattemptid' => $qaid,
            'sequencenumber' => 1,
            'state' => 'gradedright',
            'fraction' => 1.0,
            'timecreated' => $now,
            'userid' => 2,
        ];
    }
    foreach (array_chunk($steprows, $batch) as $chunk) {
        $DB->insert_records('question_attempt_steps', $chunk);
    }

    cli_writeln(sprintf(
        '  attempts: %d / %d (%d steps, %.0fs elapsed)',
        $offset + $upto,
        $attempts,
        ($offset + $upto) * $steps,
        microtime(true) - $start
    ));
}

cli_writeln(sprintf('Done in %.0f seconds.', microtime(true) - $start));
cli_writeln('scaleid=' . $scaleid . ' contextid=' . $contextid);
