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
 * Seeds a deterministic CAT manager fixture for the Playwright tests.
 *
 * Prints shell "export KEY='value'" lines so it can be sourced locally and parsed
 * by the workflow. Run from the Moodle root:
 *
 *   php local/catquiz/tests/playwright/seed.php
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../../config.php');

global $CFG, $DB;

// The search terms are deliberately nonsense words. A real word could occur in
// Moodle's own sample content, and the test would then pass or fail depending on
// what else happens to be installed rather than on the search itself.
/** @var string A word that appears in exactly one seeded question text. */
const CATQUIZ_SEED_MATCHING_TERM = 'zorblewump';

/** @var string A word that appears in no seeded question text. */
const CATQUIZ_SEED_MISSING_TERM = 'quxnotpresentqux';

/**
 * Prints the shell exports both the fresh and the reused path need.
 *
 * @param string $wwwroot
 * @param int $scaleid
 * @param int $contextid
 * @param int $matchingid
 * @param int $courseid
 * @param int $statscmid
 * @return void
 */
function catquiz_seed_export(
    string $wwwroot,
    int $scaleid,
    int $contextid,
    int $matchingid,
    int $courseid = 0,
    int $statscmid = 0
): void {
    echo "export CATQUIZ_BASE_URL='" . $wwwroot . "'\n";
    echo "export CATQUIZ_SCALEID='" . $scaleid . "'\n";
    echo "export CATQUIZ_CONTEXTID='" . $contextid . "'\n";
    echo "export CATQUIZ_MATCHING_TERM='" . CATQUIZ_SEED_MATCHING_TERM . "'\n";
    echo "export CATQUIZ_MISSING_TERM='" . CATQUIZ_SEED_MISSING_TERM . "'\n";
    echo "export CATQUIZ_MATCHING_QUESTION='Playwright matching question'\n";
    echo "export CATQUIZ_MATCHING_QUESTIONID='" . $matchingid . "'\n";
    echo "export CATQUIZ_UNUSABLE_QUESTION='Playwright unusable question'\n";
    echo "export CATQUIZ_PILOT_QUESTION='Playwright pilot question'\n";
    echo "export CATQUIZ_ADMIN_USER='admin'\n";
    echo "export CATQUIZ_ADMIN_PASS='Admin!23'\n";
    echo "export CATQUIZ_COURSEID='" . $courseid . "'\n";
    echo "export CATQUIZ_STATSPAGE_CMID='" . $statscmid . "'\n";
}

$now = time();

// Idempotent: a second run must not build a second scale next to the first. Two
// identically named scales in different contexts is what made earlier browser runs
// fail in ways that looked like application bugs - the tests then pointed at
// whichever of the two the environment happened to name.
// get_record() would warn and pick arbitrarily if an older run left more than one
// scale behind; take the oldest deterministically instead.
$existing = $DB->get_records('local_catquiz_catscales', ['name' => 'Playwright scale'], 'id ASC', '*', 0, 1);
$existing = $existing ? reset($existing) : false;
if ($existing) {
    $contextid = (int) $existing->contextid;
    $scaleid = (int) $existing->id;
    $course = $DB->get_record('course', ['shortname' => 'playwright-stats']);
    $cmid = 0;
    if ($course) {
        $page = $DB->get_record('page', ['course' => $course->id, 'name' => 'Playwright statistics']);
        if ($page) {
            // Keep the shortcode pointing at the scale that exists now. The page
            // survives a fixture reset, the scale does not - a stale id renders an
            // error instead of charts and would look like a product defect.
            $DB->set_field(
                'page',
                'content',
                '[catquizstatistics globalscale=' . $scaleid . ']',
                ['id' => $page->id]
            );
            $cm = get_coursemodule_from_instance('page', $page->id, $course->id, false, IGNORE_MISSING);
            $cmid = $cm ? (int) $cm->id : 0;
        }
    }

    catquiz_seed_export(
        $CFG->wwwroot,
        $scaleid,
        $contextid,
        (int) $DB->get_field_sql(
            "SELECT MIN(componentid) FROM {local_catquiz_items} WHERE catscaleid = :id",
            ['id' => $scaleid]
        ),
        $course ? (int) $course->id : 0,
        $cmid
    );
    exit(0);
}

$contextid = (int) $DB->insert_record('local_catquiz_catcontext', (object) [
    'name' => 'Playwright context',
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
    'name' => 'Playwright scale',
    'contextid' => $contextid,
    'timecreated' => $now,
    'timemodified' => $now,
]);

// A question category is needed so the question bank joins resolve.
$systemcontext = context_system::instance();
$categoryid = (int) $DB->insert_record('question_categories', (object) [
    'name' => 'Playwright category',
    'contextid' => $systemcontext->id,
    'info' => '',
    'infoformat' => FORMAT_HTML,
    'stamp' => make_unique_id_code(),
    'parent' => 0,
    'sortorder' => 0,
]);

/**
 * Creates a question with its bank entry, version and CAT item.
 *
 * @param string $name
 * @param string $text
 * @param int $categoryid
 * @param int $scaleid
 * @param int $contextid
 * @param ?array $param
 * @return int The question id.
 */
function catquiz_seed_question(
    string $name,
    string $text,
    int $categoryid,
    int $scaleid,
    int $contextid,
    ?array $param = ['model' => 'rasch', 'difficulty' => 0.5]
): int {
    global $DB;

    $now = time();

    $questionid = (int) $DB->insert_record('question', (object) [
        'name' => $name,
        'questiontext' => $text,
        'questiontextformat' => FORMAT_HTML,
        'qtype' => 'truefalse',
        'generalfeedback' => '',
        'generalfeedbackformat' => FORMAT_HTML,
        'timecreated' => $now,
        'timemodified' => $now,
        'createdby' => 2,
        'modifiedby' => 2,
    ]);

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

    $itemid = (int) $DB->insert_record('local_catquiz_items', (object) [
        'componentid' => $questionid,
        'componentname' => 'question',
        'catscaleid' => $scaleid,
        'contextid' => $contextid,
        'activeparamid' => 0,
        'status' => 0,
    ]);

    // No parameter at all: a classic pilot item.
    if ($param === null) {
        return $questionid;
    }

    $record = (object) array_merge([
        'itemid' => $itemid,
        'componentname' => 'question',
        'contextid' => $contextid,
        'status' => 4,
        'timecreated' => $now,
        'timemodified' => $now,
    ], $param);

    // Stamp through the same helper the plugin uses, so the fixture carries the
    // usability flag the application would have written.
    \local_catquiz\local\itemparam_validity::stamp($record);

    $paramid = (int) $DB->insert_record('local_catquiz_itemparams', $record);
    $DB->set_field('local_catquiz_items', 'activeparamid', $paramid, ['id' => $itemid]);

    return $questionid;
}

$matchingid = catquiz_seed_question(
    'Playwright matching question',
    '<p>This question body contains ' . CATQUIZ_SEED_MATCHING_TERM . ' as its marker.</p>',
    $categoryid,
    $scaleid,
    $contextid
);

// Two further questions without the marker, so a successful search has to reduce
// the list rather than simply return everything.
catquiz_seed_question(
    'Playwright other question one',
    '<p>An unrelated body.</p>',
    $categoryid,
    $scaleid,
    $contextid
);
catquiz_seed_question(
    'Playwright other question two',
    '<p>Another unrelated body.</p>',
    $categoryid,
    $scaleid,
    $contextid
);

// Issue #54: an item whose parameters exist but violate the contract of their
// model. A 2PL item with discrimination 0 is mathematically mute, so it is played
// as a pilot item - exactly the state the backend column has to make visible.
$unusableid = catquiz_seed_question(
    'Playwright unusable question',
    '<p>Parameters exist but cannot be used.</p>',
    $categoryid,
    $scaleid,
    $contextid,
    ['model' => 'raschbirnbaum', 'difficulty' => 0.5, 'discrimination' => 0.0]
);

// An item in piloting: registered for the scale, but without any active parameter.
$pilotid = catquiz_seed_question(
    'Playwright pilot question',
    '<p>No parameters at all.</p>',
    $categoryid,
    $scaleid,
    $contextid,
    null
);

// Issue #23: the charts are rendered by the [catquizstatistics] shortcode, not by
// the CAT manager, so a browser test needs a page that carries it. Without attempt
// data the shortcode would only ever show its "no data" state, which would make the
// test pass without exercising the aggregation at all.
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');

$course = $DB->get_record('course', ['shortname' => 'playwright-stats']);
if (!$course) {
    $course = create_course((object) [
        'fullname' => 'Playwright statistics course',
        'shortname' => 'playwright-stats',
        'category' => 1,
        'format' => 'topics',
    ]);
}

// The filter_shortcodes plugin must be active, otherwise the page shows the raw shortcode text.
filter_set_global_state('shortcodes', TEXTFILTER_ON);

$module = $DB->get_record('modules', ['name' => 'page'], '*', MUST_EXIST);
$pageinstance = $DB->get_record('page', ['course' => $course->id, 'name' => 'Playwright statistics']);
if (!$pageinstance) {
    $pageid = (int) $DB->insert_record('page', (object) [
        'course' => $course->id,
        'name' => 'Playwright statistics',
        'intro' => '',
        'introformat' => FORMAT_HTML,
        'content' => '[catquizstatistics globalscale=' . $scaleid . ']',
        'contentformat' => FORMAT_HTML,
        'display' => 0,
        'timemodified' => $now,
    ]);
    $newcm = (object) [
        'course' => $course->id,
        'module' => $module->id,
        'instance' => $pageid,
        'section' => 0,
        'visible' => 1,
        'visibleold' => 1,
        'added' => $now,
    ];
    $cmid = add_course_module($newcm);
    course_add_cm_to_section($course->id, $cmid, 0);
} else {
    // The page is created once, but the scale id changes whenever the fixtures are
    // reset. Keeping the content in step is part of being idempotent: a page that
    // points at a scale which no longer exists renders an error instead of charts,
    // and the test would report a product defect that is not one.
    $DB->set_field(
        'page',
        'content',
        '[catquizstatistics globalscale=' . $scaleid . ']',
        ['id' => $pageinstance->id]
    );
    $cm = get_coursemodule_from_instance('page', $pageinstance->id, $course->id, false, IGNORE_MISSING);
    $cmid = $cm ? (int) $cm->id : 0;
}

// The shortcode resolves the scale through a registered test; without one it reports
// "no tests can be found for the given arguments" and never reaches the charts.
//
// componentid must be a real adaptivequiz instance: get_heading() builds a link with
// get_course_and_cm_from_instance($test->componentid, 'adaptivequiz'), which fails
// with "Can't find data record in database" for anything else. Pointing it at the
// page's course module - the obvious shortcut - produced exactly that, and only in
// the course context, because without a course the heading takes a different branch.
//
// json->name is read as well; a payload without it throws on decoding.
if (!$DB->record_exists('local_catquiz_tests', ['catscaleid' => $scaleid])) {
    $aqmodule = $DB->get_record('modules', ['name' => 'adaptivequiz']);
    $aqinstanceid = 0;

    if ($aqmodule) {
        $aqinstanceid = (int) $DB->insert_record('adaptivequiz', (object) [
            'course' => $course->id,
            'name' => 'Playwright adaptive quiz',
            'intro' => '',
            'introformat' => FORMAT_HTML,
            // The attemptfeedback field is NOT NULL without a default - the same field that
            // breaks adaptivequiz_add_instance() in integration tests (engineering
            // guide, dependencies section). Omitting it aborts the seed.
            'attemptfeedback' => '',
            'attemptfeedbackformat' => FORMAT_HTML,
            'attempts' => 0,
            'highestlevel' => 100,
            'lowestlevel' => 1,
            'startinglevel' => 50,
            'stopingcondition' => 0,
            'minimumquestions' => 1,
            'maximumquestions' => 10,
            'standarderror' => 5.0,
            'showabilitymeasure' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $aqcm = (object) [
            'course' => $course->id,
            'module' => $aqmodule->id,
            'instance' => $aqinstanceid,
            'section' => 0,
            'visible' => 1,
            'visibleold' => 1,
            'added' => $now,
        ];
        $aqcmid = add_course_module($aqcm);
        course_add_cm_to_section($course->id, $aqcmid, 0);
    }

    $DB->insert_record('local_catquiz_tests', (object) [
        'componentid' => $aqinstanceid,
        'component' => 'mod_adaptivequiz',
        'catscaleid' => $scaleid,
        'contextid' => $contextid,
        'courseid' => $course->id,
        'name' => 'Playwright test',
        'description' => '',
        'descriptionformat' => FORMAT_HTML,
        'json' => json_encode(['name' => 'Playwright test', 'catscaleid' => $scaleid]),
        'status' => 1,
        'timecreated' => $now,
        'timemodified' => $now,
    ]);
}

// A handful of attempts with differing answer counts and abilities, so the histogram
// has something to classify rather than a single bar.
$existingattempts = $DB->count_records('local_catquiz_attempts', ['scaleid' => $scaleid]);
if (!$existingattempts) {
    for ($i = 0; $i < 12; $i++) {
        $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => 2,
            'scaleid' => $scaleid,
            'contextid' => $contextid,
            'courseid' => $course->id,
            'attemptid' => 900000 + $i,
            'component' => 'mod_adaptivequiz',
            'instanceid' => $cmid,
            'status' => 1,
            'timecreated' => $now - (86400 * $i),
            'timemodified' => $now,
            'endtime' => $now,
            'json' => json_encode(['attemptid' => 900000 + $i]),
        ]);
    }
}

catquiz_seed_export($CFG->wwwroot, $scaleid, $contextid, $matchingid, (int) $course->id, (int) $cmid);
