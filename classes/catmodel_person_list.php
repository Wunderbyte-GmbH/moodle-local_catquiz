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
 * This class
 */
class catmodel_person_list {

    /**
     * This is an array with following structure
     * [
     *   'id' => userid,
     *   'ability' => ability,
     * ]
     */
    private array $persons;
    private $estimated_person_abilities = [];

    public function __construct(array $persons) { // TODO: use class instead of array?
        $this->persons = $persons;
    }
    public function estimate_person_abilities(catmodel_response $response, $item_difficulties) {
        foreach($this->persons as $person){

            $person_id = $person['id'];
            $person_response = \local_catquiz\helpercat::get_person_response($response->getData(), $person_id);
            $person_ability = \local_catquiz\catcalc::estimate_person_ability($person_response, $item_difficulties);
            $this->estimated_person_abilities[$person_id] = $person_ability;
        }
    }

    public function get_estimated_person_abilities() {
        return $this->estimated_person_abilities;
    }
};