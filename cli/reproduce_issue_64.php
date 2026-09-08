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
 * Reproduces issue #64 and prints the stage trace of every selection.
 *
 * The issue reports that an attempt ends after question 1: an answer is submitted and
 * no second question follows. The stage instrumentation is diagnostic infrastructure,
 * not a fix - what it has to deliver is the name of the stage that discards the last
 * candidate, with the pool size before and after it.
 *
 * The script drives the selection directly rather than through the web layer, so the
 * trace belongs to the selection and not to session handling or rendering.
 *
 * Usage:
 *   php cli/reproduce_issue_64.php --scaleid=N [--contextid=N] [--steps=5]
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params([
    'help' => false,
    'scaleid' => 0,
    'contextid' => 0,
    'steps' => 5,
    'strategy' => 0,
], ['h' => 'help']);

if ($options['help'] || empty($options['scaleid'])) {
    cli_writeln("Reproduces issue #64 - attempt ends after question 1.\n");
    cli_writeln('  --scaleid=N     Scale to run the selection on (required).');
    cli_writeln("  --contextid=N   Context; defaults to the scale's own.");
    cli_writeln('  --steps=N       Questions to request in sequence (default 5).');
    cli_writeln('  --strategy=N    Test strategy id; defaults to every known one.');
    exit(0);
}

$scaleid = (int) $options['scaleid'];
$contextid = (int) $options['contextid'];
$steps = max(2, (int) $options['steps']);

if (empty($contextid)) {
    $contextid = (int) $DB->get_field('local_catquiz_catscales', 'contextid', ['id' => $scaleid]);
}

\core\session\manager::set_user(get_admin());

/**
 * Prints one stage trace as a table.
 *
 * @param array $counts
 * @param array $timings
 * @return void
 */
function print_trace(array $counts, array $timings): void {
    if (empty($counts)) {
        cli_writeln('    (no stages recorded - the selection returned before the chain ran)');
        return;
    }

    $previous = null;
    cli_writeln(sprintf('    %-28s %10s %10s %10s', 'Stage', 'before', 'after', 'ms'));

    foreach ($counts as $stage => $after) {
        $lost = ($previous === null || $after === null) ? '' : ($previous - $after);
        cli_writeln(sprintf(
            '    %-28s %10s %10s %10s%s',
            $stage,
            $previous ?? '-',
            $after ?? '-',
            isset($timings[$stage]) ? number_format($timings[$stage], 1) : '-',
            ($lost !== '' && $lost > 0) ? "   (-$lost)" : ''
        ));
        $previous = $after;
    }
}

// The info class already hands back strategy instances, keyed by id.
// Resolving them again through get_teststrategy() returned false for keys that are
// array positions rather than strategy ids, and the run died on the first entry.
$available = \local_catquiz\teststrategy\info::return_available_strategies();

$strategies = [];
foreach ($available as $key => $instance) {
    if (!is_object($instance)) {
        continue;
    }
    $id = (int) ($instance->id ?? $key);
    if (!empty($options['strategy']) && $id !== (int) $options['strategy']) {
        continue;
    }
    $strategies[$id] = $instance;
}

cli_writeln(sprintf(
    "Scale %d, context %d, %s items in the pool\n",
    $scaleid,
    $contextid,
    number_format($DB->count_records('local_catquiz_items', ['contextid' => $contextid]), 0, ',', '.')
));

foreach ($strategies as $strategyid => $strategy) {
    cli_writeln(sprintf('=== Strategy %d (%s)', $strategyid, get_class($strategy)));

    // A fresh attempt per strategy: reusing one would hide a defect that only shows
    // on a clean start, which is exactly what the issue describes.
    $attemptid = random_int(900000, 999999);

    $quizsettings = (object) [
        'catquiz_catscales' => $scaleid,
        'maxquestionspertest' => $steps + 5,
        'minquestionspertest' => 1,
        'catquiz_includepilotquestions' => false,
        'catquiz_standarderror_min' => 0.1,
        'catquiz_standarderror_max' => 3.0,
        'catquiz_selectteststrategy' => $strategyid,
        'maxquestionsperscale' => 0,
        'minquestionspersubscale' => 0,
    ];

    // The settings go into load(): a progress created without them falls back to
    // defaults, and the run would then measure a configuration nobody uses.
    $progress = \local_catquiz\teststrategy\progress::load(
        $attemptid,
        'mod_adaptivequiz',
        $contextid,
        $quizsettings
    );

    // The keys mirror catquiz_handler::get_next_question(): a context assembled from
    // guesses would exercise a code path that never runs in production, and a trace
    // of that path says nothing about the reported defect.
    $context = [
        'testid' => 1,
        'contextid' => $contextid,
        'quizsettings' => $quizsettings,
        'catscaleid' => $scaleid,
        'installed_models' => \local_catquiz\local\model\model_strategy::get_installed_models(),
        'includesubscales' => true,
        'maximumquestions' => $steps + 5,
        'minimumquestions' => 1,
        'penalty_threshold' => 60 * 60 * 24 * 30,
        'initial_standarderror' => 1.0,
        'pilot_ratio' => 0,
        'pilot_attempts_threshold' => 10,
        'questionsattempted' => 0,
        'firstquestion_use_existing_data' => false,
        'selectfirstquestion' => null,
        'skip_reason' => null,
        'userid' => $USER->id,
        'max_attempts_per_scale' => 0,
        'progress' => $progress,
        'teststrategy' => $strategyid,
        'component' => 'mod_adaptivequiz',
        'attemptid' => $attemptid,
        'max_attempttime_in_sec' => 3600,
        'breakduration' => 0,
    ];

    for ($step = 1; $step <= $steps; $step++) {
        try {
            $result = $strategy->return_next_testitem($context);
        } catch (\Throwable $e) {
            cli_writeln(sprintf('  Step %d: exception %s', $step, substr($e->getMessage(), 0, 90)));

            // The location matters more than the message here: a context assembled by
            // hand fails in places production never reaches, and only the frame says
            // whether the defect is in the plugin or in this harness.
            foreach (array_slice($e->getTrace(), 0, 4) as $frame) {
                cli_writeln(sprintf(
                    '      %s:%s  %s',
                    basename($frame['file'] ?? '?'),
                    $frame['line'] ?? '?',
                    ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? '')
                ));
            }
            break;
        }

        $counts = method_exists($strategy, 'get_stage_counts') ? $strategy->get_stage_counts() : [];
        $timings = method_exists($strategy, 'get_stage_timings') ? $strategy->get_stage_timings() : [];

        if ($result->iserror()) {
            cli_writeln(sprintf('  Step %d: NO QUESTION - %s', $step, $result->get_status()));
            print_trace($counts, $timings);
            break;
        }

        $question = $result->unwrap();
        cli_writeln(sprintf('  Step %d: question %d selected', $step, $question->id ?? 0));
        print_trace($counts, $timings);
    }

    cli_writeln('');
}
