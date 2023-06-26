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
 * Class for math functions;
 *
 * @package local_catquiz
 * @author Daniel Pasterk
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

class mathcat
{

    static function newtonraphson($func, $derivative, $start = 0, $min_inc = 0.0001, $max_iter = 150): float
    {

        $return_val = 0.0;
        $x_0 = $start;

        for ($n = 1; $n < $max_iter; $n++) {

            #$diff  = - $func($x_0) / ($derivative($x_0)+0.001);
            $diff = -$func($x_0) / ($derivative($x_0) + 0.00000001);
            //echo "Iteration:" . $n . "and diff: " . $diff . " x_0=" . $x_0 . " value: ". $func($x_0)  . "<br>";
            $x_0 += $diff;

            # workarround for numerical stability -> TODO: replace with gauß
            if ($diff > 10) {
                //echo "warning - drift: " . abs($diff) . "<br>";
                return 5;
            } elseif ($diff < -10) {
                //echo "warning - drift: " . abs($diff) .  "<br>";
                return -5;
            }

            if (abs($diff) < $min_inc) {
                break;
            }
        }
        return $x_0;
    }

    static function newtonraphson_numeric($f, $x0, $tolerance, $max_iterations = 150, $h = 0.001)
    {

        for ($i = 0; $i < $max_iterations; $i++) {
            $fx0 = $f($x0);
            $dfx0 = ($f($x0 + $h) - $f($x0 - $h)) / (2 * $h);

            if ($dfx0 == 0) {
                return $x0;
            }

            $x1 = $x0 - $fx0 / $dfx0;

            if (abs($x1 - $x0) < $tolerance) {
                return $x1;
            }
            //echo "Iteration:" . $i . "and diff: " . $x1 - $x0 . " x_0=" . $x1 . " value: ". $f($x1)  . "<br>";
            $x0 = $x1;
        }

        return $x0;
    }

    static function get_numerical_derivative(callable $func, float $h = 1e-5)
    {
        $returnfn = function ($x) use ($func, $h) {
            return ($func($x + $h) - $func($x)) / $h;
        };
        return $returnfn;
    }

    static function get_numerical_derivative2(callable $func, float $h = 1e-6)
    {
        $returnfn = function ($x) use ($func, $h) {
            return ($func($x + $h) - $func($x - $h)) / (2 * $h);
        };
        return $returnfn;
    }

    static function gradient(callable $func, $point, $delta = 1e-5)
    {
        $grad = [];
        for ($i = 0; $i < count($point); $i++) {
            $point_plus_delta = $point;
            $point_minus_delta = $point;
            $point_plus_delta[$i] += $delta;
            $point_minus_delta[$i] -= $delta;
            $grad[$i] = ($func($point_plus_delta) - $func($point_minus_delta)) / (2 * $delta);
        }
        return $grad;
    }

    static function matrix_vector_product($matrix, $vector)
    {
        $result = [];
        for ($i = 0; $i < count($matrix); $i++) {
            $result[$i] = 0;
            for ($j = 0; $j < count($matrix[$i]); $j++) {
                $result[$i] += $matrix[$i][$j] * $vector[$j];
            }
        }
        return $result;
    }

    static function bfgs(callable $func, $start_point, $step_size = 0.01, $tolerance = 1e-6, $max_iterations = 1000)
    {
        $n = count($start_point);
        $current_point = $start_point;
        $iteration = 0;
        $H = array();

        // Initialize H with the identity matrix
        for ($i = 0; $i < $n; $i++) {
            $H[$i] = array();
            for ($j = 0; $j < $n; $j++) {
                $H[$i][$j] = $i == $j ? 1 : 0;
            }
        }

        while ($iteration < $max_iterations) {
            $grad = self::gradient($func, $current_point);
            $direction = self::matrix_vector_product($H, $grad);

            for ($i = 0; $i < $n; $i++) {
                $direction[$i] = -$direction[$i];
            }

            // Line search with constant step size
            $next_point = [];
            for ($i = 0; $i < $n; $i++) {
                $next_point[$i] = $current_point[$i] + $step_size * $direction[$i];
            }

            // Update H using BFGS formula
            $s = [];
            $y = [];
            for ($i = 0; $i < $n; $i++) {
                $s[$i] = $next_point[$i] - $current_point[$i];
                $y[$i] = self::gradient($func, $next_point)[$i] - $grad[$i];
            }

            $rho = 1 / array_sum(array_map(function ($yi, $si) {
                    return $yi * $si;
                }, $y, $s));

            $I = [];
            for ($i = 0; $i < $n; $i++) {
                $I[$i] = array();
                for ($j = 0; $j < $n; $j++) {
                    $I[$i][$j] = $i == $j ? 1 : 0;
                }
            }

            $A1 = [];
            for ($i = 0; $i < $n; $i++) {
                $A1[$i] = array();
                for ($j = 0; $j < $n; $j++) {
                    $A1[$i][$j] = $I[$i][$j] - $rho * $s[$i] * $y[$j];
                }
            }

            $A2 = [];
            for ($i = 0; $i < $n; $i++) {
                $A2[$i] = array();
                for ($j = 0; $j < $n; $j++) {
                    $A2[$i][$j] = $I[$i][$j] - $rho * $y[$i] * $s[$j];
                }
            }

            $H_new = [];
            for ($i = 0; $i < $n; $i++) {
                $H_new[$i] = array();
                for ($j = 0; $j < $n; $j++) {
                    $H_new[$i][$j] = $A1[$i][$j] * $H[$j][$i] * $A2[$j][$i] + $rho * $s[$i] * $s[$j];
                }
            }

            $H = $H_new;

            // Check for convergence
            $diff = 0;
            for ($i = 0; $i < count($current_point); $i++) {
                $diff += abs($next_point[$i] - $current_point[$i]);
            }

            if ($diff < $tolerance) {
                break;
            }

            $current_point = $next_point;
            $iteration++;
        }

        return $current_point;
    }

    static function newton_raphson_multi($func, $derivative, $start, $min_inc = 0.0001, $max_iter = 2000)
    {

        $model_dim = count($func);

        $ml = new matrixcat();
        // get real jacobian/hessian

        $z_0 = $start;


        // jacobian, hessian, model_dim, start_value


        for ($i = 0; $i < $max_iter; $i++) {

            for ($k = 0; $k <= $model_dim-1; $k++) {

                $real_func[$k] = [$func[$k]($z_0)];

                for ($j = 0; $j <= $model_dim-1; $j++) {
                    $real_derivative[$k][$j] = $derivative[$k][$j]($z_0);
                }
            }


            $G = $real_func;
            $J = $real_derivative;

            $j_inv = $ml->inverseMatrix($J);

            $z_1 = $ml->subtractVectors($z_0, $ml->flattenArray($ml->multiplyMatrices($j_inv, $G)));


            $dist = $ml->dist($z_0,$z_1);

            if ($dist < $min_inc){
                $z_0 = $z_1;
                break;
            }
            $z_0 = $z_1;
        }

        return $z_0;
    }
}

