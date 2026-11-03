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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * The chart "attempts per period" across a change of daylight saving time (issue #151).
 *
 * The labels are the keys of the periods, the bars count the attempts by the label of their end time. With the
 * person in another time zone than the server, a day was missing from the keys across the change: its attempts
 * opened a key of their own behind the others - a series longer than the labels, the bars under the wrong days.
 * When the missing day was the last one, the page never came back.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\output\catquizstatistics::render_attempts_per_timerange_chart
 */
final class attempts_per_timerange_dst_test extends advanced_testcase {
    /** @var int CAT context. */
    private int $contextid;

    /** @var int Root scale. */
    private int $root;

    /** @var \stdClass */
    private \stdClass $course;

    /** @var int Running number of the host attempts. */
    private int $hostattempt = 151000;

    /**
     * Context, root scale, course, test.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $now = time();
        $this->contextid = (int) $DB->insert_record('local_catquiz_catcontext', (object) [
            'name' => 'Issue 151', 'description' => '', 'descriptionformat' => FORMAT_HTML,
            'starttimestamp' => 0, 'endtimestamp' => $now + 10000000, 'timecreated' => $now, 'timemodified' => $now,
            'usermodified' => 0,
        ]);
        $this->root = (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'parentid' => 0, 'name' => 'Global 151', 'contextid' => $this->contextid,
            'minscalevalue' => -5, 'maxscalevalue' => 5, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $this->course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->get_plugin_generator('mod_adaptivequiz')->create_instance([
            'course' => $this->course->id, 'highestlevel' => 10, 'lowestlevel' => 1, 'standarderror' => 14,
            'attemptfeedbackeditor' => ['text' => '', 'format' => FORMAT_MOODLE],
        ]);
        $settings = [
            'catquiz_catscales' => $this->root, 'catquiz_selectteststrategy' => LOCAL_CATQUIZ_STRATEGY_LOWESTSUB,
            'numberoffeedbackoptionsselect' => 2, 'name' => 'Test 151',
            "feedback_scaleid_limit_lower_{$this->root}_1" => -5, "feedback_scaleid_limit_upper_{$this->root}_1" => 0,
            "feedback_scaleid_limit_lower_{$this->root}_2" => 0, "feedback_scaleid_limit_upper_{$this->root}_2" => 5,
            "wb_colourpicker_{$this->root}_1" => '3', "wb_colourpicker_{$this->root}_2" => '6',
        ];
        $DB->insert_record('local_catquiz_tests', (object) [
            'componentid' => $quiz->id, 'component' => 'mod_adaptivequiz', 'catscaleid' => $this->root,
            'courseid' => $this->course->id, 'name' => 'Test 151', 'status' => 1, 'json' => json_encode($settings),
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        \cache_helper::purge_all();
    }

    /**
     * A completed attempt with a valid result, ending at the given time.
     *
     * @param int $endtime
     * @param float $ability
     * @return void
     */
    private function attempt(int $endtime, float $ability): void {
        global $DB;
        $userid = (int) $this->getDataGenerator()->create_and_enrol($this->course, 'student')->id;
        $json = ['personabilities' => [$this->root => $ability], 'personabilities_abilities' => [
            $this->root => ['value' => $ability, 'primary' => true, 'toreport' => true],
        ]];
        $attemptid = (int) $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => $userid, 'scaleid' => $this->root, 'contextid' => $this->contextid,
            'courseid' => $this->course->id, 'attemptid' => ++$this->hostattempt, 'component' => 'mod_adaptivequiz',
            'instanceid' => 1, 'teststrategy' => LOCAL_CATQUIZ_STRATEGY_LOWESTSUB, 'status' => LOCAL_CATQUIZ_ATTEMPT_OK,
            'starttime' => $endtime - 600, 'endtime' => $endtime, 'personability_after_attempt' => $ability,
            'json' => json_encode($json), 'debug_info' => '', 'timecreated' => $endtime, 'timemodified' => $endtime,
        ]);
        $DB->insert_record('local_catquiz_personparams', (object) [
            'userid' => $userid, 'catscaleid' => $this->root, 'contextid' => $this->contextid,
            'attemptid' => $attemptid, 'ability' => $ability, 'isvalid' => 1, 'resultsource' => 'current',
            'isprimary' => 1, 'status' => 0, 'timecreated' => $endtime, 'timemodified' => $endtime,
        ]);
        eligible_population::reset();
    }

    /**
     * The charts rendered since the last call, as the browser gets them.
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
     * Server and person in different zones, across both changes; a third zone.
     *
     * @return array [server zone, person zone, end times (UTC), end of the range (UTC), counts per day of the person]
     */
    public static function zone_provider(): array {
        return [
            'autumn: server Berlin, person UTC' => [
                'Europe/Berlin', 'UTC',
                ['2026-10-24 23:45', '2026-10-25 10:00', '2026-10-25 22:00', '2026-10-26 01:30', '2026-10-27 09:00'],
                '2026-10-27 12:00', [1, 2, 1, 1],
            ],
            'spring: server UTC, person Berlin' => [
                'UTC', 'Europe/Berlin',
                ['2026-03-28 22:45', '2026-03-29 09:00', '2026-03-30 07:00', '2026-03-31 06:00'],
                '2026-03-31 08:00', [1, 1, 1, 1],
            ],
            'spring, US: server Berlin, person New York' => [
                'Europe/Berlin', 'America/New_York',
                ['2026-03-08 03:30', '2026-03-08 16:00', '2026-03-09 14:00', '2026-03-10 14:00'],
                '2026-03-10 18:00', [1, 1, 1, 1],
            ],
        ];
    }

    /**
     * One bar per day of the person, every series as long as the labels, every attempt counted once under its day.
     *
     * @param string $server
     * @param string $person
     * @param string[] $endtimes
     * @param string $end
     * @param int[] $perday
     * @dataProvider zone_provider
     */
    public function test_each_attempt_is_counted_once_under_its_day(
        string $server,
        string $person,
        array $endtimes,
        string $end,
        array $perday
    ): void {
        $this->setTimezone($server, $server);
        $utc = new \DateTimeZone('UTC');
        foreach ($endtimes as $i => $endtime) {
            $this->attempt((new \DateTimeImmutable($endtime, $utc))->getTimestamp(), $i % 2 ? -1.0 : 1.0);
        }
        $this->setUser($this->getDataGenerator()->create_user(['timezone' => $person]));
        $this->charts_sent();

        $statistics = new catquizstatistics(
            (int) $this->course->id,
            null,
            $this->root,
            (new \DateTimeImmutable($end, $utc))->getTimestamp()
        );
        $statistics->render_attempts_per_timerange_chart();
        $charts = $this->charts_sent();

        $this->assertCount(1, $charts);
        $chart = $charts[0];
        $this->assertCount(count($perday), $chart['labels'], 'One bar per day of the person.');
        $sums = array_fill(0, count($chart['labels']), 0);
        foreach ($chart['series'] as $series) {
            $this->assertCount(count($chart['labels']), $series['values'], "Series '{$series['label']}' fits the labels.");
            foreach ($series['values'] as $i => $value) {
                $sums[$i] += (int) $value;
            }
        }
        $this->assertSame($perday, $sums, 'Every attempt counted once, under its day.');
        $this->assertSame(count($endtimes), array_sum($sums));
    }
}
