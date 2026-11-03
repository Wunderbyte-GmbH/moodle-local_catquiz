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

namespace local_catquiz\local\access;

use context;
use moodle_exception;
use require_login_exception;
use stdClass;

/**
 * Who may load the files embedded in the feedback texts of a test (filearea feedback_files_*).
 *
 * The files belong to the test's activity. They are shown on the result page, in the feedback of
 * the [catquizfeedback] shortcode and in the settings. The result page of a finished attempt opens
 * also when the activity is hidden (mod_adaptivequiz #16), so the course decides, not the activity:
 * logged in, and allowed into the course of the activity. A file outside an activity - a test
 * template - is for logged-in users who are no guests. pluginfile.php served the files to anybody
 * before.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class feedback_file_access {
    /**
     * Checks access to a feedback file and logs the user in.
     *
     * @param context $context The context of the file.
     * @param stdClass|null $course The course pluginfile.php resolved for the context.
     * @return void
     * @throws moodle_exception|require_login_exception When access is refused.
     */
    public static function require_access(context $context, ?stdClass $course): void {
        if ($context->contextlevel == CONTEXT_MODULE) {
            if (!$course) {
                throw new moodle_exception('invalidcourseid');
            }
            require_login($course, false);
            return;
        }
        if ($context->contextlevel == CONTEXT_SYSTEM) {
            require_login(null, false);
            if (isguestuser()) {
                throw new require_login_exception('Guests cannot load feedback files of templates.');
            }
            return;
        }
        throw new moodle_exception('invalidcontext');
    }
}
