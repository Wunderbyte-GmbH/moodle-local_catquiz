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
 * The quiz history of attempts stored in the older format (follow-up B of issue #150).
 *
 * Rows stored before personability_after existed carry the ability as personability. The chart took
 * its labels from $abilitiesafter, which this branch never sets: count(null) is a TypeError under
 * PHP 8, and the whole feedback of such an attempt was lost. The table printed the missing value as
 * 0.00.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\teststrategy\feedbackgenerator\graphicalsummary
 */
final class graphicalsummary_legacy_data_test extends advanced_testcase {
    /**
     * Three rows in the older format: the ability as personability, no flag for pilot items.
     *
     * @return array
     */
    private static function rows(): array {
        $row = fn(int $id, float $fraction, float $ability) => [
            'id' => $id, 'questionname' => "Q$id", 'lastresponse' => $fraction, 'personability' => $ability,
            'questionscale_name' => 'Scale', 'difficulty' => 0.0, 'fisherinformation' => '0.25',
        ];
        return [$row(21, 1.0, 0.5), $row(22, 0.0, -0.25), $row(23, 1.0, 0.75)];
    }

    /**
     * The generator, with a progress that knows no pilot items.
     *
     * @return graphicalsummary
     */
    private function generator(): graphicalsummary {
        $progress = $this->getMockBuilder(progress::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get_played_pilot_questions', 'get_quiz_settings', 'get_attemptid'])
            ->getMock();
        $progress->method('get_played_pilot_questions')->willReturn([]);
        $progress->method('get_quiz_settings')->willReturn((object) [
            'catquiz_catscales' => 150001,
            'catquiz_questionfeedbacksettings' => (object) ['catquiz_showquestionresponse' => 0],
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
        return (new \ReflectionMethod(graphicalsummary::class, $name))->invokeArgs($generator, $args);
    }

    /**
     * Renders the chart of the rows and returns what the browser gets.
     *
     * @param array $rows
     * @return array [html, charts sent]
     */
    private function chart(array $rows): array {
        global $PAGE;
        $property = new \ReflectionProperty(get_class($PAGE->requires), 'amdjscode');
        $property->setValue($PAGE->requires, ['']);
        $html = self::call($this->generator(), 'render_chart', [$rows, null, []]);
        $code = implode("\n", $property->getValue($PAGE->requires));
        preg_match_all('/var data = (\{.*?\}),\s*\n\s*uniqid/s', $code, $matches);
        return [$html, array_map(fn($json) => json_decode($json, true), $matches[1])];
    }

    /**
     * Rows in the current format: the ability in the global scale, as before the fix.
     */
    public function test_the_chart_of_current_rows_is_unchanged(): void {
        $this->resetAfterTest();
        $rows = array_map(function ($row) {
            $row['personability_after'] = $row['personability'];
            unset($row['personability']);
            return $row;
        }, self::rows());

        [, $charts] = $this->chart($rows);

        $this->assertCount(1, $charts);
        $this->assertSame([1, 2, 3], $charts[0]['labels']);
        $this->assertCount(1, $charts[0]['series']);
        $this->assertSame(get_string('abilityinglobalscale', 'local_catquiz'), $charts[0]['series'][0]['label']);
        $this->assertSame([0.5, -0.25, 0.75], $charts[0]['series'][0]['values']);
    }

    /**
     * Rows of both formats in one attempt - begun before an update, finished after it: a series each, gaps where a
     * row has no value of that kind; no value is taken over from the other.
     */
    public function test_mixed_rows_have_a_series_each(): void {
        $this->resetAfterTest();
        $rows = self::rows();
        $rows[2]['personability_after'] = 0.75;
        unset($rows[2]['personability']);

        [, $charts] = $this->chart($rows);

        $this->assertCount(1, $charts);
        $series = array_column($charts[0]['series'], 'values', 'label');
        $this->assertSame([
            get_string('abilityinglobalscale', 'local_catquiz') => [null, null, 0.75],
            get_string('abilityintestedscale', 'local_catquiz') => [0.5, -0.25, null],
        ], $series);
        foreach ($charts[0]['series'] as $one) {
            $this->assertCount(3, $one['values']);
        }
    }

    /**
     * Rows without any ability: no chart - a chart of no line - and the table still lists the questions.
     */
    public function test_rows_without_an_ability_draw_no_chart(): void {
        $this->resetAfterTest();
        $rows = array_map(function ($row) {
            unset($row['personability']);
            return $row;
        }, self::rows());

        [$html, $charts] = $this->chart($rows);

        $this->assertSame('', $html);
        $this->assertSame([], $charts);
        $table = self::call($this->generator(), 'render_table', [$rows, false, [false, false, false], false]);
        $this->assertSame(3, preg_match_all('~<td[^>]*catquiz-col-number~', $table));
    }

    /**
     * The chart of older rows: one label per row, the abilities of the tested scale as the series.
     */
    public function test_the_chart_of_older_rows_is_drawn(): void {
        global $PAGE;
        $this->resetAfterTest();
        $property = new \ReflectionProperty(get_class($PAGE->requires), 'amdjscode');
        $property->setValue($PAGE->requires, ['']);

        $html = self::call($this->generator(), 'render_chart', [self::rows(), null, []]);

        $this->assertStringContainsString('chart-area', $html);
        $code = implode("\n", $property->getValue($PAGE->requires));
        $this->assertSame(1, preg_match('/var data = (\{.*?\}),\s*\n\s*uniqid/s', $code, $match), 'One chart sent.');
        $chart = json_decode($match[1], true);
        $this->assertSame([1, 2, 3], $chart['labels']);
        $this->assertCount(1, $chart['series']);
        $this->assertSame(get_string('abilityintestedscale', 'local_catquiz'), $chart['series'][0]['label']);
        $this->assertSame([0.5, -0.25, 0.75], $chart['series'][0]['values']);
    }

    /**
     * The table of older rows shows the stored ability, not 0.00 for a value it cannot find.
     */
    public function test_the_table_of_older_rows_shows_the_ability(): void {
        $this->resetAfterTest();

        $html = self::call($this->generator(), 'render_table', [self::rows(), false, [false, false, false], false]);

        foreach (['0.50', '-0.25', '0.75'] as $ability) {
            $this->assertStringContainsString(">$ability<", $html);
        }
        $this->assertStringNotContainsString('>0.00<', $html);
    }
}
