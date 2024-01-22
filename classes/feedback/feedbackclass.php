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
class feedbackclass {

    /**
     * Add Form elements to form.
     * @param MoodleQuickForm $mform
     * @param array $elements
     * @return void
     */
    public static function instance_form_definition(MoodleQuickForm &$mform, array &$elements) {

        global $CFG, $PAGE, $OUTPUT;

        require_once($CFG->libdir .'/datalib.php');

        // Get all Values from the form.
        $data = $mform->getSubmitValues();
        $defaultvalues = $mform->_defaultValues;

        // phpcs:ignore
        // TODO: Display Name of Teststrategy. $teststrategyid = intval($data['catquiz_selectteststrategy']);
        $elements[] = $mform->addElement('header', 'catquiz_feedback',
                get_string('catquiz_feedbackheader', 'local_catquiz'));
        $mform->setExpanded('catquiz_feedback');

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
            ? LOCAL_CATQUIZ_DEFAULT_NUMBER_OF_FEEDBACKS_PER_SCALE : intval($numberoffeedbackspersubscale);

        $element = $mform->addElement(
            'static',
            'disclaimer:numberoffeedbackchange',
            "",
            get_string('disclaimer:numberoffeedbackchange', 'local_catquiz'),
        );
        $elements[] = $element;

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
        // Generate the options for the colors. Same values are applied to all subscales and subfeedbacks.
        $coloroptions = self::get_array_of_colors($numberoffeedbackspersubscale);
        // We need this to close the collapsable header elements.
        $html2 = $OUTPUT->render_from_template('local_catquiz/feedback/feedbackform_collapsible_close', []);
        // Finds all courses user is enroled to.
        $enrolledcourses = enrol_get_my_courses();
        $coursesfromtags = dataapi::get_courses_from_settings_tags() ?? [];
        $courses = array_merge($enrolledcourses, $coursesfromtags);

        foreach ($scales as $scale) {
            $subelements = [];
            $numberoffeedbacksfilledout = 0;

            // Only for parentscales and selected scales we display the feedbackoptions.
            $checkboxchecked = optional_param('catquiz_subscalecheckbox_' . $scale->id, 0, PARAM_INT);
            if ($scale->depth !== 0 && $checkboxchecked !== 1) {
                continue;
            }

            for ($j = 1; $j <= $numberoffeedbackspersubscale; $j++) {
                // We need to create a div tag to "wrap" feedback range.
                $element = $mform->createElement('html',
                '<div data-name="feedback_scale_' . $scale->name . '_range_' . $j. '" data-depth="' . $scale->depth . '" >');
                $element->setName('feedback_scale_' . $scale->id . '_rangestart_' . $j);
                $subelements[] = $mform->addElement($element);

                // Check for each feedback editor field, if there is content.
                // This is the preparation for the header element (to be appended in the end) where we apply the distinction.

                // If reload was triggered (ie via nosubmitbutton), data is set in submitvalues.
                if (isset($data['feedbackeditor_scaleid_'  . $scale->id . '_' . $j])) {
                    $feedback = $data['feedbackeditor_scaleid_'  . $scale->id . '_' . $j];
                } else if (isset($defaultvalues['feedbackeditor_scaleid_'  . $scale->id . '_' . $j])) {
                    // If values of form where saved before, and form is loaded, data is in defaultvalues.
                    $feedback = $defaultvalues['feedbackeditor_scaleid_'  . $scale->id . '_' . $j];
                }
                // Check type and value.
                if (!empty($feedback)) {
                    $feedbacktext = $feedback['text'];
                    $feedbacktext = strip_tags($feedbacktext ?? '');
                }

                if (isset($feedbacktext) && strlen($feedbacktext) > 0) {
                    $numberoffeedbacksfilledout ++;
                }

                // Header for Subfeedback.
                $subelements[] = $mform->addElement('static', 'headingforfeedback' . $scale->id . '_'. $j,
                get_string('feedbacknumber', 'local_catquiz', $j));

                // Define range.
                $element = $mform->addElement(
                    'float',
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
                    'float',
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
                $mform->setType('feedbackeditor_scaleid_' . $scale->id . '_' . $j, PARAM_RAW);

                // Text field for feedback legend. Displayed only for parentscale.
                $subelements[] = $mform->addElement(
                    'text',
                    'feedbacklegend_scaleid_' . $scale->id . '_' . $j,
                    get_string('feedbacklegend', 'local_catquiz'),
                    'size="80"',
                );

                $subelements[] = $mform->addElement(
                    'select',
                    'wb_colourpicker_' .$scale->id . '_' . $j,
                    get_string('feedback_colorrange', 'local_catquiz'),
                    $coloroptions,
                );
                // Preset selected color regarding order of feedbacks.
                $sequencecolors = array_keys($coloroptions);

                $savedcolorvalue = $mform->_defaultValues['wb_colourpicker_' .$scale->id . '_' . $j]
                    ?? $data['wb_colourpicker_' .$scale->id . '_' . $j]
                    ?? 0;

                if (empty($savedcolorvalue)) {
                    $mform->setDefault('wb_colourpicker_' .$scale->id . '_' . $j, $sequencecolors[$j - 1]);
                }

                $subelements[] = $mform->addElement('hidden', 'selectedcolour', '', PARAM_TEXT);
                $PAGE->requires->js_call_amd('local_catquiz/colourpicker', 'init');

                // Enrol to a group.
                // Limit Courses - See GH-183.
                $options = [
                    'multiple' => true,
                    'noselectionstring' => get_string('courseselection', 'local_catquiz'),
                ];
                $select = [
                    0 => get_string('courseselection', 'local_catquiz'),
                ];
                // Check if courses were saved before (ie from other teacher, directly in db) and in this case allow them.
                $preselectcourseids = $mform->_defaultValues['catquiz_courses_' . $scale->id . '_'. $j] ?? [];
                if (!empty($preselectcourseids)) {
                    $newcourses = [];
                    foreach ($preselectcourseids as $preselectcourseid) {
                        $foundcourse = false;
                        foreach ($courses as $course) {
                            if ($preselectcourseid == $course->id) {
                                $foundcourse = true;
                            }
                        }
                        if (!$foundcourse && !empty($preselectcourseid)) {
                            $newcourses[] = get_course((int)$preselectcourseid);
                        }
                    }
                    $courses = array_merge($newcourses, $courses);
                }

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

                // Enrol to a group.

                $element = $mform->addElement(
                    'text',
                    'catquiz_group_' . $scale->id . '_'. $j,
                    get_string('setgrouprenrolmentforscale', 'local_catquiz')
                );
                $mform->addHelpButton('catquiz_group_' . $scale->id . '_' . $j, 'groupenrolmenthelptext', 'local_catquiz');
                $subelements[] = $element;

                // Checkbox messaging of groupselect and courseselect.
                $subelements[] = $mform->addElement('advcheckbox', 'enrolment_message_checkbox_' . $scale->id . '_'. $j,
                get_string('setautonitificationonenrolmentforscale', 'local_catquiz'), null, null, [0, 1]);
                if (!optional_param('enrolment_message_checkbox_' . $scale->id . '_'. $j, 1, PARAM_INT)) {
                    $mform->setDefault('enrolment_message_checkbox_' . $scale->id . '_'. $j, 1);
                }

                // Close of feedback range HTML tag element.
                $element = $mform->createElement('html', '</div>');
                $element->setName('feedback_scale_' . $scale->id . '_rangeend_' . $j);
                $subelements[] = $mform->addElement($element);
            }

            // Add a header for each scale.
            // We check if feedbacks completed partially, entirely or not at all.
            if ($numberoffeedbacksfilledout == 0) {
                // No feedback entries saved in editor.
                $headersuffix = "";
            } else if ($numberoffeedbacksfilledout == $j - 1) {
                // All feedback entries saved in editor.
                $headersuffix = ' : ' . get_string('feedbackcompletedentirely', 'local_catquiz');
            } else {
                // Partially submitted feedback.
                $statusofcompletion = strval($numberoffeedbacksfilledout) . "/" . strval($j - 1);
                $headersuffix = ' : ' . get_string('feedbackcompletedpartially', 'local_catquiz', $statusofcompletion);
            }

            $mform->registerNoSubmitButton('copysettingsforallsubscales_' . $scale->id);
            $subelements[] = $mform->addElement(
                'submit',
                'copysettingsforallsubscales_' . $scale->id,
                get_string('copysettingsforallsubscales', 'local_catquiz'),
                [
                    'data-action' => 'submitFeedbackValues',
                ]
            );

            // Make the different feedback options nested.
            $numberofclosinghtmls = 0;
            if (!isset($previousdepth) || !isset($previousparentscaleid)) {
                $numberofclosinghtmls = 0;
                // If it's the first scale, don't close the html.
            } else if ($scale->depth == $previousdepth) {
                $numberofclosinghtmls = 1;
                // Element on the same level.
            } else if ($scale->parentid != $previousparentscaleid
            && $scale->depth < $previousdepth) {
                $depthdifference = $previousdepth - $scale->depth;
                $numberofclosinghtmls = $depthdifference + 1;
            }

            $previousparentscaleid = $scale->parentid;
            $previousdepth = $scale->depth;

            $headername = get_string('catquizfeedbackheader', 'local_catquiz', $scale->name) . $headersuffix;
            $headerid = 'catquiz_feedback_header_' . $scale->id;
            $collapseid = 'catquiz_feedback_collapse_' . $scale->id;
            $accordionid = 'accordion_header_scaleid_' . $scale->id;
            $dataname = 'catquiz_feedback_header_' . $scale->name;
            $headerdata = [
                'headername' => $headername,
                'headerid' => $headerid,
                'collapseid' => $collapseid,
                'accordionid' => $accordionid,
                'datadepth' => $previousdepth,
                'dataname' => $dataname,
            ];

            // Closing the elements.
            self::add_closing_html($numberofclosinghtmls, $scale->id, $mform, $elements, $html2);

            $html1 = $OUTPUT->render_from_template('local_catquiz/feedback/feedbackform_collapsible_open', $headerdata);

            $element = $mform->createElement('html', $html1);
            $element->setName('header_accordion_start_scale_' . $scale->id);
            $mform->addElement($element);
            $elements[] = $element;

            // Now append elements from loop.
            foreach ($subelements as $element) {
                $elements[] = $element;
            }
        }
        if (!empty($scale)) {
            self::add_closing_html(1, $scale->id, $mform, $elements, $html2);
        }

    }

    /**
     * Add HTML Elements to close collapsable accordion.
     *
     * @param int $counter
     * @param int $scaleid
     * @param mixed $mform
     * @param array $elements
     * @param string $html2
     *
     */
    private static function add_closing_html($counter, $scaleid, &$mform, &$elements, $html2) {
        for ($i = 1; $i <= $counter; $i++) {
            $element = $mform->createElement('html', $html2);
            $element->setName('header_accordion_end_scale_' . $scaleid . '_' . $i);
            $mform->addElement($element);
            $elements[] = $element;
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
        $colorname = get_string('color_' . $color . '_name', 'local_catquiz');

        $coloroptions[$color] = get_string(
            'color_' . $color . '_code',
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
        // See strings for colornames.
        switch ($numberoffeedbackspersubscale) {
            case 1:
                self::add_coloroption("3", $coloroptions);
                break;
            case 2:
                self::add_coloroption("3", $coloroptions);
                self::add_coloroption("6", $coloroptions);
                break;
            case 3:
                self::add_coloroption("3", $coloroptions);
                self::add_coloroption("5", $coloroptions);
                self::add_coloroption("6", $coloroptions);
                break;
            case 4:
                self::add_coloroption('3', $coloroptions);
                self::add_coloroption('4', $coloroptions);
                self::add_coloroption('5', $coloroptions);
                self::add_coloroption('6', $coloroptions);
                break;
            case 5:
                self::add_coloroption('3', $coloroptions);
                self::add_coloroption('4', $coloroptions);
                self::add_coloroption('5', $coloroptions);
                self::add_coloroption('6', $coloroptions);
                self::add_coloroption('7', $coloroptions);
                break;
            case 6:
                self::add_coloroption('2', $coloroptions);
                self::add_coloroption('3', $coloroptions);
                self::add_coloroption('4', $coloroptions);
                self::add_coloroption('5', $coloroptions);
                self::add_coloroption('6', $coloroptions);
                self::add_coloroption('7', $coloroptions);
                break;
            case 7:
                self::add_coloroption('1', $coloroptions);
                self::add_coloroption('2', $coloroptions);
                self::add_coloroption('3', $coloroptions);
                self::add_coloroption('4', $coloroptions);
                self::add_coloroption('5', $coloroptions);
                self::add_coloroption('6', $coloroptions);
                self::add_coloroption('7', $coloroptions);
                break;
            case 7:
                self::add_coloroption('1', $coloroptions);
                self::add_coloroption('2', $coloroptions);
                self::add_coloroption('3', $coloroptions);
                self::add_coloroption('4', $coloroptions);
                self::add_coloroption('5', $coloroptions);
                self::add_coloroption('6', $coloroptions);
                self::add_coloroption('7', $coloroptions);
                break;
            case 8:
                self::add_coloroption('1', $coloroptions); // 1
                self::add_coloroption('2', $coloroptions); // 2
                self::add_coloroption('3', $coloroptions); // 3
                self::add_coloroption('4', $coloroptions); // 4
                self::add_coloroption('5', $coloroptions); // 5
                self::add_coloroption('6', $coloroptions); // 6
                self::add_coloroption('7', $coloroptions); // 7
                self::add_coloroption('8', $coloroptions); // 8
                break;
        }

        return $coloroptions;
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
     * Returns lower or upper value for scales, depending on bool.
     *
     * @param mixed $nroptions
     * @param mixed $optioncounter
     * @param bool $lower
     *
     * @return float
     *
     */
    public static function return_limits_for_scale($nroptions, $optioncounter, bool $lower) {

        // Calculate equal default values for limits in scales.
        $sizeofrange = abs(LOCAL_CATQUIZ_PERSONABILITY_LOWER_LIMIT - LOCAL_CATQUIZ_PERSONABILITY_UPPER_LIMIT);
        $increment = round($sizeofrange / $nroptions, 2);

        if ($lower) {
            return round(LOCAL_CATQUIZ_PERSONABILITY_LOWER_LIMIT + ($optioncounter - 1) * $increment, 2);
        } else {
            return round(LOCAL_CATQUIZ_PERSONABILITY_LOWER_LIMIT + $optioncounter * $increment, 2);
        }
    }
}
