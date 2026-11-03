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
 * Lists the items of a CAT context whose active parameters do not resolve (issue #157). Read-only.
 *
 * Usage: php local/catquiz/cli/check_item_integrity.php --contextid=1605 [--scaleid=22] [--json]
 * With --scaleid, the scale and every subscale below it. Exit code 2 when an item is found.
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
    cli_writeln('Items of a CAT context whose active parameters do not resolve. Read-only.');
    cli_writeln('  --contextid=ID   the CAT context (required)');
    cli_writeln('  --scaleid=ID     only this scale and its subscales');
    cli_writeln('  --json           JSON instead of text');
    exit($options['help'] ? 0 : 1);
}
$scaleids = [];
if ($options['scaleid']) {
    $scaleids = [(int) $options['scaleid'], ...\local_catquiz\catscale::get_subscale_ids((int) $options['scaleid'])];
}
$result = \local_catquiz\local\integrity\item_param_integrity::check((int) $options['contextid'], $scaleids);
if ($options['json']) {
    cli_writeln(json_encode($result, JSON_PRETTY_PRINT));
} else {
    cli_writeln("Items with a reference that does not resolve: {$result['count']}");
    foreach ($result['bykind'] as $kind => $count) {
        cli_writeln(sprintf('  %-14s %d  items: %s', $kind, $count, implode(', ', $result['sample'][$kind])));
    }
    if ($scaleids) {
        cli_writeln("Items of the scales without a CAT context (in no pool): {$result['withoutcontext']}");
    }
}
exit($result['count'] > 0 ? 2 : 0);
