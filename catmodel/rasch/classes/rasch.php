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
 * Class rasch.
 *
 * @package    catmodel_rasch
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace catmodel_rasch;

use local_catquiz\catcalc;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_person_param_list;
use local_catquiz\local\model\model_raschmodel;
use stdClass;

/**
 * Class rasch of catmodels.
 *
 * @package    catmodel_rasch
 * @copyright 2024 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license  http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rasch extends model_raschmodel {

    /**
     * {@inheritDoc}
     *
     * @param stdClass $record
     * @return array
     */
    public static function get_parameters_from_record(stdClass $record): array {
        return [
            'difficulty' => $record->difficulty,
        ];
    }

    /**
     * Returns the name of this model.
     *
     * @return string
     */
    public function get_model_name(): string {
        return 'rasch';
    }

    // Definitions and Dimensions.

    /**
     * Definition of the number of model parameters
     *
     * @return int
     */
    public static function get_model_dim(): int {
        return 2;  // 2 parameters: person ability, difficulty
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

    /**
     * Defines names if item parameter list
     *
     * @return array of string
     */
    public static function get_parameter_names(): array {
        return ['difficulty'];
    }

    // Calculate the Likelihood.

    /**
     * Calculates the Likelihood for a given the person ability parameter
     *
     * @param array $pp - person ability parameter ('ability')
     * @param array $ip - item parameters ('difficulty')
     * @param float $k - answer category (0 or 1.0)
     *
     * @return float
     */
    public static function likelihood(array $pp, array $ip, float $k): float {
        $ability = $pp['ability'];
        $a = $ip['difficulty'];

        if ($k < 1.0) {
            return 1 - self::likelihood($pp, $ip, 1.0);
        } else {
            return 1 / (1 + exp($a - $ability));
        }
    }

    // Calculate the LOG Likelihood and its derivatives.

    /**
     * Calculates the LOG Likelihood for a given the person ability parameter
     *
     * @param array $pp - person ability parameter ('ability')
     * @param array $ip - item parameters ('difficulty')
     * @param float $k - answer category (0 or 1.0)
     *
     * @return float - log likelihood
     */
    public static function log_likelihood(array $pp, array $ip, float $k): float {
        return log(self::likelihood($pp, $ip, $k));
    }

    /**
     * Calculates the 1st derivative of the LOG Likelihood with respect to the item parameters
     *
     * @param array $pp - person ability parameter ('ability')
     * @param array $ip - item parameters ('difficulty')
     * @param float $k - answer category (0 or 1.0)
     *
     * @return float - 1st derivative of log likelihood with respect to $pp
     */
    public static function log_likelihood_p(array $pp, array $ip, float $k): float {
        $pp = $pp['ability'];
        $a = $ip['difficulty'];

        // Pre-Calculate high frequently used exp-terms.
        $expa = exp($a);
        $expp = exp($pp);

        if ($k < 1.0) {
            return -$expp / ($expa + $expp);
        } else {
            return $expa / ($expa + $expp);
        }
    }

    /**
     * Calculates the 2nd derivative of the LOG Likelihood with respect to the person ability parameter
     *
     * @param array $pp - person ability parameter
     * @param array $ip - item parameters ('difficulty')
     * @param float $k - answer category (0 or 1.0)
     *
     * @return float - 2nd derivative of log likelihood with respect to $pp
     */
    public static function log_likelihood_p_p(array $pp, array $ip, float $k): float {
        $pp = $pp['ability'];
        $a = $ip['difficulty'];

        // Pre-Calculate high frequently used exp-terms.
        $expa = exp($a);
        $expp = exp($pp);

        return - ($expa * $expp) / (($expa + $expp) ** 2);
    }

    /**
     * Calculates the 1st derivative of the LOG Likelihood with respect to the item parameters
     *
     * @param array $pp - person ability parameter ('ability')
     * @param array $ip - item parameters ('difficulty')
     * @param float $k - answer category (0 or 1.0)
     *
     * @return array - jacobian vector
     */
    public static function get_log_jacobian(array $pp, array $ip, float $k): array {
        $pp = $pp['ability'];
        $a = $ip['difficulty'];

        $jacobian = [];

        // Pre-Calculate high frequently used exp-terms.
        $expa = exp($a);
        $expp = exp($pp);

        if ($k >= 1.0) {
            $jacobian[0] = -($expa * $expp) / (($expa + $expp) * $expp); // The d/da .
        } else {
            $jacobian[0] = $expp / ($expa + $expp); // The d/da .
        }
        return $jacobian;
    }

    /**
     * Calculates the 2nd derivative of the LOG Likelihood with respect to the item parameters
     *
     * @param array $pp - person ability parameter ('ability')
     * @param array $ip - item parameters ('difficulty')
     * @param float $k - answer category (0 or 1.0)
     *
     * @return array - hessian matrx
     */
    public static function get_log_hessian(array $pp, $ip, float $k): array {
        $pp = $pp['ability'];
        $a = $ip['difficulty'];

        $hessian = [[]];

        // Pre-Calculate high frequently used exp-terms.
        $expa = exp($a);
        $expp = exp($pp);

        // 2nd derivative is equal for both k = 0 and k = 1
        $hessian[0][0] = -($expa * $expp) / ($expa + $expp) ** 2; // The d²/ da² .
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
     *
     * @return float - weighted residuals
     */
    public static function least_mean_squares(array $pp, array $ip, float $frac, float $n): float {
        return $n * ($frac - self::likelihood($pp, $ip, 1.0)) ** 2;
    }

    /**
     * Calculates the 1st derivative of Least Mean Squres with respect to the item parameters
     *
     * @param array $pp - person ability parameter ('ability')
     * @param array $ip - item parameters ('difficulty')
     * @param float $frac - fraction of correct (0 ... 1.0)
     * @param float $n - number of observations
     *
     * @return array - 1st derivative of lms with respect to $ip
     */
    public static function least_mean_squares_1st_derivative_ip(array $pp, array $ip, float $frac, float $n): array {
        $pp = $pp['ability'];
        $a = $ip['difficulty'];

        $derivative = [];

        // Pre-Calculate high frequently used exp-terms.
        $expap0 = exp($a - $pp);

        $derivative[0] = $n * (2 * $expap0 * ($frac - 1 + $expap0 * $frac)) / (1 + $expap0) ** 3; // Calculate d/da.

        return $derivative;
    }

    /**
     * Calculates the 2nd derivative of Least Mean Squres with respect to the item parameters
     *
     * @param array $pp - person ability parameter ('ability')
     * @param array $ip - item parameters ('difficulty')
     * @param float $frac - fraction of correct (0 ... 1.0)
     * @param float $n - number of observations
     *
     * @return array - 2nd derivative of lms with respect to $ip
     */
    public static function least_mean_squares_2nd_derivative_ip(array $pp, array $ip, float $frac, float $n): array {
        $pp = $pp['ability'];
        $a = $ip['difficulty'];

        $derivative = [[]];

        // Pre-Calculate high frequently used exp-terms.
        $expa = exp($a);
        $expp = exp($pp);

        $derivative[0][0] = $n * (2 * ($expa * $expp)
            * (-$expp ** 2 + 2 * ($expa * $expp)
            + (-$expa ** 2 + $expp ** 2) * $frac)) / ($expa + $expp) ** 4;

        return $derivative;
    }

    // Calculate the Log'ed Odds-Ratio Squared (LORS) approach.

    /**
     * Calculates the Log'ed Odds-Ratio Squared (residuals)
     * for a given the person ability parameter and a given expected/observed score
     *
     * @param array $pp - person ability parameter ('ability')
     * @param array $ip - item parameters ('difficulty')
     * @param float $or - odds ratio
     * @param float $n - number of observations
     *
     * @return float - weighted residuals
     */
    public static function lors_residuals(array $pp, array $ip, float $or, float $n = 1): float {
        $pp = $pp['ability'];
        $a = $ip['difficulty'];

        return $n * (log($or) + ($a - $pp)) ** 2;
    }

    /**
     * Calculates the 1st derivative of Log'ed Odds-Ratio Squared with respect to the item parameters
     *
     * @param array $pp - person ability parameter ('ability')
     * @param array $ip - item parameters ('difficulty')
     * @param float $or - odds ratio
     * @param float $n - number of observations
     *
     * @return array - 1st derivative
     */
    public static function lors_1st_derivative_ip(array $pp, array $ip, float $or, float $n = 1): array {
        $pp = $pp['ability'];
        $a = $ip['difficulty'];

        $derivative = [];

        $derivative[0] = $n * 2 * ($a - $pp + log($or)); // Calculate d/da.

        return $derivative;
    }

    /**
     * Calculates the 2nd derivative of Log'ed Odds-Ratio Squared with respect to the item parameters
     *
     * @param array $pp - person ability parameter ('ability')
     * @param array $ip - item parameters ('difficulty')
     * @param float $or - odds ratio
     * @param float $n - number of observations
     *
     * @return array - 1st derivative
     */
    public static function lors_2nd_derivative_ip(array $pp, array $ip, float $or, float $n = 1): array {
        $pp = $pp['ability'];

        $derivative = [[]];

        $derivative[0][0]  = $n * 2; // Calculate d²2/da².

        return $derivative;
    }

    /**
     * Calculate Fisher-Information.
     *
     * @param array $pp
     * @param array $ip
     *
     * @return mixed
     *
     */
    public function fisher_info(array $pp, array $ip) {
        return (self::likelihood($pp, $ip, 0) * self::likelihood($pp, $ip, 1.0));
    }

    // Implements handling of the Trusted Regions (TR) approach.

    /**
     * Implements a Filter Function for trusted regions in the item parameter estimation
     *
     * @param array $ip - item parameters ('difficulty')
     *
     * @return array - chunked item parameter
     */
    public static function restrict_to_trusted_region(array $ip): array {
        // Set values for difficulty parameter.
        $a = $ip['difficulty'];

        $am = 0; // Mean of difficulty.
        $as = 2; // Standard derivation of difficulty.

        // Use x times of SD as range of trusted regions.
        $atr = floatval(get_config('catmodel_rasch', 'trusted_region_factor_sd_a'));
        $amin = floatval(get_config('catmodel_rasch', 'trusted_region_min_a'));
        $amax = floatval(get_config('catmodel_rasch', 'trusted_region_max_a'));

        // Test TR for difficulty.
        if (($a - $am) < max(- ($atr * $as), $amin)) {
            $a = max(- ($atr * $as), $amin);
        }
        if (($a - $am) > min(($atr * $as), $amax)) {
            $a = min(($atr * $as), $amax);
        }

        $ip['difficulty'] = $a;

        return $ip;
    }

    /**
     * Calculates the 1st derivative trusted regions for item parameters
     *
     * @param array $ip - item parameters ('difficulty')
     *
     * @return array - 1st derivative of TR function with respect to $ip
     */
    public static function get_log_tr_jacobian(array $ip): array {
        // Set values for difficulty parameter.
        $am = 0; // Mean of difficulty.
        $as = 2; // Standard derivation of difficulty.

        return [
            ($am - $ip['difficulty']) / ($as ** 2), // The d/da .
        ];
    }

    /**
     * Calculates the 2nd derivative trusted regions for item parameters
     *
     * @param array $ip - item parameters ('difficulty')
     *
     * @return array - 2nd derivative of TR function with respect to $ip
     */
    public static function get_log_tr_hessian(array $ip): array {
        // Set values for difficulty parameter.
        $as = 2; // Standard derivation of difficulty.

        // Calculate d/da d/da.
        return [[ -1 / ($as ** 2) ]];
    }

    public function get_default_params(): array {
        return ['difficulty' => 0.0];
    }

    public function get_static_param_array(\local_catquiz\local\model\model_item_param $param): array {
        $label = get_string('difficulty', 'local_catquiz');
        return [
            $label => $param->get_difficulty(),
        ];
    }
}
