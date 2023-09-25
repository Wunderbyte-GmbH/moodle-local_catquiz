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
     * @param array <float> $pp - person ability parameter ('ability')
     * @param array <float> $ip - item parameters ('difficulty', 'discrimination')
     * @param float $k - answer category (0 or 1.0)
     * @return float
     */
    public static function likelihood(array $pp, array $ip, float $k): float {
        $pp = $pp['ability'];
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
     * @param array <float> $pp - person ability parameter ('ability')
     * @param array $ip - item parameters ('difficulty')
     * @param float $k - answer category (0 or 1.0)
     * @return float - log likelihood
     */
    public static function log_likelihood(array $pp, array $ip, float $k): float {
        return log(self::likelihood($pp, $ip, $k));
    }
    
    /**
     * Calculates the 1st derivative of the LOG Likelihood with respect to the item parameters
     *
     * @param float $pp - person ability parameter
     * @param float $k - answer category (0 or 1.0)
     * @return float - 1st derivative of log likelihood with respect to $pp
     */
    public static function log_likelihood_p(array $pp, array $ip, float $k):float {
        $pp = $pp['ability'];
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];
        
        if ($k < 1.0) {
            return -($b * exp($b * $pp)) / (exp($a * $b) + exp($b * $pp));
        } else {
            return ($b * exp($a * $b)) / (exp($a * $b) + exp($b * $pp));
        }
    }

    /**
     * Calculates the 2nd derivative of the LOG Likelihood with respect to the person ability parameter
     *
     * @param array<float> $pp - person ability parameter
     * @param array<float> $ip - item parameters ('difficulty')
     * @param float $k - answer category (0 or 1.0)
     * @return float - 2nd derivative of log likelihood with respect to $pp
     */
    public static function log_likelihood_p_p(array $pp, array $ip, float $k):float {
        $pp = $pp['ability'];
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];
        
        return [[-(($b ** 2 * exp($b * ($a + $pp))) / ((exp($a * $b) + exp($b * $pp)) ** 2))]];
    }

    /**
     * Calculates the 1st derivative of the LOG Likelihood with respect to the item parameters
     *
     * @param array<float> $pp - person ability parameter ('ability')
     * @param array<float> $ip - item parameters ('difficulty', 'discrimination')
     * @param float $k - answer category (0 or 1.0)
     * @return array - jacobian vector
     */
    public static function get_log_jacobian(array $pp, array $ip, float $k):array {
        $pp = $pp['ability'];
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];
        
        $jacobian = [];
        
        // Pre-Calculate high frequently used exp-terms.
        $exp_ab = exp($a * $b);
        $exp_bp = exp($b * $pp);
      
        if ($k < 1.0) {
          $jacobian[0] = ($b * $exp_bp) / ($exp_ab + $exp_bp); // Calculates d/da.
          $jacobian[1] = ($exp_bp * ( $a - $pp)) / ($exp_ab + $exp_bp); // Calculates d/db.
        } else {
          $jacobian[0] = -$b * $exp_ab / (exp( $a * $b) + $exp_bp); // Calculates d/da.
          $jacobian[1] = $exp_ab * ($pp - $a) / ($exp_ab + $exp_bp); // Calculates d/db.
        }
      return $jacobian;
    }
    
    /**
     * Calculates the 2nd derivative of the LOG Likelihood with respect to the item parameters
     *
     * @param array<float> $pp - person ability parameter ('ability')
     * @param array<float> $ip - item parameters ('difficulty', 'discrimination')
     * @param float $k - answer category (0 or 1.0)
     * @return array - hessian matrx
     */
    public static function get_log_hessian(array $pp, array $ip, float $itemresponse): array {
        $pp = $pp['ability'];
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];
        
        $hessian = [[]];
        
        // Pre-Calculate high frequently used exp-terms.
        $exp_ab = exp($a * $b);
        $exp_bp = exp($b * $pp);
         
        if ($k >= 1.0) {
          $exp_bap1 = exp($b * ($a + $pp));
          $hessian[0][0] = (-($b ** 2 * $exp_bap1) / (($exp_ab + $exp_bp) ** 2)); // Calculates d²/da².
          $hessian[0][1] = (-($exp_ab * ($exp_ab + $exp_bp * (1 + $b * ($a - $pp)))) / (($exp_ab + $exp_bp) ** 2)); // Calculates d/a d/db.
          $hessian[1][0] = $hessian[0][1];
          $hessian[1][1] = (-($exp_bap1 * ($a - $pp) ** 2) / (($exp_ab + $exp_bp) ** 2)); // Calculates d²/db².
        } else {
          $exp_bap0 = exp($b * ($a - $pp));
          $hessian[0][0] = -($b ** 2 * $exp_bap0) / (1 + $exp_bap0) ** 2; // Calculates d²/da².
          $hessian[0][1] = (1 + $exp_bap0 * (1 + $b * ($pp - $a))) / (1 + $exp_bap0) ** 2; // Calculates d/da d/db.
          $hessian[1][0] = $hessian[0][1];
          $hessian[1][1] = -($exp_bap0 * ($a - $pp) ** 2) / (1 + $exp_bap0) ** 2; // Calculates d²/db².
        }
      return $hessian;
    }

    // Calculate the Least-Mean-Squres (LMS) approach.

    /**
     * Calculates the Least Mean Squres (residuals) for a given the person ability parameter and a given expected/observed score
     *
     * @param array<float> $pp - person ability parameter ('ability')
     * @param array<float> $ip - item parameters ('difficulty', 'discrimination', 'guessing')
     * @param float $k - fraction of correct (0 ... 1.0)
     * @param float $n - number of observations
     * @return float - weighted residuals
     */
   function least_mean_squares(array $pp, array $ip, float $k, float $n):float{
        return $n * ($k - likelihood($pp, $ip, 1.0)) ** 2;
    }

    /**
     * Calculates the 1st derivative of Least Mean Squares with respect to the item parameters
     *
     * @param array<float> $pp - person ability parameter ('ability')
     * @param array<float> $ip - item parameters ('difficulty', 'discrimination', 'guessing')
     * @param float $k - fraction of correct (0 ... 1.0)
     * @param float $n - number of observations
     * @return array - 1st derivative of lms with respect to $ip
     */ 
   function least_mean_squares_1st_derivative_ip(array $pp, array $ip, float $k, float $n):array{
        $pp = $pp['ability'];
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];

        $derivative = [];
        
        // Pre-Calculate high frequently used exp-terms.
        $exp_bap = exp($b * ($a - $pp));
        
        $derivative[0] = $n * (2 * $b * $exp_bap * ($k -1 + $exp_bap * $k)) / (1 + $exp_bap) ** 3; // Calculate d/da.            
        $derivative[1] = $n * (2 * $exp_bap * ($a - $pp) * ($k -1 + $exp_bap * $k)) / (1 + $exp_bap) ** 3; // Calculate d/db.

        return $derivative;
    }

    /**
     * Calculates the 2nd derivative of Least Mean Squres with respect to the item parameters
     *
     * @param array<float> $pp - person ability parameter ('ability')
     * @param array<float> $ip - item parameters ('difficulty', 'discrimination', 'guessing')
     * @param float $k - fraction of correct (0 ... 1.0)
     * @param float $n - number of observations
     * @return array - 2nd derivative of lms with respect to $ip
     */ 
   function least_mean_squares_2nd_derivative_ip(array $pp, array $ip, float $k, float $n):array{
        $pp = $pp['ability'];
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];

        $derivative = [[]];
        
        // Pre-Calculate high frequently used exp-terms.
        $exp_bap1 = exp($b * ($a + $pp));
        $exp_bap0 = exp($b * ($a - $pp));
        $exp_ab = exp($a * $b);
        $exp_bp = exp($b * $pp):
        
        $derivative[0][0]  = $n * (2 * $b ** 2 * $exp_bap1 * (-$exp_bp ** 2 + 2 * $exp_bap1 + (-$exp_ab ** 2 + $exp_bp ** 2) * $k)) / ($exp_ab + $exp_bp) ** 4; // Calculate d²2/da².            
        $derivative[0][1]  = $n * (2 * $exp_bap0 * ((1 + $a * $b - $b * $pp) * ($k -1) - $exp_bap0 ** 2 * ($b * ($a - $pp) - 1) * $k + $exp_bap0 * (2 * $a * $b - 2 * $b * $pp + 2 * $k - 1))) / (1 + $exp_bap0) ** 4; // Calculate d/da d/db.    
        $derivative[1][1]  = $n * (2 * $exp_bap1 * ($a - $pp) ** 2 * (2 * $exp_bap1 + (-$exp_ab ** 2 + $exp_bp ** 2) * $k - $exp_bp ** 2)) / ($exp_ab + $exp_bp) ** 4; // Calculate d²/db².

        // Note: Partial derivations are exchangeable, cf. Theorem of Schwarz.
        $derivative[1][0] = $derivative[0][1];
      
        return $derivative;
    }

    // Calculate the Log'ed Odds-Ratio Squared (LORS) approach.
    
    /**
     * Calculates the Log'ed Odds-Ratio Squared (residuals) for a given the person ability parameter and a given expected/observed score
     *
     * @param array<float> $pp - person ability parameter ('ability')
     * @param array<float> $ip - item parameters ('difficulty', 'discrimination', 'guessing')
     * @param float $or - odds ratio
     * @param float $n - number of observations
     * @return float - weighted residuals
     */   
    function lors_residuals(array $pp, array $ip, float $or, float $n = 1):float {
        $pp = $pp['ability'];
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];
        
        return $n * (log($or) + $b * ($a - $pp)) ** 2;
    }

    /**
     * Calculates the 1st derivative of Log'ed Odds-Ratio Squared with respect to the item parameters
     *
     * @param array<float> $pp - person ability parameter ('ability')
     * @param array<float> $ip - item parameters ('difficulty', 'discrimination', 'guessing')
     * @param float $or - odds ratio
     * @param float $n - number of observations
     * @return array - 1st derivative
     */   
   function lors_1st_derivative_ip(array $pp, array $ip, float $or, float $n = 1): array {
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
     * @param array<float> $pp - person ability parameter ('ability')
     * @param array<float> $ip - item parameters ('difficulty', 'discrimination', 'guessing')
     * @param float $or - odds ratio
     * @param float $n - number of observations
     * @return array - 1st derivative
     */   
   function lors_2nd_derivative_ip(array $pp, array $ip, float $or, float $n = 1): array {
        $pp = $pp['ability'];
        $a = $ip['difficulty'];
        $b = $ip['discrimination'];
        
        $derivative = [[]];
        
        $derivative[0][0]  = $n * 2 * $b ** 2; // Calculate d²2/da².            
        $derivative[0][1]  = 0; // $n * 2 * (2 * $b * ($a - $pp) + log($or)); // Calculate d/da d/db.    
        $derivative[1][1]  = $n * 2 * ($a - $pp) ** 2; // Calculate d²/db².

        // Note: Partial derivations are exchangeable, cf. Theorem of Schwarz.
        $derivative[1][0] = $derivative[0][1];
      
        return $derivative;
    }
    
    // Calculate Fisher-Information.

    /**
     * Calculates the Fisher Information for a given person ability parameter
     *
     * @param array<float> $pp - person ability parameter ('ability')
     * @param array<float> $ip - item parameters ('difficulty', 'discrimination', 'guessing')
     * @return float
     */
    public static function fisher_info(array $pp, array $ip): float {
        return ($ip['discrimination'] ** 2 * self::likelihood($pp, $ip, 0) * self::likelihood($pp, $ip, 1.0));
    }

    // Implements handling of the Trusted Regions (TR) approach.
    
    /**
     * Implements a Filter Function for trusted regions in the item parameter estimation
     *
     * @param array<float> $ip - item parameters ('difficulty', 'discrimination', 'guessing')
     * return array - chunked item parameter
     */
     function restrict_to_trusted_region(array $ip): array {
        // Set values for difficulty parameter.
        $a = $ip['difficulty'];

        $a_m = 0; // Mean of difficulty.
        $a_s = 2; // Standard derivation of difficulty.

        // Use x times of SD as range of trusted regions.
        $a_tr = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_factor_sd_a'));
        $a_min = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_min_a'));
        $a_max = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_max_a'));

        // Set values for disrciminatory parameter.
        $b = $ip['discrimination'];

        // Placement of the discriminatory parameter.
        $b_p = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_placement_b'));
        // Slope of the discriminatory parameter.
        $b_s = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_slope_b'));
        // Use x times of placement as maximal value of trusted region.
        $b_tr = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_factor_max_b'));

        $b_min = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_min_b'));
        $b_max = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_max_b'));

        // Test TR for difficulty.
        if ($a < max($a_m - ($a_tr * $a_s), $a_min)) {$a = max($a_m - ($a_tr * $a_s), $a_min); }
        if ($a > min($a_m + ($a_tr * $a_s), $a_max)) {$a = min($a_m + ($a_tr * $a_s), $a_max); }

        $ip['difficulty'] = $a;

        // Test TR for discriminatory.
        if ($b < $b_min) {$b = $b_min; }
        if ($b > min(($b_tr * $b_p),$b_max)) {$b = min(($b_tr * $b_p),$b_max); }

        $ip['discrimination'] = $b;

        return $ip;
    }

    /**
     * Calculates the 1st derivative trusted regions for item parameters
     *
     * @param array<float> $ip - item parameters ('difficulty', 'discrimination', 'guessing')
     * @return array - 1st derivative of TR function with respect to $ip
     */
    function get_log_tr_jacobian($ip): array {
      // Set values for difficulty parameter.

      // @DAVID: Diese Werte sollten dynamisch berechnet werden können
      $a_m = 0; // Mean of difficulty.
      $a_s = 2; // Standard derivation of difficulty.

      // Placement of the discriminatory parameter.
      $b_p = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_placement_b'));
      // Slope of the discriminatory parameter.
      $b_s = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_slope_b'));

      return [
        ($a_m - $ip['difficulty']) / ($a_s ** 2), // Calculates d/da.
        -($b_s * exp($b_s * $ip['discrimination'])) / (exp($b_s * $b_p) + exp($b_s * $ip['discrimination'])) // Calculates d/db.
      ];
    }

    /**
     * Calculates the 2nd derivative trusted regions for item parameters
     *
     * @param array<float> $ip - item parameters ('difficulty', 'discrimination', 'guessing')
     * @return array<array> - 2nd derivative of TR function with respect to $ip
     */
    function get_log_tr_hessian(array $ip):array{
      // Set values for difficulty parameter.

      // @DAVID: Diese Werte sollten dynamisch berechnet werden können  
      $a_m = 0; // Mean of difficulty.
      $a_s = 2; // Standard derivation of difficulty.

      // Placement of the discriminatory parameter.
      $b_p = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_placement_b'));
      // Slope of the discriminatory parameter.
      $b_s = floatval(get_config('catmodel_raschbirnbaumb', 'trusted_region_slope_b'));

      return [[
        -1/ ($a_s ** 2), // Calculates d²/da².
        0 // Calculates d/da d/db.
      ],[
        0, // Calculates d/da d/db.
        -($b_s ** 2 * exp($b_s * ($b_p + $ip['discrimination']))) / (exp($b_s * $b_p) + exp($b_s * $ip['discrimination'])) ** 2 // Calculates d²/db².
      ]];  
    }
    
}
