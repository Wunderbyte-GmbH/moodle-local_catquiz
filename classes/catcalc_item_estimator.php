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
     * Calculates the 1st derivative of the LOG Likelihood with respect to the item parameters
     *
     * @param array $ability
     * @param array $ip
     * @param float $itemresponse
     *
     * @return array
     *
     */
    public static function get_log_jacobian(array $ability, array $ip, float $itemresponse): array;

    /**
     * Calculates the 2nd derivative of the LOG Likelihood with respect to the item parameters
     *
     * @param array $ability
     * @param array $ip
     * @param float $itemresponse
     *
     * @return array
     *
     */
    public static function get_log_hessian(array $ability, array $ip, float $itemresponse): array;

    /**
     * Get log tr jacobian.
     *
     * @param array $ip
     *
     * @return array
     *
     */
    public static function get_log_tr_jacobian(array $ip): array;

    /**
     * Get log tr hessian.
     *
     * @param array $ip
     *
     * @return array
     *
     */
    public static function get_log_tr_hessian(array $ip): array;

    /**
     * Get model dim.
     *
     * @return int
     *
     */
    public static function get_model_dim(): int;

    /**
     * Update parameters so that they are located in a trusted region
     * @param array $parameters
     *
     * @return array
     */
    public static function restrict_to_trusted_region(array $parameters): array;
}
