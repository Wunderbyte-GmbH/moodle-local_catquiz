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
 * Reusable finite-difference helpers to verify analytic derivatives numerically.
 *
 * @package    local_catquiz
 * @copyright  2025 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use local_catquiz\local\model\model_raschmodel;

/**
 * Provides central finite-difference approximations of a gradient and a Hessian.
 *
 * The IRT models expose analytic first and second derivatives of the log
 * likelihood with respect to the item parameters (get_log_jacobian /
 * get_log_hessian). This trait lets a test verify those analytic results against
 * an independent numeric reference obtained purely from log_likelihood(), so the
 * two paths share no code. Tolerances are derived from the plugin's own
 * item-parameter precision (model_raschmodel::PRECISION) rather than fixed magic
 * numbers.
 *
 * @package    local_catquiz
 * @copyright  2025 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait derivative_fd_trait {
    /**
     * Step width for a given coordinate value, scaled to its magnitude.
     *
     * @param float $value the current coordinate value
     * @param float $exponent exponent applied to the machine epsilon
     * @return float
     */
    protected function fd_step(float $value, float $exponent): float {
        return (PHP_FLOAT_EPSILON ** $exponent) * max(1.0, abs($value));
    }

    /**
     * Central finite-difference gradient of a scalar function.
     *
     * @param callable $function maps a numeric vector to a float
     * @param array $x the point at which the gradient is evaluated
     * @return array the approximated gradient, same keys/order as $x
     */
    protected function fd_gradient(callable $function, array $x): array {
        $keys = array_keys($x);
        $values = array_values($x);
        $gradient = [];
        foreach ($values as $i => $value) {
            $h = $this->fd_step($value, 1 / 3);
            $plus = $values;
            $minus = $values;
            $plus[$i] += $h;
            $minus[$i] -= $h;
            $gradient[$keys[$i]] = ($function($plus) - $function($minus)) / (2 * $h);
        }
        return $gradient;
    }

    /**
     * Central finite-difference Hessian of a scalar function.
     *
     * Diagonal entries use the three-point second difference, off-diagonal
     * entries the four-point mixed difference. The result is symmetric by
     * construction.
     *
     * @param callable $function maps a numeric vector to a float
     * @param array $x the point at which the Hessian is evaluated
     * @return array a square matrix (array of arrays) of size count($x)
     */
    protected function fd_hessian(callable $function, array $x): array {
        $values = array_values($x);
        $n = count($values);
        $f0 = $function($values);
        $steps = [];
        foreach ($values as $i => $value) {
            $steps[$i] = $this->fd_step($value, 0.25);
        }
        $result = array_fill(0, $n, array_fill(0, $n, 0.0));
        for ($i = 0; $i < $n; $i++) {
            $hi = $steps[$i];
            $plus = $values;
            $minus = $values;
            $plus[$i] += $hi;
            $minus[$i] -= $hi;
            $result[$i][$i] = ($function($plus) - 2 * $f0 + $function($minus)) / ($hi ** 2);
            for ($j = $i + 1; $j < $n; $j++) {
                $hj = $steps[$j];
                $pp = $values;
                $pm = $values;
                $mp = $values;
                $mm = $values;
                $pp[$i] += $hi;
                $pp[$j] += $hj;
                $pm[$i] += $hi;
                $pm[$j] -= $hj;
                $mp[$i] -= $hi;
                $mp[$j] += $hj;
                $mm[$i] -= $hi;
                $mm[$j] -= $hj;
                $value = ($function($pp) - $function($pm) - $function($mp) + $function($mm)) / (4 * $hi * $hj);
                $result[$i][$j] = $value;
                $result[$j][$i] = $value;
            }
        }
        return $result;
    }

    /**
     * Absolute tolerance for finite-difference comparisons.
     *
     * Central differences with the adaptive step used here agree with the analytic
     * derivatives far more tightly than the item-parameter display precision, so the
     * FD tolerance is set well below 10^-PRECISION to catch small regressions. The
     * Hessian path adds relative slack on top (see assert_hessian_close) because the
     * second difference is noisier.
     *
     * @return float
     */
    protected function fd_atol(): float {
        return 1e-6;
    }

    /**
     * Asserts that two floats agree within a combined absolute/relative tolerance.
     *
     * @param float $expected reference (numeric) value
     * @param float $actual analytic value under test
     * @param float $atol absolute tolerance
     * @param float $rtol relative tolerance
     * @return void
     */
    protected function assert_close(float $expected, float $actual, float $atol, float $rtol): void {
        $limit = $atol + $rtol * abs($expected);
        $this->assertLessThanOrEqual(
            $limit,
            abs($expected - $actual),
            sprintf('expected %.12g, got %.12g (limit %.3g)', $expected, $actual, $limit)
        );
    }

    /**
     * Asserts that an analytic gradient matches its numeric reference.
     *
     * @param array $numeric numeric gradient (values compared in order)
     * @param array $analytic analytic gradient (values compared in order)
     * @return void
     */
    protected function assert_gradient_close(array $numeric, array $analytic): void {
        $numeric = array_values($numeric);
        $analytic = array_values($analytic);
        $this->assertCount(count($numeric), $analytic, 'gradient dimension mismatch');
        $atol = $this->fd_atol();
        foreach ($numeric as $i => $value) {
            $this->assert_close($value, (float) $analytic[$i], $atol, $atol);
        }
    }

    /**
     * Asserts that an analytic Hessian matches its numeric reference and is symmetric.
     *
     * @param array $numeric numeric Hessian matrix
     * @param array $analytic analytic Hessian matrix
     * @return void
     */
    protected function assert_hessian_close(array $numeric, array $analytic): void {
        $normalise = function (array $matrix): array {
            ksort($matrix);
            $rows = [];
            foreach ($matrix as $row) {
                ksort($row);
                $rows[] = array_values($row);
            }
            return $rows;
        };
        $numeric = $normalise($numeric);
        $analytic = $normalise($analytic);
        $n = count($numeric);
        $this->assertCount($n, $analytic, 'hessian dimension mismatch');
        // Hessian finite differences are noisier (cancellation), so allow more relative slack.
        $atol = $this->fd_atol() * 10.0;
        $rtol = 100 * $this->fd_atol();
        for ($i = 0; $i < $n; $i++) {
            $this->assertCount($n, $analytic[$i], 'hessian row dimension mismatch');
            for ($j = 0; $j < $n; $j++) {
                $this->assert_close((float) $numeric[$i][$j], (float) $analytic[$i][$j], $atol, $rtol);
                // Analytic symmetry must hold tightly.
                $this->assertEqualsWithDelta((float) $analytic[$i][$j], (float) $analytic[$j][$i], 1e-9);
            }
        }
    }
}
