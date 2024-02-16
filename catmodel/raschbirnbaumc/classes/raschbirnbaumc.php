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
 * Class raschbirnbaumc.
 *
 * @package  catmodel_raschbirnbaumc
 * @copyright 2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license  http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace catmodel_raschbirnbaumc;

use local_catquiz\catcalc;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_person_param_list;
use local_catquiz\local\model\model_raschmodel;

/**
 * Class raschbirnbaumc of catmodels.
 *
 * @copyright 2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license  http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class raschbirnbaumc extends model_raschmodel {

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
    public static function get_model_dim(): int {
        // Adds +1 for the person ability.
        return count (self::get_parameter_names()) + 1;
    }

    /**
     * Initiate item parameter list
     *
     * @return model_item_param_list
     */
    public static function get_item_parameters(): model_item_param_list {
        // TODO implement.
        return new model_item_param_list();
    }

    /**
     * Initiate person ability parameter list
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
     *
     * @return array
     *
     */
    public function calculate_params($itemresponse): array {
        return catcalc::estimate_item_params($itemresponse, $this);
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
            return $c + (1 - $c) / (1 + exp($b * ($a - $ability)));
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
        $pp = $pp['ability'];
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];
        $c = $ip['guessing'];

        if ($k < 1.0) {
            return -(($b * exp($b * $pp)) / (exp($a * $b) + exp($b * $pp)));
        } else {
            return -(($b * (-1 + $c) * exp($b * ($a + $pp))) /
                ((exp($a * $b) + exp($b * $pp)) * ($c * exp($a * $b) + exp($b * $pp))));
        }
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
        $pp = $pp['ability'];
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];
        $c = $ip['guessing'];

        if ($k < 1.0) {
            return -(($b ** 2 * exp($b * ($a + $pp))) / (exp($a * $b) + exp($b * $pp)) ** 2);
        } else {
            return ($b ** 2 * ($c - 1) * exp( $b * ($pp - $a)) * (exp(2 * $b * ($pp - $a)) - $c)) /
                ((1 + exp($b * ( $pp - $a))) ** 2 * ($c + exp($b * ($pp - $a))) ** 2);
        }
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
        $pp = $pp['ability'];
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];
        $c = $ip['guessing'];

        $jacobian = [];

        // Pre-Calculate high frequently used exp-terms.
        $expab = exp($a * $b);
        $expbp = exp($b * $pp);
        $expbap1 = exp($b * ($a + $pp));
        $expbap0 = exp($b * ($a - $pp));

        if ($k >= 1.0) {
            $jacobian[0] = ($b * ($c - 1) * $expbap1) / (($expab + $expbp) * ($c * $expab + $expbp)); // Calculate d/da.
            $jacobian[1] = (($c - 1) * $expbap1 * ($a - $pp)) / (($expab + $expbp) * ($c * $expab + $expbp)); // Calculate d/db.
            $jacobian[2] = $expab / ($c * $expab + $expbp); // Calculate d/dc.
        } else {
            $jacobian[0] = $b / (1 + $expbap0); // Calculate d/da.
            $jacobian[1] = ($a - $pp) / (1 + $expbap0); // Calculate d/db.
            $jacobian[2] = 1 / ($c - 1); // Calculate d/dc.
        }
        return $jacobian;
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
        $pp = $pp['ability'];
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];
        $c = $ip['guessing'];

        $hessian = [[]];

        // Pre-Calculate high frequently used exp-terms.
        $expab = exp($a * $b);
        $expbp = exp($b * $pp);

        if ($k >= 1.0) {
            // Calculate d²/ da².
            $hessian[0][0] = -($b ** 2 * ($c - 1) * exp($b * ($a + $pp)) * ($c * exp(2 * $a * $b) - exp(2 * $b * $pp))) /
                ((exp($a * $b) + exp($b * $pp)) ** 2 * ($c * exp($a * $b) + exp($b * $pp)) ** 2);
            // Calculate d/da d/db.
            $hessian[0][1] = (($c - 1) * exp($b * ($a + $pp)) * (exp($b * ($a + $pp)) + exp(2 * $b * $pp) *
                (1 + $a * $b - $b * $pp) + $c * (exp($b * ($a + $pp)) + exp(2 * $a * $b) * (1 - $a * $b + $b * $pp)))) /
                ((exp($a * $b) + exp($b * $pp)) ** 2 * ($c * exp($a * $b) + exp($b * $pp)) ** 2);
            // Calculate d/da d/dc.
            $hessian[0][2] = ($b * exp($b * ($a + $pp))) / ($c * exp($a * $b) + exp($b * $pp)) ** 2;
            $hessian[1][0] = $hessian[0][1];
            // Calculate d²/db².
            $hessian[1][1] = -(($c - 1) * exp($b * ($a - $pp)) * ($c * exp(2 * $b * ($a - $pp)) - 1) * ($a - $pp) ** 2) /
                (((1 + exp($b * ($a - $pp))) * (1 + $c * exp($b * ($a - $pp)))) ** 2);
            // Calculate d/db d/dc.
            $hessian[1][2] = (exp($b * ($a + $pp)) * ($a - $pp)) / ($c * exp($a * $b) + exp($b * $pp)) ** 2;
            $hessian[2][0] = $hessian[0][2];
            $hessian[2][1] = $hessian[1][2];
            // Calculate d²/dc².
            $hessian[2][2] = -exp(2 * $a * $b) / ($c * exp($a * $b) + exp($b * $pp)) ** 2;
        } else {
            // Calculate d²/da².
            $hessian[0][0] = -($b ** 2 * exp($b * ($a - $pp))) / (1 + exp($b * ($a - $pp))) ** 2;
            // Calculate d/da d/db.
            $hessian[0][1] = (exp($b * ($a - $pp)) * ($b * ($pp - $a) + 1) + 1) / (exp($b * ($a - $pp)) + 1) ** 2;
            // Calculate d/da d/dc.
            $hessian[0][2] = 0;
            $hessian[1][0] = $hessian[0][1];
            // Calculate .d²/db².
            $hessian[1][1] = -(exp($b * ($a - $pp)) * ($a - $pp) ** 2) / (1 + exp($b * ($a - $pp))) ** 2;
            // Calculate d/db d/dc.
            $hessian[1][2] = 0;
            $hessian[2][0] = $hessian[0][2];
            $hessian[2][1] = $hessian[1][2];
            // Calculate d²/dc².
            $hessian[2][2] = -1 / ($c - 1) ** 2;
        }
        return $hessian;
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
        $pp = $pp['ability'];
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];
        $c = $ip['guessing'];

        $derivative = [];

        // Pre-Calculate high frequently used exp-terms.
        $expbap = exp($b * ($a - $pp));

        // Calculate d/da.
        $derivative[0] = $n * (-(2 * $b * (1 - $c) * $expbap) / (1 + $expbap - $frac) ** 3);
        // Calculate d/db.
        $derivative[1] = $n * (-(2 * (1 - $c) * $expbap * ($c + (1 - $c) / (1 + $expbap) - $frac) * ($a - $pp)) /
            (1 + $expbap) ** 2);
        // Calculate d/dc.
        $derivative[2] = $n * 2 * (1 - 1 / (1 + $expbap)) * ($c + (1 - $c) / (1 + $expbap) - $frac);

        return $derivative;
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
        $pp = $pp['ability'];
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];
        $c = $ip['guessing'];

        $derivative = [[]];

        // Pre-Calculate high frequently used exp-terms.
        $expbap = exp($b * ($a - $pp));
        $expab = exp($a * $b);
        $expbp = exp($b * $pp);

        // Calculate d²/da².
        $derivative[0][0]  = $n * (-(2 * $b ** 2 * (1 - $c) * $expbap * ((1 - $c) / ($expbap + 1) + $c - $frac)) /
            ($expbap + 1) ** 2 + (4 * $b ** 2 * (1 - $c) * $expbap ** 2 * ((1 - $c) / ($expbap + 1) + $c - $frac)) /
            ($expbap + 1) ** 3 + (2 * $b ** 2 * (1 - $c) ** 2 * $expbap ** 2) / ($expbap + 1) ** 4);
        // Calculate d/da d/db.
        $derivative[0][1]  = $n * (-(2 * (1 - $c) * $expbap * ((1 - $c) / ($expbap + 1) + $c - $frac)) /
            ($expbap + 1) ** 2 - (2 * $b * (1 - $c) * ($a - $pp) * $expbap * ((1 - $c) / ($expbap + 1) + $c - $frac)) /
            ($expbap + 1) ** 2 + (4 * $b * (1 - $c) * ($a - $pp) * $expbap ** 2 * ((1 - $c) / ($expbap + 1) + $c - $frac)) /
            ($expbap + 1) ** 3 + (2 * $b * (1 - $c) ** 2 * ($a - $pp) * $expbap ** 2) / ($expbap + 1) ** 4);
        // Calculate d/da d/dc.
        $derivative[0][2]  = $n * (2 * $b * $expbap * ((2 * $c - $frac - 1) * $expbap - $frac + 1)) / ($expbap + 1) ** 3;
        // Calculate d²/db².
        $derivative[1][1]  = $n * (2 * ($a - $pp) * $expbap * ((1 - $c) / ($expbap + 1) + $c - $frac)) /
            ($expbap + 1) ** 2 - (2 * (1 - $c) * ($a - $pp) * $expbap * (1 - 1 / ($expbap + 1))) / ($expbap + 1) ** 2;
        // Calculate d/db d/dc.
        $derivative[1][2]  = $n * (2 * ($a - $pp) * $expbap * ((2 * $c - $frac - 1) * $expbap - $frac + 1)) / ($expbap + 1) ** 3;
        // Calculate d²/dc².
        $derivative[2][2]  = $n * (2 * $expab ** 2) / ($expab + $expbp) ** 2;

        // Note: Partial derivations are exchangeible, cf. Theorem of Schwarz.
        $derivative[1][0] = $derivative[0][1];
        $derivative[2][0] = $derivative[0][2];
        $derivative[2][1] = $derivative[1][2];

        return $derivative;
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
        $c = $ip['guessing'];

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
        $pp = $pp['ability'];
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];
        $c = $ip['guessing'];

        $derivative = [];

        // TODO: @RALF: Korrekte Formeln für 3PL implementieren!

        $derivative[0] = $n * 2 * $b * ($b * ($a - $pp) + log($or)); // Calculate d/da.
        $derivative[1] = $n * 2 * ($a - $pp) * ($b * ($a - $pp) + log($or)); // Calculate d/db.

        return $derivative;
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
        $pp = $pp['ability'];
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];
        $c = $ip['guessing'];

        $derivative = [[]];

        // TODO: @RALF: Korrekte Formeln für 3PL implementieren!

        $derivative[0][0]  = $n * 2 * $b ** 2; // Calculate d²2/da².
        $derivative[0][1]  = 0; // TODO: $n * 2 * (2 * $b * ($a - $pp) + log($or)); // Calculate d/da d/db.
        $derivative[1][1]  = $n * 2 * ($a - $pp) ** 2; // Calculate d²/db².

        // Note: Partial derivations are exchangeable, cf. Theorem of Schwarz.
        $derivative[1][0] = $derivative[0][1];

        return $derivative;
    }

    /**
     * Calculate Fisher-Information.
     *
     * @param array $pp
     * @param array $ip
     *
     * @return float
     *
     */
    public static function fisher_info(array $pp, array $ip): float {
        return $ip['difficulty'] ** 2 * (1 - $ip['guessing']) * self::likelihood($pp, $ip, 1.0) * (self::likelihood($pp, $ip, 0.0));
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
        $atr = floatval(get_config('catmodel_raschbirnbaumc', 'trusted_region_factor_sd_a'));
        $amin = floatval(get_config('catmodel_raschbirnbaumc', 'trusted_region_min_a'));
        $amax = floatval(get_config('catmodel_raschbirnbaumc', 'trusted_region_max_a'));

        // Set values for disrciminatory parameter.
        $b = $ip['discrimination'];

        // Placement of the discriminatory parameter.
        $bp = floatval(get_config('catmodel_raschbirnbaumc', 'trusted_region_placement_b'));
        // Slope of the discriminatory parameter.
        $bs = floatval(get_config('catmodel_raschbirnbaumc', 'trusted_region_slope_b'));
        // Use 5 times of placement as maximal value of trusted region.
        $btr = floatval(get_config('catmodel_raschbirnbaumc', 'trusted_region_factor_max_b'));

        $bmin = floatval(get_config('catmodel_raschbirnbaumc', 'trusted_region_min_b'));
        $bmax = floatval(get_config('catmodel_raschbirnbaumc', 'trusted_region_max_b'));

        // Set values for guessing parameter.
        $c = $ip['guessing'];

        $cmax = floatval(get_config('catmodel_raschbirnbaumc', 'trusted_region_max_c'));

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
     * Calculates the 1st derivative trusted regions for item parameters
     *
     * @param array $ip - item parameters ('difficulty', 'discrimination', 'guessing')
     * @return array - 1st derivative of TR function with respect to $ip
     */
    public static function get_log_tr_jacobian(array $ip): array {
        // Set values for difficulty parameter.
        $am = 0; // Mean of difficulty.
        $as = 2; // Standard derivation of difficulty.

        // Placement of the discriminatory parameter.
        $bp = floatval(get_config('catmodel_raschbirnbaumc', 'trusted_region_placement_b'));
        // Slope of the discriminatory parameter.
        $bs = floatval(get_config('catmodel_raschbirnbaumc', 'trusted_region_slope_b'));

        return [
            // Calculate d/da.
            ($am - $ip['difficulty']) / ($as ** 2),
            // Calculate d/db.
            -($bs * exp($bs * $ip['discrimination'])) / (exp($bs * $bp) + exp($bs * $ip['discrimination'])),
            // Calculate d/dc.
            0,
        ];
    }

    /**
     * Calculates the 2nd derivative trusted regions for item parameters
     *
     * @param array $ip - item parameters ('difficulty', 'discrimination', 'guessing')
     *
     * @return array - 2nd derivative of TR function with respect to $ip
     */
    public static function get_log_tr_hessian(array $ip): array {
        // Set values for difficulty parameter.
        $am = 0; // Mean of difficulty.
        $as = 2; // Standard derivation of difficulty.

        // Placement of the discriminatory parameter.
        $bp = floatval(get_config('catmodel_raschbirnbaumc', 'trusted_region_placement_b'));
        // Slope of the discriminatory parameter.
        $bs = floatval(get_config('catmodel_raschbirnbaumc', 'trusted_region_slope_b'));

        return [
            [
                -1 / ($as ** 2), // Calculate d²/da².
                0, // Calculate d/da d/db.
                0, // Calculate d/da d/dc.
            ],
            [
                0, // The d/da d/db.
                -($bs ** 2 * exp($bs * ($bp + $ip['discrimination']))) /
                    (exp($bs * $bp) + exp($bs * $ip['discrimination'])) ** 2, // Calculate d²/db².
                0, // Calculate d/db d/dc.
            ],
            [
                0, // Calculate d/da d/dc.
                0, // Calculate d/db d/dc.
                0, // Calculate d²/dc².
            ],
        ];
    }
}
