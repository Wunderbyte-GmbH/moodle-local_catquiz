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
 * Test page for quiz attempt feedback
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH
 * @author     David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_catquiz\catquiz;
use local_catquiz\output\attemptfeedback;

require_once('../../config.php');

global $USER, $OUTPUT, $COURSE;
$context = \context_system::instance();
$PAGE->set_context($context);
require_login();
require_capability('local/catquiz:manage_catscales', $context);

$attemptid = optional_param('attemptid', 0, PARAM_INT);
$contextid = optional_param('contextid', 0, PARAM_INT);
$courseid = $COURSE->id;

$attemptfeedback = new attemptfeedback($attemptid, $contextid, null, $courseid);

$PAGE->set_url(new moodle_url('/local/catquiz/show_attemptfeedback.php', []));

echo $OUTPUT->header();

$data = $attemptfeedback->export_for_template($OUTPUT);
// Hier einhängen??

echo $OUTPUT->render_from_template('local_catquiz/attemptfeedback', $data);

echo $OUTPUT->footer();
