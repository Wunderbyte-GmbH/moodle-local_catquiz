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
 * Judges finalised attempts again by today's validation rules (issue #141).
 *
 * Dry run by default: lists the verdict before and after. With --execute the new verdict is stored.
 * The grades follow only when the activity regrades the changed attempts - the script prints the
 * command for that.
 *
 * Usage: php local/catquiz/cli/revalidate_attempts.php (--attempts=1,2,3 | --instance=N) [--execute]
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_catquiz\local\attempt\attempt_revalidator;

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params(
    ['attempts' => '', 'instance' => 0, 'execute' => false, 'help' => false],
    ['h' => 'help']
);
if ($options['help'] || $unrecognised || ($options['attempts'] === '' && empty($options['instance']))) {
    cli_writeln('Judges finalised attempts again by the current validation rules. Dry run by default.');
    cli_writeln('  --attempts=1,2,3  Attempts of the activity (adaptivequiz_attempt.id).');
    cli_writeln('  --instance=N      All completed attempts of the activity N (adaptivequiz.id).');
    cli_writeln('  --execute         Store the new verdict.');
    exit($options['help'] ? 0 : 1);
}

if ($options['attempts'] !== '') {
    $ids = array_values(array_filter(array_map('intval', explode(',', $options['attempts']))));
    $instances = $DB->get_records_list('adaptivequiz_attempt', 'id', $ids, '', 'id, instance');
    $instanceids = array_unique(array_column($instances, 'instance'));
} else {
    $ids = array_keys($DB->get_records('adaptivequiz_attempt', [
        'instance' => (int) $options['instance'], 'attemptstate' => 'complete',
    ], 'id', 'id'));
    $instanceids = [(int) $options['instance']];
}

$verdict = fn(?bool $valid) => $valid === null ? '-' : ($valid ? 'valid' : 'invalid');
$changed = [];
foreach ($ids as $id) {
    $report = attempt_revalidator::revalidate($id, (bool) $options['execute']);
    if (!$report->reconstructable) {
        cli_writeln("attempt $id: not reconstructable ({$report->reason}), left as it is");
        continue;
    }
    $line = "attempt $id: {$verdict($report->before)} -> {$verdict($report->after)}";
    if ($report->reasons !== '') {
        $line .= " ({$report->reasons})";
    }
    cli_writeln($line . ($report->changed ? ' CHANGED' : ''));
    if ($report->changed) {
        $changed[] = $id;
    }
}

cli_writeln(count($changed) . ' of ' . count($ids) . ' attempt(s) ' . ($options['execute'] ? 'changed.' : 'would change.'));
if ($changed && $options['execute']) {
    cli_writeln('Grades follow after the regrade of the activity:');
    foreach ($instanceids as $instanceid) {
        cli_writeln("  php {$CFG->dirroot}/mod/adaptivequiz/cli/regrade_results.php --instance=$instanceid --resnapshot="
            . implode(',', $changed));
    }
}
