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
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

interface catcalc_ability_estimator {

    /**
     * Likelihood.
     *
     * @param mixed $x
     * @param array $itemparams
     * @param float $itemresponse
     *
     * @return mixed
     *
     */
    public static function likelihood($x, array $itemparams, float $itemresponse);

    /**
     * Log likelihood.
     *
     * @param mixed $x
     * @param array $itemparams
     * @param float $itemresponse
     *
     * @return mixed
     *
     */
    public static function log_likelihood($x, array $itemparams, float $itemresponse);

    /**
     * Log likelihood p
     *
     * @param array $x
     * @param array $itemparams
     * @param float $itemresponse
     *
     * @return mixed
     *
     */
    public static function log_likelihood_p(array $x, array $itemparams, float $itemresponse);

    /**
     * Log likelihood p p
     *
     * @param array $x
     * @param array $itemparams
     * @param float $itemresponse
     *
     * @return mixed
     *
     */
    public static function log_likelihood_p_p(array $x, array $itemparams, float $itemresponse);

}
