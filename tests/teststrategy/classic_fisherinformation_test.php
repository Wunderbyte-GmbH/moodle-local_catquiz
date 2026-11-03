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
use catmodel_raschbirnbaum\raschbirnbaum;
use local_catquiz\teststrategy\preselect_task\fisherinformation;
use local_catquiz\teststrategy\strategy\classicalcat;
use local_catquiz\teststrategy\strategy\teststrategy_fastest;

/**
 * CLASSIC chooses in fixed order and computes no fisher information for the pool (issue #144).
 *
 * The information of every remaining item for every scale was computed on every answer, for a choice
 * by item id that never looks at it. Only the item handed out needs it - for the progress and the
 * feedback - and gets exactly the values it had before.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\teststrategy\strategy\classicalcat
 * @covers \local_catquiz\teststrategy\preselect_task\fisherinformation
 */
final class classic_fisherinformation_test extends advanced_testcase {
    /**
     * A selection context: a pool in shuffled order and abilities in two scales.
     *
     * @param int $pilotratio
     * @return array
     */
    private function context(int $pilotratio = 0): array {
        $progress = new class extends progress {
            /**
             * Constructor without database.
             */
            public function __construct() {
            }

            /**
             * Abilities in two scales.
             *
             * @param bool $rounded
             * @param int $precision
             * @return array
             */
            public function get_abilities(bool $rounded = false, int $precision = 2): array {
                return [10 => 0.4, 11 => -0.3];
            }
        };
        $questions = [];
        foreach ([7, 3, 9, 1, 5] as $itemid) {
            $questions[$itemid] = (object) [
                'id' => $itemid, 'itemid' => $itemid, 'componentid' => $itemid, 'model' => 'raschbirnbaum',
                'status' => 4, 'json' => null,
                'difficulty' => 0.1 * $itemid, 'discrimination' => 1.2, 'guessing' => 0.0, 'is_pilot' => false,
            ];
        }
        return [
            'questions' => $questions,
            'original_questions' => $questions,
            'installed_models' => ['raschbirnbaum' => raschbirnbaum::class],
            'progress' => $progress,
            'pilot_ratio' => $pilotratio,
        ];
    }

    /**
     * Runs a protected step of the strategy on the context.
     *
     * @param \local_catquiz\teststrategy\strategy $strategy
     * @param array $context
     * @param string $step
     * @return mixed
     */
    private function step(strategy $strategy, array $context, string $step) {
        $property = new \ReflectionProperty(strategy::class, 'context');
        $property->setAccessible(true);
        $property->setValue($strategy, $context);
        $method = new \ReflectionMethod($strategy, $step);
        $method->setAccessible(true);
        return $method->invoke($strategy)->unwrap();
    }

    /**
     * The pool gets no information; the item handed out - the lowest item id - gets it for every scale,
     * the same values the full computation gives it.
     */
    public function test_only_the_chosen_item_gets_its_information(): void {
        $this->resetAfterTest();
        $context = $this->context();

        $this->step(new classicalcat(), $context, 'fisherinformation');
        foreach ($context['questions'] as $question) {
            $this->assertFalse(isset($question->fisherinformation), "Item {$question->id} was computed for nothing.");
        }

        $chosen = $this->step(new classicalcat(), $context, 'select_question');
        $this->assertSame(1, $chosen->id, 'Fixed order by item id.');

        $expected = $this->context();
        $task = new fisherinformation();
        $task->run($expected);
        $this->assertSame($expected['questions'][1]->fisherinformation, $chosen->fisherinformation);
        $this->assertSame([10, 11], array_keys($chosen->fisherinformation));
    }

    /**
     * With pilot items the pool is annotated as before: their choice uses the information.
     */
    public function test_with_pilots_the_pool_is_annotated(): void {
        $this->resetAfterTest();
        $context = $this->context(20);

        $this->step(new classicalcat(), $context, 'fisherinformation');

        foreach ($context['questions'] as $question) {
            $this->assertTrue(isset($question->fisherinformation[10]), "Item {$question->id}");
        }
    }

    /**
     * The adaptive strategies still compute the whole pool: they choose by it.
     */
    public function test_adaptive_strategies_still_annotate_the_pool(): void {
        $this->resetAfterTest();
        $context = $this->context();

        $this->step(new teststrategy_fastest(), $context, 'fisherinformation');

        foreach ($context['questions'] as $question) {
            $this->assertTrue(isset($question->fisherinformation[11]), "Item {$question->id}");
        }
    }
}
