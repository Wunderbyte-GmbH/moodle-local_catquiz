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
 * Entities Class to display list of entity records.
 *
 * @package local_catquiz
 * @author Georg Maißer
 * @copyright 2022 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use cache;
use cache_exception;
use cache_helper;
use cm_info;
use coding_exception;
use context_system;
use core_plugin_manager;
use Exception;
use local_catquiz\local\model\model_strategy;
use local_catquiz\output\attemptfeedback;
use local_catquiz\teststrategy\info;
use moodle_exception;
use MoodleQuickForm;
use stdClass;

/**
 * Class catquiz
 *
 * @author Georg Maißer
 * @copyright 2022 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catquiz_handler {

    /** @var int If this is selected, the catquiz engine is deactivated. */
    public const DEACTIVATED_MODEL = 0;
    /** @var int Rasch model to select next question to be displayed to user */
    public const RASCH_MODEL = 1;
    /** @var int Artificial intelligence model to select next question to be displayed to user */
    public const AI_MODEL = 2;

    /**
     * Constructor for catquiz.
     */
    public function __construct() {

    }

    /**
     * Create the form fields relevant to this plugin.
     *
     * @param MoodleQuickForm $mform
     * @return array
     */
    public static function instance_form_definition(MoodleQuickForm &$mform) {

        global $PAGE;

        $elements = [];

        // This function is for some architectural reason executed twice.
        // In order to avoid adding elements twice, we need this exit.
        if ($mform->elementExists('choosetemplate')) {
            return [];
        }

        $testtemplates = testenvironment::get_environments_as_array();

        // We introduce the option of a custom test environment.
        $testtemplates[0] = get_string('newcustomtest', 'local_catquiz');

        ksort($testtemplates);

        $elements[] = $mform->addElement(
            'select',
            'choosetemplate',
            get_string('choosetemplate',
            'local_catquiz'),
            $testtemplates,
            ['data-on-change-action' => 'reloadTestForm']);

        $mform->setType('choosetemplate', PARAM_INT);

        $context = context_system::instance();

        if (has_capability('local/catquiz:manage_testenvironments', $context)) {
            // If you have the right, you can define this setting as template.
            $elements[] = $mform->addElement(
                'advcheckbox',
                'testenvironment_addoredittemplate',
                get_string('addoredittemplate', 'local_catquiz'));
            $elements[] = $mform->addElement('text', 'testenvironment_name', get_string('name', 'core'));
            $mform->setType('testenvironment_name', PARAM_TEXT);
            $mform->hideIf('testenvironment_name', 'testenvironment_addoredittemplate', 'eq', 0);
        }

        // We have require JS to click no submit button on change of test environment.
        $PAGE->requires->js_call_amd('local_catquiz/catquizTestChooser', 'init');

        // We want to make sure the cat model section is always expanded.
        $mform->setExpanded('catmodelheading');

         // Button to attach JavaScript to to reload the form.
         $mform->registerNoSubmitButton('submitcattestoption');
         $elements[] = $mform->addElement('submit', 'submitcattestoption', 'cattestsubmit',
             ['class' => 'd-none', 'data-action' => 'submitCatTest']);

        // Add a special header for catquiz.
        $elements[] = $mform->addElement('header', 'catquiz_header',
                get_string('catquizsettings', 'local_catquiz'));
        $mform->setExpanded('catquiz_header');

        // Question categories or tags to use for this quiz.

        $catscales = \local_catquiz\data\dataapi::get_all_catscales();
        $options = [
            'multiple' => false,
            'noselectionstring' => get_string('allareas', 'search'),
        ];

        $select = [];
        foreach ($catscales as $catscale) {
            $select[$catscale->id] = $catscale->name;
        }
        $elements[] = $mform->addElement(
            'autocomplete',
            'catquiz_catcatscales',
            get_string('catcatscales', 'local_catquiz'), $select, $options);
        $mform->addHelpButton('catquiz_catcatscales', 'catcatscales', 'local_catquiz');

        $catcontexts = \local_catquiz\data\dataapi::get_all_catcontexts();
        $options = [
            'multiple' => false,
            'noselectionstring' => get_string('defaultcontextname', 'local_catquiz'),
        ];

        $select = [];
        foreach ($catcontexts as $catcontext) {
            $select[$catcontext->id] = $catcontext->getName();
        }
        $elements[] = $mform->addElement(
            'autocomplete',
            'catquiz_catcontext',
            get_string('selectcatcontext', 'local_catquiz'),
            $select,
            $options
        );

        $elements[] = $mform->addElement('text', 'catquiz_passinglevel', get_string('passinglevel', 'local_catquiz'));
        $mform->addHelpButton('catquiz_passinglevel', 'passinglevel', 'local_catquiz');
        $mform->setType('catquiz_passinglevel', PARAM_INT);

        // Is it a time paced test?
        $elements[] = $mform->addElement('advcheckbox', 'catquiz_timepacedtest',
                get_string('timepacedtest', 'local_catquiz'), null, null, [0, 1]);

        $elements[] = $mform->addElement('text', 'catquiz_maxtimeperitem', get_string('maxtimeperitem', 'local_catquiz'));
        $mform->setType('catquiz_maxtimeperitem', PARAM_INT);
        $mform->hideIf('catquiz_maxtimeperitem', 'catquiz_timepacedtest', 'neq', 1);

        $elements[] = $mform->addElement('text', 'catquiz_mintimeperitem', get_string('mintimeperitem', 'local_catquiz'));
        $mform->setType('catquiz_mintimeperitem', PARAM_INT);
        $mform->hideIf('catquiz_mintimeperitem', 'catquiz_timepacedtest', 'neq', 1);

        $timeoutoptions = [
            1 => get_string('timeoutfinishwithresult', 'local_catquiz'),
            2 => get_string('timeoutabortresult', 'local_catquiz'),
            3 => get_string('timeoutabortnoresult', 'local_catquiz'),
        ];
         // Choose a model for this instance.
         $elements[] = $mform->addElement('select', 'catquiz_actontimeout',
            get_string('actontimeout', 'local_catquiz'), $timeoutoptions);
        $mform->hideIf('catquiz_actontimeout', 'catquiz_timepacedtest', 'neq', 1);

        info::instance_form_definition($mform, $elements);

        return $elements;
    }

    /**
     * Set the data relvant to this plugin.
     *
     * @param stdClass $data
     * @return void
     */
    public static function instance_form_before_set_data(stdClass &$data) {
        global $DB;

        // Todo: We might rather use data_preprocessing.
    }

    /**
     * Undocumented function
     *
     * @param array $formdefaultvalues
     * @param MoodleQuickForm|null $mform
     * @return void
     */
    public static function data_preprocessing(array &$formdefaultvalues, MoodleQuickForm &$mform = null) {

        global $DB;

        if (!isset($formdefaultvalues['instance'])) {
            return;
        }

        $componentid = $formdefaultvalues['instance'];

        // We can hardcode this at this moment.
        $component = 'mod_adaptivequiz';

        if ($mform) {
            $data = $mform->getSubmitValues();
        }

        // The test environment is always on custom to start with.
        if (empty($data['choosetemplate'])) {

            // Create stdClass with all the values.
            $cattest = (object)[
                'componentid' => $componentid,
                'component' => $component,
            ];

            // Pass on the values as stdClas.
            $test = new testenvironment($cattest);
            $test->apply_jsonsaved_values($formdefaultvalues);
            $formdefaultvalues['choosetemplate'] = 0;
        } else {
            // Create stdClass with all the values.
            $cattest = (object)[
                'id' => $data['choosetemplate'],
            ];
            // Pass on the values as stdClas.
            $test = new testenvironment($cattest);
            $test->apply_jsonsaved_values($formdefaultvalues);
        }

        $formdefaultvalues['testenvironment_addoredittemplate'] = 0;
        unset($formdefaultvalues['testenvironment_name']);

        // Todo: Read json and set all the values.
    }

    /**
     * Undocumented function
     *
     * @param stdClass $data
     * @return void
     */
    public static function instance_form_definition_after_data(stdClass &$data) {

    }

    /**
     * Validate the submitted fields relevant to this plugin.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public static function instance_form_validation(array $data, array $files) {

        $errors = [];

        // Todo: Make a real validation of necessary fields.

        return $errors;
    }

    /**
     * Save submitted data relevant to this plugin.
     *
     * @param stdClass $data
     * @param int $instanceid
     * @param string $componentname
     * @return void
     */
    public static function instance_form_save(stdClass &$data, int $instanceid, string $componentname) {
        global $DB;

        $componentid = $data->instance;

        // We can hardcode this at this moment.
        $component = 'mod_adaptivequiz';

        if (!$catquiz = $DB->get_record('local_catquiz_tests',
            ['component' => $component, 'componentid' => $componentid])) {
            return;
        }
    }

    /**
     * Delete settings related to
     *
     * @param string $componentname
     * @param int $id
     *
     * @return void
     *
     */
    public static function delete_settings(string $componentname, int $id) {
        global $DB;
        $DB->delete_records('local_catquiz', ['componentname' => $componentname, 'componentid' => $id]);
    }

    /**
     * Check if this instance is setup to actually use catquiz.
     * When called by an external plugin, this must specify its signature.
     * Like "mod_adaptivequiz" and use its own id (not cmid, but instance id).
     *
     * @param int $quizid
     * @param string $component
     *
     * @return bool
     *
     */
    public static function use_catquiz(int $quizid, string $component) {

        // TODO: Implement fuctionality.

        return true;
    }

    /**
     * Class to be called from external plugin to save quiz data.
     *
     * @param stdClass $quizdata
     * @return void
     */
    public static function add_or_update_instance_callback(stdClass $quizdata) {

        $clone = clone($quizdata);

        // We unset id & instance. We don't want to introduce confusion because of it.
        unset($clone->id);
        unset($clone->instance);
        unset($clone->course);
        unset($clone->section);

        // If there is a new template name.
        if (!empty($quizdata->testenvironment_addoredittemplate) && !empty($quizdata->testenvironment_name)) {

            // If we have a template name, we first check if we come from an existing template.
            // Create stdClass with all the values.
            $cattest = (object)[
                'id' => $quizdata->choosetemplate, // When a template is selected, we might want to update it.
                'json' => json_encode($clone),
                'component' => 'mod_adaptivequiz',
                'catscaleid' => $clone->catquiz_catcatscales,
            ];

            $test = new testenvironment($cattest);
            // In this case, we want to add or update the template.
            $parentid = $test->save_or_update($quizdata->testenvironment_name);

            cache_helper::purge_by_event('changesintestenvironments');
        }

        // Create stdClass with all the values.
        $cattest = (object)[
             'componentid' => $quizdata->id,
            'component' => 'mod_adaptivequiz',
            'json' => json_encode($clone),
            'parentid' => $parentid ?? 0,
            'catscaleid' => $quizdata->catquiz_catcatscales,
            'courseid' => $quizdata->course,
        ];

         // Pass on the values as stdClas.
        $test = new testenvironment($cattest);
        // Save the values in the DB.
        $test->save_or_update();

        cache_helper::purge_by_event('changesintestenvironments');
    }

    /**
     * We use this function to apply eg template data.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public static function set_data_after_definition(MoodleQuickForm &$mform) {

        $values = $mform->getSubmitValues();

        if (empty($values['choosetemplate'])) {
            return;
        }

        $cattest = (object)[
            'id' => $values['choosetemplate'],
        ];
        // Pass on the values as stdClas.
        $test = new testenvironment($cattest);
        $test->apply_jsonsaved_values($values);

        $overridevalues = [
            'testenvironment_addoredittemplate' => '0',
        ];

        $igonorevalues = [
            'choosetemplate',
        ];

        foreach ($values as $k => $v) {

            if (isset($overridevalues[$k])) {
                $v = $overridevalues[$k];
            }

            if (in_array($k, $igonorevalues)) {
                continue;
            }

            if ($mform->elementExists($k)) {
                $element = $mform->getElement($k);
                $element->setValue($v);
                if ($test->status_force() && $k !== 'choosetemplate') {
                    $element->freeze();
                }
            }
        }
    }

    /**
     * Returns the ID of the next question
     *
     * This is called by adaptive quiz
     * @param int $cmid // Cmid of quiz instance.
     * @param string $component // like mod_adaptivequiz
     * @param stdClass $attemptdata
     * @return array
     */
    public static function fetch_question_id(int $cmid, string $component, stdClass $attemptdata): array {

        $data = (object)['componentid' => $cmid, 'component' => $component];

        $testenvironment = new testenvironment($data);

        $quizsettings = $testenvironment->return_settings();
        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        $cache->set('quizsettings', $quizsettings);
        $cache->set('attemptdata', $attemptdata);

        $tsinfo = new info();
        $teststrategy = $tsinfo
            ->return_active_strategy($quizsettings->catquiz_selectteststrategy)
            ->set_scale($quizsettings->catquiz_catcatscales)
            ->set_catcontextid($quizsettings->catquiz_catcontext);

        $selectioncontext = self::get_strategy_selectcontext($quizsettings, $attemptdata);
        $result = $teststrategy->return_next_testitem($selectioncontext);
        if (!$result->isOk()) {
            return [0, $result->getErrorMessage()];
        }

        $question = $result->unwrap();
        return [$question->id, ""];
    }

    /**
     * Purges the questions cache
     *
     * @return void
     * @throws coding_exception
     * @throws cache_exception
     */
    public static function prepare_attempt_caches() {
        global $USER;
        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        $cache->purge();
        $cache->set('isfirstquestionofattempt', true);
        $cache->set('userresponses', [$USER->id => []]);
        $cache->set('starttime', time());
    }

    /**
     * Attempt feedback.
     *
     * @param stdClass $adaptivequiz
     * @param cm_info $cm
     * @param stdClass $attemptrecord
     *
     * @return string
     *
     */
    public static function attemptfeedback(
        stdClass $adaptivequiz,
        cm_info $cm,
        stdClass $attemptrecord): string {
        global $OUTPUT;
        $contextid = optional_param('context', 0, PARAM_INT);

        $attemptfeedback = new attemptfeedback($attemptrecord->id, $contextid);
        $data = $attemptfeedback->export_for_template($OUTPUT);

        // We need to delete caches.
        cache_helper::purge_by_event('changesinquizattempts');

        return $OUTPUT->render_from_template('local_catquiz/attemptfeedback', $data);
    }

    /**
     * Gets data required by the preselect_task middleware classes.
     *
     * Don't confuse with the context from local_catquiz_catcontext table.
     * This context contains data that are required by the preselect_task
     * middleware classes.
     *
     * @param stdClass $quizsettings
     * @param stdClass $attemptdata
     */
    private static function get_strategy_selectcontext(stdClass $quizsettings, stdClass $attemptdata) {
        global $USER;
        $contextcreator = info::get_contextcreator();

        if ($quizsettings->catquiz_includepilotquestions) {
            $pilotratio = floatval($quizsettings->catquiz_pilotratio);
        }

        $maxquestionsperscale = intval($quizsettings->catquiz_maxquestionspersubscale);
        if ($maxquestionsperscale == 0) {
            $maxquestionsperscale = INF;
        }

        $maxquestions = $quizsettings->catquiz_maxquestions;
        if ($maxquestions == 0) {
            $maxquestions = INF;
        }

        $initialcontext = [
            'testid' => intval($attemptdata->instance),
            'contextid' => intval($quizsettings->catquiz_catcontext),
            'catscaleid' => $quizsettings->catquiz_catcatscales,
            'installed_models' => model_strategy::get_installed_models(),
            // When selecting questions from a scale, also include questions from its subscales.
            // This option is required by the questions_loader context loader.
            'includesubscales' => true,
            'maximumquestions' => $maxquestions,
            'minimumquestions' => $quizsettings->catquiz_minquestions,
            'penalty_threshold' => 60 * 60 * 24 * 30 - 90, // TODO: make dynamic.
            /*
                 * After this time, the penalty for a question goes back to 0
                 * Currently, it is set to 30 days
                 */
            'penalty_time_range' => 60 * 60 * 24 * 30,
            'pilot_ratio' => $pilotratio ?? 0,
            'pilot_attempts_threshold' => intval($quizsettings->catquiz_pilotattemptsthreshold),
            'questionsattempted' => intval($attemptdata->questionsattempted),
            'selectfirstquestion' => $quizsettings->catquiz_selectfirstquestion,
            'skip_reason' => null,
            'userid' => $USER->id,
            'max_attempts_per_scale' => $maxquestionsperscale,
            'min_attempts_per_scale' => $quizsettings->catquiz_minquestionspersubscale,
            'teststrategy' => $quizsettings->catquiz_selectteststrategy,
            'timestamp' => time(),
            'attemptid' => intval($attemptdata->id),
            'updateabilityfallback' => false,
            'excludedsubscales' => [],
            'has_fisherinformation' => false,
            'standarderrorpersubscale' => empty($quizsettings->catquiz_standarderrorpersubscale)
                ? null
                : ($quizsettings->catquiz_standarderrorpersubscale / 100),
            // phpcs:disable
            // 'breakduration' => $quizsettings->catquiz_breakduration,
            // 'breakinfourl' => '/local/catquiz/breakinfo.php',
            // 'maxtimeperquestion' => $quizsettings->catquiz_maxtimeperquestion,
            // phpcs:enable
        ];
        return $contextcreator->load(
            [
                'lastquestion',
                'person_ability',
                'contextid',
                'questions',
                'pilot_questions',
            ],
            $initialcontext
        );
    }
}
