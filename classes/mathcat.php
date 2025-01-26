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
 * Class mathcat.
 *
 * @package local_catquiz
 * @author Daniel Pasterk
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

/**
 * Class for math functions.
 *
 * @package local_catquiz
 * @author Daniel Pasterk
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mathcat {

    /**
     * Performs BFGS algorithm and returns optimized parameters.
     *
     * @param callable $fnfunction - Function to be calculated on with parameter $parameter
     * @param callable $fnderivative - 1st derivative (Jacobian) of $fn_function with parameter $parameter
     * @param array $parameterstart - Parameter-set to start with (should be near zero point)
     * @param int $precission - Accuracy to how many decimal places
     * @param int $maxiterations - Maximum number of iterations
     * @param callable|null $fnparameterrestrictions - Parameter-check for trusted Region
     * @param callable|null $fneapestimator - EAP-Estimator (bell curve) function
     * @param callable|null $fneapestimatorderivative1st - Deriavative of $fneapestimator
     *
     * @return array
     *
     */
    public static function bfgs(
        callable $fnfunction,
        callable $fnderivative,
        array $parameterstart,
        int $precission = 2,
        int $maxiterations = 50,
        ?callable $fnparameterrestrictions = null,
        ?callable $fneapestimator = null,
        ?callable $fneapestimatorderivative1st = null): array {

        $parameter = $parameterstart;
        $parameterstructure = self::array_to_vector($parameter);
        $steplength = 1;
        $mxparameter = new matrix($parameter);

        // Calculate the function values from the given functions for current $parameter.
        $valfunction = $fnfunction(self::vector_to_array($parameter, $parameterstructure));

        // Note: Takte the identity matrx as first approximation of the inverse Hessian.
        $mxinvhessian = (new matrix(count($parameter), count($parameter)))->identity();

        $valjacobian = $fnderivative(self::vector_to_array($parameter, $parameterstructure));
        $mxgradient = new matrix ($valjacobian); // Note: Line vector.

        // Begin with numerical iteration.
        for ($i = 0; $i < $maxiterations; $i++) {

            if ($mxgradient->rooted_summed_squares() == 0) {
                // Note: There is nothing to be climed on, we are already at a local extrema.
                return self::vector_to_array(((array) $mxparameter)[0], $parameterstructure);
            }

            $mxdirection = $mxinvhessian->multiply($mxgradient->transpose());

            // Note: Perform line search sensitive to given limitations.
            $directionlength = $mxdirection->rooted_summed_squares();
            if ($directionlength == 0) {
                // Note: We hit the maximum.
                return self::vector_to_array(((array) $mxparameter)[0], $parameterstructure);
            }
            $mxparametertest = $mxparameter->add($mxdirection->multiply($steplength /
                $directionlength));
            $valfunctiontest = $fnfunction(self::vector_to_array(((array) $mxparametertest)[0], $parameterstructure));

            $stepdirection = ($valfunctiontest - $valfunction) <=> 0;

            do {
                $mxparameternew = $mxparametertest;
                $valfunctionnew = $valfunctiontest;

                $steplength *= 2 ** $stepdirection;
                $mxparametertest = $mxparameternew->add($mxdirection->multiply($steplength / $directionlength));
                $valfunctiontest = $fnfunction(self::vector_to_array(((array) $mxparametertest)[0],
                    $parameterstructure));

                if ($steplength < 10 ** (-$precission)) {
                    break;
                }
                // Do here a check against filterfunction as well!

            } while ($valfunctionnew < $valfunctiontest);

            $valjacobian = $fnderivative(self::vector_to_array(((array) $mxparameternew)[0], $parameterstructure));
            $mxgradientnew = new ($valjacobian);

            $mxparameterdiff = $mxparameternew->subtract($mxparameter);
            $mxgradientdiff = $mxgradientnew->subtract($mxgradient);

            // Note: Calculate scaling factor.
            $rho = $mxparameterdiff->multiply($mxgradientdiff->transpose());

            if ($rho <> 0) {
                // Note: Update inverse hessian matrix.
                $mxidentity = (new matrix (count($parameter), count($parameter)))->identity();

                $mxparamxgrad = ($mxparameterdiff->transpose())->multiply($mxgradientdiff);
                $mxgradxparam = ($mxgradientdiff->transpose())->multiply($mxparameterdiff);
                $mxparamxparam = ($mxparameterdiff->transpose())->multiply($mxparameterdiff);

                $part1 = $mxidentity->subtract($mxparamxgrad->multiply((1.0 / $rho)));
                $part2 = $mxidentity->subtract($mxgradxparam->multiply((1.0 / $rho)));
                $part3 = $mxparamxparam->multiply(1.0 / $rho);

                $mxinvhessian = $part1->multiply($mxinvhessian)->multiply($part2)->add($part3);
            } else {
                // Note: There is no progress in parameter, no further gradient or gradient is transverse to progrssion.
                return self::vector_to_array(((array) $mxparameternew)[0], $parameterstructure);
            }

            $mxparameter = $mxparameternew;
            if ((abs($valfunctionnew - $valfunction)) < (10 ** (-$precission))) {
                return self::vector_to_array(((array) $mxparameter)[0], $parameterstructure);
            }

            debugging ('Iteration i: '.$i.'
            Position: '.print_r($parameter, true).'
            Gradient: '.print_r($mxgradient, true).'
            Direction: '.print_r($mxdirection, true).'
            Length: '.$directionlength.'
            Step Length: '.$steplength, DEBUG_DEVELOPER);
        }

        // Return the concurrent solution even the precission criteria hasn't been met.
        return self::vector_to_array(((array) $mxparameter)[0], $parameterstructure);
    }

    /**
     * Performs the Newton-Raphson approach for determine the zero point of a function
     *
     * @param callable $fnfunction - Function to be calculated on with parameter $parameter
     * @param callable $fnderivative - Deriavative of $fn_function with parameter $parameter
     * @param array $parameterstart - Parameter-set to start with (should be near zero point)
     * @param int $precission - Accuracy to how many decimal places
     * @param int $maxiterations - Maximum number of iterations
     * @param callable|null $fntrfilter - Parameter-check for trusted Region
     * @param callable|null $fntrfunction - Trusted Region modelling function
     * @param callable|null $fntrderivative - Deriavative of $fn_trusted_regions_function
     *
     * @return array
     *
     */
    public static function newton_raphson_multi_stable(
        callable $fnfunction,
        callable $fnderivative,
        array $parameterstart,
        int $precission = 6,
        int $maxiterations = 100,
        ?callable $fntrfilter = null,
        ?callable $fntrfunction = null,
        ?callable $fntrderivative = null): array {

        // Set initial values.
        $parameter = $parameterstart;
        // Note: Please check for yourself...
        // ... that the order of your parameters in your array corresponds to the order of $fn_function!
        $parameternames = array_keys($parameterstart);
        $iscritical = false;
        $maxsteplength = 0.1;
        $usegauss = false;

        // Begin with numerical iteration.
        for ($i = 0; $i < $maxiterations; $i++) {

            // DAVID: Sollte serialisiert werden für den Fall genesteter Arrays.
            $mxparameter = new matrix($parameter);
            $mxparameter = $mxparameter->transpose();

            // Calculate the function and derivative values from  $fn_function and $fn_derivative at point $parameter.
            $valfunction = $fnfunction($parameter);
            $valderivative = $fnderivative($parameter);

            $mxfunction = new matrix($valfunction);
            $mxderivative = new matrix($valderivative);

            $mxfunction = $mxfunction->transpose();

            // If the determinant is null, we already found the value.
            if ($mxderivative->determinant() == 0) {
                return array_combine($parameternames, $parameter);
            }

            $mxderivativeinv = $mxderivative->inverse();

            // Calculate the new point $mx_parameter as well as the distance.
            $mxdelta = $mxderivativeinv->multiply($mxfunction);
            $mxparameteralt = $mxparameter;
            $distance = $mxdelta->rooted_summed_squares();

            // TODO: If used like this, the reduction of the $mxdelta value can
            // prevent the trusted regions filter to be applied, because with the
            // reduced delta we might not leave the trusted region. If we still
            // want to use this code, it has to be refactored. Maybe the
            // reduction should happen after application of the trusted region
            // filter or we add a $maxsteplength argument and let the calling
            // function set it.
            if ($distance >= $maxsteplength) {
                // Shorten step matrix $mx_delta to concurrent step length.
                $mxdelta = $mxdelta->multiply($maxsteplength / $distance);
            } else {
                // Set new $max_step_length.
                $maxsteplength = $distance;
            }

            $mxparameter = $mxparameter->subtract($mxdelta);
            $parameter = array_combine($parameternames, ($mxparameter->transpose())[0]);

            // If Trusted Region filter is provided, check for being still in Trusted Regions.
            if (isset($fntrfilter)) {
                // Check for glitches within the calculated result.
                if (count(array_filter($parameter, fn ($x) => is_nan($x))) > 0) {
                    $parameter = $fntrfilter($parameter); // DAVID: Darüber sollten wir noch einmal nachdenken.
                    $iscritical = true;
                    return array_combine($parameternames, $parameter);
                }

                // Check if $parameter is still in the Trusted Region.
                if ($fntrfilter($parameter) !== $parameter) {
                    $parameter = $fntrfilter($parameter);
                    // DAVID: Sollte serialisiert werden für den Fall genesteter Arrays.
                    $mxparameter = new matrix(is_array($parameter) ? [$parameter] : [[$parameter]]);
                    $mxparameter = $mxparameter->transpose();

                    // If Trusted Region function and its derivative are provided, add them to $fn_function and $fn_derivative.
                    if (isset($fntrfunction) && isset($fntrderivative) && ! $usegauss) {
                        $fnfunction = fn($x) => matrixcat::multi_sum($fntrfunction($x), $fnfunction($x));
                        $fnderivative = fn($x) => matrixcat::multi_sum($fntrderivative($x), $fnderivative($x));
                        $usegauss = true;
                    }

                    // If the problem occurs a second time in a row...
                    // ... additionally reset the parameter $parameter to $parameter_start.
                    if ($iscritical) {
                        $parameter = $parameterstart;
                        // DAVID: Sollte serialisiert werden für den Fall genesteter Arrays.
                        $mxparameter = new matrix(is_array($parameter) ? [$parameter] : [[$parameter]]);
                        $mxparameter = $mxparameter->transpose();
                    }
                } else {
                    // If everything went fine, keep/reset $is_critical as FALSE.
                    $iscritical = false;
                }
            }
            // Test if precisiion criteria for stopping iterations has been reached.
            if ($mxdelta->max_absolute_element() < 10 ** (-$precission)) {
                return $parameter;
            }
        }
        // Return the concurrent solution even the precission criteria hasn't been met.
        return $parameter;
    }

    /**
     * Performs the Gradient Ascent approach for determine the maximum of a function
     *
     * @param callable $fnfunction - Function to be calculated on with parameter $parameter
     * @param callable $fnderivative - Derivative of $fn_function with parameter $parameter
     * @param array $parameterstart - Parameter-set to start with (should be near zero point)
     * @param int $precission - Accuracy to how many decimal places
     * @param int $maxiterations - Maximum number of iterations
     * @param callable|null $fnparameterrestrictions - Parameter-check for trusted Region
     * @param callable|null $fneapestimator - EAP-Estimator (bell curve) function
     * @param callable|null $fneapestimatorderivative1st - Deriavative of $fneapestimator
     *
     * @return array
     *
     */
    public static function gradient_ascent(
        callable $fnfunction,
        callable $fnderivative,
        array $parameterstart,
        int $precission = 6,
        int $maxiterations = 50,
        ?callable $fnparameterrestrictions = null,
        ?callable $fneapestimator = null,
        ?callable $fneapestimatorderivative1st = null): array {

        // Set initial values.
        $parameter = $parameterstart;
        $parameterstructure = self::array_to_vector($parameter);
        $steplength = 1;

        // Calculate the function values from $fn_function for current $parameter.
        $valfunction = $fnfunction(self::vector_to_array($parameter, $parameterstructure));

        // Begin with numerical iteration.
        for ($i = 0; $i < $maxiterations; $i++) {

            $mxparameter = new matrix($parameter);

            // Calculate the derivative values from $fn_derivative for current $parameter.
            $valderivative = $fnderivative(self::vector_to_array($parameter, $parameterstructure));

            $mxgradient = new matrix($valderivative);
            $gradientlength = $mxgradient->rooted_summed_squares();

            debugging ('Iteration i: '.$i.'
            Position: '.print_r($parameter, true).'
            Gradient: '.print_r($mxgradient, true).'
            Length: '.$gradientlength.'
            Step Length: '.$steplength, DEBUG_DEVELOPER);
            if ($gradientlength == 0.0) {
                // There is nothing to climb on anymore. Quit the job.
                return self::vector_to_array($parameter, $parameterstructure);
            }

            $mxparameternew = $mxparameter->add($mxgradient->multiply($steplength / $gradientlength));
            $parameternew = ((array) $mxparameternew)[0];
            $valfunctionnew = $fnfunction(self::vector_to_array($parameternew, $parameterstructure));

            // Perform adaptive line search for step length.
            if ($valfunctionnew > $valfunction) {
                // Double step length.

                while ($valfunctionnew > $valfunction) {
                    $valfunction = $valfunctionnew;
                    $parameter = $parameternew;
                    $steplength *= 2;

                    $mxparameternew = $mxparameter->add($mxgradient->multiply($steplength / $gradientlength));
                    $parameternew = ((array) $mxparameternew)[0];
                    $valfunctionnew = $fnfunction(self::vector_to_array($parameternew, $parameterstructure));
                }
                $steplength /= 2;
            } else {
                // Cut step length to half and try again.

                while ($valfunctionnew <= $valfunction && $steplength > 10 ** (-$precission)) {
                    $parameter = $parameternew;
                    $steplength /= 2;

                    $mxparameternew = $mxparameter->add($mxgradient->multiply($steplength / $gradientlength));
                    $parameternew = ((array)$mxparameternew)[0];
                    $valfunctionnew = $fnfunction(self::vector_to_array($parameternew, $parameterstructure));
                }
                $parameter = $parameternew;
                $valfunction = $valfunctionnew;
            }

            // Test if precisiion criteria for stopping iterations has been reached.
            if ($steplength < 10 ** (-$precission)) {
                return self::vector_to_array($parameter, $parameterstructure);
            }
        }
        // Return the concurrent solution even the precission criteria hasn't been met.
        return self::vector_to_array($parameter, $parameterstructure);
    }

    /**
     * Converts item parameters from an array to a vector
     *
     * @param array|float $data - array or float to be transformed into a serialized vevtor
     * @param int $n - just ignore that, it's for the recursion
     *
     * @return array - structure of the given array, needed for restoring by vector_to_array
     */
    public static function array_to_vector(&$data, int &$n = 0): array {
        // NOTE: The operation will be done directly on $data, so work with a copy!

        if (is_array($data) && count($data) > 0) {

            // Handle all arrays given.
            $datatmp = [];
            $structure = [];
            foreach ($data as $key => $val) {

                if (is_array($val) && count($val) > 0) {

                    // Analyse further recursively.
                    $structuretmp = self::array_to_vector($val, $n);

                    // Test if result is legid.
                    if (is_null($structuretmp)) {
                        // TODO: Here should be some error/warning handling be done.
                        return [];
                    }

                    // Perpare results.
                    $structure[$key] = $structuretmp;
                    $datatmp = array_merge($datatmp, $val);
                } else if (is_numeric($val)) {

                    // Give back part of the array and structure, also increment $n.
                    $datatmp[$n] = floatval($val);
                    $structure[$key] = $n;
                    $n += 1;
                } else {

                    // Handle any other cases, like strings or objects.
                    // TODO: Throw error warning and exit with null.
                    return [];
                }
            }

            // Overwrite $data and return $structure.
            $data = $datatmp;
            return $structure;
        } else if (is_numeric($data)) {

            // Handle the case that something like a float is given instead.
            $structure = $n;
            $data = [$n => $data];
            $n += 1;
            return $structure;
        }

        debugging('not float or array given in method array_to_vector', DEBUG_DEVELOPER);
        return [];
    }

    /**
     * Converts item parameters from a vector to an array or float
     *
     * @param array $data - the vector to be restored
     * @param mixed $structure - the array structure given by array_to_vector
     *
     * @return array - the restored array or float
     */
    public static function vector_to_array(array $data, $structure): array {

        if (is_array($structure)) {

            // Handle arrays.
            $datatmp = [];
            foreach ($structure as $key => $val) {

                if (is_array($val)) {

                    $datatmp[$key] = self::vector_to_array($data, $val);
                } else if (is_int($val)) {

                    $datatmp[$key] = $data[$val];
                }
            }
            return $datatmp;
        } else if (is_int($structure)) {

            // Handle floats or anything like it.
            if (array_key_exists($structure, $data)) {

                // Give back just the value.
                return $data[$structure];
            } else {

                debugging('given structure array does not match vector in vector_to_array', DEBUG_DEVELOPER);
                return [];
            }
        }

        debugging('corrupted structure array given in vector_to_array', DEBUG_DEVELOPER);
        return [];
    }
}