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
 * class feedback.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\feedback;

use cache;
use coding_exception;
use dml_exception;
use ddl_exception;
use local_catquiz\catscale;
use local_catquiz\data\dataapi;
use local_catquiz\local\result;
use local_catquiz\feedback\info;
use local_catquiz\feedback\preselect_task;
use local_catquiz\wb_middleware_runner;
use moodle_exception;
use MoodleQuickForm;
use stdClass;

/**
 * Base class for test strategies.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedback {

    /**
     * Add Form elements to form.
     * @param MoodleQuickForm $mform
     * @param array $elements
     * @return void
     */
    public static function instance_form_definition(MoodleQuickForm &$mform, array &$elements) {

        global $CFG, $PAGE;

        require_once($CFG->libdir .'/datalib.php');

        // Get all Values from the form.
        $data = $mform->getSubmitValues();

        // phpcs:ignore
        // TODO: Display Name of Teststrategy. $teststrategyid = intval($data['catquiz_selectteststrategy']);
        $elements[] = $mform->addElement('header', 'catquiz_feedback',
                get_string('catquiz_feedbackheader', 'local_catquiz'));
        $mform->setExpanded('catquiz_feedback');

        // phpcs:ignore Squiz.PHP.CommentedOutCode.Found, moodle.Commenting.InlineComment.NotCapital
        // $scaleids = catscale::get_subscale_ids(0);

        $courses = get_courses("all", "c.sortorder ASC", "c.id, c.fullname");

        $coursesarray = [];
        foreach ($courses as $course) {
            if ($course->id == 1) {
                continue;
            }
            $coursesarray[$course->id] = $course->fullname;
        }

        $options = [
            'multiple' => true,
            'noselectionstring' => get_string('noselection', 'local_catquiz'),
        ];

        $selectedparentscale = optional_param('catquiz_catscales', 0, PARAM_INT);

        if (!empty($selectedparentscale)) {
            $scales = dataapi::get_catscale_and_children($selectedparentscale, true);
        } else {
            // Right now, we just get all subscales.
            $scales = dataapi::get_all_catscales();
        }

        // Select to set number of feedback options per subscale.
        $options = [];
        for ($i = 1; $i <= 8; $i++) {
            $options[$i] = $i;
        }

        $numberoffeedbackspersubscale = $data['numberoffeedbackoptionsselect']
            ?? optional_param('numberoffeedbackoptionsselect', 0, PARAM_INT);
        $numberoffeedbackspersubscale = empty($numberoffeedbackspersubscale)
            ? DEFAULT_NUMBER_OF_FEEDBACKS_PER_SCALE : intval($numberoffeedbackspersubscale);

        $element = $mform->addElement(
            'select',
            'numberoffeedbackoptionsselect',
            get_string('numberoffeedbackoptionpersubscale', 'local_catquiz'),
            $options,
            ['data-on-change-action' => 'numberOfFeedbacksSubmit'],
        );
        $element->setValue($numberoffeedbackspersubscale);
        $mform->addHelpButton('numberoffeedbackoptionsselect', 'numberoffeedbackoptionpersubscale', 'local_catquiz');
        $elements[] = $element;

        // Button to attach JavaScript to reload the form.
        $mform->registerNoSubmitButton('submitnumberoffeedbackoptions');
        $elements[] = $mform->addElement('submit', 'submitnumberoffeedbackoptions', 'numberoffeedbackoptionssubmit',
            [
            'class' => 'd-none',
            'data-action' => 'submitNumberOfFeedbackOptions',
        ]);

        foreach ($scales as $scale) {
            $subelements = [];
            $numberoffeedbacksfilledout = 0;

            // Only for parentscales and selected scales we display the feedbackoptions.
            $checkboxchecked = optional_param('catquiz_subscalecheckbox_' . $scale->id, 0, PARAM_INT);
            if ($scale->depth !== 0 && $checkboxchecked !== 1) {
                continue;
            }

            for ($j = 1; $j <= $numberoffeedbackspersubscale; $j++) {
                // Check for each feedback editor field, if there is content.
                // This is the preparation for the header element (to be appended in the end) where we apply the distinction.

                // TODO: Maybe find a better check if form is newly loaded or saved.
                if (count($data) > 1) {
                    $feedback = optional_param_array('feedbackeditor_scaleid_'  . $scale->id . '_' . $j, [], PARAM_RAW);
                    if ($feedback != []) {
                        $feedbacktext = $feedback['text'];
                    }
                } else {
                    $feedback = optional_param('feedbackeditor_scaleid_'  . $scale->id . '_' . $j, "", PARAM_TEXT);
                    if (!empty($feedback)) {
                        $jsonobject = json_decode($feedback);
                        $feedbacktext = strip_tags($jsonobject->text);
                    }
                }
                    if (isset($feedbacktext) && strlen($feedbacktext) > 0) {
                        $numberoffeedbacksfilledout ++;
                    }

                // Header for Subfeedback.
                $subelements[] = $mform->addElement('static', 'headingforfeedback' . $scale->id . '_'. $j,
                get_string('feedbacknumber', 'local_catquiz', $j));

                // Define range.
                $element = $mform->addElement(
                    'text',
                    'feedback_scaleid_limit_lower_'. $scale->id . '_' . $j,
                     get_string('lowerlimit', 'local_catquiz'
                ));
                $mform->settype('feedback_scaleid_limit_lower_'. $scale->id . '_' . $j, PARAM_FLOAT);

                // If the Element is new, we set the default.
                // If we get a value here, the overriding value is set in the set_data_after_definition function.
                $lowerlimit = $data['feedback_scaleid_limit_lower_'. $scale->id . '_' . $j] ?? null;
                if ($lowerlimit === null) {
                    $lowerlimit = self::return_limits_for_scale($numberoffeedbackspersubscale, $j, true);
                    $element->setValue($lowerlimit);
                }
                $subelements[] = $element;

                $element = $mform->addElement(
                    'text',
                    'feedback_scaleid_limit_upper_'. $scale->id . '_' . $j,
                    get_string('upperlimit', 'local_catquiz'
                ));
                $mform->settype('feedback_scaleid_limit_upper_' . $scale->id . '_' . $j, PARAM_FLOAT);

                // If the Element is new, we set the default.
                // If we get a value here, the overriding value is set in the set_data_after_definition function.
                $upperlimit = $data['feedback_scaleid_limit_upper_'. $scale->id . '_' . $j] ?? null;
                if ($upperlimit === null) {
                    $upperlimit = self::return_limits_for_scale($numberoffeedbackspersubscale, $j, false);
                    $element->setValue($upperlimit);
                }
                $subelements[] = $element;

                // TODO Switch to check which colors should be displayed.
                $options = self::get_array_of_colors($numberoffeedbackspersubscale);

                $subelements[] = $mform->addElement(
                    'select',
                    'wb_colourpicker_' .$scale->id . '_' . $j,
                    get_string('feedback_colorrange', 'local_catquiz'),
                    $options
                );

                $subelements[] = $mform->addElement('hidden', 'selectedcolour', '', PARAM_TEXT);
                // We have require JS to click no submit button on change of test environment.
                $PAGE->requires->js_call_amd('local_catquiz/colourpicker', 'init');

                // Rich text field for subfeedback.
                $subelements[] = $mform->addElement(
                    'editor',
                    'feedbackeditor_scaleid_' . $scale->id . '_' . $j,
                    get_string('feedback', 'core'),
                    ['rows' => 10],
                    [
                        'maxfiles' => EDITOR_UNLIMITED_FILES,
                        'noclean' => true, CONTEXT_SYSTEM,
                        'subdirs' => true,
                    ]);
                    $mform->setType('feedbackeditor_scaleid_' . $scale->id  . $j, PARAM_RAW);

                // Enrole to a group.
                $groups = groups_get_all_groups(intval($course->id));
                $options = [
                    'multiple' => false,
                    'noselectionstring' => get_string('groupselection', 'local_catquiz'),
                ];
                $select = [
                    0 => get_string('groupselection', 'local_catquiz'),
                ];
                foreach ($groups as $group) {
                    $select[$group->id] = $group->name;
                }
                $subelements[] = $mform->addElement(
                    'autocomplete',
                    'catquiz_groups_' . $scale->id . '_'. $j,
                    get_string('setgrouprenrolmentforscale', 'local_catquiz'),
                    $select,
                    $options
                );
                $mform->addHelpButton('catquiz_groups_' . $scale->id . '_'. $j, 'setgrouprenrolmentforscale', 'local_catquiz');

                // Enrole to a group.
                // Limit Courses - See GH-183.
                $courses = enrol_get_my_courses();
                $options = [
                    'multiple' => false,
                    'noselectionstring' => get_string('courseselection', 'local_catquiz'),
                ];
                $select = [
                    0 => get_string('courseselection', 'local_catquiz'),
                ];
                foreach ($courses as $course) {
                    $select[$course->id] = $course->fullname;
                }
                $subelements[] = $mform->addElement(
                    'autocomplete',
                    'catquiz_courses_' . $scale->id . '_'. $j,
                    get_string('setcourseenrolmentforscale', 'local_catquiz'),
                    $select,
                    $options
                );
                $mform->addHelpButton('catquiz_courses_' . $scale->id . '_' . $j, 'setcourseenrolmentforscale', 'local_catquiz');

                // Checkbox dependent on groupselect and courseselect.
                $subelements[] = $mform->addElement('advcheckbox', 'enrolement_message_checkbox' . $scale->id . '_'. $j,
                get_string('setautonitificationonenrolmentforscale', 'local_catquiz'), null, null, [0, 1]);
                $mform->setDefault('enrolement_message_checkbox' . $scale->id . '_'. $j, 1);
                // TODO: If none of both is selected, hide properly. $mform->hideIf('enrolement_message_checkbox' . $scale->id . '_'. $j, 'catquiz_groups_' . $scale->id . '_'. $j, 'eq', 0);
            }

            // Only for the parentscale (=first form), we display to button to apply values for all subscales.
            if ($scale->parentid == 0) {
                // TODO: Attach function!
                $mform->registerNoSubmitButton('copysettingsforallsubscales');
                $subelements[] = $mform->addElement(
                    'submit',
                    'copysettingsforallsubscales',
                    get_string('copysettingsforallsubscales', 'local_catquiz'),
                    [
                        'data-action' => 'submitFeedbackValues',
                    ]
                );
            }

            // Add a header for each scale.
            // We check if feedbacks completed partially, entirely or not at all.
            if ($numberoffeedbacksfilledout == 0) {
                // No feedback entries saved in editor.
                $headersuffix = "";
                $expanded = true;
            } else if ($numberoffeedbacksfilledout == $j - 1) {
                // All feedback entries saved in editor.
                $headersuffix = ' : ' . get_string('feedbackcompletedentirely', 'local_catquiz');
                $expanded = false;
            } else {
                // Partially submitted feedback
                $statusofcompletion = $numberoffeedbacksfilledout . '/' . $j - 1;
                $headersuffix = ' : ' . get_string('feedbackcompletedpartially', 'local_catquiz', $statusofcompletion);
                $expanded = true;
            }
            $elements[] = $mform->addElement('header', 'catquiz_feedback_header_' . $scale->id,
            get_string('catquizfeedbackheader', 'local_catquiz', $scale->name) . $headersuffix);
            if ($expanded) {
                $mform->setExpanded('catquiz_feedback_header_' . $scale->id);
            }

            // Now append elements from loop.
            foreach ($subelements as $element) {
                $elements[] = $element;
            }

        }
    }

    /**
     * Fill coloroptions array with strings.
     *
     * @param string $color
     * @param array $coloroptions
     *
     */
    public static function add_coloroption(string $color, array &$coloroptions) {
        $colorname = get_string('colorpicker_color_'. $color, 'local_catquiz');
        $coloroptions[$colorname] = get_string(
            'colorvalue_'. $color,
            'local_catquiz'
        );
    }

    /**
     * Get right number and type of colors.
     *
     * @param int $numberoffeedbackspersubscale
     * @return array $coloroptions
     *
     */
    public static function get_array_of_colors($numberoffeedbackspersubscale) {

        $coloroptions = [];
        // Depending of the number of options, different colors will be chosen.
        switch ($numberoffeedbackspersubscale) {
            case 1:
                self::add_coloroption("red", $coloroptions);
                break;
            case 2:
                self::add_coloroption("red", $coloroptions);
                self::add_coloroption("lightgreen", $coloroptions);
                break;
            case 3:
                self::add_coloroption("red", $coloroptions);
                self::add_coloroption("yellow", $coloroptions);
                self::add_coloroption("lightgreen", $coloroptions);
                break;
            case 4:
                self::add_coloroption('red', $coloroptions);
                self::add_coloroption('orange', $coloroptions);
                self::add_coloroption('yellow', $coloroptions);
                self::add_coloroption('lightgreen', $coloroptions);
                break;
            case 5:
                self::add_coloroption('red', $coloroptions);
                self::add_coloroption('orange', $coloroptions);
                self::add_coloroption('yellow', $coloroptions);
                self::add_coloroption('lightgreen', $coloroptions);
                self::add_coloroption('darkgreen', $coloroptions);
                break;
            case 6:
                self::add_coloroption('darkred', $coloroptions);
                self::add_coloroption('red', $coloroptions);
                self::add_coloroption('orange', $coloroptions);
                self::add_coloroption('yellow', $coloroptions);
                self::add_coloroption('lightgreen', $coloroptions);
                self::add_coloroption('darkgreen', $coloroptions);
                break;
            case 7:
                self::add_coloroption('black', $coloroptions);
                self::add_coloroption('darkred', $coloroptions);
                self::add_coloroption('red', $coloroptions);
                self::add_coloroption('orange', $coloroptions);
                self::add_coloroption('yellow', $coloroptions);
                self::add_coloroption('lightgreen', $coloroptions);
                self::add_coloroption('darkgreen', $coloroptions);
                break;
            case 7:
                self::add_coloroption('black', $coloroptions);
                self::add_coloroption('darkred', $coloroptions);
                self::add_coloroption('red', $coloroptions);
                self::add_coloroption('orange', $coloroptions);
                self::add_coloroption('yellow', $coloroptions);
                self::add_coloroption('lightgreen', $coloroptions);
                self::add_coloroption('darkgreen', $coloroptions);
                break;
            case 8:
                self::add_coloroption('black', $coloroptions);
                self::add_coloroption('darkred', $coloroptions);
                self::add_coloroption('red', $coloroptions);
                self::add_coloroption('orange', $coloroptions);
                self::add_coloroption('yellow', $coloroptions);
                self::add_coloroption('lightgreen', $coloroptions);
                self::add_coloroption('darkgreen', $coloroptions);
                self::add_coloroption('white', $coloroptions);
                break;
        }

        return $coloroptions;
    }



    /**
     * Takes the result of a test and applies the after test actions.
     * Right now, it's just very limited.
     * As we don't have the correct structure, we assume the following:
     *
     * @param int $quizid
     * @param array $result
     * @param string $component
     * @return void
     */
    public static function inscribe_users_to_failed_scales(
        int $quizid,
        array $result,
        string $component = 'mod_adaptivequiz'
        ) {

        global $USER;

        // First, we need to find out the settings for the current text.
        // We use a function to extract the data from the stored json.
        $settings = self::return_feedback_settings_from_json($quizid, $component);

        // We run through all the scales we got feedback for.
        foreach ($result['scales'] as $scaleid => $scale) {

            // If we find settings for a scale...
            if (isset($settings[$scaleid])) {
                // We check if we are below the lower threshhold.
                $personability = $scale['personability'];
                $lowerlimit = (float)$settings[$scaleid]['lowerlimit'];
                $courseids = $settings[$scaleid]["courseids"];

                if ($personability < $lowerlimit
                    && !empty($courseids)) {

                    // Do the course inscription of the current user.
                    foreach ($courseids as $courseid) {

                        self::enrol_user($USER->id, $courseid);
                    }
                }
            }

        }

    }

    /**
     * Function to access test record and return the settings relevant for feedback.
     * @param int $quizid
     * @param string $component
     * @return array
     */
    private static function return_feedback_settings_from_json(int $quizid, string $component) {

        global $DB;

        $test = $DB->get_record('local_catquiz_tests', ['componentid' => $quizid, 'component' => $component]);

        $settings = json_decode($test->json);

        $returnarray = [];
        foreach ($settings as $key => $value) {
            if (strpos($key, 'feedback_scaleid_') === 0) {

                list($a, $b, $scaleid, $field) = explode('_', $key);

                $returnarray[$scaleid][$field] = $value;

            }
        }

        return $returnarray;
    }

    /**
     * Function to enrol user to course.
     * @param int $userid
     * @param int $courseid
     * @param int $roleid
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     * @throws ddl_exception
     * @throws moodle_exception
     */
    public static function enrol_user(int $userid, int $courseid, int $roleid = 0) {
        global $DB;

        if (!enrol_is_enabled('manual')) {
            return; // Manual enrolment not enabled.
        }

        if (!$enrol = enrol_get_plugin('manual')) {
            return; // No manual enrolment plugin.
        }
        if (!$instances = $DB->get_records(
                'enrol',
                ['enrol' => 'manual', 'courseid' => $courseid, 'status' => ENROL_INSTANCE_ENABLED],
                'sortorder,id ASC'
            )) {
            return; // No manual enrolment instance on this course.
        }

        $instance = reset($instances); // Use the first manual enrolment plugin in the course.

        $enrol->enrol_user($instance, $userid, ($roleid > 0 ? $roleid : $instance->roleid));
    }


    /**
     * Returns lower or upper value for scales, depending on bool.
     *
     * @param mixed $nroptions
     * @param mixed $optioncounter
     * @param bool $lower
     *
     * @return int
     *
     */
    public static function return_limits_for_scale($nroptions, $optioncounter, bool $lower) {

        // Calculate equal default values for limits in scales.
        $sizeofrange = abs(PERSONABILITY_LOWER_LIMIT - PERSONABILITY_UPPER_LIMIT);
        $increment = $sizeofrange / $nroptions;

        if ($lower) {
            return PERSONABILITY_LOWER_LIMIT + ($optioncounter - 1) * $increment;
        } else {
            return PERSONABILITY_LOWER_LIMIT + $optioncounter * $increment;
        }
    }
}
