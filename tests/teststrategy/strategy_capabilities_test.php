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

namespace local_catquiz\teststrategy;

use advanced_testcase;
use local_catquiz\teststrategy\strategy\classicalcat;
use local_catquiz\teststrategy\strategy\inferallsubscales;
use local_catquiz\teststrategy\strategy\infergreateststrength;
use local_catquiz\teststrategy\strategy\inferlowestskillgap;
use local_catquiz\teststrategy\strategy\relevantscales;
use local_catquiz\teststrategy\strategy\teststrategy_fastest;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * Which mechanisms of the shared pipeline each strategy uses, declared in one place (issue #135).
 *
 * The matrix of the issue: FASTEST chooses globally and CLASSIC plays a fixed form, so neither drops
 * scales, applies a per-scale maximum or filters by test information; the subscale strategies do all
 * three, and ALLSUBS also covers every scale. The steps of the pipeline follow the declaration.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\teststrategy\strategy
 */
final class strategy_capabilities_test extends advanced_testcase {
    /**
     * The matrix of issue #135, per strategy.
     *
     * @return array
     */
    public static function matrix_provider(): array {
        $subscales = [
            'scale_dropping' => true, 'per_scale_selection_limits' => true, 'testinfo_filter' => true,
            'scale_coverage' => false, 'global_precision_stop' => false,
        ];
        return [
            'FASTEST' => [teststrategy_fastest::class, [
                'scale_dropping' => false, 'per_scale_selection_limits' => false, 'testinfo_filter' => false,
                'scale_coverage' => false, 'global_precision_stop' => true,
            ]],
            'CLASSIC' => [classicalcat::class, [
                'scale_dropping' => false, 'per_scale_selection_limits' => false, 'testinfo_filter' => false,
                'scale_coverage' => false, 'global_precision_stop' => false,
            ]],
            'ALLSUBS' => [inferallsubscales::class, array_merge($subscales, ['scale_coverage' => true])],
            'LOWESTSUB' => [inferlowestskillgap::class, $subscales],
            'HIGHESTSUB' => [infergreateststrength::class, $subscales],
            'RELSUBS' => [relevantscales::class, $subscales],
        ];
    }

    /**
     * Each strategy declares the mechanisms of the matrix.
     *
     * @param string $class
     * @param array $expected
     * @dataProvider matrix_provider
     */
    public function test_capabilities_follow_the_matrix(string $class, array $expected): void {
        $this->assertSame($expected, (new $class())->get_capabilities());
    }

    /**
     * Runs a protected step of a strategy on a context.
     *
     * @param strategy $strategy
     * @param string $step
     * @param array $context
     * @return array The context after the step.
     */
    private function step(strategy $strategy, string $step, array $context): array {
        $property = new \ReflectionProperty(strategy::class, 'context');
        $property->setAccessible(true);
        $property->setValue($strategy, $context);
        $method = new \ReflectionMethod($strategy, $step);
        $method->setAccessible(true);
        $method->invoke($strategy);
        return $property->getValue($strategy);
    }

    /**
     * A context in which scale 11 reached the per-scale maximum of 2.
     *
     * @return array
     */
    private function context(): array {
        $progress = (new \ReflectionClass(progress::class))->newInstanceWithoutConstructor();
        $played = [];
        foreach ([1, 2] as $id) {
            $played[$id] = (object) ['id' => $id, 'catscaleid' => 11, 'is_pilot' => false, 'fisherinformation' => []];
        }
        foreach (['playedquestions' => $played, 'playedquestionsbyscale' => [11 => $played]] as $name => $value) {
            $property = new \ReflectionProperty($progress, $name);
            $property->setAccessible(true);
            $property->setValue($progress, $value);
        }
        $questions = [];
        foreach ([3 => 11, 4 => 11, 5 => 12] as $id => $scaleid) {
            $questions[$id] = (object) ['id' => $id, 'catscaleid' => $scaleid];
        }
        return ['progress' => $progress, 'questions' => $questions, 'max_attempts_per_scale' => 2];
    }

    /**
     * The per-scale maximum takes items out of the pool only where the strategy declares it.
     *
     * @param string $class
     * @param array $expected
     * @dataProvider matrix_provider
     */
    public function test_per_scale_maximum_follows_the_declaration(string $class, array $expected): void {
        $after = $this->step(new $class(), 'mayberemovescale', $this->context());

        $this->assertSame(
            $expected['per_scale_selection_limits'] ? [5] : [3, 4, 5],
            array_keys($after['questions'])
        );
    }

    /**
     * Without scale dropping and without a global precision stop the standard error filter is not run.
     */
    public function test_classic_has_no_standard_error_stop(): void {
        $context = ['marker' => 'untouched'];
        $after = $this->step(new classicalcat(), 'filterbystandarderror', $context);

        $this->assertSame($context, $after, 'The filter ran for CLASSIC.');
    }

    /**
     * FASTEST hands out the item with the highest information on the root, also from a scale that
     * reached its per-scale maximum: the pool is not cut, the score decides.
     */
    public function test_fastest_chooses_the_best_item_of_a_well_measured_scale(): void {
        $context = $this->context();
        $context['catscaleid'] = 10;
        foreach ($context['questions'] as $id => $question) {
            $question->lasttimeplayedpenaltyfactor = 1.0;
            // Scale 11 is the one at its maximum; its item 4 is the most informative of the pool.
            $question->fisherinformation = [10 => [3 => 0.2, 4 => 0.9, 5 => 0.5][$id]];
        }

        $after = $this->step(new teststrategy_fastest(), 'mayberemovescale', $context);
        $chosen = (new \local_catquiz\teststrategy\preselect_task\strategyfastestscore())->run($after)->unwrap();

        $this->assertSame(4, $chosen->id);
    }

    /**
     * ALLSUBS deactivates a scale that has its minimum while others have not, and activates all
     * again once every scale has it.
     */
    public function test_allsubs_deactivates_and_reactivates_for_coverage(): void {
        $progress = (new \ReflectionClass(progress::class))->newInstanceWithoutConstructor();
        $set = function (array $values) use ($progress) {
            foreach ($values as $name => $value) {
                $property = new \ReflectionProperty($progress, $name);
                $property->setAccessible(true);
                $property->setValue($progress, $value);
            }
        };
        $q = fn($id, $scaleid) => (object) ['id' => $id, 'catscaleid' => $scaleid, 'is_pilot' => false];
        // Scale 11 has its one answer, scale 12 none yet.
        $set([
            'playedquestions' => [1 => $q(1, 11)],
            'playedquestionsbyscale' => [11 => [$q(1, 11)]],
            'responses' => [1 => ['questionid' => 1, 'fraction' => 1.0]],
            'abilities' => [11 => 0.0, 12 => 0.0],
            'activescales' => [11, 12],
            'lockedscales' => [],
            'droppedscales' => [],
        ]);
        $context = ['progress' => $progress, 'min_attempts_per_scale' => 1, 'teststrategy' => LOCAL_CATQUIZ_STRATEGY_ALLSUBS];

        $this->step(new inferallsubscales(), 'filterbyquestionsperscale', $context);
        $this->assertSame([12], array_values($progress->get_active_scales()), 'Scale 11 was not set aside.');

        // Now scale 12 has its answer as well: both are active again.
        $set([
            'playedquestions' => [1 => $q(1, 11), 2 => $q(2, 12)],
            'playedquestionsbyscale' => [11 => [$q(1, 11)], 12 => [$q(2, 12)]],
            'responses' => [1 => ['questionid' => 1, 'fraction' => 1.0], 2 => ['questionid' => 2, 'fraction' => 0.0]],
        ]);
        $this->step(new inferallsubscales(), 'filterbyquestionsperscale', $context);
        $active = array_values($progress->get_active_scales());
        sort($active);
        $this->assertSame([11, 12], $active);

        // The other strategies do not run this step.
        $set(['activescales' => [11, 12]]);
        $this->step(new inferlowestskillgap(), 'filterbyquestionsperscale', $context);
        $this->assertSame([11, 12], array_values($progress->get_active_scales()));
    }
}
