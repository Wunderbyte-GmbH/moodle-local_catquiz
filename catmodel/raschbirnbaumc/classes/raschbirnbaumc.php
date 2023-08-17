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
    public static function get_parameter_names():array {
        return ['difficulty', 'discrimination', 'guessing', ];
    }

    /**
     * Definition of the number of model parameters
     *
     * @return int
     */
    public static function get_model_dim():int {
        return count (self::get_parameter_names());
    }

    /**
     * Initiate item parameter list
     *
     * @return model_item_param_list
     */
    public static function get_item_parameters():model_item_param_list {
        // TODO implement.
        return new model_item_param_list();
    }

    /**
     * Initiate person ability parameter list
     *
     * @return model_person_param_list
     */
    public static function get_person_abilities():model_person_param_list {
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
    public function calculate_params($itemresponse):array {
        return catcalc::estimate_item_params($itemresponse, $this);
    }

    // Calculate the Likelihood.

    /**
     * Calculates the Likelihood for a given the person ability parameter
     *
     * @param float $pp - person ability parameter
     * @param array $ip - item parameters ('difficulty')
     * @param float $k - answer category (0 or 1.0)
     * @return float
     */
    public static function likelihood($pp, array $ip, float $k):float {
        $a = $ip['difficulty']; $b = $ip['discrimination']; $c = $ip['guessing'];

        if ($k < 1.0) {
            return 1 - self::likelihood($pp, $ip, 1.0);
        } else {
            return $c + (1 - $c) / (1 + exp($b * ($a - $pp)));
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
    public static function log_likelihood($pp, array $ip, float $k):float {
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
    public static function log_likelihood_p($pp, array $ip, float $k):float {
        $a = $ip['difficulty']; $b = $ip['discrimination']; $c = $ip['guessing'];

        if ($k < 1.0) {
            return -(($b * exp($b * $pp)) / (exp($a * $b) + exp($b * $pp)));
        } else {
            return -(($b * (-1 + $c) * exp($b * ($a + $pp))) / ((exp($a * $b) + exp($b * $pp)) * ($c * exp($a * $b) + exp($b * $pp))));
        }
    }

    /**
     * Calculates the 2nd derivative of the LOG Likelihood with respect to the person ability parameter
     *
     * @param float $pp - person ability parameter
     * @param array $ip - item parameters ('difficulty')
     * @param float $k - answer category (0 or 1.0)
     * @return float
     */
    public static function log_likelihood_p_p($pp, array $ip, float $k):float {
        $a = $ip['difficulty']; $b = $ip['discrimination']; $c = $ip['guessing'];

        if ($k < 1.0) {
            return -(($b ** 2 * exp($b * ($a + $pp))) / (exp($a * $b) + exp($b * $pp)) ** 2);
        } else {
            return ($b ** 2 * ($c - 1) * exp( $b * ($pp - $a)) * (exp(2 * $b * ($pp - $a)) - $c)) / ((1 + exp($b * ( $pp - $a))) ** 2 * ($c + exp($b * ($pp - $a))) ** 2);
        }
    }

    /**
     * Calculates the 1st derivative of the LOG Likelihood with respect to the item parameters
     *
     * @param float $pp - person ability parameter
     * @param float $k - answer category (0 or 1.0)
     * @return array of function($ip)
     */
    public static function get_log_jacobian($pp, float $k):array {
        if ($k >= 1.0) {
            return [
                fn ($ip) => ($ip['discrimination'] * ($ip['guessing'] - 1) * exp($ip['discrimination'] * ($ip['difficulty'] + $pp))) / ((exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)) * ($ip['guessing'] * exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp))), // Calculate d/da.
                fn ($ip) => (($ip['guessing'] - 1) * exp($ip['discrimination'] * ($ip['difficulty'] + $pp)) * ($ip['difficulty'] - $pp)) / ((exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)) * ($ip['guessing'] * exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp))), // Calculate d/db.
                fn ($ip) => exp($ip['difficulty'] * $ip['discrimination']) / ($ip['guessing'] * exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)) // Calculate d/dc.
            ];
        } else {
            return [
                fn ($ip) => $ip['discrimination'] / (1 + exp($ip['discrimination'] * ($ip['difficulty'] - $pp))), // Calculate d/da.
                fn ($ip) => ($ip['difficulty'] - $pp) / (1 + exp($ip['discrimination'] * ($ip['difficulty'] - $pp))), // Calculate d/db.
                fn ($ip) => 1 / ($ip['guessing'] - 1) // Calculate d/dc.
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
    public static function get_log_hessian($pp, float $k):array {

        if ($k >= 1.0) {
            return [[
                fn ($ip) => -($ip['discrimination'] ** 2 * ($ip['guessing'] - 1) * exp($ip['discrimination'] * ($ip['difficulty'] + $pp)) * ($ip['guessing'] * exp(2 * $ip['difficulty'] * $ip['discrimination']) - exp(2 * $ip['discrimination'] * $pp))) / ((exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)) ** 2 * ($ip['guessing'] * exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)) ** 2), // Calculate d²/ da².
                fn ($ip) => (($ip['guessing'] - 1) * exp($ip['discrimination'] * ($ip['difficulty'] + $pp)) * (exp($ip['discrimination'] * ($ip['difficulty'] + $pp)) + exp(2 * $ip['discrimination'] * $pp) * (1 + $ip['difficulty'] * $ip['discrimination'] - $ip['discrimination'] * $pp) + $ip['guessing'] * (exp($ip['discrimination'] * ($ip['difficulty'] + $pp)) + exp(2 * $ip['difficulty'] * $ip['discrimination']) * (1 - $ip['difficulty'] * $ip['discrimination'] + $ip['discrimination'] * $pp)))) / ((exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)) ** 2 * ($ip['guessing'] * exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)) ** 2), // Calculate d/da d/db.
                fn ($ip) => ($ip['discrimination'] * exp($ip['discrimination'] * ($ip['difficulty'] + $pp))) / ($ip['guessing'] * exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)) ** 2 // d/da d/dc
            ], [
                fn ($ip) => (($ip['guessing'] - 1) * exp($ip['discrimination'] * ($ip['difficulty'] + $pp)) * (exp($ip['discrimination'] * ($ip['difficulty'] + $pp)) + exp(2 * $ip['discrimination'] * $pp) * (1 + $ip['difficulty'] * $ip['discrimination'] - $ip['discrimination'] * $pp) + $ip['guessing'] * (exp($ip['discrimination'] * ($ip['difficulty'] + $pp)) + exp(2 * $ip['difficulty'] * $ip['discrimination']) * (1 - $ip['difficulty'] * $ip['discrimination'] + $ip['discrimination'] * $pp)))) / ((exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)) ** 2 * ($ip['guessing'] * exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)) ** 2), // Calculate d/da d/db.
                fn ($ip) => -(($ip['guessing'] - 1) * exp($ip['discrimination'] * ($ip['difficulty'] - $pp)) * ($ip['guessing'] * exp(2 * $ip['discrimination'] * ($ip['difficulty'] - $pp)) - 1) * ($ip['difficulty'] - $pp) ** 2) / (((1 + exp($ip['discrimination'] * ($ip['difficulty'] - $pp))) * (1 + $ip['guessing'] * exp($ip['discrimination'] * ($ip['difficulty'] - $pp)))) ** 2), // Calculate d²/db².
                fn ($ip) => (exp($ip['discrimination'] * ($ip['difficulty'] + $pp)) * ($ip['difficulty'] - $pp)) / ($ip['guessing'] * exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)) ** 2 // Calculate d/db d/dc.
            ], [
                fn ($ip) => ($ip['discrimination'] * exp($ip['discrimination'] * ($ip['difficulty'] + $pp))) / ($ip['guessing'] * exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)) ** 2, // Calculate d/da d/dc.
                fn ($ip) => (exp($ip['discrimination'] * ($ip['difficulty'] + $pp)) * ($ip['difficulty'] - $pp)) / ($ip['guessing'] * exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)) ** 2, // Calculate d/db d/dc.
                fn ($ip) => -exp(2 * $ip['difficulty'] * $ip['discrimination']) / ($ip['guessing'] * exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)) ** 2 // Calculate d²/dc².
            ]];
        } else {
            return [[
                fn ($ip) => -($ip['discrimination'] ** 2 * exp($ip['discrimination'] * ($ip['difficulty'] - $pp))) / (1 + exp($ip['discrimination'] * ($ip['difficulty'] - $pp))) ** 2, // Calculate d²/da².
                fn ($ip) => (exp($ip['discrimination'] * ($ip['difficulty'] - $pp)) * ($ip['discrimination'] * ($pp - $ip['difficulty']) + 1) + 1) / (exp($ip['discrimination'] * ($ip['difficulty'] - $pp)) + 1) ** 2, // Calculate d/da d/db.
                fn ($ip) => 0 // Calculate d/da d/dc.
            ], [
                fn ($ip) => (exp($ip['discrimination'] * ($ip['difficulty'] - $pp)) * ($ip['discrimination'] * ($pp - $ip['difficulty']) + 1) + 1) / (exp($ip['discrimination'] * ($ip['difficulty'] - $pp)) + 1) ** 2, // Calculate d/da d/db.
                fn ($ip) => -(exp($ip['discrimination'] * ($ip['difficulty'] - $pp)) * ($ip['difficulty'] - $pp) ** 2) / (1 + exp($ip['discrimination'] * ($ip['difficulty'] - $pp))) ** 2, // Calculate .d²/db²
                fn ($ip) => 0 // Calculate d/db d/dc.
            ], [
                fn ($ip) => 0, // Calculate d/da d/dc.
                fn ($ip) => 0, // Calculate d/db d/dc.
                fn ($ip) => -1 / ($ip['guessing'] - 1) ** 2 // Calculate d²/dc².
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
    public static function least_mean_squares(array $pp, array $ip, array $k, array $n):float {
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
    public static function least_mean_squares_1st_derivative_ip(array $pp, array $ip, array $k, array $n) {
        $derivative = [0, 0, 0];
        $a = $ip['difficulty']; $b = $ip['discrimination']; $c = $ip['guessing'];

        foreach ($pp as $key => $ability) {
            if (!(is_numeric($n[$key]) && is_numeric($k[$key]))) {
                continue;
            }

            $derivative[0] += $n[$key] * (-(2 * $b * (1 - $c) * exp($b * ($a - $ability)))
                / (1 + exp($b * ($a - $ability)) - $k[$key]) ** 3); // Calculate d/da.
            $derivative[1] += $n[$key] * (-(2 * (1 - $c) * exp($b * ($a - $ability))
                * ($c + (1 - $c) / (1 + exp($b * ($a - $ability))) - $k[$key]) * ($a - $ability))
                / (1 + exp($b * ($a - $ability))) ** 2); // Calculate d/db.
            $derivative[2] += $n[$key] * 2 * (1 - 1 / (1 + exp($b * ($a - $ability)))) * ($c + (1 - $c)
                / (1 + exp($b * ($a - $ability))) - $k[$key]); // Calculate d/dc.
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
    public static function least_mean_squares_2nd_derivative_ip(array $pp, array $ip, array $k, array $n) {
        $derivative = [[0, 0, 0], [0, 0, 0], [0, 0, 0]];
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];
        $c = $ip['guessing'];

        foreach ($pp as $key => $ability) {
            if (!(is_numeric($n[$key]) && is_numeric($k[$key]))) {
                continue;
            }

            $derivative[0][0]  += $n[$key] * (-(2 * $b ** 2 * (1 - $c) * exp($b * ($a - $ability)) * ((1 - $c)
                / (exp($b * ($a - $ability)) + 1) + $c - $k[$key])) / (exp($b * ($a - $ability)) + 1) ** 2
                + (4 * $b ** 2 * (1 - $c) * exp(2 * $b * ($a - $ability)) * ((1 - $c) / (exp($b * ($a - $ability)) + 1) + $c - $k[$key]))
                / (exp($b * ($a - $ability)) + 1) ** 3 + (2 * $b ** 2 * (1 - $c) ** 2 * exp(2 * $b * ($a - $ability)))
                / (exp($b * ($a - $ability)) + 1) ** 4); // Calculate d²/da².
            $derivative[0][1]  += $n[$key] * (-(2 * (1 - $c) * exp($b * ($a - $ability)) * ((1 - $c) / (exp($b * ($a - $ability)) + 1)
                + $c - $k[$key])) / (exp($b * ($a - $ability)) + 1) ** 2 - (2 * $b * (1 - $c) * ($a - $ability)
                * exp($b * ($a - $ability)) * ((1 - $c) / (exp($b * ($a - $ability)) + 1) + $c - $k[$key])) / (exp($b * ($a - $ability)) + 1)
                ** 2 + (4 * $b * (1 - $c) * ($a - $ability) * exp(2 * $b * ($a - $ability)) * ((1 - $c) / (exp($b * ($a - $ability)) + 1)
                + $c - $k[$key])) / (exp($b * ($a - $ability)) + 1) ** 3 + (2 * $b * (1 - $c) ** 2 * ($a - $ability)
                * exp(2 * $b * ($a - $ability))) / (exp($b * ($a - $ability)) + 1) ** 4); // Calculate d/da d/db.
            $derivative[0][2]  += $n[$key] * (2 * $b * exp($b * ($a - $ability)) * ((2 * $c - $k[$key] - 1)
                * exp($b * ($a - $ability)) - $k[$key] + 1)) / (exp($b * ($a - $ability)) + 1) ** 3; // Calculate d/da d/dc.
            $derivative[1][1]  += $n[$key] * (2 * ($a - $ability) * exp($b * ($a - $ability)) * ((1 - $c)
                / (exp($b * ($a - $ability)) + 1) + $c - $k[$key])) / (exp($b * ($a - $ability)) + 1) ** 2
                - (2 * (1 - $c) * ($a - $ability) * exp($b * ($a - $ability)) * (1 - 1 / (exp($b * ($a - $ability)) + 1)))
                / (exp($b * ($a - $ability)) + 1) ** 2; // Calculate d²/db².
            $derivative[1][2]  += $n[$key] * (2 * ($a - $ability) * exp($b * ($a - $ability)) * ((2 * $c - $k[$key] - 1)
                * exp($b * ($a - $ability)) - $k[$key] + 1)) / (exp($b * ($a - $ability)) + 1) ** 3; // Calculate d/db d/dc.
            $derivative[2][2]  += $n[$key] * (2 * exp(2 * $a * $b)) / (exp($a * $b) + exp($b * $ability)) ** 2; // Calculate d²/dc².
        }

        // Note: Partial derivations are exchangeible, cf. Theorem of Schwarz.
        $derivative[1][0] = $derivative[0][1];
        $derivative[2][0] = $derivative[0][2];
        $derivative[2][1] = $derivative[1][2];

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
    public static function fisher_info($pp, $ip) {
        return $ip['difficulty'] ** 2 * (1 - $ip['guessing']) * self::likelihood($pp, $ip, 1.0) * (self::likelihood($pp, $ip, 0.0));
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
     * @return array
     */
    public static function get_log_tr_jacobian(): array {
        // Set values for difficulty parameter.
        $am = 0; // Mean of difficulty.
        $as = 2; // Standard derivation of difficulty.

        // Placement of the discriminatory parameter.
        $bp = floatval(get_config('catmodel_raschbirnbaumc', 'trusted_region_placement_b'));
        // Slope of the discriminatory parameter.
        $bs = floatval(get_config('catmodel_raschbirnbaumc', 'trusted_region_slope_b'));

        return [
            fn ($ip) => (($am - $ip['difficulty']) / ($as ** 2)), // Calculate d/da.
            // Calculate d/db.
            fn ($ip) => (-($bs * exp($bs * $ip['discrimination'])) / (exp($bs * $bp) + exp($bs * $ip['discrimination']))),
            fn ($ip) => (0) // Calculate d/dc.
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

        // Placement of the discriminatory parameter.
        $bp = floatval(get_config('catmodel_raschbirnbaumc', 'trusted_region_placement_b'));
        // Slope of the discriminatory parameter.
        $bs = floatval(get_config('catmodel_raschbirnbaumc', 'trusted_region_slope_b'));

        return [[
            fn ($ip) => (-1 / ($as ** 2)), // Calculate d²/da².
            fn ($ip) => (0), // Calculate d/da d/db.
            fn ($ip) => (0) // Calculate d/da d/dc.
        ], [
            fn ($ip) => (0), // The d/da d/db.
            fn ($ip) => (-($bs ** 2 * exp($bs * ($bp + $ip['discrimination']))) / (exp($bs * $bp) + exp($bs * $ip['discrimination'])) ** 2), // d²/db²
            fn ($ip) => (0) // Calculate d/db d/dc.
        ], [
            fn ($ip) => (0), // Calculate d/da d/dc.
            fn ($ip) => (0), // Calculate d/db d/dc.
            fn ($ip) => (0)// Calculate d²/dc².
        ]];
    }
}
