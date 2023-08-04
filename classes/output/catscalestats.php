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

namespace local_catquiz\output;

use coding_exception;
use local_catquiz\catquiz;
use local_catquiz\data\dataapi;
use local_catquiz\subscription;
use templatable;
use renderable;

/**
 * Renderable class for the catscales page
 *
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH
 * @author     David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catscalestats {

    /**
     * @var integer
     */
    private int $num_assigned_catscales = 0;

    /**
     * @var integer
     */
    private int $num_assigned_tests = 0;

    /**
     * @var integer
     */
    private int $num_assigned_questions = 0;

    /**
     * @var string
     */
    private string $last_calculated = "";

    /**
     *
     */
    public function __construct() {
        $this->get_data_from_db();
    }

    /**
     * @return void
     * @throws coding_exception
     */
    private function get_data_from_db() {
        global $USER, $DB;

        list($sql, $params) = catquiz::get_sql_for_number_of_assigned_catscales($USER->id);
        $this->num_assigned_catscales = $DB->count_records_sql($sql, $params);
        list($sql, $params) = catquiz::get_sql_for_number_of_assigned_tests($USER->id);
        $this->num_assigned_tests = $DB->count_records_sql($sql, $params);
        list($sql, $params) = catquiz::get_sql_for_number_of_assigned_questions($USER->id);
        $this->num_assigned_questions = $DB->count_records_sql($sql, $params);
        list($sql, $params) = catquiz::get_sql_for_last_calculation_time($USER->id);
        $number = $DB->get_field_sql($sql, $params);
        $this->last_calculated = userdate($number, get_string('strftimedatetime', 'core_langconfig'));
    }


    /**
     * Return the item tree of all catscales.
     * @return array
     */
    public function export_data_array(): array {

        $data = [
            'num_assigned_catscales' => $this->num_assigned_catscales,
            'num_assigned_tests' => $this->num_assigned_tests,
            'num_assigned_questions' => $this->num_assigned_questions,
            'last_calculated' => $this->last_calculated,
        ];
        return $data;
    }
}
