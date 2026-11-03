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
use local_catquiz\catquiz;
use local_catquiz\local\attempt\attempt_finalizer;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * The CAT attempt exists from the first progress access on (issue #101).
 *
 * It used to be written only when the result page was shown. Since issue #95 files progress under
 * the CAT attempt, that meant: no CAT attempt during the test, so no progress in the database -
 * only in the cache of the person taking the test. A teacher opening the result read nothing, and
 * any cache flush lost the progress. The Behat run of 27 September failed on exactly that.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\teststrategy\progress
 */
final class cat_attempt_lifecycle_test extends advanced_testcase {
    /**
     * Starting a test files a running CAT attempt, and the progress reaches the database at once.
     */
    public function test_progress_is_persisted_from_the_start(): void {
        global $DB;

        $this->resetAfterTest();
        $student = $this->getDataGenerator()->create_user();
        $this->setUser($student);

        $progress = progress::load(93001, 'mod_adaptivequiz', 9, (object) []);
        $progress->save();

        $cat = $DB->get_record('local_catquiz_attempts', ['attemptid' => 93001, 'component' => 'mod_adaptivequiz']);
        $this->assertNotFalse($cat, 'Starting a test must file its CAT attempt.');
        $this->assertEquals(LOCAL_CATQUIZ_ATTEMPT_RUNNING, (int) $cat->status);
        $this->assertNull($cat->scaleid, 'A running attempt carries no scale - statistics must not see it.');
        $this->assertTrue(
            $DB->record_exists('local_catquiz_progress', ['attemptid' => $cat->id]),
            'The progress must be in the database while the test runs, not only in the cache.'
        );
    }

    /**
     * A teacher reads the progress of a test that is still running, after the cache has gone.
     */
    public function test_a_teacher_reads_a_running_test(): void {
        $this->resetAfterTest();
        $student = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();

        $this->setUser($student);
        $progress = progress::load(93002, 'mod_adaptivequiz', 9, (object) []);
        $progress->save();

        $this->setUser($teacher);
        \cache::make('local_catquiz', 'adaptivequizattempt')->purge();

        $read = progress::load(93002, 'mod_adaptivequiz', 9);
        $this->assertEquals($progress->get_id(), $read->get_id());
    }

    /**
     * A reader never creates an attempt.
     */
    public function test_reading_does_not_start_an_attempt(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        // Issue #149: a reader of an attempt without progress gets progress_missing_exception - no longer the
        // TypeError of creating a progress without settings - and files nothing.
        try {
            progress::load(93003, 'mod_adaptivequiz', 9);
            $this->fail('A reader got a progress of an attempt that never existed.');
        } catch (\local_catquiz\teststrategy\progress_missing_exception $e) {
            $this->assertSame('progressmissing', $e->errorcode);
        }

        $this->assertFalse($DB->record_exists('local_catquiz_attempts', ['attemptid' => 93003]));
    }

    /**
     * A test that ended before any answer is still finalised and announced as ended.
     *
     * A test can end without one processed answer - by the time limit, after too long a break, or
     * by leaving right after the start - and without the result page ever being reached. It used to
     * stay 'running' and was never announced; it is finalised now, without a result.
     */
    public function test_a_test_ended_without_an_answer_is_finalised(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        progress::load(93004, 'mod_adaptivequiz', 9, (object) [])->save();

        $sink = $this->redirectEvents();
        $this->assertTrue(attempt_finalizer::finalize(93004, time(), 'reason'), 'The ended test was not finalised.');
        $this->assertFalse(attempt_finalizer::finalize(93004, time(), 'reason'), 'It was finalised twice.');
        $events = array_filter($sink->get_events(), fn($e) => $e instanceof \local_catquiz\event\attempt_completed);
        $sink->close();

        $row = $DB->get_record('local_catquiz_attempts', ['attemptid' => 93004]);
        $this->assertNotEmpty($row->endtime);
        $this->assertEquals(LOCAL_CATQUIZ_ATTEMPT_ABORTED, (int) $row->status, 'Not marked as ended without a result.');
        $this->assertCount(1, $events, 'The ended test was not announced exactly once.');
        $this->assertEquals(0, reset($events)->other['resultvalid']);
    }

    /**
     * After the result page has saved the attempt, the next access still finds it.
     *
     * save_attempt_to_db() stores the bare module name - 'adaptivequiz' - where the start of the
     * test wrote 'mod_adaptivequiz'. The lookup compared with one spelling only, so the next access
     * missed its own CAT attempt and reported a collision with 'another component'. That debugging
     * message failed 15 Behat scenarios.
     */
    public function test_the_attempt_is_found_after_the_result_page_saved_it(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $progress = progress::load(93006, 'mod_adaptivequiz', 9, (object) []);
        $progress->save();

        // What the result page does to the row: it writes the bare module name.
        $DB->set_field('local_catquiz_attempts', 'component', 'adaptivequiz', ['attemptid' => 93006]);
        \cache::make('local_catquiz', 'adaptivequizattempt')->purge();

        $again = progress::load(93006, 'mod_adaptivequiz', 9, (object) []);

        $this->assertEquals($progress->get_id(), $again->get_id(), 'The test lost its own progress.');
        $this->assertEquals(1, $DB->count_records('local_catquiz_attempts', ['attemptid' => 93006]));
    }

    /**
     * A running attempt does not appear among the attempts that are listed.
     */
    public function test_a_running_attempt_is_not_listed(): void {
        $this->resetAfterTest();
        $student = $this->getDataGenerator()->create_user();
        $this->setUser($student);

        progress::load(93005, 'mod_adaptivequiz', 9, (object) [])->save();

        // The method get_attempts() returns a recordset, which is an object whether or not it holds rows.
        $rows = catquiz::get_attempts((int) $student->id);
        $listed = iterator_to_array($rows, false);
        $rows->close();

        $this->assertSame([], $listed, 'A running attempt must not be listed.');
    }

    /**
     * Starting a CAT attempt through a reflection of the private start, as load() does.
     *
     * @param int $adaptiveattemptid
     * @return int|null
     */
    private function start(int $adaptiveattemptid): ?int {
        $method = new \ReflectionMethod(progress::class, 'start_cat_attempt');
        $method->setAccessible(true);
        return $method->invoke(null, $adaptiveattemptid, 'mod_adaptivequiz');
    }

    /**
     * An activity attempt of a person.
     *
     * @param int $userid
     * @return int
     */
    private function activity_attempt(int $userid): int {
        global $DB;
        return (int) $DB->insert_record('adaptivequiz_attempt', (object) [
            'instance' => 1, 'userid' => $userid, 'uniqueid' => 0, 'attemptstate' => 'inprogress',
            'attemptstopcriteria' => '', 'questionsattempted' => 0, 'difficultysum' => 0, 'standarderror' => 1,
            'measure' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * A CAT attempt a parallel request filed already is taken over, not refused (issue #95); one of
     * another person or component is not.
     */
    public function test_an_existing_cat_attempt_is_taken_over_only_when_it_is_the_same(): void {
        global $DB;
        $this->resetAfterTest();
        $student = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->setUser($student);

        $mine = $this->activity_attempt((int) $student->id);
        $existing = (int) $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => $student->id, 'attemptid' => $mine, 'component' => 'mod_adaptivequiz', 'status' => 2,
            'json' => '{}', 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $this->assertSame($existing, $this->start($mine), 'The CAT attempt of a parallel request is this one.');

        $theirs = $this->activity_attempt((int) $other->id);
        $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => $student->id, 'attemptid' => $theirs, 'component' => 'mod_adaptivequiz', 'status' => 2,
            'json' => '{}', 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $this->assertNull($this->start($theirs), 'A CAT attempt of the wrong person is not taken over.');
        $this->assertDebuggingCalled();
    }

    /**
     * Whoever is first to touch it, the CAT attempt belongs to the owner of the activity attempt (issue #95).
     */
    public function test_the_cat_attempt_belongs_to_the_owner_of_the_attempt(): void {
        global $DB;
        $this->resetAfterTest();
        $student = $this->getDataGenerator()->create_user();
        $this->setAdminUser();

        $attemptid = $this->activity_attempt((int) $student->id);
        $catid = $this->start($attemptid);

        $this->assertSame((int) $student->id, (int) $DB->get_field('local_catquiz_attempts', 'userid', ['id' => $catid]));
    }

    /**
     * Cross collision (issues #95, #101): the activity attempt of B has the number of A's CAT attempt. B gets
     * a CAT attempt and a progress of their own; A's attempt and progress stay as they are, and A goes on
     * with their own progress.
     */
    public function test_the_attempt_id_of_one_person_is_the_cat_id_of_another(): void {
        global $DB;
        $this->resetAfterTest();
        $a = $this->getDataGenerator()->create_user();
        $b = $this->getDataGenerator()->create_user();

        $this->setUser($a);
        $attempta = $this->activity_attempt((int) $a->id);
        $progressa = progress::load($attempta, 'mod_adaptivequiz', 9, (object) []);
        $progressa->save();
        $cata = $DB->get_record('local_catquiz_attempts', ['id' => $progressa->get_catattemptid()], '*', MUST_EXIST);
        $this->assertNotEquals((int) $cata->id, $attempta, 'Without different numbers there is nothing to test.');
        $DB->import_record('adaptivequiz_attempt', (object) [
            'id' => $cata->id, 'instance' => 1, 'userid' => $b->id, 'uniqueid' => 0, 'attemptstate' => 'inprogress',
            'attemptstopcriteria' => '', 'questionsattempted' => 0, 'difficultysum' => 0, 'standarderror' => 1,
            'measure' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);

        $this->setUser($b);
        $progressb = progress::load((int) $cata->id, 'mod_adaptivequiz', 9, (object) []);
        $progressb->save();

        $catb = $DB->get_record('local_catquiz_attempts', ['id' => $progressb->get_catattemptid()], '*', MUST_EXIST);
        $this->assertSame((int) $cata->id, (int) $catb->attemptid, 'B files under their own activity attempt.');
        $this->assertSame((int) $b->id, (int) $catb->userid);
        $this->assertNotEquals((int) $cata->id, (int) $catb->id);
        $this->assertEquals($cata, $DB->get_record('local_catquiz_attempts', ['id' => $cata->id]), 'A is unchanged.');
        $this->assertSame((int) $a->id, (int) $DB->get_field('local_catquiz_progress', 'userid', ['attemptid' => $cata->id]));
        $this->assertSame((int) $b->id, (int) $DB->get_field('local_catquiz_progress', 'userid', ['attemptid' => $catb->id]));

        $this->setUser($a);
        \cache::make('local_catquiz', 'adaptivequizattempt')->purge();
        $this->assertEquals($progressa->get_id(), progress::load($attempta, 'mod_adaptivequiz', 9, (object) [])->get_id());
    }
}
