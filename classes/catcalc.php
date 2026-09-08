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
use local_catquiz\local\model\model_item_param;
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
     * Residual gradient (max abs) above which a Newton item-parameter result is
     * treated as not converged and a BFGS rescue is tried (issue: experiment K1).
     * Converged Newton runs leave a residual far below this; only ill-conditioned
     * geometries exceed it.
     *
     * @var float
     */
    private const NEWTON_RESCUE_GRADIENT = 1.0e-2;

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

            $n = $numpassed + $numfailed;
            // Empirical logit keeps the estimate finite in every boundary case
            // (no responses, all correct, all incorrect) and is symmetric:
            // p = (r + 0.5) / (n + 1),   difficulty = -log(p / (1 - p)).
            // With n = 0 this yields p = 0.5 and a neutral difficulty of 0 rather
            // than a division by zero; r = 0 and r = n no longer hit log(0).
            $p = ($numpassed + 0.5) / ($n + 1);
            $itemdifficulty = -log($p / (1 - $p));
            $itemdifficulties[$id] = $itemdifficulty;
        }
        return $itemdifficulties;
    }

    /**
     * Builds a per-response callable returning the combined ability derivatives.
     *
     * The returned closure computes {@see model_raschmodel::get_ability_derivatives()}
     * once per distinct ability value and caches it, so the jacobian and the hessian
     * callables share a single probability/moment computation. The cache lives in this
     * method's own scope, giving every response an independent cache (a shared cache
     * across responses would return one response's derivatives for another).
     *
     * @param string $model model class name
     * @param array $itemparams item parameters for this response
     * @param float $response response fraction
     * @return callable fn(array $pp): array{jacobian: float, hessian: float}
     */
    private static function make_ability_derivative_callable($model, array $itemparams, float $response): callable {
        $memo = [];
        return function ($pp) use ($model, $itemparams, $response, &$memo) {
            // Format %.17g round-trips a double exactly, so distinct Newton iterates never collide.
            $key = sprintf('%.17g', $pp['ability']);
            if (!array_key_exists($key, $memo)) {
                $memo[$key] = $model::get_ability_derivatives($pp, $itemparams, $response);
            }
            return $memo[$key];
        };
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

            // Combined score/hessian: the person-ability derivatives share an
            // (often expensive) probability/moment computation. Build a per-response
            // callable that computes both once per ability value and caches the result;
            // the helper gives each response its own cache scope.
            $combined = self::make_ability_derivative_callable(
                $model,
                $itemparams,
                $qresponse->get_response()
            );
            $jfuns[] = fn ($pp) => $combined($pp)['jacobian'];
            $hfuns[] = fn ($pp) => $combined($pp)['hessian'];
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

        $result = mathcat::newton_raphson(
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
     * @param ?model_item_param $startvalue
     *
     * @return mixed
     *
     */
    public static function estimate_item_params(array $itemresponse, model_model $model, ?model_item_param $startvalue = null) {
        if (! $model instanceof catcalc_item_estimator) {
            throw new \InvalidArgumentException("Model does not implement the catcalc_item_estimator interface");
        }

        // Build the starting item parameters. Polytomous models have a
        // data-dependent number of thresholds, so their start values are derived
        // from the observed response categories (get_start_ip); dichotomous models
        // use the fixed default start sliced to their dimension.
        if ($model::is_polytomous()) {
            $startip = $startvalue ? $startvalue->get_params_array() : $model::get_start_ip($itemresponse);
            $thresholdkey = isset($startip['difficulties']) ? 'difficulties' : 'intercepts';
            $fractions = array_keys($startip[$thresholdkey]);
        } else {
            $modeldim = $model::get_model_dim();
            $defaultstart = ['difficulty' => 0.50, 'discrimination' => 1.0, 'guessing' => 0.25];
            $startvalue = $startvalue ? $startvalue->get_params_array() : [];
            $startip = array_slice(array_merge($defaultstart, $startvalue), 0, $modeldim - 1);
            $fractions = array_keys($startip);
        }

        // Parameter codec: Newton-Raphson operates on a flat numeric vector, so the
        // (possibly nested) item parameters are serialised via convert_ip_to_vector
        // and reconstructed via convert_vector_to_ip. The fractions carry the
        // category keys used for the reconstruction; the dimensionality is therefore
        // driven by the data rather than a fixed constant.
        $z0 = $model::convert_ip_to_vector($startip);

        $jacobian = self::build_itemparam_jacobian($itemresponse, $model);
        $hessian = self::build_itemparam_hessian($itemresponse, $model);

        // Adapt the closures to accept the flat parameter vector.
        $jacobianvec = fn ($vector) => $jacobian($model::convert_vector_to_ip($vector, $fractions));
        $hessianvec = fn ($vector) => $hessian($model::convert_vector_to_ip($vector, $fractions));
        $trfilter = fn ($vector) => $model::convert_ip_to_vector(
            $model::restrict_to_trusted_region($model::convert_vector_to_ip($vector, $fractions))
        );

        // Estimate item parameters via Newton-Raphson algorithm.
        $resultvector = mathcat::newton_raphson(
            $jacobianvec,
            $hessianvec,
            $z0,
            6,
            50,
            $trfilter
        );

        // K1/K4 (experiment consequences): Newton quality gate with a BFGS rescue
        // and a keep-best policy. On flat or ill-conditioned geometries (near-zero
        // discrimination, missing categories, bimodal ability) the Hessian is nearly
        // singular and Newton can stop at a point with a large residual gradient --
        // a worse optimum than a first-order method reaches. When the residual is
        // large, run BFGS from the same start and keep whichever result has the
        // higher log-likelihood. Well-behaved items converge to a tiny residual, so
        // the gate does not fire and the result is unchanged; and keep-best
        // guarantees the outcome is never worse than Newton alone.
        // K4: use the PROJECTED gradient, not the raw one. At an active
        // trusted-region bound the raw gradient can be large while the point is a
        // legitimate (KKT) boundary optimum. The projected residual is the step the
        // box actually permits along the gradient -- projected = trust(x+eps*g) - x
        // -- so a bound that clamps an outward-pointing gradient contributes ~0 and
        // does not trigger a spurious rescue.
        $gradient = $jacobianvec($resultvector);
        $eps = 1.0e-6;
        $probe = [];
        foreach ($resultvector as $i => $value) {
            $probe[$i] = $value + $eps * (float) ($gradient[$i] ?? 0.0);
        }
        $projected = $trfilter($probe);
        $residual = 0.0;
        foreach ($resultvector as $i => $value) {
            $step = ((float) ($projected[$i] ?? $value) - (float) $value) / $eps;
            $residual = max($residual, abs($step));
        }
        if (is_finite($residual) && $residual > self::NEWTON_RESCUE_GRADIENT) {
            $objective = self::build_itemparam_objective($itemresponse, $model);
            $objectivevec = fn ($vector) => $objective($model::convert_vector_to_ip($vector, $fractions));
            try {
                $rescuevector = mathcat::bfgs($objectivevec, $jacobianvec, $z0, 6, 100, $trfilter);
                if ($objectivevec($rescuevector) > $objectivevec($resultvector)) {
                    $resultvector = $rescuevector;
                }
            } catch (\Throwable $e) {
                // Keep the Newton result if the rescue cannot run (e.g. a reduced
                // category structure whose free dimension is inconsistent).
                unset($e);
            }
        }

        return $model::convert_vector_to_ip($resultvector, $fractions);
    }

    /**
     * Identifiability report for an estimated item (experiment consequence K5).
     *
     * Returns per-item diagnostics that a calibration workflow (issue #43) can
     * surface or persist: the number of observed response categories, the
     * projected gradient residual at the estimate (K4), whether any parameter sits
     * at a trusted-region bound, a well-identified flag and human-readable
     * warnings. It never mutates state and can be called after estimate_item_params.
     *
     * @param array $itemresponse array of model_item_response
     * @param model_model $model
     * @param array $ip estimated item parameters
     * @return array {observedcategories:int, gradientresidual:float, atbound:bool,
     *               wellidentified:bool, warnings:string[]}
     */
    public static function item_identifiability_report(array $itemresponse, model_model $model, array $ip): array {
        $observed = [];
        foreach ($itemresponse as $r) {
            $observed[(string) $r->get_response()] = true;
        }

        // Reconstruct the codec key layout used for this model/estimate.
        if ($model::is_polytomous()) {
            $thresholdkey = isset($ip['difficulties']) ? 'difficulties' : 'intercepts';
            $fractions = array_keys($ip[$thresholdkey]);
        } else {
            $fractions = array_keys($ip);
        }
        $vector = $model::convert_ip_to_vector($ip);
        $jacobian = self::build_itemparam_jacobian($itemresponse, $model);
        $tofrac = fn ($v) => $model::convert_vector_to_ip($v, $fractions);
        $trfilter = fn ($v) => $model::convert_ip_to_vector($model::restrict_to_trusted_region($tofrac($v)));

        $gradient = $jacobian($tofrac($vector));

        // Projected gradient residual (K4): the step the trusted region permits
        // along the gradient. A bound clamping an outward-pointing gradient
        // contributes ~0, so a legitimate boundary optimum is not flagged as
        // non-converged here.
        $eps = 1.0e-6;
        $probe = [];
        foreach ($vector as $i => $value) {
            $probe[$i] = $value + $eps * (float) ($gradient[$i] ?? 0.0);
        }
        $projected = $trfilter($probe);
        $residual = 0.0;
        $atbound = false;
        foreach ($vector as $i => $value) {
            $rawstep = (float) ($gradient[$i] ?? 0.0);
            $projstep = ((float) ($projected[$i] ?? $value) - (float) $value) / $eps;
            $residual = max($residual, abs($projstep));
            // Active bound: the gradient wants to move the parameter but the
            // trusted-region projection blocks (most of) that move.
            if (abs($rawstep) > 1.0e-6 && abs($projstep) < 0.5 * abs($rawstep)) {
                $atbound = true;
            }
        }

        $warnings = [];
        if ($atbound) {
            $warnings[] = 'parameter at trusted-region boundary';
        }
        if (!is_finite($residual) || $residual > self::NEWTON_RESCUE_GRADIENT) {
            $warnings[] = 'large residual gradient (possibly weakly identified or flat)';
        }

        return [
            'observedcategories' => count($observed),
            'gradientresidual' => $residual,
            'atbound' => $atbound,
            'wellidentified' => is_finite($residual) && $residual <= self::NEWTON_RESCUE_GRADIENT && !$atbound,
            'warnings' => $warnings,
        ];
    }

    /**
     * Builds the scalar Log Likelihood objective for item params and the given model.
     *
     * Companion to {@see self::build_itemparam_jacobian()} for optimisers that
     * require the objective value (e.g. BFGS, gradient ascent) rather than only
     * its gradient. The model must also implement catcalc_ability_estimator so
     * that log_likelihood() is available.
     *
     * @param array $itemresponse
     * @param catcalc_item_estimator $model
     *
     * @return Closure
     */
    public static function build_itemparam_objective(array $itemresponse, catcalc_item_estimator $model): Closure {
        // Define the scalar Log Likelihood as a function of the item params.
        $funs = [];

        foreach ($itemresponse as $r) {
            $funs[] = fn($ip) => $model::log_likelihood($r->get_personparams()->to_array(), $ip, $r->get_response());
        }

        return function ($ip) use ($funs) {
            $sum = 0.0;
            foreach ($funs as $fun) {
                $sum += $fun($ip);
            }
            return $sum;
        };
    }

    /**
     * Builds the jacobian function for item params and the given model.
     *
     * @param array $itemresponse
     * @param catcalc_item_estimator $model
     *
     * @return Closure
     *
     */
    public static function build_itemparam_jacobian(array $itemresponse, catcalc_item_estimator $model): Closure {
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
        return function ($x) use ($functions) {
            $new = [];
            foreach ($functions as $key => $f) {
                $new[$key] = $f($x);
            }
            return $new;
        };
    }
}
