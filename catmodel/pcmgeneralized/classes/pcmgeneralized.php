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
 * Class pcmgeneralized.
 *
 * @package    catmodel_pcmgeneralized
 * @copyright  2024 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace catmodel_pcmgeneralized;

use local_catquiz\catcalc;
use local_catquiz\local\model\model_item_param;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_multiparam;
use local_catquiz\local\model\model_person_param_list;
use local_catquiz\local\model\model_raschmodel;
use stdClass;

/**
 * Class pcm of catmodels.
 *
 * Example data:
 *
 *  'difficulty' => will be calculated from the intercept values
 *  'discrimination' => 2.1,
 * 'json' => {
 *  intercept: [
 *      '0.000' => 0.0,
 *      '0.333' => 0.4,
 * ]
 * }
 *
 * @package    catmodel_grmgeneralized
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pcmgeneralized extends model_multiparam {
    /**
     * {@inheritDoc}
     *
     * @param array $parameters
     * @return float
     */
    public static function get_difficulty(array $parameters): float {
        return self::calculate_mean_difficulty($parameters);
    }

    /**
     * {@inheritDoc}
     *
     * @param stdClass $record
     * @return array
     */
    /**
     * Validates the item parameters for this generalized polytomous model.
     *
     * On top of the polytomous contract (a well formed json payload) this model
     * uses a discrimination, which must be strictly positive. With a slope of 0 the
     * category probabilities no longer depend on the ability at all: the item
     * carries no information and the ability estimate stops moving.
     *
     * @param stdClass $record The raw item parameter record.
     * @return string[] Reasons the parameters are invalid; empty array if valid.
     */
    public static function validate_parameters(stdClass $record): array {
        $reasons = parent::validate_parameters($record);

        if (!self::is_valid_positive_float($record->discrimination ?? null)) {
            $reasons[] = 'discrimination must be a number greater than 0';
        }

        return $reasons;
    }

    /**
     * Returns the parameters as associative array, where the key is the parameter name.
     *
     * @param stdClass $record
     * @return array
     */
    public static function get_parameters_from_record(stdClass $record): array {

        $intercepts = json_decode($record->json, true)['intercepts'];
        $discrimination = round($record->discrimination, self::PRECISION);

        return [
            'difficulty' => round(self::calculate_mean_difficulty(['intercepts' => $intercepts]), self::PRECISION),
            'discrimination' => $discrimination,
            'intercepts' => $intercepts,
        ];
    }

    /**
     * Serialise the polytomous parameters into the record so they survive persistence.
     *
     * The reverse of get_parameters_from_record(): the 'intercepts' map is stored in the
     * record JSON, and the scalar difficulty column receives the mean difficulty.
     *
     * @param stdClass $record the record to enrich
     * @param array $parameters the item parameters
     *
     * @return stdClass
     *
     */
    public static function add_parameters_to_record(stdClass $record, array $parameters): stdClass {
        $record->json = json_encode(['intercepts' => $parameters['intercepts']]);
        $record->difficulty = $record->difficulty ?? self::calculate_mean_difficulty($parameters);
        $record->discrimination = $parameters['discrimination'] ?? $record->discrimination;
        return $record;
    }

    /**
     * Returns the name of this model.
     *
     * @return string
     */
    public function get_model_name(): string {
        return 'pcmgeneralized';
    }

    // Definitions and Dimensions.

    /**
     * Goes modified to mathcat.php.
     *
     * @param array $ip
     *
     * @return array
     */
    public static function convert_ip_to_vector(array $ip): array {
        // Free intercepts (baseline excluded) followed by the discrimination.
        return array_merge(array_slice(array_values($ip['intercepts']), 1), [$ip['discrimination']]);
    }

    /**
     * Convert vector to item param
     *
     * @param array $vector
     * @param mixed $fractions
     *
     * @return array
     */
    public static function convert_vector_to_ip(array $vector, $fractions): array {
        // Last element is the discrimination; the remainder are the free intercepts.
        $discrimination = array_pop($vector);
        $intercepts = [(string) $fractions[0] => 0.0];
        foreach ($vector as $i => $value) {
            $intercepts[(string) $fractions[$i + 1]] = $value;
        }
        return ['intercepts' => $intercepts, 'discrimination' => $discrimination];
    }

    /**
     * Defines names if item parameter list
     *
     * The parameters have the following structure.
     * [
     *   'difficultiy': [fraction 1: 0, fraction 2: intercept 1, ..., fraction k+1: intercept k-1],
     *   'discrimination': discrimination
     * ]
     * @return array of string
     */

    /**
     * Get parameter names
     *
     * This will have the following structure.
     * [
     *   'difficultiy': [fraction1: 0, fraction2: intercept 1, ..., fraction k: difficulty k-1],
     *   'discrimination': discrimination
     * ]
     *
     * @return array
     */
    public static function get_parameter_names(): array {
        return ['intercepts', 'discrimination', 'difficulty'];
    }

    /**
     * Definition of the number of model parameters
     *
     * @return int
     */
    /**
     * This model has a data-dependent number of parameters.
     *
     * @return bool
     *
     */
    public static function is_polytomous(): bool {
        return true;
    }

    /**
     * Data-driven start item parameters (empirical thresholds + fallback).
     *
     * @param array $itemresponse array of model_item_response
     *
     * @return array
     *
     */
    public static function get_start_ip(array $itemresponse): array {
        return [
            'intercepts' => self::empirical_start_thresholds($itemresponse),
            'discrimination' => 1.0,
        ];
    }

    /**
     * LORS objective value: n * sum_k R_k^2 over the free boundaries.
     *
     * @param array $pp person ability parameter
     * @param array $ip item parameters
     * @param array $ors observed odds ratios keyed by the free fractions
     * @param float $n number of observations
     *
     * @return float
     *
     */
    public static function lors_residuals(array $pp, array $ip, array $ors, float $n = 1): float {
        return self::compute_lors($pp, $ip, $ors, $n, 'intercepts', true)['residuals'];
    }

    /**
     * First derivative of the LORS objective w.r.t. the item parameters.
     *
     * @param array $pp person ability parameter
     * @param array $ip item parameters
     * @param array $ors observed odds ratios keyed by the free fractions
     * @param float $n number of observations
     *
     * @return array
     *
     */
    public static function lors_1st_derivative_ip(array $pp, array $ip, array $ors, float $n = 1): array {
        return self::compute_lors($pp, $ip, $ors, $n, 'intercepts', true)['jacobian'];
    }

    /**
     * Second derivative of the LORS objective w.r.t. the item parameters.
     *
     * @param array $pp person ability parameter
     * @param array $ip item parameters
     * @param array $ors observed odds ratios keyed by the free fractions
     * @param float $n number of observations
     *
     * @return array
     *
     */
    public static function lors_2nd_derivative_ip(array $pp, array $ip, array $ors, float $n = 1): array {
        return self::compute_lors($pp, $ip, $ors, $n, 'intercepts', true)['hessian'];
    }

    /**
     * LMS objective: n (frac - mu)^2 with the expected score mu = sum_k frac_k P_k.
     *
     * @param array $pp person ability parameter
     * @param array $ip item parameters
     * @param float $frac observed response fraction
     * @param float $n number of observations
     *
     * @return float
     *
     */
    public static function least_mean_squares(array $pp, array $ip, float $frac, float $n): float {
        return self::gpcm_lms($pp, $ip, $frac, $n)['residuals'];
    }

    /**
     * First derivative of the LMS objective w.r.t. the item parameters.
     *
     * @param array $pp person ability parameter
     * @param array $ip item parameters
     * @param float $frac observed response fraction
     * @param float $n number of observations
     *
     * @return array
     *
     */
    public static function least_mean_squares_1st_derivative_ip(array $pp, array $ip, float $frac, float $n): array {
        return self::gpcm_lms($pp, $ip, $frac, $n)['jacobian'];
    }

    /**
     * Second derivative of the LMS objective w.r.t. the item parameters.
     *
     * @param array $pp person ability parameter
     * @param array $ip item parameters
     * @param float $frac observed response fraction
     * @param float $n number of observations
     *
     * @return array
     *
     */
    public static function least_mean_squares_2nd_derivative_ip(array $pp, array $ip, float $frac, float $n): array {
        return self::gpcm_lms($pp, $ip, $frac, $n)['hessian'];
    }

    /**
     * Expected-score moments and LMS assembly for GPCM (partial credit with discrimination).
     *
     * With P_k = softmax(b s_k)_k, s_k = k theta - D_k and mu = sum_k frac_k P_k, the
     * expected-score derivatives use the tail sums T_j, the frac-weighted tails FF_j and
     * FMS_j, and the (frac-weighted) score moments. Codec order: the free intercepts
     * delta_1..delta_M followed by the discrimination b.
     *
     * @param array $pp person ability parameter
     * @param array $ip item parameters
     * @param float $frac observed response fraction
     * @param float $n number of observations
     *
     * @return array
     *
     */
    private static function gpcm_lms(array $pp, array $ip, float $frac, float $n): array {
        $ability = $pp['ability'];
        $a = self::sanitize_fractions($ip['intercepts']);
        $b = $ip['discrimination'];
        $fractions = self::get_fractions($a);
        $kmax = count($fractions) - 1;
        $bidx = $kmax;

        $cumulative = 0.0;
        $sc = [];
        $fr = [];
        for ($k = 0; $k <= $kmax; $k++) {
            if ($k > 0) {
                $cumulative += $a[$fractions[$k]];
            }
            $sc[$k] = $k * $ability - $cumulative;
            $fr[$k] = (float) $fractions[$k];
        }
        $shift = max(array_map(fn($scv) => $b * $scv, $sc));
        $weights = [];
        for ($k = 0; $k <= $kmax; $k++) {
            $weights[$k] = exp($b * $sc[$k] - $shift);
        }
        $z = array_sum($weights);

        $p = [];
        $mu = 0.0;
        $es = 0.0;
        $es2 = 0.0;
        $fs = 0.0;
        $fss = 0.0;
        for ($k = 0; $k <= $kmax; $k++) {
            $p[$k] = $weights[$k] / $z;
            $mu += $fr[$k] * $p[$k];
            $es += $p[$k] * $sc[$k];
            $es2 += $p[$k] * $sc[$k] ** 2;
            $fs += $fr[$k] * $p[$k] * $sc[$k];
            $fss += $fr[$k] * $p[$k] * $sc[$k] ** 2;
        }
        $var = $es2 - $es ** 2;

        // Tail sums and frac-weighted tail sums.
        $t = [];
        $ff = [];
        $ms = [];
        $fms = [];
        for ($j = 0; $j <= $kmax; $j++) {
            $tp = 0.0;
            $ffj = 0.0;
            $msj = 0.0;
            $fmsj = 0.0;
            for ($k = $j; $k <= $kmax; $k++) {
                $tp += $p[$k];
                $ffj += $fr[$k] * $p[$k];
                $msj += $p[$k] * $sc[$k];
                $fmsj += $fr[$k] * $p[$k] * $sc[$k];
            }
            $t[$j] = $tp;
            $ff[$j] = $ffj;
            $ms[$j] = $msj;
            $fms[$j] = $fmsj;
        }

        $dim = $kmax + 1;
        $dmu = array_fill(0, $dim, 0.0);
        $ddmu = [];
        for ($i = 0; $i < $dim; $i++) {
            $ddmu[$i] = array_fill(0, $dim, 0.0);
        }

        for ($j = 1; $j <= $kmax; $j++) {
            $dmu[$j - 1] = $b * ($t[$j] * $mu - $ff[$j]);
        }
        $dmu[$bidx] = $fs - $es * $mu;

        for ($j = 1; $j <= $kmax; $j++) {
            for ($l = 1; $l <= $kmax; $l++) {
                $mx = max($j, $l);
                $ddmu[$j - 1][$l - 1] = $b ** 2 * (
                    2 * $t[$j] * $t[$l] * $mu - $t[$mx] * $mu
                    - $t[$j] * $ff[$l] - $t[$l] * $ff[$j] + $ff[$mx]
                );
            }
            $cross = ($t[$j] * $mu - $ff[$j])
                + $b * ($ms[$j] * $mu + $t[$j] * $fs - 2 * $t[$j] * $es * $mu - $fms[$j] + $es * $ff[$j]);
            $ddmu[$j - 1][$bidx] = $cross;
            $ddmu[$bidx][$j - 1] = $cross;
        }
        $ddmu[$bidx][$bidx] = $fss - 2 * $es * $fs - $var * $mu + $es ** 2 * $mu;

        return self::lms_assemble($frac, $n, $mu, $dmu, $ddmu);
    }

    /**
     * A fixed model dimension is undefined for polytomous models; use the
     * data-driven get_model_dim_from_ip($ip) instead.
     *
     * @return int
     *
     */
    public static function get_model_dim(): int {
        // The number of parameters of a polytomous model depends on the number of
        // response categories in the data, so a fixed dimensionality is undefined.
        // Callers must use the data-driven get_model_dim_from_ip($ip) instead.
        throw new \coding_exception(
            'get_model_dim() is data-driven for polytomous models; use get_model_dim_from_ip($ip).'
        );
    }

    /**
     * Get item parameters.
     *
     * @return model_item_param_list
     */
    public static function get_item_parameters(): model_item_param_list {
        // TODO implement.
        return new model_item_param_list();
    }

    /**
     * Get person abilities.
     *
     * @return model_person_param_list
     */
    public static function get_person_abilities(): model_person_param_list {
        // TODO implement.
        return new model_person_param_list();
    }

    /**
     * Estimate item parameters
     *
     * @param mixed $itemresponse
     * @param ?model_item_param $startvalue
     *
     * @return array
     */
    public function calculate_params($itemresponse, ?model_item_param $startvalue = null): array {
        return catcalc::estimate_item_params($itemresponse, $this, $startvalue);
    }

    /**
     * Calculate the mean difficulty
     *
     * @param array $ip
     *
     * @return float
     *
     */
    public static function calculate_mean_difficulty(array $ip): float {
        $ip['intercepts'] = self::sanitize_fractions($ip['intercepts']);
        $fractions = self::get_fractions($ip['intercepts']);
        $kmax = count($fractions) - 1;

        return ($ip['intercepts'][$fractions[1]] + $ip['intercepts'][$fractions[$kmax]]) / 2;
    }

    // Calculate the Likelihood.

    /**
     * Calculates the Likelihood for a given the person ability parameter
     *
     * @param array $pp - person ability parameter
     * @param array $ip - item parameters ('difficulty', 'discrimination')
     * @param float $frac - answer fraction (0 ... 1.0)
     * @return float
     */
    public static function likelihood(array $pp, array $ip, float $frac): float {
        $ability = $pp['ability'];

        $a = self::sanitize_fractions($ip['intercepts']);
        $b = $ip['discrimination'];

        $fractions = self::get_fractions($a);
        $kmax = count($fractions) - 1;

        // Category log-weights l_k = b*(k*theta - sum_{j<=k} intercept_j). A
        // max-shifted softmax keeps exp() finite at extreme abilities/discriminations
        // where the raw sum would overflow to INF and yield INF/INF = NaN.
        $logweights = [];
        $intercepts = 0.0;
        for ($k = 0; $k <= $kmax; $k++) {
            $intercepts += ($k == 0) ? 0.0 : $a[$fractions[$k]];
            $logweights[$k] = $b * ($k * $ability - $intercepts);
        }
        $max = max($logweights);
        $denominator = 0.0;
        foreach ($logweights as $logweight) {
            $denominator += exp($logweight - $max);
        }

        $kfrac = self::get_key_by_fractions($frac, $a);
        return exp($logweights[$kfrac] - $max) / $denominator;
    }

    // Calculate the LOG Likelihood and its derivatives.

    /**
     * Calculates the LOG Likelihood for a given the person ability parameter
     *
     * @param array $pp - person ability parameter
     * @param array $ip - item parameters ('difficulty', 'discrimination')
     * @param float $frac - answer fraction (0 ... 1.0)
     * @return float - log likelihood
     */
    public static function log_likelihood(array $pp, array $ip, float $frac): float {
        return log(self::likelihood($pp, $ip, $frac));
    }

    /**
     * Calculates the 1st derivative of the LOG Likelihood with respect to the item parameters
     *
     * @param array $pp - person ability parameter
     * @param array $ip - item parameters ('difficulty', 'discrimination')
     * @param float $frac - answer fraction (0 ... 1.0)
     * @return float - 1st derivative of log likelihood with respect to $pp
     */
    public static function log_likelihood_p(array $pp, array $ip, float $frac): float {
        $m = self::gpcm_ability_moments($pp, $ip);
        $r = self::get_key_by_fractions($frac, $m['a']);
        // Score d/dtheta log L = b (r - E[K]).
        return $m['b'] * ($r - $m['ek']);
    }

    /**
     * Calculates the 2nd derivative of the LOG Likelihood with respect to the person ability.
     *
     * @param array $pp person ability parameter
     * @param array $ip item parameters
     * @param float $frac response fraction
     * @return float
     */
    public static function log_likelihood_p_p(array $pp, array $ip, float $frac): float {
        $m = self::gpcm_ability_moments($pp, $ip);
        // Hessian d^2/dtheta^2 log L = -b^2 Var(K).
        return -($m['b'] ** 2) * ($m['ek2'] - $m['ek'] ** 2);
    }

    /**
     * Combined score and hessian sharing a single moment computation.
     *
     * @param array $pp person ability parameter
     * @param array $ip item parameters
     * @param float $frac response fraction
     * @return array ['jacobian' => b(r - E[K]), 'hessian' => -b^2 Var(K)]
     */
    public static function get_ability_derivatives(array $pp, array $ip, float $frac): array {
        $m = self::gpcm_ability_moments($pp, $ip);
        $r = self::get_key_by_fractions($frac, $m['a']);
        return [
            'jacobian' => $m['b'] * ($r - $m['ek']),
            'hessian' => -($m['b'] ** 2) * ($m['ek2'] - $m['ek'] ** 2),
        ];
    }

    /**
     * Stable category moments E[K] and E[K^2] of the GPCM response distribution.
     *
     * Uses a max-shifted softmax over b*(k*theta - D_k) to avoid overflow, replacing
     * the earlier raw exp() sums.
     *
     * @param array $pp person ability parameter
     * @param array $ip item parameters
     * @return array ['a' => sanitized intercepts, 'b' => discrimination, 'ek' => E[K], 'ek2' => E[K^2]]
     */
    private static function gpcm_ability_moments(array $pp, array $ip): array {
        $ability = $pp['ability'];
        $a = self::sanitize_fractions($ip['intercepts']);
        $b = $ip['discrimination'];
        $fractions = self::get_fractions($a);
        $kmax = count($fractions) - 1;

        $cumulative = 0.0;
        $logweights = [];
        for ($k = 0; $k <= $kmax; $k++) {
            if ($k > 0) {
                $cumulative += $a[$fractions[$k]];
            }
            $logweights[$k] = $b * ($k * $ability - $cumulative);
        }
        $shift = max($logweights);

        $z = 0.0;
        $weights = [];
        for ($k = 0; $k <= $kmax; $k++) {
            $weights[$k] = exp($logweights[$k] - $shift);
            $z += $weights[$k];
        }
        $ek = 0.0;
        $ek2 = 0.0;
        for ($k = 0; $k <= $kmax; $k++) {
            $pk = $weights[$k] / $z;
            $ek += $k * $pk;
            $ek2 += $k * $k * $pk;
        }
        return ['a' => $a, 'b' => $b, 'ek' => $ek, 'ek2' => $ek2];
    }

    /**
     * Calculates the 2nd derivative of the LOG Likelihood with respect to the person ability parameter
     *
     * @param array $pp - person ability parameter
     * @param array $ip - item parameters ('difficulty', 'discrimination')
     * @param float $frac - answer fraction (0 ... 1.0)
     * @return float - 2nd derivative of log likelihood with respect to $pp
     */

    /**
     * Calculates the 1st derivative of the LOG Likelihood with respect to the item parameters
     *
     * @param array $pp - person ability parameter ('ability')
     * @param array $ip - item parameters ('difficulty', 'discrimination')
     * @param float $k - answer category (0 or 1.0)
     * @return array - jacobian vector
     */
    public static function get_log_jacobian(array $pp, array $ip, float $k): array {
        // GPCM item-parameter score. With s_cat = cat*theta - D_cat, category
        // probabilities P_cat = exp(b s_cat)/Z, tail probabilities T_j and the
        // observed category r:  d/ddelta_j = b (T_j - [r>=j]) and d/db = s_r - E[s].
        $m = self::gpcm_moments($pp, $ip, $k);
        $b = $ip['discrimination'];
        $kmax = $m['kmax'];

        $result = [];
        for ($p = 0; $p < $kmax; $p++) {
            $j = $p + 1;
            $result[$p] = $b * ($m['t'][$j] - (($m['r'] >= $j) ? 1.0 : 0.0));
        }
        // Discrimination derivative is the last codec entry (after M free intercepts).
        $result[$kmax] = $m['s'][$m['r']] - $m['es'];
        return $result;
    }

    /**
     * Category probabilities and moments used by the GPCM derivatives.
     *
     * @param array $pp person ability parameter
     * @param array $ip item parameters
     * @param float $frac observed response fraction
     *
     * @return array
     *
     */
    private static function gpcm_moments(array $pp, array $ip, float $frac): array {
        $ability = $pp['ability'];
        $a = self::sanitize_fractions($ip['intercepts']);
        $b = $ip['discrimination'];
        $fractions = self::get_fractions($a);
        $kmax = count($fractions) - 1;

        // Score term s_cat = cat*theta - D_cat with cumulative intercepts D_cat.
        // A max-shift on b*s_cat keeps the exponentials finite at extreme abilities.
        $cumulative = 0.0;
        $s = [];
        for ($cat = 0; $cat <= $kmax; $cat++) {
            if ($cat > 0) {
                $cumulative += $a[$fractions[$cat]];
            }
            $s[$cat] = $cat * $ability - $cumulative;
        }
        $shift = max(array_map(fn($sc) => $b * $sc, $s));
        $weights = [];
        for ($cat = 0; $cat <= $kmax; $cat++) {
            $weights[$cat] = exp($b * $s[$cat] - $shift);
        }
        $z = array_sum($weights);

        $p = [];
        $es = 0.0;
        $es2 = 0.0;
        for ($cat = 0; $cat <= $kmax; $cat++) {
            $p[$cat] = $weights[$cat] / $z;
            $es += $p[$cat] * $s[$cat];
            $es2 += $p[$cat] * $s[$cat] ** 2;
        }

        // Tail probabilities T_j and partial moments Msum_j = sum_{cat>=j} P_cat s_cat.
        $t = [];
        $msum = [];
        for ($j = 0; $j <= $kmax; $j++) {
            $tailp = 0.0;
            $tailm = 0.0;
            for ($cat = $j; $cat <= $kmax; $cat++) {
                $tailp += $p[$cat];
                $tailm += $p[$cat] * $s[$cat];
            }
            $t[$j] = $tailp;
            $msum[$j] = $tailm;
        }

        return [
            's' => $s,
            't' => $t,
            'msum' => $msum,
            'es' => $es,
            'es2' => $es2,
            'r' => self::get_key_by_fractions($frac, $a),
            'kmax' => $kmax,
        ];
    }

    /**
     * Calculates the 2nd derivative of the LOG Likelihood with respect to the item parameters
     *
     * @param array $pp - person ability parameter ('ability')
     * @param array $ip - item parameters ('difficulty', 'discrimination')
     * @param float $itemresponse - answer category (0 or 1.0)
     *
     * @return array - hessian matrx
     */
    public static function get_log_hessian(array $pp, array $ip, float $itemresponse): array {
        // GPCM item-parameter curvature. Intercept block scaled by b^2; the
        // discrimination row/column via partial moments; H_bb = -Var(s).
        $m = self::gpcm_moments($pp, $ip, $itemresponse);
        $b = $ip['discrimination'];
        $kmax = $m['kmax'];
        $t = $m['t'];
        $bidx = $kmax; // Discrimination index (after M free intercepts 0..kmax-1).

        $result = [];
        for ($i = 0; $i <= $bidx; $i++) {
            $result[$i] = [];
            for ($j = 0; $j <= $bidx; $j++) {
                $result[$i][$j] = 0.0;
            }
        }

        // Intercept-intercept block: b^2 (T_i T_j - T_max(i,j)), free params 0-based.
        for ($pi = 0; $pi < $kmax; $pi++) {
            for ($pj = 0; $pj < $kmax; $pj++) {
                $i = $pi + 1;
                $j = $pj + 1;
                $result[$pi][$pj] = $b ** 2 * ($t[$i] * $t[$j] - $t[max($i, $j)]);
            }
        }

        // Intercept-discrimination cross terms: (T_j - [r>=j]) + b (Msum_j - T_j E[s]).
        for ($pj = 0; $pj < $kmax; $pj++) {
            $j = $pj + 1;
            $indicator = ($m['r'] >= $j) ? 1.0 : 0.0;
            $cross = ($t[$j] - $indicator) + $b * ($m['msum'][$j] - $t[$j] * $m['es']);
            $result[$pj][$bidx] = $cross;
            $result[$bidx][$pj] = $cross;
        }

        // Discrimination-discrimination: -Var(s).
        $result[$bidx][$bidx] = -($m['es2'] - $m['es'] ** 2);
        return $result;
    }


    /**
     * Calculate Item and Category-Information.
     *
     * @param array $pp
     * @param array $ip
     *
     * @return float
     *
     */


    /**
     * Return the fisher information
     *
     * @param array $pp
     * @param array $ip
     *
     * @return float
     * TOOO: renam fisher_info into item_information, until than this acts as an alias.
     */
    public function fisher_info(array $pp, array $ip): float {
        return self::item_information($pp, $ip);
    }

    /**
     * Return category information
     *
     * @param array $pp
     * @param array $ip
     * @param float $frac
     *
     * @return float
     */
    public static function category_information(array $pp, array $ip, float $frac): float {
        return -(self::log_likelihood_p_p($pp, $ip, $frac));
    }

    /**
     * Return item information
     *
     * @param array $pp
     * @param array $ip
     *
     * @return float
     */
    public static function item_information(array $pp, array $ip): float {
        // Fisher information I(theta) = sum_k P_k * (-d^2/dtheta^2 log P_k).
        // The category array already contains the baseline category, so it must be
        // summed exactly once. (The earlier code added the baseline term separately
        // and then again inside the loop, inflating the information by a factor
        // (1 + P_baseline).)
        $iif = 0.0;
        foreach ($ip['intercepts'] as $f => $val) {
            $iif += self::category_information($pp, $ip, (float) $f) * self::likelihood($pp, $ip, (float) $f);
        }
        return $iif;
    }

    // Implements handling of the Trusted Regions (TR) approach.

    /**
     * Implements a Filter Function for trusted regions in the item parameter estimation
     *
     * @param array $ip - item parameters ('difficulty', 'discrimination')
     * @return array - chunked item parameter
     */
    public static function restrict_to_trusted_region(array $ip): array {
        // Clamp each free threshold to a sensible range; keep discrimination
        // positive. The baseline entry stays 0 (re-inserted by the codec).
        // Trusted-region bounds from the model's admin settings (fallback to +/-5).
        // Only an unset (false) or empty config falls back; a configured 0 is honoured.
        $minconfig = get_config('catmodel_pcmgeneralized', 'trusted_region_min_a');
        $min = ($minconfig === false || $minconfig === '') ? -5.0 : (float) $minconfig;
        $maxconfig = get_config('catmodel_pcmgeneralized', 'trusted_region_max_a');
        $max = ($maxconfig === false || $maxconfig === '') ? 5.0 : (float) $maxconfig;
        foreach ($ip['intercepts'] as $fraction => $value) {
            $ip['intercepts'][$fraction] = max($min, min($max, $value));
        }
        if (isset($ip['discrimination'])) {
            $ip['discrimination'] = self::restrict_discrimination('catmodel_pcmgeneralized', $ip['discrimination']);
        }
        return $ip;
    }

    /**
     * Get default params
     *
     * @return array
     */
    public function get_default_params(): array {
        return [
            'discrimination' => 1.0,
            'intercepts' => [
                '0.00' => 0.00,
                '0.50' => 0.50,
                '1.00' => 1.00,
            ],
        ];
    }

    /**
     * Get multi param name
     *
     * @return string
     */
    protected static function get_multi_param_name(): string {
        return 'intercepts';
    }

    /**
     * Adds a new combination of itemparams
     *
     * @param array $existingparams
     * @param \stdClass $new
     * @return array
     */
    public function add_new_param(array $existingparams, stdClass $new): array {
        $num = count($existingparams['intercepts']) + 1;
        $difficultyprop = sprintf('difficulty_%d', $num);
        $fractionprop = sprintf('fraction_%d', $num);
        $newintercepts = $existingparams['intercepts'] + [$new->$fractionprop => $new->$difficultyprop];
        $newparams['intercepts'] = $newintercepts;
        $newparams['difficulty'] = self::calculate_mean_difficulty($newparams);
        return $newparams;
    }

    /**
     * Drops the itemparams at the given index
     *
     * @param array $existingparams
     * @param int $index
     * @return array
     */
    public function drop_param_at(array $existingparams, int $index): array {
        $counter = 0;
        $newintercepts = array_filter(
            $existingparams['intercepts'],
            function ($v) use (&$counter, $index) {
                $match = $counter == $index;
                $counter++;
                return !$match;
            }
        );
        $newparams['intercepts'] = $newintercepts;
        $newparams['difficulty'] = self::calculate_mean_difficulty($newparams);
        return $newparams;
    }
}
