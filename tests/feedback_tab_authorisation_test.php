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
 * Issue #68: authorisation of feedback_tab_clicked, role by role.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;
use local_catquiz\external\feedback_tab_clicked;

/**
 * Each role is checked against its own attempt and against somebody else's.
 *
 * The endpoint writes an event that names a user and a feedback tab. An event about
 * something that did not happen is worse than a missing one: it is evidence, and it
 * was previously produced whenever the caller passed any id at all.
 *
 * Every denial is therefore asserted twice - the call is refused, and no event is
 * raised. A guard that throws after the event has already been triggered would pass
 * the first assertion and fail the second.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\external\feedback_tab_clicked::execute
 */
final class feedback_tab_authorisation_test extends advanced_testcase {
    /**
     * Creates an attempt owned by a user.
     *
     * @param int $attemptid
     * @param int $userid
     * @return void
     */
    private function add_attempt(int $attemptid, int $userid): void {
        global $DB;

        $now = time();
        $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => $userid,
            'scaleid' => 1,
            'contextid' => 1,
            'courseid' => 1,
            'attemptid' => $attemptid,
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
     * Asserts that a call is refused and leaves no event behind.
     *
     * @param int $attemptid
     * @return void
     */
    private function assert_refused_without_event(int $attemptid): void {
        $sink = $this->redirectEvents();

        try {
            feedback_tab_clicked::execute($attemptid, 'personabilities', 'Person abilities');
            $this->fail('The call had to be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame([], $sink->get_events(), 'A refused call must raise no event.');
        }
    }

    /**
     * A participant may act on their own attempt.
     *
     * @return void
     */
    public function test_student_with_own_attempt_is_allowed(): void {
        $this->resetAfterTest();

        $student = $this->getDataGenerator()->create_user();
        $this->add_attempt(9101, (int) $student->id);
        $this->setUser($student);

        $sink = $this->redirectEvents();
        feedback_tab_clicked::execute(9101, 'personabilities', 'Person abilities');

        $this->assertNotEmpty($sink->get_events(), 'The ordinary path has to keep working.');
    }

    /**
     * A participant may not act on somebody else's attempt.
     *
     * @return void
     */
    public function test_student_with_foreign_attempt_is_denied(): void {
        $this->resetAfterTest();

        $owner = $this->getDataGenerator()->create_user();
        $intruder = $this->getDataGenerator()->create_user();
        $this->add_attempt(9102, (int) $owner->id);
        $this->setUser($intruder);

        $this->assert_refused_without_event(9102);
    }

    /**
     * A manager may act on an attempt that is not theirs.
     *
     * @return void
     */
    public function test_manager_with_existing_attempt_is_allowed(): void {
        $this->resetAfterTest();

        $owner = $this->getDataGenerator()->create_user();
        $this->add_attempt(9103, (int) $owner->id);
        $this->setAdminUser();

        $sink = $this->redirectEvents();
        feedback_tab_clicked::execute(9103, 'personabilities', 'Person abilities');

        $this->assertNotEmpty($sink->get_events());
    }

    /**
     * A manager may not act on an attempt that does not exist.
     *
     * Capability answers who may act, not whether there is anything to act on. Without
     * loading the attempt first this produced an event about an attempt that was never
     * made.
     *
     * @return void
     */
    public function test_manager_with_unknown_attempt_is_denied(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assert_refused_without_event(999999);
    }

    /**
     * A user without any capability may not act on a foreign attempt.
     *
     * @return void
     */
    public function test_user_without_capability_is_denied(): void {
        $this->resetAfterTest();

        $owner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->add_attempt(9104, (int) $owner->id);
        $this->setUser($other);

        $this->assert_refused_without_event(9104);
    }

    /**
     * An unknown attempt is refused for an ordinary participant too.
     *
     * @return void
     */
    public function test_student_with_unknown_attempt_is_denied(): void {
        $this->resetAfterTest();

        $student = $this->getDataGenerator()->create_user();
        $this->setUser($student);

        $this->assert_refused_without_event(999998);
    }

    /**
     * The event records the role that acted, not the one the caller claims.
     *
     * @return void
     */
    public function test_event_records_the_resolved_role(): void {
        $this->resetAfterTest();

        $student = $this->getDataGenerator()->create_user();
        $this->add_attempt(9105, (int) $student->id);
        $this->setUser($student);

        $sink = $this->redirectEvents();
        feedback_tab_clicked::execute(9105, 'personabilities', 'Person abilities');

        $events = $sink->get_events();
        $this->assertNotEmpty($events);

        $last = end($events);
        $this->assertSame('student', $last->other['role'] ?? null);
        $this->assertSame('personabilities', $last->other['feedback'] ?? null);
    }
}
