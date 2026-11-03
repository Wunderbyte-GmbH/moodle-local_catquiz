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

/**
 * "No attempt" counts only the people who could have taken a test (issue #132).
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\local\statistics\eligible_population
 * @covers \local_catquiz\catquiz
 */
final class eligible_population_test extends advanced_testcase {
    /** @var int The scale of the tests. */
    private const SCALE = 601;

    /**
     * A CATquiz test of the scale in a course.
     *
     * @param int $courseid
     * @return \stdClass The course module.
     */
    private function test_in(int $courseid): \stdClass {
        global $DB;
        $quiz = $this->getDataGenerator()->get_plugin_generator('mod_adaptivequiz')->create_instance([
            'course' => $courseid,
            'highestlevel' => 10,
            'lowestlevel' => 1,
            'standarderror' => 14,
            'attemptfeedbackeditor' => ['text' => '', 'format' => FORMAT_MOODLE],
        ]);
        $DB->insert_record('local_catquiz_tests', (object) [
            'componentid' => $quiz->id, 'component' => 'mod_adaptivequiz', 'catscaleid' => self::SCALE,
            'courseid' => $courseid, 'name' => 'test', 'status' => 1, 'json' => '{}',
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        return get_coursemodule_from_instance('adaptivequiz', $quiz->id, $courseid, false, MUST_EXIST);
    }

    /**
     * Enrols new users.
     *
     * @param int $courseid
     * @param int $n
     * @param string $role
     * @return int[]
     */
    private function enrol(int $courseid, int $n, string $role = 'student'): array {
        $ids = [];
        for ($i = 0; $i < $n; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($user->id, $courseid, $role);
            $ids[] = (int) $user->id;
        }
        return $ids;
    }

    /**
     * Restricts an activity by availability conditions.
     *
     * @param \stdClass $cm
     * @param array $conditions
     */
    private function restrict(\stdClass $cm, array $conditions): void {
        global $DB;
        $DB->set_field(
            'course_modules',
            'availability',
            json_encode(\core_availability\tree::get_root_json($conditions)),
            ['id' => $cm->id]
        );
        rebuild_course_cache($cm->course, true);
    }

    /**
     * An attempt of a person on the scale.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $instanceid
     */
    private function attempt(int $userid, int $courseid, int $instanceid): void {
        global $DB;
        static $hostattemptid = 81000;
        $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => $userid, 'scaleid' => self::SCALE, 'contextid' => 1, 'courseid' => $courseid,
            'attemptid' => ++$hostattemptid, 'component' => 'mod_adaptivequiz', 'instanceid' => $instanceid,
            'teststrategy' => 1, 'status' => 0, 'json' => '{}', 'endtime' => time(), 'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * The chart's rows: attempts per person.
     *
     * @param int|null $courseid
     * @param int|null $testid
     * @return array userid => attempts
     */
    private function rows(?int $courseid, ?int $testid = null): array {
        global $DB;
        eligible_population::reset();
        [$sql, $params] = catquiz::get_sql_for_attempts_per_person(1, self::SCALE, $courseid, null, $testid);
        $rows = [];
        foreach ($DB->get_records_sql($sql, $params) as $row) {
            $rows[(int) $row->userid] = (int) $row->attempts;
        }
        return $rows;
    }

    /**
     * The people in the "no attempt" bar.
     *
     * @param int|null $courseid
     * @param int|null $testid
     * @return int[]
     */
    private function without_attempt(?int $courseid, ?int $testid = null): array {
        $ids = array_keys(array_filter($this->rows($courseid, $testid), fn($n) => $n === 0));
        sort($ids);
        return $ids;
    }

    /**
     * Ten enrolled, the test open to a group of three: at most three without an attempt.
     */
    public function test_group_restriction_limits_the_population(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $users = $this->enrol((int) $course->id, 10);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        foreach (array_slice($users, 0, 3) as $userid) {
            $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $userid]);
        }
        $cm = $this->test_in((int) $course->id);
        $this->restrict($cm, [\availability_group\condition::get_json($group->id)]);

        $this->assertSame(array_slice($users, 0, 3), $this->without_attempt((int) $course->id));
    }

    /**
     * A grouping restriction works the same way.
     */
    public function test_grouping_restriction_limits_the_population(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $users = $this->enrol((int) $course->id, 4);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $grouping = $this->getDataGenerator()->create_grouping(['courseid' => $course->id]);
        $this->getDataGenerator()->create_grouping_group(['groupingid' => $grouping->id, 'groupid' => $group->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $users[1]]);
        $cm = $this->test_in((int) $course->id);
        $this->restrict($cm, [\availability_grouping\condition::get_json($grouping->id)]);

        $this->assertSame([$users[1]], $this->without_attempt((int) $course->id));
    }

    /**
     * Teachers - without the right to attempt the test - are not counted.
     */
    public function test_teachers_are_not_counted(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $students = $this->enrol((int) $course->id, 2);
        $this->enrol((int) $course->id, 1, 'editingteacher');
        $this->enrol((int) $course->id, 1, 'teacher');
        $this->test_in((int) $course->id);

        $this->assertSame($students, $this->without_attempt((int) $course->id));
    }

    /**
     * Without mod/adaptivequiz:attempt in the activity - here taken from students there - nobody counts.
     */
    public function test_the_attempt_capability_decides(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $students = $this->enrol((int) $course->id, 2);
        $cm = $this->test_in((int) $course->id);
        $this->assertSame($students, $this->without_attempt((int) $course->id));

        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'student']);
        assign_capability('mod/adaptivequiz:attempt', CAP_PROHIBIT, $roleid, \context_module::instance($cm->id)->id);
        accesslib_clear_all_caches_for_unit_testing();

        $this->assertSame([], $this->without_attempt((int) $course->id));
    }

    /**
     * Suspended and expired enrolments are not counted.
     */
    public function test_suspended_and_expired_enrolments_are_not_counted(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        [$active, $suspended, $expired] = $this->enrol((int) $course->id, 3);
        $enrolid = (int) $DB->get_field('enrol', 'id', ['courseid' => $course->id, 'enrol' => 'manual']);
        $DB->set_field('user_enrolments', 'status', ENROL_USER_SUSPENDED, ['userid' => $suspended, 'enrolid' => $enrolid]);
        $DB->set_field('user_enrolments', 'timeend', time() - DAYSECS, ['userid' => $expired, 'enrolid' => $enrolid]);
        $this->test_in((int) $course->id);

        $this->assertSame([$active], $this->without_attempt((int) $course->id));
    }

    /**
     * Conditions that select people - here a profile field - are respected; a hidden activity leaves
     * nobody without an attempt.
     */
    public function test_availability_and_visibility_are_respected(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $users = $this->enrol((int) $course->id, 3);
        $insider = $this->getDataGenerator()->create_user(['email' => 'someone@insider.example']);
        $this->getDataGenerator()->enrol_user($insider->id, $course->id, 'student');
        $cm = $this->test_in((int) $course->id);

        $this->restrict($cm, [\availability_profile\condition::get_json(false, 'email', 'contains', 'insider')]);
        $this->assertSame([(int) $insider->id], $this->without_attempt((int) $course->id), 'Profile condition.');

        $this->restrict($cm, []);
        set_coursemodule_visible($cm->id, 0);
        rebuild_course_cache($cm->course, true);
        $this->assertSame([], $this->without_attempt((int) $course->id), 'Hidden activity.');
    }

    /**
     * A time window says when, not who: once it has closed, the people who could have taken the test are
     * still the population - otherwise every exam with an end date would lose its "no attempt" bar.
     */
    public function test_a_closed_time_window_keeps_the_population(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $users = $this->enrol((int) $course->id, 2);
        $cm = $this->test_in((int) $course->id);
        $this->restrict($cm, [
            \availability_date\condition::get_json(\availability_date\condition::DIRECTION_UNTIL, time() - HOURSECS),
        ]);

        $this->assertSame($users, $this->without_attempt((int) $course->id));
    }

    /**
     * Two tests of the scale for two groups: without a test filter their union, with one only its group.
     */
    public function test_union_of_tests_and_the_test_filter(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $users = $this->enrol((int) $course->id, 5);
        [$g1, $g2] = [
            $this->getDataGenerator()->create_group(['courseid' => $course->id]),
            $this->getDataGenerator()->create_group(['courseid' => $course->id]),
        ];
        $this->getDataGenerator()->create_group_member(['groupid' => $g1->id, 'userid' => $users[0]]);
        $this->getDataGenerator()->create_group_member(['groupid' => $g1->id, 'userid' => $users[1]]);
        $this->getDataGenerator()->create_group_member(['groupid' => $g2->id, 'userid' => $users[2]]);
        $cm1 = $this->test_in((int) $course->id);
        $cm2 = $this->test_in((int) $course->id);
        $this->restrict($cm1, [\availability_group\condition::get_json($g1->id)]);
        $this->restrict($cm2, [\availability_group\condition::get_json($g2->id)]);

        $this->assertSame(array_slice($users, 0, 3), $this->without_attempt((int) $course->id));
        $this->assertSame(array_slice($users, 0, 2), $this->without_attempt((int) $course->id, (int) $cm1->instance));
        $this->assertSame([$users[2]], $this->without_attempt((int) $course->id, (int) $cm2->instance));
    }

    /**
     * Without a course filter: the people of every course with a test of the scale, nobody else.
     */
    public function test_without_course_filter_only_courses_with_a_test_count(): void {
        $this->resetAfterTest();
        $withtest = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $inside = $this->enrol((int) $withtest->id, 2);
        $this->enrol((int) $other->id, 4);
        $this->test_in((int) $withtest->id);

        $this->assertSame($inside, $this->without_attempt(null));
    }

    /**
     * A person with an attempt stays in the bar of their attempts, even without access today; the bars
     * with attempts are unchanged.
     */
    public function test_attempts_count_whatever_the_access_today(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        [$tested, $untested] = $this->enrol((int) $course->id, 2);
        $cm = $this->test_in((int) $course->id);
        $this->attempt($tested, (int) $course->id, (int) $cm->instance);
        $this->attempt($tested, (int) $course->id, (int) $cm->instance);
        $enrolid = (int) $DB->get_field('enrol', 'id', ['courseid' => $course->id, 'enrol' => 'manual']);
        $DB->set_field('user_enrolments', 'status', ENROL_USER_SUSPENDED, ['userid' => $tested, 'enrolid' => $enrolid]);

        $rows = $this->rows((int) $course->id);
        ksort($rows);
        $expected = [$tested => 2, $untested => 0];
        ksort($expected);
        $this->assertSame($expected, $rows);
    }
}
