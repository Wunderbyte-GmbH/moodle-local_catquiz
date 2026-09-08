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
 * Issue #18: separate groups are honoured in the course context as well.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\local\access;

use advanced_testcase;
use context_course;
use context_system;

/**
 * Verifies which users' results a teacher may see.
 *
 * The interesting case is the course-wide view. With separate groups a teacher
 * without moodle/site:accessallgroups must not see participants of other groups -
 * and a course-wide statistic is exactly where the widest set of people appears.
 * The restriction used to be skipped there because no course module is involved.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\local\access\feedback_access::get_allowed_userids
 */
final class feedback_access_test extends advanced_testcase {
    /**
     * Builds a course with separate groups, a teacher in group A and two students.
     *
     * @param int $groupmode SEPARATEGROUPS, VISIBLEGROUPS or NOGROUPS.
     * @return array [course, teacher, studenta, studentb]
     */
    private function make_course(int $groupmode): array {
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['groupmode' => $groupmode, 'groupmodeforce' => 1]);

        $teacher = $generator->create_user();
        $studenta = $generator->create_user();
        $studentb = $generator->create_user();

        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');

        // A teacher holds moodle/site:accessallgroups by default in a standard
        // Moodle, which lifts every group restriction. The separate-groups case only
        // arises once a site takes that away, so the test has to model exactly that -
        // otherwise it would assert against a configuration that cannot occur.
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        assign_capability(
            'moodle/site:accessallgroups',
            CAP_PROHIBIT,
            $roleid,
            \context_course::instance($course->id)->id,
            true
        );
        accesslib_clear_all_caches_for_unit_testing();
        $generator->enrol_user($studenta->id, $course->id, 'student');
        $generator->enrol_user($studentb->id, $course->id, 'student');

        $groupa = $generator->create_group(['courseid' => $course->id]);
        $groupb = $generator->create_group(['courseid' => $course->id]);

        // The teacher belongs to group A only - together with student A.
        $generator->create_group_member(['groupid' => $groupa->id, 'userid' => $teacher->id]);
        $generator->create_group_member(['groupid' => $groupa->id, 'userid' => $studenta->id]);
        $generator->create_group_member(['groupid' => $groupb->id, 'userid' => $studentb->id]);

        return [$course, $teacher, $studenta, $studentb];
    }

    /**
     * In the course context a foreign group stays invisible.
     *
     * @return void
     */
    public function test_course_context_hides_other_groups(): void {
        $this->resetAfterTest();

        [$course, $teacher, $studenta, $studentb] = $this->make_course(SEPARATEGROUPS);
        $this->setUser($teacher);

        $allowed = feedback_access::get_allowed_userids(context_course::instance($course->id));

        $this->assertIsArray($allowed, 'With separate groups the set must be restricted.');
        $this->assertContains((int) $studenta->id, $allowed, 'The own group is visible.');
        $this->assertNotContains(
            (int) $studentb->id,
            $allowed,
            'A participant of another group must not be disclosed course-wide.'
        );
        $this->assertContains((int) $teacher->id, $allowed, 'Users always see themselves.');
    }

    /**
     * Without separate groups nothing is restricted.
     *
     * @return void
     */
    public function test_visible_groups_do_not_restrict(): void {
        $this->resetAfterTest();

        [$course, $teacher] = $this->make_course(VISIBLEGROUPS);
        $this->setUser($teacher);

        $this->assertNull(
            feedback_access::get_allowed_userids(context_course::instance($course->id)),
            'Visible groups impose no restriction, so callers can skip filtering.'
        );
    }

    /**
     * accessallgroups lifts the restriction.
     *
     * @return void
     */
    public function test_accessallgroups_sees_everyone(): void {
        global $DB;

        $this->resetAfterTest();

        [$course, $teacher] = $this->make_course(SEPARATEGROUPS);
        $context = context_course::instance($course->id);

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        // The fixture prohibits the capability; grant it back for this case.
        assign_capability('moodle/site:accessallgroups', CAP_ALLOW, $roleid, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();

        $this->setUser($teacher);

        $this->assertNull(
            feedback_access::get_allowed_userids($context),
            'With accessallgroups the whole course is visible.'
        );
    }

    /**
     * The system context carries no group semantics.
     *
     * @return void
     */
    public function test_system_context_is_unrestricted(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertNull(feedback_access::get_allowed_userids(context_system::instance()));
    }
}
