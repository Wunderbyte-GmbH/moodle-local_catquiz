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
 * Explains the ability profile of [catquizstatistics] for one scale: who is counted, why the others are not, and where
 * the test information comes from (issue #150, re-audit 27.10.2026).
 *
 * Read-only and anonymised: counts, ids of scales, tests and CAT contexts, and the bars - no user, no name, no single
 * ability value. The bars are computed twice: from the raw rows here, and by the code of the statistics; both must
 * be the same.
 *
 * Usage:
 *   php local/catquiz/cli/diagnose_statistics.php --scaleid=22 [--courseid=..] [--testid=..] [--starttime=..]
 *       [--endtime=..] [--selection=all|first|last] [--json]
 *
 * --testid is the instance id of the test (adaptivequiz.id = local_catquiz_tests.componentid), as in the shortcode.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/local/catquiz/lib.php');

[$options, $unrecognised] = cli_get_params([
    'scaleid' => 0, 'courseid' => 0, 'testid' => 0, 'starttime' => null, 'endtime' => null, 'selection' => 'all',
    'json' => false, 'help' => false,
], ['h' => 'help']);
if ($options['help'] || $unrecognised || empty($options['scaleid'])) {
    cli_writeln('Explains the ability profile of [catquizstatistics] for a scale. Read-only, anonymised.');
    cli_writeln('  --scaleid=ID     the scale of the chart (required)');
    cli_writeln('  --courseid=ID    the course of the page, as the shortcode takes it');
    cli_writeln('  --testid=ID      the test: adaptivequiz.id (local_catquiz_tests.componentid)');
    cli_writeln('  --starttime=TS   --endtime=TS   the period of the end times (unix time)');
    cli_writeln('  --selection=all|first|last');
    cli_writeln('  --json           JSON instead of text');
    exit($options['help'] ? 0 : 1);
}

// The admin user: the statistics read the scales and pools as a manager does.
\core\session\manager::set_user(get_admin());

$report = \local_catquiz\local\statistics\statistics_diagnosis::report(
    (int) $options['scaleid'],
    (int) $options['courseid'] ?: null,
    (int) $options['testid'] ?: null,
    $options['starttime'] !== null ? (int) $options['starttime'] : null,
    $options['endtime'] !== null ? (int) $options['endtime'] : null,
    (string) $options['selection']
);

if ($options['json']) {
    cli_writeln(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    exit(0);
}
foreach ($report as $section => $content) {
    cli_heading($section);
    cli_writeln(json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
