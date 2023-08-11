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

    // Definitions and Dimensions.

    /**
     * Defines names if item parameter list
     *
     * @return array of string
     */
    public static function get_parameter_names():array{
        return ['difficulty', ];
    }
    
    /**
     * Definition of the number of model parameters
     *
     * @return int
     */
    public static function get_model_dim():int{
        return count(self::get_parameter_names());
    }

    /**
     * Initiate item parameter list
     *
     * @return model_item_param_list
     */
    public static function get_item_parameters():model_item_param_list{
        // TODO implement
        return new model_item_param_list();
    }

    /**
     * Initiate person ability parameter list
     *
     * @return model_person_param_list
     */
    public static function get_person_abilities():model_person_param_list{
        // TODO implement
        return new model_person_param_list();
    }

    /**
     * Estimate item parameters
     *
     * @param float
     * @return model_person_param_list
     */
    public function calculate_params($item_response):array{
        return catcalc::estimate_item_params($item_response, $this);
    }
    
    // Calculate the Likelihood.
    
    /**
     * Calculates the Likelihood for a given the person ability parameter
     *
     * @param float $pp - person ability parameter
     * @param array<float> $ip - item parameters ('difficulty')
     * @param float $k - answer category (0 or 1.0)
     * @return float
     */
    public static function likelihood($pp, array $ip, float $k):float{
        $a = $params['difficulty'];
        if ($item_response < 1.0) {
            return 1/(1 + exp($pp-$a));
        } else {
            return 1/(1 + exp($a-$pp));
        }
    }
    
    // Calculate the LOG Likelihood and its derivatives.

    /**
     * Calculates the LOG Likelihood for a given the person ability parameter
     *
     * @param float $pp - person ability parameter
     * @param array<float> $ip - item parameters ('difficulty')
     * @param float $k - answer category (0 or 1.0)
     * @return float
     */
    public static function log_likelihood($pp, array $ip, float $k):float{
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
    public static function log_likelihood_p($pp, array $ip, float $k):float{
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
        if ($k >= 1.0) {
            return [
                fn ($ip) => (-exp($ip['difficulty'] + $pp) / ((exp($ip['difficulty']) + exp($pp)) * (exp($pp)))) // d/da
            ];  
        } else {
            return [
                fn ($ip) => (exp($pp) / (exp($ip['difficulty']) + exp($pp))) // d/da
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
    public static function get_log_hessian($pp, float $k): array
    {
        // 2nd derivative is equal for both k = 0 and k = 1
        return [[
            fn ($ip) => -exp($ip['difficulty'] + $pp) / (exp($ip['difficulty']) + exp($pp)) ** 2 // d²/ da²               
        ]];
    }
    
    // Calculate the Least-Mean-Squres (LMS) approach.
    
     /**
     * Calculates the Least Mean Squres (residuals) for a given the person ability parameter and a given expected/observed score
     *
     * @param array of float $pp - person ability parameter
     * @param array of float $ip - item parameters ('difficulty', 'discrimination', 'guessing')
     * @param array of float $k - fraction of correct (0 ... 1.0)
     * @param array of float $n - number of observations
     * @return float - weighted residuals
     */   
    public static function least_mean_squares(array $pp, array $ip, array $k, array $n):float{
        $lms_residuals = 0;
        $number_total = 0;
        
        foreach ($pp as $key => $ability) {
            if (!(is_float($n[$key]) && is_float($k[$key]))) { continue; }
            
            $lms_residuals += $n[$key] * ($k[$key] - self::likelihood($ability, $ip, 1.0)) ** 2;
            $number_total += $n[$key];
        }
        return (($number_total  > 0) ? ($lms_residuals / $number_total) : (0));
    }

    /**
     * Calculates the 1st derivative of Least Mean Squres with respect to the item parameters
     *
     * @param array of float $pp - person ability parameter
     * @param array of float $ip - item parameters ('difficulty', 'discrimination')
     * @param array of float $k - fraction of correct (0 ... 1.0)
     * @param array of float $n - number of observations
     * @return array - 1st derivative
     */   
    public static function least_mean_squares_1st_derivative_ip(array $pp, array $ip, array $k, array $n):array{
        $derivative = [0];
        $a = $ip['difficulty']; $b = $ip['discrimination']; $c = $ip['guessing'];
        
        foreach ($pp as $key => $ability) {
            if (!(is_float($n[$key]) && is_float($k[$key]))) { continue; }
            
            $derivative[0] += $n[$key] * (2 * exp($a + $pp) * (exp($a) * $k[$key] + exp($pp) * ($k[$key]) - 1)) / (exp($a) + exp($pp)) ** 3; // Calculate d/da.            
        }
        return $derivative;
    }
    
    /**
     * Calculates the 2nd derivative of Least Mean Squres with respect to the item parameters
     *
     * @param array of float $pp - person ability parameter
     * @param array of float $ip - item parameters ('difficulty', 'discrimination')
     * @param array of float $k - fraction of correct (0 ... 1.0)
     * @param array of float $n - number of observations
     * @return array - 1st derivative
     */   
    public static function least_mean_squares_2nd_derivative_ip(array $pp, array $ip, array $k, array $n):array{
        $derivative = [[0]];
        $a = $ip['difficulty']; $b = $ip['discrimination'];
        
        foreach ($pp as $key => $ability) {
            if (!(is_float($n[$key]) && is_float($k[$key]))) { continue; }
            
            $derivative[0][0]  += $n[$key] * (2 * exp($a + $pp) * (2 * exp($a + $pp) + exp(2 * $pp) * (-1 + $k[$key]) - exp(2 * $a) * $k[$key])) / (exp($a) + exp($pp)) ** 4; // Calculate d²/da².            
            }
        return $derivative;
    }

    // Calculate Fisher-Information.
    
    /**
     * Calculates the Fisher Information for a given person ability parameter.
     *
     * @param float $pp
     * @param array<float> $ip
     * @return float
     */
    public static function fisher_info(float $pp,array $ip){
        return (self::likelihood($pp,$ip,0) * self::likelihood($pp,$ip,1.0));
    }

    // Implements handling of the Trusted Regions (TR) approach.
    
    /**
     * Implements a Filter Function for trusted regions in the item parameter estimation
     *
     * @param array $ip
     * return array
     */
    public static function restrict_to_trusted_region(array $ip): array {
        // Set values for difficulty parameter.
        $a = $ip['difficulty'];
        
        $a_m = 0; // Mean of difficulty.
        $a_s = 2; // Standard derivation of difficulty.

        // Use x times of SD as range of trusted regions.
        $a_tr = floatval(get_config('catmodel_raschbirnbauma', 'trusted_region_factor_sd_a'));
        $a_min = floatval(get_config('catmodel_raschbirnbauma', 'trusted_region_min_a'));
        $a_max = floatval(get_config('catmodel_raschbirnbauma', 'trusted_region_max_a'));

        // Test TR for difficulty.
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
        // Set values for difficulty parameter.
        $a_m = 0; // Mean of difficulty.
        $a_s = 2; // Standard derivation of difficulty.

        $a_tr = floatval(get_config('catmodel_raschbirnbauma', 'trusted_region_factor_sd_a'));

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
        // Set values for difficulty parameter.
        $a_m = 0; // Mean of difficulty
        $a_s = 2; // Standard derivation of difficulty

        return [[
            fn ($x) => (-1/ ($a_s ** 2)) // Calculate d/da d/da.
        ]];
    }
}
