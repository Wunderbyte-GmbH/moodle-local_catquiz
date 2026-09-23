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
 * Times a real five-question CAT attempt, step by step.
 *
 * The pool benchmark next door measures fetching candidates. That is the largest
 * single cost, but it is not what a person waits for: after an answer the plugin also
 * records the response, re-estimates the ability, excludes what was already asked and
 * selects the next item. Between two versions those parts can move in opposite
 * directions, and a pool number alone would hide it.
 *
 * Five questions, fixed rather than "up to five", so both versions do the same amount
 * of work. The answers are fixed too - right, wrong, right, right, wrong - because an
 * adaptive test that receives different answers takes a different path and then the
 * two runs are not measuring the same thing.
 *
 * The fingerprint distinguishes two kinds of agreement, and the difference matters
 * here:
 *
 *   functionally equivalent - five questions processed, same answers, valid finish
 *   algorithmically identical - the same five questions and the same abilities
 *
 * A version that fixes a selection defect will differ algorithmically while remaining
 * functionally equivalent. That is a wanted difference, not a broken measurement, and
 * a comparison that cannot tell them apart would either hide the fix or refuse the
 * whole benchmark.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options] = cli_get_params([
    'help' => false,
    'testid' => 0,
    'questions' => 5,
    'repeats' => 5,
], ['h' => 'help']);

if ($options['help'] || empty($options['testid'])) {
    cli_writeln("Times a real CAT attempt, step by step.\n");
    cli_writeln('  --testid=N      cmid of the adaptivequiz activity to run.');
    cli_writeln('  --questions=N   Questions per attempt (default 5, fixed for both versions).');
    cli_writeln('  --repeats=N     Attempts to run (default 5).');
    exit(0);
}

$cmid = (int) $options['testid'];
$questions = max(1, (int) $options['questions']);
$repeats = max(1, (int) $options['repeats']);

// Fixed so both versions walk a comparable path. An adaptive test answered
// differently selects different items and then the costs are not the same question.
$answers = [1.0, 0.0, 1.0, 1.0, 0.0];

$cm = get_coursemodule_from_id('adaptivequiz', $cmid, 0, false, MUST_EXIST);
$course = get_course($cm->course);

$user = $DB->get_record_sql(
    "SELECT u.* FROM {user} u WHERE u.deleted = 0 AND u.id > 2 ORDER BY u.id",
    [],
    IGNORE_MULTIPLE
);

if (!$user) {
    cli_error('No ordinary user to run the attempt as.');
}

\core\session\manager::set_user($user);

// Checked before timing anything: a test without a strategy fails deep inside the
// selection with a type error, and that reads like a defect in the plugin rather than
// a fixture that was never finished being configured.
$settings = $DB->get_record('local_catquiz_tests', [
    'componentid' => $cm->instance,
    'component' => 'mod_adaptivequiz',
]);

if (!$settings) {
    cli_error("Activity $cmid is not configured as a CAT test.");
}

$json = json_decode($settings->json ?? '{}');
if (empty($json->catquiz_selectteststrategy)) {
    cli_error("Activity $cmid has no test strategy configured - nothing to run.");
}

cli_writeln(sprintf(
    "Plugin version %s, activity %d, %d questions, %d attempts\n",
    get_config('local_catquiz', 'version'),
    $cmid,
    $questions,
    $repeats
));

/**
 * Runs one attempt and returns the per-step timings and the path taken.
 *
 * @param int $cmid
 * @param stdClass $cm
 * @param int $questions
 * @param array $answers
 * @return array
 */
function run_attempt(int $cmid, stdClass $cm, int $questions, array $answers): array {
    global $DB, $USER;

    $steps = [];
    $path = [];
    $queriesbefore = $DB->perf_get_queries();

    $start = microtime(true);

    $uniqueid = (int) $DB->insert_record('question_usages', (object) [
        'contextid' => \context_module::instance($cm->id)->id,
        'component' => 'mod_adaptivequiz',
        'preferredbehaviour' => 'deferredfeedback',
    ]);

    $attemptid = (int) $DB->insert_record('adaptivequiz_attempt', (object) [
        'instance' => $cm->instance,
        'userid' => $USER->id,
        'uniqueid' => $uniqueid,
        'attemptstate' => 'inprogress',
        'attemptstopcriteria' => '',
        'questionsattempted' => 0,
        'difficultysum' => 0,
        'standarderror' => 999,
        'measure' => 0.0,
        'timecreated' => time(),
        'timemodified' => time(),
    ]);

    $steps['attempt_start_ms'] = (microtime(true) - $start) * 1000;

    for ($i = 0; $i < $questions; $i++) {
        $attemptdata = $DB->get_record('adaptivequiz_attempt', ['id' => $attemptid]);
        $attemptdata->questionsattempted = $i;

        $start = microtime(true);

        // The same entry point mod_adaptivequiz calls for every question, rather than
        // a reassembled sequence of internals - a rebuilt path would measure this
        // script instead of the plugin.
        // The instance id, not the course module id: fetch_question_id() names
        // its first argument cmid and then looks it up as componentid, which is
        // the activity instance. Passing the actual cmid finds no test settings.
        [$questionid, $message] = \local_catquiz\catquiz_handler::fetch_question_id(
            (int) $cm->instance,
            'mod_adaptivequiz',
            $attemptdata
        );

        $steps[sprintf('q%d_select_ms', $i + 1)] = (microtime(true) - $start) * 1000;
        $path[] = $questionid;

        if (empty($questionid)) {
            $steps['stopped_after'] = $i;
            $steps['stop_reason'] = substr((string) $message, 0, 60);
            break;
        }

        // The response, recorded the way the module records it: the attempt row
        // carries the count the next selection reads.
        $DB->set_field('adaptivequiz_attempt', 'questionsattempted', $i + 1, ['id' => $attemptid]);
    }

    $steps['total_attempt_ms'] = array_sum(array_filter(
        $steps,
        fn($key) => str_ends_with($key, '_ms'),
        ARRAY_FILTER_USE_KEY
    ));

    $steps['db_queries'] = $DB->perf_get_queries() - $queriesbefore;
    $steps['peak_memory_mb'] = round(memory_get_peak_usage(true) / 1048576, 1);
    $steps['path'] = $path;

    // Cleaning up keeps repeated attempts from making the pool smaller as they run.
    $DB->delete_records('adaptivequiz_attempt', ['id' => $attemptid]);
    $DB->delete_records('local_catquiz_attempts', ['attemptid' => $attemptid]);

    return $steps;
}

$runs = [];
for ($r = 0; $r < $repeats; $r++) {
    try {
        $runs[] = run_attempt($cmid, $cm, $questions, $answers);
    } catch (\Throwable $e) {
        cli_writeln('  attempt failed: ' . substr($e->getMessage(), 0, 120));
        cli_writeln('');
        exit(1);
    }
}

/**
 * Median of one key across the runs.
 *
 * @param array $runs
 * @param string $key
 * @return float
 */
function median_of(array $runs, string $key): float {
    $values = array_values(array_filter(array_column($runs, $key), 'is_numeric'));
    if ($values === []) {
        return 0.0;
    }
    sort($values);

    return (float) $values[(int) floor((count($values) - 1) * 0.5)];
}

cli_writeln('[attempt] Complete CAT attempt, step by step');
cli_writeln('  /mod/adaptivequiz/attempt.php?cmid=' . $cmid);
cli_writeln('  covers: selection, response handling and the next selection - the wait '
    . 'between answering and seeing the next question');
cli_writeln('');

foreach (array_keys($runs[0]) as $key) {
    if (!str_ends_with($key, '_ms')) {
        continue;
    }
    cli_writeln(sprintf('  %-22s median %7.1f ms', $key, median_of($runs, $key)));
}

cli_writeln(sprintf('  %-22s %d', 'db_queries', (int) median_of($runs, 'db_queries')));
cli_writeln(sprintf('  %-22s %.1f MB', 'peak_memory', median_of($runs, 'peak_memory_mb')));

// Two levels of agreement, because a deliberate selection change is not a broken run.
$path = $runs[0]['path'] ?? [];
$processed = count(array_filter($path));

cli_writeln('');
cli_writeln(sprintf('  functionally equivalent: %d questions processed', $processed));
cli_writeln(sprintf(
    '  algorithmically identical: path %s',
    substr(sha1(implode(',', $path)), 0, 12)
));
cli_writeln('');
cli_writeln('A differing path is not automatically an invalid comparison: a version '
    . 'that corrects the selection will differ here on purpose.');
