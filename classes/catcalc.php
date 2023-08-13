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
 * Class for catcalc functions;
 *
 * @package local_catquiz
 * @author Daniel Pasterk
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_model;
use local_catquiz\local\model\model_strategy;
use local_catquiz\mathcat;

class catcalc {
    
    /**
     * Gives a first (and rought) estimation of 1PL item parameters upon probability of being solved without any prior information
     *
     * @param array $item_list
     * @return array<float> - difficulties of items
     */
    static function estimate_initial_item_difficulties($item_list) {

        $item_difficulties = array();
        $item_ids = array_keys($item_list);

        foreach ($item_ids as $id) {

            $item_fractions = $item_list[$id];
            $num_passed = 0;
            $num_failed = 0;

            // TODO taking fractions into account
            
            foreach ($item_fractions as $fraction) {
                if ($fraction == 1) {
                    $num_passed += 1;
                } else {
                    $num_failed += 1;
                }
            }

            $p = $num_passed / ($num_failed + $num_passed);
            //$item_difficulty = -log($num_passed / $num_failed);
            $item_difficulty = -log($p / (1 - $p + 0.00001)); //TODO: numerical stability check
            $item_difficulties[$id] = $item_difficulty;

        }
        return $item_difficulties;
    }
    
    /**
     *Estimates numerically a person's ability via maximizing Log Likelihood
     *
     * @param array $person_responses
     * @param model_item_param_list $items
     * @return array<float>
     */
    static function estimate_person_ability($person_responses, model_item_param_list $items): float {
        $all_models = model_strategy::get_installed_models();

        // Define 1st derivative and 2nd derivative of the Log Likelihood with resect to person ability.
        $ll_1st_derivative = [];
        $ll_2nd_derivative = [];

        // Get 1st derivative and 2nd derivative for all given items.
        foreach ($person_responses as $qid => $qresponse) {
            $item = $items[$qid];
            
            // The item parameter for this response was filtered out.
            if ($item === null) {
                continue;
            }
            $item_params = $item->get_params_array();

            $model = $all_models[$item->get_model_name()];
            if (!in_array(catcalc_ability_estimator::class, class_implements($model))) {
                throw new \Exception(sprintf("The given model %s can not be used with the catcalc class", $item->get_model_name()));
            }
            
            $ll_1st_derivative[] = fn ($pp) => $model::log_likelihood_p($pp, $item_params, $qresponse['fraction']);
            $ll_2nd_derivative[] = fn ($pp) => $model::log_likelihood_p_p($pp, $item_params, $qresponse['fraction']);
        }

        // Add all 1st and 2nd derivatives together to one function each.
        $ll_1st_derivative = function($pp) use($1st_derivative) {foreach ($ll_1st_derivative as $key => $ll1) {$ll_1st_derivative[$key] = $ll1($pp);} return $ll_1st_derivative;};
        $ll_1st_derivative = fn($pp) => $ml->multi_sum($ll_1st_derivative($pp));
        
        $ll_2nd_derivative = function($pp) use($ll_2nd_derivative) {foreach ($ll_2nd_derivative as $key => $ll2) {$ll_2nd_derivative[$key] = $ll2($pp);} return $ll_2nd_derivative;};
        $ll_2nd_derivative = fn($pp) => $ml->multi_sum($ll_2nd_derivative($pp));

        // Estimate person ability via Newton-Raphson algorithm.
        return mathcat::newton_raphson_multi_stable(
            $loglikelihood_1st_derivative,
            $loglikelihood_2nd_derivative,
            0,
            6,
            100,
            function($pp) {if ($pp < -10) {return -10;}; if ($pp > 10) {return 10;}; return $pp;}
        ); // @DAVID: Auch hier: Trusted Regions needed, oder?
    }

    /**
     *Estimates numerically item parameters via minimizing Least Mean Squares and maximizing Log Likelihood
     *
     * @param array $item_response
     * @param model_model $model
     * @return array<float>
     */
    static function estimate_item_params(array $item_response, model_model $model) {
        if (! $model instanceof catcalc_item_estimator) {
            throw new \InvalidArgumentException("Model does not implement the catcalc_item_estimator interface");
        }
        
        $ml = new matrixcat();
        $model_dim = $model::get_model_dim();
        
        // Estimate the starting point via Least Mean Squares.
        $start_arr = ['difficulty' => 0, 'discrimination' => 1, 'guessing' => 0]; //@DAVID: Das ist modellabhängig, da die Parameter selbst modellabhängig ist. Sollte dies nicht besser in den Modellen definiert werden?
        $z_0 = array_slice($start_arr, 0, $model_dim-1);

        // @DAVID @RALF: TODO Implement Least Mean Squares estmation.
        
        // Define Jacobi vector (1st derivative) and Hesse matrix (2nd derivative) of the Log Likelihood.
        $jacobian = [];
        $hessian = [];
        
        foreach ($item_response as $r) {
            $jacobian[] = fn($ip) => $model::get_log_jacobian($r->get_ability(), $ip, $r->get_response());
            $hessian[] = fn($ip) => $model::get_log_hessian($r->get_ability(), $ip, $r->get_response());
        }

        $jacobian = function($ip) use($jacobian) {foreach ($jacobian as $key => $j) {$jacobian[$key] = $j($ip);} return $jacobian;};
        $jacobian = fn($ip) => $ml->multi_sum($jacobian($ip));
        
        $hessian = function($ip) use($hessian) {foreach ($hessian as $key => $h) {$hessian[$key] = $h($ip);} return $hessian;};
        $$hessian = fn($ip) => $ml->multi_sum($$hessian($ip));

        // Estimate item parameters via Newton-Raphson algorithm.
        return mathcat::newton_raphson_multi_stable(
            $jacobian,
            $hessian,
            $z_0,
            6,
            100,
            $model->restrict_to_trusted_region,
            $model->get_log_tr_jacobian,
            $model->get_log_tr_hessian
        );
    }
}
