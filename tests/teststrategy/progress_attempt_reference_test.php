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
 * Progress references the CAT attempt, not the attempt of the component.
 *
 * local_catquiz_progress.attemptid is a foreign key to local_catquiz_attempts.id, but the runtime
 * used to store adaptivequiz_attempt.id - two primary keys of different tables that match only by
 * accident (issue #95). The ids are deliberately far apart here, so a mix-up cannot pass unnoticed.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\teststrategy\progress
 */
final class progress_attempt_reference_test extends advanced_testcase {
    /**
     * Files a CAT attempt whose own id differs from the id of the component's attempt.
     *
     * @param int $attemptid Id of the attempt of the component.
     * @param int $contextid The CAT context.
     * @return int The internal CAT attempt id.
     */
    private function make_cat_attempt(int $attemptid, int $contextid): int {
        global $DB, $USER;

        // The fixture may already have filed one; local_catquiz_attempts.attemptid is unique.
        $existing = $DB->get_field('local_catquiz_attempts', 'id', [
            'attemptid' => $attemptid,
            'component' => 'mod_adaptivequiz',
        ]);
        if ($existing) {
            return (int) $existing;
        }

        $now = time();

        return (int) $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => $USER->id,
            'scaleid' => 1,
            'contextid' => $contextid,
            'courseid' => 1,
            'attemptid' => $attemptid,
            'component' => 'mod_adaptivequiz',
            'instanceid' => 1,
            'status' => 1,
            'json' => '{}',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * What is stored is the CAT attempt, and the progress is found again through it.
     */
    public function test_progress_is_filed_under_the_cat_attempt(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $componentattemptid = 77001;
        $contextid = 9;
        $catattemptid = $this->make_cat_attempt($componentattemptid, $contextid);

        // The two ids must differ, otherwise the test could not tell them apart.
        $this->assertNotEquals($componentattemptid, $catattemptid);

        $progress = progress::load($componentattemptid, 'mod_adaptivequiz', $contextid, (object) []);
        $progress->save();

        $this->assertTrue(
            $DB->record_exists('local_catquiz_progress', ['attemptid' => $catattemptid]),
            'The progress is not filed under the CAT attempt.'
        );
        $this->assertFalse(
            $DB->record_exists('local_catquiz_progress', ['attemptid' => $componentattemptid]),
            'The progress is filed under the id of the component instead of the CAT attempt.'
        );

        // And it is found again from the caller's point of view, which is the external id.
        \cache::make('local_catquiz', 'adaptivequizattempt')->purge();
        $reloaded = progress::load($componentattemptid, 'mod_adaptivequiz', $contextid);

        $this->assertEquals(
            $progress->get_id(),
            $reloaded->get_id(),
            'The stored progress was not found again through the attempt of the component.'
        );
    }

    /**
     * Load, save, delete and the cache keys work with the CAT attempt only (issue #95, re-audit 27.10.2026): with the
     * two ids different, and a CAT attempt of somebody else that has the number of the host attempt as its id, the
     * progress of that other attempt is neither read, written nor deleted.
     */
    public function test_crud_and_cache_keys_use_the_cat_attempt(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $componentattemptid = 77005;
        $contextid = 9;
        $catattemptid = $this->make_cat_attempt($componentattemptid, $contextid);
        $this->assertNotEquals($componentattemptid, $catattemptid);
        // A CAT attempt of another person whose id is the number of the host attempt, with a progress.
        $other = $this->getDataGenerator()->create_user();
        $DB->import_record('local_catquiz_attempts', (object) [
            'id' => $componentattemptid, 'userid' => $other->id, 'scaleid' => 1, 'contextid' => $contextid,
            'courseid' => 1, 'attemptid' => 99999, 'component' => 'mod_adaptivequiz', 'instanceid' => 1, 'status' => 1,
            'json' => '{}', 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $foreignid = $DB->insert_record('local_catquiz_progress', (object) [
            'attemptid' => $componentattemptid, 'userid' => $other->id, 'component' => 'mod_adaptivequiz',
            'json' => '{"foreign":true}', 'quizsettings' => '{}', 'timemodified' => time(),
        ]);
        $foreign = $DB->get_record('local_catquiz_progress', ['id' => $foreignid]);
        $cache = \cache::make('local_catquiz', 'adaptivequizattempt');
        $key = new \ReflectionMethod(progress::class, 'get_cache_key');
        $key->setAccessible(true);
        $catkey = $key->invoke(null, $catattemptid, $componentattemptid, 'mod_adaptivequiz');
        $prekey = $key->invoke(null, null, $componentattemptid, 'mod_adaptivequiz');
        $this->assertSame('progress_cat_' . $catattemptid, $catkey);

        $progress = progress::load($componentattemptid, 'mod_adaptivequiz', $contextid, (object) []);
        $this->assertSame($catattemptid, $progress->get_catattemptid());
        $progress->save();
        $this->assertTrue($DB->record_exists('local_catquiz_progress', ['attemptid' => $catattemptid]));
        $this->assertInstanceOf(progress::class, $cache->get($catkey), 'Cached under the CAT attempt.');
        $this->assertFalse($cache->get($prekey), 'Nothing cached under the number of the host attempt.');

        $cache->purge();
        $this->assertEquals($progress->get_id(), progress::load($componentattemptid, 'mod_adaptivequiz', $contextid)->get_id());

        progress::delete($componentattemptid, 'mod_adaptivequiz');
        $this->assertFalse($DB->record_exists('local_catquiz_progress', ['attemptid' => $catattemptid]));
        $this->assertFalse($cache->get($catkey));
        $this->assertEquals(
            $foreign,
            $DB->get_record('local_catquiz_progress', ['id' => $foreignid]),
            'The progress of the CAT attempt with the number of the host attempt was touched.'
        );
    }

    /**
     * Progress of another user is not taken over.
     *
     * Issue #96: the record was accepted on the strength of a numerically matching id alone. With
     * orphaned or re-used rows that carries another person's answers into a running attempt.
     */
    public function test_progress_of_another_user_is_not_taken_over(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $contextid = 9;
        $catattemptid = $this->make_cat_attempt(77003, $contextid);

        $progress = progress::load(77003, 'mod_adaptivequiz', $contextid, (object) []);
        $progress->save();

        // The row now belongs to somebody else - an orphan from an earlier installation, say.
        $stranger = $this->getDataGenerator()->create_user();
        $DB->set_field('local_catquiz_progress', 'userid', $stranger->id, ['attemptid' => $catattemptid]);
        \cache::make('local_catquiz', 'adaptivequizattempt')->purge();

        // Issue #96: fail closed - a controlled error, nothing taken over.
        try {
            progress::load(77003, 'mod_adaptivequiz', $contextid, (object) []);
            $this->fail('The progress of another user was taken over for the current one.');
        } catch (\moodle_exception $e) {
            $this->assertSame('progressintegrityerror', $e->errorcode);
        }
        $this->assertEquals(
            $stranger->id,
            $DB->get_field('local_catquiz_progress', 'userid', ['attemptid' => $catattemptid]),
            'The foreign row must be left alone, not reassigned.'
        );
    }

    /**
     * The same number in another component does not reach this progress.
     */
    public function test_another_component_does_not_reach_this_progress(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $contextid = 9;
        $this->make_cat_attempt(77002, $contextid);

        $progress = progress::load(77002, 'mod_adaptivequiz', $contextid, (object) []);
        $progress->save();

        \cache::make('local_catquiz', 'adaptivequizattempt')->purge();
        $other = progress::load(77002, 'mod_quiz', $contextid, (object) []);
        // The number is taken by the other component; that is reported, not silently worked around.
        $this->assertDebuggingCalled();

        $this->assertNull(
            $other->get_id(),
            'Progress of one component was handed to another under the same number.'
        );
    }
}
