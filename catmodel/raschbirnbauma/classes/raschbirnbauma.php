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
class raschbirnbauma extends model_model
{

    // info: x[0] <- "difficulty"


    public function get_model_dim()
    {
        return 2;  // we have 4 params ( ability, difficulty)
    }


    public function get_item_parameters(): model_item_param_list
    {
        // TODO implement
        return new model_item_param_list();
    }

    public function get_person_abilities(): model_person_param_list
    {
        // TODO implement
        return new model_person_param_list();
    }

    public function calculate_params($item_response): array
    {
        $difficulty = catcalc::estimate_item_params($item_response, $this);
        return ['difficulty' => $difficulty];
    }

    /**
     * @return string[] 
     */
    public static function get_parameter_names(): array {
        return ['difficulty',];
    }

    // # elementary model functions


    public function likelihood($p,$b)
    {

        $a = 1;
        $c = 0;

        return $c + (1- $c) * (exp($a*($p - $b)))/(1 + exp($a*($p-$b)));
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

    public function counter_likelihood($p, $b)
    {
        $a = 1;
        $c = 0;
        return 1 - $this->likelihood($p,$a,$b,$c);
    }

    public function log_likelihood($p, $b)
    {
        $a = 1;
        $c = 0;
        return log($c + ((1-$c)*exp($a*(-$b+$p)))/(1+exp($a*(-$b+$p))));

    }

    public function log_counter_likelihood($p, $b)
    {
        $a = 1;
        $c = 0;
        return log(1-$c-((1-$c)*exp($a*(-$b+$p)))/(1+exp($a*(-$b+$p))));
    }

    public function log_likelihood_b($p, $b)
    {
        $a = 1;
        $c = 0;
        return ($a*(-1+$c)*exp($a*($b+$p)))/((exp($a * $b)+exp($a*$p))*($c*exp($a*$b)+exp($a*$p)));
    }



    // jacobian

    public function log_counter_likelihood_b($p, $b)
    {
        $a = 1;
        $c = 0;
        return ($a*exp($a*$p))/(exp($a*$b)+exp($a*$p));
    }



    // hessian


    public function log_likelihood_b_b($p, $b)
    {
        $a = 1;
        $c = 0;

        return ($a**2 * (-1 + $c) * exp($a * (-$b + $p)) * (-$c + exp(2 * $a * (-$b + $p))))/((1 + exp($a * (-$b + $p)))**2 * ($c + exp($a * (-$b + $p)))**2) ;
    }

    // counter

    public function log_counter_likelihood_b_b($p, $b)
    {

        $a = 1;
        $c = 0;
        return -(($a**2 * exp($a * ($b + $p)))/(exp($a * $b) + exp($a * $p))**2);
    }



    /**
     * Used to estimate the item difficulty
     * @param mixed $p
     * @return Closure(mixed $x): float
     */
    public function get_log_likelihood($p)
    {

        $fun = function ($x) use ($p) {
            return $this->log_likelihood($p, $x[0]);
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
            return $this->log_counter_likelihood($p, $x[0]);
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
            return $this->log_likelihood_b($p, $x[0]);
        };

        return [$fun1];

    }

    public function get_log_counter_jacobian($p)
    {


        $fun1 = function ($x) use ($p) {
            return $this->log_counter_likelihood_b($p, $x[0]);
        };
        return [$fun1];

    }


    public function get_log_hessian($p)
    {

        $fun22 = function ($x) use ($p) {
            return $this->log_likelihood_b_b($p, $x[0]);
        };

        return [[$fun22]];

    }

    public function get_log_counter_hessian($p)
    {

        $fun22 = function ($x) use ($p) {
            return $this->log_counter_likelihood_b_b($p, $x[0]);
        };

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
}
