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
     * Returns gaussian density.
     *
     * @param mixed $x
     * @param mixed $mean
     * @param mixed $stddeviation
     *
     * @return mixed
     *
     */
    public static function gaussian_density($x, $mean, $stddeviation) {
        $factor1 = 1 / sqrt(2 * M_PI * pow($stddeviation, 2));
        $factor2 = exp(-pow($x - $mean, 2) / (2 * pow($stddeviation, 2)));
        return $factor1 * $factor2;
    }

    /**
     * Returns gaussian density derivative1 value.
     *
     * @param mixed $x
     * @param mixed $m
     * @param mixed $std
     *
     * @return mixed
     *
     */
    public static function gaussian_density_derivative1($x, $m, $std) {

        return (exp(-(($m - $x) ** 2 / (2 * $std ** 2))) * ($m - $x)) / (sqrt(2 * M_PI) * $std ** 3);
    }

    /**
     * Returns gaussian density derivative2.
     *
     * @param mixed $x
     * @param mixed $m
     * @param mixed $std
     *
     * @return mixed
     *
     */
    public static function gaussian_density_derivative2($x, $m, $std) {
        return (exp(-(($m - $x) ** 2 / (2 * $std ** 2)))
            * ($m ** 2 - $std ** 2 - 2 * $m * $x + $x ** 2)) / (sqrt(2 * M_PI) * $std ** 5);
    }

    /**
     * Returns numerical derivative.
     *
     * @param callable $func
     * @param float $h
     *
     * @return mixed
     *
     */
    public static function get_numerical_derivative(callable $func, float $h = 1e-5) {
        $returnfn = function ($x) use ($func, $h) {
            return ($func($x + $h) - $func($x)) / $h;
        };
        return $returnfn;
    }

    /**
     * Returns numerical derivative2.
     *
     * @param callable $func
     * @param float $h
     *
     * @return mixed
     *
     */
    public static function get_numerical_derivative2(callable $func, float $h = 1e-6) {
        $returnfn = function ($x) use ($func, $h) {
            return ($func($x + $h) - $func($x - $h)) / (2 * $h);
        };
        return $returnfn;
    }

    /**
     * Returns numerical gradient.
     *
     * @param callable $func
     * @param mixed $point
     * @param mixed $delta
     *
     * @return array
     *
     */
    public static function gradient(callable $func, $point, $delta = 1e-5) {
        $grad = [];
        for ($i = 0; $i < count($point); $i++) {
            $pointplusdelta = $point;
            $pointminusdelta = $point;
            $pointplusdelta[$i] += $delta;
            $pointminusdelta[$i] -= $delta;
            $grad[$i] = ($func($pointplusdelta) - $func($pointminusdelta)) / (2 * $delta);
        }
        return $grad;
    }

    /**
     * Returns matrix vector product.
     *
     * @param mixed $matrix
     * @param mixed $vector
     *
     * @return array
     *
     */
    public static function matrix_vector_product($matrix, $vector) {
        $result = [];
        for ($i = 0; $i < count($matrix); $i++) {
            $result[$i] = 0;
            for ($j = 0; $j < count($matrix[$i]); $j++) {
                $result[$i] += $matrix[$i][$j] * $vector[$j];
            }
        }
        return $result;
    }

    /**
     * Returns bfgs value.
     *
     * @param callable $func
     * @param mixed $startpoint
     * @param mixed $stepsize
     * @param mixed $tolerance
     * @param int $maxiterations
     *
     * @return mixed
     *
     */
    public static function bfgs(callable $func, $startpoint, $stepsize = 0.01, $tolerance = 1e-6, $maxiterations = 1000) {
        $n = count($startpoint);
        $currentpoint = $startpoint;
        $iteration = 0;
        $h = [];

        // Initialize H with the identity matrix.
        for ($i = 0; $i < $n; $i++) {
            $h[$i] = [];
            for ($j = 0; $j < $n; $j++) {
                $h[$i][$j] = $i == $j ? 1 : 0;
            }
        }

        while ($iteration < $maxiterations) {
            $grad = self::gradient($func, $currentpoint);
            $direction = self::matrix_vector_product($h, $grad);

            for ($i = 0; $i < $n; $i++) {
                $direction[$i] = -$direction[$i];
            }

            // Line search with constant step size.
            $nextpoint = [];
            for ($i = 0; $i < $n; $i++) {
                $nextpoint[$i] = $currentpoint[$i] + $stepsize * $direction[$i];
            }

            // Update H using BFGS formula.
            $s = [];
            $y = [];
            for ($i = 0; $i < $n; $i++) {
                $s[$i] = $nextpoint[$i] - $currentpoint[$i];
                $y[$i] = self::gradient($func, $nextpoint)[$i] - $grad[$i];
            }

            $rho = 1 / array_sum(array_map(function ($yi, $si) {
                    return $yi * $si;
            }, $y, $s));

            $i = [];
            for ($i = 0; $i < $n; $i++) {
                $i[$i] = [];
                for ($j = 0; $j < $n; $j++) {
                    $i[$i][$j] = $i == $j ? 1 : 0;
                }
            }

            $a1 = [];
            for ($i = 0; $i < $n; $i++) {
                $a1[$i] = [];
                for ($j = 0; $j < $n; $j++) {
                    $a1[$i][$j] = $i[$i][$j] - $rho * $s[$i] * $y[$j];
                }
            }

            $a2 = [];
            for ($i = 0; $i < $n; $i++) {
                $a2[$i] = [];
                for ($j = 0; $j < $n; $j++) {
                    $a2[$i][$j] = $i[$i][$j] - $rho * $y[$i] * $s[$j];
                }
            }

            $hnew = [];
            for ($i = 0; $i < $n; $i++) {
                $hnew[$i] = [];
                for ($j = 0; $j < $n; $j++) {
                    $hnew[$i][$j] = $a1[$i][$j] * $h[$j][$i] * $a2[$j][$i] + $rho * $s[$i] * $s[$j];
                }
            }

            $h = $hnew;

            // Check for convergence.
            $diff = 0;
            for ($i = 0; $i < count($currentpoint); $i++) {
                $diff += abs($nextpoint[$i] - $currentpoint[$i]);
            }

            if ($diff < $tolerance) {
                break;
            }

            $currentpoint = $nextpoint;
            $iteration++;
        }

        return $currentpoint;
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

            // Throws error Object of class Closure can not be converted to float.
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
        int $precission = 2,
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

            if ($gradientlength == 0.0) {
                // There is nothing to climb on anymore. Quit the job.
                return self::vector_to_array($parameter, $parameterstructure);
            }

            do {
                // Climb when function value rises, cut steplength to half and try again otherwise.
                $mxdelta = $mxgradient->multiply($steplength / $gradientlength);
                $mxparameternew = $mxparameter->add($mxdelta);
                $parameternew = (array) $mxparameternew;
                $parameternew = $parameternew[0];
                $valfunctionnew = $fnfunction(self::vector_to_array($parameternew, $parameterstructure));
                debugging('Parameter: '.print_r($parameternew, true), DEBUG_DEVELOPER);

                if ($valfunctionnew - $valfunction <= 0) {
                    $steplength /= 2; // Cut steptlength to half.
                }
            } while ($valfunctionnew - $valfunction <= 0 && $steplength > 10 ** (-$precission));

            $parameter = $parameternew;
            $valfunction = $valfunctionnew;

            // Test if precisiion criteria for stopping iterations has been reached.
            if ($steplength < 10 ** (-$precission)) {
                return self::vector_to_array($parameter, $parameterstructure);
            }
        }
        // Return the concurrent solution even the precission criteria hasn't been met.
        return self::vector_to_array($parameter, $parameterstructure);
    }

    // Deprecated, falls bfgs nicht genutzt wird.

    /**
     * Returns add gauss der1 callable.
     *
     * @param callable $func
     * @param mixed $mean
     * @param mixed $std
     *
     * @return callable
     *
     */
    private static function add_gauss_der1(callable $func, $mean, $std) {

        $gaussian = function($x) use ($mean, $std)  {
            return 1 * self::gaussian_density_derivative1($x, $mean, $std);
        };
        $newfunc = self::compose_plus($func, $gaussian);
        return $newfunc;
    }

    /**
     * Returns add gauss der1 callable.
     *
     * @param callable $func
     * @param mixed $mean
     * @param mixed $std
     *
     * @return callable
     *
     */
    private static function add_gauss_der2(callable $func, $mean, $std) {

        $gaussian = function($x) use ($mean, $std)  {
            return 1 * self::gaussian_density_derivative2($x, $mean, $std);
        };
        $newfunc = self::compose_plus($func, $gaussian);
        return $newfunc;
    }

    /**
     * Returns compose plus (functions).
     *
     * @param mixed $function1
     * @param mixed $function2
     *
     * @return mixed
     *
     */
    public static function compose_plus($function1, $function2) {
        $returnfn = function ($x) use ($function1, $function2) {
            return $function1($x) + $function2($x);
        };
        return $returnfn;
    }

    /**
     * Returns compose multiply (functions).
     *
     * @param mixed $function1
     * @param mixed $function2
     *
     * @return mixed
     *
     */
    public static function compose_multiply($function1, $function2) {
        $returnfn = function ($x) use ($function1, $function2) {
            return $function1($x) * $function2($x);
        };
        return $returnfn;
    }

    /**
     * Returns compose chain (functions).
     *
     * @param mixed $function1
     * @param mixed $function2
     *
     * @return mixed
     *
     */
    public static function compose_chain($function1, $function2) {
        $returnfn = function ($x) use ($function1, $function2) {
            return $function1($function2);
        };
        return $returnfn;
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
