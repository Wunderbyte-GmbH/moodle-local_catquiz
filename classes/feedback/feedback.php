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
                'scaleid_' . $scale->id . '_intro', $scale->name, get_string('setcoursesforscaletext', 'local_catquiz', $scale->name));

            $elements[] = $mform->addElement('autocomplete',
                'scaleid_' . $scale->id . '_courseid', '', $coursesarray, $options);

            $elements[] = $mform->addElement('text',
                'scaleid_' . $scale->id . '_lowerlimit', get_string('lowerlimit', 'local_catquiz'));
            $mform->settype('scaleid_' . $scale->id . '_lowerlimit', PARAM_FLOAT);

            $elements[] = $mform->addElement('textarea',
                'scaleid_' . $scale->id . '_feedback', get_string('feedback', 'core'), '');

        }
    }

    /**
     *
     * @param array $result
     * @return void
     */
    public static function inscribe_users_to_failed_scales(array $result) {




    }

}
