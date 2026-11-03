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
use local_catquiz\catquiz;
use local_catquiz\output\catquizstatistics;
use local_catquiz\teststrategy\feedback_helper;
use local_catquiz\teststrategy\feedbackgenerator\learningprogress;
use local_catquiz\teststrategy\feedbacksettings;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * Learning progress as a cumulative "latest valid result per participant" series (issue #119).
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\local\statistics\learning_progress_series
 */
final class learning_progress_series_test extends advanced_testcase {
    /** @var string[] Four periods. */
    private const DAYS = ['d1', 'd2', 'd3', 'd4'];

    /**
     * One result.
     *
     * @param int $userid
     * @param string $day One of DAYS.
     * @param float|null $value
     * @param bool $valid
     * @param int $hour Hour of the day, for several results on one day.
     * @return array
     */
    private static function item(int $userid, string $day, ?float $value, bool $valid = true, int $hour = 12): array {
        static $order = 0;
        $time = (array_search($day, self::DAYS) + 1) * DAYSECS + $hour * HOURSECS;
        return ['userid' => $userid, 'time' => $time, 'order' => ++$order, 'bucket' => $day, 'value' => $value,
            'valid' => $valid];
    }

    /**
     * The reference example of the issue, exactly: A, B on day 1; B, C on day 2; C, A on day 3; B on day 4.
     */
    public function test_four_day_reference_example(): void {
        [$a, $b, $c] = [1, 2, 3];
        $items = [
            self::item($a, 'd1', 1.0), self::item($b, 'd1', 2.0),
            self::item($b, 'd2', 4.0), self::item($c, 'd2', 6.0),
            self::item($c, 'd3', 9.0), self::item($a, 'd3', 3.0),
            self::item($b, 'd4', 8.0),
        ];

        $series = learning_progress_series::build_cumulative_latest_series($items, self::DAYS);

        $this->assertEqualsWithDelta((1.0 + 2.0) / 2, $series['d1'], 1e-12, 'MN(A1, B1)');
        $this->assertEqualsWithDelta((1.0 + 4.0 + 6.0) / 3, $series['d2'], 1e-12, 'MN(A1, B2, C2)');
        $this->assertEqualsWithDelta((3.0 + 4.0 + 9.0) / 3, $series['d3'], 1e-12, 'MN(A3, B2, C3)');
        $this->assertEqualsWithDelta((3.0 + 8.0 + 9.0) / 3, $series['d4'], 1e-12, 'MN(A3, B4, C3)');
    }

    /**
     * A person enters with the first valid result and stays in the mean without new attempts.
     */
    public function test_late_entrant_and_staying_member(): void {
        $items = [self::item(1, 'd1', 2.0), self::item(2, 'd3', 4.0)];

        $series = learning_progress_series::build_cumulative_latest_series($items, self::DAYS);

        $this->assertSame([2.0, 2.0, 3.0, 3.0], array_values($series));
    }

    /**
     * A period without attempts carries the previous state; before the first result there is none.
     */
    public function test_period_without_attempts_carries_the_state(): void {
        $items = [self::item(1, 'd2', 1.0), self::item(2, 'd2', 3.0)];

        $series = learning_progress_series::build_cumulative_latest_series($items, self::DAYS);

        $this->assertNull($series['d1']);
        $this->assertSame(2.0, $series['d3']);
        $this->assertSame(2.0, $series['d4']);
    }

    /**
     * Two attempts of one person on one day: the later valid one counts; a later invalid one does not.
     */
    public function test_same_day_the_last_valid_result_wins(): void {
        $later = [self::item(1, 'd1', 3.0, true, 15), self::item(1, 'd1', 1.0, true, 9)];
        $this->assertSame(3.0, learning_progress_series::build_cumulative_latest_series($later, ['d1'])['d1']);

        $laterinvalid = [self::item(1, 'd1', 1.0, true, 9), self::item(1, 'd1', -5.0, false, 15)];
        $this->assertSame(1.0, learning_progress_series::build_cumulative_latest_series($laterinvalid, ['d1'])['d1']);
    }

    /**
     * An invalid result on a later day replaces nothing, and a person with only invalid results never enters.
     */
    public function test_an_invalid_result_replaces_nothing(): void {
        $items = [self::item(1, 'd1', 2.0), self::item(1, 'd2', -5.0, false), self::item(2, 'd2', 5.0, false)];

        $series = learning_progress_series::build_cumulative_latest_series($items, self::DAYS);

        $this->assertSame([2.0, 2.0, 2.0, 2.0], array_values($series));
    }

    /**
     * An ability of 0.0 is a value.
     */
    public function test_zero_is_a_value(): void {
        $items = [self::item(1, 'd1', 0.0), self::item(2, 'd1', 2.0)];

        $this->assertSame(1.0, learning_progress_series::build_cumulative_latest_series($items, ['d1'])['d1']);
    }

    /**
     * Stores a finished attempt and, where given, the results stored at its finalisation.
     *
     * @param int $userid
     * @param int $endtime
     * @param array $json personabilities of the attempt's JSON: scale => ability.
     * @param array|null $results scale => [ability, isvalid]; null for an attempt without stored results.
     * @param array $scope courseid, instanceid, contextid, scaleid
     * @return int local_catquiz_attempts.id
     */
    private function attempt(int $userid, int $endtime, array $json, ?array $results, array $scope): int {
        global $DB;
        static $hostattemptid = 7000;
        $id = (int) $DB->insert_record('local_catquiz_attempts', (object) ($scope + [
            'userid' => $userid, 'component' => 'mod_adaptivequiz', 'attemptid' => ++$hostattemptid, 'teststrategy' => 1,
            'status' => 0, 'starttime' => $endtime - 60, 'endtime' => $endtime,
            'json' => json_encode(['personabilities' => $json]), 'timecreated' => $endtime, 'timemodified' => $endtime,
        ]));
        foreach ($results ?? [] as $scaleid => [$ability, $valid]) {
            $DB->insert_record('local_catquiz_personparams', (object) [
                'userid' => $userid, 'catscaleid' => $scaleid, 'contextid' => $scope['contextid'], 'attemptid' => $id,
                'ability' => $ability, 'isvalid' => $valid ? 1 : 0, 'resultsource' => 'current', 'status' => 0,
                'timecreated' => $endtime, 'timemodified' => $endtime,
            ]);
        }
        return $id;
    }

    /**
     * The value comes from the result stored with the attempt, not from the attempt's JSON or today's
     * person parameters; an attempt without stored results (legacy) falls back to its JSON.
     */
    public function test_values_come_from_the_stored_results(): void {
        global $DB;
        $this->resetAfterTest();
        $scope = ['courseid' => 2, 'instanceid' => 5, 'contextid' => 9, 'scaleid' => 10];
        $t = mktime(12, 0, 0, 3, 2, 2026);

        $stored = $this->attempt(1, $t, [10 => 9.9], [10 => [1.5, true]], $scope);
        $invalid = $this->attempt(2, $t, [10 => 9.9], [10 => [-5.0, false]], $scope);
        $legacy = $this->attempt(3, $t, [10 => 0.0], null, $scope);
        $otherscale = $this->attempt(4, $t, [10 => 9.9], [11 => [2.0, true]], $scope);
        $nothing = $this->attempt(5, $t, [], null, $scope);
        // A later person parameter of today does not change the past.
        $DB->insert_record('local_catquiz_personparams', (object) [
            'userid' => 1, 'catscaleid' => 10, 'contextid' => 9, 'attemptid' => null, 'ability' => 4.0, 'isvalid' => 1,
            'resultsource' => 'recalibration', 'status' => 0, 'timecreated' => $t + DAYSECS, 'timemodified' => $t + DAYSECS,
        ]);

        $attempts = $DB->get_records_list('local_catquiz_attempts', 'id', [$stored, $invalid, $legacy, $otherscale, $nothing]);
        $items = learning_progress_series::items_for_attempts($attempts, 10, LOCAL_CATQUIZ_TIMERANGE_DAY);
        $byuser = array_column($items, null, 'userid');

        $this->assertSame([1, 2, 3], array_keys($byuser));
        $this->assertSame([1.5, true], [$byuser[1]['value'], $byuser[1]['valid']]);
        $this->assertSame([-5.0, false], [$byuser[2]['value'], $byuser[2]['valid']]);
        $this->assertSame([0.0, true], [$byuser[3]['value'], $byuser[3]['valid']], 'Legacy attempt, 0.0 kept.');
        $this->assertSame(feedback_helper::return_datestring_label(LOCAL_CATQUIZ_TIMERANGE_DAY, $t), $byuser[1]['bucket']);

        // Scale 11 of the same attempts: only the attempt that measured it.
        $items = learning_progress_series::items_for_attempts($attempts, 11, LOCAL_CATQUIZ_TIMERANGE_DAY);
        $this->assertSame([[4, 2.0]], array_map(fn($i) => [$i['userid'], $i['value']], $items));
    }

    /**
     * Scope and period filter the attempts that enter the series: course, test, context, start and end.
     */
    public function test_scope_and_period_select_the_attempts(): void {
        global $DB;
        $this->resetAfterTest();
        $scope = ['courseid' => 2, 'instanceid' => 5, 'contextid' => 9, 'scaleid' => 10];
        $t = mktime(12, 0, 0, 3, 2, 2026);
        $in = $this->attempt(1, $t, [], [10 => [1.0, true]], $scope);
        $this->attempt(2, $t, [], [10 => [2.0, true]], ['courseid' => 3] + $scope);
        $this->attempt(3, $t, [], [10 => [3.0, true]], ['instanceid' => 6] + $scope);
        $this->attempt(4, $t, [], [10 => [4.0, true]], ['contextid' => 8] + $scope);
        $this->attempt(5, $t - 5 * DAYSECS, [], [10 => [5.0, true]], $scope);
        $this->attempt(6, $t + 5 * DAYSECS, [], [10 => [6.0, true]], $scope);

        $records = catquiz::get_attempts(null, 10, 2, 5, 9, $t - DAYSECS, $t + DAYSECS, false);
        $attempts = [];
        foreach ($records as $record) {
            $attempts[] = $record;
        }
        if ($records instanceof \moodle_recordset) {
            $records->close();
        }

        $this->assertSame([$in], array_map(fn($a) => (int) $a->id, $attempts));
    }

    /**
     * Personal feedback and statistics shortcode draw the same two series for the same scope.
     */
    public function test_feedback_and_statistics_show_the_same_series(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $contextid = (int) $DB->insert_record('local_catquiz_catcontext', (object) [
            'name' => 'ctx', 'description' => '', 'descriptionformat' => 1, 'starttimestamp' => 0,
            'endtimestamp' => time() + YEARSECS, 'json' => '', 'usermodified' => 2, 'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $scaleid = (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'name' => 'Progress', 'description' => '', 'parentid' => 0, 'contextid' => $contextid,
            'minscalevalue' => -5, 'maxscalevalue' => 5, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $DB->insert_record('local_catquiz_tests', (object) [
            'componentid' => 77, 'component' => 'mod_adaptivequiz', 'catscaleid' => $scaleid, 'courseid' => $course->id,
            'name' => 'test', 'status' => 1, 'json' => '{}', 'contextid' => $contextid, 'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $scope = ['courseid' => $course->id, 'instanceid' => 77, 'contextid' => $contextid, 'scaleid' => $scaleid];
        $day = fn(int $n) => mktime(12, 0, 0, 3, $n, 2026);
        // A cohort of four, with an invalid and a late result, and two results of the viewer.
        $cohort = [];
        foreach ([1, 2, 3, 4, 5] as $n) {
            $cohort[$n] = (int) $this->getDataGenerator()->create_user()->id;
            $this->getDataGenerator()->enrol_user($cohort[$n], $course->id, 'student');
        }
        $viewer = $DB->get_record('user', ['id' => $cohort[5]]);
        $this->attempt($cohort[1], $day(2), [], [$scaleid => [1.0, true]], $scope);
        $this->attempt($cohort[2], $day(2), [], [$scaleid => [2.0, true]], $scope);
        $this->attempt($cohort[2], $day(3), [], [$scaleid => [-5.0, false]], $scope);
        $this->attempt($cohort[3], $day(4), [], [$scaleid => [0.0, true]], $scope);
        $this->attempt($cohort[4], $day(5), [], [$scaleid => [3.0, true]], $scope);
        $this->attempt((int) $viewer->id, $day(3), [], [$scaleid => [0.5, true]], $scope);
        $this->attempt((int) $viewer->id, $day(5), [], [$scaleid => [1.5, true]], $scope);
        $end = make_timestamp(2026, 3, 5, 23, 59, 59);

        // The chart data is in the JavaScript the chart template adds to the page.
        $chartdata = function (callable $render) use ($PAGE): array {
            $property = new \ReflectionProperty($PAGE->requires, 'amdjscode');
            $property->setAccessible(true);
            $before = count($property->getValue($PAGE->requires));
            $render();
            $code = implode("\n", array_slice($property->getValue($PAGE->requires), $before));
            preg_match_all('/var data = (\{.*?\}),\s*uniqid/s', $code, $m);
            $charts = array_map(fn($json) => json_decode($json, true), $m[1]);
            $twoseries = array_values(array_filter($charts, fn($c) => count($c['series']) === 2));
            $this->assertCount(1, $twoseries);
            return $twoseries[0];
        };

        $this->setUser($viewer);
        $statistics = $chartdata(fn() => (new catquizstatistics($course->id, 77, $scaleid, $end))->render_learning_progress());
        $generator = new learningprogress(new feedbacksettings(LOCAL_CATQUIZ_STRATEGY_FASTEST), new feedback_helper());
        $feedback = $chartdata(fn() => $generator->render_abilityprogress([
            'userid' => (int) $viewer->id, 'endtime' => $day(5), 'courseid' => $course->id, 'catscaleid' => $scaleid,
            'testid' => 77, 'contextid' => $contextid,
        ], (object) ['id' => $scaleid, 'name' => 'Progress']));

        // The statistics name the cohort first, the feedback the viewer first.
        $values = fn(array $chart, int $i) => array_map(
            fn($v) => $v === null ? null : round($v, 9),
            $chart['series'][$i]['values']
        );
        $this->assertSame($values($statistics, 0), $values($feedback, 1), 'Cohort');
        $this->assertSame($values($statistics, 1), $values($feedback, 0), 'Viewer');
        $this->assertSame($statistics['labels'], $feedback['labels']);
        // The cohort of the others: 1.5 | 1.5 (invalid ignored) | 1.0 (0.0 enters) | 1.5 (3.0 enters).
        $this->assertSame([1.5, 1.5, 1.0, 1.5], $values($feedback, 1));
        $this->assertSame([null, 0.5, 0.5, 1.5], $values($feedback, 0));
        $this->assertFalse($statistics['smooth'] ?? false, 'Straight lines, no smoothing.');
    }
}
