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
}
