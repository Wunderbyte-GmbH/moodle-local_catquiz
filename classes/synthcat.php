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
 * Classes for generating synthetic data;
 *
 * @package local_catquiz
 * @author Daniel Pasterk
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;
use local_catquiz\local\model\model_item_response;

defined('MOODLE_INTERNAL') || die();

/**
 * Class for synthcat
 *
 * @package local_catquiz
 * @author Daniel Pasterk
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class synthcat {

    /**
     * Method to generate persons.
     *
     * @param mixed $randomvec
     *
     * @return array
     *
     */
    public static function generate_persons($randomvec) {
        $persons = [];
        for ($i = 1; $i <= count($randomvec); $i++) {
            $person = [
                    'id' => $i,
                    'ability' => $randomvec[$i - 1], // Generate a random ability parameter between 0 and 1.
            ];
            array_push($persons, $person);
        }
        return $persons;
    }

    /**
     * Generate test items.
     *
     * @param mixed $difficultyvec
     *
     * @return array
     *
     */
    public static function generate_test_items($difficultyvec) {
        $testitems = [];
        for ($i = 1; $i <= count($difficultyvec); $i++) {
            $item = [
                    'id' => $i,
                    'difficulty' => $difficultyvec[$i - 1], // Generate a random difficulty parameter between 0 and 1.
                // 'discrimination' => rand(0, 100) / 100 // Generate a random discrimination parameter between 0 and 1.
                // 'discrimination' => 1 // Generate a random discrimination parameter between 0 and 1.
            ];
            array_push($testitems, $item);
        }
        return $testitems;
    }

    /**
     * Get probability for passing.
     *
     * @param mixed $difficulty
     * @param mixed $ability
     *
     * @return float
     *
     */
    public static function get_probability_for_passing($difficulty, $ability) {

        $discrimination = 1; // Hardcode override because of 1pl.
        $p = (1 / (1 + exp($discrimination * ($difficulty - $ability))));
        return $p;

    }

    /**
     * Generate response.
     *
     * @param mixed $demopersons
     * @param mixed $demoitems
     *
     * @return array
     *
     */
    public static function generate_response($demopersons, $demoitems) {
        $componentname = 'comp1';
        $response = [];

        foreach ($demopersons as $person) {

            $personid = $person['id'];
            $response[$personid] = [];
            $response[$personid][$componentname] = [];

            $personability = $person['ability']; // Set the person's ability parameter.
            foreach ($demoitems as $item) {

                $itemid = $item['id'];
                $itemdifficulty = $item['difficulty'];

                $p = self::get_probability_for_passing($itemdifficulty, $personability);

                // phpcs:disable
                // if ($person_ability >= $item_difficulty){  // Non-probabilistic workaround.
                // $passed = 1;
                // } else {
                // $passed = 0;
                // }
                // phpcs:enable

                if ($p >= 0.5) {  // Non-probabilistic workaround.
                    $passed = 1;
                } else {
                    $passed = 0;
                }

                $itemresponse = [
                        'fraction' => $passed,
                        'max_fraction' => 1,
                        'min_fraction' => 0,
                        'qtype' => 'truefalse',
                        'timestamp' => 12345678,
                ];
                $response[$personid][$componentname][$itemid] = $itemresponse;
            }
        }
        return $response;
    }

    /**
     * Generate test items multi.
     *
     * @param mixed $paramvec
     *
     * @return array
     *
     */
    public static function generate_test_items_multi($paramvec) {

        $testitems = [];

        // Get the number of subarrays.
        $numsubarrays = count($paramvec);

        // Get the length of each subarray.
        $subarraylength = count($paramvec[0]);

        // Iterate over the elements of the subarrays.
        for ($i = 0; $i < $subarraylength; $i++) {
            $temp = [];

            // Iterate over the subarrays.
            for ($j = 0; $j < $numsubarrays; $j++) {
                $temp[] = $paramvec[$j][$i];
            }

            $item = [
                    'id' => $i + 1,
                    'params' => $temp,
            ];

            array_push($testitems, $item);

        }

        return $testitems;
    }

    /**
     * Get probability for passing mutli.
     *
     * @param mixed $personability
     * @param mixed $itemparams
     * @param mixed $model
     *
     * @return mixed
     *
     */
    public static function get_probability_for_passing_mutli($personability, $itemparams, $model) {

        return $model::likelihood_multi($personability, $itemparams);

    }

    /**
     * Generate response multi.
     *
     * @param mixed $demopersons
     * @param mixed $demoitems
     * @param mixed $model
     *
     * @return array
     *
     */
    public static function generate_response_multi($demopersons, $demoitems, $model) {

        $componentname = 'comp1';
        $response = [];

        foreach ($demopersons as $person) {

            $personid = $person['id'];
            $response[$personid] = [];
            $response[$personid][$componentname] = [];

            $personability = $person['ability']; // Set the person's ability parameter.
            foreach ($demoitems as $item) {

                $itemid = $item['id'];
                $itemparams = $item['params'];

                $p = self::get_probability_for_passing_mutli($personability, $itemparams, $model);

                // phpcs:disable
                // if ($person_ability >= $item_difficulty){  // non-probabilistic workaround
                // $passed = 1;
                // } else {
                // $passed = 0;
                // }
                // phpcs:enable

                if ($p >= 0.5) {  // Non-probabilistic workaround.
                    $passed = 1;
                } else {
                    $passed = 0;
                }

                $itemresponse = [
                        'fraction' => $passed,
                        'max_fraction' => 1,
                        'min_fraction' => 0,
                        'qtype' => 'truefalse',
                        'timestamp' => 12345678,
                ];
                $response[$personid][$componentname][$itemid] = $itemresponse;
            }
        }
        return $response;

    }

    /**
     * Get person abilities.
     *
     * @param mixed $num
     *
     * @return array
     *
     */
    public static function get_person_abilities($num) {
        return [];
    }

    /**
     * Get item response2.
     *
     * @param mixed $numpos
     * @param mixed $numneg
     * @param mixed $personability
     *
     * @return array
     *
     */
    public static function get_item_response2($numpos, $numneg, $personability) {

        $list = [];

        for ($i = 1; $i <= $numpos; $i++) {

            $tmpitemresponse = new model_item_response("correct_$i", 1, $personability);
            array_push($list, $tmpitemresponse);

        }

        for ($i = 1; $i <= $numneg; $i++) {

            $tmpitemresponse = new model_item_response("incorrect_$i", 0, $personability);
            array_push($list, $tmpitemresponse);

        }

        return $list;

    }

    /**
     * Get person response.
     *
     * @param mixed $numpos
     * @param mixed $numneg
     *
     * @return array
     *
     */
    public static function get_person_response($numpos, $numneg) {

        $list = [];

        for ($i = 1; $i <= $numpos; $i++) {

            $list[$i] = ['fraction' => 1];
            // phpcs:ignore
            // array_push($list,$tmp_item_response);

        }

        for ($i = $numpos + 1; $i <= $numpos + $numneg; $i++) {

            $list[$i] = ['fraction' => 0];
            // phpcs:ignore
            // array_push($list,$tmp_item_response);

        }

        return $list;

    }

    /**
     * Get model item param list.
     *
     * @param mixed $itemdifficulty
     * @param mixed $num
     *
     * @return array
     *
     */
    public static function get_model_item_param_list($itemdifficulty, $num) {
        $list = [];

        for ($i = 1; $i <= $num; $i++) {

            $list[$i] = $itemdifficulty;

        }

        return $list;

    }
}

/**
 * Class for mysynthcat
 *
 * @package local_catquiz
 * @author Daniel Pasterk
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mytestclass {
    /**
     * Testtest method.
     *
     * @return string
     *
     */
    public static function testtest() {
        return "test";
    }
}

/**
 * Class for synthcat2
 *
 * @package local_catquiz
 * @author Daniel Pasterk
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class synthcat2 {

    /**
     * Method to generate persons.
     *
     * @param mixed $randomvec
     *
     * @return array
     *
     */
    public static function generate_persons($randomvec) {
        $persons = [];
        for ($i = 1; $i <= count($randomvec); $i++) {
            $person = [
                    'id' => $i,
                    'ability' => $randomvec[$i - 1], // Generate a random ability parameter between 0 and 1.
            ];
            array_push($persons, $person);
        }
        return $persons;
    }

    /**
     * Generate test items.
     *
     * @param mixed $paramvec
     *
     * @return array
     *
     */
    public static function generate_test_items($paramvec) {

        $testitems = [];

        // Get the number of subarrays.
        $numsubarrays = count($paramvec);

        // Get the length of each subarray.
        $subarraylength = count($paramvec[0]);

        // Iterate over the elements of the subarrays.
        for ($i = 0; $i < $subarraylength; $i++) {
            $temp = [];

            // Iterate over the subarrays.
            for ($j = 0; $j < $numsubarrays; $j++) {
                $temp[] = $paramvec[$j][$i];
            }

            $item = [
                    'id' => $i,
                    'params' => $temp,
            ];

            array_push($testitems, $item);

        }

        return $testitems;
    }
}
