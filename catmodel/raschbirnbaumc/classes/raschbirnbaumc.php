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

namespace catmodel_raschbirnbaumc;

use local_catquiz\catcalc;
use local_catquiz\catcalc_interface;
use local_catquiz\local\model\model_raschmodel;

defined('MOODLE_INTERNAL') || die();

defined('MOODLE_INTERNAL') || die();

/**
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class raschbirnbaumc extends model_raschmodel implements catcalc_interface
{

    public static function log_likelihood_p($p, array $params): float {
        //$a = $params['difficulty'];
        //$b = $params['discrimination'];
        //$c = $params['guessing'];
        $a = $params[0];
        $b = $params[1];
        $c = $params[2];
        return -(($a * (-1 + $c) * exp($a * ($b + $p)))/((exp($a * $b) + exp($a * $p)) * ($c * exp($a * $b) + exp($a * $p))));
    }

    public static function counter_log_likelihood_p($p, array $params): float {
        $a = $params[0];
        $b = $params[1];
        $c = $params[2];

        // TODO: implement here
        return -(($a * exp($a * $p))/(exp($a * $b) + exp($a * $p)));
    }

    public static function log_likelihood_p_p($p, array $params): float {
        $a = $params[0];
        $b = $params[1];
        $c = $params[2];
        return ($a**2 * (-1 + $c) * exp( $a * (-$b + $p)) * (-$c + exp(2 * $a (-$b + $p))))/((1 + exp($a (-$b + $p)))**2 * ($c + exp(   $a * (-$b + $p)))**2);
    }

    public static function counter_log_likelihood_p_p($p, array $params): float {
        $a = $params[0];
        $b = $params[1];
        $c = $params[2];
        return -(($a**2 * exp($a * ($b + $p)))/(exp($a * $b) + exp($a * $p))**2);
    }

    public static function get_model_dim(): int
    {
        return 4;  // we have 4 params ( ability, difficulty, discrimination, guessing)
    }

    public function calculate_params($item_response): array
    {
        return catcalc::estimate_item_params($item_response, $this);
    }

    /**
     * @return string[]
     */
    public static function get_parameter_names(): array {
        return ['difficulty', 'discrimination', 'guessing'];
    }

    // # elementary model functions


    public function likelihood($p, array $params)
    {
        $a = $params['discrimination'];
        $b = $params['difficulty'];
        $c = $params['guessing'];

        return $c + (1- $c) * (exp($a*($p - $b)))/(1 + exp($a*($p-$b)));
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
        return $x['guessing'] + (1- $x['guessing']) * (exp($x['difficulty']*($p - $x['discrimination'])))/(1 + exp($x['difficulty']*($p-$x['discrimination'])));
    }

    public static function counter_likelihood($p, array $params)
    {
        $a = $params['discrimination'];
        $b = $params['difficulty'];
        $c = $params['guessing'];

        return 1 - self::likelihood($p,$params);
    }

    public static function log_likelihood($p, array $params)
    {
        $a = $params['discrimination'];
        $b = $params['difficulty'];
        $c = $params['guessing'];

        return log($c + ((1-$c)*exp($a*(-$b+$p)))/(1+exp($a*(-$b+$p))));

    }

    public static function log_counter_likelihood($p, array $params)
    {
        $a = $params['discrimination'];
        $b = $params['difficulty'];
        $c = $params['guessing'];

        return log(1-$c-((1-$c)*exp($a*(-$b+$p)))/(1+exp($a*(-$b+$p))));
    }

    // jacobian


    public static function log_likelihood_a($p, array $params)
    {
        $a = $params['discrimination'];
        $b = $params['difficulty'];
        $c = $params['guessing'];

        return ((-1 + $c) * exp($a*($b+$p))*($b-$p))/((exp($a*$b)+exp($a*$p))*($c * exp($a * $b) + exp($a*$p))) ;
    }

    public static function log_likelihood_b($p, array $params)
    {
        $a = $params['discrimination'];
        $b = $params['difficulty'];
        $c = $params['guessing'];

        return ($a*(-1+$c)*exp($a*($b+$p)))/((exp($a * $b)+exp($a*$p))*($c*exp($a*$b)+exp($a*$p)));
    }

    public static function log_likelihood_c($p, array $params)
    {
        $a = $params['discrimination'];
        $b = $params['difficulty'];
        $c = $params['guessing'];

        return 1 / ($c + exp($a * (-$b + $p)));
    }

    public static function log_counter_likelihood_a($p, array $params)
    {
        $a = $params['discrimination'];
        $b = $params['difficulty'];
        $c = $params['guessing'];

        return (exp($a * $p)*($b-$p))/(exp($a*$b)+exp($a*$p));
    }

    public static function log_counter_likelihood_b($p, array $params)
    {
        $a = $params['discrimination'];
        $b = $params['difficulty'];
        $c = $params['guessing'];

        return ($a*exp($a*$p))/(exp($a*$b)+exp($a*$p));
    }

    public static function log_counter_likelihood_c($p, array $params)
    {
        $a = $params['discrimination'];
        $b = $params['difficulty'];
        $c = $params['guessing'];
        return 1 / (-1 + $c);
    }

    // hessian

    public static function log_likelihood_a_a($p, array $params)
    {
        $a = $params['discrimination'];
        $b = $params['difficulty'];
        $c = $params['guessing'];

        return ((-1 + $c) * exp($a * (-$b + $p)) * (-$c + exp(2 * $a * (-$b + $p))) * ($b - $p)**2)/((1 + exp($a * (-$b + $p)))**2 * ($c + exp($a * (-$b + $p)))**2);
    }

    public static function log_likelihood_b_b($p, array $params)
    {
        $a = $params['discrimination'];
        $b = $params['difficulty'];
        $c = $params['guessing'];

        return ($a**2 * (-1 + $c) * exp($a * (-$b + $p)) * (-$c + exp(2 * $a * (-$b + $p))))/((1 + exp($a * (-$b + $p)))**2 * ($c + exp($a * (-$b + $p)))**2) ;
    }

    public static function log_likelihood_c_c($p, array $params)
    {
        $a = $params['discrimination'];
        $b = $params['difficulty'];
        $c = $params['guessing'];

        return -(1/($c + exp($a * (-$b + $p)))**2);
    }

    public static function log_likelihood_a_b($p, array $params)
    {
        $a = $params['discrimination'];
        $b = $params['difficulty'];
        $c = $params['guessing'];

        return ((-1 + $c) * exp($a * ($b + $p)) * (exp($a * ($b + $p)) + exp(2 * $a * $p) * (1 + $a * $b - $a * $p) + $c * (exp($a * ($b + $p)) + exp(2 * $a * $b) * (1 - $a * $b + $a * $p))))/((exp($a * $b) + exp($a * $p))**2 * ($c * exp($a * $b) + exp($a * $p))**2);
    }

    public static function log_likelihood_a_c($p, array $params)
    {
        $a = $params['discrimination'];
        $b = $params['difficulty'];
        $c = $params['guessing'];

        return (exp($a * ($b + $p)) * ($b - $p))/($c * exp($a * $b) + exp($a *$p))**2 ;
    }

    public static function log_likelihood_b_c($p, array $params)
    {
        $a = $params['discrimination'];
        $b = $params['difficulty'];
        $c = $params['guessing'];

        return ($a * exp($a * ($b + $p)))/($c * exp($a * $b) + exp($a * $p))**2;
    }


    // counter


    public static function log_counter_likelihood_a_a($p, array $params)
    {
        $a = $params['discrimination'];
        $b = $params['difficulty'];
        $c = $params['guessing'];

        return -((exp($a * ($b + $p)) * ($b - $p)**2)/(exp($a * $b) + exp($a * $p))**2);
    }

    public static function log_counter_likelihood_b_b($p, array $params)
    {
        $a = $params['discrimination'];
        $b = $params['difficulty'];
        $c = $params['guessing'];

        return -(($a**2 * exp($a * ($b + $p)))/(exp($a * $b) + exp($a * $p))**2);
    }

    public static function log_counter_likelihood_c_c($p, array $params)
    {
        $a = $params['discrimination'];
        $b = $params['difficulty'];
        $c = $params['guessing'];

        return -1/(-1 + $c)**2;
        #return 1;
    }

    public static function log_counter_likelihood_a_b($p, array $params)
    {
        $a = $params['discrimination'];
        $b = $params['difficulty'];
        $c = $params['guessing'];

        return (exp(2 * $a * $p) + exp($a * ($b + $p)) * (1 + $a * (-$b + $p)))/(exp($a * $b) + exp($a * $p))**2 ;
    }

    public static function log_counter_likelihood_a_c($p, array $params)
    {
        return  0;
    }

    public static function log_counter_likelihood_b_c($p, array $params)
    {
        return  0;
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
        $fun3 = function ($x) use ($p) {
            return self::log_likelihood_c($p, $x);
        };



        return [$fun1, $fun2, $fun3];

    }

    public static function get_log_counter_jacobian($p)
    {

        $fun1 = function ($x) use ($p) {
            return self::log_counter_likelihood_a($p, $x);
        };
        $fun2 = function ($x) use ($p) {
            return self::log_counter_likelihood_b($p, $x);
        };
        $fun3 = function ($x) use ($p) {
            return self::log_counter_likelihood_c($p, $x);
        };

        return [$fun1, $fun2, $fun3];

    }


    public static function get_log_hessian($p)
    {

        $fun11 = function ($x) use ($p) {
            return self::log_likelihood_a_a($p, $x);
        };
        $fun12 = function ($x) use ($p) {
            return self::log_likelihood_a_b($p, $x);
        };

        $fun13 = function ($x) use ($p) {
            return self::log_likelihood_a_c($p, $x);
        };

        $fun21 = $fun12; # theorem of Schwarz

        $fun22 = function ($x) use ($p) {
            return self::log_likelihood_b_b($p, $x);
        };

        $fun23 = function ($x) use ($p) {
            return self::log_likelihood_b_c($p, $x);
        };

        $fun31 = $fun13; # theorem of Schwarz

        $fun32 = $fun23; # theorem of Schwarz

        $fun33 = function ($x) use ($p) {
            return self::log_likelihood_c_c($p, $x);
        };

        return [[$fun11, $fun12, $fun13], [$fun21, $fun22, $fun23], [$fun31, $fun32, $fun33]];

    }

    public static function get_log_counter_hessian($p)
    {

        $fun11 = function ($x) use ($p) {
            return self::log_counter_likelihood_a_a($p, $x);
        };
        $fun12 = function ($x) use ($p) {
            return self::log_counter_likelihood_a_b($p, $x);
        };

        $fun13 = function ($x) use ($p) {
            return self::log_counter_likelihood_a_c($p, $x);
        };

        $fun21 = $fun12; # theorem of Schwarz

        $fun22 = function ($x) use ($p) {
            return self::log_counter_likelihood_b_b($p, $x);
        };

        $fun23 = function ($x) use ($p) {
            return self::log_counter_likelihood_b_c($p, $x);
        };

        $fun31 = $fun13; # theorem of Schwarz

        $fun32 = $fun23; # theorem of Schwarz

        $fun33 = function ($x) use ($p) {
            return @self::log_counter_likelihood_c_c($p, $x);
        };

        return [[$fun11, $fun12, $fun13], [$fun21, $fun22, $fun23], [$fun31, $fun32, $fun33]];
    }

    public static function fisher_info($p,$x){

        return $x['difficulty']**2 * (1 - $x['guessing']) * self::likelihood_multi($p,$x) * (1-self::likelihood_multi($p,$x));

    }
}
