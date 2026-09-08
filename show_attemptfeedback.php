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
use local_catquiz\local\access\context_resolver;
use local_catquiz\local\access\feedback_access;
use local_catquiz\output\attemptfeedback;

require_once('../../config.php');

global $USER, $OUTPUT, $COURSE;

$attemptid = optional_param('attemptid', 0, PARAM_INT);
$contextid = optional_param('contextid', 0, PARAM_INT);

// Issue #18: this page used to set the system context and require
// local/catquiz:manage_catscales there. A teacher of the very course the attempt
// belongs to could therefore not review it, while the check said nothing about
// whether the attempt had anything to do with the person looking at it. Resolve
// the attempt's own context and judge access in it.
$context = context_resolver::for_attempt($attemptid);

// A module or course context also needs its course (and course module) on the
// page. Moodle's navigation builds the course tree from them; setting only the
// context leaves it without a course and it fails while rendering the header.
// require_login() with the course also enforces enrolment for the resolved course.
$course = null;
$cm = null;
if ($context->contextlevel == CONTEXT_MODULE) {
    // Both are needed, for different reasons: the course for require_login() below,
    // the course module for $PAGE. Dropping the module once looked like removing dead
    // code - it is not passed to require_login() - but the navigation reads it while
    // building the secondary nav and fails on null without it.
    [$course, $cm] = get_course_and_cm_from_cmid($context->instanceid);
} else if ($context->contextlevel == CONTEXT_COURSE) {
    $course = get_course($context->instanceid);
}

// Deliberately without the course module, even when one was resolved: passing $cm
// makes require_login() enforce $cm->uservisible, and that is false as soon as the
// activity - or the section holding it - becomes unavailable. A common setup hides
// the activity once the test is completed, and reviewing a finished attempt would
// then fail with "course or activity not available" precisely for the attempts that
// are worth reviewing.
//
// The course is still required, so enrolment is still enforced, and the permission
// check below is what actually guards this page.
if ($course) {
    require_login($course, false);
} else {
    require_login();
}
// Set explicitly rather than through require_login(): passing the module there would
// enforce $cm->uservisible and close this page for exactly the completed attempts it
// exists to show.
if (!empty($cm)) {
    $PAGE->set_cm($cm, $course);
}

$PAGE->set_context($context);

if (!feedback_access::can_view_other_users($context)) {
    throw new moodle_exception('error:noreviewpermission', 'local_catquiz');
}

$courseid = $course ? $course->id : $COURSE->id;

$attemptfeedback = new attemptfeedback($attemptid, $contextid, null, $courseid);

$PAGE->set_url(new moodle_url('/local/catquiz/show_attemptfeedback.php', ['attemptid' => $attemptid]));

echo $OUTPUT->header();

$data = $attemptfeedback->export_for_template($OUTPUT);

echo $OUTPUT->render_from_template('local_catquiz/attemptfeedback', $data);

echo $OUTPUT->footer();
