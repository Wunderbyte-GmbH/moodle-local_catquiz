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
use local_catquiz\feedback\feedbackclass;
use local_catquiz\local\model\model_strategy;
use local_catquiz\output\attemptfeedback;
use local_catquiz\teststrategy\info;
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

        // Button to attach JavaScript to reload the form.
        $mform->registerNoSubmitButton('submitcattestoption');
        $elements[] = $mform->addElement('submit', 'submitcattestoption', 'cattestsubmit',
            [
            'class' => 'd-none',
            'data-action' => 'submitCatTest',
        ]);

        // Add a special header for catquiz.
        $elements[] = $mform->addElement('header', 'catquiz_header',
                get_string('catquizsettings', 'local_catquiz'));
        $mform->setExpanded('catquiz_header');

        // Question categories or tags to use for this quiz.

        // Parent Catscales have parentscaleid 0.
        $parentcatscales = \local_catquiz\data\dataapi::get_catscales_by_parent(0);
        $options = [
            'multiple' => false,
            'noselectionstring' => get_string('allareas', 'search'),
            'data-on-change-action' => 'reloadFormFromScaleSelect',
        ];

        $select = [];
        foreach ($parentcatscales as $catscale) {
            $select[$catscale->id] = $catscale->name;
        }
        $elements[] = $mform->addElement(
            'select',
            'catquiz_catscales',
            get_string('selectparentscale', 'local_catquiz'), $select, $options);
        $mform->addHelpButton('catquiz_catscales', 'catcatscales', 'local_catquiz');

        // We want to adjust our form depending on the nosubmit action which might just have taken place.
        // But we don't get the correct data, because these elements are added to the mform only...
        // ... after this function has finished execution. submitted form.
        // But the submitted via post, so we can access the variable via the superglobal $POST.

        $selectedparentscale = optional_param('catquiz_catscales', 0, PARAM_INT);

        if (!empty($selectedparentscale)) {
            $element = $mform->getElement('catquiz_catscales');
            $element->setValue($selectedparentscale);
            $subscales = \local_catquiz\data\dataapi::get_catscale_and_children($selectedparentscale, true);

            self::generate_subscale_checkboxes($subscales, $elements, $mform);
        } else {
            $selectedparentscale = reset($parentcatscales)->id ?? 0;
            $_POST['catquiz_catscales'] = $selectedparentscale;
            $subscales = \local_catquiz\data\dataapi::get_catscale_and_children($selectedparentscale, true);

            self::generate_subscale_checkboxes($subscales, $elements, $mform);
        }

        // Button to attach JavaScript to reload the form.
        $mform->registerNoSubmitButton('submitcatscaleoption');
        $elements[] = $mform->addElement('submit', 'submitcatscaleoption', 'catscalesubmit',
            [
            'class' => 'd-none',
            'data-action' => 'submitCatScale',
        ]);

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
     *  Generate recursive checkboxes for sub(-sub)scales.
     * @param array $subscales
     * @param array $elements
     * @param mixed $mform
     * @param string $elementadded
     * @param string $parentscalename
     *
     * @return void
     */
    public static function generate_subscale_checkboxes (
        array $subscales,
        array &$elements,
        $mform,
        string $elementadded = '',
        $parentscalename = '') {

        $data = $mform->getSubmitValues();

        // We don't need the parent scale.
        $parentscale = array_shift($subscales);

        if (empty($subscales)) {
            return;
        }

        foreach ($subscales as $subscale) {
            if (!isset($data['catquiz_subscalecheckbox_' . $subscale->id])
                && !isset($mform->_defaultValues['catquiz_subscalecheckbox_113'])) {
                $_POST['catquiz_subscalecheckbox_' . $subscale->id] = "1";
            }

            $parentscalechecked = optional_param('catquiz_subscalecheckbox_' . $subscale->parentid, 0, PARAM_INT);

            if (empty($parentscalechecked) && $subscale->parentid != $parentscale->id) {
                $_POST['catquiz_subscalecheckbox_' . $subscale->id] = "0";
                continue;
            }

            // For subsubscales add a sign to show nested structure.
            $elementadded = str_repeat('- ', $subscale->depth - 1);

            $scaleiddisplay = get_string('scaleiddisplay', 'local_catquiz', $subscale->id);

            $elements[] = $mform->addElement(
                'advcheckbox',
                'catquiz_subscalecheckbox_' . $subscale->id,
                $elementadded . $subscale->name . $scaleiddisplay,
                null,
                ['data-on-change-action' => 'reloadFormFromScaleSelect'],
                [0, 1]
            );
            $value = optional_param('catquiz_subscalecheckbox_' . $subscale->id, 0, PARAM_INT);
            $mform->setDefault('catquiz_subscalecheckbox_' . $subscale->id, $value);
        }
    }

    /**
     * Set the data relvant to this plugin.
     *
     * @param stdClass $data
     * @return void
     */
    public static function instance_form_before_set_data(stdClass &$data) {
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

        if (!isset($formdefaultvalues['instance'])) {
            return;
        }

        $componentid = $formdefaultvalues['instance'];

        // We can hardcode this at this moment.
        $component = 'mod_adaptivequiz';

        if ($mform) {
            $data = $mform->getSubmitValues();
        }

        // We have the following cases.

        // A) If load the first time, we load the json values from our custom environment.
        // We have no submitted values => we load new.
        // Post variable with json value.
        if (empty($data)) {
            // Create stdClass with all the values.
            $cattest = (object)[
                'componentid' => $componentid,
                'component' => $component,
            ];

            // Pass on the values as stdClass.
            $test = new testenvironment($cattest);
            $test->apply_jsonsaved_values($formdefaultvalues);

            self::write_variables_to_post($formdefaultvalues);

        } else if (isset($data['submitcattestoption'])
            && !empty($data['choosetemplate'])
            && ($data['submitcattestoption'] == "cattestsubmit")) {
            // B) If we have submitted a new testenvironment, we need to take this an load different json values.
            // cattestsubmit && choosetemplate not empty.
            // Post variable has to be set with json value.

            // Create stdClass with all the values.
            $cattest = (object)[
                'id' => $data['choosetemplate'],
            ];

            // Pass on the values as stdClas.
            $test = new testenvironment($cattest);
            $test->apply_jsonsaved_values($formdefaultvalues);

            self::write_variables_to_post($formdefaultvalues);
        }

        $formdefaultvalues['choosetemplate'] = 0;
        $formdefaultvalues['testenvironment_addoredittemplate'] = 0;
    }

    /**
     * Write values from formdefaultvalues into POST variable.
     *
     * @param array $values
     *
     */
    private static function write_variables_to_post(array $values) {

        // Boolean stands for exact match of name.
        $keystooverwrite = [
            'catquiz_catscales' => true,
            'catquiz_subscalecheckbox_' => false,
            'numberoffeedbackoptionsselect' => true,
            'feedbackeditor_scaleid_' => false,
        ];

        foreach ($values as $key => $value) {
            if (isset($keystooverwrite[$key])) {
                $_POST[$key] = $value;
            } else {
                foreach ($keystooverwrite as $kokey => $kovalue) {
                    if (!$kovalue && (strpos($key, $kokey) !== false)) {
                        if (gettype($value) === 'array') {
                            $_POST[$key] = json_encode($value);
                        } else {
                            $_POST[$key] = $value;
                        }
                    }
                }
            }
        }

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
                'catscaleid' => $clone->catquiz_catscales,
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
            'catscaleid' => $quizdata->catquiz_catscales,
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
     * This is the latest moment where we can change the values.
     * We can override submitted values here.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public static function set_data_after_definition(MoodleQuickForm &$mform) {

        $values = $mform->getSubmitValues();
        $keepselectedtemplate = false;

        // Check if button was triggered to copy values.
        foreach ($values as $key => $value) {
            if (strpos($key, 'copysettingsforallsubscales_') === 0) {
                $scaleidofcopyvalue = substr($key, strlen('copysettingsforallsubscales_'));
                $subscaleids = catscale::get_subscale_ids(intval($scaleidofcopyvalue));
            }

        };

        // If we just changed the number of the feedbackoptions.
        // We add the right values.
        if (isset($values["submitnumberoffeedbackoptions"])
            && $values["submitnumberoffeedbackoptions"] == "numberoffeedbackoptionssubmit") {

            // First, get the setting.
            $numberofoptions = $values['numberoffeedbackoptionsselect'];

            foreach ($values as $k => $v) {

                if (strpos($k, 'feedback_scaleid_limit_') !== false) {

                    if ($mform->elementExists($k)) {

                        preg_match('/_(\d+)$/', $k, $matches);
                        $j = $matches[1];

                        if (strpos($k, '_lower')) {
                            $value = feedbackclass::return_limits_for_scale($numberofoptions, $j, true);
                        } else {
                            $value = feedbackclass::return_limits_for_scale($numberofoptions, $j, false);
                        }

                        if ($mform->elementExists($k)) {
                            $element = $mform->getElement($k);
                            $element->setValue($value);
                        }
                    }
                }
            }
            return;
        } else if (isset($scaleidofcopyvalue) && !empty($subscaleids)) {
            // Copy values from a parent scale to all other subscale elements.
            $numberoffeedbackoptions = intval($values['numberoffeedbackoptionsselect']);
            $standardvalues = [];
            $feedbackvaluekeys = [
                'feedback_scaleid_limit_lower_',
                'feedback_scaleid_limit_upper_',
                'wb_colourpicker_',
                'feedbackeditor_scaleid_',
                'catquiz_groups_',
                'catquiz_courses_',
                'enrolement_message_checkbox_',
                'feedbacklegend_scaleid_',
            ];
            // Fetch standard values from the parentscale, we want to apply to all subscales.
            for ($j = 1; $j <= $numberoffeedbackoptions; $j++) {
                foreach ($feedbackvaluekeys as $feedbackvaluekey) {
                    if (!isset($standardvalues[$feedbackvaluekey])) {
                        $standardvalues[$feedbackvaluekey] = [];
                    }
                    $keyname = $feedbackvaluekey . $scaleidofcopyvalue . '_' . $j;
                    $standardvalues[$feedbackvaluekey][$j] = $values[$keyname] ?? null;
                }
            }

            // Check foreach subscale if it's selected.
            // Get the ids of all scales to apply feedback to.
            foreach ($subscaleids as $k => $subscaleid) {
                $key = 'catquiz_subscalecheckbox_' . $subscaleid;
                if (!isset($values[$key]) || $values[$key] == "0") {
                    unset($subscaleids[$k]);
                }
            }

            // Apply standard values to all subscales.
            // For all keys (in array) with all subscales (in array) for required number of feedbackoptions.
            foreach ($feedbackvaluekeys as $feedbackvaluekey) {
                foreach ($subscaleids as $subscaleid) {
                    for ($j = 1; $j <= $numberoffeedbackoptions; $j++) {
                        $subscalekey = $feedbackvaluekey . $subscaleid . '_' . $j;
                        $values[$subscalekey] = $standardvalues[$feedbackvaluekey][$j];
                    }
                }
            }

            // Make sure the counter in the headerelement is corrected.
            if ($mform->elementExists('header_accordion_start_scale_' . $scaleidofcopyvalue)) {
                $parentheader = $mform->getElement('header_accordion_start_scale_' . $scaleidofcopyvalue);
                $pht = $parentheader->_text;
                $parentscalename = catscale::return_catscale_object($scaleidofcopyvalue)->name;
                // $pht = strip_tags($parentheader->_text);
                // $pheadertxt = trim($pht);
                foreach ($subscaleids as $subscaleid) {
                    if ($mform->elementExists('header_accordion_start_scale_' . $subscaleid)) {
                        $element = $mform->getElement('header_accordion_start_scale_' . $subscaleid);
                        $subscalename = catscale::return_catscale_object($subscaleid)->name;
                        $newtext = str_replace($parentscalename, $subscalename, $pht);
                        $element->_text = $newtext;
                    }
                }
            }
            // In this case, we keep the selected template.
            $keepselectedtemplate = true;
        } else if (!isset($values["submitcattestoption"])
        || $values["submitcattestoption"] != "cattestsubmit") {
            return;
        }

        $cattest = (object)[
            'id' => $values['choosetemplate'],
        ];
        // Pass on the values as stdClass.
        $test = new testenvironment($cattest);
        $test->apply_jsonsaved_values($values);

        if ($keepselectedtemplate === false) {
            // We only want to unset the values when we change the template.
            $overridevalues = [
                'testenvironment_addoredittemplate' => '0',
            ];
            $igonorevalues = [
                'choosetemplate',
            ];
        } else {
            $overridevalues = [];
            $igonorevalues = [];
        }

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

        $catcontext = catscale::get_context_id($quizsettings->catquiz_catscales);
        $tsinfo = new info();
        $teststrategy = $tsinfo
            ->return_active_strategy($quizsettings->catquiz_selectteststrategy)
            ->set_scale($quizsettings->catquiz_catscales)
            ->set_catcontextid($catcontext);

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
        global $OUTPUT, $COURSE;
        $contextid = optional_param('context', 0, PARAM_INT);

        $attemptfeedback = new attemptfeedback($attemptrecord->id, $contextid, null, $COURSE->id);
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

        // Default is infinite represented by -1.
        $maxquestionsperscale = intval($quizsettings->catquiz_maxquestionspersubscale);
        if ($maxquestionsperscale == 0) {
            $maxquestionsperscale = -1;
        }

        $maxquestions = $quizsettings->catquiz_maxquestions;
        if (!$maxquestions) {
            $maxquestions = -1;
        }

        // Get selected subscales from quizdata.
        $selectedsubscales = self::get_selected_subscales($quizsettings);

        $catcontext = catscale::get_context_id($quizsettings->catquiz_catscales);
        $initialcontext = [
            'testid' => intval($attemptdata->instance),
            'contextid' => $catcontext,
            'catscaleid' => $quizsettings->catquiz_catscales,
            'installed_models' => model_strategy::get_installed_models(),
            // When selecting questions from a scale, also include questions from its subscales.
            // This option is required by the questions_loader context loader.
            'includesubscales' => true,
            'selectedsubscales' => $selectedsubscales,
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

    /**
     * Gets selected subscales
     *
     *
     * @param stdClass $quizsettings
     * @return array
     *
     */
    public static function get_selected_subscales(stdClass $quizsettings) {
        // Get selected subscales from quizdata.
        $selectedsubscales = [];
        foreach ($quizsettings as $key => $value) {
            if (strpos($key, 'catquiz_subscalecheckbox_') !== false
                && $value == "1") {
                    $catscaleid = substr_replace($key, '', 0, 25);
                    $selectedsubscales[] = $catscaleid;
            }
        };
        return $selectedsubscales;
    }
}
