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
use local_catquiz\local\result\result_provenance;
use local_catquiz\local\result\scale_result;
use local_catquiz\output\catquizstatistics;
use local_catquiz\teststrategy\feedback_helper;
use local_catquiz\teststrategy\feedbackgenerator\comparetotestaverage;
use local_catquiz\teststrategy\feedbacksettings;
use local_catquiz\teststrategy\progress;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * The statistics of a test with the strategy "lowest skill gap" (LOWESTSUB, 4): the case of test 20 (issue #150,
 * re-audit 27.10.2026).
 *
 * The live case: a root scale without items of its own, its subscales with parameters in the CAT context the
 * attempts were played in; the root scale now attached to another context (an attempt is played in the context its
 * scale has at the time). Under LOWESTSUB the reported scale of an attempt is
 * the lowest subscale; the root scale is measured, but stored with isvalid = 0 and the reason not_primary. The
 * histogram of the root scale counted only isvalid = 1: almost nobody (n = 1 live). The test information was taken
 * in the context the scale has now, not in the one the results were measured in: "no item of this scale has
 * calculated parameters in this CAT context" while that context held parameters for every item.
 *
 * The fixture, by person (root scale R, range [-5, 5], bars of 0.5 around -4.75 ... 4.75):
 * - p1: attempt 1 measures R validly (not primary, -1.1); attempt 2, later, measures R with SE too large: the latest
 *   valid measurement is -1.1 (bar -1.25).
 * - p2: R measured, reporting switched off and not primary: 0.1 (bar 0.25).
 * - p3: R not measured in the attempt (no row for R): no value.
 * - p4: an attempt before stored results (no personparams of the attempt): its root value 2.3 (bar 2.25).
 * - p5: R primary and valid: 4.9 (bar 4.75).
 * - p6: an attempt that is still running (no end time, no stored results): not in the population.
 * - p7, p8: values without an attempt (resultsource legacy, attemptid null), 3.1 and -3.9: no result of an
 *   attempt - not in the statistics; estimates of the person in the context - in the peer comparison of the
 *   feedback.
 * - p9: an attempt finalised after results were stored, without a stored row, carrying the root value -1.3505 (the
 *   attempt 180 of the live case, re-audit 28.10.2026): no result - not in the histogram.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\local\statistics\attempt_population
 * @covers \local_catquiz\local\statistics\peer_population
 * @covers \local_catquiz\local\result\scale_result::is_valid_measurement
 * @covers \local_catquiz\local\result\scale_result::valid_measurement_sql
 * @covers \local_catquiz\output\catquizstatistics::render_abilityprofilechart
 * @covers \local_catquiz\output\catquizstatistics::get_testinfo_contextid
 * @covers \local_catquiz\teststrategy\feedback_helper::testinfo_for_scale
 * @covers \local_catquiz\teststrategy\feedback_helper::testinfo_note
 * @covers \local_catquiz\teststrategy\feedbackgenerator\comparetotestaverage::render_abilityprofile_chart
 * @covers \local_catquiz\local\statistics\statistics_diagnosis
 */
final class lowestsub_statistics_test extends advanced_testcase {
    /** @var int The context the root scale is attached to (catscale.contextid). */
    private int $scalecontext;

    /** @var int The CAT context of the test: all parameters are here. */
    private int $testcontext;

    /** @var int Root scale, no items of its own. */
    private int $root;

    /** @var int[] Subscales: A, A1 (below A), B. */
    private array $subs = [];

    /** @var \stdClass */
    private \stdClass $course;

    /** @var array Settings of the test. */
    private array $settings;

    /** @var int Running number of the host attempts. */
    private int $hostattempt = 270000;

    /** @var int[] Users by name. */
    private array $users = [];

    /** @var string[] Labels of the ability steps of [-5, 5]. */
    private array $steps = [];

    /** @var int Items with parameters in the test context. */
    private int $calibrated = 0;

    /**
     * Two contexts, root scale in the first, subscales with items and parameters in the second, the test.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $now = time();
        $context = fn(string $name) => (int) $DB->insert_record('local_catquiz_catcontext', (object) [
            'name' => $name, 'description' => '', 'descriptionformat' => FORMAT_HTML, 'starttimestamp' => 0,
            'endtimestamp' => $now + 10000, 'timecreated' => $now, 'timemodified' => $now, 'usermodified' => 0,
        ]);
        $this->scalecontext = $context('Context of the scale');
        $this->testcontext = $context('Context of test 20');
        $this->root = (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'parentid' => 0, 'name' => 'Mathematik 150', 'contextid' => $this->scalecontext,
            'minscalevalue' => -5, 'maxscalevalue' => 5, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $scale = fn(string $name, int $parent) => (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'parentid' => $parent, 'name' => $name, 'minscalevalue' => -5, 'maxscalevalue' => 5,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        $this->subs['A'] = $scale('A', $this->root);
        $this->subs['A1'] = $scale('A1', $this->subs['A']);
        $this->subs['B'] = $scale('B', $this->root);
        $this->course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->get_plugin_generator('mod_adaptivequiz')->create_instance([
            'course' => $this->course->id, 'highestlevel' => 10, 'lowestlevel' => 1, 'standarderror' => 14,
            'attemptfeedbackeditor' => ['text' => '', 'format' => FORMAT_MOODLE],
        ]);
        $this->settings = [
            'catquiz_catscales' => $this->root, 'catquiz_selectteststrategy' => LOCAL_CATQUIZ_STRATEGY_LOWESTSUB,
            'catquiz_catcontext' => $this->testcontext, "catquiz_scalereportcheckbox_{$this->root}" => 0,
            'numberoffeedbackoptionsselect' => 2, 'name' => 'Test 20',
            "feedback_scaleid_limit_lower_{$this->root}_1" => -5, "feedback_scaleid_limit_upper_{$this->root}_1" => 0,
            "feedback_scaleid_limit_lower_{$this->root}_2" => 0, "feedback_scaleid_limit_upper_{$this->root}_2" => 5,
            "wb_colourpicker_{$this->root}_1" => '3', "wb_colourpicker_{$this->root}_2" => '6',
        ];
        $DB->insert_record('local_catquiz_tests', (object) [
            'componentid' => $quiz->id, 'component' => 'mod_adaptivequiz', 'catscaleid' => $this->root,
            'contextid' => $this->testcontext, 'courseid' => $this->course->id, 'name' => 'Test 20', 'status' => 1,
            'json' => json_encode($this->settings), 'timecreated' => $now, 'timemodified' => $now,
        ]);
        // Items in the subscales only, all with parameters in the context of the test: 3 in A, 2 in A1, 3 in B.
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();
        foreach (['A' => 3, 'A1' => 2, 'B' => 3] as $sub => $count) {
            for ($i = 0; $i < $count; $i++) {
                $question = $generator->create_question('truefalse', null, ['category' => $category->id]);
                $itemid = (int) $DB->insert_record('local_catquiz_items', (object) [
                    'componentid' => $question->id, 'componentname' => 'question', 'catscaleid' => $this->subs[$sub],
                    'contextid' => $this->testcontext, 'status' => LOCAL_CATQUIZ_TESTITEM_STATUS_ACTIVE,
                ]);
                $paramid = $DB->insert_record('local_catquiz_itemparams', (object) [
                    'itemid' => $itemid, 'componentid' => $question->id, 'componentname' => 'question',
                    'contextid' => $this->testcontext, 'model' => 'raschbirnbaum', 'difficulty' => -2 + $i,
                    'discrimination' => 1.0, 'guessing' => 0, 'json' => '{}',
                    'status' => LOCAL_CATQUIZ_STATUS_CALCULATED, 'usable' => 1, 'timecreated' => $now,
                    'timemodified' => $now,
                ]);
                $DB->set_field('local_catquiz_items', 'activeparamid', $paramid, ['id' => $itemid]);
                $this->calibrated++;
            }
        }
        for ($a = -4.75; $a <= 4.75; $a += 0.5) {
            $this->steps[] = strval($a);
        }
        foreach (['p1', 'p2', 'p3', 'p4', 'p5', 'p6', 'p7', 'p8', 'p9'] as $name) {
            $this->users[$name] = (int) $this->getDataGenerator()->create_and_enrol($this->course, 'student')->id;
        }
        $this->fixture();
        \cache_helper::purge_all();
        eligible_population::reset();
    }

    /**
     * A CAT attempt with its stored results.
     *
     * @param string $user
     * @param int $endtime 0 for a running attempt.
     * @param array $rows scaleid => [ability, isvalid, isprimary, reasons]; empty: an attempt before stored results.
     * @param float|null $rootvalue personability_after_attempt.
     * @return int local_catquiz_attempts.id
     */
    private function attempt(string $user, int $endtime, array $rows, ?float $rootvalue = null): int {
        global $DB;
        $userid = $this->users[$user];
        $id = (int) $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => $userid, 'scaleid' => $this->root, 'contextid' => $this->testcontext,
            'courseid' => $this->course->id, 'attemptid' => ++$this->hostattempt, 'component' => 'mod_adaptivequiz',
            'instanceid' => 1, 'teststrategy' => LOCAL_CATQUIZ_STRATEGY_LOWESTSUB, 'status' => LOCAL_CATQUIZ_ATTEMPT_OK,
            'starttime' => ($endtime ?: time()) - 600, 'endtime' => $endtime ?: null,
            'personability_after_attempt' => $rootvalue, 'json' => json_encode(['personabilities' => []]),
            'debug_info' => '', 'timecreated' => $endtime ?: time(), 'timemodified' => $endtime ?: time(),
        ]);
        foreach ($rows as $scaleid => [$ability, $isvalid, $isprimary, $reasons]) {
            $DB->insert_record('local_catquiz_personparams', (object) [
                'userid' => $userid, 'catscaleid' => $scaleid, 'contextid' => $this->testcontext, 'attemptid' => $id,
                'ability' => $ability, 'isvalid' => $isvalid, 'isprimary' => $isprimary, 'resultsource' => 'current',
                'validationstatus' => implode(',', $reasons), 'status' => 0, 'timecreated' => $endtime ?: time(),
                'timemodified' => $endtime ?: time(),
            ]);
        }
        return $id;
    }

    /**
     * The persons of the class docblock.
     *
     * @return void
     */
    private function fixture(): void {
        global $DB;
        $t = time() - 10 * DAYSECS;
        // Results are stored since just after p4's attempt (result_provenance).
        set_config(result_provenance::CONFIG, $t + 1, 'local_catquiz');
        [$r, $a, $b] = [$this->root, $this->subs['A'], $this->subs['B']];
        $notprimary = [scale_result::REASON_NOT_PRIMARY];
        // The validator writes the reasons in this order: statistical ones first, then display ones.
        $this->attempt('p1', $t, [$r => [-1.1, 0, 0, $notprimary], $a => [-2.0, 1, 1, []]]);
        $this->attempt('p1', $t + DAYSECS, [
            $r => [3.6, 0, 0, [scale_result::REASON_SE_MAX, scale_result::REASON_NOT_PRIMARY]],
            $a => [-1.5, 1, 1, []],
        ]);
        $this->attempt('p2', $t, [
            $r => [0.1, 0, 0, [scale_result::REASON_REPORTING_DISABLED, scale_result::REASON_NOT_PRIMARY]],
            $b => [-0.4, 1, 1, []],
        ]);
        $this->attempt('p3', $t, [$b => [1.0, 1, 1, []]]);
        $this->attempt('p4', $t, [], 2.3);
        $this->attempt('p5', $t, [$r => [4.9, 1, 1, []]]);
        // Running: the finalisation, which stores the results, has not happened yet.
        $this->attempt('p6', 0, []);
        // Finalised after results were stored, nothing stored: the attempt 180.
        $this->attempt('p9', $t + 2 * DAYSECS, [], -1.3505);
        foreach (['p7' => 3.1, 'p8' => -3.9] as $user => $ability) {
            $DB->insert_record('local_catquiz_personparams', (object) [
                'userid' => $this->users[$user], 'catscaleid' => $r, 'contextid' => $this->testcontext,
                'attemptid' => null, 'ability' => $ability, 'isvalid' => 1, 'isprimary' => 0,
                'resultsource' => 'legacy', 'status' => 0, 'timecreated' => $t - DAYSECS, 'timemodified' => $t - DAYSECS,
            ]);
        }
    }

    /**
     * The chart data the browser gets, as in ability_histogram_test.
     *
     * @return array[]
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
     * Bars expected: people per step label.
     *
     * @param array $bylabel label => count
     * @return int[]
     */
    private function bars_expected(array $bylabel): array {
        $bars = array_fill(0, count($this->steps), 0);
        foreach ($bylabel as $label => $count) {
            $bars[array_search($label, $this->steps, true)] = $count;
        }
        return $bars;
    }

    /**
     * The bar series and the line (or null) of a chart.
     *
     * @param array $chart
     * @return array [int[] bars, array|null line]
     */
    private function split(array $chart): array {
        $bars = null;
        $line = null;
        foreach ($chart['series'] as $series) {
            if (($series['type'] ?? null) === 'line') {
                $line = $series;
            } else {
                $bars = array_map('intval', $series['values']);
            }
        }
        return [$bars, $line];
    }

    /**
     * The histogram of the root scale counts the valid measurements, reported or not: four persons, and the
     * statistics take the test information from the context the attempts were played in.
     */
    public function test_the_root_histogram_counts_valid_measurements_in_the_context_of_the_attempts(): void {
        // The standard shortcode: the course of the page, no test, no period.
        $statistics = new catquizstatistics((int) $this->course->id, null, $this->root);
        $values = attempt_population::value_per_person($statistics->get_population()->attempts(), 'last');
        ksort($values);
        $expected = [
            $this->users['p1'] => -1.1, $this->users['p2'] => 0.1, $this->users['p4'] => 2.3, $this->users['p5'] => 4.9,
        ];
        ksort($expected);
        $this->assertSame($expected, $values, 'One value per person: the latest valid measurement.');
        $this->assertCount(7, $statistics->get_population()->attempts(), 'Seven completed attempts, the running one not.');
        $this->assertSame($this->testcontext, $statistics->get_testinfo_contextid(), 'The context of the attempts.');

        $this->charts_sent();
        $out = $statistics->render_abilityprofilechart();
        $charts = $this->charts_sent();
        $this->assertCount(1, $charts);
        [$bars, $line] = $this->split($charts[0]);
        $this->assertSame($this->steps, array_map('strval', $charts[0]['labels']));
        $this->assertSame($this->bars_expected(['-1.25' => 1, '0.25' => 1, '2.25' => 1, '4.75' => 1]), $bars);
        $this->assertNotNull($line, 'The test information of the subscales of the root scale is drawn.');
        $this->assertCount(count($this->steps), $line['values']);
        $this->assertStringContainsString('n = 4 persons', $out['chart']);
        $this->assertStringContainsString(
            '(1 the reported result of the attempt, 2 measured validly without being reported, 1 from attempts finalised '
                . 'before results were stored)',
            $out['chart']
        );
        $this->assertStringContainsString(
            'Out of 7 completed attempts of 6 persons in the selection; 3 of them have no valid',
            $out['chart']
        );
        $this->assertStringContainsString(
            "Test information: {$this->calibrated} items of “Mathematik 150” and its subscales with parameters in the "
                . 'CAT context “Context of test 20”.',
            $out['chart']
        );
        $this->assertStringNotContainsString('The test information is not shown', $out['chart']);
    }

    /**
     * The feedback compares with the valid estimates of the persons in the context: the measurements of the
     * attempts, reported or not, and the values without an attempt.
     */
    public function test_the_feedback_peers_are_the_valid_measurements_in_the_context(): void {
        [$sql, $params] = peer_population::sql($this->testcontext, $this->root);
        $peers = array_map(fn($r) => (float) $r->ability, $GLOBALS['DB']->get_records_sql($sql, $params));
        ksort($peers);
        $expected = [
            $this->users['p1'] => -1.1, $this->users['p2'] => 0.1, $this->users['p5'] => 4.9,
            $this->users['p7'] => 3.1, $this->users['p8'] => -3.9,
        ];
        ksort($expected);
        $this->assertSame($expected, $peers);

        $progress = $this->getMockBuilder(progress::class)->disableOriginalConstructor()
            ->onlyMethods(['get_quiz_settings'])->getMock();
        $progress->method('get_quiz_settings')->willReturn((object) $this->settings);
        $generator = $this->getMockBuilder(comparetotestaverage::class)
            ->setConstructorArgs([new feedbacksettings(LOCAL_CATQUIZ_STRATEGY_LOWESTSUB), new feedback_helper()])
            ->onlyMethods(['get_progress', 'has_extended_view_permissions'])->getMock();
        $generator->method('get_progress')->willReturn($progress);
        $generator->method('has_extended_view_permissions')->willReturn(true);
        $this->charts_sent();
        $out = (new \ReflectionMethod($generator, 'render_abilityprofile_chart'))->invoke(
            $generator,
            ['contextid' => $this->testcontext, 'personabilities_abilities' => [$this->root => ['name' => 'Mathematik 150']]],
            ['id' => $this->root]
        );
        $charts = $this->charts_sent();
        $this->assertCount(1, $charts);
        [$bars, $line] = $this->split($charts[0]);
        $this->assertSame(
            $this->bars_expected(['-3.75' => 1, '-1.25' => 1, '0.25' => 1, '3.25' => 1, '4.75' => 1]),
            $bars
        );
        $this->assertNotNull($line);
        $this->assertStringContainsString('“Context of test 20”', $out['chart']);
    }

    /**
     * The reasons for a missing test information are told apart, with the scale and the context.
     */
    public function test_the_reason_for_a_missing_test_information(): void {
        global $DB;
        $helper = new feedback_helper();
        $steps = array_map('floatval', $this->steps);

        $intest = $helper->testinfo_for_scale($this->root, $this->testcontext, $steps);
        $this->assertNull($intest['cause']);
        $this->assertSame(
            [$this->calibrated, $this->calibrated, $this->calibrated],
            [$intest['items'], $intest['withparameters'], $intest['computed']]
        );

        // In the context of the scale there is no item: what the live warning wrongly called missing parameters.
        $inscale = $helper->testinfo_for_scale($this->root, $this->scalecontext, $steps);
        $this->assertSame(feedback_helper::TESTINFO_NOITEMS, $inscale['cause']);
        $note = feedback_helper::testinfo_note($inscale, 'Mathematik 150');
        $this->assertStringContainsString(
            'neither “Mathematik 150” nor any of its subscales has an item in the CAT context “Context of the scale”',
            $note
        );

        // Items, but no active parameters.
        $DB->delete_records('local_catquiz_itemparams', ['contextid' => $this->testcontext]);
        $DB->set_field('local_catquiz_items', 'activeparamid', null, ['contextid' => $this->testcontext]);
        \cache_helper::purge_by_event('changesintestitems');
        $noparams = $helper->testinfo_for_scale($this->root, $this->testcontext, $steps);
        $this->assertSame(feedback_helper::TESTINFO_NOPARAMETERS, $noparams['cause']);
        $this->assertSame($this->calibrated, $noparams['items']);
        $this->assertStringContainsString(
            "none of the {$this->calibrated} items of “Mathematik 150” and its subscales has active parameters",
            feedback_helper::testinfo_note($noparams, 'Mathematik 150')
        );

        // Parameters whose information cannot be computed.
        $helper = $this->getMockBuilder(feedback_helper::class)->onlyMethods(['get_testitems_for_catscale'])->getMock();
        $helper->method('get_testitems_for_catscale')->willReturn([
            (object) ['componentid' => 1, 'model' => 'raschbirnbaum', 'difficulty' => INF, 'discrimination' => NAN,
                'guessing' => 0, 'json' => '{}', 'id' => 1, 'itemid' => 1, 'contextid' => $this->testcontext,
                'status' => LOCAL_CATQUIZ_STATUS_CALCULATED],
        ]);
        $notcomputable = $helper->testinfo_for_scale($this->root, $this->testcontext, $steps);
        $this->assertDebuggingCalledCount(1);
        $this->assertSame(feedback_helper::TESTINFO_NOTCOMPUTABLE, $notcomputable['cause']);
        $this->assertSame([1, 1, 0], [$notcomputable['items'], $notcomputable['withparameters'], $notcomputable['computed']]);
    }

    /**
     * The diagnosis computes the bars from the raw rows as the statistics do, and says who is not counted and why.
     */
    public function test_the_diagnosis_explains_the_chart(): void {
        $report = statistics_diagnosis::report($this->root, (int) $this->course->id);
        $this->assertSame('LOWESTSUB (infer lowest skill gap)', $report['scope']['tests'][0]['strategyname']);
        $this->assertSame(0, $report['scope']['tests'][0]['reportcheckbox_of_scale']);
        $this->assertSame($this->scalecontext, $report['scope']['catcontext_of_scale']);
        $this->assertSame($this->testcontext, $report['scope']['tests'][0]['catcontext']);
        $this->assertSame(8, $report['funnel']['attempts of the root scale']);
        $this->assertSame(7, $report['funnel']['... completed (end time set)']);
        $this->assertSame(7, $report['funnel']['... in the period and selection (the population)']);
        $this->assertSame([
            'after results were stored, none stored, carries a value (not counted since 28.10.2026)' => 1,
            'before stored results, with value (counted)' => 1,
            'stored results, scale not among them (not measured)' => 1,
            'stored: not a valid measurement' => 1,
            'stored: valid measurement, not the reported scale (counted since 27.10.2026)' => 2,
            'stored: valid result (primary) (counted)' => 1,
        ], $report['attempts_by_value']);
        $this->assertSame(['not_primary' => 1, 'se_max' => 1], $report['reasons_of_invalid_measurements']);
        $this->assertTrue($report['bars']['identical']);
        $this->assertSame(4, $report['bars']['n_persons']);
        $this->assertSame(
            $this->bars_expected(['-1.25' => 1, '0.25' => 1, '2.25' => 1, '4.75' => 1]),
            $report['bars']['from_raw_rows']
        );
        $withoutattempt = array_values(array_filter(
            $report['personparams_of_the_scale'],
            fn($r) => (int) $r['withattempt'] === 0
        ));
        $this->assertCount(1, $withoutattempt);
        $this->assertSame(['legacy', 2, 2], [$withoutattempt[0]['resultsource'], (int) $withoutattempt[0]['rowcount'],
            (int) $withoutattempt[0]['persons']]);
        $this->assertSame($this->testcontext, $report['testinfo']['context_the_statistics_use']);
        $this->assertSame('noitems', $report['testinfo']['by_context'][$this->scalecontext]['cause']);
        $intest = $report['testinfo']['by_context'][$this->testcontext];
        $this->assertSame(
            ['shown', $this->calibrated, 0, $this->calibrated],
            [$intest['cause'], $intest['items'], $intest['of_the_scale_itself'], $intest['of_subscales']]
        );
    }

    /**
     * The rule of a valid measurement, row by row.
     */
    public function test_valid_measurement_rule(): void {
        global $DB;
        $cases = [
            'valid result' => [1, '', true],
            'valid result, reporting switched off' => [1, 'reporting_disabled', true],
            'not the reported scale' => [0, 'not_primary', true],
            'hidden, not reported' => [0, 'reporting_disabled,hidden,not_primary', true],
            'SE too large' => [0, 'se_max,not_primary', false],
            'not measured' => [0, 'not_primary,not_measured_in_current_attempt', false],
            'below the minimum of questions' => [0, 'min_questions_not_reached,not_primary', false],
            'invalid without reasons (before reasons were stored)' => [0, '', false],
            'invalid, reasons null' => [0, null, false],
        ];
        foreach ($cases as $name => [$isvalid, $status, $expected]) {
            $this->assertSame($expected, scale_result::is_valid_measurement($isvalid, $status), $name);
            $id = $DB->insert_record('local_catquiz_personparams', (object) [
                'userid' => 1, 'catscaleid' => 999999, 'contextid' => 1, 'ability' => 0, 'isvalid' => $isvalid,
                'validationstatus' => $status, 'status' => 0, 'timecreated' => 0, 'timemodified' => 0,
            ]);
            [$sql, $params] = scale_result::valid_measurement_sql('p', 'vm');
            $this->assertSame(
                $expected,
                $DB->record_exists_sql(
                    "SELECT 1 FROM {local_catquiz_personparams} p WHERE p.id = :id AND $sql",
                    $params + ['id' => $id]
                ),
                "$name (SQL)"
            );
        }
    }
}
