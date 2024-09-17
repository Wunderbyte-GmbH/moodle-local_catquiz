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
 * Class pcm.
 *
 * @package    catmodel_pcm
 * @copyright  2024 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace catmodel_pcm;

use Exception;
use local_catquiz\catcalc;
use local_catquiz\local\model\model_item_param;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_multiparam;
use local_catquiz\local\model\model_person_param_list;
use local_catquiz\local\model\model_raschmodel;
use stdClass;

/**
 * Class pcm of catmodels.
 *
 * @package    catmodel_pcm
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pcm extends model_multiparam {

    /**
     * Returns the item parameter array from a database record.
     *
     * @param stdClass $record
     * @return array
     */
    public static function get_parameters_from_record(stdClass $record): array {

        $intercepts = json_decode($record->json, true)['intercept'];

      //  $meandifficulty = self::calculate_mean_difficulty([
      //      'intercept' => $intercepts,
      //  ]);

        return [
       //     'difficulty' => round($meandifficulty, self::PRECISION),
            'intercept' => $intercepts,
            'discrimination' => round($record->discrimination, self::PRECISION),
        ];
    }

    /**
     * Returns the name of this model.
     *
     * @return string
     */
    public function get_model_name(): string {
        return 'pcm';
    }

    // Definitions and Dimensions.

    /**
     * Defines names if item parameter list
     *
     * @param array $ip
     * @return array of string
     */
    public static function get_fractions(array $ip): array {
        $frac = [];

        foreach ($ip['intercept'] as $fraction => $val) {
            $frac[] = $fraction;
        }

        $frac = array_unique($frac);
        sort($frac);
        return $frac;
    }

    /**
     * Defines names if item parameter list
     *
     * @param float $frac
     * @param array $fractions
     *
     * @return int
     */
    public static function get_category(float $frac, array $fractions): int {

        for ($k = 0; $fractions[$k] < $frac; $k++);

        return $k;
    }

    /**
     * Goes modified to mathcat.php.
     *
     * @param array $ip
     *
     * @return array
     */
    public static function convert_ip_to_vector(array $ip): array {

        // TODO: This is very dirty and needs more attention on length / dimensionality.
        return array_merge($ip['intercept'], [$ip['intercept']]);
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
            'intercept' => array_combine($fractions, array_splice($vector, count($vector) - 1)),
        ];
    }

    /**
     * Defines names if item parameter list
     *
     * The parameters have the following structure.
     * [
     *   'difficultiy': [fraction 1: 0, fraction 2: intercept 1, ..., fraction k+1: intercept k-1],
     *   'discrimination': discrimination
     * ]
     * @return array of string
     */

    /**
     * Get parameter names
     *
     * This will have the following structure.
     * [
     *   'difficultiy': [fraction1: 0, fraction2: intercept 1, ..., fraction k: difficulty k-1],
     *   'discrimination': discrimination
     * ]
     *
     * @return array
     */
    public static function get_parameter_names(): array {
        return ['intercept', 'discrimination'];

    }

    /**
     * Definition of the number of model parameters
     *
     * @return int
     */
    public static function get_model_dim(): int {
        // Adds +1 for the person ability.
        return array_sum(array_map("count", self::get_parameter_names())) + 1;
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

    /**
     * Calculate the mean difficulty
     *
     * @param array $ip
     *
     * @return float
     *
     */
    public static function calculate_mean_difficulty(array $ip): float {

        $fractions = self::get_fractions($ip);
        $kmax = max(array_keys($fractions));
        $sum = 0;

        for ($k = 1; $k < $kmax; $k++) {
            $sum += $ip['intercept'][$fractions[$k]];
        }
        return $sum / $kmax;
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

        $fractions = self::get_fractions($ip);
        $kmax = max(array_keys($fractions));

        // Making sure, that the first intercept is 0, so that for k=0: 1=exp(0*pp - intercept).
        $ip['intercept'][$fractions[0]] = 0;

        // Calculation the denominator of the formulae.
        $denominator = 0;
        $intercepts = 0;
        for ($k = 0; $k < $kmax; $k++) {
            $intercepts += $ip['intercept'][$fractions[$k]];
            $denominator += exp($k * $ability - $intercepts);
        }

        // Calculation the probability.
        $k = self::get_category($frac, $fractions);
        return exp($k * $pp['ability'] - $intercepts) / $denominator;
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
        $ability = $pp['ability'];

        $fractions = self::get_fractions($ip);
        $kmax = max(array_keys($fractions));

        // Making sure, that the first intercept is 0, so that for k=0: 1=exp(0*pp - intercept).
        $ip['intercept'][$fractions[0]] = 0;

        // Calculation the denominator of the formulae.
        $denominator = 0;
        $firstderivative = 0;
        $secondderivative = 0;
        $intercepts = 0;
        for ($k = 0; $k < $kmax; $k++) {
            $intercepts += $ip['intercept'][$fractions[$k]];
            $denominator += exp($k * $ability - $intercepts);
            $firstderivative += $k * exp($k * $ability - $intercepts);
            $secondderivative += $k ** 2 * exp($k * $ability - $intercepts);
        }
        $k = self::get_category($frac, $fractions);

        return $k - $firstderivative / $denominator;
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
        $ability = $pp['ability'];

        $fractions = self::get_fractions($ip);
        $kmax = max(array_keys($fractions));

        // Making sure, that the first intercept is 0, so that for k=0: 1=exp(0*pp - intercept).
        $ip['intercept'][$fractions[0]] = 0;

        // Calculation the denominator of the formulae.
        $denominator = 0;
        $firstderivative = 0;
        $secondderivative = 0;
        $intercepts = 0;
        for ($k = 0; $k < $kmax; $k++) {
            $intercepts += $ip['intercept'][$fractions[$k]];
            $denominator += exp($k * $ability - $intercepts);
            $firstderivative += $k * exp($k * $ability - $intercepts);
            $secondderivative += $k ** 2 * exp($k * $ability - $intercepts);
        }

        return $firstderivative ** 2 / $denominator ** 2 - $secondderivative / $denominator;
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
        throw new Exception("Not yet implemented");
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
        throw new Exception("Not yet implemented");
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
        // Ralf hab ich von $ip['difficulty'] geändert.
        foreach ($ip['intercept'] as $f => $val) {
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
        $b = $ip['discrimination'];

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

//    public function get_parameter_fields(model_item_param $param): array {
//        if (!$param->get_params_array()) {
//            $params = $this->get_default_params();
//        }
//        $parameters = ['discrimination' => $params['discrimination']];
//        foreach ($params['intercept'] as $frac => $val) {
//            $parameters['fraction_'.$frac] = $frac;
//            $parameters['difficulty_'.$frac] = $val;
//        }
//        return $parameters;
//    }

    public function get_default_params(): array {
        return [
            'discrimination' => 0.0,
            'intercept' => [
                '0.00' => 0.0,
                '0.50' => 0.5,
                '1.00' => 1.0,
            ]
        ];
    }

    protected function get_multi_param_name(): string {
        return 'intercept';
    }
}
