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

defined('MOODLE_INTERNAL') || die();

/**
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class raschbirnbauma extends model_raschmodel
{
    
    public static function log_likelihood_p($p, array $params, float $item_response): float {
        if ($item_response < 1.0) {
            return self::counter_log_likelihood_p($p, $params);
        }
        $b = $params['difficulty'];

        return exp($b)/(exp($b) + exp($p));
    }

    public static function counter_log_likelihood_p($p, array $params): float {
        $b = $params['difficulty'];
        return -(exp($p)/(exp($b) + exp($p)));
    }

    public static function log_likelihood_p_p($p, array $params, float $item_response): float {
        if ($item_response < 1.0) {
            return self::counter_log_likelihood_p_p($p, $params);
        }
        $b = $params['difficulty'];
        return -(exp($b + $p)/(exp($b) + exp($p))**2);
    }

    public static function counter_log_likelihood_p_p($p, array $params): float {
        $b = $params['difficulty'];
        return -(exp($b + $p)/(exp($b) + exp($p))**2);
    }

    // info: x[0] <- "difficulty"


    public static function get_model_dim(): int
    {
        return 2;  // we have 2 params ( ability, difficulty)
    }


    public static function get_item_parameters(): model_item_param_list
    {
        // TODO implement
        return new model_item_param_list();
    }

    public static function get_person_abilities(): model_person_param_list
    {
        // TODO implement
        return new model_person_param_list();
    }

    public function calculate_params($item_response): array
    {
        return catcalc::estimate_item_params($item_response, $this);
    }

    /**
     * @return string[]
     */
    public static function get_parameter_names(): array {
        return ['difficulty',];
    }

    // # elementary model functions


    public static function likelihood($p, array $params, float $item_response)
    {
        $b = $params['difficulty'];

        $a = 1;
        $c = 0;

        $value = $c + (1- $c) * (exp($a*($p - $b)))/(1 + exp($a*($p-$b)));

        if ($item_response < 1.0) {
            return 1 - $value;
        }
        return $value;
    }

    /**
     * Generalisierung von `likelihood`: params $a und $b werden im array/vector als $x[0] und $x[1] angesprochen
     * Kann in likelihood umbenannt werden
     * @param float $p
     * @param array<float> $x
     * @return int|float
     */
    public static function likelihood_multi(float $p, array $x)
    {
        $a = 1;
        $c = 0;
        $b = $x['difficulty'];

        return $c + (1- $c) * (exp($a*($p - $b)))/(1 + exp($a*($p-$b)));
    }

    public static function log_likelihood($p, array $params, float $item_response)
    {
        if ($item_response < 1.0) {
            return self::log_counter_likelihood($p, $params);
        }

        $b = $params['difficulty'];

        $a = 1;
        $c = 0;
        return log($c + ((1-$c)*exp($a*(-$b+$p)))/(1+exp($a*(-$b+$p))));

    }

    public static function log_counter_likelihood($p, array $params)
    {
        $b = $params['difficulty'];

        $a = 1;
        $c = 0;
        return log(1-$c-((1-$c)*exp($a*(-$b+$p)))/(1+exp($a*(-$b+$p))));
    }

    public static function log_likelihood_b($p, array $params)
    {
        $b = $params['difficulty'];

        $a = 1;
        $c = 0;
        return ($a*(-1+$c)*exp($a*($b+$p)))/((exp($a * $b)+exp($a*$p))*($c*exp($a*$b)+exp($a*$p)));
    }



    // jacobian

    public static function log_counter_likelihood_b($p, array $params)
    {
        $b = $params['difficulty'];
        $a = 1;

        return ($a*exp($a*$p))/(exp($a*$b)+exp($a*$p));
    }



    // hessian


    public static function log_likelihood_b_b($p, array $params)
    {
        $b = $params['difficulty'];

        $a = 1;
        $c = 0;

        return ($a**2 * (-1 + $c) * exp($a * (-$b + $p)) * (-$c + exp(2 * $a * (-$b + $p))))/((1 + exp($a * (-$b + $p)))**2 * ($c + exp($a * (-$b + $p)))**2) ;
    }

    // counter

    public static function log_counter_likelihood_b_b($p, array $params)
    {
        $b = $params['difficulty'];

        $a = 1;
        return -(($a**2 * exp($a * ($b + $p)))/(exp($a * $b) + exp($a * $p))**2);
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
            $fun1 = fn ($x) => self::log_counter_likelihood_b($p, $x);
        } else {
            $fun1 = fn ($x) => self::log_likelihood_b($p, $x);
        }
        return [$fun1];
    }

    public static function get_log_hessian($p, float $item_response): array
    {
        // We can do this better, yet it works
        if ($item_response < 1.0) {
            $fun22 = fn ($x) => self::log_counter_likelihood_b_b($p, $x);
        } else {
            $fun22 = fn ($x) => self::log_likelihood_b_b($p, $x);
        }
        return [[$fun22]];
    }

    /**
     *
     * @param float $p
     * @param array<float> $x
     * @return int|float
     */
    public static function fisher_info(float $p,array $x){

        return self::likelihood_multi($p,$x) * (1-self::likelihood_multi($p,$x));

    }

    public function restrict_to_trusted_region(array $parameters): array {
        // Set values for difficulty parameter
        $a = $parameters['difficulty'];
        
        $a_m = 0; // Mean of difficulty
        $a_s = 2; // Standard derivation of difficulty

        // Use x times of SD as range of trusted regions
        $a_tr = get_config('catmodel_raschbirnbauma', 'trusted_region_factor_sd_a');
        $a_min = get_config('catmodel_raschbirnbauma', 'trusted_region_min_a');
        $a_max = get_config('catmodel_raschbirnbauma', 'trusted_region_max_a');

        // Test TR for difficulty
        if (($a - $a_m) < max(-($a_tr * $a_s), $a_min)) {$a = max(-($a_tr * $a_s), $a_min); }
        if (($a - $a_m) > min(($a_tr * $a_s), $a_max)) {$a = min(($a_tr * $a_s), $a_max); }

        $parameters['difficulty'] = $a;
        
        return $parameters;
    }

    /**
     * Calculates the 1st derivative trusted regions for item parameters
     *
     * @param float $p
     * @return array
     */
    public static function get_log_tr_jacobian(array $parameters): array {
        // Set values for difficulty parameter
        $a_m = 0; // Mean of difficulty
        $a_s = 2; // Standard derivation of difficulty

        $a_tr = get_config('catmodel_raschbirnbauma', 'trusted_region_factor_sd_a');

        return [
            fn ($x) => (($a_m - $x['difficulty']) / ($a_s ** 2)) // d/da
        ];
    }

    /**
     * Calculates the 2nd derivative trusted regions for item parameters
     *
     * @param float $p
     * @return array
     */
    public static function get_log_tr_hessian(array $parameters): array {
        // Set values for difficulty parameter
        $a_m = 0; // Mean of difficulty
        $a_s = 2; // Standard derivation of difficulty

        return [[
            fn ($x) => (-1/ ($a_s ** 2)) // d/da d/da
        ]];
    }
}
