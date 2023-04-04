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
 * Class for generating synthetic data;
 *
 * @package local_catquiz
 * @author Daniel Pasterk
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;
class synthcat{

    static function generate_persons($randomvec) {
        $persons = array();
        for ($i = 1; $i <= count($randomvec); $i++) {
            $person = array(
                    'id' => $i,
                    'ability' => $randomvec[$i-1] // generate a random ability parameter between 0 and 1
            );
            array_push($persons, $person);
        }
        return $persons;
    }

    static function generate_test_items($difficulty_vec) {
        $test_items = array();
        for ($i = 1; $i <= count($difficulty_vec); $i++) {
            $item = array(
                    'id' => $i,
                    'difficulty' => $difficulty_vec[$i-1], // generate a random difficulty parameter between 0 and 1
                //'discrimination' => rand(0, 100) / 100 // generate a random discrimination parameter between 0 and 1
                //    'discrimination' => 1 // generate a random discrimination parameter between 0 and 1
            );
            array_push($test_items, $item);
        }
        return $test_items;
    }

    static function get_probability_for_passing($difficulty, $ability){

        $discrimination = 1; // hardcode override because of 1pl
        $p = (1 / (1 + exp($discrimination * ($difficulty - $ability))));
        return $p;

    }

    static function generate_response($demopersons, $demoitems){
        $component_name = 'comp1';
        $response = array();

        foreach ($demopersons as $person) {

            $person_id = $person['id'];
            $response[$person_id] = Array();
            $response[$person_id][$component_name] = Array();

            $person_ability = $person['ability']; // set the person's ability parameter
            foreach ($demoitems as $item) {

                $item_id = $item['id'];
                $item_difficulty = $item['difficulty'];

                $p = self::get_probability_for_passing($item_difficulty, $person_ability);

                //if ($person_ability >= $item_difficulty){  // non-probabilistic workaround
                //    $passed = 1;
                //} else {
                //    $passed = 0;
                //}

                if ($p >= 0.5){  // non-probabilistic workaround
                    $passed = 1;
                } else {
                    $passed = 0;
                }

                $item_response = array(
                        'fraction' => $passed,
                        'max_fraction' => 1,
                        'min_fraction' => 0,
                        'qtype' => 'truefalse',
                        'timestamp' => 12345678
                );
                $response[$person_id][$component_name][$item_id] = $item_response;
            }
        }
        return $response;
    }
}
