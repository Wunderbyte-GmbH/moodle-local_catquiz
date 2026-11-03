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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * One population of attempts for every chart (issue #88): a fixture matrix checked chart by chart.
 *
 * The persons of the fixture, inside the period [T0, T1] unless said otherwise:
 * - P1: a valid first attempt, then an invalid repetition.
 * - P2: an open attempt (no end time, status "OK" - as every running attempt is saved) and an aborted one
 *   (completed without an answer, no result).
 * - P3: one invalid, saturated result.
 * - P4: attempts ending exactly at T0, exactly at T1, and one second after T1.
 * - P5: enrolled, no attempt.
 * Two scales: the root scale R (global) and its subscale S, each with its own stored results.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\local\statistics\attempt_population
 * @covers \local_catquiz\output\catquizstatistics
 * @covers \local_catquiz\catquiz
 */
final class attempt_population_test extends advanced_testcase {
    /** @var int Start of the period. */
    private int $t0;

    /** @var int End of the period. */
    private int $t1;

    /** @var int Root scale. */
    private int $root;

    /** @var int Subscale. */
    private int $sub;

    /** @var int CAT context. */
    private int $contextid;

    /** @var \stdClass Course. */
    private \stdClass $course;

    /** @var int adaptivequiz.id */
    private int $quizid;

    /** @var array Users by name P1..P5. */
    private array $users = [];

    /** @var int Running number of the host attempts. */
    private int $hostattempt = 880000;

    /**
     * Context, root scale, subscale, course, test, five enrolled people.
     *
     * @param int $numberofranges Feedback ranges of the test.
     * @return void
     */
    private function setup_scope(int $numberofranges = 2): void {
        global $DB;

        $this->t1 = make_timestamp(2026, 3, 10, 12, 0, 0);
        $this->t0 = $this->t1 - 5 * DAYSECS;
        $now = time();
        $this->contextid = (int) $DB->insert_record('local_catquiz_catcontext', (object) [
            'name' => 'Issue 88', 'description' => '', 'descriptionformat' => FORMAT_HTML,
            'starttimestamp' => 0, 'endtimestamp' => $now + 10000, 'timecreated' => $now, 'timemodified' => $now,
            'usermodified' => 0,
        ]);
        $this->root = (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'parentid' => 0, 'name' => 'Global', 'contextid' => $this->contextid,
            'minscalevalue' => -5, 'maxscalevalue' => 5, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $this->sub = (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'parentid' => $this->root, 'name' => 'Sub', 'contextid' => $this->contextid,
            'minscalevalue' => -5, 'maxscalevalue' => 5, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        \cache_helper::purge_all();

        $this->course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->get_plugin_generator('mod_adaptivequiz')->create_instance([
            'course' => $this->course->id, 'highestlevel' => 10, 'lowestlevel' => 1, 'standarderror' => 14,
            'attemptfeedbackeditor' => ['text' => '', 'format' => FORMAT_MOODLE],
        ]);
        $this->quizid = (int) $quiz->id;
        $this->add_test($this->quizid, $numberofranges);
        foreach (['P1', 'P2', 'P3', 'P4', 'P5'] as $name) {
            $this->users[$name] = (int) $this->getDataGenerator()->create_and_enrol($this->course, 'student')->id;
        }
        eligible_population::reset();
        // Results are stored since after the fixtures: an attempt without stored rows is legacy (issue #150).
        set_config(\local_catquiz\local\result\result_provenance::CONFIG, $this->t1 + DAYSECS, 'local_catquiz');
    }

    /**
     * The CAT settings of a test: ranges [-5, 0) and [0, 5] on both scales (or one range).
     *
     * @param int $quizid
     * @param int $numberofranges
     * @return void
     */
    private function add_test(int $quizid, int $numberofranges): void {
        global $DB;
        $settings = [
            'catquiz_catscales' => $this->root,
            'catquiz_selectteststrategy' => LOCAL_CATQUIZ_STRATEGY_LOWESTSUB,
            'numberoffeedbackoptionsselect' => $numberofranges,
            'name' => 'Test',
        ];
        foreach ([$this->root, $this->sub] as $scaleid) {
            $bounds = $numberofranges === 1 ? [[-5, 5]] : [[-5, 0], [0, 5]];
            foreach ($bounds as $i => [$lower, $upper]) {
                $j = $i + 1;
                $settings["feedback_scaleid_limit_lower_{$scaleid}_{$j}"] = $lower;
                $settings["feedback_scaleid_limit_upper_{$scaleid}_{$j}"] = $upper;
                $settings["wb_colourpicker_{$scaleid}_{$j}"] = $j === 1 ? '3' : '6';
                $settings["feedbacklegend_scaleid_{$scaleid}_{$j}"] = "Range $j";
            }
        }
        $DB->insert_record('local_catquiz_tests', (object) [
            'componentid' => $quizid, 'component' => 'mod_adaptivequiz', 'catscaleid' => $this->root,
            'courseid' => $this->course->id, 'name' => 'Test', 'status' => 1, 'json' => json_encode($settings),
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * Records an attempt the way the finalisation leaves it.
     *
     * @param string $person P1..P5
     * @param int|null $endtime Null for a running attempt.
     * @param array|null $results [scaleid => [ability, valid]]; null for an attempt without results.
     * @param array $options status, starttime, quizid, courseid.
     * @return int local_catquiz_attempts.id
     */
    private function attempt(string $person, ?int $endtime, ?array $results, array $options = []): int {
        global $DB;
        $userid = $this->users[$person];
        $abilities = [];
        $json = ['personabilities' => [], 'personabilities_abilities' => []];
        foreach ($results ?? [] as $scaleid => [$ability]) {
            $json['personabilities'][$scaleid] = $ability;
            $json['personabilities_abilities'][$scaleid] = [
                'value' => $ability, 'primary' => $scaleid === $this->root, 'toreport' => true,
            ];
            $abilities[$scaleid] = $ability;
        }
        $id = (int) $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => $userid, 'scaleid' => $this->root, 'contextid' => $this->contextid,
            'courseid' => $options['courseid'] ?? $this->course->id, 'attemptid' => ++$this->hostattempt,
            'component' => 'mod_adaptivequiz', 'instanceid' => $options['quizid'] ?? $this->quizid,
            'teststrategy' => LOCAL_CATQUIZ_STRATEGY_LOWESTSUB,
            'status' => $options['status'] ?? LOCAL_CATQUIZ_ATTEMPT_OK,
            'starttime' => $options['starttime'] ?? (($endtime ?? $this->t1) - 600), 'endtime' => $endtime,
            'personability_after_attempt' => $abilities[$this->root] ?? null,
            'json' => json_encode($json), 'debug_info' => '',
            'timecreated' => $endtime ?? $this->t1, 'timemodified' => $options['timemodified'] ?? ($endtime ?? $this->t1),
        ]);
        if ($endtime !== null) {
            foreach ($results ?? [] as $scaleid => [$ability, $valid]) {
                $DB->insert_record('local_catquiz_personparams', (object) [
                    'userid' => $userid, 'catscaleid' => $scaleid, 'contextid' => $this->contextid, 'attemptid' => $id,
                    'ability' => $ability, 'isvalid' => $valid ? 1 : 0, 'resultsource' => 'current',
                    'isprimary' => $scaleid === $this->root ? 1 : 0, 'status' => 0,
                    'timecreated' => $endtime, 'timemodified' => $endtime,
                ]);
            }
        }
        return $id;
    }

    /**
     * The fixture matrix of the class comment.
     *
     * @return array local_catquiz_attempts.id by label.
     */
    private function seed(): array {
        $r = $this->root;
        $s = $this->sub;
        $day = DAYSECS;
        return [
            'P1a' => $this->attempt('P1', $this->t0 + $day, [$r => [1.0, true], $s => [0.5, true]]),
            'P1b' => $this->attempt('P1', $this->t0 + 2 * $day, [$r => [-2.0, false], $s => [-2.5, false]]),
            'P2open' => $this->attempt('P2', null, [$r => [3.0, true], $s => [3.0, true]]),
            'P2aborted' => $this->attempt('P2', $this->t0 + 2 * $day, null, ['status' => LOCAL_CATQUIZ_ATTEMPT_ABORTED]),
            'P3' => $this->attempt('P3', $this->t0 + 3 * $day, [$r => [50.0, false], $s => [50.0, false]]),
            'P4start' => $this->attempt('P4', $this->t0, [$r => [0.2, true], $s => [0.1, true]]),
            'P4end' => $this->attempt('P4', $this->t1, [$r => [0.3, true], $s => [0.4, true]]),
            'P4after' => $this->attempt('P4', $this->t1 + 1, [$r => [-3.0, true], $s => [-3.0, true]]),
        ];
    }

    /**
     * Scales and selections, with the population every chart has to show.
     *
     * @return array
     */
    public static function population_provider(): array {
        return [
            // Every completed attempt of [T0, T1]: P1 twice, P2 aborted, P3, P4 on both bounds.
            'root, all' => ['root', attempt_population::ALL,
                ['P4start', 'P1a', 'P1b', 'P2aborted', 'P3', 'P4end'], ['P1' => 1.0, 'P4' => 0.3]],
            'subscale, all' => ['sub', attempt_population::ALL,
                ['P4start', 'P1a', 'P1b', 'P2aborted', 'P3', 'P4end'], ['P1' => 0.5, 'P4' => 0.4]],
            // The first completed attempt of each person: never the open one.
            'root, first' => ['root', attempt_population::FIRST,
                ['P4start', 'P1a', 'P2aborted', 'P3'], ['P1' => 1.0, 'P4' => 0.2]],
            'subscale, first' => ['sub', attempt_population::FIRST,
                ['P4start', 'P1a', 'P2aborted', 'P3'], ['P1' => 0.5, 'P4' => 0.1]],
            // The last over the whole history: P4's lies after the period, P1's is invalid.
            'root, last' => ['root', attempt_population::LAST, ['P1b', 'P2aborted', 'P3'], []],
        ];
    }

    /**
     * Every chart is built from the same attempts and the same valid results (issue #88).
     *
     * @param string $scale 'root' or 'sub'.
     * @param string $selection
     * @param string[] $expectedattempts Labels of the attempts of the population, in end time order.
     * @param array $expectedvalues The valid value per person.
     * @dataProvider population_provider
     */
    public function test_every_chart_uses_the_same_population(
        string $scale,
        string $selection,
        array $expectedattempts,
        array $expectedvalues
    ): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->setup_scope();
        $ids = $this->seed();
        $scaleid = $scale === 'root' ? $this->root : $this->sub;

        $statistics = new catquizstatistics((int) $this->course->id, null, $scaleid, $this->t1, $this->t0, $selection);
        $attempts = $statistics->get_population()->attempts();
        $this->assertSame(
            array_map(fn($label) => $ids[$label], $expectedattempts),
            array_map(fn($a) => (int) $a->id, $attempts),
            'The population: completed, inside [T0, T1] with both bounds, selected as asked.'
        );
        $values = [];
        foreach (attempt_population::value_per_person($attempts) as $userid => $value) {
            $values[array_search($userid, $this->users, true)] = round($value, 4);
        }
        ksort($values);
        $this->assertSame($expectedvalues, $values, 'One valid value per person, for the scale asked for.');

        $n = count($expectedattempts);
        $persons = count(array_unique(array_map(fn($label) => substr($label, 0, 2), $expectedattempts)));
        $withvalue = count($expectedvalues);

        // Attempts per period: every attempt counted once, the ones without a valid result as "no result".
        $timerange = $statistics->get_chart_data(catquizstatistics::CHART_ATTEMPTSPERTIMERANGE);
        $this->assertSame($n, $timerange['population']['attempts']);
        $this->assertSame($n, array_sum(array_map('array_sum', $timerange['series'])));
        $this->assertSame($n - count(array_filter(
            $attempts,
            fn($a) => $a->value !== null
        )), array_sum($timerange['series'][0]));

        // Attempts per person: the same attempts, the same people, coloured by the same values.
        $perperson = $statistics->get_chart_data(catquizstatistics::CHART_ATTEMPTSPERPERSON);
        $this->assertSame($n, $perperson['population']['attempts']);
        $this->assertSame($persons, $perperson['population']['persons']);
        $this->assertSame($withvalue, $perperson['population']['personswithvalue']);
        $this->assertSame(5 - $persons, $perperson['population']['noattempt'], 'The eligible people without an attempt.');
        $this->assertSame(0, array_sum($perperson['series'][1]), 'No in-scope valid value lies below 0.');

        // Ability profile: one value per person with a valid result.
        $profile = $statistics->get_chart_data(catquizstatistics::CHART_ABILITYPROFILE);
        $this->assertSame($withvalue, $profile['population']['persons']);
        $this->assertSame($withvalue, array_sum($profile['series']['counter']));

        // Detected scales: the reported scale is the root scale here, so the same people.
        $detected = $statistics->get_chart_data(catquizstatistics::CHART_DETECTEDSCALES);
        $this->assertSame($withvalue, $detected ? $detected['population']['persons'] : 0);
    }

    /**
     * Open attempts are no results anywhere: not in the list of attempts, not in the feedback list.
     */
    public function test_open_attempts_are_not_results(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->setup_scope();
        $ids = $this->seed();

        $listed = [];
        $recordset = catquiz::get_attempts($this->users['P2'], $this->root, null, null, null, null, null, false);
        foreach ($recordset as $record) {
            $listed[] = (int) $record->id;
        }
        $recordset->close();
        $this->assertSame([$ids['P2aborted']], $listed, 'A running attempt is saved with status OK; it is not listed.');

        $feedback = catquiz::return_data_from_attemptstable(0, 0, 0, 0, $this->users['P2']);
        $this->assertSame([$ids['P2aborted']], array_map('intval', array_keys($feedback)), 'No phantom feedback.');
    }

    /**
     * The newest result is the one that ended last, not the row saved last (issue #88).
     */
    public function test_feedback_list_is_ordered_by_end_time(): void {
        $this->resetAfterTest();
        $this->setup_scope();
        $early = $this->attempt('P1', $this->t0, [$this->root => [1.0, true]], ['timemodified' => $this->t1 + 100]);
        $late = $this->attempt('P1', $this->t1, [$this->root => [2.0, true]], ['timemodified' => $this->t0]);

        $records = catquiz::return_data_from_attemptstable(0, 0, 0, 0, $this->users['P1']);
        $this->assertSame([$late, $early], array_map('intval', array_keys($records)));
        $newest = catquiz::return_data_from_attemptstable(1, 0, 0, 0, $this->users['P1']);
        $this->assertSame([$late], array_map('intval', array_keys($newest)));
    }

    /**
     * An attempt belongs to the period of its end, not its start; empty periods count zero.
     */
    public function test_periods_by_end_time_and_empty_periods(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->setup_scope();
        $first = $this->attempt('P1', $this->t0, [$this->root => [1.0, true]]);
        // Begun on the first day, finished two days later.
        $this->attempt('P2', $this->t0 + 2 * DAYSECS, [$this->root => [1.0, true]], ['starttime' => $this->t0 - 60]);
        $this->assertGreaterThan(0, $first);

        $statistics = new catquizstatistics((int) $this->course->id, null, $this->root, $this->t0 + 3 * DAYSECS, $this->t0);
        $data = $statistics->get_chart_data(catquizstatistics::CHART_ATTEMPTSPERTIMERANGE);
        $total = array_map(null, ...array_values($data['series']));
        $perday = array_combine($data['labels'], array_map(fn($column) => array_sum((array) $column), $total));

        $label = fn(int $time) => \local_catquiz\teststrategy\feedback_helper::return_datestring_label(
            LOCAL_CATQUIZ_TIMERANGE_DAY,
            $time
        );
        $this->assertSame(1, $perday[$label($this->t0)], 'The first day: only the attempt that ended on it.');
        $this->assertSame(0, $perday[$label($this->t0 + DAYSECS)], 'An empty day is shown with zero.');
        $this->assertSame(1, $perday[$label($this->t0 + 2 * DAYSECS)], 'Counted on the day it ended.');
        $this->assertSame(0, $perday[$label($this->t0 + 3 * DAYSECS)]);
    }

    /**
     * Without compatible quiz settings, a result of exactly 0.0 is a result, not "no result" (issue #88).
     */
    public function test_zero_is_a_result(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->setup_scope();
        // A second test with another number of ranges makes the settings incompatible.
        $other = $this->getDataGenerator()->get_plugin_generator('mod_adaptivequiz')->create_instance([
            'course' => $this->course->id, 'highestlevel' => 10, 'lowestlevel' => 1, 'standarderror' => 14,
            'attemptfeedbackeditor' => ['text' => '', 'format' => FORMAT_MOODLE],
        ]);
        $this->add_test((int) $other->id, 1);
        $this->attempt('P1', $this->t0, [$this->root => [0.0, true]]);
        $this->attempt('P2', $this->t0 + DAYSECS, null);
        $this->assertSame(2, $DB->count_records('local_catquiz_tests', ['courseid' => $this->course->id]));

        $statistics = new catquizstatistics((int) $this->course->id, null, $this->root, $this->t1, $this->t0);
        $data = $statistics->get_chart_data(catquizstatistics::CHART_ATTEMPTSPERTIMERANGE);
        $this->assertSame(1, array_sum($data['series'][1]), 'The result 0.0 has a value.');
        $this->assertSame(1, array_sum($data['series'][0]), 'The attempt without result has none.');
    }

    /**
     * The learning progress needs three people, not three attempts (issue #88).
     */
    public function test_learning_progress_counts_people(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->setup_scope();
        foreach (['P1', 'P2'] as $person) {
            foreach ([1, 2, 3] as $day) {
                $this->attempt($person, $this->t0 + $day * DAYSECS, [$this->root => [(float) $day, true]]);
            }
        }
        $statistics = new catquizstatistics((int) $this->course->id, null, $this->root, $this->t1, $this->t0);
        $this->assertSame([], $statistics->get_chart_data(catquizstatistics::CHART_LEARNINGPROGRESS), 'Two people.');

        $this->attempt('P3', $this->t0 + DAYSECS, [$this->root => [1.0, true]]);
        $statistics = new catquizstatistics((int) $this->course->id, null, $this->root, $this->t1, $this->t0);
        $data = $statistics->get_chart_data(catquizstatistics::CHART_LEARNINGPROGRESS);
        $this->assertSame(3, $data['population']['persons']);
    }

    /**
     * The colour of a person's bar comes from their latest valid result in the scope (issue #88).
     *
     * It came from the latest attempt on the scale anywhere and at any time - outside the period, in
     * another course, still running (a NULL end time sorts first on PostgreSQL).
     */
    public function test_attempts_per_person_ability_stays_in_scope(): void {
        $this->resetAfterTest();
        $this->setup_scope();
        $this->attempt('P1', $this->t0, [$this->root => [1.0, true]]);
        // Later, but outside the period, in another course, or not finished: all of them below 0.
        $this->attempt('P1', $this->t1 + 1, [$this->root => [-3.0, true]]);
        $this->attempt('P1', $this->t0 + DAYSECS, [$this->root => [-3.0, true]], ['courseid' => 999999]);
        $this->attempt('P1', null, [$this->root => [-4.0, true]]);

        [$sql, $params] = catquiz::get_sql_for_attempts_per_person(
            $this->contextid,
            $this->root,
            (int) $this->course->id,
            null,
            null,
            $this->t0,
            $this->t1
        );
        $rows = $GLOBALS['DB']->get_records_sql($sql, $params);
        $row = $rows[$this->users['P1']];
        $this->assertSame(1, (int) $row->attempts);
        $this->assertEqualsWithDelta(1.0, (float) $row->ability, 0.0001);
    }

    /**
     * A subscale selects the attempts of its root scale and reports its own values (issue #88).
     */
    public function test_subscale_selects_through_its_root(): void {
        $this->resetAfterTest();
        $this->setup_scope();
        $this->attempt('P1', $this->t0, [$this->root => [1.0, true], $this->sub => [-1.5, true]]);

        $population = new attempt_population($this->sub, (int) $this->course->id);
        $this->assertSame($this->root, $population->get_rootscaleid());
        [$attempt] = $population->attempts();
        $this->assertEqualsWithDelta(-1.5, $attempt->value, 0.0001);

        [$sql, $params] = catquiz::get_sql_for_attempts_per_person($this->contextid, $this->sub, (int) $this->course->id);
        $row = $GLOBALS['DB']->get_records_sql($sql, $params)[$this->users['P1']];
        $this->assertSame(1, (int) $row->attempts, 'local_catquiz_attempts holds the root scale.');
        $this->assertEqualsWithDelta(-1.5, (float) $row->ability, 0.0001, 'The value of the subscale.');
    }

    /**
     * A legacy attempt (no stored results) counts with its value, as never judged.
     */
    public function test_legacy_attempt_counts_with_its_value(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setup_scope();
        $id = $this->attempt('P1', $this->t0, [$this->root => [1.25, true], $this->sub => [0.75, true]]);
        $DB->delete_records('local_catquiz_personparams', ['attemptid' => $id]);

        $root = (new attempt_population($this->root, (int) $this->course->id))->attempts();
        $sub = (new attempt_population($this->sub, (int) $this->course->id))->attempts();
        $this->assertEqualsWithDelta(1.25, $root[0]->value, 0.0001);
        $this->assertEqualsWithDelta(0.75, $sub[0]->value, 0.0001, 'PHP reads the subscale from the JSON.');

        // The named difference: in SQL a legacy subscale has no value.
        [$sql, $params] = catquiz::get_sql_for_attempts_per_person($this->contextid, $this->sub, (int) $this->course->id);
        $row = $DB->get_records_sql($sql, $params)[$this->users['P1']];
        $this->assertNull($row->ability);
    }

    /**
     * The shortcode argument attempts: first, last or all; nothing else (issue #88).
     */
    public function test_shortcode_attempt_selection(): void {
        $method = new \ReflectionMethod(\local_catquiz\shortcodes::class, 'attempt_selection');
        $method->setAccessible(true);
        $this->assertSame(attempt_population::ALL, $method->invoke(null, []));
        $this->assertSame(attempt_population::FIRST, $method->invoke(null, ['attempts' => 'First']));
        $this->assertSame(attempt_population::LAST, $method->invoke(null, ['attempts' => 'last']));
        $this->expectException(\Exception::class);
        $method->invoke(null, ['attempts' => 'best']);
    }

    /**
     * Course and scope arguments of the shortcode compare what they say (issue #88: operator precedence).
     */
    public function test_shortcode_arguments(): void {
        global $COURSE;
        $this->resetAfterTest();
        $this->setup_scope();
        $method = new \ReflectionMethod(\local_catquiz\shortcodes::class, 'populate_arguments');
        $method->setAccessible(true);
        $cm = get_coursemodule_from_instance('adaptivequiz', $this->quizid);

        // The matching course is no conflict.
        $result = $method->invoke(null, ['testid' => $cm->id, 'course' => $this->course->id]);
        $this->assertSame((int) $this->course->id, (int) $result['course']);
        try {
            $method->invoke(null, ['testid' => $cm->id, 'course' => $this->course->id + 1000]);
            $this->fail('Another course is a conflict.');
        } catch (\Exception $e) {
            $this->assertSame(get_string('catquizstatistics_scale_course_conflict', 'local_catquiz'), $e->getMessage());
        }

        // Scope "all" leaves the course out; without it, the course stays.
        $COURSE = $this->course;
        $this->assertNull($method->invoke(null, ['globalscale' => $this->root, 'scope' => 'all'])['course']);
        $this->assertSame(
            (int) $this->course->id,
            (int) $method->invoke(null, ['globalscale' => $this->root])['course']
        );
    }

    /**
     * Every chart renders from the fixture, lazily or not.
     */
    public function test_every_chart_renders(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->setup_scope();
        $this->seed();
        $statistics = new catquizstatistics((int) $this->course->id, null, $this->root, $this->t1, $this->t0);
        foreach (array_keys(catquizstatistics::CHARTS) as $chart) {
            $data = \local_catquiz\shortcodes::render_statistics_chart($statistics, $chart);
            $this->assertSame($chart, $data['chartname']);
            $this->assertNotSame(get_string('statisticschartloadfailed', 'local_catquiz'), $data['chart'], $chart);
        }
    }
}
