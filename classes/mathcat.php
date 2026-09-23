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
     * Maximises a function using the BFGS quasi-Newton algorithm.
     *
     * The derivative must return the gradient in the same logical parameter
     * order as the start parameters. Nested numeric parameter arrays are
     * flattened and restored through {@see self::array_to_vector()}.
     *
     * @param callable $fnfunction Objective function to maximise.
     * @param callable $fnderivative First derivative (gradient) of the objective function.
     * @param array $parameterstart Parameter set to start with.
     * @param int $precision Accuracy in decimal places used as convergence threshold.
     * @param int $maxiterations Maximum number of iterations.
     * @param callable|null $fnparameterrestrictions Optional projection to the trusted parameter region.
     * @param callable|null $fnmapestimator Optional additive MAP objective term.
     * @param callable|null $fnmapestimatorderivative1st Optional gradient of the MAP objective term.
     * @return array Optimised parameters in the same structure as $parameterstart.
     */
    public static function bfgs(
        callable $fnfunction,
        callable $fnderivative,
        array $parameterstart,
        int $precision = 6,
        int $maxiterations = 100,
        ?callable $fnparameterrestrictions = null,
        ?callable $fnmapestimator = null,
        ?callable $fnmapestimatorderivative1st = null
    ): array {
        if (($fnmapestimator === null) !== ($fnmapestimatorderivative1st === null)) {
            throw new \InvalidArgumentException('MAP objective and derivative must either both be provided or both be null.');
        }

        $parameter = $parameterstart;
        $parameterstructure = self::array_to_vector($parameter);
        if ($parameter === []) {
            return [];
        }
        $parameter = array_values($parameter);
        $dimensions = count($parameter);
        $tolerance = 10 ** (-$precision);
        $inversehessian = matrix::identity_array($dimensions);

        $evaluate = static function (array $vector) use (
            $fnfunction,
            $fnderivative,
            $parameterstructure,
            $fnmapestimator,
            $fnmapestimatorderivative1st
        ): array {
            $structured = self::vector_to_array($vector, $parameterstructure);
            $value = (float) $fnfunction($structured);
            $gradient = $fnderivative($structured);

            if ($fnmapestimator !== null) {
                $value += (float) $fnmapestimator($structured);
                $mapgradient = $fnmapestimatorderivative1st($structured);
            } else {
                $mapgradient = null;
            }

            $gradientvector = $gradient;
            self::array_to_vector($gradientvector);
            $gradientvector = array_values($gradientvector);

            if ($mapgradient !== null) {
                $mapgradientvector = $mapgradient;
                self::array_to_vector($mapgradientvector);
                $mapgradientvector = array_values($mapgradientvector);
                if (count($mapgradientvector) !== count($gradientvector)) {
                    throw new \InvalidArgumentException('MAP gradient dimension does not match objective gradient dimension.');
                }
                foreach ($gradientvector as $index => $gradientvalue) {
                    $gradientvector[$index] = $gradientvalue + $mapgradientvector[$index];
                }
            }

            return [$value, $gradientvector];
        };

        $applyrestrictions = static function (array $vector) use ($fnparameterrestrictions, $parameterstructure): array {
            if ($fnparameterrestrictions === null) {
                return $vector;
            }
            $structured = self::vector_to_array($vector, $parameterstructure);
            $structured = $fnparameterrestrictions($structured);
            $restricted = $structured;
            self::array_to_vector($restricted);
            return array_values($restricted);
        };

        [$value, $gradient] = $evaluate($parameter);
        if (count($gradient) !== $dimensions) {
            throw new \InvalidArgumentException('Gradient dimension does not match parameter dimension.');
        }

        for ($iteration = 0; $iteration < $maxiterations; $iteration++) {
            if (matrix::max_absolute_value($gradient) <= $tolerance) {
                return self::vector_to_array($parameter, $parameterstructure);
            }

            $direction = matrix::matrix_vector_product($inversehessian, $gradient);
            $directionalderivative = matrix::dot_product($gradient, $direction);

            // A valid inverse negative-Hessian approximation must yield an ascent direction.
            if (!is_finite($directionalderivative) || $directionalderivative <= 0.0) {
                $inversehessian = matrix::identity_array($dimensions);
                $direction = $gradient;
                $directionalderivative = matrix::dot_product($gradient, $direction);
            }

            $steplength = 1.0;
            $candidate = $parameter;
            $candidatevalue = $value;
            $candidategradient = $gradient;
            $accepted = false;

            // Armijo backtracking line search for maximisation.
            while ($steplength >= $tolerance) {
                $trial = [];
                foreach ($parameter as $index => $parametervalue) {
                    $trial[$index] = $parametervalue + $steplength * $direction[$index];
                }
                $trial = $applyrestrictions($trial);
                $step = matrix::vector_subtract($trial, $parameter);
                if (matrix::max_absolute_value($step) <= $tolerance) {
                    $steplength *= 0.5;
                    continue;
                }

                [$trialvalue, $trialgradient] = $evaluate($trial);
                $actualdirectionalderivative = matrix::dot_product($gradient, $step);
                if ($trialvalue >= $value + 1e-4 * $actualdirectionalderivative) {
                    $candidate = $trial;
                    $candidatevalue = $trialvalue;
                    $candidategradient = $trialgradient;
                    $accepted = true;
                    break;
                }
                $steplength *= 0.5;
            }

            if (!$accepted) {
                return self::vector_to_array($parameter, $parameterstructure);
            }

            $step = matrix::vector_subtract($candidate, $parameter);
            // Standard inverse-BFGS update applied to -f: y = grad(-f)new - grad(-f)old.
            $y = matrix::vector_subtract($gradient, $candidategradient);
            $ys = matrix::dot_product($y, $step);

            if (is_finite($ys) && $ys > 1e-12) {
                $hy = matrix::matrix_vector_product($inversehessian, $y);
                $yhy = matrix::dot_product($y, $hy);
                $coefficient = ($ys + $yhy) / ($ys * $ys);
                $updated = $inversehessian;
                for ($row = 0; $row < $dimensions; $row++) {
                    for ($col = 0; $col < $dimensions; $col++) {
                        $updated[$row][$col] += $coefficient * $step[$row] * $step[$col]
                            - (($hy[$row] * $step[$col]) + ($step[$row] * $hy[$col])) / $ys;
                    }
                }
                $inversehessian = $updated;
            } else {
                // Curvature information is not usable; restart with a neutral approximation.
                $inversehessian = matrix::identity_array($dimensions);
            }

            $parameter = $candidate;
            $value = $candidatevalue;
            $gradient = $candidategradient;

            if (matrix::max_absolute_value($step) <= $tolerance) {
                break;
            }
        }

        return self::vector_to_array($parameter, $parameterstructure);
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
    public static function newton_raphson(
        callable $fnfunction,
        callable $fnderivative,
        array $parameterstart,
        int $precission = 6,
        int $maxiterations = 500,
        ?callable $fntrfilter = null,
        ?callable $fntrfunction = null,
        ?callable $fntrderivative = null
    ): array {

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
     * Maximises a function using normalised gradient ascent with adaptive line search.
     *
     * @param callable $fnfunction Objective function to maximise.
     * @param callable $fnderivative First derivative (gradient) of the objective function.
     * @param array $parameterstart Parameter set to start with.
     * @param int $precision Accuracy in decimal places used as convergence threshold.
     * @param int $maxiterations Maximum number of iterations.
     * @param callable|null $fnparameterrestrictions Optional projection to the trusted parameter region.
     * @param callable|null $fnmapestimator Optional additive MAP objective term.
     * @param callable|null $fnmapestimatorderivative1st Optional gradient of the MAP objective term.
     * @return array Optimised parameters in the same structure as $parameterstart.
     */
    public static function gradient_ascent(
        callable $fnfunction,
        callable $fnderivative,
        array $parameterstart,
        int $precision = 6,
        int $maxiterations = 50,
        ?callable $fnparameterrestrictions = null,
        ?callable $fnmapestimator = null,
        ?callable $fnmapestimatorderivative1st = null
    ): array {
        if (($fnmapestimator === null) !== ($fnmapestimatorderivative1st === null)) {
            throw new \InvalidArgumentException('MAP objective and derivative must either both be provided or both be null.');
        }

        $parameter = $parameterstart;
        $parameterstructure = self::array_to_vector($parameter);
        if ($parameter === []) {
            return [];
        }
        $parameter = array_values($parameter);
        $tolerance = 10 ** (-$precision);
        $steplength = 1.0;

        $evaluate = static function (array $vector) use (
            $fnfunction,
            $fnderivative,
            $parameterstructure,
            $fnmapestimator,
            $fnmapestimatorderivative1st
        ): array {
            $structured = self::vector_to_array($vector, $parameterstructure);
            $value = (float) $fnfunction($structured);
            $gradient = $fnderivative($structured);
            if ($fnmapestimator !== null) {
                $value += (float) $fnmapestimator($structured);
                $mapgradient = $fnmapestimatorderivative1st($structured);
            } else {
                $mapgradient = null;
            }

            $gradientvector = $gradient;
            self::array_to_vector($gradientvector);
            $gradientvector = array_values($gradientvector);
            if ($mapgradient !== null) {
                $mapgradientvector = $mapgradient;
                self::array_to_vector($mapgradientvector);
                $mapgradientvector = array_values($mapgradientvector);
                if (count($mapgradientvector) !== count($gradientvector)) {
                    throw new \InvalidArgumentException('MAP gradient dimension does not match objective gradient dimension.');
                }
                foreach ($gradientvector as $index => $gradientvalue) {
                    $gradientvector[$index] = $gradientvalue + $mapgradientvector[$index];
                }
            }
            return [$value, $gradientvector];
        };

        $applyrestrictions = static function (array $vector) use ($fnparameterrestrictions, $parameterstructure): array {
            if ($fnparameterrestrictions === null) {
                return $vector;
            }
            $structured = self::vector_to_array($vector, $parameterstructure);
            $structured = $fnparameterrestrictions($structured);
            $restricted = $structured;
            self::array_to_vector($restricted);
            return array_values($restricted);
        };

        [$value, $gradient] = $evaluate($parameter);
        if (count($gradient) !== count($parameter)) {
            throw new \InvalidArgumentException('Gradient dimension does not match parameter dimension.');
        }

        for ($iteration = 0; $iteration < $maxiterations; $iteration++) {
            $gradientlength = sqrt(matrix::dot_product($gradient, $gradient));
            if (!is_finite($gradientlength) || $gradientlength <= $tolerance) {
                break;
            }

            $direction = array_map(static fn($value) => $value / $gradientlength, $gradient);
            $trialstep = $steplength;
            $accepted = false;
            $bestparameter = $parameter;
            $bestvalue = $value;
            $bestgradient = $gradient;

            // First find an improving step, reducing the step length as necessary.
            while ($trialstep >= $tolerance) {
                $trial = [];
                foreach ($parameter as $index => $parametervalue) {
                    $trial[$index] = $parametervalue + $trialstep * $direction[$index];
                }
                $trial = $applyrestrictions($trial);
                [$trialvalue, $trialgradient] = $evaluate($trial);
                if ($trialvalue > $value) {
                    $bestparameter = $trial;
                    $bestvalue = $trialvalue;
                    $bestgradient = $trialgradient;
                    $accepted = true;
                    break;
                }
                $trialstep *= 0.5;
            }

            if (!$accepted) {
                break;
            }

            // Expand while the same direction still improves the objective.
            while (true) {
                $expandedstep = $trialstep * 2.0;
                $trial = [];
                foreach ($parameter as $index => $parametervalue) {
                    $trial[$index] = $parametervalue + $expandedstep * $direction[$index];
                }
                $trial = $applyrestrictions($trial);
                [$trialvalue, $trialgradient] = $evaluate($trial);
                if ($trialvalue <= $bestvalue) {
                    break;
                }
                $trialstep = $expandedstep;
                $bestparameter = $trial;
                $bestvalue = $trialvalue;
                $bestgradient = $trialgradient;
            }

            $step = matrix::vector_subtract($bestparameter, $parameter);
            $parameter = $bestparameter;
            $value = $bestvalue;
            $gradient = $bestgradient;
            $steplength = $trialstep;

            if (matrix::max_absolute_value($step) <= $tolerance) {
                break;
            }
        }

        return self::vector_to_array($parameter, $parameterstructure);
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
    private static function add_gauss_der1(callable $func, $mean, $std) {

        $gaussian = function ($x) use ($mean, $std) {
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

        $gaussian = function ($x) use ($mean, $std) {
            return 1 * self::gaussian_density_derivative2($x, $mean, $std);
        };
        $newfunc = self::compose_plus($func, $gaussian);
        return $newfunc;
    }

    /**
     * REturns compose plus (functions).
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
