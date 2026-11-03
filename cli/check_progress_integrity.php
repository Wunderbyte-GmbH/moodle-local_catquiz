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
 * Lists stored progress that does not belong to its CAT attempt (issue #96).
 *
 * Read-only: nothing is changed or deleted. Rows reported here are refused when a test tries to
 * load them; cleaning them up is a separate, deliberate step.
 *
 * Usage: php local/catquiz/cli/check_progress_integrity.php [--ids]
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_catquiz\local\attempt\progress_integrity;

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params(['ids' => false, 'help' => false], ['h' => 'help']);
if ($options['help'] || $unrecognised) {
    cli_writeln('Lists stored progress that does not belong to its CAT attempt. Read-only.');
    cli_writeln('  --ids     List every affected progress id, not only the ranges.');
    exit($unrecognised ? 1 : 0);
}

$labels = [
    progress_integrity::NO_CAT_ATTEMPT => 'Progress without its CAT attempt',
    progress_integrity::USER_MISMATCH => 'Progress of another person than its CAT attempt',
    progress_integrity::COMPONENT_MISMATCH => 'Progress of another component than its CAT attempt',
];
$total = 0;
foreach (progress_integrity::find_inconsistencies() as $reason => $ids) {
    $total += count($ids);
    $range = $ids ? sprintf(' (ids %d-%d)', min($ids), max($ids)) : '';
    cli_writeln(sprintf('%-52s %6d%s', $labels[$reason] . ':', count($ids), $range));
    if ($options['ids'] && $ids) {
        cli_writeln('    ' . implode(' ', $ids));
    }
}
cli_writeln('');
cli_writeln($total ? "$total progress row(s) would be refused when loaded." : 'No inconsistencies found.');
