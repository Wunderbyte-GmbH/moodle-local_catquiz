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

use local_catquiz\teststrategy\info;

/**
 * Behat data generator for local_catquiz.
 *
 * @package   local_catquiz
 * @category  test
 * @copyright 2024 Andrii Semenets
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_catquiz_generator extends behat_generator_base {

    /**
     * Get a list of the entities that Behat can create using the generator step.
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'questions' => [
                'datagenerator' => 'catquiz_questions',
                'required' => ['filepath', 'filename', 'course'],
                'switchids' => ['course' => 'courseid'],
            ],
            'importedcatscales' => [
                'datagenerator' => 'catquiz_importedcatscales',
                'required' => ['filepath', 'filename'],
            ],
            'testsettings' => [
                'datagenerator' => 'catquiz_testsettings',
                'required' => ['course', 'adaptivecatquiz', 'catmodel', 'catscales', 'cateststrategy'],
                'switchids' => ['course' => 'courseid',
                                'adaptivecatquiz' => 'adaptivecatquizid',
                                'catscales' => 'catscalesid',
                                'cateststrategy' => 'cateststrategyid',
                            ],
            ],
        ];
    }

    /**
     * Get the adaptivecatquiz ID using an idnumber.
     *
     * @param string $adaptivecatquizidnumber
     * @return int The id
     */
    protected function get_adaptivecatquiz_id(string $adaptivecatquizidnumber): int {
        global $DB;

        if (!$id = $DB->get_field('course_modules', 'instance', ['idnumber' => $adaptivecatquizidnumber])) {
            throw new Exception('The specified adaptivecatquiz with name "' . $adaptivecatquizidnumber . '" does not exist');
        }
        return $id;
    }

    /**
     * Get the catscales ID using a name.
     *
     * @param string $catscalesname
     * @return int The id
     */
    protected function get_catscales_id(string $catscalesname): int {
        global $DB;

        if (!$id = $DB->get_field('local_catquiz_catscales', 'id', ['name' => $catscalesname])) {
            throw new Exception('The specified catscales with name "' . $catscalesname . '" does not exist');
        }
        return $id;
    }

    /**
     * Get the cateststrategy ID using a name.
     *
     * @param string $cateststrategyname
     * @return int The id
     */
    protected function get_cateststrategy_id(string $cateststrategyname): int {
        global $CFG;

        require_once($CFG->dirroot . '/local/catquiz/lib.php');

        $strategies = info::return_available_strategies();
        foreach ($strategies as $strategy) {

            if (isset($strategy->id) && $strategy->get_description() == $cateststrategyname) {
                return $strategy->id;
            }
        }
        // If we come here, we will just throw error.
        throw new Exception('The specified cateststrategy with name "' . $cateststrategyname . '" does not exist');
    }
}
