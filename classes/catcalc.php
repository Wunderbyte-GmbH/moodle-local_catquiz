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
 * Class for catcalc.
 *
 * @package local_catquiz
 * @author Daniel Pasterk
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use Closure;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_model;
use local_catquiz\local\model\model_strategy;
use local_catquiz\mathcat;

/**
 * Class for catcalc functions.
 *
 * @package local_catquiz
 * @author Daniel Pasterk
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catcalc {

    /**
     * Estimate initial item difficulties.
     *
     * @param mixed $itemlist
     *
     * @return array
     *
     */
    public static function estimate_initial_item_difficulties($itemlist) {

        $itemdifficulties = array();
        $itemids = array_keys($itemlist);

        foreach ($itemids as $id) {

            $itemfractions = $itemlist[$id];
            $numpassed = 0;
            $numfailed = 0;

            foreach ($itemfractions as $fraction) {
                if ($fraction == 1) {
                    $numpassed += 1;
                } else {
                    $numfailed += 1;
                }
            }

            $p = $numpassed / ($numfailed + $numpassed);
            // phpcs:ignore
            // $item_difficulty = -log($num_passed / $num_failed);
            $itemdifficulty = -log($p / (1 - $p + 0.00001)); // TODO: numerical stability check.
            $itemdifficulties[$id] = $itemdifficulty;

        }
        return $itemdifficulties;
    }

    /**
     * Estimate person ability.
     *
     * @param mixed $personresponses
     * @param model_item_param_list $items
     *
     * @return float
     *
     */
    public static function estimate_person_ability($personresponses, model_item_param_list $items): float {
        $allmodels = model_strategy::get_installed_models();

        $likelihood = fn($x) => 1;
        $loglikelihood = fn($x) => 0;
        $loglikelihood1stderivative = fn($x) => 0;
        $loglikelihood2ndderivative = fn($x) => 0;

        foreach ($personresponses as $qid => $qresponse) {
            $item = $items[$qid];
            // The item parameter for this response was filtered out.
            if ($item === null) {
                continue;
            }
            $itemparams = $item->get_params_array();

            /**
             * @var catcalc_ability_estimator
             */
            $model = $allmodels[$item->get_model_name()];
            if (!in_array(catcalc_ability_estimator::class, class_implements($model))) {
                throw new \Exception(sprintf("The given model %s can not be used with the catcalc class", $item->get_model_name()));
            }

            $likelihoodpart = fn ($x) => $model::likelihood($x, $itemparams, $qresponse['fraction']);
            $loglikelihoodpart = fn ($x) => $model::log_likelihood($x, $itemparams, $qresponse['fraction']);
            $loglikelihood1stderivativepart = fn ($x) => $model::log_likelihood_p($x, $itemparams, $qresponse['fraction']);
            $loglikelihood2ndderivativepart = fn ($x) => $model::log_likelihood_p_p($x, $itemparams, $qresponse['fraction']);

            $likelihood = fn ($x) => $likelihood($x) * $likelihoodpart($x);
            $loglikelihood = fn ($x) => $loglikelihood($x) + $loglikelihoodpart($x);
            $loglikelihood1stderivative = fn ($x) => $loglikelihood1stderivative($x) + $loglikelihood1stderivativepart($x);
            $loglikelihood2ndderivative = fn ($x) => $loglikelihood2ndderivative($x) + $loglikelihood2ndderivativepart($x);
        }

        $result = mathcat::newton_raphson_multi_stable(
            $loglikelihood1stderivative,
            $loglikelihood2ndderivative,
            [0.1],
            6,
            50
        );

        return reset($result);
    }

    /**
     * Estimate item params.
     *
     * @param array $itemresponse
     * @param model_model $model
     *
     * @return mixed
     *
     */
    public static function estimate_item_params(array $itemresponse, model_model $model) {
        if (! $model instanceof catcalc_item_estimator) {
            throw new \InvalidArgumentException("Model does not implement the catcalc_item_estimator interface");
        }

        $modeldim = $model::get_model_dim();

        // Defines the starting point.
        $startarr = ['difficulty' => 0.50, 'discrimination' => 1.0, 'guessing' => 0.25];
        $z_0 = array_slice($startarr, 0, $modeldim - 1);
    
        $jacobian = self::build_itemparam_jacobian($itemresponse, $model);
        $hessian = self::build_itemparam_hessian($itemresponse, $model);

        // Estimate item parameters via Newton-Raphson algorithm.
        return mathcat::newton_raphson_multi_stable(
            $jacobian,
            $hessian,
            $z_0,
            6,
            50,
            fn ($ip) => $model::restrict_to_trusted_region($ip)
        );
    }

    /**
     * Builds the jacobian function for item params and the given model. 
     * 
     * @param array $itemresponse 
     * @param mixed $model 
     * @return Closure(mixed $ip): mixed 
     */
    public static function build_itemparam_jacobian(array $itemresponse, catcalc_item_estimator $model) {
        // Define Jacobi vector (1st derivative) of the Log Likelihood.
        $funs = [];
        
        foreach ($itemresponse as $r) {
            $funs[] = fn($ip) => $model::get_log_jacobian($r->get_ability(), $ip, $r->get_response());
        }
    
        $jacobian = self::build_callable_array($funs); // from [fn($x), fn($x),...] to fn($x): [...]
        $jacobian = fn ($ip) => matrixcat::multi_sum($jacobian($ip));

        return $jacobian;
    }

    /**
     * Builds the hessian function for item params and the given model.
     * 
     * @param array $itemresponse 
     * @param mixed $model 
     * @return Closure(mixed $ip): mixed 
     */
    public static function build_itemparam_hessian(array $itemresponse, $model) {
        // Define Hesse matrix (2nd derivative) of the Log Likelihood.
        $hessian = [];
        
        foreach ($itemresponse as $r) {
            $hessian[] = fn($ip) => $model::get_log_hessian($r->get_ability(), $ip, $r->get_response());
        }
            
        $hessian = self::build_callable_array($hessian);
        $hessian = fn ($ip) => matrixcat::multi_sum($hessian($ip));

        return $hessian;
    }

    /**
     * Re-Builds an Array of Callables into a Callable that delivers an Array
     *
     * @param array<callable> $functions
     * @return Closure(mixed $x): array
     */
    public static function build_callable_array ($functions) {
        return function($x) use ($functions) {
            $new = [];
            foreach ($functions as $key => $f) {
            	$new[$key] = $f($x);
            }
        	return $new;
        };
    }
}
