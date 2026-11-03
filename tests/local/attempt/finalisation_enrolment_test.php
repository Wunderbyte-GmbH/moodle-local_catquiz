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

namespace local_catquiz\local\attempt;

use advanced_testcase;
use local_catquiz\event\usertocourse_enroled;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/tests/fixtures/measured_progress.php');
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * Enrolment happens when the attempt is finalised, on every way a test ends (issue #129).
 *
 * It used to happen when the result page was built. A test that ended by the time limit, by a
 * teacher closing it or after too long a break, and whose page was never opened, enrolled nobody.
 * Finalisation here runs as the administrator, as in the time-limit task; the owner of the attempt
 * must be enrolled, in the course routed to.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\local\attempt\attempt_enrolment
 */
final class finalisation_enrolment_test extends advanced_testcase {
    /**
     * Files a completed attempt whose root scale routes into a target course.
     *
     * @param array $root The entry of the root scale in the abilities.
     * @return array{0: int, 1: \stdClass, 2: \stdClass} Attempt id, the owner, the target course.
     */
    private function attempt_routing_into_a_course(array $root): array {
        global $DB;

        $home = $this->getDataGenerator()->create_course();
        $target = $this->getDataGenerator()->create_course(['fullname' => 'Target course']);
        $owner = $this->getDataGenerator()->create_and_enrol($home, 'student');
        $module = $this->getDataGenerator()->create_module('adaptivequiz', [
            'course' => $home->id,
            'attemptfeedbackeditor' => ['text' => '', 'format' => FORMAT_MOODLE],
        ]);
        $now = time();
        $attemptid = (int) $DB->insert_record('adaptivequiz_attempt', (object) [
            'instance' => $module->id, 'userid' => $owner->id, 'uniqueid' => 66000 + $module->id,
            'attemptstate' => 'complete', 'attemptstopcriteria' => '', 'questionsattempted' => 3,
            'difficultysum' => 0, 'standarderror' => 0.3, 'measure' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => $owner->id, 'scaleid' => 5, 'contextid' => 9, 'courseid' => $home->id, 'instanceid' => $module->id,
            'attemptid' => $attemptid, 'component' => 'mod_adaptivequiz', 'status' => 0, 'teststrategy' => 1,
            'json' => json_encode(['personabilities_abilities' => [5 => $root], 'se' => [5 => 0.3]]),
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        $this->setUser($owner);
        local_catquiz_measured_progress($attemptid, [5 => 3], 9, [
            'name' => 'Test',
            'numberoffeedbackoptionsselect' => 1,
            'feedback_scaleid_limit_lower_5_1' => -5,
            'feedback_scaleid_limit_upper_5_1' => 5,
            'catquiz_courses_5_1' => [0, $target->id],
            'enrolment_message_checkbox_5_1' => 1,
        ]);

        return [$attemptid, $owner, $target];
    }

    /**
     * A valid result enrols its owner at finalisation, once, and the message is kept for the page.
     */
    public function test_a_valid_result_enrols_at_finalisation(): void {
        $this->resetAfterTest();
        [$attemptid, $owner, $target] = $this->attempt_routing_into_a_course(
            ['value' => 0.4, 'toreport' => true, 'primary' => true]
        );

        // Someone else finalises: the administrator, as the time-limit task does.
        $this->setAdminUser();
        $this->redirectMessages();
        $sink = $this->redirectEvents();
        $this->assertTrue(attempt_finalizer::finalize($attemptid, time(), 'closed by time limit'));
        $this->assertFalse(attempt_finalizer::finalize($attemptid, time(), 'closed by time limit'));
        $enrolments = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof usertocourse_enroled));
        $sink->close();

        $this->assertTrue(is_enrolled(\context_course::instance($target->id), $owner->id), 'The owner was not enrolled.');
        $this->assertFalse(is_enrolled(\context_course::instance($target->id), get_admin()->id), 'The wrong person was enrolled.');
        $this->assertCount(1, $enrolments, 'The owner must be enrolled exactly once.');
        $this->assertStringContainsString('Target course', attempt_enrolment::stored_message($attemptid));
    }

    /**
     * An invalid result enrols nobody.
     */
    public function test_an_invalid_result_enrols_nobody(): void {
        $this->resetAfterTest();
        [$attemptid, $owner, $target] = $this->attempt_routing_into_a_course(
            ['value' => 0.4, 'toreport' => true, 'primary' => true, 'excluded' => true,
                'error' => ['se' => ['semaxdefined' => 0.2]]]
        );

        $this->setAdminUser();
        $this->redirectMessages();
        attempt_finalizer::finalize($attemptid, time(), 'reason');

        $this->assertFalse(is_enrolled(\context_course::instance($target->id), $owner->id));
        $this->assertSame('', attempt_enrolment::stored_message($attemptid));
    }

    /**
     * Routing into many scales costs a fixed number of queries per scale, not a query per scale and attempt or
     * worse (issue #131, no N+1): the queries of routing 2, 4 and 8 scales grow by the same step. Measured on 5.3
     * PostgreSQL 28.10.2026: about 19 queries per routed scale, nearly all in the enrolment and group API of Moodle.
     */
    public function test_routing_more_scales_adds_a_fixed_cost_per_scale(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->redirectMessages();

        $queries = [];
        // The first routing warms the caches of the site (configuration, table structure): measured, not compared.
        foreach ([1, 2, 4, 8] as $k) {
            $home = $this->getDataGenerator()->create_course();
            $target = $this->getDataGenerator()->create_course();
            $owner = $this->getDataGenerator()->create_and_enrol($home, 'student');
            $module = $this->getDataGenerator()->create_module('adaptivequiz', [
                'course' => $home->id,
                'attemptfeedbackeditor' => ['text' => '', 'format' => FORMAT_MOODLE],
            ]);
            $now = time();
            $attemptid = (int) $DB->insert_record('adaptivequiz_attempt', (object) [
                'instance' => $module->id, 'userid' => $owner->id, 'uniqueid' => 67000 + $module->id,
                'attemptstate' => 'complete', 'attemptstopcriteria' => '', 'questionsattempted' => 3 * $k,
                'difficultysum' => 0, 'standarderror' => 0.3, 'measure' => 0, 'timecreated' => $now, 'timemodified' => $now,
            ]);
            $abilities = [];
            $settings = ['name' => 'Test', 'numberoffeedbackoptionsselect' => 1];
            $nbyscale = [];
            for ($s = 1; $s <= $k; $s++) {
                $scaleid = 500 + $s;
                $abilities[$scaleid] = ['value' => 0.4, 'toreport' => true, 'primary' => $s === 1];
                $nbyscale[$scaleid] = 3;
                $group = $this->getDataGenerator()->create_group(['courseid' => $home->id, 'name' => "G$k-$s"]);
                $settings += [
                    "feedback_scaleid_limit_lower_{$scaleid}_1" => -5,
                    "feedback_scaleid_limit_upper_{$scaleid}_1" => 5,
                    "catquiz_courses_{$scaleid}_1" => [0, $target->id],
                    "catquiz_group_{$scaleid}_1" => $group->name,
                    "enrolment_message_checkbox_{$scaleid}_1" => 1,
                ];
            }
            $DB->insert_record('local_catquiz_attempts', (object) [
                'userid' => $owner->id, 'scaleid' => 501, 'contextid' => 9, 'courseid' => $home->id,
                'instanceid' => $module->id, 'attemptid' => $attemptid, 'component' => 'mod_adaptivequiz', 'status' => 0,
                'teststrategy' => LOCAL_CATQUIZ_STRATEGY_ALLSUBS,
                'json' => json_encode([
                    'personabilities_abilities' => $abilities,
                    'se' => array_fill_keys(array_keys($abilities), 0.3),
                ]),
                'timecreated' => $now, 'timemodified' => $now,
            ]);
            $this->setUser($owner);
            local_catquiz_measured_progress($attemptid, $nbyscale, 9, $settings);
            $this->setAdminUser();

            $before = $DB->perf_get_queries();
            attempt_enrolment::enrol($attemptid);
            $queries[$k] = $DB->perf_get_queries() - $before;
            $groups = $DB->count_records_sql(
                'SELECT COUNT(1) FROM {groups_members} gm JOIN {groups} g ON g.id = gm.groupid
                  WHERE gm.userid = ? AND g.courseid = ?',
                [$owner->id, $home->id]
            );
            $this->assertSame($k, $groups, "$k scales route into $k groups.");
        }
        $step24 = ($queries[4] - $queries[2]) / 2;
        $step48 = ($queries[8] - $queries[4]) / 4;
        $this->assertLessThanOrEqual($step24 + 1, $step48, 'Queries per scale: ' . json_encode($queries));
        $this->assertLessThanOrEqual(25, $step48, 'Queries per routed scale: ' . json_encode($queries));
    }
}
