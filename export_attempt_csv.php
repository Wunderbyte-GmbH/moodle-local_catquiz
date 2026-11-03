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
 * CSV export of one attempt, valid result or not (issue #120).
 *
 * The export tab linked to export_feedback_csv.php, the statistics export, which needs a scale id
 * and stopped with a missing parameter. This is the export of the one attempt the tab belongs to.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_catquiz\local\access\context_resolver;
use local_catquiz\local\attempt\attempt_export;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/csvlib.class.php');

$adaptiveattemptid = required_param('attemptid', PARAM_INT);

require_login();

$DB->get_record('adaptivequiz_attempt', ['id' => $adaptiveattemptid], 'id', MUST_EXIST);
$context = context_resolver::for_attempt($adaptiveattemptid);
$PAGE->set_context($context);
// The same rule as the export tab: the teacher view of the attempt.
if (!attempt_export::can_export($adaptiveattemptid)) {
    throw new required_capability_exception($context, 'local/catquiz:view_teacher_feedback', 'nopermissions', '');
}

$csv = new csv_export_writer('semicolon');
$csv->set_filename(clean_filename('catquiz_attempt_' . $adaptiveattemptid));
$csv->add_data(attempt_export::COLUMNS);
foreach (attempt_export::rows($adaptiveattemptid) as $row) {
    $csv->add_data(array_values($row));
}
$csv->download_file();
