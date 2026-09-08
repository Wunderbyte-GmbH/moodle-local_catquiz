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
 * This class contains a list of webservice functions related to the catquiz Module by Wunderbyte.
 *
 * @package    local_catquiz
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_catquiz\external;

use coding_exception;
use context_module;
use context_system;
use core_external\external_function_parameters;
use dml_exception;
use core_external\external_api;
use core_external\external_value;
use core_external\external_single_structure;
use local_catquiz\testenvironment;
use moodle_exception;
use question_display_options;
use question_engine;
use require_login_exception;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/questionlib.php');

/**
 * External Service for local catquiz.
 *
 * @package   local_catquiz
 * @copyright 2024 Wunderbyte GmbH {@link http://www.wunderbyte.at}
 * @author    David Szkiba
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class render_question_with_response extends external_api {
    /**
     * Describes the parameters for update_parameters webservice.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'slot'  => new external_value(PARAM_INT, 'Slot'),
            'attemptid'  => new external_value(PARAM_INT, 'Attempt ID'),
            'questionattemptid' => new external_value(
                PARAM_INT,
                'Question attempt id used to verify the slot maps to the expected question',
                VALUE_DEFAULT,
                0
            ),
        ]);
    }

    /**
     * Webservice for the local catquiz plugin to update context parameters
     *
     * @param int $slot
     * @param int $attemptid
     * @param int $questionattemptid Optional question attempt id to verify the slot mapping.
     *
     * @return array
     */
    public static function execute(int $slot, int $attemptid, int $questionattemptid = 0): array {
        global $PAGE, $OUTPUT;
        self::validate_parameters(self::execute_parameters(), [
            'slot' => $slot,
            'attemptid' => $attemptid,
            'questionattemptid' => $questionattemptid,
        ]);

        require_login();
        $PAGE->set_context(context_system::instance());
        $PAGE->set_url('/local/catquiz/external/render_question_with_response.php');

        // The renderer has to be initialised before the question is rendered, but
        // $OUTPUT->header() actually STARTS the output. Anything the question
        // rendering does afterwards that touches the page - most notably
        // $PAGE->add_body_class(), which several question types and the question
        // engine call - then dies with "Cannot call moodle_page::add_body_class
        // after output has been started". Force the theme/renderer to initialise
        // without emitting anything instead.
        $PAGE->set_pagelayout('embedded');
        $OUTPUT->doctype();

        $PAGE->start_collecting_javascript_requirements();
        $questionhtml = self::render_question($slot, $attemptid, $questionattemptid);
        $jsfooter = $PAGE->requires->get_end_code();

        return [
            'questionhtml' => $questionhtml['body'],
            'javascript' => $jsfooter,
        ];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'questionhtml' => new external_value(PARAM_RAW, 'The rendered question HTML'),
            'javascript' => new external_value(PARAM_RAW, 'The rendered question javascript'),
        ]);
    }

    /**
     * Returns an array with the rendered question HTML.
     *
     * @param int $slot
     * @param int $attemptid
     * @param int $questionattemptid Expected question attempt id (0 = skip check).
     * @return array
     * @throws dml_exception
     * @throws coding_exception
     * @throws require_login_exception
     * @throws moodle_exception
     */
    private static function render_question(int $slot, int $attemptid, int $questionattemptid = 0): array {
        global $DB, $PAGE, $USER;
        $attempt = $DB->get_record('adaptivequiz_attempt', ['id' => $attemptid], '*', MUST_EXIST);
        $instanceid = $attempt->instance;

        $cm = get_coursemodule_from_instance('adaptivequiz', $instanceid, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        // Moodle-compliant context validation (also enforces login for the context).
        self::validate_context($context);
        $PAGE->set_context($context);

        // Enforce access before revealing anything about the attempt.
        // An attempt may only be inspected by its owner or by a user with the
        // review capability; otherwise a participant could pass a foreign
        // attemptid and read another user's question and response.
        if ((int) $attempt->userid !== (int) $USER->id) {
            require_capability('local/catquiz:view_users_feedback', $context);
        }

        // Get the question settings for this quiz.
        $data = (object)['componentid' => $instanceid, 'component' => 'mod_adaptivequiz'];
        $testenvironment = new testenvironment($data);
        $testsettings = $testenvironment->return_settings();
        if (!$testsettings->catquiz_showquestion) {
            return ['body' => get_string('questionfeedbackdisabled', 'local_catquiz')];
        }

        // Get the question attempt.
        $uniqueid = $attempt->uniqueid;
        $quba = question_engine::load_questions_usage_by_activity($uniqueid);

        // Validate that the slot really exists in this usage and, when
        // a question attempt id is supplied, that the slot maps to exactly that
        // question attempt. This replaces the previous reliance on a slot that was
        // reconstructed from a table row index.
        try {
            $qa = $quba->get_question_attempt($slot);
        } catch (\moodle_exception $e) {
            throw new moodle_exception('invalidquestionslot', 'local_catquiz');
        }
        if ($questionattemptid > 0 && (int) $qa->get_database_id() !== $questionattemptid) {
            throw new moodle_exception('invalidquestionslot', 'local_catquiz');
        }

        // Render the question.
        $displayoptions = new question_display_options();
        $displayoptions->readonly = true; // Set to false if you want the question to be interactive.
        $displayoptions->marks = question_display_options::MARK_AND_MAX;
        // Show an indicator if the given response was correct or wrong.
        $showresponse = boolval($testsettings->catquiz_questionfeedbacksettings->catquiz_showquestionresponse)
            ? question_display_options::VISIBLE
            : question_display_options::HIDDEN;
        $showrightanswer = boolval($testsettings->catquiz_questionfeedbacksettings->catquiz_showquestioncorrectresponse)
            ? question_display_options::VISIBLE
            : question_display_options::HIDDEN;
        $showfeedback = boolval($testsettings->catquiz_questionfeedbacksettings->catquiz_showquestionfeedback)
            ? question_display_options::VISIBLE
            : question_display_options::HIDDEN;
        $displayoptions->correctness = $showresponse;
        $displayoptions->rightanswer = $showrightanswer;
        $displayoptions->generalfeedback = $showfeedback;
        $displayoptions->feedback = $showfeedback;

        // Emit the QUBA HTML unchanged. Running it through format_text
        // corrupts inputs, ids, JavaScript hooks and STACK structures. The head
        // html carries per-question CSS/JS (MathJax, STACK, ...) that the modal
        // needs; the question's own JavaScript is collected by the page
        // requirements in execute() (question_engine::initialise_js()).
        $headhtml = $quba->render_question_head_html($slot);
        $html = $quba->render_question($slot, $displayoptions);

        return [
            'body' => $headhtml . $html,
        ];
    }
}
