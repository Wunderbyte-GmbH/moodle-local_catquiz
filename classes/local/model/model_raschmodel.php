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
 * Class model_raschmodel.
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\local\model;

use local_catquiz\catcalc_ability_estimator;
use local_catquiz\catcalc_item_estimator;

defined('MOODLE_INTERNAL') || die();

/**
 * This class implements model raschmodel.
 *
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class model_raschmodel extends model_model implements catcalc_item_estimator, catcalc_ability_estimator {

    /**
     * Likelihood 1pl.
     *
     * @param mixed $person_ability
     * @param mixed $item_difficulty
     * 
     * @return float
     * 
     */
    static function likelihood_1pl($person_ability, $item_difficulty ){

        $discrimination = 1; // hardcode override because of 1pl
        return (1 / (1 + exp($discrimination * ($item_difficulty - $person_ability))));
    }

    /**
     * Executes the model-specific code to estimate item-parameters based
     * on the given person abilities.
     *
     * @param model_person_param_list $person_params
     * @return model_item_param_list
     */
    public function estimate_item_params(model_person_param_list $person_params): model_item_param_list
    {
        $estimated_item_params = new model_item_param_list();
        foreach ($this->responses->get_item_response($person_params) as $item_id => $item_response) {
            $parameters = $this->calculate_params($item_response);
            // Now create a new item difficulty object (param)
            $param = $this
                ->create_item_param($item_id)
                ->set_parameters($parameters);
            // ... and append it to the list of calculated item difficulties
            $estimated_item_params->add($param);
        }
        return $estimated_item_params;
    }

    /**
     * Returns the item parameters as associative array, with the parameter name as key.
     *
     * @param mixed $item_response
     * @return array
     */
    abstract protected function calculate_params($item_response): array;

    /**
     * Likelihood.
     *
     * @param mixed $x
     * @param array $item_params
     * @param float $item_response
     * 
     * @return mixed
     * 
     */
    abstract public static function likelihood($x, array $item_params, float $item_response);

    /**
     * Log likelihood.
     *
     * @param mixed $x
     * @param array $item_params
     * @param float $item_response
     * 
     * @return mixed
     * 
     */
    abstract public static function log_likelihood($x, array $item_params, float $item_response);

    /**
     * Log likelihood p.
     *
     * @param mixed $x
     * @param array $item_params
     * @param float $item_response
     * 
     * @return mixed
     * 
     */
    abstract public static function log_likelihood_p($x, array $item_params, float $item_response);
    
    /**
     * Log likelihood p p.
     *
     * @param mixed $x
     * @param array $item_params
     * @param float $item_response
     * 
     * @return mixed
     * 
     */
    abstract public static function log_likelihood_p_p($x, array $item_params, float $item_response);
}
