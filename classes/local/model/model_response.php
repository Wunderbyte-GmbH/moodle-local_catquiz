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

namespace local_catquiz\local\model;

use local_catquiz\local\model\model_item_list;
use local_catquiz\local\model\model_item_response;
use local_catquiz\local\model\model_person_param_list;

/**
 * Contains information about a performed quiz or test
 * 
 * For example: a list of users performed a test. Objects of this class show how the users performed on this test.
 * E.g.: which users took part, how did they answer the questions, etc.
 */
class model_response {
    private $data;

    public function to_item_list(): model_item_list {
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

        return new model_item_list($questions);
    }

    /**
     * Returns an array of arrays of item responses indexed by question id.
     * So for each question ID, there is an array of model_item_response entries
     *
     * @return array<array<model_item_response>>
     */
    public function get_item_response(model_person_param_list $person_param_list): array {
        $item_response = [];
        $user_ids = array_keys($this->data);
        foreach ($person_param_list->get_person_params() as $pp) {
            $components = array();
            $components = array_merge($components, array_keys($this->data[$pp->get_id()]));
            foreach ($components as $component) {
                $question_ids = array_keys($this->data[$pp->get_id()][$component]);
                foreach ($question_ids as $question_id) {
                    $fraction = $this->data[$pp->get_id()][$component][$question_id]['fraction'];
                    $item_response[$question_id][] = new model_item_response(
                        $fraction, $pp->get_ability()
                    );
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

    public function get_initial_person_abilities() {
        return array_map(
            function($id) {
                return ['id' => $id, 'ability' => 0];
            },
            array_keys($this->getData())
        );
    }
};