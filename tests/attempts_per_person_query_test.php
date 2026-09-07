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

/**
 * The attempts-per-person query counts the right attempts, once each.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;

/**
 * Three defects sat in one query, and each produced a wrong number on its own.
 *
 * The attempts were counted without a scale restriction, so the bar height came from
 * every scale in the context while its colour came from the person ability of the
 * selected one. A second enrolment in the same course multiplied the count. And the
 * course filter overwrote the group restriction instead of adding to it, so a teacher
 * without accessallgroups saw everyone.
 *
 * None of these needs a context change to go wrong, which is why they were invisible
 * behind the context defect.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\catquiz::get_sql_for_attempts_per_person
 */
final class attempts_per_person_query_test extends advanced_testcase {
    /** @var int Course used by the fixture. */
    private int $courseid = 0;

    /** @var int Context the attempts live in. */
    private int $contextid = 7001;

    /**
     * Records an attempt of a user on a scale.
     *
     * @param int $userid
     * @param int $scaleid
     * @return void
     */
    private function add_attempt(int $userid, int $scaleid): void {
        global $DB;

        static $attemptid = 70000;
        $now = time();

        $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => $userid,
            'scaleid' => $scaleid,
            'contextid' => $this->contextid,
            'courseid' => $this->courseid,
            'attemptid' => ++$attemptid,
            'component' => 'mod_adaptivequiz',
            'instanceid' => 1,
            'teststrategy' => 4,
            'status' => 1,
            'json' => '{}',
            'debug_info' => '',
            'timecreated' => $now,
            'timemodified' => $now,
            'endtime' => $now,
        ]);
    }

    /**
     * Runs the query and returns attempts keyed by user id.
     *
     * @param array|null $alloweduserids
     * @param int $scaleid
     * @return array
     */
    private function attempts_per_user(?array $alloweduserids, int $scaleid = 501): array {
        global $DB;

        [$sql, $params] = catquiz::get_sql_for_attempts_per_person(
            $this->contextid,
            $scaleid,
            $this->courseid,
            $alloweduserids
        );

        $result = [];
        foreach ($DB->get_records_sql($sql, $params) as $row) {
            $result[(int) $row->userid] = (int) $row->attempts;
        }

        return $result;
    }

    /**
     * Only attempts on the requested scale are counted.
     *
     * @return void
     */
    public function test_attempts_of_other_scales_are_not_counted(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $this->courseid = (int) $course->id;

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $this->add_attempt((int) $user->id, 501);
        $this->add_attempt((int) $user->id, 501);
        $this->add_attempt((int) $user->id, 502);

        $counts = $this->attempts_per_user(null);

        $this->assertSame(
            2,
            $counts[(int) $user->id] ?? -1,
            'The third attempt belongs to another scale; counting it mixes the bar '
                . 'height of one population with the colour of another.'
        );
    }

    /**
     * A second enrolment does not double the count.
     *
     * @return void
     */
    public function test_second_enrolment_does_not_multiply(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $this->courseid = (int) $course->id;

        $user = $this->getDataGenerator()->create_user();

        // Two enrolment methods in the same course - a manual one and a self one -
        // give the person two user_enrolments rows.
        $this->getDataGenerator()->enrol_user($user->id, $course->id, null, 'manual');
        $this->getDataGenerator()->enrol_user($user->id, $course->id, null, 'self');

        $this->add_attempt((int) $user->id, 501);
        $this->add_attempt((int) $user->id, 501);

        $counts = $this->attempts_per_user(null);

        $this->assertSame(
            2,
            $counts[(int) $user->id] ?? -1,
            'Each enrolment row brought the same attempt count into the sum, so two '
                . 'real attempts could show as four.'
        );
    }

    /**
     * The group restriction survives the course filter.
     *
     * @return void
     */
    public function test_allowed_userids_are_not_discarded(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $this->courseid = (int) $course->id;

        $visible = $this->getDataGenerator()->create_user();
        $hidden = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($visible->id, $course->id);
        $this->getDataGenerator()->enrol_user($hidden->id, $course->id);

        $this->add_attempt((int) $visible->id, 501);
        $this->add_attempt((int) $hidden->id, 501);

        $counts = $this->attempts_per_user([(int) $visible->id]);

        $this->assertArrayHasKey((int) $visible->id, $counts);
        $this->assertArrayNotHasKey(
            (int) $hidden->id,
            $counts,
            'The course filter used to overwrite this restriction, so a teacher '
                . 'without accessallgroups saw every participant.'
        );
    }

    /**
     * An empty allowlist yields nothing, not everything.
     *
     * @return void
     */
    public function test_empty_allowlist_yields_no_rows(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $this->courseid = (int) $course->id;

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $this->add_attempt((int) $user->id, 501);

        $this->assertSame(
            [],
            $this->attempts_per_user([]),
            '"Nothing is visible" has to mean no rows; the discarded guard turned it '
                . 'into "everything is visible".'
        );
    }
    /**
     * An enrolment without a usable role still counts.
     *
     * enrol.roleid describes the enrolment *instance*, not a user's role, and several
     * plugins leave it at 0 - the LTI enrolment does. The query used to inner-join
     * {role} on it, and since no role has id 0, every one of those people was removed
     * from the statistic.
     *
     * On a course filled through LTI that is not an edge case but the whole
     * population: the observed chart showed a single grey bar of height 1, which was
     * not a participant without an attempt but the only user who survived the join.
     *
     * @return void
     */
    public function test_enrolment_without_role_still_counts(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $this->courseid = (int) $course->id;

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        // What an LTI enrolment looks like: the instance carries no role.
        $DB->set_field('enrol', 'roleid', 0, ['courseid' => $course->id]);

        $this->assertFalse(
            $DB->record_exists('role', ['id' => 0]),
            'The premise of the defect: there is no role with id 0, so an inner join '
                . 'on it drops the row.'
        );

        $this->add_attempt((int) $user->id, 501);
        $this->add_attempt((int) $user->id, 501);

        $counts = $this->attempts_per_user(null);

        $this->assertSame(
            2,
            $counts[(int) $user->id] ?? -1,
            'A person enrolled through LTI has to appear with their attempts, not '
                . 'vanish from the chart.'
        );
    }
    /**
     * A realistic cohort arrives complete.
     *
     * The reported course had 87 enrolled people, 81 of them with attempts, and the
     * chart showed five. Every defect found so far lived in the enrolment side of the
     * query - the role join, the duplicate enrolments, the context filter - so this
     * test builds the same shape and asserts the totals rather than a single user.
     *
     * The sum over all buckets has to be the number of people, and the sum of the
     * attempts has to be the number of attempts. Neither held before.
     *
     * @return void
     */
    public function test_a_whole_cohort_is_counted(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $this->courseid = (int) $course->id;

        $expectedattempts = 0;
        $withattempts = 0;

        // 20 people: 12 with one attempt, 6 with two, 2 with none.
        for ($i = 0; $i < 20; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($user->id, $course->id);

            $count = 0;
            if ($i < 12) {
                $count = 1;
            } else if ($i < 18) {
                $count = 2;
            }

            for ($n = 0; $n < $count; $n++) {
                $this->add_attempt((int) $user->id, 501);
            }

            $expectedattempts += $count;
            if ($count > 0) {
                $withattempts++;
            }
        }

        // Enrolled through LTI, which leaves the instance without a role.
        $DB->set_field('enrol', 'roleid', 0, ['courseid' => $course->id]);

        $counts = $this->attempts_per_user(null);

        $this->assertCount(
            20,
            $counts,
            'Every enrolled person belongs in the chart, those without an attempt in '
                . 'the "no attempt" bucket.'
        );
        $this->assertSame(
            $expectedattempts,
            array_sum($counts),
            'The attempts add up to what was recorded.'
        );
        $this->assertSame(
            18,
            count(array_filter($counts)),
            'Twelve people with one attempt and six with two.'
        );
        $this->assertSame(
            2,
            count($counts) - $withattempts,
            'Two people without any attempt.'
        );
    }
}
