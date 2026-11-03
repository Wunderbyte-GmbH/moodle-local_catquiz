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
 * Audit of the completed attempts of a CAT context against issue #157. Read-only, aggregated, no person.
 *
 * Usage: php local/catquiz/cli/audit_context_pool_attempts.php --contextid=1605 [--scaleid=22] [--json]
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params(['contextid' => 0, 'scaleid' => 0, 'json' => false, 'help' => false], ['h' => 'help']);
if ($options['help'] || $unrecognised || empty($options['contextid'])) {
    cli_writeln('Which completed attempts of a CAT context may have been played with the pool of issue #157.');
    cli_writeln('  --contextid=ID   the CAT context (required)');
    cli_writeln('  --scaleid=ID     only attempts of this root scale');
    cli_writeln('  --json           JSON instead of text');
    exit($options['help'] ? 0 : 1);
}
$report = \local_catquiz\local\integrity\attempt_pool_audit::report((int) $options['contextid'], (int) $options['scaleid'] ?: null);
if ($options['json']) {
    cli_writeln(json_encode($report, JSON_PRETTY_PRINT));
} else {
    foreach ($report as $label => $count) {
        cli_writeln(sprintf('%-70s %d', $label, $count));
    }
}
