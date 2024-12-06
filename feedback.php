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
 * Displays feedback for quiz attempts
 *
 * @package     local_catquiz
 * @copyright   2024 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_catquiz\output\feedback_page;

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Get URL parameters.
$courseid = optional_param('courseid', 0, PARAM_INT);
$instanceid = optional_param('instanceid', 0, PARAM_INT);
$numberofattempts = optional_param('numberofattempts', INF, PARAM_INT);
$attemptid = optional_param('attemptid', 0, PARAM_INT);

// Set up the page.
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/catquiz/feedback.php'));
$PAGE->set_title(get_string('feedback', 'local_catquiz'));
$PAGE->set_heading(get_string('feedback', 'local_catquiz'));

require_login();

// Create the page output.
$output = $PAGE->get_renderer('local_catquiz');
$page = new feedback_page([
    'courseid' => $courseid,
    'instanceid' => $instanceid,
    'numberofattempts' => $numberofattempts,
    'attemptid' => $attemptid,
]);

echo $OUTPUT->header();
echo $output->render($page);
echo $OUTPUT->footer();
