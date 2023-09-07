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
 * Class raschbirnbauma.
 *
 * @package    catmodel_raschbirnbauma
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace catmodel_raschbirnbauma;

use local_catquiz\catcalc;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_person_param_list;
use local_catquiz\local\model\model_raschmodel;

/**
 * Class raschbirnbauma of catmodels.
 *
 * @package    catmodel_raschbirnbauma
 * @copyright 2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license  http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class raschbirnbauma extends model_raschmodel {


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

    /**
     * Defines names if item parameter list
     *
     * @return array of string
     */
    public static function get_parameter_names(): array {
        return ['difficulty', ];
    }

    // Calculate the Likelihood.

    /**
     * Calculates the Likelihood for a given the person ability parameter
     *
     * @param float $pp - person ability parameter
     * @param array $ip - item parameters ('difficulty')
     * @param float $k - answer category (0 or 1.0)
     * @return float
     *
     */
    public static function likelihood($pp, array $ip, float $k): float {
        $a = $ip['difficulty'];
        if ($k < 1.0) {
            return 1 / (1 + exp($pp - $a));
        } else {
            return 1 / (1 + exp($a - $pp));
        }
    }
    // Calculate the LOG Likelihood and its derivatives.

    /**
     * Calculates the LOG Likelihood for a given the person ability parameter
     *
     * @param float $pp - person ability parameter
     * @param array $ip - item parameters ('difficulty')
     * @param float $k - answer category (0 or 1.0)
     * @return float
     */
    public static function log_likelihood($pp, array $ip, float $k): float {
        return log(self::likelihood($pp, $ip, $k));
    }

    /**
     * Calculates the 1st derivative of the LOG Likelihood with respect to the person ability parameter
     *
     * @param float $pp - person ability parameter
     * @param array $ip - item parameters ('difficulty')
     * @param float $k - answer category (0 or 1.0)
     * @return float
     */
    public static function log_likelihood_p($pp, array $ip, float $k): float {
        $a = $ip['difficulty'];
        if ($k < 1.0) {
            return -exp($pp) / (exp($a) + exp($pp));
        } else {
            return exp($a) / (exp($a) + exp($pp));
        }
    }

    /**
     * Calculates the 2nd derivative of the LOG Likelihood with respect to the person ability parameter
     *
     * @param array<float> $pp - person ability parameter
     * @param array $ip - item parameters ('difficulty')
     * @param float $k - answer category (0 or 1.0)
     * @return float
     */
    public static function log_likelihood_p_p($pp, array $ip, float $k): float {
        $a = $ip['difficulty'];
        return - (exp($a + $pp[0]) / ((exp($a) + exp($pp[0])) ** 2));
    }

    /**
     * Calculates the 1st derivative of the LOG Likelihood with respect to the item parameters
     *
     * @param float $pp - person ability parameter
     * @param float $k - answer category (0 or 1.0)
     * @return mixed of function($ip)
     */
    public static function get_log_jacobian($pp, float $k): array {
        if ($k >= 1.0) {
            return [
                fn ($ip) => (-exp($ip['difficulty'] + $pp) / ((exp($ip['difficulty']) + exp($pp)) * (exp($pp)))) // The d/da .
            ];
        } else {
            return [
                fn ($ip) => (exp($pp) / (exp($ip['difficulty']) + exp($pp))) // The d/da .
            ];
        }
    }

    /**
     * Calculates the 2nd derivative of the LOG Likelihood with respect to the item parameters
     *
     * @param float $pp - person ability parameter
     * @param float $k - answer category (0 or 1.0)
     * @return array of function($ip)
     */
    public static function get_log_hessian($pp, float $k): array {
        // 2nd derivative is equal for both k = 0 and k = 1
        return [[
            fn ($ip) => -exp($ip['difficulty'] + $pp) / (exp($ip['difficulty']) + exp($pp)) ** 2 // The d²/ da² .
        ]];
    }

    // Calculate the Least-Mean-Squres (LMS) approach.
    /**
     * Calculates the Least Mean Squres (residuals) for a given the person ability parameter and a given expected/observed score
     *
     * @param array $pp - person ability parameter
     * @param array $ip - item parameters ('difficulty', 'discrimination', 'guessing')
     * @param array $k - fraction of correct (0 ... 1.0)
     * @param array $n - number of observations
     * @return float - weighted residuals
     */
    public static function least_mean_squares(array $pp, array $ip, array $k, array $n): float {
        $lmsresiduals = 0;
        $numbertotal = 0;

        foreach ($pp as $key => $ability) {
            if (!(is_float($n[$key]) && is_float($k[$key]))) {
                continue;
            }

            $lmsresiduals += $n[$key] * ($k[$key] - self::likelihood($ability, $ip, 1.0)) ** 2;
            $numbertotal += $n[$key];
        }
        return (($numbertotal > 0) ? ($lmsresiduals / $numbertotal) : (0));
    }

    /**
     * Calculates the 1st derivative of Least Mean Squres with respect to the item parameters
     *
     * @param array $pp - person ability parameter
     * @param array $ip - item parameters ('difficulty', 'discrimination')
     * @param array $k - fraction of correct (0 ... 1.0)
     * @param array $n - number of observations
     * @return array - 1st derivative
     */
    public static function least_mean_squares_1st_derivative_ip(array $pp, array $ip, array $k, array $n): array {
        $derivative = 0;
        $a = $ip['difficulty'];

        foreach ($pp as $key => $ability) {
            if (!(is_numeric($n[$key]) && is_numeric($k[$key]))) {
                continue;
            }

            $derivative += $n[$key] * (2 * exp($a + $ability) * (exp($a)
                * $k[$key] + exp($ability) * ($k[$key]) - 1)) / (exp($a) + exp($ability)) ** 3; // Calculate d/da.
        }
        return [$derivative];
    }

    /**
     * Calculates the 2nd derivative of Least Mean Squres with respect to the item parameters
     *
     * @param array $pp - person ability parameter
     * @param array $ip - item parameters ('difficulty', 'discrimination')
     * @param array $k - fraction of correct (0 ... 1.0)
     * @param array $n - number of observations
     * @return array - 1st derivative
     */
    public static function least_mean_squares_2nd_derivative_ip(array $pp, array $ip, array $k, array $n): array {
        $derivative = [[0]];
        $a = $ip['difficulty'];

        foreach ($pp as $key => $ability) {
            if (!(is_numeric($n[$key]) && is_numeric($k[$key]))) {
                continue;
            }

            // Calculate d²/da².
            $derivative[0][0]  += $n[$key] * (2 * exp($a + $ability) *
                                (2 * exp($a + $ability) + exp(2 * $ability) * (-1 + $k[$key]) - exp(2 * $a) * $k[$key]))
                                / (exp($a) + exp($ability)) ** 4;
        }
        return $derivative;
    }

    // Calculate Fisher-Information.

    /**
     * Calculates the Fisher Information for a given person ability parameter.
     *
     * @param float $pp
     * @param array $ip
     * @return float
     */
    public static function fisher_info(float $pp, array $ip) {
        return (self::likelihood($pp, $ip, 0) * self::likelihood($pp, $ip, 1.0));
    }

    // Implements handling of the Trusted Regions (TR) approach.

    /**
     * Implements a Filter Function for trusted regions in the item parameter estimation
     *
     * @param array $ip
     * return array
     */
    public static function restrict_to_trusted_region(array $ip): array {
        // Set values for difficulty parameter.
        $a = $ip['difficulty'];

        $am = 0; // Mean of difficulty.
        $as = 2; // Standard derivation of difficulty.

        // Use x times of SD as range of trusted regions.
        $atr = floatval(get_config('catmodel_raschbirnbauma', 'trusted_region_factor_sd_a'));
        $amin = floatval(get_config('catmodel_raschbirnbauma', 'trusted_region_min_a'));
        $amax = floatval(get_config('catmodel_raschbirnbauma', 'trusted_region_max_a'));

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
     * @return array
     */
    public static function get_log_tr_jacobian(): array {
        // Set values for difficulty parameter.
        $am = 0; // Mean of difficulty.
        $as = 2; // Standard derivation of difficulty.

        $atr = floatval(get_config('catmodel_raschbirnbauma', 'trusted_region_factor_sd_a'));

        return [
            fn ($ip) => (($am - $ip['difficulty']) / ($as ** 2)) // The d/da .
        ];
    }

    /**
     * Calculates the 2nd derivative trusted regions for item parameters
     *
     * @return array
     */
    public static function get_log_tr_hessian(): array {
        // Set values for difficulty parameter.
        $am = 0; // Mean of difficulty.
        $as = 2; // Standard derivation of difficulty.

        return [[
            fn ($x) => (-1 / ($as ** 2)) // Calculate d/da d/da.
        ]];
    }
}
