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

namespace local_catquiz\teststrategy;

use advanced_testcase;

/**
 * Whose progress may be read: the attempt owner's, by whoever is allowed to look at the attempt.
 *
 * The ownership check of issue #96 first compared the progress with $USER. A teacher opening a
 * student's result then had the student's progress refused, a new one was started without quiz
 * settings, and the feedback page crashed - the Behat scenario 'CAT quiz permissions are judged in
 * the course context of the attempt' caught it. The check now compares with the owner of the CAT
 * attempt, which is what #96 is about: no foreign row attached to someone's attempt.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\teststrategy\progress
 */
final class progress_viewer_test extends advanced_testcase {
    /**
     * Files a CAT attempt and the progress its owner wrote.
     *
     * @param int $ownerid The person the attempt belongs to.
     * @param int $componentattemptid Id of the attempt of the component.
     * @return int Id of the progress row.
     */
    private function attempt_with_progress(int $ownerid, int $componentattemptid): int {
        global $DB;

        $now = time();
        $catid = (int) $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => $ownerid, 'scaleid' => 1, 'contextid' => 9, 'courseid' => 1,
            'attemptid' => $componentattemptid, 'component' => 'mod_adaptivequiz',
            'instanceid' => 1, 'status' => 1, 'json' => '{}',
            'timecreated' => $now, 'timemodified' => $now,
        ]);

        // Written the way production writes it: by the owner, through progress itself.
        $this->setUser($ownerid);
        $progress = progress::load($componentattemptid, 'mod_adaptivequiz', 9, (object) []);
        $progress->save();
        \cache::make('local_catquiz', 'adaptivequizattempt')->purge();

        return (int) $DB->get_field('local_catquiz_progress', 'id', ['attemptid' => $catid]);
    }

    /**
     * A teacher reads the student's progress; nothing new is started and nothing crashes.
     */
    public function test_a_teacher_reads_the_students_progress(): void {
        $this->resetAfterTest();

        $student = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $progressid = $this->attempt_with_progress((int) $student->id, 88001);

        $this->setUser($teacher);
        \cache::make('local_catquiz', 'adaptivequizattempt')->purge();

        // Without quiz settings, exactly as the feedback page calls it.
        $progress = progress::load(88001, 'mod_adaptivequiz', 9);

        $this->assertEquals(
            $progressid,
            $progress->get_id(),
            'The student\'s progress must be read, not replaced by a new one for the teacher.'
        );
    }

    /**
     * A row of somebody else attached to an attempt is still refused - that is what #96 is about.
     */
    public function test_a_foreign_row_on_an_attempt_is_refused(): void {
        global $DB;

        $this->resetAfterTest();

        $student = $this->getDataGenerator()->create_user();
        $stranger = $this->getDataGenerator()->create_user();
        $progressid = $this->attempt_with_progress((int) $student->id, 88002);

        // The row now belongs to somebody other than the attempt owner.
        $DB->set_field('local_catquiz_progress', 'userid', $stranger->id, ['id' => $progressid]);

        $this->setUser($student);
        \cache::make('local_catquiz', 'adaptivequizattempt')->purge();

        // Issue #96: fail closed - a controlled error instead of a fresh progress that would collide
        // with the foreign row when saved.
        try {
            progress::load(88002, 'mod_adaptivequiz', 9, (object) []);
            $this->fail('A row of another person attached to this attempt was taken over.');
        } catch (\moodle_exception $e) {
            $this->assertSame('progressintegrityerror', $e->errorcode);
        }
        $this->assertEquals($stranger->id, $DB->get_field('local_catquiz_progress', 'userid', ['id' => $progressid]));
    }
}
