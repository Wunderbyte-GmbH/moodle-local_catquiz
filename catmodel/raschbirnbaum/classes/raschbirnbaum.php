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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Class raschbirnbaum.
 *
 * @package    catmodel_raschbirnbaum
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace catmodel_raschbirnbaum;

use coding_exception;
use local_catquiz\catcalc;
use local_catquiz\local\model\model_item_param;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_raschmodel;
use stdClass;

/**
 * Class rasch of catmodels.
 *
 * @package    catmodel_raschbirnbaum
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class raschbirnbaum extends model_raschmodel {
    /**
     * {@inheritDoc}
     *
     * @param stdClass $record
     * @return array
     */
    /**
     * Validates the item parameters for the 2PL model.
     *
     * On top of the base contract (finite, signed difficulty) a 2PL item needs a
     * strictly positive discrimination. With a = 0 the response probability
     * collapses to 0.5 for every ability, the Fisher information is 0 and the item
     * contributes nothing - the ability estimate then appears "frozen". A negative
     * a would invert the item (harder items becoming easier), which is never a
     * valid calibration result here.
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
        return [
            'difficulty' => $record->difficulty,
            'discrimination' => $record->discrimination,
        ];
    }

    // Definitions and Dimensions.

    /**
     * Defines names if item parameter list
     *
     * @return array of string
     */
    public static function get_parameter_names(): array {
        return ['difficulty', 'discrimination'];
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
        return [$ip['difficulty'], $ip['discrimination']];
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
        return ['difficulty' => $vector[0], 'discrimination' => $vector[1]];
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
     * Returns the name of this model.
     *
     * @return string
     */
    public function get_model_name(): string {
        return 'raschbirnbaum';
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
     * @param array $pp - person ability parameter
     * @param array $ip - item parameters ('difficulty', 'discrimination')
     * @param float $k - answer category (0 or 1.0)
     * @return float
     */
    public static function likelihood(array $pp, array $ip, float $k): float {
        $ability = $pp['ability'];
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];

        if ($k < 1.0) {
            return 1 - self::likelihood($pp, $ip, 1.0);
        } else {
            return self::logistic($b * ($ability - $a));
        }
    }

    // Calculate the LOG Likelihood and its derivatives.

    /**
     * Calculates the LOG Likelihood for a given the person ability parameter
     *
     * @param array $pp - person ability parameter
     * @param array $ip - item parameters ('difficulty', 'discrimination')
     * @param float $k - answer category (0 or 1.0)
     * @return float - log likelihood
     */
    public static function log_likelihood(array $pp, array $ip, float $k): float {
        return log(self::likelihood($pp, $ip, $k));
    }

    /**
     * Combined person-ability score and hessian sharing one P computation.
     *
     * @param array $pp person ability parameter ('ability')
     * @param array $ip item parameters ('difficulty', 'discrimination')
     * @param float $frac response fraction
     * @return array ['jacobian' => b(k - P), 'hessian' => -b^2 W]
     */
    public static function get_ability_derivatives(array $pp, array $ip, float $frac): array {
        $b = $ip['discrimination'];
        $p = self::logistic($b * ($pp['ability'] - $ip['difficulty']));
        return ['jacobian' => $b * ($frac - $p), 'hessian' => -($b ** 2) * self::logistic_w($p)];
    }

    /**
     * Calculates the 1st derivative of the LOG Likelihood with respect to the item parameters
     *
     * @param array $pp - person ability parameter
     * @param array $ip - item parameters ('difficulty', 'discrimination')
     * @param float $k - answer category (0 or 1.0)
     * @return float - 1st derivative of log likelihood with respect to $pp
     */
    public static function log_likelihood_p(array $pp, array $ip, float $k): float {
        $ability = $pp['ability'];
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];

        // P/W form: d/dtheta log L = b (k - P), P = sigma(b (theta - a)).
        $p = self::logistic($b * ($ability - $a));
        return $b * ($k - $p);
    }

    /**
     * Calculates the 2nd derivative of the LOG Likelihood with respect to the person ability parameter
     *
     * @param array $pp - person ability parameter
     * @param array $ip - item parameters ('difficulty', 'discrimination')
     * @param float $k - answer category (0 or 1.0)
     * @return float - 2nd derivative of log likelihood with respect to $pp
     */
    public static function log_likelihood_p_p(array $pp, array $ip, float $k): float {
        $ability = $pp['ability'];
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];

        // P/W form: d^2/dtheta^2 log L = -b^2 W, independent of k.
        $p = self::logistic($b * ($ability - $a));
        return -($b ** 2) * self::logistic_w($p);
    }

    /**
     * Calculates the 1st derivative of the LOG Likelihood with respect to the item parameters
     *
     * @param array $pp - person ability parameter ('ability')
     * @param array $ip - item parameters ('difficulty', 'discrimination')
     * @param float $k - answer category (0 or 1.0)
     * @return array - jacobian vector
     */
    public static function get_log_jacobian(array $pp, array $ip, float $k): array {
        $ability = $pp['ability'];
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];

        // P/W form. x = theta - a; z = b x; P = sigma(z).
        $x = $ability - $a;
        $p = self::logistic($b * $x);

        return [
            $b * ($p - $k), // D/da log L = b (P - k).
            $x * ($k - $p), // D/db log L = x (k - P).
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
        $ability = $pp['ability'];
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];

        // P/W form. x = theta - a; P = sigma(b x); W = P(1 - P).
        $x = $ability - $a;
        $p = self::logistic($b * $x);
        $w = self::logistic_w($p);

        $haa = -($b ** 2) * $w;                            // D²/da².
        $hbb = -($x ** 2) * $w;                            // D²/db².
        $hab = $p - $itemresponse + $b * $x * $w;          // D²/da db = P - k + b x W.

        return [
            [$haa, $hab],
            [$hab, $hbb],
        ];
    }

    // Calculate the Least-Mean-Squres (LMS) approach.

    /**
     * Calculates the Least Mean Squres (residuals) for a given the person ability parameter and a given expected/observed score
     *
     * @param array $pp - person ability parameter ('ability')
     * @param array $ip - item parameters ('difficulty', 'discrimination')
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
     * @param array $ip - item parameters ('difficulty', 'discrimination')
     * @param float $frac - fraction of correct (0 ... 1.0)
     * @param float $n - number of observations
     * @return array - 1st derivative of lms with respect to $ip
     */
    public static function least_mean_squares_1st_derivative_ip(array $pp, array $ip, float $frac, float $n): array {
        $pp = $pp['ability'];
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];

        $derivative = [];

        // Pre-Calculate high frequently used exp-terms.
        $expbap = exp($b * ($a - $pp));

        $derivative[0] = $n * (2 * $b * $expbap * ($frac - 1 + $expbap * $frac)) / (1 + $expbap) ** 3; // Calculate d/da.
        $derivative[1] = $n * (2 * $expbap * ($a - $pp) * ($frac - 1 + $expbap * $frac)) / (1 + $expbap) ** 3; // Calculate d/db.

        return $derivative;
    }

    /**
     * Calculates the 2nd derivative of Least Mean Squres with respect to the item parameters
     *
     * @param array $pp - person ability parameter ('ability')
     * @param array $ip - item parameters ('difficulty', 'discrimination')
     * @param float $frac - fraction of correct (0 ... 1.0)
     * @param float $n - number of observations
     * @return array - 2nd derivative of lms with respect to $ip
     */
    public static function least_mean_squares_2nd_derivative_ip(array $pp, array $ip, float $frac, float $n): array {
        $pp = $pp['ability'];
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];

        $derivative = [[]];

        // Pre-Calculate high frequently used exp-terms.
        $expbap1 = exp($b * ($a + $pp));
        $expbap0 = exp($b * ($a - $pp));
        $expab = exp($a * $b);
        $expbp = exp($b * $pp);

        // Calculate d²2/da².
        $derivative[0][0]  = $n * (2 * $b ** 2 * $expbap1 * (-$expbp ** 2 + 2 * $expbap1 + (-$expab ** 2 + $expbp ** 2) * $frac))
            / ($expab + $expbp) ** 4;
        // Calculate d/da d/db.
        $derivative[0][1]  = $n * (2 * $expbap0 * ((1 + $a * $b - $b * $pp) * ($frac - 1) - $expbap0 ** 2 * ($b * ($a - $pp) - 1)
            * $frac + $expbap0 * (2 * $a * $b - 2 * $b * $pp + 2 * $frac - 1))) / (1 + $expbap0) ** 4;
        // Calculate d²/db².
        $derivative[1][1]  = $n * (2 * $expbap1 * ($a - $pp) ** 2 * (2 * $expbap1 + (-$expab ** 2 + $expbp ** 2)
            * $frac - $expbp ** 2)) / ($expab + $expbp) ** 4;

        // Note: Partial derivations are exchangeable, cf. Theorem of Schwarz.
        $derivative[1][0] = $derivative[0][1];

        return $derivative;
    }

    // Calculate the Log'ed Odds-Ratio Squared (LORS) approach.

    /**
     * Calculates the Log'ed Odds-Ratio Squared (residuals) for a given the person ability parameter
     * and a given expected/observed score
     *
     * @param array $pp - person ability parameter ('ability')
     * @param array $ip - item parameters ('difficulty', 'discrimination')
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
     * @param array $ip - item parameters ('difficulty', 'discrimination')
     * @param float $or - odds ratio
     * @param float $n - number of observations
     * @return array - 1st derivative
     */
    public static function lors_1st_derivative_ip(array $pp, array $ip, float $or, float $n = 1): array {
        $pp = $pp['ability'];
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];

        $derivative = [];

        $derivative[0] = $n * 2 * $b * ($b * ($a - $pp) + log($or)); // Calculate d/da.
        $derivative[1] = $n * 2 * ($a - $pp) * ($b * ($a - $pp) + log($or)); // Calculate d/db.

        return $derivative;
    }

    /**
     * Calculates the 2nd derivative of Log'ed Odds-Ratio Squared with respect to the item parameters
     *
     * @param array $pp - person ability parameter ('ability')
     * @param array $ip - item parameters ('difficulty', 'discrimination')
     * @param float $or - odds ratio
     * @param float $n - number of observations
     * @return array - 1st derivative
     */
    public static function lors_2nd_derivative_ip(array $pp, array $ip, float $or, float $n = 1): array {
        $pp = $pp['ability'];
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];

        $derivative = [[]];

        $derivative[0][0]  = $n * 2 * $b ** 2; // Calculate d²2/da².
        // d²/da db = 2n (2 b (a-theta) + log(OR)).
        $derivative[0][1] = $n * 2 * (2 * $b * ($a - $pp) + log($or));
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
    public function fisher_info(array $pp, array $ip): float {
        // I(theta) = b^2 W = b^2 P (1 - P) with P = sigma(b (theta - a)).
        $p = self::likelihood($pp, $ip, 1.0);
        return $ip['discrimination'] ** 2 * self::logistic_w($p);
    }

    // Implements handling of the Trusted Regions (TR) approach.

    /**
     * Implements a Filter Function for trusted regions in the item parameter estimation
     *
     * @param array $ip - item parameters ('difficulty', 'discrimination')
     * @return array - chunked item parameter
     */
    public static function restrict_to_trusted_region(array $ip): array {
        // Set values for difficulty parameter.
        $a = $ip['difficulty'];

        $am = 0; // Mean of difficulty.
        $as = 2; // Standard derivation of difficulty.

        // Use x times of SD as range of trusted regions.
        $atr = floatval(get_config('catmodel_raschbirnbaum', 'trusted_region_factor_sd_a'));
        $amin = floatval(get_config('catmodel_raschbirnbaum', 'trusted_region_min_a'));
        $amax = floatval(get_config('catmodel_raschbirnbaum', 'trusted_region_max_a'));

        // Set values for disrciminatory parameter.
        $b = $ip['discrimination'];

        // Placement of the discriminatory parameter.
        $bp = floatval(get_config('catmodel_raschbirnbaum', 'trusted_region_placement_b'));
        // Use x times of placement as maximal value of trusted region.
        $btr = floatval(get_config('catmodel_raschbirnbaum', 'trusted_region_factor_max_b'));

        $bmin = floatval(get_config('catmodel_raschbirnbaum', 'trusted_region_min_b'));
        $bmax = floatval(get_config('catmodel_raschbirnbaum', 'trusted_region_max_b'));

        // Test TR for difficulty.
        if ($a < max($am - ($atr * $as), $amin)) {
            $a = max($am - ($atr * $as), $amin);
        }
        if ($a > min($am + ($atr * $as), $amax)) {
            $a = min($am + ($atr * $as), $amax);
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

        return $ip;
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
        return [
            $difflabel => $param->get_difficulty(),
            $disclabel => $param->get_params_array()['discrimination'],
        ];
    }
}
