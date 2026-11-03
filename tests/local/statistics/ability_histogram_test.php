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

namespace local_catquiz\local\statistics;

use advanced_testcase;
use local_catquiz\output\catquizstatistics;
use local_catquiz\teststrategy\feedback_helper;
use local_catquiz\teststrategy\feedbackgenerator\comparetotestaverage;
use local_catquiz\teststrategy\feedbacksettings;
use local_catquiz\teststrategy\progress;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * The ability histograms of the feedback and of the statistics, whatever the item pool (issue #150).
 *
 * Both took the labels of their bars from the test information - the summed Fisher information of the items
 * with parameters. Without such an item (pool empty, only pilot items, parameters not yet calculated) the
 * labels were empty and, for a teacher, the test information series had no value: core/chart_series refused it
 * ("Invalid values received for series.", the live error) inside core/chart_builder, uncaught, and the page kept
 * an empty chart area; for a student the bars had no labels and nothing was drawn.
 *
 * The data are read as the browser gets them: the chart JSON of the JavaScript the page collects.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\output\catquizstatistics::render_abilityprofilechart
 * @covers \local_catquiz\teststrategy\feedbackgenerator\comparetotestaverage::render_abilityprofile_chart
 * @covers \local_catquiz\local\statistics\chart_output
 */
final class ability_histogram_test extends advanced_testcase {
    /** @var int CAT context. */
    private int $contextid;

    /** @var int Root scale, range [-5, 5]. */
    private int $root;

    /** @var \stdClass */
    private \stdClass $course;

    /** @var array Settings of the test. */
    private array $settings;

    /** @var int Running number of the host attempts. */
    private int $hostattempt = 150000;

    /** @var string[] Labels of the ability steps of [-5, 5]: -4.75, -4.25, ..., 4.75. */
    private array $steps = [];

    /**
     * Context, root scale, course, test.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $now = time();
        $this->contextid = (int) $DB->insert_record('local_catquiz_catcontext', (object) [
            'name' => 'Issue 150', 'description' => '', 'descriptionformat' => FORMAT_HTML,
            'starttimestamp' => 0, 'endtimestamp' => $now + 10000, 'timecreated' => $now, 'timemodified' => $now,
            'usermodified' => 0,
        ]);
        $this->root = (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'parentid' => 0, 'name' => 'Global 150', 'contextid' => $this->contextid,
            'minscalevalue' => -5, 'maxscalevalue' => 5, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $this->course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->get_plugin_generator('mod_adaptivequiz')->create_instance([
            'course' => $this->course->id, 'highestlevel' => 10, 'lowestlevel' => 1, 'standarderror' => 14,
            'attemptfeedbackeditor' => ['text' => '', 'format' => FORMAT_MOODLE],
        ]);
        $this->settings = [
            'catquiz_catscales' => $this->root, 'catquiz_selectteststrategy' => LOCAL_CATQUIZ_STRATEGY_LOWESTSUB,
            'numberoffeedbackoptionsselect' => 2, 'name' => 'Test 150',
            "feedback_scaleid_limit_lower_{$this->root}_1" => -5, "feedback_scaleid_limit_upper_{$this->root}_1" => 0,
            "feedback_scaleid_limit_lower_{$this->root}_2" => 0, "feedback_scaleid_limit_upper_{$this->root}_2" => 5,
            "wb_colourpicker_{$this->root}_1" => '3', "wb_colourpicker_{$this->root}_2" => '6',
        ];
        $DB->insert_record('local_catquiz_tests', (object) [
            'componentid' => $quiz->id, 'component' => 'mod_adaptivequiz', 'catscaleid' => $this->root,
            'courseid' => $this->course->id, 'name' => 'Test 150', 'status' => 1, 'json' => json_encode($this->settings),
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        for ($a = -4.75; $a <= 4.75; $a += 0.5) {
            $this->steps[] = strval($a);
        }
        \cache_helper::purge_all();
    }

    /**
     * Items of the scale: $calibrated with parameters, $pilots without.
     *
     * @param int $calibrated
     * @param int $pilots
     * @return void
     */
    private function items(int $calibrated, int $pilots): void {
        global $DB;
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();
        for ($i = 0; $i < $calibrated + $pilots; $i++) {
            $question = $generator->create_question('truefalse', null, ['category' => $category->id]);
            $itemid = (int) $DB->insert_record('local_catquiz_items', (object) [
                'componentid' => $question->id, 'componentname' => 'question', 'catscaleid' => $this->root,
                'contextid' => $this->contextid, 'status' => LOCAL_CATQUIZ_TESTITEM_STATUS_ACTIVE,
            ]);
            if ($i < $calibrated) {
                $paramid = $DB->insert_record('local_catquiz_itemparams', (object) [
                    'itemid' => $itemid, 'componentid' => $question->id, 'componentname' => 'question',
                    'contextid' => $this->contextid, 'model' => 'raschbirnbaum', 'difficulty' => -2 + $i,
                    'discrimination' => 1.0, 'guessing' => 0, 'json' => '{}', 'status' => LOCAL_CATQUIZ_STATUS_CALCULATED,
                    'usable' => 1, 'timecreated' => time(), 'timemodified' => time(),
                ]);
                $DB->set_field('local_catquiz_items', 'activeparamid', $paramid, ['id' => $itemid]);
            }
        }
        \cache_helper::purge_all();
    }

    /**
     * Historical results: one valid result per person, as the finalisation stores it.
     *
     * @param float[] $abilities
     * @return void
     */
    private function results(array $abilities): void {
        global $DB;
        $time = time() - DAYSECS;
        foreach ($abilities as $ability) {
            $userid = (int) $this->getDataGenerator()->create_and_enrol($this->course, 'student')->id;
            $json = ['personabilities' => [$this->root => $ability], 'personabilities_abilities' => [
                $this->root => ['value' => $ability, 'primary' => true, 'toreport' => true],
            ]];
            $attemptid = (int) $DB->insert_record('local_catquiz_attempts', (object) [
                'userid' => $userid, 'scaleid' => $this->root, 'contextid' => $this->contextid,
                'courseid' => $this->course->id, 'attemptid' => ++$this->hostattempt, 'component' => 'mod_adaptivequiz',
                'instanceid' => 1, 'teststrategy' => LOCAL_CATQUIZ_STRATEGY_LOWESTSUB, 'status' => LOCAL_CATQUIZ_ATTEMPT_OK,
                'starttime' => $time - 600, 'endtime' => $time, 'personability_after_attempt' => $ability,
                'json' => json_encode($json), 'debug_info' => '', 'timecreated' => $time, 'timemodified' => $time,
            ]);
            $DB->insert_record('local_catquiz_personparams', (object) [
                'userid' => $userid, 'catscaleid' => $this->root, 'contextid' => $this->contextid,
                'attemptid' => $attemptid, 'ability' => $ability, 'isvalid' => 1, 'resultsource' => 'current',
                'isprimary' => 1, 'status' => 0, 'timecreated' => $time, 'timemodified' => $time,
            ]);
        }
        eligible_population::reset();
    }

    /**
     * The chart data the browser gets: the JSON of the charts in the JavaScript the page collected so far.
     *
     * @return array[] One decoded chart per chart rendered since the last call.
     */
    private function charts_sent(): array {
        global $PAGE;
        $property = new \ReflectionProperty(get_class($PAGE->requires), 'amdjscode');
        $code = implode("\n", $property->getValue($PAGE->requires));
        $property->setValue($PAGE->requires, ['']);
        preg_match_all('/var data = (\{.*?\}),\s*\n\s*uniqid/s', $code, $matches);
        return array_map(fn($json) => json_decode($json, true), $matches[1]);
    }

    /**
     * What Moodle's chart JavaScript checks before it draws (chart_series: values; chart_base: lengths, labels), and
     * what makes bars visible: labels.
     *
     * @param array $chart
     * @param string $where
     * @return void
     */
    private function assert_drawable(array $chart, string $where): void {
        $this->assertSame($this->steps, array_map('strval', $chart['labels']), "$where: the bars are the ability steps.");
        foreach ($chart['series'] as $series) {
            $this->assertNotEmpty($series['values'], "$where: series '{$series['label']}' has values (core/chart_series).");
            $this->assertCount(count($chart['labels']), $series['values'], "$where: series '{$series['label']}' fits the labels.");
            foreach ($series['values'] as $value) {
                $this->assertTrue(is_int($value) || is_float($value), "$where: series '{$series['label']}' has numbers.");
            }
        }
    }

    /**
     * The values of the bar series.
     *
     * @param array $chart
     * @return int[]
     */
    private function bars(array $chart): array {
        foreach ($chart['series'] as $series) {
            if (($series['type'] ?? null) !== 'line') {
                return array_map('intval', $series['values']);
            }
        }
        $this->fail('No bar series.');
    }

    /**
     * The statistics chart.
     *
     * @return array [html, charts sent]
     */
    private function statistics_chart(): array {
        $this->charts_sent();
        $out = (new catquizstatistics((int) $this->course->id, null, $this->root))->render_abilityprofilechart();
        return [$out['chart'], $this->charts_sent()];
    }

    /**
     * The chart of the personal feedback.
     *
     * @param bool $extended Teacher view: with the test information.
     * @return array [html, charts sent]
     */
    private function feedback_chart(bool $extended): array {
        $progress = $this->getMockBuilder(progress::class)->disableOriginalConstructor()
            ->onlyMethods(['get_quiz_settings'])->getMock();
        $progress->method('get_quiz_settings')->willReturn((object) $this->settings);
        $generator = $this->getMockBuilder(comparetotestaverage::class)
            ->setConstructorArgs([new feedbacksettings(LOCAL_CATQUIZ_STRATEGY_LOWESTSUB), new feedback_helper()])
            ->onlyMethods(['get_progress', 'has_extended_view_permissions'])->getMock();
        $generator->method('get_progress')->willReturn($progress);
        $generator->method('has_extended_view_permissions')->willReturn($extended);
        $this->charts_sent();
        $out = (new \ReflectionMethod($generator, 'render_abilityprofile_chart'))->invoke(
            $generator,
            ['contextid' => $this->contextid, 'personabilities_abilities' => [$this->root => ['name' => 'Global 150']]],
            ['id' => $this->root]
        );
        return [$out['chart'], $this->charts_sent()];
    }

    /**
     * Item pools from none to complete.
     *
     * @return array [calibrated items, pilot items, test information expected for a teacher]
     */
    public static function pool_provider(): array {
        return [
            'no item' => [0, 0, false],
            'only pilot items, no parameters' => [0, 3, false],
            'partly calibrated' => [2, 3, true],
            'fully calibrated (counter-check)' => [4, 0, true],
        ];
    }

    /**
     * Both histograms draw their bars from the historical results whatever the pool; the test information is
     * there when it covers every step and left out - not invented - when not (AK1-AK3, DoD2, DoD3, DoD6).
     *
     * @param int $calibrated
     * @param int $pilots
     * @param bool $withtestinfo
     * @dataProvider pool_provider
     */
    public function test_the_histograms_draw_whatever_the_pool(int $calibrated, int $pilots, bool $withtestinfo): void {
        $this->setAdminUser();
        $this->items($calibrated, $pilots);
        // Three people in [-5, -4.5), one at 0.1 (in the bar of 0.25), one at 4.9.
        $this->results([-4.9, -4.6, -4.7, 0.1, 4.9]);
        $expectedbars = array_fill(0, count($this->steps), 0);
        $expectedbars[0] = 3;
        $expectedbars[array_search('0.25', $this->steps, true)] = 1;
        $expectedbars[count($this->steps) - 1] = 1;

        $views = [
            'statistics' => fn() => $this->statistics_chart(),
            'feedback, teacher' => fn() => $this->feedback_chart(true),
            'feedback, student' => fn() => $this->feedback_chart(false),
        ];
        foreach ($views as $where => $render) {
            [$html, $charts] = $render();
            $this->assertCount(1, $charts, "$where: one chart sent.");
            $this->assert_drawable($charts[0], $where);
            $this->assertSame($expectedbars, $this->bars($charts[0]), "$where: people per step.");
            $lines = array_filter($charts[0]['series'], fn($s) => ($s['type'] ?? null) === 'line');
            $teacher = $where !== 'feedback, student';
            $this->assertCount($teacher && $withtestinfo ? 1 : 0, $lines, "$where: test information.");
            // Re-audit 27.10.2026: the reason names the scale and the CAT context; when the line is drawn, its source.
            $this->assertSame(
                $teacher && !$withtestinfo,
                str_contains($html, 'The test information is not shown:'),
                "$where: a teacher is told why the test information is missing."
            );
            $this->assertSame(
                $teacher && $withtestinfo,
                str_contains($html, 'local_catquiz-testinfo-source'),
                "$where: a teacher is told where the test information comes from."
            );
        }
    }

    /**
     * Without a valid result there is nothing to draw: a text says so, no empty chart area (AK4).
     */
    public function test_without_results_a_text_says_so(): void {
        $this->setAdminUser();
        $this->items(0, 2);
        $views = ['statistics' => fn() => $this->statistics_chart(), 'feedback' => fn() => $this->feedback_chart(true)];
        foreach ($views as $where => $render) {
            [$html, $charts] = $render();
            $this->assertSame([], $charts, "$where: no chart sent.");
            $this->assertStringContainsString(get_string('abilityprofilenodata', 'local_catquiz'), $html, $where);
            $this->assertStringNotContainsString('chart-area', $html, $where);
        }
    }

    /**
     * A chart whose series do not fit its labels is not sent to the browser, which would refuse it and leave an
     * empty area; the page says the chart could not be drawn (AK5).
     */
    public function test_an_inconsistent_chart_is_not_sent(): void {
        global $PAGE;
        $chart = new \core\chart_bar();
        $chart->add_series(new \core\chart_series('Bars', [1, 2, 3]));
        $chart->add_series(new \core\chart_series('Line', []));
        $chart->set_labels(['a', 'b', 'c']);
        $this->charts_sent();
        $html = chart_output::render($chart, true, 'No data');
        $this->assertDebuggingCalled();
        $this->assertSame([], $this->charts_sent());
        $this->assertStringContainsString(get_string('chartnotdrawn', 'local_catquiz'), $html);

        // The live chart: no labels, the test information without values - not sent either.
        $chart = new \core\chart_bar();
        $chart->add_series(new \core\chart_series('Test information', []));
        $this->assertFalse(chart_output::is_consistent($chart));
        $chart = new \core\chart_bar();
        $chart->add_series(new \core\chart_series('Bars', [1, NAN]));
        $chart->set_labels(['a', 'b']);
        $this->assertFalse(chart_output::is_consistent($chart), 'JSON has no NaN.');

        // A chart without labels has nothing to draw: the text for no data, no chart area.
        $chart = new \core\chart_bar();
        $chart->add_series(new \core\chart_series('Bars', []));
        $html = chart_output::render($chart, true, 'Nothing to show yet');
        $this->assertStringContainsString('Nothing to show yet', $html);
        $this->assertStringNotContainsString('chart-area', $html);
        $this->assertSame([], $this->charts_sent());

        // A consistent chart goes out through the template, drawn by local_catquiz/chart, which shows a message for a
        // chart the browser refuses (tests/jest/chart.test.js).
        $chart = new \core\chart_bar();
        $chart->add_series(new \core\chart_series('Bars', [1, 2, 3]));
        $chart->set_labels(['a', 'b', 'c']);
        $html = chart_output::render($chart, true, 'No data');
        $this->assertStringContainsString('chart-area', $html);
        $property = new \ReflectionProperty(get_class($PAGE->requires), 'amdjscode');
        $code = implode("\n", $property->getValue($PAGE->requires));
        $this->assertStringContainsString("'local_catquiz/chart'", $code, 'Drawn by the module that catches a refusal.');
        $this->assertStringContainsString('Chart.init(uniqid, data, ', $code);
        $this->assertStringNotContainsString('core/chart_builder', $code, 'Not by the builder, whose errors are uncaught.');
        $this->assertCount(1, $this->charts_sent());
    }
}
