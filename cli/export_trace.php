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
 * Exports the timeline of an attempt, or percentiles over all traces (issue #136).
 *
 * Switch the trace on first: Site administration > Plugins > Local plugins > CAT Quiz,
 * "Trace attempt requests" (local_catquiz/attempttrace).
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_catquiz\local\monitoring\trace_report;

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options] = cli_get_params([
    'help' => false,
    'attemptid' => 0,
    'summary' => false,
    'days' => 0,
], ['h' => 'help']);

if ($options['help'] || (empty($options['attemptid']) && empty($options['summary']))) {
    cli_writeln("Exports attempt traces as JSON.\n");
    cli_writeln('  --attemptid=N   Every trace of the attempt N (adaptivequiz_attempt.id), span by span.');
    cli_writeln('  --summary       p50/p95/p99 of the request time and of every span, per kind of request.');
    cli_writeln('  --days=N        With --summary: only traces of the last N days.');
    exit(0);
}

if (!empty($options['attemptid'])) {
    $data = trace_report::for_attempt((int) $options['attemptid']);
} else {
    $since = (int) $options['days'] > 0 ? time() - (int) $options['days'] * DAYSECS : 0;
    $data = trace_report::summary($since);
}
cli_writeln(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
