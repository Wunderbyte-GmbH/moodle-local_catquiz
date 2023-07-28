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
* Class for calculating Rasch 1PL Model
*
* @package local_catquiz
* @author Daniel Pasterk
* @copyright 2023 Wunderbyte GmbH
* @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/


namespace catmodel_raschbirnbauma;

defined('MOODLE_INTERNAL') || die();

class raschmodel {   //TODO: Interface Implementation

    static function likelihood($person_ability, $item_difficulty ){

        $discrimination = 1; // hardcode override because of 1pl
        return (1 / (1 + exp($discrimination * ($item_difficulty - $person_ability))));
    }

    static function likelihood_counter($person_ability, $item_difficulty ){

        $discrimination = 1; // hardcode override because of 1pl
        return 1 - (1 / (1 + exp($discrimination * ($item_difficulty - $person_ability))));
    }

    static function likelihood_1st_derivative($person_ability, $item_difficulty ){

        $discrimination = 1; // hardcode override because of 1pl
        //return (1 / (1 + exp($discrimination * ($item_difficulty - $person_ability))));
        return ($discrimination*exp($discrimination*($item_difficulty*$person_ability)))/(exp($discrimination*$item_difficulty)+exp($discrimination*$person_ability))**2;
    }

    static function likelihood_2nd_derivative($person_ability, $item_difficulty ){

        $discrimination = 1; // hardcode override because of 1pl
        return ($discrimination*exp($discrimination*($item_difficulty+$person_ability))*(exp($discrimination*$item_difficulty)-exp($discrimination*$person_ability)))/(exp($discrimination*$item_difficulty)+exp($discrimination*$person_ability))**3;

    }


    static function log_likelihood($person_ability, $item_difficulty){

        return log(self::likelihood($person_ability, $item_difficulty));

    }

    static function log_likelihood_counter($person_ability, $item_difficulty){

        return log(1-self::likelihood($person_ability, $item_difficulty));

    }

    static function log_likelihood_1st_derivative($person_ability, $item_difficulty){

        $discrimination = 1;
        return $discrimination / (1 + exp($discrimination*(-$item_difficulty+$person_ability)));

    }

    static function log_likelihood_counter_1st_derivative($person_ability, $item_difficulty){

        $discrimination = 1;
        #return $discrimination / (1 + exp($discrimination*(-$item_difficulty+$person_ability)));

        return -($discrimination * exp($discrimination * $person_ability))/(exp($discrimination * $item_difficulty)+exp($discrimination * $person_ability));

    }

    static function log_likelihood_counter_2nd_derivative($person_ability, $item_difficulty){

        $discrimination = 1;
        #return ($discrimination**2 * exp($discrimination*($item_difficulty+$person_ability)) )/(exp($discrimination * $item_difficulty) + exp($discrimination * $person_ability))**2;
        return -($discrimination**2 * exp($discrimination*($item_difficulty+$person_ability)))/(exp($discrimination * $item_difficulty)+exp($discrimination * $person_ability))**2;
    }

    static function log_likelihood_2nd_derivative($person_ability, $item_difficulty){

        $discrimination = 1;
        return -($discrimination**2 * exp($discrimination*($item_difficulty+$person_ability)) )/(exp($discrimination * $item_difficulty) + exp($discrimination * $person_ability))**2;

    }
    static function estimate_person_ability($item_parameter,$person_resonse ) {

    }

    // add derivatives $item_parameter

    static function log_likelihood_1st_derivative_item($person_ability, $item_difficulty){
        $discrimination = 1;
        return -($discrimination)/(1+exp($discrimination*(-$item_difficulty + $person_ability)));

    }

    static function log_likelihood_counter_1st_derivative_item($person_ability, $item_difficulty){
        $discrimination = 1;
        return ($discrimination * exp($discrimination * $person_ability))/(exp($discrimination * $item_difficulty)+exp($discrimination * $person_ability));
    }

    static function log_likelihood_2nd_derivative_item($person_ability, $item_difficulty){
        $discrimination = 1;
        return -($discrimination**2 * exp($discrimination*($item_difficulty + $person_ability)))/(exp($discrimination * $item_difficulty)+exp($discrimination * $person_ability))**2;
    }

    static function log_likelihood_counter_2nd_derivative_item($person_ability, $item_difficulty){
        $discrimination = 1;
        return -($discrimination**2 * exp($discrimination * ($item_difficulty + $person_ability)))/(exp($discrimination * $item_difficulty) + exp($discrimination * $person_ability))**2;
    }

    // Calculate Akaike Information Criterion
    // Input:
    //   $person_ability - array of person abilities which the model has been optimised for
    //   $k - array of answer categories in the same order like $person_ability(e.g. 0 or 1)
    public function calc_AIC($person_ability, $k){
        $number_of_parameters = 1;
        $result = 0;

        foreach ($person_ability as $pp) {
            $result += log_likelihood($pp, $item_difficulty, $k);        }
        return 2 * $number_of_parameters - 2 * $result;
    }

    // Calculate Bayesian Information Criterion
    // Input:
    //   $person_ability - array of person abilities which the model has been optimised for
    //   $k - array of answer categories in the same order like $person_ability(e.g. 0 or 1)
    public function calc_BIC($person_ability, $k){
        $number_of_parameters = 1;
        $number_of_cases = count($person_ability); // array_filter nutzen!
        $result = 0;

        foreach ($person_ability as $pp) {
            $result += log_likelihood($pp, $item_difficulty, $k);
        }
        return $number_of_parameters * log($number_of_cases) - 2 * $result;
    }
}
