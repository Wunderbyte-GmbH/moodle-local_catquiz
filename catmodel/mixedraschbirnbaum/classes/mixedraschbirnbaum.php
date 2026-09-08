<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Class mixedraschbirnbaum.
 *
 * @package  catmodel_mixedraschbirnbaum
 * @copyright 2024 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license  http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace catmodel_mixedraschbirnbaum;

use coding_exception;
use local_catquiz\catcalc;
use local_catquiz\local\model\model_item_param;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_person_param_list;
use local_catquiz\local\model\model_raschmodel;
use stdClass;

/**
 * Class mixedraschbirnbaum of catmodels.
 *
 * @copyright 2024 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license  http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mixedraschbirnbaum extends model_raschmodel {
    /**
     * {@inheritDoc}
     *
     * @param stdClass $record
     * @return array
     */
    /**
     * Validates the item parameters for the 3PL model.
     *
     * A 3PL item needs a strictly positive discrimination (see 2PL) and a guessing
     * parameter in [0, 1). A guessing value of 1 or above would make the item
     * answerable with certainty regardless of ability; a negative one is meaningless.
     *
     * @param stdClass $record The raw item parameter record.
     * @return string[] Reasons the parameters are invalid; empty array if valid.
     */
    public static function validate_parameters(stdClass $record): array {
        $reasons = parent::validate_parameters($record);

        if (!self::is_valid_positive_float($record->discrimination ?? null)) {
            $reasons[] = 'discrimination must be a number greater than 0';
        }

        $guessing = $record->guessing ?? null;
        if (!self::is_valid_float($guessing) || (float) $guessing < 0.0 || (float) $guessing >= 1.0) {
            $reasons[] = 'guessing must be a number in the range [0, 1)';
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
        return [
            'difficulty' => $record->difficulty,
            'discrimination' => $record->discrimination,
            'guessing' => $record->guessing,
        ];
    }

    /**
     * Allows subclasses to overwrite the parameters.
     *
     * @param stdClass $record
     * @param array $parameters
     * @return stdClass
     */
    public static function add_parameters_to_record(stdClass $record, array $parameters): stdClass {
        $record->difficulty = $parameters['difficulty'];
        $record->discrimination = $parameters['discrimination'];
        $record->guessing = $parameters['guessing'];
        return $record;
    }

    /**
     * Returns the name of this model.
     *
     * @return string
     */
    public function get_model_name(): string {
        return 'mixedraschbirnbaum';
    }

    // Definitions and Dimensions.

    /**
     * Defines names if item parameter list
     *
     * @return array of string
     */
    public static function get_parameter_names(): array {
        return ['difficulty', 'discrimination', 'guessing'];
    }

    /**
     * Definition of the number of model parameters
     *
     * @return int
     */
    /**
     * Serialises the item parameters into a flat numeric vector (parameter codec).
     *
     * @param array $ip item parameters
     *
     * @return array
     *
     */
    public static function convert_ip_to_vector(array $ip): array {
        return [$ip['difficulty'], $ip['discrimination'], $ip['guessing']];
    }

    /**
     * Reconstructs the item parameters from a flat numeric vector (parameter codec).
     *
     * @param array $vector flat parameter vector
     * @param mixed $fractions response fractions (unused for dichotomous models)
     *
     * @return array
     *
     */
    public static function convert_vector_to_ip(array $vector, $fractions = null): array {
        return ['difficulty' => $vector[0], 'discrimination' => $vector[1], 'guessing' => $vector[2]];
    }

    /**
     * The fixed model dimension (person ability plus item parameters).
     *
     * @return int
     *
     */
    public static function get_model_dim(): int {
        // Adds +1 for the person ability.
        return count(self::get_parameter_names()) + 1;
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

    // Calculate the Likelihood.

    /**
     * Calculates the Likelihood for a given the person ability parameter
     *
     * @param array $pp - person ability parameter ('ability')
     * @param array $ip - item parameters ('difficulty', 'discrimination', 'guessing')
     * @param float $k - answer category (0 or 1.0)
     * @return float
     */
    public static function likelihood(array $pp, array $ip, float $k): float {
        $ability = $pp['ability'];
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];
        $c = $ip['guessing'];

        if ($k < 1.0) {
            return 1 - self::likelihood($pp, $ip, 1.0);
        } else {
            return $c + (1 - $c) * self::logistic($b * ($ability - $a));
        }
    }

    // Calculate the LOG Likelihood and its derivatives.

    /**
     * Calculates the LOG Likelihood for a given the person ability parameter
     *
     * @param array $pp - person ability parameter ('ability')
     * @param array $ip - item parameters ('difficulty', 'discrimination', 'guessing')
     * @param float $k - answer category (0 or 1.0)
     * @return float
     */
    public static function log_likelihood(array $pp, array $ip, float $k): float {
        return log(self::likelihood($pp, $ip, $k));
    }

    /**
     * Calculates the 1st derivative of the LOG Likelihood with respect to the item parameters
     *
     * @param array $pp - person ability parameter ('ability')
     * @param array $ip - item parameters ('difficulty', 'discrimination', 'guessing')
     * @param float $k - answer category (0 or 1.0)
     * @return float - 1st derivative of log likelihood with respect to $pp
     */
    public static function log_likelihood_p(array $pp, array $ip, float $k): float {
        $ability = $pp['ability'];
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];
        $c = $ip['guessing'];

        // P/W form. L logistic core, P = c + (1 - c) L; dP/dtheta = (1 - c) b W_L.
        $l = self::logistic($b * ($ability - $a));
        $p = $c + (1.0 - $c) * $l;

        // The naive score (k - P) / (P (1 - P)) * dP/dtheta divides by P (1 - P),
        // which underflows to exactly 0 at saturation (L -> 0 or 1). Using
        // 1 - P = (1 - c)(1 - L) and W_L = L (1 - L), the factor (1 - c) W_L / (1 - P)
        // cancels to L, so the score simplifies to b L (k - P) / P — dividing only by
        // P, and P >= c. The remaining P = 0 case (c = 0 with an underflowed L) is the
        // 2PL limit b (k - L).
        if ($p <= 0.0) {
            return $b * ($k - $l);
        }
        return $b * $l * ($k - $p) / $p;
    }

    /**
     * Calculates the 2nd derivative of the LOG Likelihood with respect to the person ability parameter
     *
     * @param array $pp - person ability parameter
     * @param array $ip - item parameters ('difficulty', 'discrimination', 'guessing')
     * @param float $k - answer category (0 or 1.0)
     * @return float - 2nd derivative of log likelihood with respect to $pp
     */
    public static function log_likelihood_p_p(array $pp, array $ip, float $k): float {
        $ability = $pp['ability'];
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];
        $c = $ip['guessing'];

        // P/W form via the chain rule on P = c + (1 - c) L. The naive expression
        // divides by P (1 - P), P^2 and (1 - P)^2, all of which underflow to 0 at
        // saturation. We express the surviving terms through the ratio L/P, which is
        // well defined even when both L and P are denormal (it tends to 1 for c = 0
        // and to 0 for c > 0). This avoids forming P^2, which underflows to 0.0 for a
        // denormal P and would otherwise break the L^2/P^2 -> 1 cancellation, leaving
        // a spurious +b^2 instead of the correct 2PL limit at saturation.
        // The P = 0 case (c = 0 with an exactly-underflowed L) is the 2PL limit -b^2 W_L.
        $l = self::logistic($b * ($ability - $a));
        $wl = self::logistic_w($l);
        $p = $c + (1.0 - $c) * $l;

        if ($p <= 0.0) {
            return -($b ** 2) * $wl;
        }

        $b2 = $b ** 2;
        $onemp = 1.0 - $p;
        $ratio = $l / $p;
        $terma = $b2 * $ratio * (1.0 - 2.0 * $l) * ($k - $p);
        $termmid = -$k * $b2 * $ratio ** 2 * $onemp ** 2;
        $termlast = -(1.0 - $k) * $b2 * $l ** 2;

        return $terma + $termmid + $termlast;
    }

    /**
     * Combined person-ability score and hessian sharing one L/P computation.
     *
     * @param array $pp person ability parameter ('ability')
     * @param array $ip item parameters ('difficulty', 'discrimination', 'guessing')
     * @param float $frac answer category (0 or 1.0)
     * @return array ['jacobian' => 1st derivative, 'hessian' => 2nd derivative]
     */
    public static function get_ability_derivatives(array $pp, array $ip, float $frac): array {
        $ability = $pp['ability'];
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];
        $c = $ip['guessing'];

        $l = self::logistic($b * ($ability - $a));
        $wl = self::logistic_w($l);
        $p = $c + (1.0 - $c) * $l;

        if ($p <= 0.0) {
            // Degenerate saturation (c = 0, L underflowed): 2PL limits.
            return ['jacobian' => $b * ($frac - $l), 'hessian' => -($b ** 2) * $wl];
        }

        $b2 = $b ** 2;
        $onemp = 1.0 - $p;
        $ratio = $l / $p;
        return [
            'jacobian' => $b * $ratio * ($frac - $p),
            'hessian' => $b2 * $ratio * (1.0 - 2.0 * $l) * ($frac - $p)
                - $frac * $b2 * $ratio ** 2 * $onemp ** 2
                - (1.0 - $frac) * $b2 * $l ** 2,
        ];
    }

    /**
     * Calculates the 1st derivative of the LOG Likelihood with respect to the item parameters
     *
     * @param array $pp - person ability parameter ('ability')
     * @param array $ip - item parameters ('difficulty', 'discrimination', 'guessing')
     * @param float $k - answer category (0 or 1.0)
     * @return array - jacobian vector
     */
    public static function get_log_jacobian($pp, $ip, float $k): array {
        $ability = $pp['ability'];
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];
        $c = $ip['guessing'];

        // P/W form. L is the logistic core, P the actual 3PL success probability.
        $x = $ability - $a;               // Theta - a.
        $l = self::logistic($b * $x);     // L = sigma(b (theta - a)).
        $wl = self::logistic_w($l);       // Wl = L (1 - L).
        $omc = 1.0 - $c;
        $p = $c + $omc * $l;              // P = c + (1 - c) L.

        // First derivatives of P.
        $pa = -$b * $omc * $wl;           // DP/da.
        $pb = $omc * $wl * $x;            // DP/db.
        $pc = 1.0 - $l;                   // DP/dc.

        $dlp = ($k - $p) / self::stabilize_denominator($p * (1.0 - $p)); // D log L / dP.

        return [
            $dlp * $pa, // D/da.
            $dlp * $pb, // D/db.
            $dlp * $pc, // D/dc.
        ];
    }

    /**
     * Calculates the 2nd derivative of the LOG Likelihood with respect to the item parameters
     *
     * @param array $pp - person ability parameter ('ability')
     * @param array $ip - item parameters ('difficulty', 'discrimination', 'guessing')
     * @param float $k - answer category (0 or 1.0)
     * @return array - hessian matrx
     */
    public static function get_log_hessian($pp, $ip, float $k): array {
        $ability = $pp['ability'];
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];
        $c = $ip['guessing'];

        // P/W form via the chain rule on P = c + (1 - c) L, with
        // H_ij = (d^2 log L / dP^2) P_i P_j + (d log L / dP) P_ij.
        $x = $ability - $a;               // Theta - a.
        $l = self::logistic($b * $x);     // L = sigma(b (theta - a)).
        $wl = self::logistic_w($l);       // Wl = L (1 - L) = L'(z).
        $vl = $wl * (1.0 - 2.0 * $l);     // Vl = Wl (1 - 2L) = L''(z).
        $omc = 1.0 - $c;
        $p = $c + $omc * $l;             // P.

        // First and second derivatives of P.
        $pa = -$b * $omc * $wl;
        $pb = $omc * $wl * $x;
        $pc = 1.0 - $l;
        $paa = $omc * $vl * $b ** 2;
        $pbb = $omc * $vl * $x ** 2;
        $pab = $omc * (-$b * $x * $vl - $wl);
        $pac = $b * $wl;
        $pbc = -$x * $wl;
        // Second derivative d^2P/dc^2 vanishes.

        // Derivatives of the Bernoulli log likelihood with respect to P.
        $dlp = ($k - $p) / self::stabilize_denominator($p * (1.0 - $p));                       // D log L / dP.
        // D^2 log L / dP^2.
        $d2lp = -$k / self::stabilize_denominator($p ** 2)
            - (1.0 - $k) / self::stabilize_denominator((1.0 - $p) ** 2);

        $haa = $d2lp * $pa * $pa + $dlp * $paa;
        $hbb = $d2lp * $pb * $pb + $dlp * $pbb;
        $hcc = $d2lp * $pc * $pc;                                   // Plus dlp * (d^2P/dc^2 = 0).
        $hab = $d2lp * $pa * $pb + $dlp * $pab;
        $hac = $d2lp * $pa * $pc + $dlp * $pac;
        $hbc = $d2lp * $pb * $pc + $dlp * $pbc;

        return [
            [$haa, $hab, $hac],
            [$hab, $hbb, $hbc],
            [$hac, $hbc, $hcc],
        ];
    }

    // Calculate the Least-Mean-Squres (LMS) approach.

    /**
     * Calculates the Least Mean Squres (residuals) for a given the person ability parameter and a given expected/observed score
     *
     * @param array $pp - person ability parameter ('ability')
     * @param array $ip - item parameters ('difficulty', 'discrimination', 'guessing')
     * @param float $frac - fraction of correct (0 ... 1.0)
     * @param float $n - number of observations
     * @return float - weighted residuals
     */
    public static function least_mean_squares(array $pp, array $ip, float $frac, float $n): float {
        return $n * ($frac - self::likelihood($pp, $ip, 1.0)) ** 2;
    }

    /**
     * Calculates the 1st derivative of Least Mean Squares with respect to the item parameters
     *
     * @param array $pp - person ability parameter ('ability')
     * @param array $ip - item parameters ('difficulty', 'discrimination', 'guessing')
     * @param float $frac - fraction of correct (0 ... 1.0)
     * @param float $n - number of observations
     * @return array - 1st derivative of lms with respect to $ip
     */
    public static function least_mean_squares_1st_derivative_ip(array $pp, array $ip, float $frac, float $n): array {
        $ability = $pp['ability'];
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];
        $c = $ip['guessing'];

        // LMS objective S = n (frac - P)^2 with P = c + (1 - c) L, L = sigma(b (theta - a)).
        // Gradient: dS/dtheta_i = 2 n (P - frac) * dP/dtheta_i.
        $x = $ability - $a;
        $l = self::logistic($b * $x);
        $wl = self::logistic_w($l);
        $omc = 1.0 - $c;
        $p = $c + $omc * $l;

        $pa = -$b * $omc * $wl;   // DP/da.
        $pb = $omc * $wl * $x;    // DP/db.
        $pc = 1.0 - $l;           // DP/dc.

        $factor = 2.0 * $n * ($p - $frac);

        return [
            $factor * $pa, // D/da.
            $factor * $pb, // D/db.
            $factor * $pc, // D/dc.
        ];
    }

    /**
     * Calculates the 2nd derivative of Least Mean Squres with respect to the item parameters
     *
     * @param array $pp - person ability parameter ('ability')
     * @param array $ip - item parameters ('difficulty', 'discrimination', 'guessing')
     * @param float $frac - fraction of correct (0 ... 1.0)
     * @param float $n - number of observations
     * @return array - 2nd derivative of lms with respect to $ip
     */
    public static function least_mean_squares_2nd_derivative_ip(array $pp, array $ip, float $frac, float $n): array {
        $ability = $pp['ability'];
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];
        $c = $ip['guessing'];

        // LMS objective S = n (frac - P)^2. Hessian:
        // D^2S/dtheta_i dtheta_j = 2 n [ P_i P_j + (P - frac) P_ij ].
        $x = $ability - $a;
        $l = self::logistic($b * $x);
        $wl = self::logistic_w($l);
        $vl = $wl * (1.0 - 2.0 * $l);
        $omc = 1.0 - $c;
        $p = $c + $omc * $l;
        $r = $p - $frac;

        // First derivatives of P.
        $pa = -$b * $omc * $wl;
        $pb = $omc * $wl * $x;
        $pc = 1.0 - $l;
        // Second derivatives of P.
        $paa = $omc * $vl * $b ** 2;
        $pbb = $omc * $vl * $x ** 2;
        $pab = $omc * (-$b * $x * $vl - $wl);
        $pac = $b * $wl;
        $pbc = -$x * $wl;
        // Second derivative d^2P/dc^2 vanishes.

        $k = 2.0 * $n;
        $haa = $k * ($pa * $pa + $r * $paa);
        $hbb = $k * ($pb * $pb + $r * $pbb);
        $hcc = $k * ($pc * $pc);
        $hab = $k * ($pa * $pb + $r * $pab);
        $hac = $k * ($pa * $pc + $r * $pac);
        $hbc = $k * ($pb * $pc + $r * $pbc);

        return [
            [$haa, $hab, $hac],
            [$hab, $hbb, $hbc],
            [$hac, $hbc, $hcc],
        ];
    }


    // Calculate the Log'ed Odds-Ratio Squared (LORS) approach.

    /**
     * Calculates the Log'ed Odds-Ratio Squared (residuals) for a given the person ability parameter
     * and a given expected/observed score
     *
     * @param array $pp - person ability parameter ('ability')
     * @param array $ip - item parameters ('difficulty', 'discrimination', 'guessing')
     * @param float $or - odds ratio
     * @param float $n - number of observations
     * @return float - weighted residuals
     */
    public static function lors_residuals(array $pp, array $ip, float $or, float $n = 1): float {
        $pp = $pp['ability'];
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];

        return $n * (log($or) + $b * ($a - $pp)) ** 2;
    }

    /**
     * Calculates the 1st derivative of Log'ed Odds-Ratio Squared with respect to the item parameters
     *
     * @param array $pp - person ability parameter ('ability')
     * @param array $ip - item parameters ('difficulty', 'discrimination', 'guessing')
     * @param float $or - odds ratio
     * @param float $n - number of observations
     * @return array - 1st derivative
     */
    public static function lors_1st_derivative_ip(array $pp, array $ip, float $or, float $n = 1): array {
        $ability = $pp['ability'];
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];

        // LORS residual R = b (a - theta) + log(OR); the objective is n * R^2.
        // R does not depend on the guessing parameter c, so d/dc is identically
        // zero and the gradient is [d/da, d/db, d/dc] = [2n b R, 2n (a-theta) R, 0].
        $x = $a - $ability;
        $r = $b * $x + log($or);

        return [
            $n * 2 * $b * $r, // Calculate d/da.
            $n * 2 * $x * $r, // Calculate d/db.
            0.0, // Calculate d/dc (LORS is independent of guessing).
        ];
    }

    /**
     * Calculates the 2nd derivative of Log'ed Odds-Ratio Squared with respect to the item parameters
     *
     * @param array $pp - person ability parameter ('ability')
     * @param array $ip - item parameters ('difficulty', 'discrimination', 'guessing')
     * @param float $or - odds ratio
     * @param float $n - number of observations
     * @return array - 1st derivative
     */
    public static function lors_2nd_derivative_ip(array $pp, array $ip, float $or, float $n = 1): array {
        $ability = $pp['ability'];
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];

        // LORS residual R = b (a - theta) + log(OR); the objective is n * R^2.
        // With dR/da = b, dR/db = (a - theta), d^2R/da db = 1 and no dependence
        // on the guessing parameter c, all second derivatives involving c vanish.
        $x = $a - $ability;

        $haa = $n * 2 * $b ** 2;                   // D^2/da^2.
        $hbb = $n * 2 * $x ** 2;                   // D^2/db^2.
        $hab = $n * 2 * (2 * $b * $x + log($or));  // D^2/da db = 2n (2 b (a-theta) + log(OR)).

        // Partial derivatives are exchangeable (Schwarz), and the guessing row and
        // column are zero because the residual is independent of c.
        return [
            [$haa, $hab, 0.0],
            [$hab, $hbb, 0.0],
            [0.0, 0.0, 0.0],
        ];
    }

    /**
     * Calculate Fisher-Information.
     *
     * For the 3PL model with P = P(Y = 1) = c + (1 - c) * sigma(b * (theta - a))
     * the item information is
     *   I(theta) = b^2 * (1 - P) / P * ((P - c) / (1 - c))^2 .
     * For c = 0 this correctly reduces to the 2PL form b^2 * P * (1 - P).
     * The information depends quadratically on the discrimination b, not on the
     * difficulty a (the previous implementation used a^2 and was incorrect).
     *
     * @param array $pp
     * @param array $ip
     *
     * @return float
     *
     */
    public function fisher_info(array $pp, array $ip): float {
        $b = $ip['discrimination'];
        $c = $ip['guessing'];
        $p = self::likelihood($pp, $ip, 1.0); // P(Y = 1).
        $q = self::likelihood($pp, $ip, 0.0); // P(Y = 0) = 1 - P.
        return $b ** 2 * ($q / $p) * (($p - $c) / (1 - $c)) ** 2;
    }

    /**
     * Implements a Filter Function for trusted regions in the item parameter estimation
     *
     * @param array $ip - item parameters ('difficulty', 'discrimination', 'guessing')
     * @return array - chunked item parameter
     */
    public static function restrict_to_trusted_region(array $ip): array {
        // Set values for difficulty parameter.
        $a = $ip['difficulty'];

        $am = 0; // Mean of difficulty.
        $as = 2; // Standard derivation of difficulty.

        // Use 3 times of SD as range of trusted regions.
        $atr = floatval(get_config('catmodel_mixedraschbirnbaum', 'trusted_region_factor_sd_a'));
        $amin = floatval(get_config('catmodel_mixedraschbirnbaum', 'trusted_region_min_a'));
        $amax = floatval(get_config('catmodel_mixedraschbirnbaum', 'trusted_region_max_a'));

        // Set values for disrciminatory parameter.
        $b = $ip['discrimination'];

        // Placement of the discriminatory parameter.
        $bp = floatval(get_config('catmodel_mixedraschbirnbaum', 'trusted_region_placement_b'));
        // Use 5 times of placement as maximal value of trusted region.
        $btr = floatval(get_config('catmodel_mixedraschbirnbaum', 'trusted_region_factor_max_b'));

        $bmin = floatval(get_config('catmodel_mixedraschbirnbaum', 'trusted_region_min_b'));
        $bmax = floatval(get_config('catmodel_mixedraschbirnbaum', 'trusted_region_max_b'));

        // Set values for guessing parameter.
        $c = $ip['guessing'];

        $cmax = floatval(get_config('catmodel_mixedraschbirnbaum', 'trusted_region_max_c'));

        // Test TR for difficulty.
        if (($a - $am) < max(-($atr * $as), $amin)) {
            $a = max(-($atr * $as), $amin);
        }
        if (($a - $am) > min(($atr * $as), $amax)) {
            $a = min(($atr * $as), $amax);
        }

        $ip['difficulty'] = $a;

        // Test TR for discriminatory.
        if ($b < $bmin) {
            $b = $bmin;
        }
        if ($b > min(($btr * $bp), $bmax)) {
            $b = min(($btr * $bp), $bmax);
        }

        $ip['discrimination'] = $b;

        // Test TR for guessing.
        if ($c < 0) {
            $c = 0;
        }
        if ($c > $cmax) {
            $c = $cmax;
        }

        $ip['guessing'] = $c;

        return $ip;
    }

    /**
     * Get default params
     *
     * @return array
     */
    public function get_default_params(): array {
        return [
            'difficulty' => 0.0,
            'discrimination' => 1.0,
            'guessing' => 0.5,
        ];
    }

    /**
     * Get static param array
     *
     * @param model_item_param $param
     * @return array
     * @throws coding_exception
     */
    public function get_static_param_array(model_item_param $param): array {
        $difflabel = get_string('difficulty', 'local_catquiz');
        $disclabel = get_string('discrimination', 'local_catquiz');
        $guessinglabel = get_string('guessing', 'local_catquiz');
        return [
            $difflabel => $param->get_difficulty(),
            $disclabel => $param->get_params_array()['discrimination'],
            $guessinglabel => $param->get_params_array()['guessing'],
        ];
    }
}
