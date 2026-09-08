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
 * Models that implement this interface can be used by the catcalc class to estimate the person ability
 *
 * @package local_catquiz
 * @author David Szkiba
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;
/**
 * Models that implement this interface can be used by the catcalc class to estimate the person ability
 *
 * @package local_catquiz
 * @author David Szkiba
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface catcalc_ability_estimator {
    /**
     * Likelihood.
     *
     * @param array $pp
     * @param array $itemparams
     * @param float $itemresponse
     *
     * @return mixed
     *
     */
    public static function likelihood(array $pp, array $itemparams, float $itemresponse);

    /**
     * Log likelihood.
     *
     * @param array $pp
     * @param array $itemparams
     * @param float $itemresponse
     *
     * @return mixed
     *
     */
    public static function log_likelihood(array $pp, array $itemparams, float $itemresponse);

    /**
     * Calculates the 1st derivative of the LOG Likelihood with respect to the person ability parameter
     *
     * @param array $pp
     * @param array $itemparams
     * @param float $itemresponse
     *
     * @return float
     *
     */
    public static function log_likelihood_p(array $pp, array $itemparams, float $itemresponse);

    /**
     * Calculates the 2nd derivative of the LOG Likelihood with respect to the person ability parameter
     *
     * @param array $pp
     * @param array $itemparams
     * @param float $itemresponse
     *
     * @return float
     *
     */
    public static function log_likelihood_p_p(array $pp, array $itemparams, float $itemresponse);

    /**
     * Combined 1st and 2nd person-ability derivatives in a single call.
     *
     * Required by catcalc::estimate_person_ability(), which evaluates the gradient
     * and the hessian at the same point each Newton iteration and shares one
     * probability/moment computation between them. model_raschmodel provides a
     * default implementation delegating to log_likelihood_p()/log_likelihood_p_p();
     * models override it where a shared computation pays off.
     *
     * @param array $pp person ability parameter ('ability')
     * @param array $itemparams item parameters
     * @param float $itemresponse response fraction
     *
     * @return array ['jacobian' => 1st derivative, 'hessian' => 2nd derivative]
     */
    public static function get_ability_derivatives(array $pp, array $itemparams, float $itemresponse): array;
}
