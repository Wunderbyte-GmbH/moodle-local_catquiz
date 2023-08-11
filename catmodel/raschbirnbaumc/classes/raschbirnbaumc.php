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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Event factory interface.
 *
 * @package  local_catquiz
 * @copyright 2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license  http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace catmodel_raschbirnbauma;

use local_catquiz\catcalc;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_person_param_list;
use local_catquiz\local\model\model_raschmodel;

defined('MOODLE_INTERNAL') || die();

/**
 * @copyright 2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license  http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class raschbirnbaumc extends model_raschmodel {


    // Definitions and Dimensions //

    /**
     * Defines names if item parameter list
     *
     * @return array of string
     */
    public static function get_parameter_names(): array {
        return ['difficulty', 'discrimination', 'guessing', ];
    }
    /**
     * Definition of the number of model parameters
     *
     * @return int
     */
    public static function get_model_dim(): int {
        return count (self::get_parameter_names()); // 4 parameters: person ability, difficulty, discrimination, guessing
    }

    /**
     * Initiate item parameter list
     *
     * @return model_item_param_list
     */
    public static function get_item_parameters(): model_item_param_list {
        // TODO implement
        return new model_item_param_list();
    }

    /**
     * Initiate person ability parameter list
     *
     * @return model_person_param_list
     */
    public static function get_person_abilities(): model_person_param_list {
        // TODO implement
        return new model_person_param_list();
    }

    /**
     * Estimate item parameters
     *
     * @param float
     * @return model_person_param_list
     */
    public function calculate_params($itemresponse): array {
        return catcalc::estimate_item_params($itemresponse, $this);
    }

    // Calculate the Likelihood //

    /**
     * Calculates the Likelihood for a given the person ability parameter
     *
     * @param float $pp - person ability parameter
     * @param array<float> $ip - item parameters ('difficulty')
     * @param float $k - answer category (0 or 1.0)
     * @return float
     */
    public static function likelihood($pp, array $ip, float $k) {
        $a = $ip['difficulty']; $b = $ip['discrimination']; $c = $ip['guessing'];

        if ($k < 1.0) {
            return 1 - self::likelihood($pp, $ip, 1.0);
        } else {
            return $c + (1 - $c) / (1 + exp($b * ($a - $pp)));
        }
    }

    // Calculate the LOG Likelihood and its derivatives //

    /**
     * Calculates the LOG Likelihood for a given the person ability parameter
     *
     * @param float $pp - person ability parameter
     * @param array<float> $ip - item parameters ('difficulty')
     * @param float $k - answer category (0 or 1.0)
     * @return int|float
     */
    public static function log_likelihood($pp, array $ip, float $k) {
        return log(self::likelihood($pp, $ip, $k));
    }

    /**
     * Calculates the 1st derivative of the LOG Likelihood with respect to the person ability parameter
     *
     * @param float $pp - person ability parameter
     * @param array<float> $ip - item parameters ('difficulty')
     * @param float $k - answer category (0 or 1.0)
     * @return float
     */
    public static function log_likelihood_p($pp, array $ip, float $k): float {
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
     * @param array<float> $ip - item parameters ('difficulty')
     * @param float $k - answer category (0 or 1.0)
     * @return float
     */
    public static function log_likelihood_p_p($pp, array $ip, float $k): float {
        $a = $ip['difficulty']; $b = $ip['discrimination']; $c = $ip['guessing'];

        if ($k < 1.0) {
            return -(($b ** 2 * exp($b * ($a + $pp))) / (exp($a * $b) + exp($b * $pp)) ** 2);
        } else {
            return ($b ** 2 * ($c - 1) * exp( $b * ($pp - $a)) * (exp(2 * $b ($pp - $a)) - $c)) / ((1 + exp($b * ( $pp - $a))) ** 2 * ($c + exp($b * ($pp - $a))) ** 2);
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
            fn ($ip) => ($ip['discrimination'] * ($ip['guessing'] - 1) * exp($ip['discrimination'] * ($ip['difficulty'] + $pp))) / ((exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)) * ($ip['guessing'] * exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp))), // d/da
            fn ($ip) => (($ip['guessing'] - 1) * exp($ip['discrimination'] * ($ip['difficulty'] + $pp)) * ($ip['difficulty'] - $pp)) / ((exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)) * ($ip['guessing'] * exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp))), // d/db
            fn ($ip) => exp($ip['difficulty'] * $ip['discrimination']) / ($ip['guessing'] * exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)) // d/dc
            ];
        } else {
            return [
            fn ($ip) => $ip['discrimination'] / (1 + exp($ip['discrimination'] * ($ip['difficulty'] - $pp))), // d/da
            fn ($ip) => ($ip['difficulty'] - $pp) / (1 + exp($ip['discrimination'] * ($ip['difficulty'] - $pp))), // d/db
            fn ($ip) => 1 / ($ip['guessing'] - 1) // d/dc
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
            fn ($ip) => -($ip['discrimination'] ** 2 * ($ip['guessing'] - 1) * exp($ip['discrimination'] * ($ip['difficulty'] + $pp)) * ($ip['guessing'] * exp(2 * $ip['difficulty'] * $ip['discrimination']) - exp(2 * $ip['discrimination'] * $pp))) / ((exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)) ** 2 * ($ip['guessing'] * exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)) ** 2), // d²/ da²
            fn ($ip) => (($ip['guessing'] - 1) * exp($ip['discrimination'] * ($ip['difficulty'] + $pp)) * (exp($ip['discrimination'] * ($ip['difficulty'] + $pp)) + exp(2 * $ip['discrimination'] * $pp) * (1 + $ip['difficulty'] * $ip['discrimination'] - $ip['discrimination'] * $pp) + $ip['guessing'] * (exp($ip['discrimination'] * ($ip['difficulty'] + $pp)) + exp(2 * $ip['difficulty'] * $ip['discrimination']) * (1 - $ip['difficulty'] * $ip['discrimination'] + $ip['discrimination'] * $pp)))) / ((exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)) ** 2 * ($ip['guessing'] * exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)) ** 2), // d/da d/db
            fn ($ip) => ($ip['discrimination'] * exp($ip['discrimination'] * ($ip['difficulty'] + $pp))) / ($ip['guessing'] * exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)) ** 2 // d/da d/dc
            ], [
            fn ($ip) => (($ip['guessing'] - 1) * exp($ip['discrimination'] * ($ip['difficulty'] + $pp)) * (exp($ip['discrimination'] * ($ip['difficulty'] + $pp)) + exp(2 * $ip['discrimination'] * $pp) * (1 + $ip['difficulty'] * $ip['discrimination'] - $ip['discrimination'] * $pp) + $ip['guessing'] * (exp($ip['discrimination'] * ($ip['difficulty'] + $pp)) + exp(2 * $ip['difficulty'] * $ip['discrimination']) * (1 - $ip['difficulty'] * $ip['discrimination'] + $ip['discrimination'] * $pp)))) / ((exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)) ** 2 * ($ip['guessing'] * exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)) ** 2), // d/da d/db
            fn ($ip) => -(($ip['guessing'] - 1) * exp($ip['discrimination'] * ($ip['difficulty'] - $pp)) * ($ip['guessing'] * exp(2 * $ip['discrimination'] * ($ip['difficulty'] - $pp)) - 1) * ($ip['difficulty'] - $pp) ** 2) / (((1 + exp($ip['discrimination'] * ($ip['difficulty'] - $pp))) * (1 + $ip['guessing'] * exp($ip['discrimination'] * ($ip['difficulty'] - $pp)))) ** 2), // d²/db²
            fn ($ip) => (exp($ip['discrimination'] * ($ip['difficulty'] + $pp)) * ($ip['difficulty'] - $pp)) / ($ip['guessing'] * exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)) ** 2 // d/db d/dc
            ], [
            fn ($ip) => ($ip['discrimination'] * exp($ip['discrimination'] * ($ip['difficulty'] + $pp))) / ($ip['guessing'] * exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)) ** 2, // d/da d/dc
            fn ($ip) => (exp($ip['discrimination'] * ($ip['difficulty'] + $pp)) * ($ip['difficulty'] - $pp)) / ($ip['guessing'] * exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)) ** 2, // d/db d/dc
            fn ($ip) => -exp(2 * $ip['difficulty'] * $ip['discrimination']) / ($ip['guessing'] * exp($ip['difficulty'] * $ip['discrimination']) + exp($ip['discrimination'] * $pp)) ** 2 // d²/dc²
            ]];
        } else {
            return [[
            fn ($ip) => -($ip['discrimination'] ** 2 * exp($ip['discrimination'] * ($ip['difficulty'] - $pp))) / (1 + exp($ip['discrimination'] * ($ip['difficulty'] - $pp))) ** 2, // d²/da²
            fn ($ip) => (exp($ip['discrimination'] * ($ip['difficulty'] - $pp)) * ($ip['discrimination'] * ($pp - $ip['difficulty']) + 1) + 1) / (exp($ip['discrimination'] * ($ip['difficulty'] - $pp)) + 1) ** 2, // d/da d/db
            fn ($ip) => 0 // d/da d/dc
   ], [
            fn ($ip) => (exp($ip['discrimination'] * ($ip['difficulty'] - $pp)) * ($ip['discrimination'] * ($pp - $ip['difficulty']) + 1) + 1) / (exp($ip['discrimination'] * ($ip['difficulty'] - $pp)) + 1) ** 2, // d/da d/db
            fn ($ip) => -(exp($ip['discrimination'] * ($ip['difficulty'] - $pp)) * ($ip['difficulty'] - $pp) ** 2) / (1 + exp($ip['discrimination'] * ($ip['difficulty'] - $pp))) ** 2, // d²/db²
            fn ($ip) => 0 // d/db d/dc
    ], [
            fn ($ip) => 0, // d/da d/dc
            fn ($ip) => 0, // d/db d/dc
            fn ($ip) => -1 / ($ip['guessing'] - 1) ** 2 // d²/dc²
            ]];
        }
    }

    /**
     * Calculates the Fisher Information for a given person ability parameter
     *
     * @param float $pp
     * @param array<float> $ip
     * @return float
     */
    public static function fisher_info($pp, $ip) {
        return $ip['difficulty'] ** 2 * (1 - $ip['guessing']) * self::likelihood($pp, $ip, 1.0) * (self::likelihood($pp, $ip, 0.0));
    }

    /**
     * Implements a Filter Function for trusted regions in the item parameter estimation
     *
     * @param array $ip
     * return array
     */
    public static function restrict_to_trusted_region(array $ip): array {
        // Set values for difficulty parameter
        $a = $ip['difficulty'];

        $am = 0; // Mean of difficulty
        $as = 2; // Standard derivation of difficulty

        // Use 3 times of SD as range of trusted regions
        $atr = floatval(get_config('catmodel_raschbirnbaumc', 'trusted_region_factor_sd_a'));
        $amin = floatval(get_config('catmodel_raschbirnbaumc', 'trusted_region_min_a'));
        $amax = floatval(get_config('catmodel_raschbirnbaumc', 'trusted_region_max_a'));

        // Set values for disrciminatory parameter
        $b = $ip['discrimination'];

        // Placement of the discriminatory parameter
        $bp = floatval(get_config('catmodel_raschbirnbaumc', 'trusted_region_placement_b'));
        // Slope of the discriminatory parameter
        $bs = floatval(get_config('catmodel_raschbirnbaumc', 'trusted_region_slope_b'));
        // Use 5 times of placement as maximal value of trusted region
        $btr = floatval(get_config('catmodel_raschbirnbaumc', 'trusted_region_factor_max_b'));

        $bmin = floatval(get_config('catmodel_raschbirnbaumc', 'trusted_region_min_b'));
        $bmax = floatval(get_config('catmodel_raschbirnbaumc', 'trusted_region_max_b'));

        // Set values for guessing parameter
        $c = $ip['guessing'];

        $cmax = floatval(get_config('catmodel_raschbirnbaumc', 'trusted_region_max_c'));

        // Test TR for difficulty
        if (($a - $am) < max(-($atr * $as), $amin)) {$a = max(-($atr * $as), $amin);
        }
        if (($a - $am) > min(($atr * $as), $amax)) {$a = min(($atr * $as), $amax);
        }

        $ip['difficulty'] = $a;

        // Test TR for discriminatory
        if ($b < $bmin) {$b = $bmin;
        }
        if ($b > min(($btr * $bp), $bmax)) {$b = min(($btr * $bp), $bmax);
        }

        $ip['discrimination'] = $b;

        // Test TR for guessing
        if ($c < 0) {$c = 0;
        }
        if ($c > $cmax) {$c = $cmax;
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
        // Set values for difficulty parameter
        $am = 0; // Mean of difficulty
        $as = 2; // Standard derivation of difficulty

        // Placement of the discriminatory parameter
        $bp = floatval(get_config('catmodel_raschbirnbaumc', 'trusted_region_placement_b'));
        // Slope of the discriminatory parameter
        $bs = floatval(get_config('catmodel_raschbirnbaumc', 'trusted_region_slope_b'));

        return [
        fn ($ip) => (($am - $ip['difficulty']) / ($as ** 2)), // d/da
        fn ($ip) => (-($bs * exp($bs * $ip['discrimination'])) / (exp($bs * $bp) + exp($bs * $ip['discrimination']))), // d/db
        fn ($ip) => (0)
        ];
    }

    /**
     * Calculates the 2nd derivative trusted regions for item parameters
     *
     * @return array
     */
    public static function get_log_tr_hessian(): array {
        // Set values for difficulty parameter
        $am = 0; // Mean of difficulty
        $as = 2; // Standard derivation of difficulty

        // Placement of the discriminatory parameter
        $bp = floatval(get_config('catmodel_raschbirnbaumc', 'trusted_region_placement_b'));
        // Slope of the discriminatory parameter
        $bs = floatval(get_config('catmodel_raschbirnbaumc', 'trusted_region_slope_b'));

        return [[
        fn ($ip) => (-1 / ($as ** 2)), // d/da d/da
        fn ($ip) => (0), // d/da d/db
        fn ($ip) => (0)
        ], [
        fn ($ip) => (0), // d/db d/da
        fn ($ip) => (-($bs ** 2 * exp($bs * ($bp + $ip['discrimination']))) / (exp($bs * $bp) + exp($bs * $ip['discrimination'])) ** 2), // d/db d/db
        fn ($ip) => (0)
        ], [
        fn ($ip) => (0),
        fn ($ip) => (0),
        fn ($ip) => (0)
        ]];
    }
}
