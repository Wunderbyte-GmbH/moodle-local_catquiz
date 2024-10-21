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

use context_module;
use context_system;
use core_external\external_function_parameters;
use external_api;
use external_value;
use external_single_structure;
use local_catquiz\catquiz_test;
use local_catquiz\testenvironment;
use qbank_previewquestion\question_preview_options;
use question_bank;
use question_display_options;
use question_engine;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
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
            ]
        );
    }

    /**
     * Webservice for the local catquiz plugin to update context parameters
     *
     * @param int $slot
     * @param int $attemptid
     * @param string $label
     *
     * @return array
     */
    public static function execute(int $slot, int $attemptid): array {
        self::validate_parameters(self::execute_parameters(), [
            'slot' => $slot,
            'attemptid' => $attemptid,
        ]);

        $questionhtml = self::render_question($slot, $attemptid);

        return [
            'questionhtml' => $questionhtml['body'],
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
        ]);
    }

    private static function render_question(int $slot, int $attemptid): array {
        global $DB, $PAGE;
        $attempt = $DB->get_record('adaptivequiz_attempt', ['id' => $attemptid]);
        $instanceid = $attempt->instance;
        // Get the question settings for this quiz.
        $data = (object)['componentid' => $instanceid, 'component' => 'mod_adaptivequiz'];
        $testenvironment = new testenvironment($data);
        $testsettings = $testenvironment->return_settings();
        if (!$testsettings->catquiz_showquestion) {
            return ['body' => get_string('questionfeedbackdisabled', 'local_catquiz')];
        }

        require_login();
        $cm = get_coursemodule_from_instance('adaptivequiz', $instanceid);
        $context = context_module::instance($cm->id);
        $PAGE->set_context($context);
        // Get the question attempt.
        $uniqueid = $attempt->uniqueid;
        $quba = question_engine::load_questions_usage_by_activity($uniqueid);

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

        $html = $quba->render_question($slot, $displayoptions);

        return [
            'body' => $html,
        ];
    }
}
