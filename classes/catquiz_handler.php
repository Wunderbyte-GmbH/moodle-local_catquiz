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
use local_catquiz\local\attempt\attempt_finalizer;
use local_catquiz\local\attempt_cache;
use local_catquiz\local\model\model_strategy;
use local_catquiz\local\monitoring\timeline;
use local_catquiz\local\status;
use local_catquiz\local\validation\settings_validator;
use local_catquiz\output\attemptfeedback;
use local_catquiz\teststrategy\info;
use local_catquiz\teststrategy\progress;
use MoodleQuickForm;
use stdClass;
use local_catquiz\local\progress_retention;

/**
 * Class catquiz
 *
 * @author Georg Maißer
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catquiz_handler {
    /** @var string Issue #89: no template action. */
    public const TEMPLATE_ACTION_NONE = 'none';

    /** @var string Issue #89: overwrite the chosen template with the settings of the activity. */
    public const TEMPLATE_ACTION_UPDATE = 'update';

    /** @var string Issue #89: save the settings of the activity as a new template. */
    public const TEMPLATE_ACTION_COPY = 'copy';

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

        $advendesettingheading = $mform->getElement('advancedheading');
        $advendesettingheading->setText(
            get_string('catmodelsettings', 'local_catquiz')
        );

        $testtemplates = testenvironment::get_environments_as_array(
            'mod_adaptivequiz',
            0,
            LOCAL_CATQUIZ_TESTENVIRONMENT_ONLYACTIVETEMPLATES
        );

        // We introduce the option of a custom test environment.
        $testtemplates[0] = get_string('newcustomtest', 'local_catquiz');

        ksort($testtemplates);

        $elements[] = $mform->addElement(
            'select',
            'choosetemplate',
            get_string('choosetemplate', 'local_catquiz'),
            $testtemplates,
            ['data-on-change-action' => 'reloadTestForm']
        );

        $mform->setType('choosetemplate', PARAM_INT);

        // Add a hidden element to store which button was clicked.
        $elements[] = $mform->addElement('hidden', 'triggered_button', '');
        $mform->setType('triggered_button', PARAM_ALPHANUMEXT);

        $context = context_system::instance();

        if (has_capability('local/catquiz:manage_testenvironments', $context)) {
            // Issue #89: two actions of their own, each named before the form is sent - overwrite the chosen
            // template, or save the settings as a new one. Before, one checkbox "add or edit" did either,
            // depending on whether the typed name matched the old one.
            $elements[] = $mform->addElement(
                'select',
                'testenvironment_templateaction',
                get_string('templateaction', 'local_catquiz'),
                [
                    self::TEMPLATE_ACTION_NONE => get_string('templateaction_none', 'local_catquiz'),
                    self::TEMPLATE_ACTION_UPDATE => get_string('templateaction_update', 'local_catquiz'),
                    self::TEMPLATE_ACTION_COPY => get_string('templateaction_copy', 'local_catquiz'),
                ]
            );
            $mform->setType('testenvironment_templateaction', PARAM_ALPHA);
            $mform->setDefault('testenvironment_templateaction', self::TEMPLATE_ACTION_NONE);
            $mform->addHelpButton('testenvironment_templateaction', 'templateaction', 'local_catquiz');

            $elements[] = $mform->addElement('text', 'testenvironment_name', get_string('templatename', 'local_catquiz'));
            $mform->setType('testenvironment_name', PARAM_TEXT);
            $mform->hideIf('testenvironment_name', 'testenvironment_templateaction', 'eq', self::TEMPLATE_ACTION_NONE);
        }

        // We have require JS to click no submit button on change of test environment.
        $PAGE->requires->js_call_amd('local_catquiz/catquizTestChooser', 'init');

        // Button to attach JavaScript to reload the form.
        $mform->registerNoSubmitButton('submitcattestoption');
        $elements[] = $mform->addElement(
            'submit',
            'submitcattestoption',
            'cattestsubmit',
            [
            'class' => 'd-none',
            'data-action' => 'submitCatTest',
            ]
        );

        // Add a special header for catquiz.
        $elements[] = $mform->addElement(
            'header',
            'catquiz_header',
            get_string('catquizsettings', 'local_catquiz')
        );
        $mform->setExpanded('catquiz_header');

        $selectedcontext = optional_param('contextid', 0, PARAM_INT);
        $name = $DB->get_field('local_catquiz_catcontext', 'name', ['id' => $selectedcontext]);
        if ($name) {
            $elements[] = $mform->addElement(
                'static',
                'selectedcontext',
                get_string('testcontext', 'local_catquiz'),
                $name
            );
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
            get_string('selectparentscale', 'local_catquiz'),
            $select,
            $options
        );
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

        // How long the working state of an attempt is kept, per test.
        //
        // Only the levels the site permits are offered. A site configured for data
        // minimisation must not be overridden here, and an option that would be
        // capped away on save would be misleading to show at all.
        $retentionoptions = [
            '' => get_string('progressretention_default', 'local_catquiz'),
        ];
        foreach (progress_retention::levels() as $level) {
            if (progress_retention::effective_level($level) !== $level) {
                continue;
            }
            $retentionoptions[$level] = get_string('progressretention_' . $level, 'local_catquiz');
        }
        $elements[] = $mform->addElement(
            'select',
            'catquiz_progressretention',
            get_string('progressretention', 'local_catquiz'),
            $retentionoptions
        );
        $mform->setType('catquiz_progressretention', PARAM_ALPHA);
        $mform->addHelpButton('catquiz_progressretention', 'progressretention', 'local_catquiz');

        // Button to attach JavaScript to reload the form.
        $mform->registerNoSubmitButton('submitcatscaleoption');
        $elements[] = $mform->addElement(
            'submit',
            'submitcatscaleoption',
            get_string('applychanges', 'local_catquiz'),
            [
            'class' => 'hidden',
            'data-action' => 'submitCatScale',
            ]
        );

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
        string $elementadded = ''
    ) {

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
     * Prepares the CAT settings of the activity form: stored values on the first load, the
     * submission on every later request.
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

        /* Issue #124: what the user has just submitted takes precedence over what is stored. The host
           hands over its form for this; an older host did not, and then every request - first load,
           reload after a change of scale, final save - took the first-load path below and wrote the
           stored settings over the fresh choice. Without a form the raw submission tells the same:
           on a first load there is none. */
        $data = $mform ? $mform->getSubmitValues() : (array) (data_submitted() ?: []);

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
        } else if (
            isset($data['submitcattestoption'])
            && !empty($data['choosetemplate'])
            && ($data['submitcattestoption'] == "cattestsubmit")
        ) {
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
        if (!empty($data)) {
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
            $formdefaultvalues['testenvironment_templateaction'] = self::TEMPLATE_ACTION_NONE;
        }
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

        // Issue #89: a template action needs what it acts on, and names of templates are unique.
        $action = self::template_action((object) $data);
        $chosen = (int) ($data['choosetemplate'] ?? 0);
        $templatename = trim((string) ($data['testenvironment_name'] ?? ''));
        if ($action === self::TEMPLATE_ACTION_UPDATE && !testenvironment::is_template($chosen)) {
            $errors['testenvironment_templateaction'] = get_string('templateaction_notemplate', 'local_catquiz');
        } else if ($action === self::TEMPLATE_ACTION_COPY && $templatename === '') {
            $errors['testenvironment_name'] = get_string('required');
        } else if (
            ($action === self::TEMPLATE_ACTION_COPY || ($action === self::TEMPLATE_ACTION_UPDATE && $templatename !== ''))
            && testenvironment::template_name_taken($templatename, $action === self::TEMPLATE_ACTION_UPDATE ? $chosen : 0)
        ) {
            $errors['testenvironment_name'] = get_string('templatenametaken', 'local_catquiz', $templatename);
        }

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
        if (
            isset($data['catquiz_standarderrorgroup']['catquiz_standarderror_min'])
            && $data['catquiz_standarderrorgroup']['catquiz_standarderror_min'] !== ""
        ) {
            if (!is_numeric($data['catquiz_standarderrorgroup']['catquiz_standarderror_min'])) {
                $errors['catquiz_standarderrorgroup'] =
                get_string('errorhastobefloat', 'local_catquiz');
            } else if (0.0 > (float)$data['catquiz_standarderrorgroup']['catquiz_standarderror_min']) {
                $errors['catquiz_standarderrorgroup'] = get_string('formelementnegative', 'local_catquiz');
            } else {
                $semin = true;
            }
        }
        if (
            isset($data['catquiz_standarderrorgroup']['catquiz_standarderror_max'])
            && $data['catquiz_standarderrorgroup']['catquiz_standarderror_max'] !== ""
        ) {
            if (
                !is_numeric($data['catquiz_standarderrorgroup']['catquiz_standarderror_max'])
                && !empty($data['catquiz_standarderrorgroup']['catquiz_standarderror_max'])
            ) {
                $errors['catquiz_standarderrorgroup'] =
                get_string('errorhastobefloat', 'local_catquiz');
            } else if (0.0 > (float)$data['catquiz_standarderrorgroup']['catquiz_standarderror_max']) {
                $errors['catquiz_standarderrorgroup'] = get_string('formelementnegative', 'local_catquiz');
            } else if (
                $semin && !empty($data['catquiz_standarderrorgroup']['catquiz_standarderror_min']
                >= (float)$data['catquiz_standarderrorgroup']['catquiz_standarderror_max'])
            ) {
                    $errors['catquiz_standarderrorgroup']
                    = get_string('formminquestgreaterthan', 'local_catquiz');
            } else {
                $semax = true;
            }
        }
        $sevalues = new stdClass();
        $sevalues->min = LOCAL_CATQUIZ_STANDARDERROR_DEFAULT_MIN;
        $sevalues->max = LOCAL_CATQUIZ_STANDARDERROR_DEFAULT_MAX;
        if ((!$semin || !$semax) && empty($errors['catquiz_standarderrorgroup'])) {
            $errors['catquiz_standarderrorgroup']
            = get_string('setsevalue', 'local_catquiz', $sevalues);
        }

        $hasmaxqpscale = array_key_exists('maxquestionsscalegroup', $data);
        // Number of questions - validate higher and lower values.
        if (
            $hasmaxqpscale
            && (int) $data['maxquestionsscalegroup']['catquiz_minquestionspersubscale']
            >= (int) $data['maxquestionsscalegroup']['catquiz_maxquestionspersubscale']
            && 0 != (int) $data['maxquestionsscalegroup']['catquiz_maxquestionspersubscale']
        ) {
            $errors['maxquestionsscalegroup'] = get_string('formminquestgreaterthan', 'local_catquiz');
        }
        if (
            $hasmaxqpscale
            && (int) $data['maxquestionsgroup']['catquiz_minquestions']
            >= (int) $data['maxquestionsgroup']['catquiz_maxquestions']
            && 0 != (int) $data['maxquestionsgroup']['catquiz_maxquestions']
        ) {
            $errors['maxquestionsgroup'] = get_string('formminquestgreaterthan', 'local_catquiz');
        }

        // Min questions per scale <= max questions per test.
        if (
            $hasmaxqpscale
            && 0 != (int) $data['maxquestionsscalegroup']['catquiz_minquestionspersubscale']
            && 0 != (int) $data['maxquestionsgroup']['catquiz_maxquestions']
        ) {
            if (
                (int) $data['maxquestionsscalegroup']['catquiz_minquestionspersubscale']
                > (int) $data['maxquestionsgroup']['catquiz_maxquestions']
            ) {
                    $errors['maxquestionsgroup']
                    = get_string('formmscalegreaterthantest', 'local_catquiz');
            }
        }

        // Validate time: at least on value must be provided if time limitation checked.
        if (!empty($data['catquiz_includetimelimit'])) {
            if (
                empty($data['catquiz_timelimitgroup']['catquiz_maxtimeperitem'])
                && empty($data['catquiz_timelimitgroup']['catquiz_maxtimeperattempt'])
            ) {
                    $errors['catquiz_timelimitgroup'] = get_string('formetimelimitnotprovided', 'local_catquiz');
            }
        }

        // Issue #51: the scales not being edited bring their feedback in a carrier each; their errors are
        // shown at the scale, whose fields are not in the form.
        $editedscaleid = (int) ($data[feedbackclass::EDITSCALE] ?? 0);
        $feedbackerrors = [];
        feedbackclass::validation_range_limits_nogaps($feedbackerrors, feedbackclass::expand_carriers($data));
        feedbackclass::move_errors_to_summaries($feedbackerrors, $editedscaleid);
        $errors = array_merge($errors, $feedbackerrors);

        // Issue #60: the settings taken together - a combination no attempt can meet is not saved. A field that
        // already has an error of its own keeps it; that one is fixed first.
        if (!empty($data['catquiz_catscales'])) {
            foreach (settings_validator::errors(settings_validator::validate(settings_validator::from_form($data))) as $finding) {
                if (empty($errors[$finding['field']])) {
                    $errors[$finding['field']] = $finding['message'];
                }
            }
        }

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

        if (
            !$catquiz = $DB->get_record(
                'local_catquiz_tests',
                ['component' => $component, 'componentid' => $componentid]
            )
        ) {
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

        // Issue #51: the scales not being edited bring their feedback in a carrier each.
        $quizdata = feedbackclass::expand_carriers($quizdata);
        $clone = clone($quizdata);

        $clone = self::prepare_editor_fields($quizdata->id, $clone);

        // We unset id & instance. We don't want to introduce confusion because of it.
        unset($clone->id);
        unset($clone->instance);
        unset($clone->course);
        unset($clone->section);

        // Issue #89: the template action, with explicit ids. The source of a copy stays as it is.
        $action = self::template_action($quizdata);
        $templateid = 0;
        if ($action !== self::TEMPLATE_ACTION_NONE) {
            require_capability('local/catquiz:manage_testenvironments', context_system::instance());
            $settings = clone($clone);
            $settings->testenvironment_templateaction = self::TEMPLATE_ACTION_NONE;
            unset($settings->testenvironment_addoredittemplate);
            $scaleid = (int) ($clone->catquiz_catscales ?? 0);
            if ($action === self::TEMPLATE_ACTION_UPDATE) {
                $templateid = testenvironment::update_template(
                    (int) $quizdata->choosetemplate,
                    $settings,
                    $scaleid,
                    (string) ($quizdata->testenvironment_name ?? '')
                );
            } else {
                $templateid = testenvironment::create_template($settings, $scaleid, (string) $quizdata->testenvironment_name);
            }
            // The files of the written feedback: from the activity to the template, where loading it takes them.
            $cmid = isset($quizdata->coursemodule) ? (int) $quizdata->coursemodule : 0;
            $cm = $cmid
                ? get_coursemodule_from_id('adaptivequiz', $cmid)
                : get_coursemodule_from_instance('adaptivequiz', (int) $quizdata->id);
            if ($cm) {
                testenvironment::copy_feedback_files(
                    context_module::instance($cm->id)->id,
                    (int) $quizdata->id,
                    context_system::instance()->id,
                    $templateid
                );
            }
        }
        // The action is done; stored with the activity it would be done again at its next save.
        $clone->testenvironment_templateaction = self::TEMPLATE_ACTION_NONE;
        unset($clone->testenvironment_addoredittemplate);
        // The template the activity is based on: the one acted on, else the one loaded into the form.
        $loaded = (int) ($quizdata->choosetemplate ?? 0);
        $parentid = $templateid ?: (testenvironment::is_template($loaded) ? $loaded : 0);

        // Create stdClass with all the values.
        $cattest = (object)[
            'componentid' => $quizdata->id,
            'component' => 'mod_adaptivequiz',
            'json' => json_encode($clone),
            'parentid' => $parentid,
            // Not every caller submits the CATquiz form: an instance created by a generator or a
            // restore may carry no scale yet. The test then has none, and is not ready to run.
            'catscaleid' => $quizdata->catquiz_catscales ?? 0,
            'courseid' => $quizdata->course,
        ];

         // Pass on the values as stdClas.
        $test = new testenvironment($cattest);
        // Save the values in the DB.
        $test->save_or_update();

        cache_helper::purge_by_event('changesintestenvironments');

        // Issue #60: what the settings allow but what can never take effect is said once, when they are saved.
        if (!empty($clone->catquiz_catscales)) {
            foreach (settings_validator::validate($clone) as $finding) {
                if ($finding['level'] === settings_validator::WARNING) {
                    \core\notification::warning($finding['message']);
                }
            }
        }

        // The progress bar on the attempt page is drawn by mod_adaptivequiz
        // against its own maximumquestions field. Keeping it in step here is what
        // makes that bar show the CAT limit instead of a placeholder.
        self::sync_activity_question_limit($quizdata);
    }

    /**
     * Writes the CAT question limit into the activity's own maximumquestions field.
     *
     * The attempt page draws its progress bar from $adaptivequiz->maximumquestions -
     * the activity's field, not ours. On a test run through catquiz that field is
     * usually left at a large placeholder so the activity's hard stop never fires
     * before the CAT logic does. The bar then counts against the wrong reference:
     * "5 / 1000" on a test that ends after twenty questions, and a bar that never
     * visibly moves.
     *
     * Syncing here keeps one field but only one source. Reading the CAT setting from
     * mod_adaptivequiz instead would make the activity depend on this plugin, which
     * is the wrong direction.
     *
     * @param stdClass $quizdata
     * @return void
     */
    private static function sync_activity_question_limit(stdClass $quizdata): void {
        global $DB;

        $catmax = (int) ($quizdata->maxquestionsgroup->catquiz_maxquestions ?? 0);

        // No CAT limit configured: the activity's own value is all there is, and
        // overwriting it with zero would remove the stop altogether.
        if ($catmax <= 0) {
            return;
        }

        if (empty($quizdata->id)) {
            return;
        }

        $DB->set_field('adaptivequiz', 'maximumquestions', $catmax, ['id' => $quizdata->id]);
    }

    /**
     * Brings the maximum of every CATquiz activity in line with its CAT settings (issue #141).
     *
     * The activity now refuses any question beyond its own maximumquestions, pilot items and
     * repetitions included. An activity whose CAT limit changed without the activity being saved
     * again - through a template, say - would stop at a stale value. Activities without a CAT
     * limit keep their own value, as on save.
     *
     * @return int Number of activities whose maximum was changed.
     */
    public static function sync_all_activity_question_limits(): int {
        global $DB;

        $changed = 0;
        $tests = $DB->get_recordset_select(
            'local_catquiz_tests',
            "component = :component AND componentid > 0",
            ['component' => 'mod_adaptivequiz'],
            'id',
            'id, componentid, json'
        );
        foreach ($tests as $test) {
            $settings = json_decode((string) $test->json);
            $catmax = (int) ($settings->maxquestionsgroup->catquiz_maxquestions ?? 0);
            if ($catmax <= 0) {
                continue;
            }
            $current = $DB->get_field('adaptivequiz', 'maximumquestions', ['id' => $test->componentid]);
            if ($current === false || (int) $current === $catmax) {
                continue;
            }
            $DB->set_field('adaptivequiz', 'maximumquestions', $catmax, ['id' => $test->componentid]);
            $changed++;
        }
        $tests->close();
        return $changed;
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
        if (
            isset($values["submitnumberoffeedbackoptions"])
            && $values["submitnumberoffeedbackoptions"] == "numberoffeedbackoptionssubmit"
        ) {
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
                // The range limit fields carry localised numbers taken raw from
                // getSubmitValues(). A German decimal comma ("1,5") must be parsed
                // with unformat_float() here; a plain (float)/floatval() would
                // truncate it to 1.0 and silently corrupt the stored yellow/green
                // boundary for every copied-to subscale (Issue: mis-coloured
                // feedback because 1,5 collapsed to 1).
                $islimitkey = ($feedbackvaluekey === 'feedback_scaleid_limit_lower_'
                    || $feedbackvaluekey === 'feedback_scaleid_limit_upper_');
                for ($j = 1; $j <= $nfeedbackoptions; $j++) {
                    $keyname = $feedbackvaluekey . $scaleidofcopyvalue . '_' . $j;
                    $rawvalue = $values[$keyname] ?? null;
                    if ($islimitkey && is_string($rawvalue) && trim($rawvalue) !== '') {
                        $parsed = unformat_float($rawvalue, true);
                        if ($parsed !== false && $parsed !== null) {
                            $rawvalue = $parsed;
                        }
                    }
                    $standardvalues[$feedbackvaluekey][$j] = $rawvalue;
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
        } else if (
            !isset($values["submitcattestoption"])
            || $values["submitcattestoption"] != "cattestsubmit"
        ) {
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
                'testenvironment_templateaction' => self::TEMPLATE_ACTION_NONE,
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
            if (strpos((string) $k, feedbackclass::CARRIER) === 0) {
                // The carriers were set up with the form; what is copied into them is set field by field.
                continue;
            }

            if ($mform->elementExists($k)) {
                $element = $mform->getElement($k);
                $element->setValue($v);
                if ($test->status_force() && $k !== 'choosetemplate') {
                    $element->freeze();
                }
            } else if (feedbackclass::set_carrier_value($mform, (string) $k, $v)) {
                // Issue #51: a scale not being edited keeps its values in its carrier.
                continue;
            } else {
                // Issue #52: only for a scale that still exists - a deleted one would come back as a field with
                // its raw key as label, and be saved into the settings again.
                if (
                    preg_match("/^catquiz_subscalecheckbox_(\\d+)$/", $k, $scalematch)
                    && array_key_exists((int) $scalematch[1], \local_catquiz\data\dataapi::get_all_catscales())
                ) {
                    $checkbox = $mform->addElement(
                        'advcheckbox',
                        $k,
                        $k,
                        null,
                        [],
                        [0, 1]
                    );
                    // Not setDefault(): it hands every element its submitted value again and so undid the
                    // values of the template set above - the reload kept the old settings (issue #123).
                    $checkbox->setValue($v);
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
        // Issue #136: the trace of this request belongs to this attempt; first item or a following one.
        // Only an attempt that has an id can own a trace; a caller may hand over less (the adapter's
        // tests pass a bare attempt object), and reading the missing id raised a warning.
        if (isset($attemptdata->id)) {
            timeline::attempt((int) $attemptdata->id, (int) ($attemptdata->questionsattempted ?? 0));
        }
        return timeline::span('catquiz:fetch_question_id', fn() => self::fetch_question_id_traced($cmid, $component, $attemptdata));
    }

    /**
     * Does the work of fetch_question_id(), inside its trace span.
     *
     * @param int $cmid
     * @param string $component
     * @param stdClass $attemptdata
     * @return array
     */
    private static function fetch_question_id_traced(int $cmid, string $component, stdClass $attemptdata): array {
        $data = (object)['componentid' => $cmid, 'component' => $component];

        timeline::start('testenvironment');
        // The span ends also when the step throws (adapter #6).
        try {
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
        } finally {
            timeline::stop('testenvironment');
        }

        // Issue #60: before the first question, the settings are checked again - a template, an import or
        // settings from before the check are not refused by the form.
        if ((int) ($attemptdata->questionsattempted ?? 0) === 0) {
            $testid = (int) ($testenvironment->return_as_array()['id'] ?? 0);
            $blocked = self::preflight($quizsettings, $attemptdata, $testid);
            if ($blocked !== null) {
                return $blocked;
            }
        }

        $selectioncontext = timeline::span(
            'contextcreator',
            fn() => self::get_strategy_selectcontext($quizsettings, $attemptdata)
        );
        $result = timeline::span('strategy', fn() => $teststrategy->return_next_testitem($selectioncontext));
        if (!$result->isOk()) {
            catquiz::set_final_attempt_status($attemptdata->id, $result->get_status());
            // The third element is the status as a code, so the activity can tell why the test ended
            // without reading the message (mod_adaptivequiz #20).
            return [0, $result->getErrorMessage(), (string) $result->get_status()];
        }

        $question = $result->unwrap();
        return [$question->id, "", status::OK];
    }

    /**
     * Checks the settings of a test before its first question (issue #60).
     *
     * Every hard error keeps the attempt from starting, with the reasons: no attempt could meet the settings,
     * and a template, an import or settings from before the check are not refused by the form. Before, only
     * "nothing to play" blocked, and any other contradiction let the attempt run until it ran out of
     * questions. The event settings_inconsistent records the refusal for the CAT manager.
     *
     * @param stdClass $quizsettings
     * @param stdClass $attemptdata
     * @param int $testid
     * @return array|null The answer of fetch_question_id() when the attempt cannot start, null otherwise.
     */
    private static function preflight(stdClass $quizsettings, stdClass $attemptdata, int $testid): ?array {
        $errors = settings_validator::errors(settings_validator::validate($quizsettings));
        if (!$errors) {
            return null;
        }
        \local_catquiz\event\settings_inconsistent::create([
            'context' => \context_system::instance(),
            'other' => [
                'attemptid' => (int) ($attemptdata->id ?? 0),
                'testid' => $testid,
                'codes' => array_column($errors, 'code'),
            ],
        ])->trigger();
        $message = get_string('settingsvalidatorstartblocked', 'local_catquiz', implode(' ', array_column($errors, 'message')));
        if (!empty($attemptdata->id)) {
            catquiz::set_final_attempt_status($attemptdata->id, status::ERROR_NO_ITEMS);
        }
        return [0, $message, status::ERROR_NO_ITEMS];
    }

    /**
     * Prepares the session for a new attempt (issue #146).
     *
     * This purged the whole session cache - and with it the progress, the feedback and the item pool of
     * every other attempt open in the session. Now only what belongs to the new attempt is set up: its
     * start time, and a new generation of the question statistics of the person, read anew at the next
     * selection. The item pool is in a cache of its own and stays.
     *
     * @param int|null $adaptiveattemptid Id of the new activity attempt; without it the start time is
     *     kept for the session, as before.
     * @return void
     * @throws coding_exception
     * @throws cache_exception
     */
    public static function prepare_attempt_caches(?int $adaptiveattemptid = null) {
        global $USER;
        timeline::start('prepare_attempt_caches');
        // The span ends also when the step throws (adapter #6).
        try {
            attempt_cache::upgrade_session_layout();
            if ($adaptiveattemptid) {
                attempt_cache::set('starttime', $adaptiveattemptid, time());
            } else {
                attempt_cache::cache()->set('starttime', time());
            }
            catscale::renew_user_question_stats((int) $USER->id);
        } finally {
            timeline::stop('prepare_attempt_caches');
        }
    }

    /**
     * The time limit of an attempt in seconds, from its test's settings (mod_adaptivequiz #20).
     *
     * The notice of an attempt that ran out of time names this limit - not the time between start and
     * finish, which for an attempt closed by the scheduled task is far longer.
     *
     * @param int $adaptiveattemptid adaptivequiz_attempt.id
     * @return int|null null when the test sets no limit.
     */
    public static function attempt_time_limit(int $adaptiveattemptid): ?int {
        $testenvironment = catquiz::get_testenvironment_by_attemptid($adaptiveattemptid);
        $settings = $testenvironment ? json_decode((string) $testenvironment->json) : null;
        return self::time_limits($settings ?: null)['attempt'];
    }

    /**
     * The time limits of a test's settings in seconds: for the attempt and for one question, null for none.
     *
     * One reading for the selection, the host's notices and dialog and the clean-up task (mod_adaptivequiz #5).
     * A test that switched the limits off explicitly - catquiz_includetimelimit "0" - has none, even with values
     * left in the form; settings from before the switch existed are read by their values alone.
     *
     * @param stdClass|null $settings The test's settings (the json of local_catquiz_tests).
     * @return array ['attempt' => ?int, 'item' => ?int]
     */
    public static function time_limits(?stdClass $settings): array {
        $limits = ['attempt' => null, 'item' => null];
        $switchedoff = $settings !== null && property_exists($settings, 'catquiz_includetimelimit')
            && empty($settings->catquiz_includetimelimit);
        if ($settings === null || $switchedoff) {
            return $limits;
        }
        $group = $settings->catquiz_timelimitgroup ?? null;
        foreach (['attempt' => 'attempt', 'item' => 'item'] as $key => $suffix) {
            $value = $group->{'catquiz_maxtimeper' . $suffix} ?? null;
            $unit = $group->{'catquiz_timeselect_' . $suffix} ?? null;
            if (empty($value) || empty($unit)) {
                continue;
            }
            $seconds = (int) self::get_number_of_seconds((string) $unit, (int) $value);
            $limits[$key] = $seconds > 0 ? $seconds : null;
        }
        return $limits;
    }

    /**
     * The time a person has for one question of an attempt, in seconds; null when the test sets none
     * (mod_adaptivequiz #5: the host offers a new question when it ran out on a pending one).
     *
     * @param int $adaptiveattemptid Id of the attempt of the component: adaptivequiz_attempt.id - not local_catquiz_attempts.id.
     * @return int|null
     */
    public static function item_time_limit(int $adaptiveattemptid): ?int {
        $testenvironment = catquiz::get_testenvironment_by_attemptid($adaptiveattemptid);
        $settings = $testenvironment ? json_decode((string) $testenvironment->json) : null;
        return self::time_limits($settings ?: null)['item'];
    }

    /**
     * Callback for adaptivequiz
     *
     * This is called when the attempt is finished.
     *
     * @param stdClass $adaptivequiz
     * @param mixed $cm
     * @param stdClass $attemptrecord
     *
     * @return string
     *
     */
    public static function attempt_finished(
        stdClass $adaptivequiz,
        mixed $cm,
        stdClass $attemptrecord
    ): string {
        global $DB;

        // Finalisation is owned by attempt_finalizer and is normally
        // triggered from the authoritative status change in
        // adaptivequiz_complete_attempt(). Here we only call it again as an
        // idempotent safety net (in case this render path is reached without the
        // status-change hook having run) and then render the feedback. The end
        // time comes from the attempt's immutable timefinished, never a cache.
        $timefinished = (int) ($DB->get_field(
            'adaptivequiz_attempt',
            'timefinished',
            ['id' => $attemptrecord->id]
        ) ?: ($attemptrecord->timemodified ?? time()));
        attempt_finalizer::finalize(
            (int) $attemptrecord->id,
            $timefinished,
            (string) ($attemptrecord->attemptstopcriteria ?? '')
        );

        // If there was an error before the quiz could be started, return that.
        // Mod_adaptivequiz #22: only an error recorded for this attempt - the session holds the latest one.
        // Issue #146: the error of this attempt, under its own key; the session-wide one only if it is its.
        $cache = attempt_cache::cache();
        $errormsg = $cache->get(attempt_cache::key('catquizerror', (int) $attemptrecord->id));
        if ($errormsg === false && (int) $cache->get('catquizerrorattempt') === (int) $attemptrecord->id) {
            $errormsg = $cache->get('catquizerror');
        }
        if ($errormsg && $attemptrecord->questionsattempted == 0) {
            return get_string($errormsg, 'local_catquiz');
        }
        // If we are here, at least one question was played and we can provide feedback. Mod_adaptivequiz #22:
        // everything is taken from the attempt and its activity - the CAT context from the attempt's test, never
        // from the request.
        $attemptfeedback = new attemptfeedback($attemptrecord->id, 0, null, (int) $adaptivequiz->course);
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
        $attemptcontext = 0;
        if ($attemptdata->questionsattempted > 0) {
            // The running test may (re)create its progress: it brings the settings (issue #149). The third
            // argument is the CAT context of the scale - it used to get the id of the scale itself.
            $progress = progress::load(
                intval($attemptdata->id),
                'mod_adaptivequiz',
                (int) catscale::get_context_id((int) $quizsettings->catquiz_catscales),
                $quizsettings
            );
            $quizsettings = $progress->get_quiz_settings();
            // Issue #157 (T10): a running attempt stays in the CAT context it started in. Its pool, parameters and
            // estimates are of one calibration, also when the scale moves to another context meanwhile.
            $attemptcontext = $progress->get_contextid();
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
        // The same reading as the host's notices and the clean-up task (mod_adaptivequiz #5).
        $limits = self::time_limits($quizsettings);
        $attemptseconds = $limits['attempt'];
        $itemseconds = $limits['item'];

        $firstuseexistingdata = false;
        if (property_exists($quizsettings, 'catquiz_firstquestionreuseexistingdata')) {
            $firstuseexistingdata = $quizsettings->catquiz_firstquestionreuseexistingdata == "1";
        }

        $catcontext = $attemptcontext ?: catscale::get_context_id($quizsettings->catquiz_catscales);
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
            'initial_standarderror' => 1.0,
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
     * The template action of a submitted form (issue #89).
     *
     * A form or a caller from before the issue sends the checkbox "add or edit template" with a name; that is
     * read as before: the chosen template is updated when the name is its own, else a new one is saved.
     *
     * @param stdClass $quizdata
     * @return string One of the TEMPLATE_ACTION_* constants.
     */
    public static function template_action(stdClass $quizdata): string {
        $action = (string) ($quizdata->testenvironment_templateaction ?? '');
        if (in_array($action, [self::TEMPLATE_ACTION_UPDATE, self::TEMPLATE_ACTION_COPY], true)) {
            return $action;
        }
        if (!empty($quizdata->testenvironment_addoredittemplate) && trim((string) ($quizdata->testenvironment_name ?? '')) !== '') {
            global $DB;
            $chosen = (int) ($quizdata->choosetemplate ?? 0);
            $oldname = $chosen && testenvironment::is_template($chosen)
                ? (string) $DB->get_field('local_catquiz_tests', 'name', ['id' => $chosen])
                : null;
            return $oldname !== null && trim($oldname) === trim((string) $quizdata->testenvironment_name)
                ? self::TEMPLATE_ACTION_UPDATE
                : self::TEMPLATE_ACTION_COPY;
        }
        return self::TEMPLATE_ACTION_NONE;
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
            $scaleid = intval($matches[1]);
            $rangeid = intval($matches[2]);
            if (empty($value['itemid'])) {
                /* Issue #51: a range whose editor was not shown - its scale was not opened - comes with
                   the text as saved and without a draft area. Its files stay where they are: saving an
                   empty draft area would delete them. */
                $editor = feedbackclass::editor_value($value);
                $clone->$property = $editor['text'];
                $clone->{$property . 'format'} = $editor['format'];
                $clone->{$property . 'trust'} = trusttext_trusted($context) ? 1 : 0;
                unset($clone->{$property . '_editor'});
                continue;
            }
            if (!property_exists($clone, $property . '_editor')) {
                $clone->{$property . '_editor'} = $clone->$property;
            }
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
