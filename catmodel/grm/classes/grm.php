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
 * Class grm.
 *
 * @package    catmodel_grm
 * @copyright  2024 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace catmodel_grm;

use local_catquiz\catcalc;
use local_catquiz\local\model\model_item_param;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_multiparam;
use local_catquiz\local\model\model_person_param_list;
use local_catquiz\local\model\model_raschmodel;
use stdClass;

/**
 * Class grmgeneralized of catmodels.
 *
 * @package    catmodel_grmgeneralized
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grm extends model_multiparam {
    /**
     * {@inheritDoc}
     *
     * @param stdClass $record
     * @return array
     */
    public static function get_parameters_from_record(stdClass $record): array {

        $difficulties = json_decode($record->json, true)['difficulties'];

        return [
            'difficulties' => $difficulties,
            'difficulty' => self::calculate_mean_difficulty(['difficulties' => $difficulties]),
        ];
    }

    /**
     * Serialise the polytomous parameters into the record so they survive persistence.
     *
     * The reverse of get_parameters_from_record(): the 'difficulties' map is stored in the
     * record JSON, and the scalar difficulty column receives the mean difficulty.
     *
     * @param stdClass $record the record to enrich
     * @param array $parameters the item parameters
     *
     * @return stdClass
     *
     */
    public static function add_parameters_to_record(stdClass $record, array $parameters): stdClass {
        $record->json = json_encode(['difficulties' => $parameters['difficulties']]);
        $record->difficulty = $record->difficulty ?? self::calculate_mean_difficulty($parameters);
        return $record;
    }

    /**
     * Returns the name of this model.
     *
     * @return string
     */
    public function get_model_name(): string {
        return 'grm';
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
        // Free thresholds only: the baseline (first fraction) is not estimated.
        return array_slice(array_values($ip['difficulties']), 1);
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
        // Re-insert the fixed baseline (first fraction => 0) and key the free
        // thresholds by the remaining fractions.
        $difficulties = [(string) $fractions[0] => 0.0];
        foreach ($vector as $i => $value) {
            $difficulties[(string) $fractions[$i + 1]] = $value;
        }
        return ['difficulties' => $difficulties];
    }

    /**
     * Get parameter names
     *
     * This will have the following structure.
     * [
     *   'difficulty': 1.23,
     *   'difficulties': [fraction1: difficulty1, fraction2: difficulty2, ..., fractionk: difficultyk],
     * ]
     *
     * @return array
     */
    public static function get_parameter_names(): array {
        return ['difficulty', 'difficulties'];
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
            'difficulties' => self::empirical_start_thresholds($itemresponse),
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
        return self::compute_lors($pp, $ip, $ors, $n, 'difficulties', false)['residuals'];
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
        return self::compute_lors($pp, $ip, $ors, $n, 'difficulties', false)['jacobian'];
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
        return self::compute_lors($pp, $ip, $ors, $n, 'difficulties', false)['hessian'];
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
        return self::grm_lms($pp, $ip, $frac, $n)['residuals'];
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
        return self::grm_lms($pp, $ip, $frac, $n)['jacobian'];
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
        return self::grm_lms($pp, $ip, $frac, $n)['hessian'];
    }

    /**
     * Expected-score moments and LMS assembly for GRM (discrimination fixed at 1).
     *
     * mu = sum_k frac_k P_k with P_k = Q_k - Q_{k+1}, Q_m = sigma(theta - a_m).
     * Only the boundary a_j enters P_{j-1} and P_j, giving
     * dmu/da_j = W_j (frac_{j-1} - frac_j) and a diagonal d2mu/da_j^2 = -V_j (...).
     *
     * @param array $pp person ability parameter
     * @param array $ip item parameters
     * @param float $frac observed response fraction
     * @param float $n number of observations
     *
     * @return array
     *
     */
    private static function grm_lms(array $pp, array $ip, float $frac, float $n): array {
        $ability = $pp['ability'];
        $a = self::sort_fractions($ip['difficulties']);
        $fractions = self::get_fractions($a);
        $kmax = max(array_keys($fractions));

        // Fraction (score) values per category and cumulative/category probabilities.
        $fr = [];
        $q = [1.0];
        for ($m = 1; $m <= $kmax; $m++) {
            $q[$m] = self::logistic($ability - $a[$fractions[$m]]);
        }
        $q[$kmax + 1] = 0.0;
        for ($k = 0; $k <= $kmax; $k++) {
            $fr[$k] = (float) $fractions[$k];
        }
        $mu = 0.0;
        for ($k = 0; $k <= $kmax; $k++) {
            $mu += $fr[$k] * ($q[$k] - $q[$k + 1]);
        }

        // Gradient / diagonal Hessian of mu w.r.t. the free thresholds a_1..a_kmax.
        $dmu = [];
        $ddmu = [];
        for ($i = 0; $i < $kmax; $i++) {
            $ddmu[$i] = array_fill(0, $kmax, 0.0);
        }
        for ($j = 1; $j <= $kmax; $j++) {
            $w = self::logistic_w($q[$j]);
            $v = $w * (1.0 - 2.0 * $q[$j]);
            $weight = $fr[$j - 1] - $fr[$j];
            $dmu[$j - 1] = $w * $weight;
            $ddmu[$j - 1][$j - 1] = -$v * $weight;
        }

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
     *
     */
    public function calculate_params($itemresponse, ?model_item_param $startvalue = null): array {
        return catcalc::estimate_item_params($itemresponse, $this, $startvalue);
    }

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
     * Calculate the mean difficulty
     *
     * @param array $ip
     *
     * @return float
     *
     */
    public static function calculate_mean_difficulty(array $ip): float {
        $ip['difficulties'] = self::sanitize_fractions($ip['difficulties']);
        $fractions = self::get_fractions($ip['difficulties']);
        $kmax = max(array_keys($fractions));

        return ($ip['difficulties'][$fractions[1]] + $ip['difficulties'][$fractions[$kmax]]) / 2;
    }

    /**
     * Get all fractions out of parts of ip array
     *
     * @param array $array
     * @return array of fractions as strings
     */
    protected static function get_fractions(array $array): array {
        $frac = [];
        $frac[0] = 0;

        $a = self::sort_fractions($array);

        // Skip the lowest fraction: it is the baseline category, whatever its
        // value. The previous "> 0" test wrongly assumed the baseline is always
        // 0.0; for a reduced category structure (e.g. an unobserved bottom
        // category shifting the baseline to 0.25) it double-counted the baseline
        // and produced a jacobian one component too long (see experiments K3).
        $first = true;
        foreach ($a as $fraction => $val) {
            if ($first) {
                $first = false;
                continue;
            }
            if ((float) $fraction <= 1) {
                $frac[] = $fraction;
            }
        }
        return $frac;
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

        $a = self::sort_fractions($ip['difficulties']);

        // Make sure $frac is between 0.0 and 1.0.
        $frac = min(1.0, max(0.0, $frac));
        $fractions = self::get_fractions($a);
        $kmax = max(array_keys($fractions));

        $k = self::get_key_by_fractions($frac, $a);

        $result = ($k == 0) ? (1) : (1 / (1 + exp($a[$fractions[$k]] - $ability)));
        $result -= ($k == $kmax) ? (0) : (1 / (1 + exp($a[$fractions[$k + 1]] - $ability)));

        return $result;
    }

    /**
     * Calculates the 1st derivate of the Likelihood
     *
     * @param array $pp - person ability parameter
     * @param array $ip - item parameters ('difficulty', 'discrimination')
     * @param float $frac - answer fraction (0 ... 1.0)
     * @return float
     */
    protected static function likelihood_p(array $pp, array $ip, float $frac): float {
        $ability = $pp['ability'];

        $a = self::sort_fractions($ip['difficulties']);

        // Make sure $frac is between 0.0 and 1.0.
        $frac = min(1.0, max(0.0, $frac));
        $fractions = self::get_fractions($a);
        $kmax = max(array_keys($fractions));

        $k = self::get_key_by_fractions($frac, $a);

        $result = ($k == 0) ? (0) : (exp($a[$fractions[$k]] - $ability) /
            (1 + exp($a[$fractions[$k]] - $ability)) ** 2);
        $result -= ($k == $kmax) ? (0) : (exp($a[$fractions[$k + 1]] - $ability) /
            (1 + exp($a[$fractions[$k + 1]] - $ability)) ** 2);

        return $result;
    }

    /**
     * Calculates the 2nd derivate of the Likelihood
     *
     * @param array $pp - person ability parameter
     * @param array $ip - item parameters ('difficulty', 'discrimination')
     * @param float $frac - answer fraction (0 ... 1.0)
     * @return float
     */
    protected static function likelihood_p_p(array $pp, array $ip, float $frac): float {
        $ability = $pp['ability'];

        $a = self::sort_fractions($ip['difficulties']);

        // Make sure $frac is between 0.0 and 1.0.
        $frac = min(1.0, max(0.0, $frac));
        $fractions = self::get_fractions($a);
        $kmax = max(array_keys($fractions));

        $k = self::get_key_by_fractions($frac, $a);

        $result = ($k == 0) ? 0 : (exp($a[$fractions[$k]] - $ability) *
            (exp($a[$fractions[$k]] - $ability) - 1) /
            (1 + exp($a[$fractions[$k]] - $ability)) ** 3);
        $result -= ($k == $kmax) ? (0) : (exp($a[$fractions[$k + 1]] - $ability) *
            (exp($a[$fractions[$k + 1]] - $ability) - 1) /
            (1 + exp($a[$fractions[$k + 1]] - $ability)) ** 3);

        return $result;
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
        $t = self::grm_ability_terms($pp, $ip, $frac);
        // Score d/dtheta log L = (W_r - W_{r+1}) / P_r.
        return ($t['wr'] - $t['wr1']) / self::stabilize_denominator($t['pr']);
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
        $t = self::grm_ability_terms($pp, $ip, $frac);
        $pr = self::stabilize_denominator($t['pr']);
        $dp = $t['wr'] - $t['wr1'];
        $ddp = $t['vr'] - $t['vr1'];
        // Hessian d^2/dtheta^2 log L = (P_r P_r'' - (P_r')^2) / P_r^2, with
        // P_r'  = 1 * (W_r - W_{r+1}) and P_r'' = 1 * (V_r - V_{r+1}).
        return ($t['pr'] * $ddp - ($dp) ** 2) / ($pr ** 2);
    }

    /**
     * Combined score and hessian sharing a single terms computation.
     *
     * @param array $pp person ability parameter
     * @param array $ip item parameters
     * @param float $frac response fraction
     * @return array ['jacobian' => 1st derivative, 'hessian' => 2nd derivative]
     */
    public static function get_ability_derivatives(array $pp, array $ip, float $frac): array {
        $t = self::grm_ability_terms($pp, $ip, $frac);
        $pr = self::stabilize_denominator($t['pr']);
        $dp = $t['wr'] - $t['wr1'];
        $ddp = $t['vr'] - $t['vr1'];
        return [
            'jacobian' => $dp / $pr,
            'hessian' => ($t['pr'] * $ddp - $dp ** 2) / ($pr ** 2),
        ];
    }

    /**
     * Cumulative-logistic terms for the observed category, used by the ability derivatives.
     *
     * Q_j = sigma((theta - a_j)) with Q_0 = 1 and Q_{kmax+1} = 0; the observed
     * category r has P_r = Q_r - Q_{r+1}. Returns the boundary W = Q(1-Q) and
     * V = W(1-2Q) values (dsigma, d2sigma cores) at r and r+1.
     *
     * @param array $pp person ability parameter
     * @param array $ip item parameters
     * @param float $frac response fraction
     * @return array
     */
    private static function grm_ability_terms(array $pp, array $ip, float $frac): array {
        $ability = $pp['ability'];
        $a = self::sort_fractions($ip['difficulties']);
        $b = 1.0;
        $fractions = self::get_fractions($a);
        $kmax = max(array_keys($fractions));
        $r = self::get_key_by_fractions(min(1.0, max(0.0, $frac)), $a);

        $qr = ($r == 0) ? 1.0 : self::logistic($b * ($ability - $a[$fractions[$r]]));
        $qr1 = ($r == $kmax) ? 0.0 : self::logistic($b * ($ability - $a[$fractions[$r + 1]]));

        return [
            'pr' => $qr - $qr1,
            'wr' => self::logistic_w($qr),
            'wr1' => self::logistic_w($qr1),
            'vr' => self::logistic_w($qr) * (1.0 - 2.0 * $qr),
            'vr1' => self::logistic_w($qr1) * (1.0 - 2.0 * $qr1),
        ];
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
     * @param float $frac - answer category (0 .. 1.0)
     * @return array - jacobian vector
     */
    public static function get_log_jacobian(array $pp, array $ip, float $frac): array {
        // GRM item-parameter score. The category probability is a difference of
        // adjacent cumulative logistics P_r = Q_r - Q_{r+1}, Q_k = sigma(theta - a_k).
        // Only the two boundaries of the observed category r contribute:
        // d/da_r   log L = -W_r / P_r      (r > 0)
        // d/da_{r+1} log L =  W_{r+1} / P_r  (r < kmax).
        $ability = $pp['ability'];
        $a = self::sort_fractions($ip['difficulties']);
        $frac = min(1.0, max(0.0, $frac));
        $fractions = self::get_fractions($a);
        $kmax = max(array_keys($fractions));
        $r = self::get_key_by_fractions($frac, $a);

        $likelihood = self::stabilize_denominator(self::likelihood($pp, $ip, $frac));

        // Free thresholds a_1..a_M map to 0-based codec indices (a_j -> j-1).
        $result = [];
        for ($p = 0; $p < $kmax; $p++) {
            $result[$p] = 0.0;
        }

        if ($r > 0) {
            $qr = self::logistic($ability - $a[$fractions[$r]]);
            $result[$r - 1] = -self::logistic_w($qr) / $likelihood;
        }
        if ($r < $kmax) {
            $qr1 = self::logistic($ability - $a[$fractions[$r + 1]]);
            $result[$r] = self::logistic_w($qr1) / $likelihood;
        }
        return $result;
    }

    /**
     * Calculates the 2nd derivative of the LOG Likelihood with respect to the item parameters
     *
     * @param array $pp - person ability parameter ('ability')
     * @param array $ip - item parameters ('difficulty', 'discrimination')
     * @param float $frac - answer category (0 .. 1.0)
     *
     * @return array - hessian matrx
     */
    public static function get_log_hessian(array $pp, array $ip, float $frac): array {
        // GRM item-parameter curvature. With Q_k = sigma(theta - a_k),
        // W_k = Q_k(1-Q_k), V_k = W_k(1-2 Q_k) and P_r = Q_r - Q_{r+1}:
        // H_{r,r}     =  V_r / P_r     - (W_r / P_r)^2
        // H_{r+1,r+1} = -V_{r+1} / P_r - (W_{r+1} / P_r)^2
        // H_{r,r+1}   =  W_r W_{r+1} / P_r^2.
        $ability = $pp['ability'];
        $a = self::sort_fractions($ip['difficulties']);
        $frac = min(1.0, max(0.0, $frac));
        $fractions = self::get_fractions($a);
        $kmax = max(array_keys($fractions));
        $r = self::get_key_by_fractions($frac, $a);

        $likelihood = self::stabilize_denominator(self::likelihood($pp, $ip, $frac));

        // Free thresholds a_1..a_M map to 0-based codec indices (a_j -> j-1).
        $result = [];
        for ($pi = 0; $pi < $kmax; $pi++) {
            $result[$pi] = [];
            for ($pj = 0; $pj < $kmax; $pj++) {
                $result[$pi][$pj] = 0.0;
            }
        }

        $wr = null;
        $wr1 = null;
        if ($r > 0) {
            $qr = self::logistic($ability - $a[$fractions[$r]]);
            $wr = self::logistic_w($qr);
            $vr = $wr * (1.0 - 2.0 * $qr);
            $result[$r - 1][$r - 1] = $vr / $likelihood - ($wr / $likelihood) ** 2;
        }
        if ($r < $kmax) {
            $qr1 = self::logistic($ability - $a[$fractions[$r + 1]]);
            $wr1 = self::logistic_w($qr1);
            $vr1 = $wr1 * (1.0 - 2.0 * $qr1);
            $result[$r][$r] = -$vr1 / $likelihood - ($wr1 / $likelihood) ** 2;
        }
        if ($wr !== null && $wr1 !== null) {
            $cross = $wr * $wr1 / $likelihood ** 2;
            $result[$r - 1][$r] = $cross;
            $result[$r][$r - 1] = $cross;
        }
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
        foreach ($ip['difficulties'] as $f => $val) {
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
        // Clamp each free threshold to a sensible range and enforce the ascending
        // ordering a_1 <= a_2 <= ... <= a_M that the graded model requires: with
        // P_k = Q_k - Q_{k+1} and Q_m = sigma(b (theta - a_m)), an out-of-order
        // threshold would yield a negative category probability and hence NaN in the
        // likelihood. The baseline entry (lowest fraction) is a placeholder and stays 0.
        // Trusted-region bounds from the model's admin settings (fallback to +/-5).
        // Only an unset (false) or empty config falls back; a configured 0 is honoured.
        $minconfig = get_config('catmodel_grm', 'trusted_region_min_a');
        $min = ($minconfig === false || $minconfig === '') ? -5.0 : (float) $minconfig;
        $maxconfig = get_config('catmodel_grm', 'trusted_region_max_a');
        $max = ($maxconfig === false || $maxconfig === '') ? 5.0 : (float) $maxconfig;
        $gap = 1e-3;
        $sorted = self::sort_fractions($ip['difficulties']);
        $fractions = array_keys($sorted);

        // Collect the free thresholds (all but the baseline placeholder) and clamp
        // each into [min, max].
        $free = [];
        foreach ($fractions as $index => $fraction) {
            if ($index === 0) {
                // Baseline category placeholder: not a real threshold.
                continue;
            }
            $free[] = $fraction;
            $sorted[$fraction] = max($min, min($max, $sorted[$fraction]));
        }

        // Forward pass: enforce the ascending minimum gap a_i >= a_{i-1} + gap.
        $count = count($free);
        for ($i = 1; $i < $count; $i++) {
            $lower = $sorted[$free[$i - 1]] + $gap;
            if ($sorted[$free[$i]] < $lower) {
                $sorted[$free[$i]] = $lower;
            }
        }

        // The forward pass can push the top threshold past max. Project the whole
        // ascending chain back into [min, max] with a backward pass from max, so the
        // box constraint stays satisfied while the ordering/gap is preserved.
        if ($count > 0 && $sorted[$free[$count - 1]] > $max) {
            $sorted[$free[$count - 1]] = $max;
            for ($i = $count - 2; $i >= 0; $i--) {
                $upper = $sorted[$free[$i + 1]] - $gap;
                if ($sorted[$free[$i]] > $upper) {
                    $sorted[$free[$i]] = $upper;
                }
            }
        }
        $ip['difficulties'] = $sorted;
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
            'difficulties' => [
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
        return 'difficulties';
    }

    /**
     * Adds a new combination of itemparams
     *
     * @param array $existingparams
     * @param \stdClass $new
     * @return array
     */
    public function add_new_param(array $existingparams, stdClass $new): array {
        $num = count($existingparams['difficulties']) + 1;
        $difficultyprop = sprintf('difficulty_%d', $num);
        $fractionprop = sprintf('fraction_%d', $num);
        $newdifficulties = $existingparams['difficulties'] + [$new->$fractionprop => $new->$difficultyprop];
        $newparams['difficulties'] = $newdifficulties;
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
        $newdifficulties = array_filter(
            $existingparams['difficulties'],
            function ($v) use (&$counter, $index) {
                $match = $counter == $index;
                $counter++;
                return !$match;
            }
        );
        $newparams['difficulties'] = $newdifficulties;
        $newparams['difficulty'] = self::calculate_mean_difficulty($newparams);
        return $newparams;
    }
}
