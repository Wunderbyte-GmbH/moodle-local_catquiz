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
 * Models that implement this interface can use the catcalc class to estimate their parameters
 *
 * @package local_catquiz
 * @author David Szkiba
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;
/**
 * Models that implement this interface can use the catcalc class to estimate their parameters
 *
 * @package local_catquiz
 * @author David Szkiba
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface catcalc_item_estimator {
    /**
     * Get model dim.
     *
     * @return int
     *
     */
    public static function get_model_dim(): int;

    /**
     * Numerically stable logistic (sigmoid) function.
     *
     * Shared primitive for all logistic IRT models so the compute-intensive
     * likelihood and derivative code can be expressed via P and W = P(1 - P)
     * instead of repeated raw exponentials.
     *
     * @param float $z linear predictor
     *
     * @return float
     *
     */
    public static function logistic(float $z): float;

    /**
     * Update parameters so that they are located in a trusted region
     * @param array $parameters
     *
     * @return array
     */
    public static function restrict_to_trusted_region(array $parameters): array;

    /**
     * 1st derivative of the log likelihood with respect to the item parameters.
     *
     * Required by catcalc::estimate_item_params(), which builds the item-parameter
     * jacobian from this method.
     *
     * @param array $ability person ability parameter ('ability')
     * @param array $ip item parameters
     * @param float $itemresponse response fraction
     *
     * @return array jacobian vector
     */
    public static function get_log_jacobian(array $ability, array $ip, float $itemresponse): array;

    /**
     * 2nd derivative (hessian) of the log likelihood with respect to the item parameters.
     *
     * Required by catcalc::estimate_item_params(), which builds the item-parameter
     * hessian from this method.
     *
     * @param array $ability person ability parameter ('ability')
     * @param array $ip item parameters
     * @param float $itemresponse response fraction
     *
     * @return array hessian matrix
     */
    public static function get_log_hessian(array $ability, array $ip, float $itemresponse): array;
}
