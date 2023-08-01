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
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\local\model;

use local_catquiz\catcalc_ability_estimator;
use local_catquiz\catcalc_item_estimator;

defined('MOODLE_INTERNAL') || die();

/**
 * TODO: add description
 *
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class model_raschmodel extends model_model implements catcalc_item_estimator, catcalc_ability_estimator {
    
    /** Die Funktion likelihood_1pl sollte nicht mehr hier in der Klasse zu finden sein
    Die Berechnung findet jetzt in raschmodela.php statt
    Wird diese Funktion noch irgendwo aufgerufen? */
    static function likelihood_1pl($person_ability, $item_difficulty ){

        $discrimination = 1; // hardcode override because of 1pl
        return (1 / (1 + exp($discrimination * ($item_difficulty - $person_ability))));
    }

    /**
     * Calculates the Information-Criterion Deviance (DIC) for a given item
     * under the condition that item-parameters have been optimized for the
     * given person abilities
     *
     * @param model $model
     * @param array $person_ability
     * @param array $k
     * @return float
     */
    public function calc_DIC_item($model, $person_ability, $k)
    {
        $number_of_parameters = $model::get_model_dim() - 1;
        $result = 0;
        foreach (arrayfilter($person_ability, function($var){return is_float($var);}, ARRAY_FILTER_USE_KEY) as $key => $pp)
        {
            if (array_key_exists($key, $k)) $result += $model::log_likelihood($pp, $k[$key]);        
        }
        return -2 * $result;
    }

    /**
     * Calculates the Aiken Information-Criterion (AIC, Akaike 1987) for a given item
     * under the condition that item-parameters have been optimized for the
     * given person abilities
     *
     * @param model $model
     * @param array $person_ability
     * @param array $k
     * @return float
     */
    public function calc_AIC_item($model, $person_ability, $k)
    {
        $number_of_parameters = $model::get_model_dim() - 1;
        return return 2 * $number_of_parameters + calc_DIC_item($model, $person_ability, $k);
    }

    /**
     * Calculates the Bayes Information-Criterion (BIC, Schwarz 1978) for a given item
     * under the condition that item-parameters have been optimized for the
     * given person abilities
     *
     * @param model $model
     * @param array $person_ability
     * @param array $k
     * @return float
     */
    public function calc_BIC_item($model, $person_ability, $k)
    {
        $number_of_parameters = $model::get_model_dim() - 1;
        $number_of_cases = count(arrayfilter($person_ability, function($var){return is_float($var);}, ARRAY_FILTER_USE_KEY));
        return $number_of_parameters * log($number_of_cases) + calc_DIC_item($model, $person_ability, $k);
    }

    /**
     * Calculates the Consistent Akaike Information-Criterion (CAIC, Bozdogan 1987) for a given item
     * under the condition that item-parameters have been optimized for the
     * given person abilities
     *
     * @param model $model
     * @param array $person_ability
     * @param array $k
     * @return float
     */
    public function calc_CAIC_item($model, $person_ability, $k)
    {
        $number_of_parameters = $model::get_model_dim() - 1;
        $number_of_cases = count(arrayfilter($person_ability, function($var){return is_float($var);}, ARRAY_FILTER_USE_KEY));
        return $number_of_parameters * (log($number_of_cases + 1)) + calc_DIC_item($model, $person_ability, $k);
    }

    /**
     * Calculates the bias corrected Akaike Information-Criterion (AICc, Sugiura 1987) for a given item
     * under the condition that item-parameters have been optimized for the
     * given person abilities
     *
     * @param model $model
     * @param array $person_ability
     * @param array $k
     * @return float
     */
    public function calc_AICc_item($model, $person_ability, $k)
    {
        $number_of_parameters = $model::get_model_dim() - 1;
        $number_of_cases = count(arrayfilter($person_ability, function($var){return is_float($var);}, ARRAY_FILTER_USE_KEY));
        if ($number_of_cases - $number_of_parameters - 1 =< 0) return 0;
        return return 2 * $number_of_parameters + (2 * $number_of_parameters * ($number_of_parameters +1)) / ($number_of_cases - $number_of_parameters - 1) + calc_DIC_item($model, $person_ability, $k);
    }

    /**
     * Calculates the sample size adjusted Bayes Information-Criterion (saBIC, Sclove 1987) for a given item
     * under the condition that item-parameters have been optimized for the
     * given person abilities
     *
     * @param model $model
     * @param array $person_ability
     * @param array $k
     * @return float
     */
    public function calc_saBIC_item($model, $person_ability, $k)
    {
        $number_of_parameters = $model::get_model_dim() - 1;
        $number_of_cases = count(arrayfilter($person_ability, function($var){return is_float($var);}, ARRAY_FILTER_USE_KEY));
        if ($number_of_cases - $number_of_parameters - 1 =< 0) return 0;
        return $number_of_parameters * log(($number_of_cases + 2) / 24) + calc_DIC_item($model, $person_ability, $k);
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
            $parameters = $this->restrict_to_trusted_region(
                $this->calculate_params($item_response)
            );
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
     * Update parameters so that they are located in a trusted region
     * @param array $parameters
     * @return array
     */
    abstract protected function restrict_to_trusted_region(array $parameters): array;

    abstract public static function likelihood($x, array $item_params, float $item_response);
    abstract public static function log_likelihood($x, array $item_params, float $item_response);
    abstract public static function log_likelihood_p($x, array $item_params, float $item_response);
    abstract public static function log_likelihood_p_p($x, array $item_params, float $item_response);
}
