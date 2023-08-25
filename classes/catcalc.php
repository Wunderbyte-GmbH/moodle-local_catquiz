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

        $retval = mathcat::newtonraphson_stable(
            $loglikelihood1stderivative,
            $loglikelihood2ndderivative,
            0,
            0.001,
            1500,
            PERSONABILITY_MAX
        );

        return $retval;
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

        // Compose likelihood matrices based on actual result.

        $modeldim = $model::get_model_dim();

        // Vector that contains the first derivatives for each parameter as functions
        // [Df/Da, Df,/Db, Df,Dc].
        $jacobian = [];
        // Matrix that contains the second derivatives
        // [
        // [Df/Daa, Df/Dab, Df/Dac]
        // [Df/Dba, Df/Dbb, Df/Dbc]
        // [Df/Dca, Df/Dcb, Df/Dcc]
        // ].
        $hessian = [];
        for ($i = 0; $i <= $modeldim - 2; $i++) {
            $jacobian[$i] = fn($x) => 0;
            $hessian[$i] = [];
            for ($j = 0; $j <= $modeldim - 2; $j++) {
                $hessian[$i][$j] = fn($x) => 0;
            }
        }

        foreach ($itemresponse as $r) {
            $jacobianpart = $model::get_log_jacobian($r->get_ability(), $r->get_response());
            $hessianpart = $model::get_log_hessian($r->get_ability(), $r->get_response());

            for ($i = 0; $i <= $modeldim - 2; $i++) {
                $jacobian[$i] = fn($x) => $jacobian[$i]($x) + $jacobianpart[$i]($x);

                for ($j = 0; $j <= $modeldim - 2; $j++) {
                    $hessian[$i][$j] = fn($x) => $hessian[$i][$j]($x) + $hessianpart[$i][$j]($x);
                }
            }
        }

        // Defines the starting point.
        $startarr = ['difficulty' => 0.5, 'discrimination' => 0.5, 'guessing' => 0.5];
        $z0 = array_slice($startarr, 0, $modeldim - 1);

        return mathcat::newton_raphson_multi_stable(
            $jacobian,
            $hessian,
            $z0,
            0.001,
            50,
            $model
        );
    }
}
