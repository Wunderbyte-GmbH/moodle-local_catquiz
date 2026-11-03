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
use local_catquiz\local\result\result_provenance;
use local_catquiz\local\result\scale_result;
use local_catquiz\output\catquizstatistics;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * The result contract of the statistics: which attempt gives a value of the root scale (issue #150, re-audit
 * 28.10.2026, AP-01/AP-02, regression cases R01-R10, R16, R19, R20).
 *
 * The case of test 20, as the export of 27.10.2026 shows it, anonymised: a root scale with seven subscales,
 * "lowest skill gap", reporting of the root scale off. 34 attempts, 30 completed, 27 persons; 29 completed attempts
 * with stored results of their subscales, none of them a valid measurement of the root scale; one completed
 * attempt (the "attempt 180") finalised after results were stored, without a stored row, empty reported results
 * and a value of -1.3505 on the root scale. The second shortcode filters the end times from a start: 21 attempts,
 * 20 persons. Reported primary subscales: 19 persons (9/4/2/1/1/1/1), in the period 15 (9/2/2/1/1).
 *
 * Before: an attempt without stored rows counted as legacy, with its value, as valid - the root histogram was one
 * bar of one person at -1.25. Now the time of its finalisation decides (result_provenance).
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\local\statistics\attempt_population
 * @covers \local_catquiz\local\result\result_provenance
 * @covers \local_catquiz\local\statistics\learning_progress_series
 * @covers \local_catquiz\local\statistics\statistics_diagnosis
 * @covers \local_catquiz\output\catquizstatistics::render_abilityprofilechart
 * @covers \local_catquiz\output\catquizstatistics::get_chart_data
 */
final class root_result_contract_test extends advanced_testcase {
    /** @var int[] CAT contexts by name: 1601, 1603 (earlier calibrations), 1605 (now). */
    private array $contexts = [];

    /** @var int Root scale. */
    private int $root;

    /** @var int[] Subscales S1..S7. */
    private array $subs = [];

    /** @var \stdClass */
    private \stdClass $course;

    /** @var int adaptivequiz.id of test 20. */
    private int $quizid;

    /** @var int[] Users by name. */
    private array $users = [];

    /** @var int Running number of the host attempts. */
    private int $hostattempt = 770000;

    /** @var int Now, fixed for the test. */
    private int $now;

    /** @var int The start of the period of the second shortcode. */
    private int $periodstart;

    /** @var int local_catquiz_attempts.id of the "attempt 180". */
    private int $attempt180;

    /**
     * Contexts, scales, course, test 20; results are stored since a year.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->now = time();
        $this->periodstart = $this->now - 30 * DAYSECS;
        foreach (['1601', '1603', '1605'] as $name) {
            $this->contexts[$name] = (int) $DB->insert_record('local_catquiz_catcontext', (object) [
                'name' => "Context $name", 'description' => '', 'descriptionformat' => FORMAT_HTML, 'starttimestamp' => 0,
                'endtimestamp' => $this->now + 10000, 'timecreated' => $this->now, 'timemodified' => $this->now,
                'usermodified' => 0,
            ]);
        }
        $this->root = (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'parentid' => 0, 'name' => 'ALiSe Mathematik', 'contextid' => $this->contexts['1605'],
            'minscalevalue' => -5, 'maxscalevalue' => 5, 'timecreated' => $this->now, 'timemodified' => $this->now,
        ]);
        foreach (['S1', 'S2', 'S3', 'S4', 'S5', 'S6', 'S7'] as $name) {
            $this->subs[$name] = (int) $DB->insert_record('local_catquiz_catscales', (object) [
                'parentid' => $this->root, 'name' => $name, 'minscalevalue' => -5, 'maxscalevalue' => 5,
                'timecreated' => $this->now, 'timemodified' => $this->now,
            ]);
        }
        $this->course = $this->getDataGenerator()->create_course();
        $this->quizid = $this->add_test(LOCAL_CATQUIZ_STRATEGY_LOWESTSUB, 'Test 20');
        set_config(result_provenance::CONFIG, $this->now - 400 * DAYSECS, 'local_catquiz');
        \cache_helper::purge_all();
        eligible_population::reset();
    }

    /**
     * A test of the root scale in the course.
     *
     * @param int $strategy
     * @param string $name
     * @return int adaptivequiz.id
     */
    private function add_test(int $strategy, string $name): int {
        global $DB;
        $quiz = $this->getDataGenerator()->get_plugin_generator('mod_adaptivequiz')->create_instance([
            'course' => $this->course->id, 'highestlevel' => 10, 'lowestlevel' => 1, 'standarderror' => 14,
            'attemptfeedbackeditor' => ['text' => '', 'format' => FORMAT_MOODLE],
        ]);
        $settings = [
            'catquiz_catscales' => $this->root, 'catquiz_selectteststrategy' => $strategy,
            'catquiz_catcontext' => $this->contexts['1605'], "catquiz_scalereportcheckbox_{$this->root}" => 0,
            'numberoffeedbackoptionsselect' => 2, 'name' => $name,
        ];
        foreach ([$this->root, ...array_values($this->subs)] as $scaleid) {
            foreach ([1 => [-5, 0], 2 => [0, 5]] as $j => [$lower, $upper]) {
                $settings["feedback_scaleid_limit_lower_{$scaleid}_{$j}"] = $lower;
                $settings["feedback_scaleid_limit_upper_{$scaleid}_{$j}"] = $upper;
                $settings["wb_colourpicker_{$scaleid}_{$j}"] = $j === 1 ? '3' : '6';
            }
        }
        $DB->insert_record('local_catquiz_tests', (object) [
            'componentid' => $quiz->id, 'component' => 'mod_adaptivequiz', 'catscaleid' => $this->root,
            'contextid' => $this->contexts['1605'], 'courseid' => $this->course->id, 'name' => $name, 'status' => 1,
            'json' => json_encode($settings), 'timecreated' => $this->now, 'timemodified' => $this->now,
        ]);
        return (int) $quiz->id;
    }

    /**
     * A person of the course.
     *
     * @param string $name
     * @return int
     */
    private function user(string $name): int {
        if (!isset($this->users[$name])) {
            $this->users[$name] = (int) $this->getDataGenerator()->create_and_enrol($this->course, 'student')->id;
        }
        return $this->users[$name];
    }

    /**
     * A CAT attempt.
     *
     * @param string $user
     * @param int|null $endtime Null: running.
     * @param array|null $rows scaleid => [ability, isvalid, isprimary, reasons]; null: no stored row.
     * @param array $options json (the reported results), rootvalue (personability_after_attempt), context, quizid,
     *     strategy.
     * @return int local_catquiz_attempts.id
     */
    private function attempt(string $user, ?int $endtime, ?array $rows, array $options = []): int {
        global $DB;
        $userid = $this->user($user);
        $contextid = $this->contexts[$options['context'] ?? '1605'];
        $id = (int) $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => $userid, 'scaleid' => $this->root, 'contextid' => $contextid, 'courseid' => $this->course->id,
            'attemptid' => ++$this->hostattempt, 'component' => 'mod_adaptivequiz',
            'instanceid' => $options['quizid'] ?? $this->quizid,
            'teststrategy' => $options['strategy'] ?? LOCAL_CATQUIZ_STRATEGY_LOWESTSUB,
            'status' => $endtime ? LOCAL_CATQUIZ_ATTEMPT_OK : LOCAL_CATQUIZ_ATTEMPT_RUNNING,
            'starttime' => ($endtime ?? $this->now) - 600, 'endtime' => $endtime,
            'personability_after_attempt' => $options['rootvalue'] ?? null,
            'json' => json_encode($options['json'] ?? ['personabilities' => []]), 'debug_info' => '',
            'timecreated' => ($endtime ?? $this->now) - 600, 'timemodified' => $endtime ?? $this->now,
        ]);
        foreach ($rows ?? [] as $scaleid => [$ability, $isvalid, $isprimary, $reasons]) {
            $DB->insert_record('local_catquiz_personparams', (object) [
                'userid' => $userid, 'catscaleid' => $scaleid, 'contextid' => $contextid, 'attemptid' => $id,
                'ability' => $ability, 'isvalid' => $isvalid, 'isprimary' => $isprimary, 'resultsource' => 'current',
                'validationstatus' => implode(',', $reasons), 'status' => 0, 'timecreated' => $endtime,
                'timemodified' => $endtime,
            ]);
        }
        return $id;
    }

    /**
     * An attempt under "lowest skill gap" whose reported subscale is valid, or not; the root scale measured with a
     * standard error too large - no valid measurement of the root scale.
     *
     * @param string $user
     * @param int $endtime
     * @param string $sub S1..S7
     * @param bool $valid
     * @param string $context
     * @return int
     */
    private function lowestsub(string $user, int $endtime, string $sub, bool $valid, string $context = '1605'): int {
        $subid = $this->subs[$sub];
        $value = $valid ? -1.0 : 2.0;
        return $this->attempt($user, $endtime, [
            $subid => [$value, $valid ? 1 : 0, 1, $valid ? [] : [scale_result::REASON_SE_MAX]],
            $this->root => [0.7, 0, 0, [scale_result::REASON_SE_MAX, scale_result::REASON_REPORTING_DISABLED,
                scale_result::REASON_NOT_PRIMARY]],
        ], [
            'context' => $context,
            'rootvalue' => 0.7,
            'json' => [
                'personabilities' => [$this->root => 0.7, $subid => $value],
                'personabilities_abilities' => [
                    $subid => ['value' => $value, 'primary' => true, 'toreport' => $valid],
                    $this->root => ['value' => 0.7, 'primary' => false, 'toreport' => false],
                ],
            ],
        ]);
    }

    /**
     * The fixture of the class comment.
     *
     * @return void
     */
    private function test20(): void {
        $day = DAYSECS;
        $in = $this->now - 20 * $day;
        $before = $this->now - 60 * $day;
        $n = 0;
        // In the period: 15 persons with a valid reported subscale, 9/2/2/1/1.
        foreach (['S1' => 9, 'S2' => 2, 'S3' => 2, 'S4' => 1, 'S5' => 1] as $sub => $count) {
            for ($i = 0; $i < $count; $i++) {
                $this->lowestsub('v' . (++$n), $in + $n * 3600, $sub, true, $n % 3 ? '1605' : '1603');
            }
        }
        // ... one of them with an earlier invalid attempt in the period, two with one before it.
        $this->lowestsub('v1', $in - $day, 'S1', false);
        $this->lowestsub('v2', $before - $day, 'S1', false, '1601');
        $this->lowestsub('v3', $before - $day, 'S2', false, '1601');
        // Before the period: 4 more persons with a valid reported subscale, S2 twice, S6, S7.
        foreach (['S2', 'S2', 'S6', 'S7'] as $sub) {
            $this->lowestsub('v' . (++$n), $before + $n * 3600, $sub, true, '1601');
        }
        // Persons without a valid result: four in the period, three before.
        foreach (['i1', 'i2', 'i3', 'i4'] as $k => $user) {
            $this->lowestsub($user, $in + ($k + 1) * 600, 'S1', false);
        }
        foreach (['i5', 'i6', 'i7'] as $k => $user) {
            $this->lowestsub($user, $before + ($k + 1) * 600, 'S3', false, '1603');
        }
        // The attempt 180: finalised after results were stored, no stored row, nothing reported, a root value.
        $this->attempt180 = $this->attempt('a180', $in + 5 * $day, null, [
            'rootvalue' => -1.3505,
            'json' => ['personabilities' => [$this->root => -1.3505], 'personabilities_abilities' => []],
        ]);
        // Four running attempts.
        foreach (['v1', 'v5', 'i1', 'a180'] as $user) {
            $this->attempt($user, null, null);
        }
    }

    /**
     * People per subscale in the chart of the detected scales.
     *
     * @param array $data get_chart_data('detectedscales')
     * @return array name => persons
     */
    private function detected(array $data): array {
        $totals = [];
        foreach ($data['labels'] as $i => $label) {
            $totals[$label] = 0;
            foreach ($data['series'] as $values) {
                $totals[$label] += $values[$i];
            }
        }
        return $totals;
    }

    /**
     * R01, R02, R03, R16: test 20 without and with the period of the second shortcode.
     *
     * @param int|null $starttime
     * @param int $attempts
     * @param int $persons
     * @param array $detected
     * @dataProvider cases_of_test20
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('cases_of_test20')]
    public function test_test20_has_no_root_bar_and_keeps_its_subscales(
        ?int $starttime,
        int $attempts,
        int $persons,
        array $detected
    ): void {
        global $DB;
        $this->test20();
        $this->assertSame(34, $DB->count_records('local_catquiz_attempts', ['scaleid' => $this->root]));
        $starttime = $starttime === null ? null : $this->periodstart;
        $statistics = new catquizstatistics((int) $this->course->id, null, $this->root, null, $starttime);

        $profile = $statistics->get_chart_data(catquizstatistics::CHART_ABILITYPROFILE);
        $this->assertSame($attempts, $profile['population']['attempts'], 'Completed attempts in the selection.');
        $this->assertSame($persons, $profile['population']['attemptpersons'], 'Persons with an attempt.');
        $this->assertSame(0, $profile['population']['persons'], 'No valid value of the root scale - no bar.');
        $this->assertSame(0, array_sum($profile['series']['counter']));
        $this->assertSame(1, $profile['population']['noresult'], 'The attempt 180: judged, nothing stored.');
        $this->assertSame(array_sum($detected), $profile['population']['primarypersons']);

        $scales = $statistics->get_chart_data(catquizstatistics::CHART_DETECTEDSCALES);
        $this->assertSame(array_sum($detected), $scales['population']['persons']);
        $this->assertEquals($detected, $this->detected($scales), 'The detected scales keep every person.');

        $out = $statistics->render_abilityprofilechart();
        $this->assertStringContainsString('local_catquiz-abilityprofile-novalue', $out['chart']);
        $this->assertStringContainsString(
            "None of the $attempts completed attempts of the selection has a valid measurement in “ALiSe Mathematik”.",
            $out['chart']
        );
        $this->assertStringContainsString(array_sum($detected) . ' persons have a valid reported result', $out['chart']);
        $this->assertStringContainsString('every test of the scale in this course (1)', $out['chart']);
        $this->assertStringNotContainsString('Test information', $out['chart'], 'No pool information without bars.');
    }

    /**
     * The two shortcodes of the page.
     *
     * @return array
     */
    public static function cases_of_test20(): array {
        return [
            'R01 without period' => [null, 30, 27, ['S1' => 9, 'S2' => 4, 'S3' => 2, 'S4' => 1, 'S5' => 1, 'S6' => 1, 'S7' => 1]],
            'R02 from the start of the period' => [1, 21, 20, ['S1' => 9, 'S2' => 2, 'S3' => 2, 'S4' => 1, 'S5' => 1]],
        ];
    }

    /**
     * R05, R06: the time of finalisation decides, not a missing row - in PHP and in SQL alike.
     */
    public function test_a_missing_row_alone_makes_no_legacy_attempt(): void {
        global $DB;
        $since = $this->now - 100 * DAYSECS;
        set_config(result_provenance::CONFIG, $since, 'local_catquiz');
        // Before results were stored: never judged, counts with its value (R06).
        $old = $this->attempt('old', $since - DAYSECS, null, [
            'rootvalue' => 1.2, 'json' => ['personabilities' => [$this->root => 1.2, $this->subs['S1'] => 0.4]],
        ]);
        // After: judged, nothing stored - aborted, nothing measured, the error of #106 (R05).
        $new = $this->attempt('new', $since + DAYSECS, null, [
            'rootvalue' => -1.3505, 'json' => ['personabilities' => [$this->root => -1.3505, $this->subs['S1'] => -2.0]],
        ]);
        // At the boundary itself: after.
        $edge = $this->attempt('edge', $since, null, ['rootvalue' => 3.3]);

        foreach ([$this->root, $this->subs['S1']] as $scaleid) {
            $byid = [];
            foreach ((new attempt_population($scaleid, (int) $this->course->id))->attempts() as $attempt) {
                $byid[(int) $attempt->id] = $attempt;
            }
            $this->assertSame(result_provenance::LEGACY, $byid[$old]->provenance);
            $this->assertTrue($byid[$old]->primaryvalid);
            $this->assertSame(result_provenance::NORESULT, $byid[$new]->provenance);
            $this->assertNull($byid[$new]->value);
            $this->assertFalse($byid[$new]->primaryvalid, 'No valid reported result either.');
            $this->assertNull($byid[$edge]->value);
        }
        $root = [];
        foreach ((new attempt_population($this->root, (int) $this->course->id))->attempts() as $attempt) {
            $root[(int) $attempt->id] = $attempt->value;
        }
        $this->assertEqualsWithDelta(1.2, $root[$old], 0.0001);

        // The same in SQL (value_sql), attempt by attempt.
        $population = new attempt_population($this->root, (int) $this->course->id);
        [$where, $params] = $population->where('a', 'w');
        [$value, $valueparams] = $population->value_sql('a', 'v');
        $sql = $DB->get_records_sql_menu(
            "SELECT a.id, $value AS v FROM {local_catquiz_attempts} a WHERE $where",
            $params + $valueparams
        );
        $this->assertEqualsWithDelta(1.2, (float) $sql[$old], 0.0001);
        $this->assertNull($sql[$new]);
        $this->assertNull($sql[$edge]);

        // The learning progress: the old attempt gives an item, the new one none.
        $items = learning_progress_series::items_for_attempts(
            $DB->get_records_list('local_catquiz_attempts', 'id', [$old, $new, $edge]),
            $this->root,
            LOCAL_CATQUIZ_TIMERANGE_DAY
        );
        $this->assertSame([$this->users['old']], array_column($items, 'userid'));
    }

    /**
     * Without a recorded boundary no attempt counts as legacy: a missing row is no proof.
     */
    public function test_without_a_boundary_nothing_is_legacy(): void {
        global $DB;
        unset_config(result_provenance::CONFIG, 'local_catquiz');
        $this->assertSame(0, result_provenance::since());
        $id = $this->attempt('old', $this->now - 900 * DAYSECS, null, ['rootvalue' => 1.2]);
        $attempts = (new attempt_population($this->root, (int) $this->course->id))->attempts();
        $this->assertSame(result_provenance::NORESULT, $attempts[0]->provenance);
        $population = new attempt_population($this->root, (int) $this->course->id);
        [$value, $params] = $population->value_sql('a', 'v');
        $this->assertNull($DB->get_field_sql(
            "SELECT $value FROM {local_catquiz_attempts} a WHERE a.id = :id",
            $params + ['id' => $id]
        ));
    }

    /**
     * The boundary from the records of the site: the upgrade log, or the first stored result, whichever is earlier.
     */
    public function test_the_boundary_comes_from_the_records(): void {
        global $DB;
        unset_config(result_provenance::CONFIG, 'local_catquiz');
        unset_config(result_provenance::CONFIG_SOURCE, 'local_catquiz');
        $DB->delete_records('upgrade_log', ['plugin' => 'local_catquiz']);
        $DB->delete_records('local_catquiz_personparams');
        $this->assertNull(result_provenance::determine()['since']);

        $log = fn(string $version, int $time) => $DB->insert_record('upgrade_log', (object) [
            'type' => 0, 'plugin' => 'local_catquiz', 'version' => $version, 'targetversion' => $version,
            'info' => 'Upgrade savepoint reached', 'details' => null, 'backtrace' => null, 'userid' => 0,
            'timemodified' => $time,
        ]);
        $log('2026081500', 1000);
        $log('2026082104', 5000);
        $log('2026092605', 7000);
        $this->assertSame(
            ['since' => 5000, 'source' => 'upgradelog', 'upgradelog' => 5000, 'firstresult' => null],
            result_provenance::determine()
        );

        // A stored result before the log entry: the log was cleaned up - the earlier one wins.
        $this->attempt('p', 4000, [$this->root => [0.0, 1, 1, []]]);
        $this->assertSame(
            ['since' => 4000, 'source' => 'firstresult', 'upgradelog' => 5000, 'firstresult' => 4000],
            result_provenance::determine()
        );

        // Recorded once; a value already set stays.
        $this->assertSame(4000, result_provenance::record(99999)['since']);
        $this->assertSame(4000, result_provenance::since());
        $this->assertSame('firstresult', get_config('local_catquiz', result_provenance::CONFIG_SOURCE));
        $this->assertSame(4000, result_provenance::record(99999)['since']);

        // Nothing in the records: the time of the upgrade.
        unset_config(result_provenance::CONFIG, 'local_catquiz');
        $DB->delete_records('upgrade_log', ['plugin' => 'local_catquiz']);
        $DB->delete_records('local_catquiz_personparams');
        $this->assertSame(12345, result_provenance::record(12345)['since']);
        $this->assertSame('upgrade', get_config('local_catquiz', result_provenance::CONFIG_SOURCE));
    }

    /**
     * R04, R19: where a root value exists, it counts - reported (CLASSIC, FASTEST), measured but not reported
     * (LOWESTSUB, RELSUBS) - and the chart says which. A modern attempt without a row adds nothing, whatever the
     * strategy.
     *
     * @param int $strategy
     * @param bool $reportsroot
     * @dataProvider strategy_provider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('strategy_provider')]
    public function test_the_root_contract_per_strategy(int $strategy, bool $reportsroot): void {
        $this->add_test($strategy, 'Strategy ' . $strategy);
        global $DB;
        $quizid = (int) $DB->get_field_sql('SELECT MAX(componentid) FROM {local_catquiz_tests}');
        $t = $this->now - 5 * DAYSECS;
        $rootrow = fn(float $v) => $reportsroot ? [$v, 1, 1, []] : [$v, 0, 0, [scale_result::REASON_NOT_PRIMARY]];
        $this->attempt('p0', $t, [$this->root => $rootrow(0.0)], ['quizid' => $quizid, 'strategy' => $strategy]);
        $this->attempt('pmin', $t, [$this->root => $rootrow(-5.0)], ['quizid' => $quizid, 'strategy' => $strategy]);
        $this->attempt('pedge', $t, [$this->root => $rootrow(0.25)], ['quizid' => $quizid, 'strategy' => $strategy]);
        $this->attempt('pnone', $t, null, ['quizid' => $quizid, 'strategy' => $strategy, 'rootvalue' => 2.0]);

        $statistics = new catquizstatistics((int) $this->course->id, $quizid, $this->root);
        $data = $statistics->get_chart_data(catquizstatistics::CHART_ABILITYPROFILE);
        $this->assertSame(3, $data['population']['persons']);
        $this->assertSame(
            ['reported' => $reportsroot ? 3 : 0, 'measured' => $reportsroot ? 0 : 3, 'legacy' => 0],
            $data['population']['bysource']
        );
        $labels = array_map('strval', $data['labels']);
        $bars = array_combine($labels, $data['series']['counter']);
        // Bars (step - 0.25, step + 0.25]: 0 in -0.25, 0.25 in 0.25, the minimum in the first.
        $this->assertSame(1, $bars['-0.25']);
        $this->assertSame(1, $bars['0.25']);
        $this->assertSame(1, $bars['-4.75']);
        $this->assertSame(3, array_sum($bars));
        $out = $statistics->render_abilityprofilechart();
        $this->assertStringContainsString(
            'n = 3 persons, one value each: the latest valid measurement in “ALiSe Mathematik” ('
                . ($reportsroot
                    ? '3 the reported result of the attempt, 0 measured validly'
                    : '0 the reported result of the attempt, 3 measured validly'),
            $out['chart']
        );
        $this->assertStringContainsString('the test “Strategy ' . $strategy . '”', $out['chart']);
    }

    /**
     * The strategies of R19.
     *
     * @return array
     */
    public static function strategy_provider(): array {
        return [
            'CLASSIC' => [LOCAL_CATQUIZ_STRATEGY_CLASSIC, true],
            'FASTEST' => [LOCAL_CATQUIZ_STRATEGY_FASTEST, true],
            'LOWESTSUB' => [LOCAL_CATQUIZ_STRATEGY_LOWESTSUB, false],
            'RELSUBS' => [LOCAL_CATQUIZ_STRATEGY_RELSUBS, false],
        ];
    }

    /**
     * R07, R08: an invalid, an aborted and a running attempt; a later attempt without result hides no valid one.
     */
    public function test_invalid_aborted_running_and_the_latest_valid(): void {
        $t = $this->now - 10 * DAYSECS;
        // P1: valid S1, then a modern attempt without stored result (like the attempt 180).
        $this->lowestsub('p1', $t, 'S1', true);
        $this->attempt('p1', $t + DAYSECS, null, ['rootvalue' => -1.3505, 'json' => ['personabilities_abilities' => []]]);
        // P2: valid S2, then invalid S2.
        $this->lowestsub('p2', $t, 'S2', true);
        $this->lowestsub('p2', $t + DAYSECS, 'S2', false);
        // P3: aborted (no row) and running.
        $this->attempt('p3', $t, null);
        $this->attempt('p3', null, null);

        $statistics = new catquizstatistics((int) $this->course->id, null, $this->root);
        $scales = $statistics->get_chart_data(catquizstatistics::CHART_DETECTEDSCALES);
        $this->assertEquals(['S1' => 1, 'S2' => 1], $this->detected($scales), 'The latest valid result of each person.');
        $sub = (new catquizstatistics((int) $this->course->id, null, $this->subs['S1']))
            ->get_chart_data(catquizstatistics::CHART_ABILITYPROFILE);
        $this->assertSame(1, $sub['population']['persons'], 'S1: P1 once, with the valid value.');
        $this->assertSame(5, $sub['population']['attempts'], 'Five completed attempts - the running one not.');
        $all = (new attempt_population($this->root, (int) $this->course->id))->attempts();
        $this->assertCount(5, $all);
    }

    /**
     * R09, R10: without a test, every test of the scale in the course counts, and attempts of every calibration.
     */
    public function test_scope_without_a_test_and_earlier_calibrations(): void {
        $second = $this->add_test(LOCAL_CATQUIZ_STRATEGY_CLASSIC, 'Test 21');
        $t = $this->now - 10 * DAYSECS;
        $this->attempt('a', $t, [$this->root => [1.1, 1, 1, []]], ['context' => '1601']);
        $this->attempt('b', $t, [$this->root => [-1.1, 1, 1, []]], ['context' => '1603']);
        $this->attempt('c', $t + 1, [$this->root => [0.6, 1, 1, []]], ['context' => '1605', 'quizid' => $second]);

        $course = new catquizstatistics((int) $this->course->id, null, $this->root);
        $this->assertSame(3, $course->get_chart_data(catquizstatistics::CHART_ABILITYPROFILE)['population']['persons']);
        $this->assertSame($this->contexts['1605'], $course->get_testinfo_contextid(), 'The context of the latest attempt.');
        $out = $course->render_abilityprofilechart()['chart'];
        $this->assertStringContainsString('every test of the scale in this course (2)', $out);
        $this->assertStringContainsString(
            'played in 3 CAT contexts (calibrations). The test information refers to “Context 1605”',
            $out
        );

        $one = new catquizstatistics((int) $this->course->id, $this->quizid, $this->root);
        $this->assertSame(2, $one->get_chart_data(catquizstatistics::CHART_ABILITYPROFILE)['population']['persons']);
        $out = $one->render_abilityprofilechart()['chart'];
        $this->assertStringContainsString('the test “Test 20”', $out);
        $this->assertStringContainsString('played in 2 CAT contexts', $out);
    }

    /**
     * R18: the test information is for those who may see other people's results - for the others it is not even
     * computed.
     */
    public function test_a_student_gets_bars_without_pool_information(): void {
        $this->attempt('p', $this->now - DAYSECS, [$this->root => [1.1, 1, 1, []]]);
        $this->setUser($this->user('p'));
        $out = (new catquizstatistics((int) $this->course->id, null, $this->root))->render_abilityprofilechart();
        $this->assertStringContainsString('n = 1 persons', $out['chart']);
        $this->assertStringNotContainsString('Test information', $out['chart']);
        $this->assertStringNotContainsString('test information', $out['chart']);
    }

    /**
     * R20: the ability profile reads the same number of times for 10 and for 40 attempts - no query per attempt.
     */
    public function test_no_query_per_attempt(): void {
        global $DB;
        $reads = function (int $persons) use ($DB): int {
            for ($i = 0; $i < $persons; $i++) {
                $this->lowestsub("q{$persons}_{$i}", $this->now - DAYSECS + $i, 'S1', true);
            }
            $statistics = new catquizstatistics((int) $this->course->id, null, $this->root);
            $before = $DB->perf_get_reads();
            $statistics->get_chart_data(catquizstatistics::CHART_ABILITYPROFILE);
            return $DB->perf_get_reads() - $before;
        };
        $ten = $reads(10);
        $forty = $reads(30);
        $this->assertSame($ten, $forty);
    }

    /**
     * The diagnosis names the attempt 180 and counts the bars as the statistics do.
     */
    public function test_the_diagnosis_names_the_case(): void {
        $this->test20();
        $report = statistics_diagnosis::report($this->root, (int) $this->course->id);
        $this->assertSame($this->now - 400 * DAYSECS, $report['results_stored_since']['time']);
        $this->assertSame(
            1,
            $report['attempts_by_value']['after results were stored, none stored, carries a value (not counted since 28.10.2026)']
        );
        $this->assertSame(29, $report['attempts_by_value']['stored: not a valid measurement']);
        $this->assertTrue($report['bars']['identical']);
        $this->assertSame(0, $report['bars']['n_persons']);
    }

    /**
     * Comparison with catquiz::get_sql_for_attempts_per_person(): the colour of the person 180 has no value.
     */
    public function test_attempts_per_person_takes_no_value_of_the_attempt_180(): void {
        global $DB;
        $this->test20();
        [$sql, $params] = catquiz::get_sql_for_attempts_per_person(
            $this->contexts['1605'],
            $this->root,
            (int) $this->course->id
        );
        $rows = $DB->get_records_sql($sql, $params);
        $this->assertArrayHasKey($this->users['a180'], $rows);
        $this->assertNull($rows[$this->users['a180']]->ability);
    }
}
