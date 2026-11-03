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

namespace local_catquiz\teststrategy\feedbackgenerator;

use advanced_testcase;
use local_catquiz\teststrategy\feedback_helper;
use local_catquiz\teststrategy\feedbacksettings;
use local_catquiz\teststrategy\progress;

/**
 * Pilot items in the quiz history (decision of 2026-10-14).
 *
 * Shown to teachers only, without a number - the numbers follow the chart -, with a grey verdict and
 * without an ability; never in the chart.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\teststrategy\feedbackgenerator\graphicalsummary
 */
final class graphicalsummary_pilot_test extends advanced_testcase {
    /**
     * Four rows: productive, pilot, productive, productive (an older row without the flag).
     *
     * @return array
     */
    private static function rows(): array {
        $row = fn(int $id, float $fraction, float $ability, ?bool $pilot) => array_filter([
            'id' => $id, 'questionname' => "Q$id", 'questiontitle' => "Question $id", 'lastresponse' => $fraction,
            'personability_after' => $ability, 'questionscale_name' => 'Scale', 'slot' => $id, 'questionattemptid' => $id,
            'pilot' => $pilot,
        ], fn($value) => $value !== null);
        return [$row(11, 1.0, 0.5, false), $row(12, 0.0, 0.5, true), $row(13, 0.0, -0.2, false), $row(14, 1.0, 0.1, null)];
    }

    /**
     * The generator, with a progress that reports question 14 as a pilot played before the flag existed.
     *
     * @param array $pilotids
     * @return graphicalsummary
     */
    private function generator(array $pilotids = []): graphicalsummary {
        $progress = $this->getMockBuilder(progress::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get_played_pilot_questions', 'get_quiz_settings', 'get_attemptid'])
            ->getMock();
        $progress->method('get_played_pilot_questions')
            ->willReturn(array_fill_keys($pilotids, (object) ['is_pilot' => true]));
        $progress->method('get_quiz_settings')->willReturn((object) [
            'catquiz_questionfeedbacksettings' => (object) ['catquiz_showquestionresponse' => 1],
        ]);
        $progress->method('get_attemptid')->willReturn(0);
        $generator = $this->getMockBuilder(graphicalsummary::class)
            ->setConstructorArgs([new feedbacksettings(LOCAL_CATQUIZ_STRATEGY_LOWESTSUB), new feedback_helper()])
            ->onlyMethods(['get_progress'])
            ->getMock();
        $generator->method('get_progress')->willReturn($progress);
        return $generator;
    }

    /**
     * Calls a private method.
     *
     * @param graphicalsummary $generator
     * @param string $name
     * @param array $args
     * @return mixed
     */
    private static function call(graphicalsummary $generator, string $name, array $args) {
        $method = new \ReflectionMethod(graphicalsummary::class, $name);
        $method->setAccessible(true);
        return $method->invokeArgs($generator, $args);
    }

    /**
     * The flag of a row decides; an older row asks the progress.
     */
    public function test_pilot_flags(): void {
        $this->assertSame([false, true, false, false], self::call($this->generator(), 'pilot_flags', [self::rows()]));
        $this->assertSame([false, true, false, true], self::call($this->generator([14]), 'pilot_flags', [self::rows()]));
    }

    /**
     * The chart: rows and the series of other scales without the pilot items, aligned to the end.
     */
    public function test_chart_leaves_pilots_out(): void {
        $rows = self::rows();
        [$chartrows, $series] = graphicalsummary::without_pilots(
            $rows,
            [false, true, false, false],
            [5 => [1.0, 2.0, 3.0, 4.0], 6 => [7.0, 8.0]]
        );

        $this->assertSame([11, 13, 14], array_column($chartrows, 'id'));
        $this->assertSame([1.0, 3.0, 4.0], $series[5]);
        $this->assertSame([7.0, 8.0], $series[6], 'A scale that came in late keeps its values from row 3.');
    }

    /**
     * Students do not see the pilot item, and the numbers run 1, 2, 3.
     */
    public function test_students_do_not_see_pilots(): void {
        $this->resetAfterTest();
        $html = self::call($this->generator(), 'render_table', [self::rows(), false, [false, true, false, false], false]);

        $this->assertStringNotContainsString('Question 12', $html);
        $this->assertSame(3, preg_match_all('~<td[^>]*catquiz-col-number~', $html), 'Three rows.');
        $this->assertMatchesRegularExpression('~<td[^>]*catquiz-col-number[^>]*>3</td>~', $html);
    }

    /**
     * Teachers see it: no number, a grey verdict, no ability; the numbers stay those of the chart.
     */
    public function test_teachers_see_pilots_without_number_and_ability(): void {
        $this->resetAfterTest();
        $html = self::call($this->generator(), 'render_table', [self::rows(), false, [false, true, false, false], true]);

        $this->assertStringContainsString('Question 12', $html);
        $this->assertSame(1, substr_count($html, 'catquiz-pilot-row'));
        preg_match('~<tr[^>]*catquiz-pilot-row[^>]*>(.*?)</tr>~s', $html, $pilot);
        $this->assertStringContainsString('text-secondary', $pilot[1], 'The verdict is grey.');
        $this->assertStringNotContainsString('text-danger', $pilot[1]);
        $this->assertStringContainsString(get_string('feedback_table_pilot', 'local_catquiz'), $pilot[1]);
        $this->assertStringNotContainsString('0.50', $pilot[1], 'No ability for a pilot item.');
        $this->assertMatchesRegularExpression('~<td[^>]*catquiz-col-number[^>]*>3</td>~', $html, 'Numbers as in the chart.');
        $this->assertDoesNotMatchRegularExpression('~<td[^>]*catquiz-col-number[^>]*>4</td>~', $html);
    }
}
