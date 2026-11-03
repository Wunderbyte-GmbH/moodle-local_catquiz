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

/**
 * The usage charts "Anzahl an Testversuchen" and "Testversuche pro Person" classify an attempt by its reported
 * result (issue #160).
 *
 * The reference case is test 20 of the export of 27.10.2026, reduced to its numbers: 17 persons with one completed
 * attempt each under "Infer lowest skill gap", a stored root snapshot (personability_after_attempt) for every one of
 * them, no valid measurement of the root scale, 13 with a valid reported primary subscale. Before, all 17 were grey,
 * "no ability was calculated" - a wrong statement about 13 of them.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\output\catquizstatistics
 * @covers \local_catquiz\local\statistics\attempt_population
 */
final class usage_chart_semantics_test extends advanced_testcase {
    /** @var int */
    private int $root;

    /** @var int[] Subscales by name. */
    private array $subs = [];

    /** @var int */
    private int $contextid;

    /** @var \stdClass */
    private \stdClass $course;

    /** @var int */
    private int $quizid;

    /** @var int */
    private int $hostattempt = 880000;

    /** @var int */
    private int $now;

    /**
     * Scales with two feedback ranges each ([-5, 0) and [0, 5]), a course and a test under LOWESTSUB.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->now = time();
        $this->contextid = (int) $DB->insert_record('local_catquiz_catcontext', (object) [
            'name' => 'Context', 'description' => '', 'descriptionformat' => FORMAT_HTML, 'starttimestamp' => 0,
            'endtimestamp' => $this->now + 10000, 'timecreated' => $this->now, 'timemodified' => $this->now,
            'usermodified' => 0,
        ]);
        $this->root = (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'parentid' => 0, 'name' => 'Root', 'contextid' => $this->contextid, 'minscalevalue' => -5,
            'maxscalevalue' => 5, 'timecreated' => $this->now, 'timemodified' => $this->now,
        ]);
        foreach (['S1', 'S2'] as $name) {
            $this->subs[$name] = (int) $DB->insert_record('local_catquiz_catscales', (object) [
                'parentid' => $this->root, 'name' => $name, 'minscalevalue' => -5, 'maxscalevalue' => 5,
                'timecreated' => $this->now, 'timemodified' => $this->now,
            ]);
        }
        $this->course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->get_plugin_generator('mod_adaptivequiz')->create_instance([
            'course' => $this->course->id, 'highestlevel' => 10, 'lowestlevel' => 1, 'standarderror' => 14,
            'attemptfeedbackeditor' => ['text' => '', 'format' => FORMAT_MOODLE],
        ]);
        $this->quizid = (int) $quiz->id;
        $settings = [
            'catquiz_catscales' => $this->root, 'catquiz_selectteststrategy' => LOCAL_CATQUIZ_STRATEGY_LOWESTSUB,
            'catquiz_catcontext' => $this->contextid, 'numberoffeedbackoptionsselect' => 2, 'name' => 'Test 20',
        ];
        foreach ([$this->root, ...array_values($this->subs)] as $scaleid) {
            // S2 has ranges of its own: a value of S2 classified by the ranges of the root scale shows.
            $ranges = $scaleid === $this->subs['S2'] ? [1 => [-5, 2.5], 2 => [2.5, 5]] : [1 => [-5, 0], 2 => [0, 5]];
            foreach ($ranges as $j => [$lower, $upper]) {
                $settings["feedback_scaleid_limit_lower_{$scaleid}_{$j}"] = $lower;
                $settings["feedback_scaleid_limit_upper_{$scaleid}_{$j}"] = $upper;
                $settings["wb_colourpicker_{$scaleid}_{$j}"] = $j === 1 ? '3' : '6';
            }
        }
        $DB->insert_record('local_catquiz_tests', (object) [
            'componentid' => $this->quizid, 'component' => 'mod_adaptivequiz', 'catscaleid' => $this->root,
            'contextid' => $this->contextid, 'courseid' => $this->course->id, 'name' => 'Test 20', 'status' => 1,
            'json' => json_encode($settings), 'timecreated' => $this->now, 'timemodified' => $this->now,
        ]);
        set_config(result_provenance::CONFIG, $this->now - 400 * DAYSECS, 'local_catquiz');
        \cache_helper::purge_all();
        eligible_population::reset();
    }

    /**
     * A completed attempt of a new person with its stored results.
     *
     * @param array $rows scaleid => [ability, isvalid, isprimary]
     * @param int $strategy
     * @return int userid
     */
    private function attempt(array $rows, int $strategy = LOCAL_CATQUIZ_STRATEGY_LOWESTSUB): int {
        global $DB;
        $userid = (int) $this->getDataGenerator()->create_and_enrol($this->course, 'student')->id;
        $end = $this->now - 3 * DAYSECS + $this->hostattempt % 1000 * 60;
        $id = (int) $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => $userid, 'scaleid' => $this->root, 'contextid' => $this->contextid,
            'courseid' => $this->course->id, 'attemptid' => ++$this->hostattempt, 'component' => 'mod_adaptivequiz',
            'instanceid' => $this->quizid, 'teststrategy' => $strategy, 'status' => LOCAL_CATQUIZ_ATTEMPT_OK,
            'starttime' => $end - 600, 'endtime' => $end,
            // A numeric root snapshot for every attempt: descriptive, never the class of the attempt.
            'personability_after_attempt' => 3.5,
            'json' => json_encode(['personabilities' => [$this->root => 3.5]]), 'debug_info' => '',
            'timecreated' => $end - 600, 'timemodified' => $end,
        ]);
        foreach ($rows as $scaleid => [$ability, $isvalid, $isprimary]) {
            $DB->insert_record('local_catquiz_personparams', (object) [
                'userid' => $userid, 'catscaleid' => $scaleid, 'contextid' => $this->contextid, 'attemptid' => $id,
                'ability' => $ability, 'isvalid' => $isvalid, 'isprimary' => $isprimary, 'resultsource' => 'current',
                'validationstatus' => $isvalid ? '' : scale_result::REASON_SE_MAX, 'status' => 0,
                'timecreated' => $end, 'timemodified' => $end,
            ]);
        }
        return $userid;
    }

    /**
     * Test 20: 9 valid on S1 at -1.0; on S2 (ranges [-5, 2.5), [2.5, 5]) 2 valid at 3.0, 1 at 2.0, 1 at exactly 0.0;
     * 4 without a valid result;
     * the root scale is measured for everybody, never validly.
     *
     * @return void
     */
    private function test20(): void {
        $root = fn(float $v) => [$this->root => [$v, 0, 0]];
        foreach (array_fill(0, 9, -1.0) as $v) {
            $this->attempt([$this->subs['S1'] => [$v, 1, 1]] + $root(0.7));
        }
        foreach ([3.0, 3.0, 2.0, 0.0] as $v) {
            $this->attempt([$this->subs['S2'] => [$v, 1, 1]] + $root(0.7));
        }
        for ($i = 0; $i < 4; $i++) {
            $this->attempt([$this->subs['S1'] => [2.0, 0, 1]] + $root(0.7));
        }
    }

    /**
     * Number of persons per class of the attempts-per-person chart (all of them made one attempt).
     *
     * @param array $data
     * @return array class => persons
     */
    private static function classes(array $data): array {
        return array_map('array_sum', $data['series']);
    }

    /**
     * The name of the grey series of the usage charts of a statistics page.
     *
     * @param catquizstatistics $statistics
     * @return string
     */
    private static function grey_label(catquizstatistics $statistics): string {
        $method = new \ReflectionMethod($statistics, 'usage_noresult_label');
        return $method->invoke($statistics);
    }

    /**
     * R160-1: the reported result classifies; grey is "no valid reported result", 4 persons, not 17.
     */
    public function test_the_root_scale_classifies_by_the_reported_result(): void {
        $this->test20();
        $statistics = new catquizstatistics((int) $this->course->id, null, $this->root);

        $perperson = $statistics->get_chart_data(catquizstatistics::CHART_ATTEMPTSPERPERSON);
        // S1: 9 in range 1; S2 by its own ranges: 2.0 and 0.0 in range 1, 3.0 twice in range 2.
        $this->assertSame([0 => 4, 1 => 11, 2 => 2], self::classes($perperson));
        $this->assertSame(17, $perperson['population']['persons']);
        $this->assertSame(13, $perperson['population']['personswithvalue']);
        $this->assertSame(13, $perperson['population']['byprimary']);
        $this->assertSame(0, $perperson['population']['noattempt']);

        $timerange = $statistics->get_chart_data(catquizstatistics::CHART_ATTEMPTSPERTIMERANGE);
        $this->assertSame([0 => 4, 1 => 11, 2 => 2], array_map('array_sum', $timerange['series']));
        $this->assertSame(17, $timerange['population']['attempts']);

        // The legend (in the chart data) says what grey means, the note under the chart how it classifies.
        $this->assertSame('No valid reported result', self::grey_label($statistics));
        foreach (['render_attempts_per_person_chart', 'render_attempts_per_timerange_chart'] as $render) {
            $out = $statistics->$render();
            $this->assertStringContainsString('this applies to 13 attempt(s) here', $out['chart'], $render);
            $this->assertStringContainsString('earlier ranges are not stored', $out['chart']);
        }
    }

    /**
     * R160-2: a subscale classifies by its own measurement; grey is "no valid measurement of this scale".
     */
    public function test_a_subscale_classifies_by_its_own_measurement(): void {
        $this->test20();
        $statistics = new catquizstatistics((int) $this->course->id, null, $this->subs['S1']);
        $perperson = $statistics->get_chart_data(catquizstatistics::CHART_ATTEMPTSPERPERSON);
        $this->assertSame([0 => 8, 1 => 9, 2 => 0], self::classes($perperson));
        $this->assertSame(0, $perperson['population']['byprimary']);
        $out = $statistics->render_attempts_per_person_chart();
        $this->assertSame('No valid measurement of this scale', self::grey_label($statistics));
        $this->assertStringContainsString('Coloured by the valid measurement of this scale', $out['chart']);
    }

    /**
     * R160-3: CLASSIC and every attempt with a valid root measurement keep the class of the root value, at the
     * boundary 0.0 too; nothing is taken from a subscale.
     */
    public function test_a_valid_root_measurement_keeps_its_class(): void {
        $this->attempt([$this->root => [0.0, 1, 1], $this->subs['S1'] => [-2.0, 1, 0]], LOCAL_CATQUIZ_STRATEGY_CLASSIC);
        $this->attempt([$this->root => [-0.5, 1, 1]], LOCAL_CATQUIZ_STRATEGY_CLASSIC);
        $this->attempt([$this->root => [1.0, 0, 1]], LOCAL_CATQUIZ_STRATEGY_CLASSIC);
        $statistics = new catquizstatistics((int) $this->course->id, null, $this->root);
        $perperson = $statistics->get_chart_data(catquizstatistics::CHART_ATTEMPTSPERPERSON);
        $this->assertSame([0 => 1, 1 => 1, 2 => 1], self::classes($perperson));
        $this->assertSame(0, $perperson['population']['byprimary']);
    }

    /**
     * No query per attempt or person.
     */
    public function test_no_query_per_person(): void {
        global $DB;
        $this->test20();
        $count = function (): int {
            global $DB;
            $before = $DB->perf_get_reads();
            eligible_population::reset();
            \cache_helper::purge_all();
            (new catquizstatistics((int) $this->course->id, null, $this->root))
                ->get_chart_data(catquizstatistics::CHART_ATTEMPTSPERPERSON);
            return $DB->perf_get_reads() - $before;
        };
        $counts = [$count()];
        foreach ([20, 20] as $more) {
            for ($i = 0; $i < $more; $i++) {
                $this->attempt([$this->subs['S1'] => [-1.0, 1, 1]]);
            }
            $counts[] = $count();
        }
        $this->assertSame($counts[1], $counts[2], 'The same number of queries for 37 and for 57 persons.');
    }
}
