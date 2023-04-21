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
* @copyright 2023 Wunderbyte GmbH <info@wunderbyte.at>
* @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

namespace local_catquiz;

/**
 * Provides data required for parameter estimation
 */
class catmodel_response {

    private $data;

    public static function create_from_db($contextid = 0) {
        global $DB;

        list ($sql, $params) = catquiz::get_sql_for_model_input($contextid);
        $data = $DB->get_records_sql($sql, $params);
        $inputdata = self::db_to_modelinput($data);
        return (new self())->setData($inputdata);
    }

    public function to_item_list(): catmodel_item_list {
        $questions = Array();
        $user_ids = array_keys($this->data);

        foreach ($user_ids as $user_id) {
            $components = array();
            $components = array_merge($components, array_keys($this->data[$user_id]));
            foreach ($components as $component) {
                $question_ids = array_keys($this->data[$user_id][$component]);
                foreach ($question_ids as $question_id) {
                    $questions[$question_id][] = $this->data[$user_id][$component][$question_id]['fraction'];
                }
            }
        }

        return new catmodel_item_list($questions);
    }
    /**
     * Returns an array of arrays of item responses indexed by question id.
     * So for each question ID, there is an array of catmodel_item_response entries
     *
     * @return array<array<catmodel_item_response>>
     */
    public function get_item_response($person_abilities): array {
        $item_response = [];
        $user_ids = array_keys($this->data);
        foreach ($user_ids as $user_id) {
            $components = array();
            $components = array_merge($components, array_keys($this->data[$user_id]));
            foreach ($components as $component) {
                $question_ids = array_keys($this->data[$user_id][$component]);
                foreach ($question_ids as $question_id) {
                    $fraction = $this->data[$user_id][$component][$question_id]['fraction'];
                    $item_response[$question_id][] = new catmodel_item_response($fraction, $person_abilities[$user_id]);
                }
            }
        }
        return $item_response;
    }

    public function setData($data) {
        $this->data = $data;
        return $this;
    }

    public function getData() {
        return $this->data;
    }

    /**
     * Returns data in the following format
     * 
     * "1" => Array( //userid
     *     "comp1" => Array( // component
     *         "1" => Array( //questionid
     *             "fraction" => 0,
     *             "max_fraction" => 1,
     *             "min_fraction" => 0,
     *             "qtype" => "truefalse",
     *             "timestamp" => 1646955326
     *         ),
     *         "2" => Array(
     *             "fraction" => 0,
     *             "max_fraction" => 1,
     *             "min_fraction" => 0,
     *             "qtype" => "truefalse",
     *             "timestamp" => 1646955332
     *         ),
     *         "3" => Array(
     *             "fraction" => 1,
     *             "max_fraction" => 1,
     *             "min_fraction" => 0,
     *             "qtype" => "truefalse",
     *             "timestamp" => 1646955338
     */
    private static function db_to_modelinput($data) {
        $modelinput = [];
        foreach ($data as $row) {
            $entry = [
                'fraction' => $row->fraction,
                'max_fraction' =>  $row->maxfraction,
                'min_fraction' => $row->minfraction,
                'qtype' => $row->qtype,
                'timestamp' => $row->timecreated,
            ];

            if (!array_key_exists($row->userid, $modelinput)) {
                $modelinput[$row->userid] = ["component" => []];
            }

            $modelinput[$row->userid]['component'][$row->questionid] = $entry;
        }
        return $modelinput;
    }
};