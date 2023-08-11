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
use local_catquiz\local\model\model_item_response;

class synthcat {

    static function generate_persons($randomvec) {
        $persons = array();
        for ($i = 1; $i <= count($randomvec); $i++) {
            $person = array(
                    'id' => $i,
                    'ability' => $randomvec[$i - 1] // generate a random ability parameter between 0 and 1
            );
            array_push($persons, $person);
        }
        return $persons;
    }

    static function generate_test_items($difficultyvec) {
        $testitems = array();
        for ($i = 1; $i <= count($difficultyvec); $i++) {
            $item = array(
                    'id' => $i,
                    'difficulty' => $difficultyvec[$i - 1], // generate a random difficulty parameter between 0 and 1
                // 'discrimination' => rand(0, 100) / 100 // generate a random discrimination parameter between 0 and 1
                // 'discrimination' => 1 // generate a random discrimination parameter between 0 and 1
            );
            array_push($testitems, $item);
        }
        return $testitems;
    }

    static function get_probability_for_passing($difficulty, $ability) {

        $discrimination = 1; // hardcode override because of 1pl
        $p = (1 / (1 + exp($discrimination * ($difficulty - $ability))));
        return $p;

    }

    static function generate_response($demopersons, $demoitems) {
        $componentname = 'comp1';
        $response = array();

        foreach ($demopersons as $person) {

            $personid = $person['id'];
            $response[$personid] = Array();
            $response[$personid][$componentname] = Array();

            $personability = $person['ability']; // set the person's ability parameter
            foreach ($demoitems as $item) {

                $itemid = $item['id'];
                $itemdifficulty = $item['difficulty'];

                $p = self::get_probability_for_passing($itemdifficulty, $personability);

                // if ($person_ability >= $item_difficulty){  // non-probabilistic workaround
                // $passed = 1;
                // } else {
                // $passed = 0;
                // }

                if ($p >= 0.5){  // non-probabilistic workaround
                    $passed = 1;
                } else {
                    $passed = 0;
                }

                $itemresponse = array(
                        'fraction' => $passed,
                        'max_fraction' => 1,
                        'min_fraction' => 0,
                        'qtype' => 'truefalse',
                        'timestamp' => 12345678
                );
                $response[$personid][$componentname][$itemid] = $itemresponse;
            }
        }
        return $response;
    }

    static function generate_test_items_multi ($paramvec) {

        $result = array();

        $testitems = array();

        // Get the number of subarrays
        $numsubarrays = count($paramvec);

        // Get the length of each subarray
        $subarraylength = count($paramvec[0]);

        // Iterate over the elements of the subarrays
        for ($i = 0; $i < $subarraylength; $i++) {
            $temp = array();

            // Iterate over the subarrays
            for ($j = 0; $j < $numsubarrays; $j++) {
                $temp[] = $paramvec[$j][$i];
            }

            $item = array(
                    'id' => $i + 1,
                    'params' => $temp
            );

            array_push($testitems, $item);

        }

        return $testitems;
    }

    static function get_probability_for_passing_mutli($personability, $itemparams, $model) {

        return $model::likelihood_multi($personability, $itemparams);

    }

    static function generate_response_multi($demopersons, $demoitems, $model) {

        $componentname = 'comp1';
        $response = array();

        foreach ($demopersons as $person) {

            $personid = $person['id'];
            $response[$personid] = array();
            $response[$personid][$componentname] = array();

            $personability = $person['ability']; // set the person's ability parameter
            foreach ($demoitems as $item) {

                $itemid = $item['id'];
                $itemparams = $item['params'];

                $p = self::get_probability_for_passing_mutli($personability, $itemparams, $model);

                // if ($person_ability >= $item_difficulty){  // non-probabilistic workaround
                // $passed = 1;
                // } else {
                // $passed = 0;
                // }

                if ($p >= 0.5) {  // non-probabilistic workaround
                    $passed = 1;
                } else {
                    $passed = 0;
                }

                $itemresponse = array(
                        'fraction' => $passed,
                        'max_fraction' => 1,
                        'min_fraction' => 0,
                        'qtype' => 'truefalse',
                        'timestamp' => 12345678
                );
                $response[$personid][$componentname][$itemid] = $itemresponse;
            }
        }
        return $response;

    }


    static function get_person_abilities($num) {

    }


    static function get_item_response2($numpos, $numneg, $personability) {

        $list = [];

        for($i = 1; $i <= $numpos; $i++){

            $tmpitemresponse = new model_item_response(1, $personability);
            array_push($list, $tmpitemresponse);

        }

        for($i = 1; $i <= $numneg; $i++){

            $tmpitemresponse = new model_item_response(0, $personability);
            array_push($list, $tmpitemresponse);

        }

        return $list;

    }


    static function get_person_response($numpos, $numneg) {

        $list = [];

        for($i = 1; $i <= $numpos; $i++){

            $list[$i] = ['fraction' => 1];
            // array_push($list,$tmp_item_response);

        }

        for($i = $numpos + 1; $i <= $numpos + $numneg; $i++){

            $list[$i] = ['fraction' => 0];
            // array_push($list,$tmp_item_response);

        }

        return $list;

    }


    static function get_model_item_param_list($itemdifficulty, $num) {
        $list = [];

        for($i = 1; $i <= $num; $i++){

            $list[$i] = $itemdifficulty;

        }

        return $list;

    }


}








class mytestclass {
    static function testtest() {
        return "test";
    }
}


class synthcat2 {

    static function generate_persons($randomvec) {
        $persons = array();
        for ($i = 1; $i <= count($randomvec); $i++) {
            $person = array(
                    'id' => $i,
                    'ability' => $randomvec[$i - 1] // generate a random ability parameter between 0 and 1
            );
            array_push($persons, $person);
        }
        return $persons;
    }

    static function generate_test_items ($paramvec) {

        $result = array();

        $testitems = array();

        // Get the number of subarrays
        $numsubarrays = count($paramvec);

        // Get the length of each subarray
        $subarraylength = count($paramvec[0]);

        // Iterate over the elements of the subarrays
        for ($i = 0; $i < $subarraylength; $i++) {
            $temp = array();

            // Iterate over the subarrays
            for ($j = 0; $j < $numsubarrays; $j++) {
                $temp[] = $paramvec[$j][$i];
            }

            $item = array(
                    'id' => $i,
                    'params' => $temp
            );

            array_push($testitems, $item);

        }

        return $testitems;
    }





}
