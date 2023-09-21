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
 * Class raschbirnbaumb.
 *
 * @package    catmodel_raschbirnbaumb
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace catmodel_raschbirnbaumb;

use local_catquiz\catcalc;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_person_param_list;
use local_catquiz\local\model\model_raschmodel;

/**
 * Class raschbirnbauma of catmodels.
 *
 * @package    catmodel_raschbirnbaumb
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class raschbirnbaumb extends model_raschmodel {

    // Definitions and Dimensions.

    /**
     * Defines names if item parameter list
     *
     * @return array of string
     */
    public static function get_parameter_names(): array {
        return ['difficulty', 'discrimination', ];
    }

    /**
     * Definition of the number of model parameters
     *
     * @return int
     */
    public static function get_model_dim(): int {
        // Adds +1 for the person ability.
        return count(self::get_parameter_names()) + 1;
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
     * @param float $pp - person ability parameter
     * @param array $ip - item parameters ('difficulty', 'discrimination')
     * @param float $k - answer category (0 or 1.0)
     * @return float
     */
    public static function likelihood($pp, array $ip, float $k): float {
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];
        if ($k < 1.0) {
            return 1 / (1 + exp($b * ($pp - $a)));
        } else {
            return 1 / (1 + exp($b * ($a - $pp)));
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

    public static function log_likelihood_p(array $pp, array $ip, float $k):float {
        $pp = $pp[0];
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];
        if ($k < 1.0) {
            return -($b * exp($b * $pp)) / (exp($a * $b) + exp($b * $pp));
        } else {
            return ($b * exp($a * $b)) / (exp($a * $b) + exp($b * $pp));
        }
    }

    public static function log_likelihood_p_p(array $pp, array $ip, float $k):float {
        $pp = $pp[0];
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];
        return -(($b ** 2 * exp($b * ($a + $pp))) / ((exp($a * $b) + exp($b * $pp)) ** 2));
    }

    public static function get_log_jacobian($pp, array $ip, float $k):array {
        if ($k < 1.0) {
            return [
                ($ip['discrimination'] * exp($ip['discrimination']
                    * $pp)) / (exp($ip['difficulty'] * $ip['discrimination'])
                    + exp($ip['discrimination'] * $pp)), // Calculates d/da.
                (exp($ip['discrimination'] * $pp) * ( $ip['difficulty'] - $pp))
                    / (exp($ip['difficulty'] * $ip['discrimination'])
                    + exp($ip['discrimination'] * $pp)) // Calculates d/db.
                ];
        } else {
            return [
                -($ip['discrimination'] * exp( $ip['difficulty']
                    * $ip['discrimination'])) / (exp( $ip['difficulty'] * $ip['discrimination'])
                    + exp($ip['discrimination'] * $pp)), // Calculates d/da.
                (exp( $ip['difficulty'] * $ip['discrimination'])
                    * ($pp - $ip['difficulty'])) / (exp($ip['difficulty'] * $ip['discrimination'])
                    + exp($ip['discrimination'] * $pp)) // Calculates d/db.
                ];
        }
    }

    public static function get_log_hessian($pp, array $ip, float $itemresponse): array {
        if ($itemresponse >= 1.0) {
            return [[
                (-($ip['discrimination'] ** 2 * exp($ip['discrimination']
                    * ($ip['difficulty'] + $pp))) / ((exp($ip['difficulty']
                    * $ip['discrimination']) + exp($ip['discrimination']
                    * $pp)) ** 2)), // Calculates d²/da².
                (-(exp($ip['difficulty'] * $ip['discrimination'])
                    * (exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)
                    * (1 + $ip['discrimination'] * ($ip['difficulty'] - $pp))))
                    / ((exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination']
                    * $pp)) ** 2)) // Calculates d/a d/db.
                ], [
                (-(exp($ip['difficulty'] * $ip['discrimination'])
                    * (exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)
                    * (1 + $ip['discrimination'] * ($ip['difficulty'] - $pp))))
                    / ((exp($ip['difficulty'] * $ip['discrimination'])
                    + exp($ip['discrimination'] * $pp)) ** 2)), // Calculates d/a d/db.
                (-(exp($ip['discrimination'] * ($ip['difficulty'] + $pp))
                    * ($ip['difficulty'] - $pp) ** 2) / ((exp($ip['difficulty'] * $ip['discrimination'])
                    + exp($ip['discrimination'] * $pp)) ** 2)) // Calculates d²/db².
            ]];
        } else {
            return [[
                -($ip['discrimination'] ** 2 * exp($ip['discrimination']
                    * ($ip['difficulty'] - $pp))) / (1 + exp($ip['discrimination']
                    * ($ip['difficulty'] - $pp))) ** 2, // Calculates d²/da².
                (1 + exp($ip['discrimination'] * ($ip['difficulty'] - $pp))
                    * (1 + $ip['discrimination'] * ($pp - $ip['difficulty'])))
                    / (1 + exp($ip['discrimination'] * ($ip['difficulty'] - $pp)))
                    ** 2, // Calculates d/da d/db.
            ], [
                (1 + exp($ip['discrimination'] * ($ip['difficulty'] - $pp))
                    * (1 + $ip['discrimination'] * ($pp - $ip['difficulty'])))
                    / (1 + exp($ip['discrimination'] * ($ip['difficulty'] - $pp)))
                    ** 2, // Calculates d/da d/db.
                -(exp($ip['discrimination'] * ($ip['difficulty'] - $pp))
                    * ($ip['difficulty'] - $pp) ** 2) / (1 + exp($ip['discrimination']
                    * ($ip['difficulty'] - $pp))) ** 2 // Calculates d²/db².
            ]];
        }
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
        $derivative = [0, 0];
        $a = $ip['difficulty']; $b = $ip['discrimination'];

        foreach ($pp as $key => $ability) {
            if (!(is_numeric($n[$key]) && is_numeric($k[$key]))) {
                continue;
            }

            $derivative[0] += $n[$key] * (2 * $b * exp($b * ($a - $ability))
                * ($k[$key] - 1 + exp($b * ($a - $ability)) * $k[$key])) / (1 + exp($b
                * ($a - $ability))) ** 3; // Calculate d/da.
            $derivative[1] += $n[$key] * (2 * exp($b * ($a - $ability))
                * ($a - $ability) * ($k[$key] - 1 + exp($b * ($a - $ability)) * $k[$key]))
                / (1 + exp($b * ($a - $ability))) ** 3; // Calculate d/db.
        }
        return $derivative;
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
        $derivative = [[0, 0], [0, 0]];
        $a = $ip['difficulty']; $b = $ip['discrimination'];

        foreach ($pp as $key => $ability) {
            if (!(is_numeric($n[$key]) && is_numeric($k[$key]))) {
                continue;
            }

            $derivative[0][0]  += $n[$key] * (2 * $b ** 2 * exp($b * ($a + $ability))
                * (-exp(2 * $b * $ability) + 2 * exp($b * ($a + $ability)) + (-exp(2 * $a * $b)
                + exp(2 * $b * $ability)) * $k[$key])) / (exp($a * $b)
                + exp($b * $ability)) ** 4; // Calculate d²2/da².
            $derivative[0][1]  += $n[$key] * (2 * exp($b * ($a - $ability))
                * ((1 + $a * $b - $b * $ability) * ($k[$key] - 1) - exp(2 * $b
                * ($a - $ability)) * ($b * ($a - $ability) - 1) * $k[$key] + exp($b * ($a - $ability))
                * (2 * $a * $b - 2 * $b * $ability + 2 * $k[$key] - 1)))
                / (1 + exp($b * ($a - $ability))) ** 4; // Calculate d/da d/db.
            $derivative[1][1]  += $n[$key] * (2 * exp($b * ($a + $ability))
                * ($a - $ability) ** 2 * (2 * exp($b * ($a + $ability)) + (-exp(2 * $a * $b) + exp(2 * $b * $ability))
                * $k[$key] - exp(2 * $b * $ability))) / (exp($a * $b) + exp($b * $ability)) ** 4; // Calculate d²/db².
        }
        // Note: Partial derivations are exchangeible, cf. Theorem of Schwarz.
        $derivative[1][0] = $derivative[0][1];

        return $derivative;
    }

    // Calculate Fisher-Information.

    /**
     * Calculates the Fisher Information for a given person ability parameter
     *
     * @param float $pp
     * @param array $ip
     * @return float
     */
    public static function fisher_info(float $pp, array $ip): float {
        return ($ip['discrimination'] ** 2 * self::likelihood($pp, $ip, 0) * self::likelihood($pp, $ip, 1.0));
    }

    // Implements handling of the Trusted Regions (TR) approach.

    /**
     * Implements a Filter Function for trusted regions in the item parameter estimation
     *
     * @param array $parameters
     *
     * @return array
     *
     */
    public static function restrict_to_trusted_region(array $parameters):array {
        // Set values for difficulty parameter.
        $a = $parameters['difficulty'];

        $am = 0; // Mean of difficulty.
        $as = 2; // Standard derivation of difficulty.

        // Use x times of SD as range of trusted regions.
        $atr = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_factor_sd_a'));
        $amin = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_min_a'));
        $amax = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_max_a'));

        // Set values for disrciminatory parameter.
        $b = $parameters['discrimination'];

        // Placement of the discriminatory parameter.
        $bp = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_placement_b'));
        // Slope of the discriminatory parameter.
        $bs = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_slope_b'));
        // Use x times of placement as maximal value of trusted region.
        $btr = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_factor_max_b'));

        $bmin = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_min_b'));
        $bmax = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_max_b'));

        // Test TR for difficulty.
        if (($a - $am) < max(-($atr * $as), $amin)) {
            $a = max(-($atr * $as), $amin);
        }
        if (($a - $am) > min(($atr * $as), $amax)) {
            $a = min(($atr * $as), $amax);
        }

        $parameters['difficulty'] = $a;

        // Test TR for discriminatory.
        if ($b < $bmin) {
            $b = $bmin;
        }
        if ($b > min(($btr * $bp), $bmax)) {
            $b = min(($btr * $bp), $bmax);
        }

        $parameters['discrimination'] = $b;

        return $parameters;
    }

    /**
     * Calculates the 1st derivative trusted regions for item parameters
     *
     * @return array
     */
    public static function get_log_tr_jacobian():array {
        // Set values for difficulty parameter.
        $am = 0; // Mean of difficulty.
        $as = 2; // Standard derivation of difficulty.

        // Placement of the discriminatory parameter.
        $bp = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_placement_b'));
        // Slope of the discriminatory parameter.
        $bs = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_slope_b'));

        return [
            fn ($x) => (($am - $x['difficulty']) / ($as ** 2)), // Calculates d/da.
            // Calculates d/db.
            fn ($x) => (-($bs * exp($bs * $x['discrimination'])) / (exp($bs * $bp) + exp($bs * $x['discrimination'])))
        ];
    }

    /**
     * Calculates the 2nd derivative trusted regions for item parameters
     *
     * @return array
     */
    public static function get_log_tr_hessian():array {
        // Set values for difficulty parameter.
        $am = 0; // Mean of difficulty.
        $as = 2; // Standard derivation of difficulty.

        // Placement of the discriminatory parameter.
        $bp = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_placement_b'));
        // Slope of the discriminatory parameter.
        $bs = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_slope_b'));

        return [[
            fn ($x) => (-1 / ($as ** 2)), // Calculates d²/da².
            fn ($x) => (0) // Calculates d/da d/db.
        ], [
            fn ($x) => (0), // Calculates d/da d/db.
            fn ($x) => (-($bs ** 2 * exp($bs * ($bp + $x['discrimination']))) / (exp($bs * $bp) + exp($bs * $x['discrimination'])) ** 2) // Calculates d²/db².
        ]];
    }

}
