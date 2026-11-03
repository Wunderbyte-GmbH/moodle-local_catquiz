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

namespace local_catquiz;

use advanced_testcase;
use catmodel_grm\grm;
use catmodel_raschbirnbaum\raschbirnbaum;
use local_catquiz\local\model\model_item_param;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_item_response;
use local_catquiz\local\model\model_person_param;

/**
 * The per-response cache of the ability derivatives stays small and returns the right values (issue #145).
 *
 * It used to keep every Newton iterate: up to 500 points per answered question and fit, with no
 * reuse beyond the latest point.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\catcalc
 */
final class ability_derivative_memo_test extends advanced_testcase {
    /**
     * The cached callable of one response, as the estimator builds it.
     *
     * @param string $model
     * @param array $params
     * @param float $response
     * @return \Closure
     */
    private function callable(string $model, array $params, float $response): \Closure {
        $method = new \ReflectionMethod(catcalc::class, 'make_ability_derivative_callable');
        $method->setAccessible(true);
        return $method->invoke(null, $model, $params, $response);
    }

    /**
     * Number of points the callable holds.
     *
     * @param \Closure $callable
     * @return int
     */
    private function cached(\Closure $callable): int {
        return count((new \ReflectionFunction($callable))->getStaticVariables()['memo']);
    }

    /**
     * Item parameters per model, dichotomous and polytomous.
     *
     * @return array
     */
    public static function models(): array {
        return [
            '2PL' => [raschbirnbaum::class, ['difficulty' => 0.4, 'discrimination' => 1.3], 1.0],
            'GRM' => [grm::class, ['difficulties' => ['0.0' => 0.0, '0.5' => -0.7, '1.0' => 0.9]], 0.5],
        ];
    }

    /**
     * However many points are asked for, at most ABILITY_DERIVATIVE_MEMO_SIZE are kept - and every
     * value equals the uncached derivative, before and after the point was dropped.
     *
     * @dataProvider models
     * @param string $model
     * @param array $params
     * @param float $response
     */
    public function test_cache_is_bounded_and_exact(string $model, array $params, float $response): void {
        $callable = $this->callable($model, $params, $response);

        for ($i = 0; $i < 600; $i++) {
            $pp = ['ability' => -6.0 + 0.02 * $i];
            $this->assertSame($model::get_ability_derivatives($pp, $params, $response), $callable($pp));
            // The hessian is asked for at the same point right after the jacobian.
            $this->assertSame($model::get_ability_derivatives($pp, $params, $response), $callable($pp));
            $this->assertLessThanOrEqual(catcalc::ABILITY_DERIVATIVE_MEMO_SIZE, $this->cached($callable));
        }
        // A point long dropped is computed again - with the same result.
        $this->assertSame(
            $model::get_ability_derivatives(['ability' => -6.0], $params, $response),
            $callable(['ability' => -6.0])
        );
    }

    /**
     * Each response keeps its own cache: one response's derivatives are never returned for another.
     */
    public function test_responses_do_not_share_a_cache(): void {
        $params = ['difficulty' => 0.4, 'discrimination' => 1.3];
        $right = $this->callable(raschbirnbaum::class, $params, 1.0);
        $wrong = $this->callable(raschbirnbaum::class, $params, 0.0);

        $pp = ['ability' => 0.3];
        $this->assertNotSame($right($pp), $wrong($pp));
        $this->assertSame(raschbirnbaum::get_ability_derivatives($pp, $params, 0.0), $wrong($pp));
    }

    /**
     * A long, slowly converging fit gives the same ability as before and terminates.
     *
     * All 150 answers right: the estimate runs to the edge of the range, the slowest case.
     */
    public function test_long_fit_terminates_with_a_finite_ability(): void {
        $this->resetAfterTest();
        $person = new model_person_param('p', 1);
        $items = new model_item_param_list();
        $responses = [];
        for ($i = 0; $i < 150; $i++) {
            $item = new model_item_param('it' . $i, 'raschbirnbaum');
            $item->set_parameters(['difficulty' => -3.0 + 6.0 * $i / 149, 'discrimination' => 1.3]);
            $items->add($item);
            $responses['it' . $i] = new model_item_response('it' . $i, 1.0, $person);
        }

        $ability = catcalc::estimate_person_ability($responses, $items, 0.0, 0.0, 1.0, -10.0, 10.0);

        $this->assertTrue(is_finite($ability));
        $this->assertGreaterThan(3.0, $ability);
        $this->assertLessThanOrEqual(10.0, $ability);
    }
}
