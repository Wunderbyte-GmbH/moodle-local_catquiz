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

use local_catquiz\local\model\model_calc;

defined('MOODLE_INTERNAL') || die();

class raschbirnbaummodel {   //TODO: Interface Implementation

    function likelihood($person_ability, $item_difficulty, $discrimination ){

        return (1 / (1 + exp($discrimination * ($item_difficulty - $person_ability))));
    }

    function likelihood_1st_derivative($person_ability, $item_difficulty, $discrimination ){

        //return (1 / (1 + exp($discrimination * ($item_difficulty - $person_ability))));
        return ($discrimination*exp($discrimination*($item_difficulty*$person_ability)))/(exp($discrimination*$item_difficulty)+exp($discrimination*$person_ability))**2;
    }

    function likelihood_2nd_derivative($person_ability, $item_difficulty, $discrimination ){

        return ($discrimination*exp($discrimination*($item_difficulty+$person_ability))*(exp($discrimination*$item_difficulty)-exp($discrimination*$person_ability)))/(exp($discrimination*$item_difficulty)+exp($discrimination*$person_ability))**3;

    }


    function log_likelihood($person_ability, $item_difficulty, $discrimination){

        return log(self::likelihood($person_ability, $item_difficulty, $discrimination));

    }

    function log_likelihood_1st_derivative($person_ability, $item_difficulty, $discrimination){

        return $discrimination / (1 + exp($discrimination*(-$item_difficulty+$person_ability)));

    }

    function log_likelihood_2nd_derivative($person_ability, $item_difficulty, $discrimination){

        return ($discrimination**2 * exp($discrimination*($item_difficulty + $person_ability)) )/(exp($discrimination * $item_difficulty) + exp($discrimination * $person_ability))**2;

    }
}