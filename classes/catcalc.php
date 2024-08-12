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
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use Closure;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_item_response;
use local_catquiz\local\model\model_model;
use local_catquiz\local\model\model_raschmodel;
use local_catquiz\local\model\model_strategy;
use local_catquiz\mathcat;
use moodle_exception;

/**
 * Class for catcalc functions.
 *
 * @package local_catquiz
 * @author Daniel Pasterk
 * @copyright 2024 Wunderbyte GmbH
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

        $itemdifficulties = [];
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
     * @param float $startvalue
     * @param float $mean The mean value of current abilities
     * @param float $sd The standard deviation of current abilities
     * @param float $trlowerlimit The lower limit of the trusted region
     * @param float $trupperlimit The upper limit of the trusted region
     * @param bool  $usetrfactor Specifies if the trusted region takes the $tr factor into account
     *
     * @return float
     *
     */
    public static function estimate_person_ability(
        $personresponses,
        model_item_param_list $items,
        float $startvalue = 0.0,
        float $mean = 0,
        float $sd = 1,
        float $trlowerlimit = -10.0,
        float $trupperlimit = 10.0,
        bool $usetrfactor = false
    ): float {
        $tr = get_config('local_catquiz', 'tr_sd_ratio');
        $allmodels = model_strategy::get_installed_models();

        $jfuns = [];
        $hfuns = [];
        foreach ($personresponses as $qid => $qresponse) {
            $item = $items[$qid];
            // The item parameter for this response was filtered out.
            if ($item === null) {
                continue;
            }
            $itemparams = $item->get_params_array();

            /** @var catcalc_ability_estimator $model */
            $model = $allmodels[$item->get_model_name()];
            if (!in_array(catcalc_ability_estimator::class, class_implements($model))) {
                throw new \Exception(sprintf("The given model %s can not be used with the catcalc class", $item->get_model_name()));
            }

            $jfuns[] = fn ($pp) => $model::log_likelihood_p($pp, $itemparams, $qresponse['fraction']);
            $hfuns[] = fn($pp) => $model::log_likelihood_p_p($pp, $itemparams, $qresponse['fraction']);
        }

        if ($jfuns === [] || $hfuns === []) {
            throw new moodle_exception('abilitycannotbecalculated', 'local_catquiz');
        }

        $jacobian = self::build_callable_array($jfuns);
        $jacobian = fn ($pp) => matrixcat::multi_sum($jacobian($pp));
        $hessian = self::build_callable_array($hfuns);
        $hessian = fn ($pp) => matrixcat::multi_sum($hessian($pp));

        $trfunction = fn($ability) => model_raschmodel::get_ability_tr_jacobian($ability, $mean, $sd);
        $trderivate = fn($ability) => model_raschmodel::get_ability_tr_hessian($ability, $mean, $sd);
        $trustedregionfilter = fn($ability) => model_raschmodel::restrict_to_trusted_region_pp(
            $ability,
            $trlowerlimit,
            $trupperlimit,
            $tr,
            $mean,
            $sd,
            $usetrfactor
        );

        $result = mathcat::newton_raphson_multi_stable(
            $jacobian,
            $hessian,
            ['ability' => $startvalue],
            6,
            500,
            $trustedregionfilter,
            $trfunction,
            $trderivate
        );

        // The ability is wrapped inside an array.
        $ability = $result['ability'];
        return $ability;
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
        $z0 = array_slice($startarr, 0, $modeldim - 1);

        $jacobian = self::build_itemparam_jacobian($itemresponse, $model);
        $hessian = self::build_itemparam_hessian($itemresponse, $model);

        // Estimate item parameters via Newton-Raphson algorithm.
        return mathcat::newton_raphson_multi_stable(
            $jacobian,
            $hessian,
            $z0,
            6,
            50,
            fn ($ip) => $model::restrict_to_trusted_region($ip)
        );
    }

    /**
     * Builds the jacobian function for item params and the given model.
     *
     * @param array $itemresponse
     * @param catcalc_item_estimator $model
     *
     * @return mixed
     *
     */
    public static function build_itemparam_jacobian(array $itemresponse, catcalc_item_estimator $model) {
        // Define Jacobi vector (1st derivative) of the Log Likelihood.
        $funs = [];

        foreach ($itemresponse as $r) {
            $funs[] = fn($ip) => $model::get_log_jacobian($r->get_personparams()->to_array(), $ip, $r->get_response());
        }

        // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
        // From [fn($x), fn($x),...] to fn($x): [...].
        $jacobian = self::build_callable_array($funs);
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
            $hessian[] = fn($ip) => $model::get_log_hessian($r->get_personparams()->to_array(), $ip, $r->get_response());
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
    public static function build_callable_array($functions) {
        return function($x) use ($functions) {
            $new = [];
            foreach ($functions as $key => $f) {
                $new[$key] = $f($x);
            }
            return $new;
        };
    }
}
