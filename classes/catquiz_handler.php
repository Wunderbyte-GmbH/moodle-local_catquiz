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
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use cache;
use cache_exception;
use cache_helper;
use cm_info;
use coding_exception;
use context_module;
use context_system;
use local_catquiz\feedback\feedbackclass;
use local_catquiz\local\model\model_strategy;
use local_catquiz\output\attemptfeedback;
use local_catquiz\teststrategy\info;
use local_catquiz\teststrategy\progress;
use MoodleQuickForm;
use stdClass;

/**
 * Class catquiz
 *
 * @author Georg Maißer
 * @copyright 2024 Wunderbyte GmbH
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

        global $DB, $PAGE;

        $elements = [];

        $testtemplates = testenvironment::get_environments_as_array(
            'mod_adaptivequiz',
            0,
            LOCAL_CATQUIZ_TESTENVIRONMENT_ONLYACTIVETEMPLATES);

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

        // Add a hidden element to store which button was clicked.
        $elements[] = $mform->addElement('hidden', 'triggered_button', '');
        $mform->setType('triggered_button', PARAM_ALPHANUMEXT);

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

        if ($selectedcontext = optional_param('contextid', 0, PARAM_INT)) {
            if ($name = $DB->get_field('local_catquiz_catcontext', 'name', ['id' => $selectedcontext])) {
                $elements[] = $mform->addElement(
                    'static',
                    'selectedcontext',
                    get_string('testcontext', 'local_catquiz'),
                    $name
                );
            }
        }

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

        $reloadtemplate = ($mform->getSubmitValues()['triggered_button'] ?? null) === "reloadTestForm";
        $template = null;
        if ($reloadtemplate && $chosentemplate = optional_param('choosetemplate', 0, PARAM_INT)) {
            // Get parent scale ID from template.
            $cattest = (object)[
                'id' => $chosentemplate,
                'component' => 'mod_adaptivequiz',
            ];

            // Pass on the values as stdClass.
            $template = new testenvironment($cattest);
            $selectedparentscale = $template->return_as_array()['catscaleid'];
        } else {
            $selectedparentscale = optional_param('catquiz_catscales', 0, PARAM_INT);
        }

        if (!empty($selectedparentscale)) {
            $element = $mform->getElement('catquiz_catscales');
            $element->setValue($selectedparentscale);
        } else {
            $selectedparentscale = reset($parentcatscales)->id ?? 0;
            $_POST['catquiz_catscales'] = $selectedparentscale;
        }
        $subscales = \local_catquiz\data\dataapi::get_catscale_and_children($selectedparentscale, true);
        self::generate_subscale_checkboxes($subscales, $elements, $mform);

        // Button to attach JavaScript to reload the form.
        $mform->registerNoSubmitButton('submitcatscaleoption');
        $elements[] = $mform->addElement('submit', 'submitcatscaleoption', get_string('applychanges', 'local_catquiz'),
            [
            'class' => 'hidden',
            'data-action' => 'submitCatScale',
        ]);

        info::instance_form_definition($mform, $elements, $template);

        return $elements;
    }

    /**
     *  Generate recursive checkboxes for sub(-sub)scales.
     * @param array $subscales
     * @param array $elements
     * @param mixed $mform
     * @param string $elementadded
     *
     * @return void
     */
    public static function generate_subscale_checkboxes(
        array $subscales,
        array &$elements,
        $mform,
        string $elementadded = '') {

        if (empty($subscales)) {
            return;
        }

        // We don't need the parent scale.
        $parentscale = array_shift($subscales);

        foreach ($subscales as $subscale) {
            $subscaledefined = optional_param('catquiz_subscalecheckbox_' . $subscale->id, -1, PARAM_INT);
            if ($subscaledefined === -1) {
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

            $dataarray = [
                'data-on-change-action' => 'reloadFormFromScaleSelect',
                'data-name' => $subscale->name,
                'data-depth' => $subscale->depth,
            ];

            $config = get_config('local_catquiz', 'automatic_reload_on_scale_selection');
            if (!$config) {
                $dataarray['data-manualreload'] = true;
            }
            $elements[] = $mform->addElement(
                'advcheckbox',
                'catquiz_subscalecheckbox_' . $subscale->id,
                $elementadded . $subscale->name . $scaleiddisplay,
                null,
                $dataarray,
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
    }

    /**
     * Undocumented function
     *
     * @param array $formdefaultvalues
     * @param MoodleQuickForm|null $mform
     * @return void
     */
    public static function data_preprocessing(array &$formdefaultvalues, ?MoodleQuickForm &$mform = null) {

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
            $_POST['contextid'] = $test->get_contextid() ?? 0;

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

        $context = context_system::instance();

        $options = [
            'trusttext' => true,
            'subdirs' => true,
            'context' => $context,
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'noclean' => true,
        ];

        foreach ($data as $property) {
            if (is_array($property) || !preg_match('/^feedbackeditor_scaleid_(\d+)_(\d+)_editor/', $property, $matches)) {
                continue;
            }
            $scaleid = intval($matches[1]);
            $rangeid = intval($matches[2]);
            $fieldname = sprintf('feedbackeditor_scaleid_%d_%d', $scaleid, $rangeid);
            $filearea = sprintf('feedback_files_%d_%d', $scaleid, $rangeid);
            $data = (object) file_prepare_standard_editor(
                $data,
                sprintf('feedbackeditor_scaleid_%d_%d', $scaleid, $rangeid),
                $options,
                $context,
                'local_catquiz',
                $filearea,
                intval($test->id)
            );
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
     * @return array
     */
    public static function instance_form_validation(array $data) {

        $errors = [];

        self::check_if_positive_int($errors, $data, "catquiz_minquestions", 'maxquestionsgroup');
        self::check_if_positive_int($errors, $data, "catquiz_maxquestions", 'maxquestionsgroup');

        self::check_if_positive_int($errors, $data, "catquiz_maxquestionspersubscale", 'maxquestionsscalegroup');
        self::check_if_positive_int($errors, $data, "catquiz_minquestionspersubscale", 'maxquestionsscalegroup');

        self::check_if_positive_int($errors, $data, "catquiz_maxtimeperattempt", 'catquiz_timelimitgroup');
        self::check_if_positive_int($errors, $data, "catquiz_maxtimeperitem", 'catquiz_timelimitgroup');

        if (isset($data['catquiz_pilotratio'])) {
            if (0 > (int) $data['catquiz_pilotratio'] || 100 < (int) $data['catquiz_pilotratio']) {
                $errors['catquiz_pilotratio'] = get_string('formelementwrongpercent', 'local_catquiz');
            }
        }

        // Standarderror- values should be positive float with min lower than max.
        $semin = false;
        $semax = false;
        if (isset($data['catquiz_standarderrorgroup']['catquiz_standarderror_min'])
            && $data['catquiz_standarderrorgroup']['catquiz_standarderror_min'] !== "") {
            if (!is_numeric($data['catquiz_standarderrorgroup']['catquiz_standarderror_min'])) {
                $errors['catquiz_standarderrorgroup'] =
                get_string('errorhastobefloat', 'local_catquiz');
            } else if (0.0 > (float)$data['catquiz_standarderrorgroup']['catquiz_standarderror_min']) {
                $errors['catquiz_standarderrorgroup'] = get_string('formelementnegative', 'local_catquiz');
            } else {
                $semin = true;
            }
        }
        if (isset($data['catquiz_standarderrorgroup']['catquiz_standarderror_max'])
            && $data['catquiz_standarderrorgroup']['catquiz_standarderror_max'] !== "") {
            if (!is_numeric($data['catquiz_standarderrorgroup']['catquiz_standarderror_max'])
                && !empty($data['catquiz_standarderrorgroup']['catquiz_standarderror_max'])) {
                $errors['catquiz_standarderrorgroup'] =
                get_string('errorhastobefloat', 'local_catquiz');
            } else if (0.0 > (float)$data['catquiz_standarderrorgroup']['catquiz_standarderror_max']) {
                $errors['catquiz_standarderrorgroup'] = get_string('formelementnegative', 'local_catquiz');
            } else if ($semin && !empty($data['catquiz_standarderrorgroup']['catquiz_standarderror_min']
                >= (float)$data['catquiz_standarderrorgroup']['catquiz_standarderror_max'])) {
                    $errors['catquiz_standarderrorgroup']
                    = get_string('formminquestgreaterthan', 'local_catquiz');
            } else {
                $semax = true;
            }
        }
        $sevalues = new stdClass;
        $sevalues->min = LOCAL_CATQUIZ_STANDARDERROR_DEFAULT_MIN;
        $sevalues->max = LOCAL_CATQUIZ_STANDARDERROR_DEFAULT_MAX;
        if ((!$semin || !$semax) && empty($errors['catquiz_standarderrorgroup'])) {
            $errors['catquiz_standarderrorgroup']
            = get_string('setsevalue', 'local_catquiz', $sevalues);
        }

        $hasmaxqpscale = array_key_exists('maxquestionsscalegroup', $data);
        // Number of questions - validate higher and lower values.
        if ($hasmaxqpscale
            && (int) $data['maxquestionsscalegroup']['catquiz_minquestionspersubscale']
            >= (int) $data['maxquestionsscalegroup']['catquiz_maxquestionspersubscale']
            && 0 != (int) $data['maxquestionsscalegroup']['catquiz_maxquestionspersubscale']) {
            $errors['maxquestionsscalegroup'] = get_string('formminquestgreaterthan', 'local_catquiz');
        }
        if ($hasmaxqpscale
            && (int) $data['maxquestionsgroup']['catquiz_minquestions']
            >= (int) $data['maxquestionsgroup']['catquiz_maxquestions']
            && 0 != (int) $data['maxquestionsgroup']['catquiz_maxquestions']) {
            $errors['maxquestionsgroup'] = get_string('formminquestgreaterthan', 'local_catquiz');
        }

        // Min questions per scale <= max questions per test.
        if ($hasmaxqpscale
            && 0 != (int) $data['maxquestionsscalegroup']['catquiz_minquestionspersubscale']
            && 0 != (int) $data['maxquestionsgroup']['catquiz_maxquestions']) {
            if ((int) $data['maxquestionsscalegroup']['catquiz_minquestionspersubscale']
                > (int) $data['maxquestionsgroup']['catquiz_maxquestions']) {
                    $errors['maxquestionsgroup']
                    = get_string('formmscalegreaterthantest', 'local_catquiz');
            }
        }

        // Validate time: at least on value must be provided if time limitation checked.
        if (!empty($data['catquiz_includetimelimit'])) {
            if (empty($data['catquiz_timelimitgroup']['catquiz_maxtimeperitem'])
                && empty($data['catquiz_timelimitgroup']['catquiz_maxtimeperattempt'])) {
                    $errors['catquiz_timelimitgroup'] = get_string('formetimelimitnotprovided', 'local_catquiz');
            }
        }

        feedbackclass::validation_range_limits_nogaps($errors, $data);

        return $errors;
    }

    /**
     * Check if a value in data is of type int and over 0 otherwise add error to validation.
     * @param array $errors
     * @param array $data
     * @param string $key
     * @param string $group
     *
     * @return [type]
     */
    private static function check_if_positive_int(array &$errors, array $data, string $key, string $group) {
        if (!empty($group)) {
            if (!empty($data[$group][$key])) {
                if (!is_numeric($data[$group][$key]) || !is_int((int)$data[$group][$key])) {
                    $errors[$group] = get_string('errorhastobeint', 'local_catquiz');
                } else if (0 > (int) $data[$group][$key]) {
                    $errors[$group] = get_string('formelementnegative', 'local_catquiz');
                }
            }
        } else {
            if (isset($data[$key])) {
                if (!is_int($data[$key])) {
                    $errors[$key] = get_string('errorhastobeint', 'local_catquiz');
                } else if (0 > (int) $data[$key]) {
                    $errors[$key] = get_string('formelementnegative', 'local_catquiz');
                }
            }
        }

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

        $clone = self::prepare_editor_fields($quizdata->id, $clone);

        // We unset id & instance. We don't want to introduce confusion because of it.
        unset($clone->id);
        unset($clone->instance);
        unset($clone->course);
        unset($clone->section);

        // If there is a new template name.
        if (!empty($quizdata->testenvironment_addoredittemplate) && !empty($quizdata->testenvironment_name)) {
            $clone = self::prepare_editor_fields($quizdata->choosetemplate, $clone);

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
            $parentscale = catscale::return_catscale_object($values['catquiz_catscales']);
            // First, get the setting.
            $numberofoptions = $values['numberoffeedbackoptionsselect'];

            foreach ($values as $k => $v) {

                if (strpos($k, 'feedback_scaleid_limit_') !== false) {

                    if ($mform->elementExists($k)) {

                        preg_match('/_(\d+)$/', $k, $matches);
                        $j = $matches[1];

                        if (strpos($k, '_lower')) {
                            $uselower = true;
                        } else {
                            $uselower = false;
                        }
                        $value = feedbackclass::return_limits_for_scale(
                            $numberofoptions,
                            $j,
                            $uselower,
                            $parentscale->minscalevalue,
                            $parentscale->maxscalevalue
                        );
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
            $nfeedbackoptions = intval($values['numberoffeedbackoptionsselect']);
            $standardvalues = [];
            $feedbackvaluekeys = [
                'feedback_scaleid_limit_lower_',
                'feedback_scaleid_limit_upper_',
                'wb_colourpicker_',
                'feedbackeditor_scaleid_',
                'catquiz_group_',
                'catquiz_courses_',
                'enrolment_message_checkbox_',
                'feedbacklegend_scaleid_',
            ];

            $feedbackvaluekeysonceperscale = [
                'catquiz_scalereportcheckbox_',
            ];
            $feedbackvaluekeys = array_merge($feedbackvaluekeys, $feedbackvaluekeysonceperscale);

            // Fetch standard values from the parentscale, we want to apply to all subscales.

            foreach ($feedbackvaluekeys as $feedbackvaluekey) {
                if (!isset($standardvalues[$feedbackvaluekey])) {
                    $standardvalues[$feedbackvaluekey] = [];
                }
                if (in_array($feedbackvaluekey, $feedbackvaluekeysonceperscale)) {
                    $keyname = $feedbackvaluekey . $scaleidofcopyvalue;
                    $standardvalues[$feedbackvaluekey] = $values[$keyname] ?? null;
                    continue;
                }
                for ($j = 1; $j <= $nfeedbackoptions; $j++) {
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
                    if (in_array($feedbackvaluekey, $feedbackvaluekeysonceperscale)) {
                        $subscalekey = $feedbackvaluekey . $subscaleid;
                        $values[$subscalekey] = $standardvalues[$feedbackvaluekey] ?? "0";
                        continue;
                    }
                    for ($j = 1; $j <= $nfeedbackoptions; $j++) {
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
            } else {
                if (preg_match("/^catquiz_subscalecheckbox_/", $k)) {
                    $mform->addElement(
                        'advcheckbox',
                        $k,
                        $k,
                        null,
                        [],
                        [0, 1]
                    );
                    $mform->setDefault($k, $v);
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

        $catcontext = $testenvironment->get_contextid();
        $tsinfo = new info();
        $teststrategy = $tsinfo
            ->return_active_strategy($quizsettings->catquiz_selectteststrategy)
            ->set_scale($quizsettings->catquiz_catscales)
            ->set_catcontextid($catcontext);

        $selectioncontext = self::get_strategy_selectcontext($quizsettings, $attemptdata);
        $result = $teststrategy->return_next_testitem($selectioncontext);
        if (!$result->isOk()) {
            catquiz::set_final_attempt_status($attemptdata->id, $result->get_status());
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
        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        $cache->purge();
        $cache->set('starttime', time());
    }

    /**
     * Callback for adaptivequiz
     *
     * This is called when the attempt is finished.
     *
     * @param stdClass $adaptivequiz
     * @param cm_info $cm
     * @param stdClass $attemptrecord
     *
     * @return string
     *
     */
    public static function attempt_finished(
        stdClass $adaptivequiz,
        cm_info $cm,
        stdClass $attemptrecord
        ): string {
        // Update the endtime and number of testitems used in the attempts table.
        global $DB, $COURSE;
        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        $id = $DB->get_record('local_catquiz_attempts', ['attemptid' => $attemptrecord->id], 'id')->id;
        $data = (object) [
            'id' => $id,
            'number_of_testitems_used' => $attemptrecord->questionsattempted,
            'endtime' => $cache->get('endtime'),
            'timemodified' => time(),
        ];
        $DB->update_record('local_catquiz_attempts', $data);

        // If there was an error before the quiz could be started, return that.
        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        if (($errormsg = $cache->get('catquizerror')) && $attemptrecord->questionsattempted == 0) {
            return get_string($errormsg, 'local_catquiz');
        }
        // If we are here, at least one question was played and we can provide feedback.
        $contextid = optional_param('context', 0, PARAM_INT);
        $attemptfeedback = new attemptfeedback($attemptrecord->id, $contextid, null, $COURSE->id);
        $enrolmentmessage = $attemptfeedback->attempt_finished_tasks();

        return self::render_attemptfeedback(
            $attemptrecord,
            $attemptfeedback,
            $enrolmentmessage
        );
    }

    /**
     * Attempt feedback.
     *
     * @param stdClass $attemptrecord
     * @param attemptfeedback $attemptfeedback
     * @param string $enrolmentmessage
     *
     * @return string
     *
     */
    private static function render_attemptfeedback(
        stdClass $attemptrecord,
        attemptfeedback $attemptfeedback,
        string $enrolmentmessage
        ): string {
        global $OUTPUT;

        $data = $attemptfeedback->export_for_template($OUTPUT);
        $data['enrolementmessage'] = $enrolmentmessage;
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
        // If this is not the first question, load the quiz settings that were used for the first question.
        if ($attemptdata->questionsattempted > 0) {
            $quizsettings = progress::load(
                intval($attemptdata->id),
                'mod_adaptivequiz',
                $quizsettings->catquiz_catscales
                )
                ->get_quiz_settings();
        }

        if ($quizsettings->catquiz_includepilotquestions) {
            $pilotratio = floatval($quizsettings->catquiz_pilotratio);
        }

        // Default is infinite represented by -1.
        $maxquestionsperscale = 0;
        $hasmaxqpscale = property_exists($quizsettings, 'maxquestionsscalegroup');
        $maxquestionsperscale = $hasmaxqpscale
            ? intval($quizsettings->maxquestionsscalegroup->catquiz_maxquestionspersubscale)
            : $maxquestionsperscale;
        if ($maxquestionsperscale == 0) {
            $maxquestionsperscale = -1;
        }

        $minquestionsperscale = $hasmaxqpscale
                ? intval($quizsettings->maxquestionsscalegroup->catquiz_minquestionspersubscale)
                : 0;

        $maxquestions = $quizsettings->maxquestionsgroup->catquiz_maxquestions;
        if (!$maxquestions) {
            $maxquestions = -1;
        }
        if (!empty($quizsettings->catquiz_timelimitgroup->catquiz_maxtimeperattempt)
            && !empty($quizsettings->catquiz_timelimitgroup->catquiz_timeselect_attempt)) {
                $attemptseconds = self::get_number_of_seconds(
                    $quizsettings->catquiz_timelimitgroup->catquiz_timeselect_attempt,
                    (int)$quizsettings->catquiz_timelimitgroup->catquiz_maxtimeperattempt);
        }

        if (!empty($quizsettings->catquiz_timelimitgroup->catquiz_maxtimeperitem)
        && !empty($quizsettings->catquiz_timelimitgroup->catquiz_timeselect_item)) {
            $itemseconds = self::get_number_of_seconds(
                $quizsettings->catquiz_timelimitgroup->catquiz_timeselect_item,
                (int)$quizsettings->catquiz_timelimitgroup->catquiz_maxtimeperitem);
        }

        $firstuseexistingdata = false;
        if (property_exists($quizsettings, 'catquiz_firstquestionreuseexistingdata')) {
            $firstuseexistingdata = $quizsettings->catquiz_firstquestionreuseexistingdata == "1";
        }

        $catcontext = catscale::get_context_id($quizsettings->catquiz_catscales);
        $initialcontext = [
            'testid' => intval($attemptdata->instance),
            'contextid' => $catcontext,
            'quizsettings' => $quizsettings,
            'catscaleid' => $quizsettings->catquiz_catscales,
            'installed_models' => model_strategy::get_installed_models(),
            // When selecting questions from a scale, also include questions from its subscales.
            // This option is required by the questions_loader context loader.
            'includesubscales' => true,
            'maximumquestions' => $maxquestions,
            'minimumquestions' => $quizsettings->maxquestionsgroup->catquiz_minquestions,
            'penalty_threshold' => 60 * 60 * 24 * intval(get_config('local_catquiz', 'time_penalty_threshold')),
            'initial_standarderror' => 1.0, // TODO: make configurable.
            'pilot_ratio' => $pilotratio ?? 0,
            'pilot_attempts_threshold' => LOCAL_CATQUIZ_THRESHOLD_DEFAULT,
            'questionsattempted' => intval($attemptdata->questionsattempted),
            'firstquestion_use_existing_data' => $firstuseexistingdata,
            'selectfirstquestion' => $quizsettings->catquiz_selectfirstquestion ?? null,
            'skip_reason' => null,
            'userid' => $USER->id,
            'max_attempts_per_scale' => $maxquestionsperscale,
            'min_attempts_per_scale' => $minquestionsperscale,
            'teststrategy' => $quizsettings->catquiz_selectteststrategy,
            'timestamp' => time(),
            'attemptid' => intval($attemptdata->id),
             // Hardcoded because this function already depends on mod_adaptivequiz attemptdata.
            'component' => 'mod_adaptivequiz',
            'has_fisherinformation' => false,
            'max_attempttime_in_sec' => $attemptseconds ?? INF,
            'max_itemtime_in_sec' => $itemseconds ?? INF,
            'se_max' => $quizsettings->catquiz_standarderrorgroup->catquiz_standarderror_max,
            'se_min' => $quizsettings->catquiz_standarderrorgroup->catquiz_standarderror_min,
            'pp_min_inc' => $quizsettings->catquiz_pp_min_inc ?? 0.01,
        ];

        if (property_exists($quizsettings, 'fake_use_tr_factor')) {
            $initialcontext['fake_use_tr_factor'] = $quizsettings->fake_use_tr_factor;
        }

        return $contextcreator->load(
            [
                'progress',
                'person_ability',
                'contextid',
                'questions',
                'pilot_questions',
                'se',
                'initial_scales',
            ],
            $initialcontext
        );
    }

    /**
     * Returns number of seconds according to string from select.
     * @param string $selectvalue
     * @param int $time
     *
     * @return int
     */
    public static function get_number_of_seconds(string $selectvalue, int $time) {
        switch ($selectvalue) {
            case 'h':
                return $time * 3600;
            case 'min':
                return $time * 60;
            case 'sec':
                return $time * 1;
            default:
                return $time;
        }
    }

    /**
     * Prepares editor fields.
     *
     * @param int $componentid The ID of the quiz component being processed
     * @param stdClass $clone The object containing the form data to be processed
     * @return stdClass The processed object with updated editor fields
     */
    private static function prepare_editor_fields(int $componentid, stdClass $clone): stdClass {
        if (!$cm = get_coursemodule_from_instance('adaptivequiz', intval($componentid))) {
            return $clone;
        }
        $context = context_module::instance($cm->id);
        $textfieldoptions = [
            'trusttext' => true,
            'subdirs' => true,
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'context' => $context,
        ];

        foreach ($clone as $property => $value) {
            if (!preg_match('/^feedbackeditor_scaleid_(\d+)_(\d+)$/', $property, $matches)) {
                continue;
            }
            if (!property_exists($clone, $property . '_editor')) {
                $clone->{$property . '_editor'} = $clone->$property;
            }
            $scaleid = intval($matches[1]);
            $rangeid = intval($matches[2]);
            $fieldname = sprintf('feedbackeditor_scaleid_%d_%d', $scaleid, $rangeid);
            $filearea = sprintf('feedback_files_%d_%d', $scaleid, $rangeid);
            $clone = file_postupdate_standard_editor(
                $clone,
                $fieldname,
                $textfieldoptions,
                $context,
                'local_catquiz',
                $filearea,
                $clone->id
            );
            unset($clone->{$property . '_editor'});
            file_save_draft_area_files(
                $value['itemid'],
                $context->id,
                'local_catquiz',
                $filearea,
                $clone->id
            );
        }

        return $clone;
    }
}
