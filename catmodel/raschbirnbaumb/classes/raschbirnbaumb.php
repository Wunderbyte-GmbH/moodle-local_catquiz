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
use local_catquiz\catcalc_interface;
use local_catquiz\local\model\model_raschmodel;

defined('MOODLE_INTERNAL') || die();

/**
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class raschbirnbaumb extends model_raschmodel implements catcalc_interface
{

    public static function log_likelihood_p($p, array $params): float {
        $a = $params['discrimination'];
        $b = $params['difficulty'];

        return $a/(1 + exp($a * (-$b + $p)));
    }

    public static function counter_log_likelihood_p($p, array $params): float {
        $a = $params['discrimination'];
        $b = $params['difficulty'];
        return -(($a * exp($a * $p))/(exp($a * $b) + exp($a * $p)));
    }

    public static function log_likelihood_p_p($p, array $params): float {
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


    public static function likelihood($p, array $params) {
        $a = $params['discrimination'];
        $b = $params['difficulty'];
        return (1 / (1 + exp($a * ($b - $p))));
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

    public static function counter_likelihood($p, array $params)
    {
        return 1 - self::likelihood($p, $params);
    }

    public static function log_likelihood($p, array $params)
    {
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
    public static function get_log_likelihood($p)
    {

        $fun = function ($x) use ($p) {
            return self::log_likelihood($p, $x);
        };
        return $fun;
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
    public static function get_log_jacobian($p)
    {

        // $ip ....Array of item parameter

        // return: Array [ df / d ip1 , df / d ip2]

        $fun1 = function ($x) use ($p) {
            return self::log_likelihood_a($p, $x);
        };
        $fun2 = function ($x) use ($p) {
            return self::log_likelihood_b($p, $x);
        };

        return [$fun1, $fun2];

    }

    public static function get_log_counter_jacobian($p)
    {

        $fun1 = function ($x) use ($p) {
            return self::log_counter_likelihood_a($p, $x);
        };
        $fun2 = function ($x) use ($p) {
            return self::log_counter_likelihood_b($p, $x);
        };

        return [$fun1, $fun2];

    }


    public static function get_log_hessian($p)
    {

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

        return [[$fun11, $fun12], [$fun21, $fun22]];

    }

    public static function get_log_counter_hessian($p)
    {

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

        return [[$fun11, $fun12], [$fun21, $fun22]];

    }
    public static function fisher_info($p,$x){

        return $x['difficulty']**2 * self::likelihood_multi($p,$x) * (1-self::likelihood_multi($p,$x));

    }
}
