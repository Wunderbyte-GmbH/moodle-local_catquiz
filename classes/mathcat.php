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
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

/**
 * Class for math functions.
 *
 * @package local_catquiz
 * @author Daniel Pasterk
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mathcat {
    /**
     * Returns newton raphson stable value.
     *
     * @param mixed $func
     * @param mixed $derivative
     * @param int $start
     * @param mixed $mininc
     * @param int $maxiter
     * @param int $max
     *
     * @return float
     *
     */
    public static function newtonraphson_stable(
        $func,
        $derivative,
        $start = 0,
        $mininc = 0.0001,
        $maxiter = 150,
        $max = 50
    ): float {

        // @DAVID: Ersetzen durch Newton-Raphson-multi-stable. Aktueller Aufruf:
        return self::newton_raphson_multi_stable(
            $func,
            $derivative,
            [$start],
            6,
            50
        );

            //-runden(log($mininc),10),0), $maxiter); // Plus Filter und 1./2. Ableitung
        // Wenn erfolgreich, dann bitte diese Funktion als deprecated behandeln und entfernen.
        
        $x0 = $start;
        $usegauss = false;
        $gaussiter = 0;

        $m = 0;
        $std = 0.5;

        for ($n = 1; $n < $maxiter; $n++) {
            $diff = 0;

            if ($usegauss == true) {

                $gaussiter += 1;
                if ($gaussiter % 10 == 0) {
                    $func = self::compose_plus($func, function($x) use ($n, $m, $std) {
                        return 1 * mathcat::gaussian_density_derivative1($x, $m, $std);
                    });

                    $derivative = self::compose_plus($derivative, function($x) use ($n, $m, $std) {
                        return 1 * mathcat::gaussian_density_derivative2($x, $m, $std);
                    });
                    // $z_0 = $m;
                    // $use_gauss = false;
                }
            }

            if ($derivative($x0) != 0) {
                $diff = -$func($x0) / ($derivative($x0));
            } else {
                $usegauss = true;
                $x0 = 0;
            }

            // $diff  = - $func($x_0) / ($derivative($x_0)+0.001);
            // $diff = -$func($x_0) / ($derivative($x_0) + 0.00000001);
            // echo "Iteration:" . $n . "and diff: " . $diff . " x_0=" . $x_0 . " value: ". $func($x_0)  . "<br>";
            $x0 += $diff;

            // Restrict values to [-$max, $max] and stop if we get outside that interval.
            if (abs($x0) > $max) {
                if ($x0 > 0) {
                    return $max;
                }
                return -$max;
            }

            if (abs($diff) > 10) {
                $usegauss = true;
                $x0 = 0;
            }

            if ($n == $maxiter) {  // Debug!
                echo "not converging!";
            }

            if (abs($diff) < $mininc) {
                break;
            }
        }
        return $x0;
    }

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
        // $factor1 = -($x - $mean) / pow($stdDeviation, 2);
        // $factor2 = exp(-pow($x - $mean, 2) / (2 * pow($stdDeviation, 2)));
        // return $factor1 * $factor2;

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
        return (exp(-(($m - $x) ** 2 / (2 * $std ** 2))) * ($m ** 2 - $std ** 2 - 2 * $m * $x + $x ** 2)) / (sqrt(2 * M_PI) * $std ** 5);
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
        $h = array();

        // Initialize H with the identity matrix.
        for ($i = 0; $i < $n; $i++) {
            $h[$i] = array();
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
                $i[$i] = array();
                for ($j = 0; $j < $n; $j++) {
                    $i[$i][$j] = $i == $j ? 1 : 0;
                }
            }

            $a1 = [];
            for ($i = 0; $i < $n; $i++) {
                $a1[$i] = array();
                for ($j = 0; $j < $n; $j++) {
                    $a1[$i][$j] = $i[$i][$j] - $rho * $s[$i] * $y[$j];
                }
            }

            $a2 = [];
            for ($i = 0; $i < $n; $i++) {
                $a2[$i] = array();
                for ($j = 0; $j < $n; $j++) {
                    $a2[$i][$j] = $i[$i][$j] - $rho * $y[$i] * $s[$j];
                }
            }

            $hnew = [];
            for ($i = 0; $i < $n; $i++) {
                $hnew[$i] = array();
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
     * @param callable<array> $fn_function($parameter) - Function to be calculated on with parameter $parameter
     * @param callable<array> $fn_derivative($parameter) - Deriavative of $fn_function with parameter $parameter
     * @param array $parameter_start - Parameter-set to start with (should be near zero point)
     * @param int $precission - Accuracy to how many decimal places
     * @param int $max_iteration - Maximum number of iterations
     * @param callable<array> $fn_trusted_regions_filter($parameter) - Parameter-check for trusted Region
     * @param callable<array> $fn_trusted_regions_function($parameter) - Trusted Region modelling function
     * @param callable<array> $fn_trusted_regions_derivative($parameter) - Deriavative of $fn_trusted_regions_function
     *
     * @return array $parameter
     */
    static function newton_raphson_multi_stable (
        callable $fn_function, // @DAVID: Hier werden nun Callables erwartet, die Arrays zurückgeben, NICHT Arrays of Callables
        callable $fn_derivative,
        array $parameter_start,
        int $precission = 6,
        int $max_iterations = 50,
        callable $fn_trusted_regions_filter = NULL,
        callable $fn_trusted_regions_function = NULL,
        callable $fn_trusted_regions_derivative = NULL): array {
        
        // Set initial values.
        $parameter = $parameter_start;
        $parameter_names = array_keys($parameter_start); // Note: Please check for yourself, that the order of your parameters in your array corresponds to the order of $fn_function!
        $is_critical = false;
        $max_step_length = 0.1;
        
        // Begin with numerical iteration.
        for ($i = 0; $i < $max_iterations; $i++) {
            
            $mx_parameter = new matrix($parameter); // @DAVID: Sollte serialisiert werden für den Fall genesteter Arrays. array('diffultiy' => array ( 0...6), 'discrimination' => float);
            $mx_parameter = $mx_parameter->transpose();
            
            // Calculate the function and derivative values from  $fn_function and $fn_derivative at point $parameter.
            $val_function = $fn_function($parameter);
            $val_derivative = $fn_derivative($parameter);
            
            // Throws error Object of class Closure can not be converted to float.
            $mx_function = new matrix($val_function);
            $mx_derivative =  new matrix($val_derivative);
            
            $mx_function = $mx_function->transpose(); 
            
            // If the determinant is null, we already found the value.
            if ($mx_derivative->determinant() == 0) {
                return array_combine($parameter_names, $parameter);
            }

            $mx_derivative_inv = $mx_derivative->inverse();
            
            // Calculate the new point $mx_parameter as well as the distance 
            $mx_delta = $mx_derivative_inv->multiply($mx_function);
            $mx_parameter_alt = $mx_parameter;
            $distance = $mx_delta->rooted_summed_squares();
            
            if ($distance >= $max_step_length) {
                // Shorten step matrix $mx_delta to concurrent step length.
                $mx_delta = $mx_delta->multiply($max_step_length / $distance);
            } else {
                // Set new $max_step_length.
                $max_step_length = $distance;
            }
            
            $mx_parameter = $mx_parameter->subtract($mx_delta);
            $parameter = array_combine($parameter_names, ($mx_parameter->transpose())[0]);

            // If Trusted Region filter is provided, check for being still in Trusted Regions.
            if (isset($fn_trusted_regions_filter)) {
                // Check for glitches within the calculated result.
                if (count(array_filter($parameter, fn ($x) => is_nan($x))) > 0) {
                    $parameter = $fn_trusted_regions_filter($parameter); // @DAVID: Darüber sollten wir noch einmal nachdenken.
                    $is_critical = true;
                    return array_combine($parameter_names, $parameter);
                }

                // Check if $parameter is still in the Trusted Region.
                if ($fn_trusted_regions_filter($parameter) !== $parameter) { 
                    $parameter = $fn_trusted_regions_filter($parameter);
                    $mx_parameter = new matrix(is_array($parameter) ? [$parameter] : [[$parameter]]); // @DAVID: Sollte serialisiert werden für den Fall genesteter Arrays.
                    $mx_parameter = $mx_parameter->transpose();
                    
                    // If Trusted Region function and its derivative are provided, add them to $fn_function and $fn_derivative.
                    if (isset($fn_trusted_regions_function) && isset($fn_trusted_regions_derivative)) {
                        $fn_function = fn($x) => matrixcat::multi_sum($fn_trusted_regions_function($x), $fn_function($x));
                        $fn_derivative = fn($x) => matrixcat::multi_sum($fn_trusted_regions_derivative($x), $fn_derivative($x));
                        print("Used Trusted Regions function and derivatied and added this to the target functions.");
                    }
                    
                    // If the problem occurs a second time in a row, additionally reset the parameter $parameter to $parameter_start
                    if ($is_critical) {
                        $parameter = $parameter_start;
                        $mx_parameter = new matrix(is_array($parameter) ? [$parameter] : [[$parameter]]); // @DAVID: Sollte serialisiert werden für den Fall genesteter Arrays.
                        $mx_parameter = $mx_parameter->transpose();
                    }
                } else {
                // If everything went fine, keep/reset $is_critical as FALSE.
                $is_critical = FALSE;
                }
            }       
            // Test if precisiion criteria for stopping iterations has been reached.
            if ($mx_delta->max_absolute_element()  < 10 ** (-$precission)) {
                return $parameter;
            }
        }
        // Return the concurrent solution even the precission criteria hasn't been met.
        return $parameter;
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
}
