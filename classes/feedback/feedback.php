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

        global $CFG, $USER;

        require_once($CFG->libdir .'/datalib.php');

        // Get all Values from the form.
        $data = $mform->exportValues();

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
        for ($i = 1; $i <= 10; $i++) {
            $options[$i] = $i;
        }
        $numbersselect = $mform->addElement(
            'select',
            'numberoffeedbackoptionsselect',
            get_string('numberoffeedbackoptionpersubscale', 'local_catquiz'),
            $options,
            ['data-on-change-action' => 'numberOfFeedbacksSubmit'],
        );
        // $numbersselect->setMultiple(true);
        $mform->addHelpButton('numberoffeedbackoptionsselect', 'numberoffeedbackoptionpersubscale', 'local_catquiz');
        $mform->setDefault('numberoffeedbackoptionsselect', DEFAULT_NUMBER_OF_FEEDBACKS_PER_SCALE);
        $elements[] = $numbersselect;

        // Button to attach JavaScript to reload the form.
        $mform->registerNoSubmitButton('submitnumberoffeedbackoptions');
        $elements[] = $mform->addElement('submit', 'submitnumberoffeedbackoptions', 'numberoffeedbackoptionssubmit',
            [
            'class' => 'd-none',
            'data-action' => 'submitNumberOfFeedbackOptions',
        ]);

        // Get data from select.
        $numberoffeedbackspersubscale = intval($numbersselect->_values[0]) ?? DEFAULT_NUMBER_OF_FEEDBACKS_PER_SCALE;

        // Calculate equal default values for limits in scales.
        $sizeofrange = abs(PERSONABILITY_LOWER_LIMIT - PERSONABILITY_UPPER_LIMIT);
        $increment = $sizeofrange / $numberoffeedbackspersubscale;

        $countfeedback = 1;
        foreach ($scales as $scale) {
            // Add a header for each scale.
            $elements[] = $mform->addElement('header', 'catquiz_feedback_header_' . $scale->id,
                get_string('catquizfeedbackheader', 'local_catquiz', $scale->name));

            // $elements[] = $mform->addElement('autocomplete',
            //      'feedback_scaleid_' . $scale->id . '_courseids', '', $coursesarray, $options);

            // // $elements[] = $mform->addElement(
            // //     'static',
            // //     'feedback_scaleid_' . $scale->id . '_intro',
            // //     $scale->name,
            // //     get_string('setcoursesforscaletext', 'local_catquiz', $scale->name));
            // $mform->hideIf(
            //     'feedback_scaleid_' . $scale->id . '_courseids',
            //     'catquiz_subscalecheckbox_' . $scale->id,
            //     'neq',
            //     1);
            // $mform->hideIf(
            //     'feedback_scaleid_' . $scale->id . '_intro',
            //     'catquiz_catcatscales',
            //     'neq',
            //     $scale->parentid);


            // TODO: Add missing elements: Anzahl Feedbackoptionen, Farbbereich

            // No Submit Button to apply values for all subscales.
            // TODO: Attach function!
            $mform->registerNoSubmitButton('copysettingsforallsubscales');
            $elements[] = $mform->addElement('submit', 'copysettingsforallsubscales', get_string('copysettingsforallsubscales', 'local_catquiz'),
                [
                    //'class' => 'd-none',
                    'data-action' => 'submitFeedbackValues',
                ]);

            // TODO: Display range of ability in one row.
            $elements[] = $mform->addElement('text',
                    'feedback_scaleid_' . $scale->id . '_lowerlimit', get_string('lowerlimit', 'local_catquiz'));
            $mform->settype('feedback_scaleid_' . $scale->id . '_lowerlimit', PARAM_FLOAT);
            $elements[] = $mform->addElement('text',
            'feedback_scaleid_' . $scale->id . '_upperlimit', get_string('upperlimit', 'local_catquiz'));
            $mform->settype('feedback_scaleid_' . $scale->id . '_upperlimit', PARAM_FLOAT);

            $elements[] = $mform->addElement('editor', 'feedbackeditor_scaleid_' . $scale->id, get_string('feedback', 'core'), array('rows' => 10), array('maxfiles' => EDITOR_UNLIMITED_FILES,
                    'noclean' => true, CONTEXT_SYSTEM, 'subdirs' => true));
                $mform->setType('feedbackeditor_scaleid_' . $scale->id, PARAM_RAW); // no XSS prevention here, users must be trusted

            // Display group- and courseselect under which condition?
            $condition = true;
            if  ($condition) {
                $groups = groups_get_all_groups(intval($course->id));
                $options = [
                    'multiple' => false,
                    'noselectionstring' => get_string('groupselection', 'local_catquiz'),
                ];
                $select = [];
                foreach ($groups as $group) {
                    $select[$group->id] = $group->name;
                }
                $elements[] = $mform->addElement(
                    'autocomplete',
                    'catquiz_groups_' . $scale->id,
                    get_string('setgrouprenrolmentforscale', 'local_catquiz'),
                    $select,
                    $options
                );
                // TODO: Set default unselected.
                //$mform->setDefault('catquiz_groups_' . $scale->id', 0);
                $mform->addHelpButton('catquiz_groups_' . $scale->id, 'setgrouprenrolmentforscale', 'local_catquiz');


                // Should we really fetch all courses?
                $courses = get_courses("all");
                $options = [
                    'multiple' => false,
                    'noselectionstring' => get_string('courseselection', 'local_catquiz'),
                ];
                $select = [];
                foreach ($courses as $course) {
                    $select[$course->id] = $course->fullname;
                }
                $elements[] = $mform->addElement(
                    'autocomplete',
                    'catquiz_courses_' . $scale->id,
                    get_string('setcourseenrolmentforscale', 'local_catquiz'),
                    $select,
                    $options
                );
                // TODO: Set default unselected.
                //$mform->setDefault('catquiz_courses_' . $scale->id, 0);
                $mform->addHelpButton('catquiz_courses_' . $scale->id, 'setcourseenrolmentforscale', 'local_catquiz');
            }

                // Enrole to a group.
                $groups = groups_get_all_groups(intval($course->id));
                $options = [
                    'multiple' => false,
                    'noselectionstring' => get_string('groupselection', 'local_catquiz'),
                ];
                $select = [];
                foreach ($groups as $group) {
                    $select[$group->id] = $group->name;
                }
                $elements[] = $mform->addElement(
                    'autocomplete',
                    'catquiz_groups_' . $scale->id . $j,
                    get_string('setgrouprenrolmentforscale', 'local_catquiz'),
                    $select,
                    $options
                );
                // TODO: Set default unselected.
                $mform->addHelpButton('catquiz_groups_' . $scale->id . $j, 'setgrouprenrolmentforscale', 'local_catquiz');

                // Enrole to a group.
                // Limit Courses - See GH-183.
                $courses = enrol_get_my_courses();
                $options = [
                    'multiple' => false,
                    'noselectionstring' => get_string('courseselection', 'local_catquiz'),
                ];
                $select = [];
                foreach ($courses as $course) {
                    $select[$course->id] = $course->fullname;
                }
                $elements[] = $mform->addElement(
                    'autocomplete',
                    'catquiz_courses_' . $scale->id . $j,
                    get_string('setcourseenrolmentforscale', 'local_catquiz'),
                    $select,
                    $options
                );
                // TODO: Set default unselected.
                $mform->addHelpButton('catquiz_courses_' . $scale->id  . $j, 'setcourseenrolmentforscale', 'local_catquiz');

                // Checkbox dependent on groupselect and courseselect.
                $elements[] = $mform->addElement('advcheckbox', 'enrolement_message_checkbox' . $scale->id . $j,
                get_string('setautonitificationonenrolmentforscale', 'local_catquiz'), null, null, [0, 1]);
                $mform->setDefault('enrolement_message_checkbox' . $scale->id . $j, 1);
                // TODO: If none of both is selected, hide properly. $mform->hideIf('enrolement_message_checkbox' . $scale->id . $j, 'catquiz_groups_' . $scale->id . $j, 'eq', 0);
            }

            // Only for the first form, we display to button to apply values for all subscales.
            if ($countfeedback === 1) {
                // TODO: Attach function!
                $mform->registerNoSubmitButton('copysettingsforallsubscales');
                $elements[] = $mform->addElement('submit', 'copysettingsforallsubscales', get_string('copysettingsforallsubscales', 'local_catquiz'),
                    [
                        // 'class' => 'd-none',
                        'data-action' => 'submitFeedbackValues',
                    ]);
            }
            $countfeedback ++;
        }
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

}
