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

namespace local_catquiz\external;

use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_catquiz\local\access\feedback_access;
use local_catquiz\local\access\question_review;
use local_catquiz\local\statistics\attempt_population;
use local_catquiz\output\attemptfeedback;
use local_catquiz\teststrategy\feedbackgenerator;

/**
 * Renders one feedback tab of an attempt when it is opened (issue #85).
 *
 * The result page builds the main feedback and offers the other tabs empty; this fills one of them.
 * The shortcode catquizfeedback shows a collapsed attempt by its header; this fills it when it is opened
 * (generator "attempt"). Access as for the page: the owner, or a user who may see the feedback of others
 * - within their groups; teacher tabs need the teacher permission. A finished attempt is reviewed in the
 * course, so a hidden activity does not block it. Read only: nothing is written to the session.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class render_feedback_tab extends external_api {
    /** @var string Generator name that asks for the whole feedback of an attempt, not one tab. */
    public const WHOLE_ATTEMPT = 'attempt';

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid' => new external_value(PARAM_INT, 'Id of the attempt: adaptivequiz_attempt.id'),
            'generatorname' => new external_value(
                PARAM_ALPHANUMEXT,
                'Name of the feedback generator, or "attempt" for the whole feedback of a collapsed attempt'
            ),
            'audience' => new external_value(PARAM_ALPHA, 'student or teacher'),
        ]);
    }

    /**
     * Renders the tab.
     *
     * @param int $adaptiveattemptid Id of the attempt of the component: adaptivequiz_attempt.id.
     * @param string $generatorname
     * @param string $audience
     * @return array
     */
    public static function execute(int $adaptiveattemptid, string $generatorname, string $audience): array {
        global $DB, $OUTPUT, $PAGE;

        [
            'attemptid' => $adaptiveattemptid,
            'generatorname' => $generatorname,
            'audience' => $audience,
        ] = self::validate_parameters(self::execute_parameters(), [
            'attemptid' => $adaptiveattemptid,
            'generatorname' => $generatorname,
            'audience' => $audience,
        ]);

        $attempt = $DB->get_record('adaptivequiz_attempt', ['id' => $adaptiveattemptid], '*', MUST_EXIST);
        self::validate_context(question_review::login_context($attempt));
        $cm = get_coursemodule_from_instance('adaptivequiz', $attempt->instance, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        $PAGE->set_context($context);

        self::require_access($attempt, $context, $audience);

        // Charts add their JavaScript to the page requirements; collect it for the client to run.
        $PAGE->set_pagelayout('embedded');
        $OUTPUT->doctype();
        $PAGE->start_collecting_javascript_requirements();
        if ($generatorname === self::WHOLE_ATTEMPT) {
            $html = self::render_attempt($adaptiveattemptid);
            $javascript = $PAGE->requires->get_end_code();
            return [
                'html' => $html ?? get_string('attemptfeedbacknotavailable', 'local_catquiz'),
                'javascript' => $html === null ? '' : $javascript,
            ];
        }
        $feedback = (new attemptfeedback($adaptiveattemptid))->get_single_feedback($generatorname, $audience);
        $javascript = $PAGE->requires->get_end_code();

        return [
            'html' => $feedback['content'] ?? get_string('attemptfeedbacknotavailable', 'local_catquiz'),
            'javascript' => $feedback ? $javascript : '',
        ];
    }

    /**
     * The same rule as the result page (show_attemptfeedback.php): the owner; anybody else who may see
     * other users' results - a CAT manager anywhere, otherwise local/catquiz:view_users_feedback in the
     * activity - and, in separate groups, only for members of their groups (issue #85). Teacher tabs
     * need the teacher permission.
     *
     * @param \stdClass $attempt adaptivequiz_attempt record.
     * @param \context $context The context of the activity.
     * @param string $audience
     * @return void
     * @throws \required_capability_exception|\moodle_exception|\invalid_parameter_exception
     */
    private static function require_access(\stdClass $attempt, \context $context, string $audience): void {
        global $USER;

        if ((int) $attempt->userid !== (int) $USER->id) {
            // Review of issue #85: the endpoint required the capability in the activity and ignored the
            // CAT manager, who may open the result page of every attempt - and both ignored the groups.
            if (!feedback_access::can_view_other_users($context)) {
                throw new \required_capability_exception($context, 'local/catquiz:view_users_feedback', 'nopermissions', '');
            }
            if (!feedback_access::can_view_user_in_groups($context, (int) $attempt->userid)) {
                throw new \moodle_exception('error:noreviewpermission', 'local_catquiz');
            }
        }
        if ($audience === feedbackgenerator::AUDIENCE_TEACHER) {
            require_capability('local/catquiz:view_teacher_feedback', $context);
        } else if ($audience !== feedbackgenerator::AUDIENCE_STUDENT) {
            throw new \invalid_parameter_exception('audience');
        }
    }

    /**
     * The whole feedback of a completed attempt, as the result page shows it: the content of a collapsed
     * attempt of the shortcode catquizfeedback, opened later (issue #85).
     *
     * Built from the stored row, so nothing is written to the session.
     *
     * @param int $adaptiveattemptid
     * @return string|null Null when the attempt has no stored, completed result.
     */
    private static function render_attempt(int $adaptiveattemptid): ?string {
        global $DB, $OUTPUT;

        $record = $DB->get_record_select(
            'local_catquiz_attempts',
            'attemptid = :attemptid AND ' . attempt_population::completed_sql(''),
            ['attemptid' => $adaptiveattemptid],
            'id, attemptid, contextid, json, debug_info'
        );
        if (!$record) {
            return null;
        }
        $feedback = new attemptfeedback($adaptiveattemptid, (int) $record->contextid);
        $data = [
            'feedback' => $feedback->get_feedback_for_attempt($record->json, $record->debug_info),
            'attemptid' => $adaptiveattemptid,
        ];
        return $OUTPUT->render_from_template('local_catquiz/attemptfeedback', $data);
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'html' => new external_value(PARAM_RAW, 'The rendered content of the tab'),
            'javascript' => new external_value(PARAM_RAW, 'JavaScript the content needs'),
        ]);
    }
}
