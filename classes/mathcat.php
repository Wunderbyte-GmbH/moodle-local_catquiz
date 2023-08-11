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
     * @param mixed $min_inc
     * @param int $max_iter
     * @param int $max
     *
     * @return float
     *
     */
    static function newtonraphson_stable(
        $func,
        $derivative,
        $start = 0,
        $mininc = 0.0001,
        $maxiter = 150,
        $max = 50
    ): float {

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

            // Restrict values to [-$max, $max] and stop if we get outside that interval
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

            if ($n == $maxiter){  // debug!
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
     * @param mixed $stdDeviation
     *
     * @return mixed
     *
     */
    static function gaussian_density($x, $mean, $stddeviation) {
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
    static function gaussian_density_derivative1($x, $m, $std) {
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
    static function gaussian_density_derivative2($x, $m, $std) {
        return (exp(-(($m - $x) ** 2 / (2 * $std ** 2))) * ($m ** 2 - $std ** 2 - 2 * $m * $x + $x ** 2)) / (sqrt(2 * M_PI) * $std ** 5);
    }

    /**
     * Returns newton raphson numeric value.
     *
     * @param mixed $f
     * @param mixed $x0
     * @param mixed $tolerance
     * @param int $max_iterations
     * @param mixed $h
     *
     * @return mixed
     *
     */
    static function newtonraphson_numeric($f, $x0, $tolerance, $maxiterations = 150, $h = 0.001) {

        for ($i = 0; $i < $maxiterations; $i++) {
            $fx0 = $f($x0);
            $dfx0 = ($f($x0 + $h) - $f($x0 - $h)) / (2 * $h);

            if ($dfx0 == 0) {
                return $x0;
            }

            $x1 = $x0 - $fx0 / $dfx0;

            if (abs($x1 - $x0) < $tolerance) {
                return $x1;
            }
            // echo "Iteration:" . $i . "and diff: " . $x1 - $x0 . " x_0=" . $x1 . " value: ". $f($x1)  . "<br>";
            $x0 = $x1;
        }

        return $x0;
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
    static function get_numerical_derivative(callable $func, float $h = 1e-5) {
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
    static function get_numerical_derivative2(callable $func, float $h = 1e-6) {
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
    static function gradient(callable $func, $point, $delta = 1e-5) {
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
    static function matrix_vector_product($matrix, $vector) {
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
     * @param mixed $start_point
     * @param mixed $step_size
     * @param mixed $tolerance
     * @param int $max_iterations
     *
     * @return mixed
     *
     */
    static function bfgs(callable $func, $startpoint, $stepsize = 0.01, $tolerance = 1e-6, $maxiterations = 1000) {
        $n = count($startpoint);
        $currentpoint = $startpoint;
        $iteration = 0;
        $h = array();

        // Initialize H with the identity matrix
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

            // Line search with constant step size
            $nextpoint = [];
            for ($i = 0; $i < $n; $i++) {
                $nextpoint[$i] = $currentpoint[$i] + $stepsize * $direction[$i];
            }

            // Update H using BFGS formula
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

            // Check for convergence
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
     * Returns newton raphson multi value(s).
     *
     * @param mixed $func
     * @param mixed $derivative
     * @param mixed $start
     * @param mixed $min_inc
     * @param int $max_iter
     *
     * @return mixed
     *
     */
    static function newton_raphson_multi($func, $derivative, $start, $mininc = 0.0001, $maxiter = 2000) {
        $modeldim = count($func);

        $ml = new matrixcat();

        // get real jacobian/hessian
        $z0 = $start;
        $parameternames = array_keys($z0);

        // jacobian, hessian, model_dim, start_value
        for ($i = 0; $i < $maxiter; $i++) {

            for ($k = 0; $k <= $modeldim - 1; $k++) {

                $realfunc[$k] = [$func[$k]($z0)];

                for ($j = 0; $j <= $modeldim - 1; $j++) {
                    $realderivative[$k][$j] = $derivative[$k][$j]($z0);
                }
            }

            $g = $realfunc;
            $j = $realderivative;

            $jinv = $ml->inverseMatrix($j);

            if (is_array($z0)){

            } else {
                $z1 = $z0 - $ml->flattenArray($ml->multiplyMatrices($jinv, $g))[0];
                $dist = abs($z0 - $z1);
            }

            if ($dist < $mininc){
                return array_combine($parameternames, array($z1));
            }
            $z0 = array_combine($parameternames, array($z1));
        }

        return $z0;
    }

    /**
     * Returns newton raphson multi stable value(s).
     *
     * @param mixed $func
     * @param mixed $derivative
     * @param mixed $start
     * @param mixed $min_inc
     * @param int $max_iter
     * @param catcalc_item_estimator $model
     *
     * @return mixed
     *
     */
    static function newton_raphson_multi_stable(
        $func,
        $derivative,
        $start,
        $mininc = 0.0001,
        $maxiter = 2000,
        catcalc_item_estimator $model
    ) {
        $modeldim = count($func);
        $ml = new matrixcat();
        $z0 = $start;
        $parameternames = array_keys($z0);

        // jacobian, hessian, model_dim, start_value
        for ($i = 0; $i < $maxiter; $i++) {
            for ($k = 0; $k <= $modeldim - 1; $k++) {
                $realfunc[$k] = [$func[$k]($z0)];
                for ($j = 0; $j <= $modeldim - 1; $j++) {
                    $realderivative[$k][$j] = $derivative[$k][$j]($z0);
                }
            }

            $g = $realfunc;
            $j = $realderivative;
            $matrix = new matrix($j);
            $jinv = ($matrix->getRows() === 1 && $matrix->isSquare())
                ? [[1 / $j[0][0]]]
                : $matrix->inverse();

            if (is_array($z0)) {
                $diff = $ml->flattenArray($ml->multiplyMatrices($jinv, $g));
                $z1 = $ml->subtractVectors(array_values($z0), $diff);
                $dist = $ml->dist(array_values($z0), $z1);
            } else {
                $z1 = array_values($z0) - $ml->flattenArray($ml->multiplyMatrices($jinv, $g))[0];
                $dist = abs(array_values($z0) - $z1);
            }

            // If one of the values is NAN, return the values restricted to the trusted region
            if (count(array_filter($z1, fn ($x) => is_nan($x))) > 0) {
                $z1 = $model->restrict_to_trusted_region($z0);
                echo "returning restricted value\n";
                return array_combine($parameternames, $z1);
            }

            $iscritical = $model->restrict_to_trusted_region($z0) !== $z0;
            if ($iscritical) {
                foreach (array_keys($func) as $i) {
                    $func[$i] = self::compose_plus(
                        $model->get_log_tr_jacobian()[$i],
                        $func[$i]
                    );
                    foreach (array_keys($derivative) as $j) {
                        $derivative[$i][$j] = self::compose_plus(
                            $model->get_log_tr_hessian()[$i][$j],
                            $derivative[$i][$j]
                        );
                    }
                }
            }

            $z0 = array_combine($parameternames, $z1);
            if ($dist < $mininc) {
                return $z0;
            }
        }

        return $z0;
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
    static function compose_plus($function1, $function2) {
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
    static function compose_multiply($function1, $function2) {
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
    static function compose_chain($function1, $function2) {
        $returnfn = function ($x) use ($function1, $function2) {
            return $function1($function2);
        };
        return $returnfn;
    }
}
