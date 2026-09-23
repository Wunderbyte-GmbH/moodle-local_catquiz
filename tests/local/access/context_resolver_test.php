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
 * Issue #18: permissions are judged in the context the attempt belongs to.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\local\access;

use advanced_testcase;
use context_course;
use context_module;
use context_system;

/**
 * Tests the attempt context resolver (issue #18).
 *
 * The regression these tests guard against: teacher feedback and statistics
 * permissions were checked against the system context or the global $COURSE.
 * A teacher enrolled in the course of the attempt was therefore denied access,
 * while a teacher of an unrelated course could pass a system wide check.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\local\access\context_resolver
 * @covers     \local_catquiz\local\access\feedback_access
 */
final class context_resolver_test extends advanced_testcase {
    /**
     * Creates a course with an adaptive quiz and a CATquiz attempt row.
     *
     * @param int $attemptid The component attempt id to register.
     * @return array [\stdClass $course, \cm_info|\stdClass $cm]
     */
    private function make_attempt(int $attemptid): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $adaptivequiz = $this->getDataGenerator()->get_plugin_generator('mod_adaptivequiz')
            ->create_instance([
                'course' => $course->id,
                'highestlevel' => 10,
                'lowestlevel' => 1,
                'standarderror' => 14,
                'attemptfeedbackeditor' => ['text' => '', 'format' => FORMAT_MOODLE],
            ]);
        $cm = get_coursemodule_from_instance('adaptivequiz', $adaptivequiz->id);

        $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => 0,
            'courseid' => $course->id,
            'attemptid' => $attemptid,
            'component' => 'mod_adaptivequiz',
            'instanceid' => $adaptivequiz->id,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        context_resolver::reset_cache();

        return [$course, $cm];
    }

    /**
     * An attempt resolves to the module context of its quiz instance.
     *
     * @return void
     */
    public function test_attempt_resolves_to_module_context(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [, $cm] = $this->make_attempt(4711);

        $resolved = context_resolver::for_attempt(4711);

        $this->assertInstanceOf(context_module::class, $resolved);
        $this->assertEquals(context_module::instance($cm->id)->id, $resolved->id);
    }

    /**
     * A teacher of the attempt's course may see teacher feedback there.
     *
     * This is the case that the old system context check got wrong: the teacher
     * has no system wide role, so has_capability() in the system context returned
     * false even though the attempt belongs to their own course.
     *
     * @return void
     */
    public function test_course_teacher_has_capability_in_attempt_context(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$course] = $this->make_attempt(4712);

        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $resolved = context_resolver::for_attempt(4712);

        $this->assertTrue(has_capability('local/catquiz:view_users_feedback', $resolved));
        // The very check the old code performed - it denies the legitimate teacher.
        $this->assertFalse(
            has_capability('local/catquiz:view_users_feedback', context_system::instance()),
            'Teacher must not hold the capability system wide - otherwise this test proves nothing.'
        );
    }

    /**
     * A teacher of an unrelated course must not gain access to this attempt.
     *
     * @return void
     */
    public function test_teacher_of_other_course_has_no_access(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->make_attempt(4713);

        $othercourse = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $othercourse->id, 'editingteacher');
        $this->setUser($teacher);

        $resolved = context_resolver::for_attempt(4713);

        $this->assertFalse(has_capability('local/catquiz:view_users_feedback', $resolved));
    }

    /**
     * An unknown attempt falls back to the system context instead of failing.
     *
     * @return void
     */
    public function test_unknown_attempt_falls_back_to_system_context(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        context_resolver::reset_cache();

        $this->assertInstanceOf(context_system::class, context_resolver::for_attempt(987654));
        $this->assertInstanceOf(context_system::class, context_resolver::for_attempt(0));
    }

    /**
     * An attempt whose module is gone still resolves to its course context.
     *
     * @return void
     */
    public function test_attempt_without_module_falls_back_to_course_context(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => 0,
            'courseid' => $course->id,
            'attemptid' => 4714,
            'component' => 'mod_adaptivequiz',
            // Instance no longer exists.
            'instanceid' => 999999,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        context_resolver::reset_cache();

        $resolved = context_resolver::for_attempt(4714);

        $this->assertInstanceOf(context_course::class, $resolved);
        $this->assertEquals(context_course::instance($course->id)->id, $resolved->id);
    }

    /**
     * The site course is treated as "no course" and never as a course context.
     *
     * Checking against the site course context would be equivalent to a system
     * wide check and would reintroduce the over-broad access of the old code.
     *
     * @return void
     */
    public function test_site_course_is_not_used_as_course_context(): void {
        $this->resetAfterTest();

        $this->assertInstanceOf(context_system::class, context_resolver::for_courseid(SITEID));
        $this->assertInstanceOf(context_system::class, context_resolver::for_courseid(0));
    }

    /**
     * Statistics scoped to a test resolve to that quiz's module context.
     *
     * @return void
     */
    public function test_statistics_context_prefers_the_test_instance(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $cm] = $this->make_attempt(4715);
        $instanceid = $cm->instance;

        $resolved = context_resolver::for_statistics($course->id, $instanceid);

        $this->assertInstanceOf(context_module::class, $resolved);
        $this->assertEquals(context_module::instance($cm->id)->id, $resolved->id);
    }

    /**
     * Statistics without any scope resolve to the system context instead of failing.
     *
     * The old export code called context_course::instance() on a null course id,
     * which threw. A site wide statistics shortcode must not crash.
     *
     * @return void
     */
    public function test_statistics_context_without_scope_does_not_throw(): void {
        $this->resetAfterTest();

        $this->assertInstanceOf(context_system::class, context_resolver::for_statistics(null, null));
        $this->assertInstanceOf(context_system::class, context_resolver::for_statistics(0, 0));
    }

    /**
     * A CAT manager may see other users' data anywhere.
     *
     * @return void
     */
    public function test_catmanager_may_view_other_users(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [, $cm] = $this->make_attempt(4716);
        $context = context_module::instance($cm->id);

        $manager = $this->getDataGenerator()->create_user();
        $managerrole = $this->getDataGenerator()->create_role();
        assign_capability(
            'local/catquiz:canmanage',
            CAP_ALLOW,
            $managerrole,
            context_system::instance()->id
        );
        role_assign($managerrole, $manager->id, context_system::instance()->id);
        $this->setUser($manager);

        $this->assertTrue(feedback_access::can_view_other_users($context));
    }

    /**
     * A plain participant may see themselves but nobody else.
     *
     * @return void
     */
    public function test_participant_sees_only_themselves(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $cm] = $this->make_attempt(4717);
        $context = context_module::instance($cm->id);

        $student = $this->getDataGenerator()->create_user();
        $otherstudent = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $this->assertFalse(feedback_access::can_view_other_users($context));
        $this->assertTrue(feedback_access::can_view_user($context, $student->id));
        $this->assertFalse(feedback_access::can_view_user($context, $otherstudent->id));
    }

    /**
     * A course teacher may view other users through the course context.
     *
     * This pins the second branch of the access rule: without it, a version of
     * feedback_access that only ever asks the system context would still pass all
     * other tests, because the CAT manager case short-circuits earlier.
     *
     * @return void
     */
    public function test_course_teacher_may_view_other_users(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $cm] = $this->make_attempt(4721);
        $context = context_module::instance($cm->id);

        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $this->assertTrue(feedback_access::can_view_other_users($context));
        // Proves the decision came from the course/module context, not system wide.
        $this->assertFalse(
            has_capability('local/catquiz:canmanage', context_system::instance())
        );
        $this->assertFalse(
            has_capability('local/catquiz:view_users_feedback', context_system::instance())
        );
    }

    /**
     * Every teacher role of the course may see the teacher feedback.
     *
     * Role archetypes are independent templates: a role with archetype
     * "editingteacher" does NOT inherit the defaults granted to "teacher". Listing
     * only 'teacher' in db/access.php therefore left editing teachers - the role
     * that already reviews other people's attempts - as the one teacher role
     * without access. Verified empirically before the fix: editingteacher returned
     * false, teacher returned true.
     *
     * @return void
     */
    public function test_all_teacher_roles_may_see_teacher_feedback(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $cm] = $this->make_attempt(4722);
        $context = context_module::instance($cm->id);

        foreach (['editingteacher', 'teacher'] as $rolename) {
            $user = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($user->id, $course->id, $rolename);
            $this->setUser($user);

            $this->assertTrue(
                has_capability('local/catquiz:view_teacher_feedback', $context),
                "Role $rolename must hold view_teacher_feedback in the attempt's context."
            );
        }

        // A participant must not, otherwise the assertions above prove nothing.
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);
        $this->assertFalse(has_capability('local/catquiz:view_teacher_feedback', $context));
    }

    /**
     * Without separate groups mode no user filtering is applied.
     *
     * @return void
     */
    public function test_no_group_restriction_without_separate_groups(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [, $cm] = $this->make_attempt(4718);

        $this->assertNull(feedback_access::get_allowed_userids(context_module::instance($cm->id)));
    }

    /**
     * In separate groups mode a teacher only sees members of their own groups.
     *
     * @return void
     */
    public function test_separate_groups_restrict_visible_users(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $cm] = $this->make_attempt(4719);

        // Switch the activity to separate groups.
        $DB->set_field('course_modules', 'groupmode', SEPARATEGROUPS, ['id' => $cm->id]);
        rebuild_course_cache($course->id, true);

        $groupa = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        $teacher = $this->getDataGenerator()->create_user();
        $mate = $this->getDataGenerator()->create_user();
        $stranger = $this->getDataGenerator()->create_user();
        // The stock teacher roles hold moodle/site:accessallgroups, which would lift
        // the restriction and make this test vacuous. Use a reviewer role that may
        // see other users' feedback but is bound to its own groups.
        $reviewerrole = $this->getDataGenerator()->create_role();
        assign_capability(
            'local/catquiz:view_users_feedback',
            CAP_ALLOW,
            $reviewerrole,
            context_course::instance($course->id)->id
        );
        assign_capability(
            'moodle/site:accessallgroups',
            CAP_PREVENT,
            $reviewerrole,
            context_course::instance($course->id)->id
        );
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'student');
        role_assign($reviewerrole, $teacher->id, context_course::instance($course->id)->id);
        $this->getDataGenerator()->enrol_user($mate->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($stranger->id, $course->id, 'student');
        $this->getDataGenerator()->create_group_member(['groupid' => $groupa->id, 'userid' => $teacher->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $groupa->id, 'userid' => $mate->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $groupb->id, 'userid' => $stranger->id]);

        $this->setUser($teacher);
        $allowed = feedback_access::get_allowed_userids(context_module::instance($cm->id));

        $this->assertIsArray($allowed);
        $this->assertContains((int) $mate->id, $allowed);
        $this->assertNotContains((int) $stranger->id, $allowed);
    }

    /**
     * A user with accessallgroups is not restricted by separate groups mode.
     *
     * @return void
     */
    public function test_accessallgroups_lifts_the_group_restriction(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $cm] = $this->make_attempt(4720);
        $DB->set_field('course_modules', 'groupmode', SEPARATEGROUPS, ['id' => $cm->id]);
        rebuild_course_cache($course->id, true);
        $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        // The admin holds moodle/site:accessallgroups.
        $this->setAdminUser();

        $this->assertNull(feedback_access::get_allowed_userids(context_module::instance($cm->id)));
    }
    /**
     * The lookup is component-safe and does not borrow a foreign component's course.
     *
     * The schema guarantees that an attempt id exists only once, so a collision
     * cannot occur today. The resolver still asks for the component rather than
     * relying on that guarantee: a lookup that authorises against whatever row it
     * happens to find would depend on a constraint held elsewhere, and it used
     * IGNORE_MULTIPLE, which explicitly accepts an arbitrary row.
     *
     * @return void
     */
    public function test_attempt_is_resolved_per_component(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $courseone = $this->getDataGenerator()->create_course();
        $coursetwo = $this->getDataGenerator()->create_course();
        $now = time();

        $insert = function (string $component, int $courseid) use ($DB, $now): void {
            $DB->insert_record('local_catquiz_attempts', (object) [
                'userid' => 2,
                'scaleid' => 1,
                'contextid' => 1,
                'courseid' => $courseid,
                'attemptid' => 4242,
                'component' => $component,
                'instanceid' => 0,
                'status' => 1,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        };

        $insert('mod_adaptivequiz', $courseone->id);

        // UNIQUE(attemptid) is a deliberate domain invariant, not an oversight: an
        // attempt belongs to exactly one component, so the same external id must not
        // exist twice. The test pins that the database really enforces it - if the
        // constraint were ever dropped, the invariant would silently stop holding
        // and this test would say so.
        try {
            $insert('mod_somethingelse', $coursetwo->id);
            $this->fail('UNIQUE(attemptid) no longer holds - the domain invariant is gone.');
        } catch (\dml_exception $e) {
            $this->assertTrue(true);
        }

        // With the component in the lookup the existing row still resolves correctly,
        // and a request for a different component does not borrow it.
        $mine = context_resolver::for_attempt(4242, 'mod_adaptivequiz');
        $foreign = context_resolver::for_attempt(4242, 'mod_somethingelse');

        $this->assertEquals(\context_course::instance($courseone->id)->id, $mine->id);
        $this->assertEquals(
            \context_system::instance()->id,
            $foreign->id,
            'A foreign component must not inherit the course of another one.'
        );
    }

    /**
     * An unknown attempt falls back to the system context, not to a foreign course.
     *
     * @return void
     */
    public function test_unknown_attempt_fails_closed(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $context = context_resolver::for_attempt(999999, 'mod_adaptivequiz');

        $this->assertEquals(\context_system::instance()->id, $context->id);
    }
    /**
     * A row stored under the plain module name is still found.
     *
     * The runtime writes 'adaptivequiz' while callers pass 'mod_adaptivequiz'. A
     * strict comparison matched neither spelling against the other and sent every
     * lookup into the system-context fallback - which is fail-closed, but denied
     * teachers a review they are entitled to.
     *
     * @return void
     */
    public function test_plain_module_name_is_accepted(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $now = time();

        $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => 2,
            'scaleid' => 1,
            'contextid' => 1,
            'courseid' => $course->id,
            'attemptid' => 7777,
            'component' => 'adaptivequiz',
            'instanceid' => 0,
            'status' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        foreach (['adaptivequiz', 'mod_adaptivequiz'] as $spelling) {
            $context = context_resolver::for_attempt(7777, $spelling);
            $this->assertEquals(
                \context_course::instance($course->id)->id,
                $context->id,
                "Spelling $spelling must resolve to the course of the attempt."
            );
        }
    }
}
