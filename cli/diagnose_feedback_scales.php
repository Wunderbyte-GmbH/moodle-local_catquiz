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
 * Shows which scales survive each step on the way into the feedback table.
 *
 * The table "Details zu Ihrem Ergebnis" listed a single scale on a production site,
 * while several met the configured criteria. Reading the code did not settle it: the
 * path from the stored attempt to the table has no filter on `primary`, and a unit
 * test rebuilding the reported case keeps every valid scale. Either the scales are
 * lost earlier than assumed, or that installation runs different code.
 *
 * This prints the scale set after each step, so the step that drops them is named
 * rather than guessed.
 *
 * Usage:
 *   php local/catquiz/cli/diagnose_feedback_scales.php --attemptid=12357
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_catquiz\teststrategy\feedback_helper;
use local_catquiz\teststrategy\feedbacksettings;
use local_catquiz\teststrategy\progress;

[$options] = cli_get_params([
    'help' => false,
    'attemptid' => 0,
], ['h' => 'help']);

if ($options['help'] || empty($options['attemptid'])) {
    cli_writeln("Shows which scales survive each step into the feedback table.\n");
    cli_writeln('  --attemptid=N   The attemptid column of local_catquiz_attempts.');
    exit(0);
}

$attemptid = (int) $options['attemptid'];

$record = $DB->get_record('local_catquiz_attempts', ['attemptid' => $attemptid]);
if (!$record) {
    cli_error("No attempt with attemptid $attemptid.");
}

\core\session\manager::set_user(get_admin());

cli_writeln(sprintf(
    "Attempt %d, user %d, scale %d, context %d\n",
    $attemptid,
    $record->userid,
    $record->scaleid,
    $record->contextid
));

$data = json_decode($record->json, true);
if (!is_array($data)) {
    cli_error('The stored json could not be decoded.');
}

/**
 * Prints one step as a scale list with the flags that decide inclusion.
 *
 * @param string $label
 * @param array $abilities
 * @return void
 */
function show_step(string $label, array $abilities): void {
    cli_writeln(sprintf('--- %s: %d scales', $label, count($abilities)));

    foreach ($abilities as $scaleid => $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $flags = [];
        foreach (['primary', 'toreport', 'excluded', 'notreported', 'hidden'] as $flag) {
            if (!empty($entry[$flag])) {
                $flags[] = $flag;
            }
        }
        if (isset($entry['error']) && is_array($entry['error'])) {
            foreach (array_keys($entry['error']) as $reason) {
                $flags[] = 'error:' . $reason;
            }
        }

        cli_writeln(sprintf(
            '    %-6s value=%-8s se=%-8s %s',
            $scaleid,
            isset($entry['value']) ? round((float) $entry['value'], 2) : '-',
            isset($entry['se']) ? round((float) $entry['se'], 3) : '-',
            $flags === [] ? '(no flags)' : implode(' ', $flags)
        ));
    }
    cli_writeln('');
}

// Step 1: what the attempt stored. This is the population everything else narrows.
$updated = $data['updated_personabilities'] ?? [];
show_step('updated_personabilities (as stored)', $updated);

// Step 2: the per-scale reporting checkboxes from the quiz settings.
// progress::load() falls back to create_new() when nothing is cached or stored, and
// that constructor insists on quiz settings. An attempt whose progress row has been
// cleaned up would otherwise end this script with a TypeError instead of a diagnosis.
$quizsettings = null;
try {
    $progress = progress::load($attemptid, $record->component, (int) $record->contextid);
    $quizsettings = $progress->get_quiz_settings();
} catch (\Throwable $e) {
    cli_writeln('    (progress unavailable: ' . substr($e->getMessage(), 0, 80) . ')');
}

if (!$quizsettings instanceof stdClass) {
    // The per-scale reporting checkboxes live in the quiz settings. Without them the
    // step below cannot be reproduced, so it is skipped rather than faked.
    cli_writeln("--- filter_excluded_scales: skipped, no quiz settings available\n");
    $quizsettings = null;
}

$settings = new feedbacksettings((int) $record->teststrategy);
$afterexcluded = $updated;
if ($quizsettings !== null) {
    $afterexcluded = $settings->filter_excluded_scales($updated, $quizsettings);
    show_step('after filter_excluded_scales', $afterexcluded);
}

// Step 3: the strategy marks its primary scale. It returns every scale; if the count
// drops here, the strategy is removing entries rather than flagging them.
// get_teststrategy() returns false for an unknown id, not null - a strict null check
// lets that false through and method_exists() then fails on a boolean.
$strategy = \local_catquiz\teststrategy\info::get_teststrategy((int) $record->teststrategy);
$afterstrategy = $afterexcluded;
if (is_object($strategy) && method_exists($strategy, 'select_scales_for_report')) {
    try {
        $afterstrategy = $strategy->select_scales_for_report(
            $settings,
            $afterexcluded,
            $data,
            (int) $record->scaleid
        );
    } catch (\Throwable $e) {
        cli_writeln('    (strategy call failed: ' . substr($e->getMessage(), 0, 90) . ')');
    }
}
show_step('after select_scales_for_report', $afterstrategy);

// Step 4: the gate the feedback actually applies.
$result = feedback_helper::build_attempt_result($afterstrategy, $data);

cli_writeln('--- after is_feedback_eligible');
$eligible = [];
foreach (array_keys($afterstrategy) as $scaleid) {
    $ok = feedback_helper::is_feedback_eligible($result, (int) $scaleid);
    $scale = $result->get_scale_result((int) $scaleid);

    cli_writeln(sprintf(
        '    %-6s %-8s reasons=%s',
        $scaleid,
        $ok ? 'kept' : 'dropped',
        $scale === null ? '(no scale result)' : (implode(',', $scale->rejectionreasons) ?: '-')
    ));

    if ($ok) {
        $eligible[] = (int) $scaleid;
    }
}

cli_writeln(sprintf("\nEligible for the feedback table: %s", implode(', ', $eligible) ?: 'none'));

// Step 5: what the attempt actually stored as the table contents, for comparison.
$stored = array_keys($data['personabilities_abilities'] ?? []);
cli_writeln(sprintf('Stored in personabilities_abilities: %s', implode(', ', $stored) ?: 'none'));

cli_writeln("\nIf the two last lines differ, the code has changed since this attempt "
    . 'was made. If they agree and are shorter than the step above, the drop happens '
    . 'in the gate.');
