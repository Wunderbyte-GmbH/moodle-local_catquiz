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

use catmodel_raschbirnbauma\raschmodel;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_model;
use local_catquiz\local\model\model_strategy;
use local_catquiz\mathcat;

class catcalc {

    static function estimate_initial_item_difficulties($item_list) {

        $item_difficulties = array();
        $item_ids = array_keys($item_list);

        foreach ($item_ids as $id) {

            $item_fractions = $item_list[$id];
            $num_passed = 0;
            $num_failed = 0;

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

    static function estimate_person_ability($demo_person_response, model_item_param_list $items): float {
        //if (! $model instanceof catcalc_interface) {
        //    throw new \InvalidArgumentException("Model does not implement the catcalc_interface");
        //}

       $all_models = model_strategy::get_installed_models();

        $likelihood = fn($x) => 1;
        $loglikelihood = fn($x) => 0;
        $loglikelihood_1st_derivative = fn($x) => 0;
        $loglikelihood_2nd_derivative = fn($x) => 0;

        foreach ($demo_person_response as $qid => $qresponse) {
            $item_params = $items[$qid]->get_params_array();
            $model = $all_models[$items[$qid]->get_model_name()];

            if ($qresponse['fraction'] == 1) {
                $likelihood_part = fn($x) => $model::get_callable_likelihood($x, $item_params);
                $loglikelihood_part = fn($x) => $model::get_callable_log_likelihood($x, $item_params);
                $loglikelihood_1st_derivative_part = fn($x) => $model::log_likelihood_p($x, $item_params);
                $loglikelihood_2nd_derivative_part = fn($x) => $model::log_likelihood_p_p($x, $item_params);

                $likelihood = fn($x) => $likelihood($x) * $likelihood_part($x);
                $loglikelihood = fn($x) => $loglikelihood($x) + $loglikelihood_part($x);
                $loglikelihood_1st_derivative = fn($x) => $loglikelihood_1st_derivative($x) + $loglikelihood_1st_derivative_part($x);
                $loglikelihood_2nd_derivative = fn($x) => $loglikelihood_2nd_derivative($x) + $loglikelihood_2nd_derivative_part($x);
            } else if ($qresponse['fraction'] == 0) {
                $likelihood_part = fn($x) => $model::get_callable_likelihood_counter($x, $item_params);
                $loglikelihood_part = fn($x) => $model::get_callable_log_likelihood_counter($x, $item_params);
                $loglikelihood_1st_derivative_part = fn($x) => $model::counter_log_likelihood_p($x, $item_params);
                $loglikelihood_2nd_derivative_part = fn($x) => $model::counter_log_likelihood_p_p($x, $item_params);

                $likelihood = fn($x) => $likelihood($x) * $likelihood_part($x);
                $loglikelihood = fn($x) => $loglikelihood($x) + $loglikelihood_part($x);
                $loglikelihood_1st_derivative = fn($x) => $loglikelihood_1st_derivative($x) + $loglikelihood_1st_derivative_part($x);
                $loglikelihood_2nd_derivative = fn($x) => $loglikelihood_2nd_derivative($x) + $loglikelihood_2nd_derivative_part($x);
            };
        }

        $retval = mathcat::newtonraphson_stable(
            $loglikelihood_1st_derivative,
            $loglikelihood_2nd_derivative,
            0,
            0.001,
            1500
        );

        return $retval;
    }

    static function estimate_person_ability2($person_response, $item_difficulties){

        $likelihood_parts = [];
        $dbg_trace1 = [];
        $dbg_trace2 = [];
        $dbg_trace3 = [];

        $likelihood = function($x) {
            return 1;
        };
        $loglikelihood = function($x) {
            return 0;
        };
        $loglikelihood_1st_derivative = function($x) {
            return 0;
        };
        $loglikelihood_2nd_derivative = function($x) {
            return 0;
        };

        $num_pos = 0;
        $num_neg = 0;


        foreach ($person_response as $qid => $qresponse) {

            //$item_difficulty = 0.5;
            $item_difficulty = $item_difficulties[$qid];

            if ($qresponse['fraction'] == 1) {

                $num_pos += 1;

                $likelihood_part = function($x) use ($item_difficulty) {
                    return \catmodel_raschbirnbauma\raschmodel::likelihood($x, $item_difficulty);
                };
                $loglikelihood_part = function($x) use ($item_difficulty) {
                    return \catmodel_raschbirnbauma\raschmodel::log_likelihood($x, $item_difficulty);
                };
                $loglikelihood_1st_derivative_part = function($x) use ($item_difficulty) {
                    return \catmodel_raschbirnbauma\raschmodel::log_likelihood_1st_derivative($x, $item_difficulty);
                };
                $loglikelihood_2nd_derivative_part = function($x) use ($item_difficulty) {
                    return \catmodel_raschbirnbauma\raschmodel::log_likelihood_2nd_derivative($x, $item_difficulty);
                };

                array_push($likelihood_parts, $likelihood_part);
                array_push($dbg_trace1, $qresponse['fraction']);
                array_push($dbg_trace2, $likelihood_part(0.3));

                $likelihood = \local_catquiz\mathcat::compose_multiply($likelihood, $likelihood_part);
                $loglikelihood = \local_catquiz\mathcat::compose_plus($loglikelihood, $loglikelihood_part);
                $loglikelihood_1st_derivative =
                        \local_catquiz\mathcat::compose_plus($loglikelihood_1st_derivative, $loglikelihood_1st_derivative_part);
                $loglikelihood_2nd_derivative =
                        \local_catquiz\mathcat::compose_plus($loglikelihood_2nd_derivative, $loglikelihood_2nd_derivative_part);

                array_push($dbg_trace3, $likelihood(0.3));

            } else if ($qresponse['fraction'] == 0) {

                $num_neg += 1;

                $likelihood_part = function($x) use ($item_difficulty) {
                    return \catmodel_raschbirnbauma\raschmodel::likelihood_counter($x, $item_difficulty);
                };

                $loglikelihood_part = function($x) use ($item_difficulty) {
                    return (\catmodel_raschbirnbauma\raschmodel::log_likelihood_counter($x, $item_difficulty));
                };
                $loglikelihood_1st_derivative_part = function($x) use ($item_difficulty) {
                    return (\catmodel_raschbirnbauma\raschmodel::log_likelihood_counter_1st_derivative($x, $item_difficulty));
                };
                $loglikelihood_2nd_derivative_part = function($x) use ($item_difficulty) {
                    return (\catmodel_raschbirnbauma\raschmodel::log_likelihood_counter_2nd_derivative($x, $item_difficulty));
                };

                array_push($likelihood_parts, $likelihood_part);
                array_push($dbg_trace1, $qresponse['fraction']);

                $likelihood = \local_catquiz\mathcat::compose_multiply($likelihood, $likelihood_part);

                $loglikelihood = \local_catquiz\mathcat::compose_plus($loglikelihood, $loglikelihood_part);
                $loglikelihood_1st_derivative =
                        \local_catquiz\mathcat::compose_plus($loglikelihood_1st_derivative, $loglikelihood_1st_derivative_part);
                $loglikelihood_2nd_derivative =
                        \local_catquiz\mathcat::compose_plus($loglikelihood_2nd_derivative, $loglikelihood_2nd_derivative_part);

                array_push($dbg_trace2, $likelihood_part(0.3));
                array_push($dbg_trace3, $likelihood(0.3));

            };
        }

        $likelihood_1st_derivative = \local_catquiz\mathcat::get_numerical_derivative2($likelihood);
        $likelihood_2nd_derivative = \local_catquiz\mathcat::get_numerical_derivative2($likelihood_1st_derivative);

        $loglike2nd_complete = \local_catquiz\mathcat::get_numerical_derivative($loglikelihood_1st_derivative);

        $num_log_like_1st_der = \local_catquiz\mathcat::get_numerical_derivative($loglikelihood);
        $num_log_like_2nd_der = \local_catquiz\mathcat::get_numerical_derivative($num_log_like_1st_der);

        $estimated_value =
                \local_catquiz\mathcat::newtonraphson_stable($loglikelihood_1st_derivative, $loglikelihood_2nd_derivative, 0, 0.001, 3000);
        //$estimated_value2 = mathcat::newton_raphson_multi([$loglikelihood_1st_derivative], [[$loglikelihood_2nd_derivative]], 0, 0.001, 1500);
        //$estimated_value2 = \local_catquiz\mathcat::newtonraphson($likelihood_1st_derivative, $likelihood_2nd_derivative,0.7,0.001,1500);
        //$estimated_value3 = \local_catquiz\mathcat::newtonraphson($num_log_like_1st_der, $num_log_like_2nd_der,0,0.001,1500);
        //$estimated_value4 = \local_catquiz\mathcat::newtonraphson_numeric($likelihood_1st_derivative,0,0.001,1500);

        return $estimated_value;



    }


    /**
     * @param array $item_response
     * @param model_model $model
     * @return array<float>
     */
    static function estimate_item_params(array $item_response, model_model $model) {
        if (! $model instanceof catcalc_interface) {
            throw new \InvalidArgumentException("Model does not implement the catcalc_interface");
        }

        // compose likelihood matrices based on actual result

        $model_dim = $model::get_model_dim();


        // empty callable structures for composition

        $loglikelihood = fn($x) => 0;

        // Vector that contains the first derivatives for each parameter as functions
        // [Df/Da, Df,/Db, Df,Dc]
        $jacobian = [];
        // Matrix that contains the second derivatives
        // [
        //  [Df/Daa, Df/Dab, Df/Dac]
        //  [Df/Dba, Df/Dbb, Df/Dbc]
        //  [Df/Dca, Df/Dcb, Df/Dcc]
        // ]
        $hessian = [];
        for ($i = 0; $i <= $model_dim - 2; $i++) {
            $jacobian[$i] = fn($x) => 0;
            $hessian[$i] = [];
            for ($j = 0; $j <= $model_dim - 2; $j++) {
                $hessian[$i][$j] = fn($x) => 0;
            }
        }

        $num_passed = 0;
        $num_failed = 0;
        foreach ($item_response as $r) {
            if ($r->get_response() == 1) { // if answer is correct
                $num_passed += 1;

                $likelihood_part = $model::get_log_likelihood($r->get_ability());
                $jacobian_part = $model::get_log_jacobian($r->get_ability());
                $hessian_part = $model::get_log_hessian($r->get_ability());

            } else {
                $num_failed += 1;

                $likelihood_part = $model::get_log_counter_likelihood($r->get_ability());
                $jacobian_part = $model::get_log_counter_jacobian($r->get_ability());
                $hessian_part = $model::get_log_counter_hessian($r->get_ability());
            }

            // chain with functions
            $loglikelihood = fn($x) => $loglikelihood($x) + $likelihood_part($x);

            for ($i=0; $i <= $model_dim-2; $i++){
                $jacobian[$i] = fn($x) => $jacobian[$i]($x) + $jacobian_part[$i]($x);

                for ($j=0; $j <= $model_dim-2; $j++) {
                    $hessian[$i][$j] = fn($x) => $hessian[$i][$j]($x) + $hessian_part[$i][$j]($x);
                }
            }
        }

        // Defines the starting point
        $start_arr = [0.5, 0.5, 0.5];
        $z_0 = array_slice($start_arr, 0, $model_dim-1);

        return mathcat::newton_raphson_multi($jacobian,$hessian,$z_0, 0.001, 50);
    }
}
