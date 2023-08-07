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
 * Event factory interface.
 *
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace catmodel_raschbirnbauma;

use local_catquiz\catcalc;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_person_param_list;
use local_catquiz\local\model\model_raschmodel;

defined('MOODLE_INTERNAL') || die();

/**
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class raschbirnbauma extends model_raschmodel
{

    // Definitions and Dimensions //

    /**
     * Definition of the number of model parameters
     *
     * @return int
     */
    public static function get_model_dim(): int
    {
        return 2;  // 2 parameters: person ability, difficulty
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
        return ['difficulty', // WAS NOCH? (Steht das Komma hier aus einem bestimmten Grund?
            ];
    }
    
    // Calculate the Likelihood //
    
    /**
     * Calculates the Likelihood for a given the person ability parameter
     *
     * @param float $pp - person ability parameter
     * @param array<float> $ip - item parameters ('difficulty')
     * @param float $k - answer category (0 or 1.0)
     * @return float
     */
    public static function likelihood($pp, array $ip, float $k)
    {
        $a = $params['difficulty'];
        if ($item_response < 1.0) {
            return 1/(1 + exp($pp-$a));
        } else {
            return 1/(1 + exp($a-$pp));
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
        if ($k < 1.0) {
            return -exp($pp) / (exp($a) + exp($pp));
        } else {
            return exp($a) / (exp($a) + exp($pp));
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
        return -(exp($a + $pp) / ((exp($a) + exp($pp)) ** 2));
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
                fn ($ip) => (exp($pp) / (exp($ip['difficulty']) + exp($pp))) // d/da
                ];
        } else {
            return [
                fn ($ip) => (-exp($ip['difficulty'] + $pp) / ((exp($ip['difficulty']) + exp($pp)) * (exp($pp)))) // d/da
                ];
        }
    }

    /**
     * Calculates the 2nd derivative of the LOG Likelihood with respect to the item parameters
     *
     * @param float $pp - person ability parameter
     * @param float $k - answer category (0 or 1.0)
     * @return array of function($ip)
     */
    public static function get_log_hessian($p, float $item_response): array
    {
        // We can do this better, yet it works
        if ($item_response < 1.0) {
           return [[
                fn ($ip) => (exp($pp) / (exp($ip['difficulty']) + exp($pp)) ** 2) // d^2/ da^2
                ]];
        } else {
            return [[
                fn ($ip) => (-exp($ip['difficulty'] + $pp) / (exp($ip['difficulty']) + exp($pp)) ** 2) // d^2/ da^2
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
        return (self::likelihood($pp,$ip,0) * self::likelihood($pp,$ip,1.0));
    }

    /**
     * Implements a Filter Function for trusted regions in the item parameter estimation
     *
     * @param array $ip
     * return array
     */
    public static function restrict_to_trusted_region(array $ip): array {
        // Set values for difficulty parameter
        $a = $ip['difficulty'];
        
        $a_m = 0; // Mean of difficulty
        $a_s = 2; // Standard derivation of difficulty

        // Use x times of SD as range of trusted regions
        $a_tr = get_config('catmodel_raschbirnbauma', 'trusted_region_factor_sd_a');
        $a_min = get_config('catmodel_raschbirnbauma', 'trusted_region_min_a');
        $a_max = get_config('catmodel_raschbirnbauma', 'trusted_region_max_a');

        // Test TR for difficulty
        if (($a - $a_m) < max(-($a_tr * $a_s), $a_min)) {$a = max(-($a_tr * $a_s), $a_min); }
        if (($a - $a_m) > min(($a_tr * $a_s), $a_max)) {$a = min(($a_tr * $a_s), $a_max); }

        $ip['difficulty'] = $a;
        
        return $ip;
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

        $a_tr = get_config('catmodel_raschbirnbauma', 'trusted_region_factor_sd_a');

        return [
            fn ($ip) => (($a_m - $ip['difficulty']) / ($a_s ** 2)) // d/da
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

        return [[
            fn ($x) => (-1/ ($a_s ** 2)) // d/da d/da
        ]];
    }


// DEPRICATED STUFF TO BE REMOVED //

    
    // Depricated, please remove
    public static function counter_log_likelihood_p_p($p, array $params): float {
        $b = $params['difficulty'];
        return -(exp($b + $p)/(exp($b) + exp($p))**2);
    }

    // Depricated, please remove
    public static function counter_log_likelihood_p($p, array $params): float {
        $b = $params['difficulty'];
        return -(exp($p)/(exp($b) + exp($p)));
    }
    
    // Depricated, please remove
    public static function log_counter_likelihood($p, array $params)
    {
        $b = $params['difficulty'];

        $a = 1;
        $c = 0;
        return log(1-$c-((1-$c)*exp($a*(-$b+$p)))/(1+exp($a*(-$b+$p))));
    }

    // Should also be depricated and same as likelihood, please remove when not necessary
    public static function likelihood_multi(float $p, array $x)
    {
        $a = 1;
        $c = 0;
        $b = $x['difficulty'];

        return $c + (1- $c) * (exp($a*($p - $b)))/(1 + exp($a*($p-$b)));
    }

    // Should be depricated as well, please remove
    public static function log_likelihood_b($pp, array $ip)
    {
        $a = $ip['difficulty'];
        return ((-1)*exp(($a+$pp)))/((exp($a)+exp($pp))*(exp($pp)));
    }

    // Should be depricated as well, please remove
    public static function log_counter_likelihood_b($p, array $params)
    {
        $a = $ip['difficulty'];
        return (exp($pp))/(exp($a)+exp($pp));
    }

    // Should be depricated as well, please remove
    public static function get_log_counter_likelihood($p)
    {

        $fun = function ($x) use ($p) {
            return self::log_counter_likelihood($p, $x);
        };
        return $fun;
    }

    // Should be depricated as well, please remove
    public static function log_likelihood_b_b($pp, array $ip)
    {
        $a = $ip['difficulty'];
        return (-exp(-$a + $pp) * ( exp(2  * (-$a + $pp))))/((1 + exp(-$a + $pp))**2 * exp(-$a + $pp)**2) ;
    }

    // Should be depricated as well, please remove
    public static function log_counter_likelihood_b_b($p, array $params)
    {
        $b = $params['difficulty'];

        $a = 1;
        return -(($a**2 * exp($a * ($b + $p)))/(exp($a * $b) + exp($a * $p))**2);
    }
}
