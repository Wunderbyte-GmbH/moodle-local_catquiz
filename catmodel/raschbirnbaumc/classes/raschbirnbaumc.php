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

use Closure;
use local_catquiz\catcalc;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_model;
use local_catquiz\local\model\model_person_param_list;

defined('MOODLE_INTERNAL') || die();

defined('MOODLE_INTERNAL') || die();

/**
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class raschbirnbaumc extends model_model
{

    // info: x[0] <- "discrimination"
    // info: x[1] <- "difficulty"
    // info: x[2] <- "guessing"

    public function get_model_dim()
    {
        return 4;  // we have 4 params ( ability, difficulty, discrimination, guessing)
    }

    public function calculate_params($item_response): array
    {
        list ($difficulty, $discrimination, $guessing) = catcalc::estimate_item_params($item_response, $this);
        return [
            'difficulty' => $difficulty,
            'discrimination' => $discrimination,
            'guessing' => $guessing,
        ];
    }

    /**
     * @return string[] 
     */
    public static function get_parameter_names(): array {
        return ['difficulty', 'discrimination', 'guessing'];
    }

    // # elementary model functions


    public function likelihood($p, $a, $b, $c)
    {

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
        return $x[2] + (1- $x[2]) * (exp($x[0]*($p - $x[1])))/(1 + exp($x[0]*($p-$x[1])));
    }

    public function counter_likelihood($p, $a, $b, $c)
    {

        return 1 - $this->likelihood($p,$a,$b,$c);
    }

    public function log_likelihood($p, $a, $b, $c)
    {

        return log($c + ((1-$c)*exp($a*(-$b+$p)))/(1+exp($a*(-$b+$p))));

    }

    public function log_counter_likelihood($p, $a, $b, $c)
    {

        return log(1-$c-((1-$c)*exp($a*(-$b+$p)))/(1+exp($a*(-$b+$p))));
    }

    // jacobian


    public function log_likelihood_a($p, $a, $b, $c)
    {
        return ((-1 + $c) * exp($a*($b+$p))*($b-$p))/((exp($a*$b)+exp($a*$p))*($c * exp($a * $b) + exp($a*$p))) ;
    }

    public function log_likelihood_b($p, $a, $b, $c)
    {
        return ($a*(-1+$c)*exp($a*($b+$p)))/((exp($a * $b)+exp($a*$p))*($c*exp($a*$b)+exp($a*$p)));
    }

    public function log_likelihood_c($p, $a, $b, $c)
    {
        return 1 / ($c + exp($a * (-$b + $p)));
    }

    public function log_counter_likelihood_a($p, $a, $b, $c)
    {
        return (exp($a * $p)*($b-$p))/(exp($a*$b)+exp($a*$p));
    }

    public function log_counter_likelihood_b($p, $a, $b, $c)
    {
        return ($a*exp($a*$p))/(exp($a*$b)+exp($a*$p));
    }

    public function log_counter_likelihood_c($p, $a, $b, $c)
    {
        return 1 / (-1 + $c);
    }

    // hessian

    public function log_likelihood_a_a($p, $a, $b, $c)
    {
        return ((-1 + $c) * exp($a * (-$b + $p)) * (-$c + exp(2 * $a * (-$b + $p))) * ($b - $p)**2)/((1 + exp($a * (-$b + $p)))**2 * ($c + exp($a * (-$b + $p)))**2);
    }

    public function log_likelihood_b_b($p, $a, $b, $c)
    {
        return ($a**2 * (-1 + $c) * exp($a * (-$b + $p)) * (-$c + exp(2 * $a * (-$b + $p))))/((1 + exp($a * (-$b + $p)))**2 * ($c + exp($a * (-$b + $p)))**2) ;
    }

    public function log_likelihood_c_c($p, $a, $b, $c)
    {
        return -(1/($c + exp($a * (-$b + $p)))**2);
    }

    public function log_likelihood_a_b($p, $a, $b, $c)
    {
        return ((-1 + $c) * exp($a * ($b + $p)) * (exp($a * ($b + $p)) + exp(2 * $a * $p) * (1 + $a * $b - $a * $p) + $c * (exp($a * ($b + $p)) + exp(2 * $a * $b) * (1 - $a * $b + $a * $p))))/((exp($a * $b) + exp($a * $p))**2 * ($c * exp($a * $b) + exp($a * $p))**2);
    }

    public function log_likelihood_a_c($p, $a, $b, $c)
    {
        return (exp($a * ($b + $p)) * ($b - $p))/($c * exp($a * $b) + exp($a *$p))**2 ;
    }

    public function log_likelihood_b_c($p, $a, $b, $c)
    {
        return ($a * exp($a * ($b + $p)))/($c * exp($a * $b) + exp($a * $p))**2;
    }


    // counter


    public function log_counter_likelihood_a_a($p, $a, $b, $c)
    {
        return -((exp($a * ($b + $p)) * ($b - $p)**2)/(exp($a * $b) + exp($a * $p))**2);
    }

    public function log_counter_likelihood_b_b($p, $a, $b, $c)
    {
        return -(($a**2 * exp($a * ($b + $p)))/(exp($a * $b) + exp($a * $p))**2);
    }

    public function log_counter_likelihood_c_c($p, $a, $b, $c)
    {
        return -1/(-1 + $c)**2;
        #return 1;
    }


    public function log_counter_likelihood_a_b($p, $a, $b, $c)
    {
        return (exp(2 * $a * $p) + exp($a * ($b + $p)) * (1 + $a * (-$b + $p)))/(exp($a * $b) + exp($a * $p))**2 ;
    }


    public function log_counter_likelihood_a_c($p, $a, $b, $c)
    {
        return  0;
    }

    public function log_counter_likelihood_b_c($p, $a, $b, $c)
    {
        return  0;
    }





    /**
     * Used to estimate the item difficulty
     * @param mixed $p
     * @return Closure(mixed $x): float
     */
    public function get_log_likelihood($p)
    {

        $fun = function ($x) use ($p) {
            return $this->log_likelihood($p, $x[0], $x[1],$x[2]);
        };
        return $fun;
    }

    /**
     * Used to estimate the item difficulty
     * @param mixed $p
     * @return Closure(mixed $x): float
     */
    public function get_log_counter_likelihood($p)
    {

        $fun = function ($x) use ($p) {
            return $this->log_counter_likelihood($p, $x[0], $x[1], $x[2]);
        };
        return $fun;
    }


    /**
     * Get elementary matrix function for being composed
     */
    public function get_log_jacobian($p)
    {

        // $ip ....Array of item parameter

        // return: Array [ df / d ip1 , df / d ip2]

        $fun1 = function ($x) use ($p) {
            return $this->log_likelihood_a($p, $x[0], $x[1],$x[2]);
        };
        $fun2 = function ($x) use ($p) {
            return $this->log_likelihood_b($p, $x[0], $x[1],$x[2]);
        };
        $fun3 = function ($x) use ($p) {
            return $this->log_likelihood_c($p, $x[0], $x[1],$x[2]);
        };



        return [$fun1, $fun2, $fun3];

    }

    public function get_log_counter_jacobian($p)
    {

        $fun1 = function ($x) use ($p) {
            return $this->log_counter_likelihood_a($p, $x[0], $x[1], $x[2]);
        };
        $fun2 = function ($x) use ($p) {
            return $this->log_counter_likelihood_b($p, $x[0], $x[1], $x[2]);
        };
        $fun3 = function ($x) use ($p) {
            return $this->log_counter_likelihood_c($p, $x[0], $x[1], $x[2]);
        };

        return [$fun1, $fun2, $fun3];

    }


    public function get_log_hessian($p)
    {

        $fun11 = function ($x) use ($p) {
            return $this->log_likelihood_a_a($p, $x[0], $x[1], $x[2]);
        };
        $fun12 = function ($x) use ($p) {
            return $this->log_likelihood_a_b($p, $x[0], $x[1], $x[2]);
        };

        $fun13 = function ($x) use ($p) {
            return $this->log_likelihood_a_c($p, $x[0], $x[1], $x[2]);
        };

        $fun21 = $fun12; # theorem of Schwarz

        $fun22 = function ($x) use ($p) {
            return $this->log_likelihood_b_b($p, $x[0], $x[1], $x[2]);
        };

        $fun23 = function ($x) use ($p) {
            return $this->log_likelihood_b_c($p, $x[0], $x[1], $x[2]);
        };

        $fun31 = $fun13; # theorem of Schwarz

        $fun32 = $fun23; # theorem of Schwarz

        $fun33 = function ($x) use ($p) {
            return $this->log_likelihood_c_c($p, $x[0], $x[1], $x[2]);
        };

        return [[$fun11, $fun12, $fun13], [$fun21, $fun22, $fun23], [$fun31, $fun32, $fun33]];

    }

    public function get_log_counter_hessian($p)
    {

        $fun11 = function ($x) use ($p) {
            return $this->log_counter_likelihood_a_a($p, $x[0], $x[1], $x[2]);
        };
        $fun12 = function ($x) use ($p) {
            return $this->log_counter_likelihood_a_b($p, $x[0], $x[1], $x[2]);
        };

        $fun13 = function ($x) use ($p) {
            return $this->log_counter_likelihood_a_c($p, $x[0], $x[1], $x[2]);
        };

        $fun21 = $fun12; # theorem of Schwarz

        $fun22 = function ($x) use ($p) {
            return $this->log_counter_likelihood_b_b($p, $x[0], $x[1], $x[2]);
        };

        $fun23 = function ($x) use ($p) {
            return $this->log_counter_likelihood_b_c($p, $x[0], $x[1], $x[2]);
        };

        $fun31 = $fun13; # theorem of Schwarz

        $fun32 = $fun23; # theorem of Schwarz

        $fun33 = function ($x) use ($p) {
            return @$this->log_counter_likelihood_c_c($p, $x[0], $x[1], $x[2]);
        };

        return [[$fun11, $fun12, $fun13], [$fun21, $fun22, $fun23], [$fun31, $fun32, $fun33]];
    }

    public static function fisher_info($p,$x){

        return $x[0]**2 * (1 - $x[2]) * self::likelihood_multi($p,$x) * (1-self::likelihood_multi($p,$x));

    }
}