<?php
use local_catquiz\output\studentdetails;
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
 * Provides details about a student
 *
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH
 * @author     David Szkiba
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

global $USER;

$context = \context_system::instance();

$PAGE->set_context($context);
require_login();

require_capability('local/catquiz:manage_catscales', $context);

$PAGE->set_url('/local/catquiz/show_student.php');

$studentid = required_param('id', PARAM_INT);
$catscaleid = required_param('catscaleid', PARAM_INT);
$contextid = optional_param('contextid', 0, PARAM_INT);

$title = get_string('studentdetails', 'local_catquiz');
$PAGE->set_title($title);
$PAGE->set_heading($title);

echo $OUTPUT->header();

$data = new studentdetails($studentid);
$output = $PAGE->get_renderer('local_catquiz');
echo $output->render_studentdetails($data);

echo $OUTPUT->footer();
