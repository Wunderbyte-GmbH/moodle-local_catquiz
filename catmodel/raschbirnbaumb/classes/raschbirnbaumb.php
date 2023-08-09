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

namespace catmodel_raschbirnbaumb;

use local_catquiz\catcalc;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_person_param_list;
use local_catquiz\local\model\model_raschmodel;

defined('MOODLE_INTERNAL') || die();

/**
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class raschbirnbaumb extends model_raschmodel
{

    // Definitions and Dimensions //

    /**
     * Definition of the number of model parameters
     *
     * @return int
     */
    public static function get_model_dim(): int
    {
        return 3;  // 3 parameters: person ability, difficulty, discrimination
    }

    /**
     * Initiate item parameter list
     *
     * @return model_item_param_list
     */
    public static function get_item_parameters(): model_item_param_list
    {
        // TODO implement
        return new model_item_param_list();
    }

    /**
     * Initiate person ability parameter list
     *
     * @return model_person_param_list
     */
    public static function get_person_abilities(): model_person_param_list
    {
        // TODO implement
        return new model_person_param_list();
    }

    /**
     * Estimate item parameters
     *
     * @param float
     * @return model_person_param_list
     */
    public function calculate_params($item_response): array
    {
        return catcalc::estimate_item_params($item_response, $this);
    }

    /**
     * Defines names if item parameter list
     *
     * @return array of string
     */
    public static function get_parameter_names(): array {
        return ['difficulty', 'discrimination', ];
    }

    // Calculate the Likelihood //

    /**
     * Calculates the Likelihood for a given the person ability parameter
     *
     * @param float $pp - person ability parameter
     * @param array<float> $ip - item parameters ('difficulty', 'discrimination')
     * @param float $k - answer category (0 or 1.0)
     * @return float
     */
    public static function likelihood($pp, array $ip, float $k)
    {
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];
        if ($k < 1.0) {
            return 1/(1 + exp($b * ($pp-$a)));
        } else {
            return 1/(1 + exp($b * ($a-$pp)));
        }
    }

    // Calculate the LOG Likelihood and its derivatives //

    /**
     * Calculates the LOG Likelihood for a given the person ability parameter
     *
     * @param float $pp - person ability parameter
     * @param array<float> $ip - item parameters ('difficulty')
     * @param float $k - answer category (0 or 1.0)
     * @return int|float
     */
    public static function log_likelihood($pp, array $ip, float $k)
    {
        return log(self::likelihood($pp, $ip, $k));
    }

    /**
     * Calculates the 1st derivative of the LOG Likelihood with respect to the person ability parameter
     *
     * @param float $pp - person ability parameter
     * @param array<float> $ip - item parameters ('difficulty')
     * @param float $k - answer category (0 or 1.0)
     * @return float
     */
    public static function log_likelihood_p($pp, array $ip, float $k): float {
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];
        if ($k < 1.0) {
            return -($b * exp($b * $pp)) / (exp($a * $b) + exp($b * $pp));
        } else {
            return ($b * exp($a * $b)) / (exp($a * $b) + exp($b * $pp));
        }
    }

    /**
     * Calculates the 2nd derivative of the LOG Likelihood with respect to the person ability parameter
     *
     * @param float $pp - person ability parameter
     * @param array<float> $ip - item parameters ('difficulty')
     * @param float $k - answer category (0 or 1.0)
     * @return float
     */
    public static function log_likelihood_p_p($pp, array $ip, float $k): float {
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];
        return -(($b ** 2 * exp($b * ($a + $pp))) / ((exp($a * $b) + exp($b * $pp)) ** 2));
    }

    /**
     * Calculates the 1st derivative of the LOG Likelihood with respect to the item parameters
     *
     * @param float $pp - person ability parameter
     * @param float $k - answer category (0 or 1.0)
     * @return array of function($ip)
     */
    public static function get_log_jacobian($pp, float $k):array
    {
        if ($k < 1.0) {
            return [
                fn ($ip) => ($ip['discrimination'] * exp($ip['discrimination'] * $pp)) / (exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)), // d/da
                fn ($ip) => (exp($ip['discrimination'] * $pp) * ( $ip['difficulty'] - $pp)) / (exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)) // d/db
                ];
        } else {
            return [
                fn ($ip) => -($ip['discrimination'] * exp( $ip['difficulty'] * $ip['discrimination'])) / (exp( $ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)), // d/da
                fn ($ip) => (exp( $ip['difficulty'] * $ip['discrimination']) * ($pp - $ip['difficulty'])) /(exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)) // d/db
                ];
        }
    }

    /**
     * Calculates the 2nd derivative of the LOG Likelihood with respect to the item parameters
     *
     * @param float $pp - person ability parameter
     * @param float $k - answer category (0 or 1.0)
     * @return array of array of function($ip)
     */
    public static function get_log_hessian($pp, float $item_response): array
    {
        if ($item_response >= 1.0) {
           return [[
                fn ($ip) => (-($ip['discrimination'] ** 2 * exp($ip['discrimination'] * ($ip['difficulty'] + $pp))) / ((exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)) ** 2)), // d²/da²
                fn ($ip) => (-(exp($ip['difficulty'] * $ip['discrimination']) * (exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp) * (1 + $ip['discrimination'] * ($ip['difficulty'] - $pp)))) / ((exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)) ** 2)) // d/a d/db
                ],[
                fn ($ip) => (-(exp($ip['difficulty'] * $ip['discrimination']) * (exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp) * (1 + $ip['discrimination'] * ($ip['difficulty'] - $pp)))) / ((exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)) ** 2)), // d/a d/db
                fn ($ip) => (-(exp($ip['discrimination'] * ($ip['difficulty'] + $pp)) * ($ip['difficulty'] - $pp) ** 2) / ((exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)) ** 2)) // d²/db²
            ]];
        } else {
            return [[
                fn ($ip) => -($ip['discrimination'] ** 2 * exp($ip['discrimination'] * ($ip['difficulty'] - $pp))) / (1 + exp($ip['discrimination'] * ($ip['difficulty'] - $pp))) ** 2, // d²/da²
                fn ($ip) => (1 + exp($ip['discrimination'] * ($ip['difficulty'] - $pp)) * (1 + $ip['discrimination'] * ($pp - $ip['difficulty']))) / (1 + exp($ip['discrimination'] * ($ip['difficulty'] - $pp))) ** 2, // d/da d/db
            ],[
                fn ($ip) => (1 + exp($ip['discrimination'] * ($ip['difficulty'] - $pp)) * (1 + $ip['discrimination'] * ($pp - $ip['difficulty']))) / (1 + exp($ip['discrimination'] * ($ip['difficulty'] - $pp))) ** 2, // d/da d/db
                fn ($ip) => -(exp($ip['discrimination'] * ($ip['difficulty'] - $pp)) * ($ip['difficulty'] - $pp) ** 2) / (1 + exp($ip['discrimination'] * ($ip['difficulty'] - $pp))) ** 2
           ]];
        }
    }

    /**
     * Calculates the Fisher Information for a given person ability parameter
     *
     * @param float $pp
     * @param array<float> $ip
     * @return float
     */
    public static function fisher_info(float $pp,array $ip){
        return ($ip['discrimination'] ** 2 * self::likelihood($pp,$ip,0) * self::likelihood($pp,$ip,1.0));
    }

    /**
     * Implements a Filter Function for trusted regions in the item parameter estimation
     *
     * @param array $ip
     * return array
     */
    public static function restrict_to_trusted_region(array $parameters): array {
        // Set values for difficulty parameter
        $a = $parameters['difficulty'];

        $a_m = 0; // Mean of difficulty
        $a_s = 2; // Standard derivation of difficulty

        // Use x times of SD as range of trusted regions
        $a_tr = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_factor_sd_a'));
        $a_min = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_min_a'));
        $a_max = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_max_a'));

        // Set values for disrciminatory parameter
        $b = $parameters['discrimination'];

        // Placement of the discriminatory parameter
        $b_p = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_placement_b'));
        // Slope of the discriminatory parameter
        $b_s = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_slope_b'));
        // Use x times of placement as maximal value of trusted region
        $b_tr = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_factor_max_b'));

        $b_min = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_min_b'));
        $b_max = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_max_b'));

        // Test TR for difficulty
        if (($a - $a_m) < max(-($a_tr * $a_s), $a_min)) {$a = max(-($a_tr * $a_s), $a_min); }
        if (($a - $a_m) > min(($a_tr * $a_s), $a_max)) {$a = min(($a_tr * $a_s), $a_max); }

        $parameters['difficulty'] = $a;

        // Test TR for discriminatory
        if ($b < $b_min) {$b = $b_min; }
        if ($b > min(($b_tr * $b_p),$b_max)) {$b = min(($b_tr * $b_p),$b_max); }

        $parameters['discrimination'] = $b;

        return $parameters;
    }

    /**
     * Calculates the 1st derivative trusted regions for item parameters
     *
     * @return array
     */
    public static function get_log_tr_jacobian(): array {
        // Set values for difficulty parameter
        $a_m = 0; // Mean of difficulty
        $a_s = 2; // Standard derivation of difficulty

        // Placement of the discriminatory parameter
        $b_p = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_placement_b'));
        // Slope of the discriminatory parameter
        $b_s = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_slope_b'));

        return [
            fn ($x) => (($a_m - $x['difficulty']) / ($a_s ** 2)), // d/da
            fn ($x) => (-($b_s * exp($b_s * $x['discrimination'])) / (exp($b_s * $b_p) + exp($b_s * $x['discrimination']))) // d/db
        ];
    }

    /**
     * Calculates the 2nd derivative trusted regions for item parameters
     *
     * @return array
     */
    public static function get_log_tr_hessian(): array {
        // Set values for difficulty parameter
        $a_m = 0; // Mean of difficulty
        $a_s = 2; // Standard derivation of difficulty

        // Placement of the discriminatory parameter
        $b_p = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_placement_b'));
        // Slope of the discriminatory parameter
        $b_s = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_slope_b'));

        return [[
            fn ($x) => (-1/ ($a_s ** 2)), // d/da d/da
            fn ($x) => (0) //d/da d/db
        ],[
            fn ($x) => (0), //d/db d/da
            fn ($x) => (-($b_s ** 2 * exp($b_s * ($b_p + $x['discrimination']))) / (exp($b_s * $b_p) + exp($b_s * $x['discrimination'])) ** 2) // d/db d/db
        ]];
    }

}
