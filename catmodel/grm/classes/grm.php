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

        // TODO: This is very dirty and needs more attention on length / dimensionality.
        return array_merge($ip['difficulty']);
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

        // TODO: This is very dirty and needs more attention on length / dimensionality.
        return [
            'difficulty' => array_combine($fractions, array_splice($vector, count($vector) - 1)),
        ];
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
    public static function get_model_dim(): int {
        return array_sum(array_map("count", self::get_parameter_names()));
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
        return catcalc::estimate_item_params($itemresponse, $this);
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

        foreach ($a as $fraction => $val) {
            if ((float) $fraction > 0 && (float) $fraction <= 1) {
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
        // We do it the easy way by using the log'f(x) = f'(x)/f(x) method.
        return self::likelihood_p($pp, $ip, $frac) / self::likelihood($pp, $ip, $frac);
    }

    /**
     * Calculates the 2nd derivative of the LOG Likelihood with respect to the person ability parameter
     *
     * @param array $pp - person ability parameter
     * @param array $ip - item parameters ('difficulty', 'discrimination')
     * @param float $frac - answer fraction (0 ... 1.0)
     * @return float - 2nd derivative of log likelihood with respect to $pp
     */
    public static function log_likelihood_p_p(array $pp, array $ip, float $frac): float {
        // We do it the easy way by using the log''f(x) = (f(x)*f''(x)-f'(x)^2)/f(x)^2 method.
        return (self::likelihood($pp, $ip, $frac) * self::likelihood_p_p($pp, $ip, $frac) -
            self::likelihood_p($pp, $ip, $frac) ** 2) / self::likelihood($pp, $ip, $frac) ** 2;
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
        $iif = self::category_information($pp, $ip, 0.0) * self::likelihood($pp, $ip, 0.0);
        foreach ($ip['difficulties'] as $f => $val) {
            $iif += self::category_information($pp, $ip, $f) * self::likelihood($pp, $ip, $f);
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
        // Set values for difficulty parameter.
        $a = $ip['difficulty'];

        $am = 0; // Mean of difficulty.
        $as = 2; // Standard derivation of difficulty.

        // Use x times of SD as range of trusted regions.
        $atr = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_factor_sd_a'));
        $amin = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_min_a'));
        $amax = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_max_a'));

        // Set values for disrciminatory parameter.
        $b = 1;

        // Placement of the discriminatory parameter.
        $bp = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_placement_b'));
        // Slope of the discriminatory parameter.
        $bs = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_slope_b'));
        // Use x times of placement as maximal value of trusted region.
        $btr = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_factor_max_b'));

        $bmin = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_min_b'));
        $bmax = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_max_b'));

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
     * Calculates the 1st derivative trusted regions for item parameters
     *
     * @param array $ip - item parameters ('difficulty', 'discrimination')
     * @return array - 1st derivative of TR function with respect to $ip
     */
    public static function get_log_tr_jacobian($ip): array {
        // Set values for difficulty parameter.

        // TODO: @DAVID: Diese Werte sollten dynamisch berechnet werden können.
        $am = 0; // Mean of difficulty.
        $as = 2; // Standard derivation of difficulty.

        // Placement of the discriminatory parameter.
        $bp = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_placement_b'));
        // Slope of the discriminatory parameter.
        $bs = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_slope_b'));

        return [
        ($am - $ip['difficulty']) / ($as ** 2), // Calculates d/da.
        -($bs * exp($bs * $ip['discrimination'])) / (exp($bs * $bp) + exp($bs * $ip['discrimination'])), // Calculates d/db.
        ];
    }

    /**
     * Calculates the 2nd derivative trusted regions for item parameters
     *
     * @param array $ip - item parameters ('difficulty', 'discrimination')
     *
     * @return array - 2nd derivative of TR function with respect to $ip
     */
    public static function get_log_tr_hessian(array $ip): array {
        // Set values for difficulty parameter.

        // TODO: @DAVID: Diese Werte sollten dynamisch berechnet werden können.
        $am = 0; // Mean of difficulty.
        $as = 2; // Standard derivation of difficulty.

        // Placement of the discriminatory parameter.
        $bp = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_placement_b'));
        // Slope of the discriminatory parameter.
        $bs = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_slope_b'));

        return [
            [
                -1 / ($as ** 2), // Calculates d²/da².
                0, // Calculates d/da d/db.
            ],
            [
                0, // Calculates d/da d/db.
                -($bs ** 2 * exp($bs * ($bp + $ip['discrimination']))) /
                    (exp($bs * $bp) + exp($bs * $ip['discrimination'])) ** 2, // Calculates d²/db².
            ],
        ];
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
    protected function get_multi_param_name(): string {
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
