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
 * Downloads the timeline of an attempt, or the percentiles over all traces, as JSON (issue #136).
 *
 * For site administrators: on a hosted site without shell access this is the way to the trace.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_catquiz\local\monitoring\trace_report;

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$adaptiveattemptid = optional_param('attemptid', 0, PARAM_INT);
$days = optional_param('days', 0, PARAM_INT);

if ($adaptiveattemptid > 0) {
    $data = trace_report::for_attempt($adaptiveattemptid);
    $filename = 'catquiz_trace_attempt_' . $adaptiveattemptid . '.json';
} else {
    $data = trace_report::summary($days > 0 ? time() - $days * DAYSECS : 0);
    $filename = 'catquiz_trace_summary.json';
}

send_file(
    json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
    $filename,
    0,
    0,
    true,
    true,
    'application/json'
);
