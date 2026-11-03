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
use local_catquiz\event\progress_integrity_failed;
use local_catquiz\local\attempt\progress_integrity;
use moodle_exception;

/**
 * A stored progress is used only by the CAT attempt it belongs to (issue #96).
 *
 * The audit behind the issue found progress rows of one person under the attempt id of another.
 * Taken over, such a row changes the other person's questions and estimates. Loading now fails
 * closed: a generic error, an event for operations, and the row stays as it is.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\local\attempt\progress_integrity
 */
final class progress_integrity_test extends advanced_testcase {
    /** @var int The attempt of the component. */
    private const ATTEMPT = 61008;

    /**
     * Files an attempt of the owner, its CAT attempt and a saved progress.
     *
     * @return array{0: \stdClass, 1: \stdClass} The owner and the stored progress row.
     */
    private function saved_progress(): array {
        global $DB;

        $owner = $this->getDataGenerator()->create_user();
        $DB->import_record('adaptivequiz_attempt', (object) [
            'id' => self::ATTEMPT, 'instance' => 1, 'userid' => $owner->id, 'uniqueid' => 0, 'attemptstate' => 'inprogress',
            'attemptstopcriteria' => '', 'questionsattempted' => 0, 'difficultysum' => 0, 'standarderror' => 1,
            'measure' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $this->setUser($owner);
        progress::load(self::ATTEMPT, 'mod_adaptivequiz', 9, (object) [])->save();
        \cache::make('local_catquiz', 'adaptivequizattempt')->purge();

        $catattemptid = \local_catquiz\catquiz::get_cat_attempt_id(self::ATTEMPT, 'mod_adaptivequiz');
        return [$owner, $DB->get_record('local_catquiz_progress', ['attemptid' => $catattemptid], '*', MUST_EXIST)];
    }

    /**
     * Loads the attempt again and returns the error code, or null if it loaded.
     *
     * @return string|null
     */
    private function load_error(): ?string {
        try {
            progress::load(self::ATTEMPT, 'mod_adaptivequiz', 9, (object) []);
            return null;
        } catch (moodle_exception $e) {
            return $e->errorcode;
        }
    }

    /**
     * The owner's own progress is resumed.
     */
    public function test_own_progress_is_resumed(): void {
        $this->resetAfterTest();
        [, $row] = $this->saved_progress();

        $progress = progress::load(self::ATTEMPT, 'mod_adaptivequiz', 9, (object) []);

        $this->assertEquals($row->id, $progress->get_id());
    }

    /**
     * Progress of another person under this attempt is refused, logged and left untouched.
     */
    public function test_progress_of_another_person_is_refused(): void {
        global $DB;

        $this->resetAfterTest();
        [$owner, $row] = $this->saved_progress();
        $stranger = $this->getDataGenerator()->create_user();
        $DB->set_field('local_catquiz_progress', 'userid', $stranger->id, ['id' => $row->id]);
        $before = $DB->get_record('local_catquiz_progress', ['id' => $row->id]);

        $sink = $this->redirectEvents();
        $this->assertSame('progressintegrityerror', $this->load_error(), 'A foreign progress was taken over.');
        $events = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof progress_integrity_failed));
        $sink->close();

        $this->assertCount(1, $events);
        $this->assertSame(progress_integrity::USER_MISMATCH, $events[0]->other['reason']);
        $this->assertEquals($owner->id, $events[0]->other['expecteduserid']);
        $this->assertEquals($stranger->id, $events[0]->other['founduserid']);
        $this->assertEquals($before, $DB->get_record('local_catquiz_progress', ['id' => $row->id]), 'The row was changed.');
        $this->assertSame(1, $DB->count_records('local_catquiz_progress'), 'A second progress was created.');
    }

    /**
     * Progress of another component under this attempt is refused.
     */
    public function test_progress_of_another_component_is_refused(): void {
        global $DB;

        $this->resetAfterTest();
        [, $row] = $this->saved_progress();
        $DB->set_field('local_catquiz_progress', 'component', 'mod_quiz', ['id' => $row->id]);

        $this->assertSame('progressintegrityerror', $this->load_error());
    }

    /**
     * Both spellings of the component are the same component.
     */
    public function test_the_other_spelling_of_the_component_is_accepted(): void {
        global $DB;

        $this->resetAfterTest();
        [, $row] = $this->saved_progress();
        $DB->set_field('local_catquiz_progress', 'component', 'adaptivequiz', ['id' => $row->id]);

        $this->assertNull($this->load_error());
    }

    /**
     * A progress whose CAT attempt is gone is not handed to a reader.
     */
    public function test_progress_without_its_cat_attempt_is_not_read(): void {
        global $DB;

        $this->resetAfterTest();
        [, $row] = $this->saved_progress();
        $DB->delete_records('local_catquiz_attempts', ['id' => $row->attemptid]);

        $sink = $this->redirectEvents();
        $this->assertNull(progress::load_for_reading((int) $row->attemptid));
        $events = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof progress_integrity_failed));
        $sink->close();

        $this->assertSame(progress_integrity::NO_CAT_ATTEMPT, $events[0]->other['reason'] ?? null);
    }

    /**
     * A row that belongs to another person by now is not overwritten with the state the session still holds
     * (issue #96, re-audit 27.10.2026): the save stops with the integrity message, the row stays as it is, the
     * cached state is dropped. Before, the save wrote the session's state over the row and set its owner back.
     */
    public function test_a_foreign_row_is_not_overwritten_from_the_cache(): void {
        global $DB;

        $this->resetAfterTest();
        [$owner, $row] = $this->saved_progress();
        // The session holds the owner's progress.
        $progress = progress::load(self::ATTEMPT, 'mod_adaptivequiz', 9, (object) []);
        $stranger = $this->getDataGenerator()->create_user();
        $DB->set_field('local_catquiz_progress', 'userid', $stranger->id, ['id' => $row->id]);
        $before = $DB->get_record('local_catquiz_progress', ['id' => $row->id]);

        $sink = $this->redirectEvents();
        try {
            $progress->save();
            $this->fail('The foreign row was overwritten.');
        } catch (moodle_exception $e) {
            $this->assertSame('progressintegrityerror', $e->errorcode);
        }
        $events = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof progress_integrity_failed));
        $sink->close();

        $this->assertCount(1, $events);
        $this->assertSame(progress_integrity::USER_MISMATCH, $events[0]->other['reason']);
        $this->assertEquals($before, $DB->get_record('local_catquiz_progress', ['id' => $row->id]), 'The row was changed.');
        // The cached state is gone: the next load reads the row and refuses as well.
        $this->assertSame('progressintegrityerror', $this->load_error());
        // The owner's own saves go on as before.
        $DB->set_field('local_catquiz_progress', 'userid', $owner->id, ['id' => $row->id]);
        \cache::make('local_catquiz', 'adaptivequizattempt')->purge();
        progress::load(self::ATTEMPT, 'mod_adaptivequiz', 9, (object) [])->save();
        $this->assertEquals($owner->id, $DB->get_field('local_catquiz_progress', 'userid', ['id' => $row->id]));
    }

    /**
     * The first save of a progress meets a row that a request in parallel inserted for the same CAT attempt: that row
     * is taken over only if it is the same person's (issue #95 insert race, issue #96).
     */
    public function test_a_foreign_row_is_not_taken_over_in_the_insert_race(): void {
        global $DB;

        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $DB->import_record('adaptivequiz_attempt', (object) [
            'id' => self::ATTEMPT, 'instance' => 1, 'userid' => $owner->id, 'uniqueid' => 0, 'attemptstate' => 'inprogress',
            'attemptstopcriteria' => '', 'questionsattempted' => 0, 'difficultysum' => 0, 'standarderror' => 1,
            'measure' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $this->setUser($owner);
        // Not saved yet: the progress of this request has no row.
        $progress = progress::load(self::ATTEMPT, 'mod_adaptivequiz', 9, (object) []);
        $this->assertNull($progress->get_id());
        $catattemptid = \local_catquiz\catquiz::get_cat_attempt_id(self::ATTEMPT, 'mod_adaptivequiz');
        $stranger = $this->getDataGenerator()->create_user();
        $foreignid = $DB->insert_record('local_catquiz_progress', (object) [
            'attemptid' => $catattemptid, 'userid' => $stranger->id, 'component' => 'mod_adaptivequiz', 'json' => '{}',
            'quizsettings' => '{}', 'timemodified' => 1000,
        ]);
        $before = $DB->get_record('local_catquiz_progress', ['id' => $foreignid]);

        $sink = $this->redirectEvents();
        try {
            $progress->save();
            $this->fail('The foreign row was taken over.');
        } catch (moodle_exception $e) {
            $this->assertSame('progressintegrityerror', $e->errorcode);
        }
        $events = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof progress_integrity_failed));
        $sink->close();

        $this->assertCount(1, $events);
        $this->assertEquals($before, $DB->get_record('local_catquiz_progress', ['id' => $foreignid]), 'The row was changed.');
        $this->assertSame(1, $DB->count_records('local_catquiz_progress'));
    }

    /**
     * A cache entry naming another person is not used.
     */
    public function test_a_foreign_cache_entry_is_not_used(): void {
        $this->resetAfterTest();
        [$owner, $row] = $this->saved_progress();
        $stranger = $this->getDataGenerator()->create_user();

        // Plant a progress of someone else under the owner's cache key.
        $foreign = progress::load(self::ATTEMPT, 'mod_adaptivequiz', 9, (object) []);
        $userid = new \ReflectionProperty($foreign, 'userid');
        $userid->setAccessible(true);
        $userid->setValue($foreign, (int) $stranger->id);
        $key = new \ReflectionMethod(progress::class, 'get_cache_key');
        $key->setAccessible(true);
        $cachekey = $key->invoke(null, (int) $row->attemptid, self::ATTEMPT, 'mod_adaptivequiz');
        \cache::make('local_catquiz', 'adaptivequizattempt')->set($cachekey, $foreign);

        $progress = progress::load(self::ATTEMPT, 'mod_adaptivequiz', 9, (object) []);

        $this->assertEquals($owner->id, $progress->get_userid(), 'The foreign cache entry was used.');
    }

    /**
     * Two components with the same attempt id keep their own cache entries (issue #95): the progress of
     * the CAT attempt under the key of the CAT attempt, the other one - without a CAT attempt, the number
     * is taken - under the key of its component. Neither pushes the other out.
     */
    public function test_the_same_attempt_id_of_two_components_shares_no_cache_entry(): void {
        $this->resetAfterTest();
        [, $row] = $this->saved_progress();
        $cache = \cache::make('local_catquiz', 'adaptivequizattempt');
        $key = new \ReflectionMethod(progress::class, 'get_cache_key');
        $key->setAccessible(true);

        $own = progress::load(self::ATTEMPT, 'mod_adaptivequiz', 9, (object) []);
        $own->save();
        $other = progress::load(self::ATTEMPT, 'mod_other', 9, (object) []);
        $this->assertDebuggingCalled();
        $this->assertNull($other->get_catattemptid());
        $other->save();

        $ownkey = $key->invoke(null, (int) $row->attemptid, self::ATTEMPT, 'mod_adaptivequiz');
        $otherkey = $key->invoke(null, null, self::ATTEMPT, 'mod_other');
        $this->assertNotSame($ownkey, $otherkey);
        $this->assertSame('mod_adaptivequiz', $cache->get($ownkey)->get_component());
        $this->assertSame('mod_other', $cache->get($otherkey)->get_component());

        // A progress cached before its CAT attempt was filed moves to the key of the CAT attempt.
        $cache->delete($ownkey);
        $cache->set($key->invoke(null, null, self::ATTEMPT, 'mod_adaptivequiz'), $own);
        progress::load(self::ATTEMPT, 'mod_adaptivequiz', 9, (object) []);
        $this->assertFalse($cache->get($key->invoke(null, null, self::ATTEMPT, 'mod_adaptivequiz')));
        $this->assertSame((int) $row->attemptid, $cache->get($ownkey)->get_catattemptid());

        // Deleted, the progress is gone under both keys.
        $cache->set($key->invoke(null, null, self::ATTEMPT, 'mod_adaptivequiz'), $own);
        progress::delete(self::ATTEMPT, 'mod_adaptivequiz');
        $this->assertFalse($cache->get($ownkey));
        $this->assertFalse($cache->get($key->invoke(null, null, self::ATTEMPT, 'mod_adaptivequiz')));
        $this->assertSame('mod_other', $cache->get($otherkey)->get_component(), 'The other component keeps its entry.');
    }

    /**
     * The health check lists every kind of mismatch, and changes nothing.
     */
    public function test_the_health_check_lists_each_kind(): void {
        global $DB;

        $this->resetAfterTest();
        [, $row] = $this->saved_progress();
        $stranger = $this->getDataGenerator()->create_user();
        $base = (array) $row;
        unset($base['id']);
        $orphan = $DB->insert_record('local_catquiz_progress', (object) (['attemptid' => 990001] + $base));
        $DB->set_field('local_catquiz_progress', 'userid', $stranger->id, ['id' => $row->id]);

        $found = progress_integrity::find_inconsistencies();

        $this->assertSame([(int) $orphan], $found[progress_integrity::NO_CAT_ATTEMPT]);
        $this->assertSame([(int) $row->id], $found[progress_integrity::USER_MISMATCH]);
        $this->assertSame([], $found[progress_integrity::COMPONENT_MISMATCH]);
        $this->assertSame(2, $DB->count_records('local_catquiz_progress'));
    }
}
