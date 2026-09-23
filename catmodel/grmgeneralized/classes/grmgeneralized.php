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
 * Class grmgeneralized.
 *
 * @package    catmodel_grmgeneralized
 * @copyright  2024 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace catmodel_grmgeneralized;

use local_catquiz\catcalc;
use local_catquiz\local\model\model_item_param;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_multiparam;
use local_catquiz\local\model\model_person_param_list;
use local_catquiz\local\model\model_raschmodel;
use MoodleQuickForm;
use stdClass;

/**
 * Class grmgeneralized of catmodels.
 *
 * @package    catmodel_grmgeneralized
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grmgeneralized extends model_multiparam {
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

        $difficulties = json_decode($record->json, true)['difficulties'];
        $discrimination = round($record->discrimination, self::PRECISION);

        return [
            'difficulty' => self::calculate_mean_difficulty(['difficulties' => $difficulties]),
            'discrimination' => $discrimination,
            'difficulties' => $difficulties,
        ];
    }

    /**
     * Returns the name of this model.
     *
     * @return string
     */
    public function get_model_name(): string {
        return 'grmgeneralized';
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
        // Free thresholds (baseline excluded) followed by the discrimination.
        return array_merge(array_slice(array_values($ip['difficulties']), 1), [$ip['discrimination']]);
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
        // Last element is the discrimination; the remainder are the free thresholds.
        $discrimination = array_pop($vector);
        $difficulties = [(string) $fractions[0] => 0.0];
        foreach ($vector as $i => $value) {
            $difficulties[(string) $fractions[$i + 1]] = $value;
        }
        return ['difficulties' => $difficulties, 'discrimination' => $discrimination];
    }

    /**
     * Defines names if item parameter list
     *
     * The parameters have the following structure.
     * [
     *   'difficulties': [fraction1: difficulty1, fraction2: difficulty2, ..., fractionk: difficultyk],
     *   'discrimination': discrimination
     * ]
     * @return array of string
     */

    /**
     * Get parameter names
     *
     * This will have the following structure.
     * [
     *   'difficultiy': [fraction1: difficulty1, fraction2: difficulty2, ..., fractionk: difficultyk],
     *   'discrimination': discrimination
     * ]
     *
     * @return array
     */
    public static function get_parameter_names(): array {
        return ['discrimination', 'difficulties', 'difficulty'];
    }

    /**
     * {@inheritDoc}
     *
     * This sets the difficulty as an aggregate value
     *
     * @param stdClass $record
     * @param array $parameters
     * @return stdClass
     */
    public static function add_parameters_to_record(stdClass $record, array $parameters): stdClass {
        $record->json = json_encode(['difficulties' => $parameters['difficulties']]);
        $record->difficulty = $record->difficulty ?? self::calculate_mean_difficulty($parameters);
        return $record;
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
     * Checks if the paramters can be saved
     *
     * @param model_item_param $itemparam
     * @return bool
     */
    public static function is_valid(model_item_param $itemparam): bool {
        $params = $itemparam->get_params_array();
        if (isset($params['discrimination']) && is_nan((float) $params['discrimination'])) {
            return false;
        }
        foreach ($params['difficulties'] as $d) {
            if (is_nan((float) $d)) {
                return false;
            }
        }
        return true;
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
        return self::compute_lors($pp, $ip, $ors, $n, 'difficulties', true)['residuals'];
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
        return self::compute_lors($pp, $ip, $ors, $n, 'difficulties', true)['jacobian'];
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
        return self::compute_lors($pp, $ip, $ors, $n, 'difficulties', true)['hessian'];
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
        return self::ggrm_lms($pp, $ip, $frac, $n)['residuals'];
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
        return self::ggrm_lms($pp, $ip, $frac, $n)['jacobian'];
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
        return self::ggrm_lms($pp, $ip, $frac, $n)['hessian'];
    }

    /**
     * Expected-score moments and LMS assembly for GGRM (graded with discrimination).
     *
     * mu = sum_k frac_k (Q_k - Q_{k+1}), Q_m = sigma(b (theta - a_m)). The threshold
     * block of d2mu is diagonal; the discrimination couples across the thresholds.
     *
     * @param array $pp person ability parameter
     * @param array $ip item parameters
     * @param float $frac observed response fraction
     * @param float $n number of observations
     *
     * @return array
     *
     */
    private static function ggrm_lms(array $pp, array $ip, float $frac, float $n): array {
        $ability = $pp['ability'];
        $a = self::sort_fractions($ip['difficulties']);
        $b = $ip['discrimination'];
        $fractions = self::get_fractions($a);
        $kmax = max(array_keys($fractions));
        $bidx = $kmax;

        $fr = [];
        for ($k = 0; $k <= $kmax; $k++) {
            $fr[$k] = (float) $fractions[$k];
        }
        $x = [];
        $q = [1.0];
        for ($m = 1; $m <= $kmax; $m++) {
            $x[$m] = $ability - $a[$fractions[$m]];
            $q[$m] = self::logistic($b * $x[$m]);
        }
        $q[$kmax + 1] = 0.0;
        $mu = 0.0;
        for ($k = 0; $k <= $kmax; $k++) {
            $mu += $fr[$k] * ($q[$k] - $q[$k + 1]);
        }

        $dim = $kmax + 1;
        $dmu = array_fill(0, $dim, 0.0);
        $ddmu = [];
        for ($i = 0; $i < $dim; $i++) {
            $ddmu[$i] = array_fill(0, $dim, 0.0);
        }

        $db = 0.0;
        $dbb = 0.0;
        for ($j = 1; $j <= $kmax; $j++) {
            $w = self::logistic_w($q[$j]);
            $v = $w * (1.0 - 2.0 * $q[$j]);
            $delta = $fr[$j - 1] - $fr[$j];

            $dmu[$j - 1] = $b * $w * $delta;
            $ddmu[$j - 1][$j - 1] = -($b ** 2) * $v * $delta;

            // Threshold-discrimination cross term.
            $cross = $delta * ($w + $b * $x[$j] * $v);
            $ddmu[$j - 1][$bidx] = $cross;
            $ddmu[$bidx][$j - 1] = $cross;

            $db += $x[$j] * $w * $delta;
            $dbb += $x[$j] ** 2 * $v * $delta;
        }
        $dmu[$bidx] = -$db;
        $ddmu[$bidx][$bidx] = -$dbb;

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

        // Skip the lowest fraction (the baseline category, whatever its value).
        // The previous "> 0" test double-counted a non-zero baseline of a reduced
        // category structure and produced a jacobian one component too long (K3).
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
        $b = $ip['discrimination'];

        // Make sure $frac is between 0.0 and 1.0.
        $frac = min(1.0, max(0.0, $frac));
        $fractions = self::get_fractions($a);
        $kmax = max(array_keys($fractions));

        $k = self::get_key_by_fractions($frac, $a);

        $result = ($k == 0) ? (1) : (1 / (1 + exp($b * ($a[$fractions[$k]] - $ability))));
        $result -= ($k == $kmax) ? (0) : (1 / (1 + exp($b * ($a[$fractions[$k + 1]] - $ability))));

        return $result;
    }

    /**
     * Calculates the 1st derivative of the Likelihood
     *
     * @param array $pp - person ability parameter
     * @param array $ip - item parameters ('difficulty', 'discrimination')
     * @param float $frac - answer fraction (0 ... 1.0)
     * @return float
     */
    protected static function likelihood_p(array $pp, array $ip, float $frac): float {
        $ability = $pp['ability'];

        $a = self::sort_fractions($ip['difficulties']);
        $b = $ip['discrimination'];

        // Make sure $frac is between 0.0 and 1.0.
        $frac = min(1.0, max(0.0, $frac));
        $fractions = self::get_fractions($a);
        $kmax = max(array_keys($fractions));

        $k = self::get_key_by_fractions($frac, $a);

        $result = ($k == 0) ? (0) : ($b * exp($b * ($a[$fractions[$k]] - $ability)) /
            (1 + exp($b * ($a[$fractions[$k]] - $ability))) ** 2);
        $result -= ($k == $kmax) ? (0) : ($b * exp($b * ($a[$fractions[$k + 1]] - $ability)) /
            (1 + exp($b * ($a[$fractions[$k + 1]] - $ability))) ** 2);

        return $result;
    }

    /**
     * Calculates the 2nd derivative of the Likelihood
     *
     * @param array $pp - person ability parameter
     * @param array $ip - item parameters ('difficulty', 'discrimination')
     * @param float $frac - answer fraction (0 ... 1.0)
     * @return float
     */
    protected static function likelihood_p_p(array $pp, array $ip, float $frac): float {
        $ability = $pp['ability'];

        $a = self::sort_fractions($ip['difficulties']);
        $b = $ip['discrimination'];

        // Make sure $frac is between 0.0 and 1.0.
        $frac = min(1.0, max(0.0, $frac));
        $fractions = self::get_fractions($a);
        $kmax = max(array_keys($fractions));

        $k = self::get_key_by_fractions($frac, $a);

        $result = ($k == 0) ? 0 : ($b ** 2 * exp($b * ($a[$fractions[$k]] - $ability)) *
            (exp($b * ($a[$fractions[$k]] - $ability)) - 1) /
            (1 + exp($b * ($a[$fractions[$k]] - $ability))) ** 3);
        $result -= ($k == $kmax) ? (0) : ($b ** 2 * exp($b * ($a[$fractions[$k + 1]] - $ability)) *
            (exp($b * ($a[$fractions[$k + 1]] - $ability)) - 1) /
            (1 + exp($b * ($a[$fractions[$k + 1]] - $ability))) ** 3);

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
        // Score d/dtheta log L = b (W_r - W_{r+1}) / P_r.
        return $t['b'] * ($t['wr'] - $t['wr1']) / self::stabilize_denominator($t['pr']);
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
        $b = $t['b'];
        // Hessian d^2/dtheta^2 log L = (P_r P_r'' - (P_r')^2) / P_r^2, with
        // P_r'  = b (W_r - W_{r+1}) and P_r'' = b^2 (V_r - V_{r+1}).
        return ($b ** 2 * $t['pr'] * $ddp - ($b * $dp) ** 2) / ($pr ** 2);
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
        $b = $t['b'];
        $pr = self::stabilize_denominator($t['pr']);
        $dp = $t['wr'] - $t['wr1'];
        $ddp = $t['vr'] - $t['vr1'];
        return [
            'jacobian' => $b * $dp / $pr,
            'hessian' => ($b ** 2 * $t['pr'] * $ddp - ($b * $dp) ** 2) / ($pr ** 2),
        ];
    }

    /**
     * Cumulative-logistic terms for the observed category, used by the ability derivatives.
     *
     * Q_j = sigma($b *(theta - a_j)) with Q_0 = 1 and Q_{kmax+1} = 0; the observed
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
        $b = $ip['discrimination'];
        $fractions = self::get_fractions($a);
        $kmax = max(array_keys($fractions));
        $r = self::get_key_by_fractions(min(1.0, max(0.0, $frac)), $a);

        $qr = ($r == 0) ? 1.0 : self::logistic($b * ($ability - $a[$fractions[$r]]));
        $qr1 = ($r == $kmax) ? 0.0 : self::logistic($b * ($ability - $a[$fractions[$r + 1]]));

        return [
            'b' => $b,
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
        // GGRM item-parameter score. Q_k = sigma(b(theta - a_k)); the observed
        // category probability P_r = Q_r - Q_{r+1} depends on its two boundaries
        // and on b. Codec order: thresholds (index 0 unused baseline), then b.
        [$m, $kmax, $p] = self::ggrm_partials($pp, $ip, $frac);
        $p = self::stabilize_denominator($p);
        $bidx = $kmax;

        $result = [];
        for ($i = 0; $i <= $bidx; $i++) {
            $result[$i] = 0.0;
        }
        foreach ($m['dp'] as $i => $dpi) {
            $result[$i] = $dpi / $p;
        }
        return $result;
    }

    /**
     * First and second derivatives of the observed category probability for GGRM.
     *
     * @param array $pp person ability parameter
     * @param array $ip item parameters
     * @param float $frac observed response fraction
     *
     * @return array [partials, kmax, likelihood]
     *
     */
    private static function ggrm_partials(array $pp, array $ip, float $frac): array {
        $ability = $pp['ability'];
        $a = self::sort_fractions($ip['difficulties']);
        $b = $ip['discrimination'];
        $frac = min(1.0, max(0.0, $frac));
        $fractions = self::get_fractions($a);
        $kmax = max(array_keys($fractions));
        $r = self::get_key_by_fractions($frac, $a);
        $bidx = $kmax; // Discrimination index (after M free thresholds 0..kmax-1).

        $p = self::stabilize_denominator(self::likelihood($pp, $ip, $frac));

        $haslo = ($r > 0);
        $hashi = ($r < $kmax);

        $dp = [];
        $d2p = [];

        if ($haslo) {
            $xlo = $ability - $a[$fractions[$r]];
            $qlo = self::logistic($b * $xlo);
            $wlo = self::logistic_w($qlo);
            $vlo = $wlo * (1.0 - 2.0 * $qlo);
            $dp[$r - 1] = -$b * $wlo;
        }
        if ($hashi) {
            $xhi = $ability - $a[$fractions[$r + 1]];
            $qhi = self::logistic($b * $xhi);
            $whi = self::logistic_w($qhi);
            $vhi = $whi * (1.0 - 2.0 * $qhi);
            $dp[$r] = $b * $whi;
        }
        $dp[$bidx] = ($haslo ? $xlo * $wlo : 0.0) - ($hashi ? $xhi * $whi : 0.0);

        // Second derivatives of P = Q_r - Q_{r+1}.
        if ($haslo) {
            $lo = $r - 1;
            $d2p["$lo,$lo"] = $b ** 2 * $vlo;
            $d2p["$lo,$bidx"] = -$wlo - $b * $xlo * $vlo;
        }
        if ($hashi) {
            $hi = $r;
            $d2p["$hi,$hi"] = -($b ** 2) * $vhi;
            $d2p["$hi,$bidx"] = $whi + $b * $xhi * $vhi;
        }
        // The two thresholds do not interact: d^2P/da_r da_{r+1} = 0.
        $d2p["$bidx,$bidx"] = ($haslo ? $xlo ** 2 * $vlo : 0.0) - ($hashi ? $xhi ** 2 * $vhi : 0.0);

        return [['dp' => $dp, 'd2p' => $d2p], $kmax, $p];
    }

    /**
     * Calculates the 2nd derivative of the LOG Likelihood with respect to the item parameters
     *
     * @param array $pp - person ability parameter ('ability')
     * @param array $ip - item parameters ('difficulty', 'discrimination')
     * @param float $frac - answer category (0 .. 1.0)
     *
     * @return array - hessian matrix
     */
    public static function get_log_hessian(array $pp, array $ip, float $frac): array {
        // GGRM item-parameter curvature: H_{p,q} = P''_{p,q}/P - (P'_p/P)(P'_q/P),
        // assembled over the active parameters (the two category boundaries and b).
        [$m, $kmax, $p] = self::ggrm_partials($pp, $ip, $frac);
        $bidx = $kmax;
        $dp = $m['dp'];
        $d2p = $m['d2p'];

        $result = [];
        for ($i = 0; $i <= $bidx; $i++) {
            $result[$i] = [];
            for ($j = 0; $j <= $bidx; $j++) {
                $result[$i][$j] = 0.0;
            }
        }

        $indices = array_keys($dp);
        foreach ($indices as $i) {
            foreach ($indices as $j) {
                $key = ($i <= $j) ? "$i,$j" : "$j,$i";
                $second = $d2p[$key] ?? 0.0;
                $result[$i][$j] = $second / $p - ($dp[$i] / $p) * ($dp[$j] / $p);
            }
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
     * TOOO: rename fisher_info into item_information, until than this acts as an alias.
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
        $minconfig = get_config('catmodel_grmgeneralized', 'trusted_region_min_a');
        $min = ($minconfig === false || $minconfig === '') ? -5.0 : (float) $minconfig;
        $maxconfig = get_config('catmodel_grmgeneralized', 'trusted_region_max_a');
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
        if (isset($ip['discrimination'])) {
            $ip['discrimination'] = self::restrict_discrimination('catmodel_grmgeneralized', $ip['discrimination']);
        }
        return $ip;
    }

    /**
     * Get parameter fields
     *
     * @param model_item_param $param
     * @return array
     */
    public function get_parameter_fields(model_item_param $param): array {
        if (!$param->get_params_array()) {
            return $this->get_default_params();
        }
        $parameters = ['discrimination' => $param->get_params_array()['discrimination']];
        $counter = 0;
        foreach ($param->get_params_array()['difficulties'] as $frac => $val) {
            $parameters['fraction_' . ++$counter] = $frac;
            $parameters['difficulty_' . $counter] = $val;
        }
        return $parameters;
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
