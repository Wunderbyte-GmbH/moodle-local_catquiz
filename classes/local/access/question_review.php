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
use context_course;
use context_module;
use local_catquiz\testenvironment;
use stdClass;

/**
 * The review of the questions of a finished attempt (mod_adaptivequiz issue #18).
 *
 * One rule for every way in - the result page, the question modal, the shortcode, the files of the
 * questions: a finished attempt is reviewed in the course, so an activity hidden or restricted
 * afterwards does not lock its owner out. A running attempt keeps the rules of the activity.
 * The owner sees the questions only when the test releases them (catquiz_showquestion); others need
 * local/catquiz:view_users_feedback.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class question_review {
    /** @var string State of a running attempt in mod_adaptivequiz. */
    private const IN_PROGRESS = 'inprogress';

    /**
     * Whether the test releases the questions of finished attempts to their owners.
     *
     * @param int $instanceid adaptivequiz.id
     * @return bool
     */
    public static function released_to_owner(int $instanceid): bool {
        $settings = (new testenvironment((object) ['componentid' => $instanceid, 'component' => 'mod_adaptivequiz']))
            ->return_settings();
        return !empty($settings->catquiz_showquestion);
    }

    /**
     * The context to log in to for the review of an attempt.
     *
     * A finished attempt: the course, so a hidden or restricted activity does not block the review -
     * logging in to the activity runs require_login() with it and fails once it is hidden. A running
     * one: the activity, with its visibility, as before.
     *
     * @param stdClass $attempt adaptivequiz_attempt record.
     * @return context
     */
    public static function login_context(stdClass $attempt): context {
        $cm = get_coursemodule_from_instance('adaptivequiz', $attempt->instance, 0, false, MUST_EXIST);
        if ($attempt->attemptstate === self::IN_PROGRESS) {
            return context_module::instance($cm->id);
        }
        return context_course::instance($cm->course);
    }
}
