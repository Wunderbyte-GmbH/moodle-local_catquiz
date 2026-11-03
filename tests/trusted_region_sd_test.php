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
use local_catquiz\local\model\model_item_param;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_item_response;
use local_catquiz\local\model\model_person_param;
use local_catquiz\local\model\model_raschmodel;
use local_catquiz\event\numeric_fallback_applied;
use local_catquiz\local\monitoring\numeric_notice;
use local_catquiz\teststrategy\preselect_task\updatepersonability;
use local_catquiz\teststrategy\progress;

/**
 * The trusted-region prior with a spread of zero, near zero or no number at all (issue #116).
 *
 * When every past person parameter is the same, their standard deviation is 0 and the prior's
 * derivatives divided by zero. The prior is needed wherever the estimate leaves the trusted region, so
 * its spread is forced away from zero in one place for every model: max(sd, 10^-PRECISION).
 *
 * Every fallback is reported as an event with figures only, not as a developer notice (issue #106).
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\local\model\model_raschmodel
 * @covers \local_catquiz\catcalc
 * @covers \local_catquiz\teststrategy\preselect_task\updatepersonability
 * @covers \local_catquiz\local\monitoring\numeric_notice
 * @covers \local_catquiz\event\numeric_fallback_applied
 * @covers \local_catquiz\mathcat
 */
final class trusted_region_sd_test extends advanced_testcase {
    /**
     * Every test is a request of its own.
     */
    protected function setUp(): void {
        parent::setUp();
        numeric_notice::reset();
    }

    /**
     * The numeric fallbacks a call reported.
     *
     * @param callable $call
     * @return array [result of the call, the reasons reported, the events]
     */
    private function reported(callable $call): array {
        $sink = $this->redirectEvents();
        $result = $call();
        $events = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof numeric_fallback_applied));
        $sink->close();
        return [$result, array_map(fn($e) => $e->other['reason'], $events), $events];
    }

    /**
     * The smallest spread the prior is built from.
     *
     * @return float
     */
    private static function minimum(): float {
        return 10 ** -model_raschmodel::PRECISION;
    }

    /**
     * Spreads that are raised to the minimum.
     *
     * @return array
     */
    public static function raised_sd_provider(): array {
        return [
            'zero' => [0.0],
            'near zero' => [1e-15],
            'just below the minimum' => [0.00099],
            'negative' => [-1.0],
            'negative zero' => [-0.0],
            'minus infinite' => [-INF],
            'not a number' => [NAN],
            'so small its square underflows' => [1e-200],
        ];
    }

    /**
     * Below the minimum the prior is built from the minimum: finite derivatives, never an error.
     *
     * @dataProvider raised_sd_provider
     * @param float $sd
     */
    public function test_spread_is_raised_to_the_minimum(float $sd): void {
        $minimum = self::minimum();
        $this->assertSame($minimum, model_raschmodel::sanitize_trusted_region_sd($sd));
        $this->assertSame((0.2 - 1.5) / ($minimum ** 2), model_raschmodel::get_ability_tr_jacobian(['ability' => 1.5], 0.2, $sd));
        $this->assertSame(-1 / ($minimum ** 2), model_raschmodel::get_ability_tr_hessian(['ability' => 1.5], 0.2, $sd));
    }

    /**
     * A spread at or above the minimum is not touched: the regular prior stays exactly as it was.
     */
    public function test_usable_spread_is_unchanged(): void {
        foreach ([self::minimum(), 0.01, 0.5, 1.0, 3.0] as $sd) {
            $this->assertSame($sd, model_raschmodel::sanitize_trusted_region_sd($sd));
            $this->assertSame(
                (0.2 - 1.5) / ($sd ** 2),
                model_raschmodel::get_ability_tr_jacobian(['ability' => 1.5], 0.2, $sd),
                "sd $sd"
            );
            $this->assertSame(-1 / ($sd ** 2), model_raschmodel::get_ability_tr_hessian(['ability' => 1.5], 0.2, $sd));
        }
    }

    /**
     * An infinite spread stays: a prior without curvature, and no error.
     */
    public function test_infinite_spread_is_a_flat_prior(): void {
        $this->assertSame(INF, model_raschmodel::sanitize_trusted_region_sd(INF));
        $this->assertEqualsWithDelta(0.0, model_raschmodel::get_ability_tr_jacobian(['ability' => 1.5], 0.2, INF), 0.0);
        $this->assertEqualsWithDelta(0.0, model_raschmodel::get_ability_tr_hessian(['ability' => 1.5], 0.2, INF), 0.0);
    }

    /**
     * The trusted region uses the same spread: mean +/- factor * minimum, not mean +/- 0 or NaN.
     */
    public function test_trusted_region_uses_the_raised_spread(): void {
        foreach ([0.0, NAN, -1.0] as $sd) {
            $pp = model_raschmodel::restrict_to_trusted_region_pp(['ability' => 4.0], -10.0, 10.0, 3, 0.2, $sd, true);
            $this->assertEqualsWithDelta(0.2 + 3 * self::minimum(), $pp['ability'], 1e-12);
        }
        $pp = model_raschmodel::restrict_to_trusted_region_pp(['ability' => 4.0], -10.0, 10.0, 3, 0.2, 1.0, true);
        $this->assertEqualsWithDelta(3.2, $pp['ability'], 1e-12);
    }

    /**
     * Responses that push the estimate out of the trusted region, so the prior is asked for.
     *
     * @param float $fraction 1.0 for all correct.
     * @param int $n
     * @return array [responses, items]
     */
    private function sequence(float $fraction, int $n = 8): array {
        $person = new model_person_param('p', 1);
        $items = new model_item_param_list();
        $responses = [];
        for ($i = 0; $i < $n; $i++) {
            $item = new model_item_param('it' . $i, 'raschbirnbaum');
            $item->set_parameters(['difficulty' => -2.0 + 4.0 * $i / ($n - 1), 'discrimination' => 1.3]);
            $items->add($item);
            $responses['it' . $i] = new model_item_response('it' . $i, $fraction, $person);
        }
        return [$responses, $items];
    }

    /**
     * The whole estimation stays finite, inside the range, and terminates - for every spread raised.
     *
     * All correct and all wrong drive the estimate to the edge, where the prior is added; with sd 0
     * that used to be a DivisionByZeroError. The result is the estimate with the minimum spread, and
     * the raise is reported.
     *
     * @dataProvider raised_sd_provider
     * @param float $sd
     */
    public function test_estimation_stays_finite_with_a_raised_spread(float $sd): void {
        $this->resetAfterTest();
        foreach ([1.0, 0.0] as $fraction) {
            // The same figures are reported once per request; each fraction stands for a request of its own.
            numeric_notice::reset();
            [$responses, $items] = $this->sequence($fraction);
            [$ability, $reasons, $events] = $this->reported(
                fn() => catcalc::estimate_person_ability($responses, $items, 0.0, 0.2, $sd, -10.0, 10.0, true)
            );
            $this->assertSame([numeric_fallback_applied::SPREAD_RAISED], $reasons, "fraction $fraction");
            $this->assertSame(self::minimum(), $events[0]->other['effectivesd']);
            $this->assertSame(8, $events[0]->other['n']);
            $this->assertStringContainsString('spreadraised', $events[0]->get_description());

            $this->assertTrue(is_finite($ability), "fraction $fraction");
            $this->assertGreaterThanOrEqual(-10.0, $ability);
            $this->assertLessThanOrEqual(10.0, $ability);
            [$minimumability, $reasons] = $this->reported(
                fn() => catcalc::estimate_person_ability($responses, $items, 0.0, 0.2, self::minimum(), -10.0, 10.0, true)
            );
            $this->assertSame($ability, $minimumability, 'The same as the estimate with the minimum spread.');
            $this->assertSame([], $reasons, 'The minimum itself is no fallback.');
        }
        $this->assertDebuggingNotCalled();
    }

    /**
     * The same fallback with the same figures is reported once per request; other figures, or a new
     * request, are reported again. The event data can be stored, also for a spread that is no number.
     */
    public function test_a_fallback_is_reported_once_per_request(): void {
        $this->resetAfterTest();
        [$responses, $items] = $this->sequence(1.0);
        $estimate = fn($sd) => catcalc::estimate_person_ability($responses, $items, 0.0, 0.2, $sd, -10.0, 10.0, true);

        [, $reasons] = $this->reported(fn() => [$estimate(0.0), $estimate(0.0), $estimate(1e-15)]);
        $this->assertCount(2, $reasons, 'Twice the same figures, once others.');

        numeric_notice::reset();
        [, $reasons, $events] = $this->reported(fn() => $estimate(NAN));
        $this->assertCount(1, $reasons);
        $this->assertSame('NAN', $events[0]->other['sd']);
        $this->assertNotFalse(json_encode($events[0]->other));
        $this->assertDebuggingNotCalled();
    }

    /**
     * An estimate that is no finite number keeps the previous ability, or 0 - reported, without an
     * exception and without a developer notice.
     */
    public function test_an_ability_that_is_no_finite_number_keeps_the_previous_one(): void {
        $this->resetAfterTest();
        [$responses] = $this->sequence(1.0, 4);
        // An item parameter that is no number makes the estimate no number.
        $items = new model_item_param_list();
        foreach (array_keys($responses) as $id) {
            $item = new model_item_param($id, 'raschbirnbaum');
            $item->set_parameters(['difficulty' => NAN, 'discrimination' => 1.3]);
            $items->add($item);
        }
        $task = new class ($items) extends updatepersonability {
            /** @var model_item_param_list The items. */
            private model_item_param_list $items;

            /**
             * Constructor.
             *
             * @param model_item_param_list $items
             */
            public function __construct(model_item_param_list $items) {
                $this->items = $items;
            }

            /**
             * The items of every scale.
             *
             * @param int $catscaleid
             * @return model_item_param_list
             */
            protected function get_item_param_list($catscaleid): model_item_param_list {
                return $this->items;
            }

            /**
             * The range of every scale.
             *
             * @param int $catscaleid
             * @return float
             */
            protected function get_min_ability_for_scale(int $catscaleid): float {
                return -5.0;
            }

            /**
             * The range of every scale.
             *
             * @param int $catscaleid
             * @return float
             */
            protected function get_max_ability_for_scale(int $catscaleid): float {
                return 5.0;
            }

            /**
             * No trusted-region factor.
             *
             * @return bool
             */
            protected function use_tr_factor(): bool {
                return false;
            }
        };
        $task->arrayresponses = $responses;
        $progress = (new \ReflectionClass(progress::class))->newInstanceWithoutConstructor();
        $played = new \ReflectionProperty(progress::class, 'playedquestionsbyscale');
        $played->setAccessible(true);
        $played->setValue($progress, []);
        foreach (['progress' => $progress, 'parentability' => 0.0, 'parentse' => 1.0] as $name => $value) {
            $property = new \ReflectionProperty(updatepersonability::class, $name);
            $property->setAccessible(true);
            $property->setValue($task, $value);
        }
        $update = new \ReflectionMethod(updatepersonability::class, 'updateability');
        $update->setAccessible(true);

        foreach ([[0.4, 0.4, 'previous'], [null, 0, 'zero'], [NAN, 0, 'zero']] as [$previous, $expected, $kept]) {
            numeric_notice::reset();
            $context = ['person_ability' => $previous === null ? [] : [7 => $previous]];
            [$result, $reasons, $events] = $this->reported(fn() => $update->invoke($task, $context, 7));
            $this->assertSame($expected, $result['person_ability'][7], var_export($previous, true));
            $this->assertSame('abilityisnan', $result['skip_reason']);
            $this->assertSame([numeric_fallback_applied::ABILITY_NOT_FINITE], $reasons);
            $this->assertSame($kept, $events[0]->other['kept']);
            $this->assertSame(7, $events[0]->other['catscaleid']);
        }
        $this->assertDebuggingNotCalled();
    }

    /**
     * The density helpers use the same lower bound for the spread: finite for a spread of zero.
     */
    public function test_density_helpers_use_the_same_lower_bound(): void {
        foreach (['gaussian_density', 'gaussian_density_derivative1', 'gaussian_density_derivative2'] as $function) {
            foreach ([0.0, -1.0, NAN] as $sd) {
                $value = mathcat::$function(0.0005, 0.0, $sd);
                $this->assertTrue(is_finite($value), "$function, sd " . var_export($sd, true));
                $this->assertSame(mathcat::$function(0.0005, 0.0, self::minimum()), $value);
            }
        }
        $this->assertEqualsWithDelta(1 / sqrt(2 * M_PI), mathcat::gaussian_density(0.0, 0.0, 1.0), 1e-15);
    }

    /**
     * Slightly different abilities - the control group - are estimated as before, without a notice.
     */
    public function test_usable_spread_estimates_without_notice(): void {
        $this->resetAfterTest();
        [$responses, $items] = $this->sequence(1.0);

        [$ability, $reasons] = $this->reported(
            fn() => catcalc::estimate_person_ability($responses, $items, 0.0, 0.2, 0.8, -10.0, 10.0, false)
        );

        $this->assertSame([], $reasons);
        $this->assertDebuggingNotCalled();
        $this->assertTrue(is_finite($ability));
        $this->assertGreaterThan(0.2, $ability, 'All correct lies above the mean.');
    }

    /**
     * Past abilities as the estimation of a new attempt sees them.
     *
     * @param array $abilities
     * @return float The spread the estimation would use.
     */
    private function past_spread(array $abilities): float {
        $task = new class ($abilities) extends updatepersonability {
            /** @var array Past abilities. */
            private array $past;

            /**
             * Constructor.
             *
             * @param array $past
             */
            public function __construct(array $past) {
                $this->past = $past;
            }

            /**
             * The past person parameters.
             *
             * @return array
             */
            protected function get_existing_personparams(): array {
                return array_map(fn($a) => (object) ['ability' => $a], $this->past);
            }
        };
        $task->set_context(['catscaleid' => 7]);
        $method = new \ReflectionMethod(updatepersonability::class, 'calculate_sd_from_past_attempts');
        $method->setAccessible(true);
        return $method->invoke($task);
    }

    /**
     * Identical past abilities: below 50 the default spread, from 50 on a spread of 0 - reported, and
     * raised to the minimum by the estimator.
     */
    public function test_identical_past_abilities(): void {
        $this->resetAfterTest();

        foreach ([1, 2, 49] as $n) {
            [$sd, $reasons] = $this->reported(fn() => $this->past_spread(array_fill(0, $n, 0.2)));
            $this->assertSame(1.0, $sd, "N $n");
            $this->assertSame([], $reasons, "N $n");
        }

        foreach ([50, 51, 500] as $n) {
            [$sd, $reasons, $events] = $this->reported(fn() => $this->past_spread(array_fill(0, $n, 0.2)));
            $this->assertEqualsWithDelta(0.0, $sd, 1e-12, "N $n");
            $this->assertSame(self::minimum(), model_raschmodel::sanitize_trusted_region_sd($sd), "N $n: raised.");
            $this->assertSame([numeric_fallback_applied::NO_SPREAD], $reasons, "N $n");
            $this->assertSame($n, $events[0]->other['n']);
            $this->assertSame(7, $events[0]->other['catscaleid']);
            $this->assertArrayNotHasKey('userid', $events[0]->other, 'Figures only.');
        }
        $this->assertDebuggingNotCalled();
    }

    /**
     * What is not a number is left out before mean and spread are taken; too few left is the default.
     */
    public function test_past_abilities_that_are_no_number_are_left_out(): void {
        $this->resetAfterTest();

        // 60 entries, but only 40 numbers: too few for an empirical spread.
        $abilities = array_merge(array_fill(0, 40, 0.5), array_fill(0, 10, NAN), array_fill(0, 10, INF));
        $this->assertSame(1.0, $this->past_spread($abilities));

        // 60 numbers spread around 0: a regular spread.
        $abilities = [];
        for ($i = 0; $i < 60; $i++) {
            $abilities[] = ($i % 2 ? 1 : -1) * 0.5;
        }
        $abilities[] = NAN;
        $this->assertEqualsWithDelta(0.5, $this->past_spread($abilities), 1e-12);
        $this->assertDebuggingNotCalled();
    }

    /**
     * Only the shared helper decides: no model has its own guard against a spread of zero.
     */
    public function test_no_model_has_its_own_spread_guard(): void {
        global $CFG;

        $hits = [];
        foreach (glob($CFG->dirroot . '/local/catquiz/catmodel/*/classes/*.php') as $file) {
            $source = file_get_contents($file);
            if (preg_match('/function get_ability_tr_(jacobian|hessian)|sd\s*==\s*0/', $source)) {
                $hits[] = basename($file);
            }
        }
        $this->assertSame([], $hits);
    }
}
