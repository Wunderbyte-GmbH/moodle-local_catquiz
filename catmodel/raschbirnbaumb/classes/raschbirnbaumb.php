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
use local_catquiz\local\model\model_raschmodel;

defined('MOODLE_INTERNAL') || die();

/**
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class raschbirnbaumb extends model_raschmodel
{

    public static function log_likelihood_p($p, array $params, float $item_response): float {
        if ($item_response < 1.0) {
            return self::counter_log_likelihood_p($p, $params);
        }
        $a = $params['discrimination'];
        $b = $params['difficulty'];

        return $a/(1 + exp($a * (-$b + $p)));
    }

    public static function counter_log_likelihood_p($p, array $params): float {
        $a = $params['discrimination'];
        $b = $params['difficulty'];
        return -(($a * exp($a * $p))/(exp($a * $b) + exp($a * $p)));
    }

    public static function log_likelihood_p_p($p, array $params, float $item_response): float {
        if ($item_response < 1.0) {
            return self::counter_log_likelihood_p_p($p, $params);
        }
        $a = $params['discrimination'];
        $b = $params['difficulty'];

        return -(($a**2 * exp($a * ($b + $p)))/(exp($a * $b) + exp($a * $p))**2);
    }

    public static function counter_log_likelihood_p_p($p, array $params): float {
        $a = $params['discrimination'];
        $b = $params['difficulty'];
        return -(($a**2 * exp($a * ($b + $p)))/(exp($a * $b) + exp($a * $p))**2);
    }

    public static function get_model_dim(): int
    {
        return 3;  // we have 3 params ( ability, difficulty, discrimination)
    }

    public function calculate_params($item_response): array
    {
        return catcalc::estimate_item_params($item_response, $this);
    }

    /**
     * @return string[]
     */
    public static function get_parameter_names(): array {
        return ['difficulty', 'discrimination',];
    }

    // # elementary model functions


    public static function likelihood($p, array $params, float $item_response) {
        $a = $params['discrimination'];
        $b = $params['difficulty'];
        $value = (1 / (1 + exp($a * ($b - $p))));

        if ($item_response < 1.0) {
            return 1 - $value;
        }
        return $value;
    }

    /**
     * Generalisierung von `likelihood`: params $a und $b werden im array/vector als $x[0] und $x[1] angesprochen
     * Kann in likelihood umbenannt werden
     * @param mixed $p
     * @param mixed $x
     * @return int|float
     */
    public static function likelihood_multi($p, $x)
    {
        return (1 / (1 + exp($x['difficulty'] * ($x['discrimination'] - $p))));
    }

    public static function log_likelihood($p, array $params, float $item_response)
    {
        if ($item_response < 1.0) {
            return self::log_counter_likelihood($p, $params);
        }

        $a = $params['discrimination'];
        $b = $params['difficulty'];

        return log((exp($a * (-$b + $p))) / (1 + exp($a * (-$b + $p))));
    }

    public static function log_counter_likelihood($p, array $params)
    {
        $a = $params['discrimination'];
        $b = $params['difficulty'];

        return log(1 - (exp($a * (-$b + $p))) / (1 + exp($a * (-$b + $p))));
    }

    // jacobian


    public static function log_likelihood_a($p, array $params)
    {
        $a = $params['discrimination'];
        $b = $params['difficulty'];

        return (-$b + $p) / (1 + exp($a * (-$b + $p)));
    }

    public static function log_likelihood_b($p, array $params)
    {
        $a = $params['discrimination'];
        $b = $params['difficulty'];

        return - ($a) / (1 + exp($a * (-$b + $p)));
    }

    public static function log_counter_likelihood_a($p, array $params)
    {
        $a = $params['discrimination'];
        $b = $params['difficulty'];

        return (exp($a * $p) * ($b - $p)) / (exp($a * $b) + exp($a * $p));
    }

    public static function log_counter_likelihood_b($p, array $params)
    {
        $a = $params['discrimination'];
        $b = $params['difficulty'];

        return ($a * exp($a * $p)) / (exp($a * $b) + exp($a * $p));
    }

    // hessian

    public static function log_likelihood_a_a($p, array $params)
    {
        $a = $params['discrimination'];
        $b = $params['difficulty'];

        return - (exp($a * ($b + $p)) * ($b - $p) ** 2) / (exp($a * $b) + exp($a * $p)) ** 2;
    }

    public static function log_likelihood_a_b($p, array $params)
    {
        $a = $params['discrimination'];
        $b = $params['difficulty'];

        return (-1 + exp($a * (-$b + $p)) * (-1 + $a * (-$b + $p))) / (1 + exp($a * (-$b + $p))) ** 2;
    }

    public static function log_likelihood_b_b($p, array $params)
    {
        $a = $params['discrimination'];
        $b = $params['difficulty'];

        return - ($a ** 2 * exp($a * ($b + $p))) / (exp($a * $b) + exp($a * $p)) ** 2;
    }


    //
    public static function log_counter_likelihood_a_a($p, array $params)
    {
        $a = $params['discrimination'];
        $b = $params['difficulty'];

        return - (exp($a * ($b + $p)) * ($b - $p) ** 2) / (exp($a * $b) + exp($a * $p)) ** 2;
    }

    public static function log_counter_likelihood_a_b($p, array $params)
    {
        $a = $params['discrimination'];
        $b = $params['difficulty'];

        return (exp(2 * $a * $p) + exp($a * ($b + $p)) * (1 + $a * (-$b + $p))) / (exp($a * $b) + exp($a * $p)) ** 2;
    }

    public static function log_counter_likelihood_b_b($p, array $params)
    {
        $a = $params['discrimination'];
        $b = $params['difficulty'];

        return - ($a ** 2 * exp($a * ($b + $p))) / (exp($a * $b) + exp($a * $p)) ** 2;

    }

    /**
     * Used to estimate the item difficulty
     * @param mixed $p
     * @return Closure(mixed $x): float
     */
    public static function get_log_counter_likelihood($p)
    {

        $fun = function ($x) use ($p) {
            return self::log_counter_likelihood($p, $x);
        };
        return $fun;
    }


    /**
     * Get elementary matrix function for being composed
     */
    public static function get_log_jacobian($p, float $item_response):array
    {
        // We can do this better, yet it works
        if ($item_response < 1.0) {
             $fun1 = function ($x) use ($p) {
                return self::log_counter_likelihood_a($p, $x);
            };
            $fun2 = function ($x) use ($p) {
                return self::log_counter_likelihood_b($p, $x);
            };
        } else {
            $fun1 = function ($x) use ($p) {
                return self::log_likelihood_a($p, $x);
            };
            $fun2 = function ($x) use ($p) {
                return self::log_likelihood_b($p, $x);
            };
        }
        return [$fun1, $fun2];
    }
    
    // deprecated, please remove
    public static function get_log_counter_jacobian($p)
    {
    }


    public static function get_log_hessian($p, float $item_response):array
    {
        // We can do this better, yet it works
        if ($item_response < 1.0) {
            $fun11 = function ($x) use ($p) {
                return self::log_counter_likelihood_a_a($p, $x);
            };
            $fun12 = function ($x) use ($p) {
                return self::log_counter_likelihood_a_b($p, $x);
            };
            $fun21 = function ($x) use ($p) {
                return self::log_counter_likelihood_a_b($p, $x);
            }; # theorem of Schwarz
            $fun22 = function ($x) use ($p) {
                return self::log_counter_likelihood_b_b($p, $x);
            };
        } else {
            $fun11 = function ($x) use ($p) {
                return self::log_likelihood_a_a($p, $x);
            };
            $fun12 = function ($x) use ($p) {
                return self::log_likelihood_a_b($p, $x);
            };
            $fun21 = function ($x) use ($p) {
                return self::log_likelihood_a_b($p, $x);
            }; # theorem of Schwarz
            $fun22 = function ($x) use ($p) {
                return self::log_likelihood_b_b($p, $x);
            };
        }
        return [[$fun11, $fun12], [$fun21, $fun22]];
    }

    // deprecated, please remove
    public static function get_log_counter_hessian($p)
    {
    }
    public static function fisher_info($p,$x){

        return $x['difficulty']**2 * self::likelihood_multi($p,$x) * (1-self::likelihood_multi($p,$x));

    }

    public function restrict_to_trusted_region(array $parameters): array {
        // Set values for difficulty parameter
        $a = $parameters[0]; // Difficulty

        $a_m = 0; // Mean of difficulty
        $a_s = 2; // Standard derivation of difficulty
        
        $a_tr = 3; // Use 3 times of SD as range of trusted regions
        // $a_tr = get_config('catmodel_raschbirnbaumb', 'trusted_region_factor_sd_a'); // Use x times of SD as range of trusted regions
        $a_min = get_config('catmodel_raschbirnbaumb', 'trusted_region_min_a');
        $a_max = get_config('catmodel_raschbirnbaumb', 'trusted_region_max_a');

        // Set values for disrciminatory parameter
        $b = $parameters[1]; // Discriminatory

        $b_p = 5; // Placement of the discriminatory parameter 
        // $b_p = get_config('catmodel_raschbirnbaumb', 'trusted_region_placement_b'); // Placement of the discriminatory parameter 
        $b_s = 2; // Slope of the discriminatory parameter
        // $b_s = get_config('catmodel_raschbirnbaumb', 'trusted_region_slope_b'); // Slope of the discriminatory parameter 
        $b_tr = 5; // // Use 5 times of placement as maximal value of trusted region
        // $b_p = get_config('catmodel_raschbirnbaumb', 'trusted_region_factor_max_b');
        
        $b_min = get_config('catmodel_raschbirnbaumb', 'trusted_region_min_b');
        $b_max = get_config('catmodel_raschbirnbaumb', 'trusted_region_max_b'); 

        // Test TR for difficulty
        if (($a - $a_m) < max(-($a_tr * $a_s), $a_min)) {$a = max(-($a_tr * $a_s), $a_min); }
        if (($a - $a_m) > min(($a_tr * $a_s), $a_max)) {$a = min(($a_tr * $a_s), $a_max); }

        $parameters[0] = $a;

        // Test TR for discriminatory
        if ($b < $b_min) {$b = $b_min; }
        if ($b > min(($b_tr * $b_p),$b_max)) {$b = min(($b_tr * $b_p),$b_max); }

        $parameters[1] = $b;
        
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

        // Set values for disrciminatory parameter
        $b = $parameters[1]; // Discriminatory

        $b_p = 5; // Placement of the discriminatory parameter 
        // $b_p = get_config('catmodel_raschbirnbaumb', 'trusted_region_placement_b'); // Placement of the discriminatory parameter 
        $b_s = 2; // Slope of the discriminatory parameter
        // $b_s = get_config('catmodel_raschbirnbaumb', 'trusted_region_slope_b');

        return [
            function ($x) { return (($a_m - $x[0]) / ($a_s ** 2)) }, // d/da
            function ($x) { return (-($b_s * exp($b_s * $x[1])) / (exp($b_s * $b_p) + exp($b_s * $x[1]))) } // d/db
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

        // Set values for disrciminatory parameter
        $b = $parameters[1]; // Discriminatory

        $b_p = 5; // Placement of the discriminatory parameter 
        // $b_p = get_config('catmodel_raschbirnbaumb', 'trusted_region_placement_b'); // Placement of the discriminatory parameter 
        $b_s = 2; // Slope of the discriminatory parameter
        // $b_s = get_config('catmodel_raschbirnbaumb', 'trusted_region_slope_b');

        return [[
            function ($x) { return (-1/ ($a_s ** 2)) }, // d/da d/da
            function ($x) { return (0) } //d/da d/db
],[
            function ($x) { return (0) }, //d/db d/da
            function ($x) { return (-($b_s ** 2 * exp($b_s * ($b_p + $x[1]))) / (exp($b_s * $b_p) + exp($b_s * $x[1])) ** 2) } // d/db d/db
]];
    }
}
