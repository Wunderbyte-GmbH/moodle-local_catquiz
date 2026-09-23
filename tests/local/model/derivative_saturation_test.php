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

namespace local_catquiz\local\model;

use basic_testcase;

/**
 * Regression guard: no model derivative may crash or return NaN/INF at saturation.
 *
 * At extreme abilities the category probabilities (and the 3PL Bernoulli variance)
 * underflow to exactly 0, which previously produced a DivisionByZeroError under
 * PHP 8 (or NaN/INF from overflowing exp() sums). Both the person-ability and the
 * item-parameter derivatives must stay finite for every model.
 *
 * @package    local_catquiz
 * @copyright  2024 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\local\model\model_raschmodel
 */
final class derivative_saturation_test extends basic_testcase {
    /**
     * Every derivative must be finite across the full ability range, incl. saturation.
     *
     * @dataProvider saturation_model_provider
     *
     * @param string $modelname model name
     * @param array $ip item parameters
     * @param array $fracs valid response fractions for the model
     *
     * @return void
     */
    public function test_derivatives_are_finite_at_saturation(string $modelname, array $ip, array $fracs): void {
        $class = "catmodel_{$modelname}\\{$modelname}";
        $thetas = [0.0, 5.0, 40.0, 200.0, 800.0, -800.0];

        foreach ($thetas as $theta) {
            $pp = ['ability' => $theta];
            foreach ($fracs as $frac) {
                $frac = (float) $frac;

                $probability = $class::likelihood($pp, $ip, $frac);
                $this->assert_finite($probability, "likelihood finite ($modelname, theta=$theta, frac=$frac)");

                $score = $class::log_likelihood_p($pp, $ip, $frac);
                $this->assert_finite($score, "log_likelihood_p finite ($modelname, theta=$theta, frac=$frac)");

                $hessian = $class::log_likelihood_p_p($pp, $ip, $frac);
                $this->assert_finite($hessian, "log_likelihood_p_p finite ($modelname, theta=$theta, frac=$frac)");

                $this->assert_all_finite(
                    $class::get_ability_derivatives($pp, $ip, $frac),
                    "get_ability_derivatives finite ($modelname, theta=$theta, frac=$frac)"
                );

                foreach (['get_log_jacobian', 'get_log_hessian'] as $method) {
                    $this->assert_all_finite(
                        $class::$method($pp, $ip, $frac),
                        "$method finite ($modelname, theta=$theta, frac=$frac)"
                    );
                }
            }
        }
    }

    /**
     * Recursively assert that every scalar in a (possibly nested) array is finite.
     *
     * @param array $values values to check
     * @param string $message assertion message
     *
     * @return void
     */
    private function assert_all_finite(array $values, string $message): void {
        array_walk_recursive($values, function ($x) use ($message) {
            $this->assert_finite((float) $x, $message);
        });
    }

    /**
     * Assert a value is neither NaN nor infinite.
     *
     * @param float $value value to check
     * @param string $message assertion message
     *
     * @return void
     */
    private function assert_finite(float $value, string $message): void {
        $this->assertFalse(is_nan($value), "NaN: $message");
        $this->assertFalse(is_infinite($value), "INF: $message");
    }

    /**
     * One representative item per model, including saturating edge cases.
     *
     * @return array
     */
    public static function saturation_model_provider(): array {
        $dich = ['0.0', '1.0'];
        $poly = ['0.0', '0.5', '1.0'];
        return [
            'rasch' => ['rasch', ['difficulty' => 0.5], $dich],
            'raschbirnbaum' => ['raschbirnbaum', ['difficulty' => 0.5, 'discrimination' => 1.2], $dich],
            // Guessing = 0 makes the 3PL degenerate to a saturating 2PL (P -> 0 exactly).
            'mixedraschbirnbaum' => [
                'mixedraschbirnbaum',
                ['difficulty' => 0.5, 'discrimination' => 1.2, 'guessing' => 0.0],
                $dich,
            ],
            'grm' => ['grm', ['difficulties' => ['0.0' => 0.0, '0.5' => -0.4, '1.0' => 0.7]], $poly],
            'grmgeneralized' => [
                'grmgeneralized',
                ['difficulties' => ['0.0' => 0.0, '0.5' => -0.4, '1.0' => 0.7], 'discrimination' => 1.3],
                $poly,
            ],
            'pcm' => ['pcm', ['intercepts' => ['0.0' => 0.0, '0.5' => -0.4, '1.0' => 0.7]], $poly],
            'pcmgeneralized' => [
                'pcmgeneralized',
                ['intercepts' => ['0.0' => 0.0, '0.5' => -0.4, '1.0' => 0.7], 'discrimination' => 1.3],
                $poly,
            ],
        ];
    }
}
