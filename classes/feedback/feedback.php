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
     * @param local_catquiz\feedback\MoodleQuickForm $mform
     * @param array $elements
     * @return void
     */
    public static function instance_form_definition(MoodleQuickForm &$mform, array &$elements) {

        global $CFG, $DB;

        require_once($CFG->libdir .'/datalib.php');

        $elements[] = $mform->addElement('header', 'catquiz_feedback',
                get_string('catquiz_feedbackheader', 'local_catquiz'));
        $mform->setExpanded('catquiz_feedback');

        // phpcs:ignore Squiz.PHP.CommentedOutCode.Found, moodle.Commenting.InlineComment.NotCapital
        // $scaleids = catscale::get_subscale_ids(0);

        $courses = get_courses("all", "c.sortorder ASC", "c.id, c.fullname");

        $coursesarray = [];
        foreach ($courses as $course) {
            $coursesarray[$course->id] = $course->fullname;
        }

        $options = array(
            'multiple' => true,
            'noselectionstring' => get_string('noselection', 'local_catquiz'),
        );

        // Right now, we just get all subscales.
        $scales = $DB->get_records('local_catquiz_catscales');

        foreach ($scales as $scale) {

            $elements[] = $mform->addElement('static',
                'feedback_scaleid_' . $scale->id . '_intro', $scale->name, get_string('setcoursesforscaletext', 'local_catquiz', $scale->name));

            $elements[] = $mform->addElement('autocomplete',
                'feedback_scaleid_' . $scale->id . '_courseids', '', $coursesarray, $options);

            $elements[] = $mform->addElement('text',
                'feedback_scaleid_' . $scale->id . '_lowerlimit', get_string('lowerlimit', 'local_catquiz'));
            $mform->settype('feedback_scaleid_' . $scale->id . '_lowerlimit', PARAM_FLOAT);

            $elements[] = $mform->addElement('textarea',
                'feedback_scaleid_' . $scale->id . '_feedback', get_string('feedback', 'core'), '');

        }
    }

    /**
     * Takes the result of a test and applies the after test actions.
     * Right now, it's just very limited.
     * As we don't have the correct structure, we assume the following:
     *
     * @param array $result
     * @return void
     */
    public static function inscribe_users_to_failed_scales(
        int $quizid,
        string $component = 'mod_adaptivequiz',
        array $result = []) {

        global $USER;

        // We use this structure just as a template to be able to execute the function.
        // The treatment should be adjusted according to the actual result data.
        $result = [
            'scales' => [
                1 => [
                    'scaleid' => 1,
                    'name' => "scale1",
                    'personability' => 0.1,
                ],
                2 => [
                    'scaleid' => 2,
                    'name' => "scale2",
                    'personability' => -0.2,
                ],
            ],
        ];

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
                $courseids = $settings[$scaleid]["courseid"];

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
     * @return void
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
        if (!$instances = $DB->get_records('enrol',
                array('enrol' => 'manual', 'courseid' => $courseid,
                    'status' => ENROL_INSTANCE_ENABLED), 'sortorder,id ASC')) {
            return; // No manual enrolment instance on this course.
        }

        $instance = reset($instances); // Use the first manual enrolment plugin in the course.

        $enrol->enrol_user($instance, $userid, ($roleid > 0 ? $roleid : $instance->roleid));
    }

}
