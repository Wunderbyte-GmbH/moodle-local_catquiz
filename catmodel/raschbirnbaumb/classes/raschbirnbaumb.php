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

    // Definitions and Dimensions.

    /**
     * Definition of the number of model parameters
     *
     * @return int
     */
    public static function get_model_dim():int{
        return 3;  // 3 parameters: person ability, difficulty, discrimination.
    }

    /**
     * Initiate item parameter list
     *
     * @return model_item_param_list
     */
    public static function get_item_parameters():model_item_param_list{
        // TODO implement.
        return new model_item_param_list();
    }

    /**
     * Initiate person ability parameter list
     *
     * @return model_person_param_list
     */
    public static function get_person_abilities():model_person_param_list{
        // TODO implement.
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

    /**
     * Defines names if item parameter list
     *
     * @return array of string
     */
    public static function get_parameter_names():array{
        return ['difficulty', 'discrimination', ];
    }

    // Calculate the Likelihood.

    /**
     * Calculates the Likelihood for a given the person ability parameter
     *
     * @param float $pp - person ability parameter
     * @param array<float> $ip - item parameters ('difficulty', 'discrimination')
     * @param float $k - answer category (0 or 1.0)
     * @return float
     */
    public static function likelihood($pp, array $ip, float $k):float{
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];
        if ($k < 1.0) {
            return 1/(1 + exp($b * ($pp-$a)));
        } else {
            return 1/(1 + exp($b * ($a-$pp)));
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
    public static function log_likelihood_p_p($pp, array $ip, float $k):float{
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
    public static function get_log_jacobian($pp, float $k):array{
        if ($k < 1.0) {
            return [
                fn ($ip) => ($ip['discrimination'] * exp($ip['discrimination'] * $pp)) / (exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)), // Calculates d/da.
                fn ($ip) => (exp($ip['discrimination'] * $pp) * ( $ip['difficulty'] - $pp)) / (exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)) // Calculates d/db.
                ];
        } else {
            return [
                fn ($ip) => -($ip['discrimination'] * exp( $ip['difficulty'] * $ip['discrimination'])) / (exp( $ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)), // Calculates d/da.
                fn ($ip) => (exp( $ip['difficulty'] * $ip['discrimination']) * ($pp - $ip['difficulty'])) /(exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)) // Calculates d/db.
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
    public static function get_log_hessian($pp, float $item_response):array{
        if ($item_response >= 1.0) {
           return [[
                fn ($ip) => (-($ip['discrimination'] ** 2 * exp($ip['discrimination'] * ($ip['difficulty'] + $pp))) / ((exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)) ** 2)), // Calculates d²/da².
                fn ($ip) => (-(exp($ip['difficulty'] * $ip['discrimination']) * (exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp) * (1 + $ip['discrimination'] * ($ip['difficulty'] - $pp)))) / ((exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)) ** 2)) // Calculates d/a d/db.
                ],[
                fn ($ip) => (-(exp($ip['difficulty'] * $ip['discrimination']) * (exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp) * (1 + $ip['discrimination'] * ($ip['difficulty'] - $pp)))) / ((exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)) ** 2)), // Calculates d/a d/db.
                fn ($ip) => (-(exp($ip['discrimination'] * ($ip['difficulty'] + $pp)) * ($ip['difficulty'] - $pp) ** 2) / ((exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)) ** 2)) // Calculates d²/db².
            ]];
        } else {
            return [[
                fn ($ip) => -($ip['discrimination'] ** 2 * exp($ip['discrimination'] * ($ip['difficulty'] - $pp))) / (1 + exp($ip['discrimination'] * ($ip['difficulty'] - $pp))) ** 2, // Calculates d²/da².
                fn ($ip) => (1 + exp($ip['discrimination'] * ($ip['difficulty'] - $pp)) * (1 + $ip['discrimination'] * ($pp - $ip['difficulty']))) / (1 + exp($ip['discrimination'] * ($ip['difficulty'] - $pp))) ** 2, // Calculates d/da d/db.
            ],[
                fn ($ip) => (1 + exp($ip['discrimination'] * ($ip['difficulty'] - $pp)) * (1 + $ip['discrimination'] * ($pp - $ip['difficulty']))) / (1 + exp($ip['discrimination'] * ($ip['difficulty'] - $pp))) ** 2, // Calculates d/da d/db.
                fn ($ip) => -(exp($ip['discrimination'] * ($ip['difficulty'] - $pp)) * ($ip['difficulty'] - $pp) ** 2) / (1 + exp($ip['discrimination'] * ($ip['difficulty'] - $pp))) ** 2 // Calculates d²/db².
           ]];
        }
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
        $derivative = [0, 0];
        $a = $ip['difficulty']; $b = $ip['discrimination'];
        
        foreach ($pp as $key => $ability) {
            if (!(is_float($n[$key]) && is_float($k[$key]))) { continue; }
            
            $derivative[0] += $n[$key] * (2 * $b * exp($b * ($a - $ability)) ($k[$key] -1 + exp($b * ($a - $ability)) * $k[$key])) / (1 + exp($b * ($a - $ability))) ** 3; // Calculate d/da.            
            $derivative[1] += $n[$key] * (2 * exp($b * ($a - $ability)) * ($a - $ability) * ($k[$key] -1 + exp($b * ($a - $ability)) * $k[$key])) / (1 + exp($b * ($a - $ability))) ** 3; // Calculate d/db.
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
        $derivative = [[0, 0],[0, 0]];
        $a = $ip['difficulty']; $b = $ip['discrimination'];
        
        foreach ($pp as $key => $ability) {
            if (!(is_float($n[$key]) && is_float($k[$key]))) { continue; }
            
            $derivative[0][0]  += $n[$key] * (2 * $b ** 2 * exp($b ($a + $ability)) * (-exp(2 * $b * $ability) + 2 * exp($b ($a + $ability)) + (-exp(2 * $a * $b) + exp(2 * $b * $ability)) * $k[$key])) / (exp($a * $b) + exp($b * $ability)) ** 4; // Calculate d²2/da².            
            $derivative[0][1]  += $n[$key] * (2 * exp($b * ($a - $ability)) * ((1 + $a * $b - $b * $ability) * ($k[$key] -1) - exp(2 * $b * ($a - $ability)) * ($b * ($a - $ability) - 1) * $k[$key] + exp($b * ($a - $ability)) * (2 * $a * $b - 2 * $b * $ability + 2 * $k[$key] - 1))) / (1 + exp($b * ($a - $ability))) ** 4; // Calculate d/da d/db.    
            $derivative[1][1]  += $n[$key] * (2 * exp($b * ($a + $ability)) * ($a - $ability) ** 2 * (2 * exp($b * ($a + $ability)) + (-exp(2 * $a * $b) + exp(2 * $b * $ability)) * $k[$key] - exp(2 * $b * $ability))) / (exp($a * $b) + exp($b * $ability)) ** 4; // Calculate d²/db².
        }
        $derivative[1][0] = $derivative[0][1]; // Note: Partial derivations are exchangeible, cf. Theorem of Schwarz.
        
        return $derivative;
    }

    // Calculate Fisher-Information.
    
    /**
     * Calculates the Fisher Information for a given person ability parameter
     *
     * @param float $pp
     * @param array<float> $ip
     * @return float
     */
    public static function fisher_info(float $pp,array $ip):float{
        return ($ip['discrimination'] ** 2 * self::likelihood($pp,$ip,0) * self::likelihood($pp,$ip,1.0));
    }

    // Implements handling of the Trusted Regions (TR) approach.
    
    /**
     * Implements a Filter Function for trusted regions in the item parameter estimation
     *
     * @param array $ip
     * return array
     */
    public static function restrict_to_trusted_region(array $parameters):array{
        // Set values for difficulty parameter.
        $a = $parameters['difficulty'];

        $a_m = 0; // Mean of difficulty.
        $a_s = 2; // Standard derivation of difficulty.

        // Use x times of SD as range of trusted regions.
        $a_tr = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_factor_sd_a'));
        $a_min = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_min_a'));
        $a_max = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_max_a'));

        // Set values for disrciminatory parameter.
        $b = $parameters['discrimination'];

        // Placement of the discriminatory parameter.
        $b_p = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_placement_b'));
        // Slope of the discriminatory parameter.
        $b_s = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_slope_b'));
        // Use x times of placement as maximal value of trusted region.
        $b_tr = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_factor_max_b'));

        $b_min = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_min_b'));
        $b_max = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_max_b'));

        // Test TR for difficulty.
        if (($a - $a_m) < max(-($a_tr * $a_s), $a_min)) {$a = max(-($a_tr * $a_s), $a_min); }
        if (($a - $a_m) > min(($a_tr * $a_s), $a_max)) {$a = min(($a_tr * $a_s), $a_max); }

        $parameters['difficulty'] = $a;

        // Test TR for discriminatory.
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
    public static function get_log_tr_jacobian():array{
        // Set values for difficulty parameter.
        $a_m = 0; // Mean of difficulty.
        $a_s = 2; // Standard derivation of difficulty.

        // Placement of the discriminatory parameter.
        $b_p = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_placement_b'));
        // Slope of the discriminatory parameter.
        $b_s = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_slope_b'));

        return [
            fn ($x) => (($a_m - $x['difficulty']) / ($a_s ** 2)), // Calculates d/da.
            fn ($x) => (-($b_s * exp($b_s * $x['discrimination'])) / (exp($b_s * $b_p) + exp($b_s * $x['discrimination']))) // Calculates d/db.
        ];
    }

    /**
     * Calculates the 2nd derivative trusted regions for item parameters
     *
     * @return array
     */
    public static function get_log_tr_hessian():array{
        // Set values for difficulty parameter.
        $a_m = 0; // Mean of difficulty.
        $a_s = 2; // Standard derivation of difficulty.

        // Placement of the discriminatory parameter.
        $b_p = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_placement_b'));
        // Slope of the discriminatory parameter.
        $b_s = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_slope_b'));

        return [[
            fn ($x) => (-1/ ($a_s ** 2)), // Calculates d²/da².
            fn ($x) => (0) // Calculates d/da d/db.
        ],[
            fn ($x) => (0), // Calculates d/da d/db.
            fn ($x) => (-($b_s ** 2 * exp($b_s * ($b_p + $x['discrimination']))) / (exp($b_s * $b_p) + exp($b_s * $x['discrimination'])) ** 2) // Calculates d²/db².
        ]];
    }

}
