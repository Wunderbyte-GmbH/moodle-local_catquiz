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
 * This class contains a webservice to trigger a tab-changed event.
 *
 * @package    local_catquiz
 * @copyright  2024 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_catquiz\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use local_catquiz\event\feedbacktab_clicked;
use local_catquiz\local\access\context_resolver;
use moodle_exception;


/**
 * External Service for local catquiz.
 *
 * @package   local_catquiz
 * @copyright 2024 Wunderbyte GmbH {@link http://www.wunderbyte.at}
 * @author    David Szkiba
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedback_tab_clicked extends external_api {
    /**
     * Describes the parameters for update_parameters webservice.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid'  => new external_value(PARAM_INT, 'attemptid'),
            'feedback' => new external_value(PARAM_TEXT, 'feedback'),
            'feedbacktranslated' => new external_value(PARAM_TEXT, 'feedbacktranslated'),
            ]);
    }

    /**
     * Webservice for the local catquiz plugin to update context parameters
     *
     * @param int $attemptid
     * @param string $feedback
     * @param string $translatedfeedback
     *
     * @return array
     */
    public static function execute(int $attemptid, string $feedback, string $translatedfeedback): array {
        global $DB, $USER;

        self::validate_parameters(self::execute_parameters(), [
            'attemptid' => $attemptid,
            'feedback' => $feedback,
            'feedbacktranslated' => $translatedfeedback,
        ]);

        // AJAX endpoints must resolve and validate the context of the
        // attempt they act on, so that they apply exactly the same rules as a
        // normal page request instead of judging everything in the system context.
        $ctx = context_resolver::for_attempt($attemptid);
        self::validate_context($ctx);

        // Review finding: every non-manager was logged as "student", so a teacher
        // looking at feedback appeared in the event log as the learner. The role is
        // now decided in the context of the attempt, which is where teaching rights
        // actually live - a site-wide manage right says nothing about a course.
        // The attempt has to exist before anyone is authorised for it.
        // Managers and teachers passed this point on capability alone, so an id that
        // matched no attempt still produced an event - a log entry about something
        // that never happened. Loading it first makes every role fail closed on an
        // unknown object.
        $attempt = $DB->get_record('local_catquiz_attempts', ['attemptid' => $attemptid]);
        if (!$attempt) {
            throw new moodle_exception('norighttoaccess', 'local_catquiz');
        }

        if (has_capability('local/catquiz:canmanage', \context_system::instance())) {
            $role = 'catmanager';
        } else if (has_capability('local/catquiz:view_teacher_feedback', $ctx)) {
            $role = 'teacher';
        } else {
            // Security: validate_context() establishes where the request acts, not
            // whether this user may act on this object. Without an ownership check
            // any authenticated user could raise events for someone else's attempt,
            // and every such caller was logged as that attempt's "student".
            //
            // Managers and teachers are already covered above; everyone else may only
            // act on their own attempt.
            if ((int) $attempt->userid !== (int) $USER->id) {
                throw new moodle_exception('norighttoaccess', 'local_catquiz');
            }
            $role = 'student';
        }

        // Security: the identifier and its translation both arrive from the client, and
        // both were written into the event log as if they described what happened.
        // The identifier is validated against the feedback generators that exist, so
        // the log records a known tab rather than whatever string was sent. The
        // translation stays as supplementary display text and is explicitly not the
        // authoritative record - a client-supplied label cannot be audit evidence.
        if (!self::is_known_feedback_generator($feedback)) {
            throw new moodle_exception('invalidfeedbackname', 'local_catquiz', '', $feedback);
        }

        $event = feedbacktab_clicked::create([
            'context' => $ctx,
            'other' => [
                'attemptid' => $attemptid,
                'feedback' => $feedback,
                'feedback_translated' => $translatedfeedback,
                'userid' => $USER->id,
                'role' => $role,
            ],
        ]);
        $event->trigger();

        return [
            'success' => false,
            'message' => '',
        ];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Successful calculation', VALUE_REQUIRED),
            'message' => new external_value(PARAM_RAW, 'message if necessary', VALUE_OPTIONAL, ''),
            ]);
    }
    /**
     * Whether the identifier names a feedback generator that exists.
     *
     * Derived from the generator classes rather than kept as a hand-written list: a
     * second copy of the same set drifts, and then the guard either rejects a valid
     * tab or accepts one that no longer exists.
     *
     * @param string $feedback
     * @return bool
     */
    private static function is_known_feedback_generator(string $feedback): bool {
        global $CFG;

        if ($feedback === '' || !preg_match('/^[a-z_]+$/', $feedback)) {
            return false;
        }

        return file_exists(
            $CFG->dirroot . '/local/catquiz/classes/teststrategy/feedbackgenerator/'
                . $feedback . '.php'
        );
    }
}
