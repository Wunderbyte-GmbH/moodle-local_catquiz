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
    static function newtonraphson_stable($func, $derivative, $start = 0, $min_inc = 0.0001, $max_iter = 150): float
    {

        $return_val = 0.0;
        $x_0 = $start;
        $use_gauss = false;
        $gauss_iter = 0;

        $m = 0;
        $std = 0.5;


        for ($n = 1; $n < $max_iter; $n++) {

            if ($use_gauss == true){

                $gauss_iter += 1;
                if ($gauss_iter % 10 == 0){
                    $func = mathcat::compose_plus($func, function($x) use ($n,$m,$std)  {
                        return 1 * mathcat::gaussian_density_derivative1($x,$m,$std);
                    });

                    $derivative = mathcat::compose_plus($derivative, function($x) use ($n,$m,$std){
                        return 1 * mathcat::gaussian_density_derivative2($x,$m,$std);
                    });
                    //$z_0 = $m;
                    //$use_gauss = false;
                }
            }

            if ($derivative($x_0) != 0) {
                $diff = -$func($x_0) / ($derivative($x_0));
            } else {
                $use_gauss = true;
                $x_0 = 0;
            }

            #$diff  = - $func($x_0) / ($derivative($x_0)+0.001);
            # $diff = -$func($x_0) / ($derivative($x_0) + 0.00000001);
            //echo "Iteration:" . $n . "and diff: " . $diff . " x_0=" . $x_0 . " value: ". $func($x_0)  . "<br>";
            $x_0 += $diff;


            if (abs($diff) > 10) {
                $use_gauss = true;
                $x_0 = 0;
            }

            if ($n == $max_iter){  //debug!
                echo "not converging!";
            }

            if (abs($diff) < $min_inc) {
                break;
            }
        }
        return $x_0;
    }


    static function gaussian_density($x, $mean, $stdDeviation) {
        $factor1 = 1 / sqrt(2 * M_PI * pow($stdDeviation, 2));
        $factor2 = exp(-pow($x - $mean, 2) / (2 * pow($stdDeviation, 2)));
        return $factor1 * $factor2;
    }

    static function gaussian_density_derivative1($x, $m, $std) {
        //$factor1 = -($x - $mean) / pow($stdDeviation, 2);
        //$factor2 = exp(-pow($x - $mean, 2) / (2 * pow($stdDeviation, 2)));
        //return $factor1 * $factor2;

        return (exp(-(($m - $x)**2 / (2 * $std**2))) * ($m - $x))/(sqrt(2 * M_PI) * $std**3);


    }

    static function gaussian_density_derivative2($x, $m, $std) {
        return (exp(-(($m - $x)**2/ (2 * $std **2))) * ($m**2 - $std**2 - 2 * $m * $x + $x**2))/(sqrt(2 * M_PI)*$std**5);
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
        $parameter_names = array_keys($z_0);



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

            if (is_array($z_0)){

            } else {
                $z_1 = $z_0 - $ml->flattenArray($ml->multiplyMatrices($j_inv, $G))[0];
                $dist = abs($z_0 - $z_1);
            }





            if ($dist < $min_inc){
                return array_combine($parameter_names, $z_1);
            }
            $z_0 = array_combine($parameter_names, $z_1);
        }

        return $z_0;
    }

    static function newton_raphson_multi_stable($func, $derivative, $start, $min_inc = 0.0001, $max_iter = 2000)
    {

        $model_dim = count($func);

        $ml = new matrixcat();
        // get real jacobian/hessian

        $z_0 = $start;
        $parameter_names = array_keys($z_0);

        $use_gauss= array_fill(0, $model_dim, false);
        $gauss_iter = array_fill(0, $model_dim, 0);


        // jacobian, hessian, model_dim, start_value


        for ($i = 0; $i < $max_iter; $i++) {

            // gauß noise treatment
            for ($k = 0; $k <= $model_dim - 1; $k++ ){

                if ($use_gauss[$k] == true){

                    $gauss_iter[$k] += 1;
                    if ($gauss_iter[$k] % 10 == 0){

                        $func[$k] = self::add_gauss_der1($func[$k],0,0.5);
                        for ($kk = 0;$kk <= $model_dim;$kk++){
                            $derivative[$k][$kk] = self::add_gauss_der2($derivative[$k][$kk],0,0.5);
                        }
                    }
                }
            }

            for ($k = 0; $k <= $model_dim-1; $k++) {

                $real_func[$k] = [$func[$k]($z_0)];

                for ($j = 0; $j <= $model_dim-1; $j++) {
                    $real_derivative[$k][$j] = $derivative[$k][$j]($z_0);
                }
            }


            $G = $real_func;
            $J = $real_derivative;

            //$j_inv = $ml->inverseMatrix($J);
            $matrix = new matrix($J);
            $j_inv = ($matrix->getRows() === 1 && $matrix->isSquare()) ? [[1/$J[0][0]]] : $matrix->inverse();





            if (is_array($z_0)){
                $diff = $ml->flattenArray($ml->multiplyMatrices($j_inv, $G));

                $z_1 = $ml->subtractVectors(array_values($z_0), $diff);
                $dist = $ml->dist(array_values($z_0),$z_1);


                //for ($ii = 0; $ii <= count($z_0);$ii++){
                //
                //    if (abs($z_0[$ii]) > 10){
                //        $use_gauss[$ii] = true;
                //        $z_1[$ii] = 0; // todo: reset to some new starting point
                //    }
                //}

            } else {
                $z_1 = array_values($z_0) - $ml->flattenArray($ml->multiplyMatrices($j_inv, $G))[0];
                $dist = abs(array_values($z_0) - $z_1);
            }

            if ($dist < $min_inc){
                return array_combine($parameter_names, $z_1);
            }
            $z_0 = array_combine($parameter_names, $z_1);
        }

        return $z_0;
    }


    private static function add_gauss_der1(callable $func, $mean, $std){

        $gaussian = function($x) use ($mean,$std)  {
            return 1 * self::gaussian_density_derivative1($x,$mean,$std);
        };
        $new_func = self::compose_plus($func, $gaussian);
        return $new_func;
    }

    private static function add_gauss_der2(callable $func, $mean, $std){

        $gaussian = function($x) use ($mean,$std)  {
            return 1 * self::gaussian_density_derivative2($x,$mean,$std);
        };
        $new_func = self::compose_plus($func,$gaussian);
        return $new_func;
    }

    static function compose_plus($function1, $function2)
    {
        $returnfn = function ($x) use ($function1, $function2) {
            return $function1($x) + $function2($x);
        };
        return $returnfn;
    }

    static function compose_multiply($function1, $function2)
    {
        $returnfn = function ($x) use ($function1, $function2) {
            return $function1($x) * $function2($x);
        };
        return $returnfn;
    }

    static function compose_chain($function1, $function2)
    {
        $returnfn = function ($x) use ($function1, $function2) {
            return $function1($function2);
        };
        return $returnfn;
    }
}

