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
use cache;
use local_catquiz\catquiz;
use local_catquiz\output\attemptfeedback;

/**
 * A reader of a progress creates nothing (issue #149).
 *
 * A reader brings no test settings: the result page, the feedback, a monitor. With neither a cached nor a stored
 * progress, progress::load() went on to create one with settings null - a TypeError on the result page. Now a
 * reader gets the progress that exists, or progress_missing_exception; it creates no progress, no CAT attempt and
 * no cache entry. The result page says that there is no result.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\teststrategy\progress::load
 * @covers \local_catquiz\teststrategy\progress::load_for_reading
 * @covers \local_catquiz\teststrategy\progress_missing_exception
 * @covers \local_catquiz\output\attemptfeedback::export_for_template
 */
final class progress_read_contract_test extends advanced_testcase {
    /**
     * An attempt of the component, of a person.
     *
     * @param int $id
     * @param int $userid
     */
    private function host_attempt(int $id, int $userid): void {
        global $DB;
        $DB->import_record('adaptivequiz_attempt', (object) [
            'id' => $id, 'instance' => 1, 'userid' => $userid, 'uniqueid' => 0, 'attemptstate' => 'inprogress',
            'attemptstopcriteria' => '', 'questionsattempted' => 0, 'difficultysum' => 0, 'standarderror' => 1,
            'measure' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * What a reader may not have created: rows of progress and CAT attempts, entries in the cache.
     *
     * @param int $adaptiveattemptid
     * @return array
     */
    private function footprint(int $adaptiveattemptid): array {
        global $DB;
        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        $catattemptid = catquiz::get_cat_attempt_id($adaptiveattemptid, 'mod_adaptivequiz');
        $key = new \ReflectionMethod(progress::class, 'get_cache_key');
        return [
            'progress' => $DB->count_records('local_catquiz_progress'),
            'catattempts' => $DB->count_records('local_catquiz_attempts'),
            'cachedbyhost' => $cache->get($key->invoke(null, null, $adaptiveattemptid, 'mod_adaptivequiz')) !== false,
            'cachedbycat' => $catattemptid !== null
                && $cache->get($key->invoke(null, $catattemptid, $adaptiveattemptid, 'mod_adaptivequiz')) !== false,
        ];
    }

    /**
     * Empty database and cache: the reader gets the exception and leaves nothing behind - also after a new login.
     */
    public function test_nothing_stored_nothing_created(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->host_attempt(71001, (int) $user->id);
        $this->setUser($user);
        $before = $this->footprint(71001);

        try {
            progress::load(71001, 'mod_adaptivequiz', 9);
            $this->fail('A progress without settings was created.');
        } catch (progress_missing_exception $e) {
            $this->assertSame('progressmissing', $e->errorcode);
        }
        $this->assertSame($before, $this->footprint(71001), 'Nothing created.');

        // A new login after the cache was purged: the same.
        cache::make('local_catquiz', 'adaptivequizattempt')->purge();
        $this->setUser($user);
        $this->expectException(progress_missing_exception::class);
        progress::load(71001, 'mod_adaptivequiz', 9);
    }

    /**
     * Only in the database: the reader gets it and does not write the cache; a writer does.
     */
    public function test_stored_progress_is_read_without_writing_the_cache(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->host_attempt(71002, (int) $user->id);
        $this->setUser($user);
        $saved = progress::load(71002, 'mod_adaptivequiz', 9, (object) ['catquiz_catscales' => 1]);
        $saved->save();
        cache::make('local_catquiz', 'adaptivequizattempt')->purge();
        $before = $this->footprint(71002);

        $read = progress::load(71002, 'mod_adaptivequiz', 9);
        $this->assertSame($saved->get_id(), $read->get_id());
        $this->assertSame($before, $this->footprint(71002), 'The reader wrote nothing, not even the cache.');
        $catattemptid = (int) catquiz::get_cat_attempt_id(71002, 'mod_adaptivequiz');
        $this->assertSame($saved->get_id(), progress::load_for_reading($catattemptid)->get_id());
        $this->assertSame($before, $this->footprint(71002));
    }

    /**
     * CAT and host ids differ, two attempts of the same person run side by side: each reader gets the progress
     * of its own attempt, by the CAT attempt filed for it - not by a number that happens to match.
     */
    public function test_two_attempts_and_separate_id_spaces(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        // CAT attempt ids that are not the host's: occupy a few first.
        for ($i = 0; $i < 3; $i++) {
            $DB->insert_record('local_catquiz_attempts', (object) [
                'userid' => 2, 'scaleid' => 1, 'contextid' => 9, 'courseid' => 1, 'attemptid' => 900 + $i,
                'component' => 'mod_quiz', 'instanceid' => 1, 'status' => 0, 'json' => '{}',
                'timecreated' => time(), 'timemodified' => time(),
            ]);
        }
        $ids = [];
        foreach ([71003, 71004] as $hostid) {
            $this->host_attempt($hostid, (int) $user->id);
            $progress = progress::load($hostid, 'mod_adaptivequiz', 9, (object) ['catquiz_catscales' => $hostid]);
            $progress->save();
            $ids[$hostid] = $progress->get_id();
            $this->assertNotSame($hostid, (int) catquiz::get_cat_attempt_id($hostid, 'mod_adaptivequiz'));
        }
        cache::make('local_catquiz', 'adaptivequizattempt')->purge();

        foreach ($ids as $hostid => $id) {
            $read = progress::load($hostid, 'mod_adaptivequiz', 9);
            $this->assertSame($id, $read->get_id(), "Attempt $hostid.");
            $this->assertEquals($hostid, $read->get_quiz_settings()->catquiz_catscales, 'Its own settings.');
        }
    }

    /**
     * A progress of another person under the attempt: the reader gets none, the writer is refused.
     */
    public function test_inconsistent_owner(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->host_attempt(71005, (int) $user->id);
        $this->setUser($user);
        progress::load(71005, 'mod_adaptivequiz', 9, (object) [])->save();
        cache::make('local_catquiz', 'adaptivequizattempt')->purge();
        $DB->set_field('local_catquiz_progress', 'userid', $other->id, []);

        $catattemptid = (int) catquiz::get_cat_attempt_id(71005, 'mod_adaptivequiz');
        $this->assertNull(progress::load_for_reading($catattemptid));
        $this->expectException(\moodle_exception::class);
        progress::load(71005, 'mod_adaptivequiz', 9);
    }

    /**
     * The result page of an attempt without progress says that there is no result - no TypeError, no
     * invented result, and nothing created.
     */
    public function test_the_result_page_says_there_is_no_result(): void {
        global $PAGE, $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('adaptivequiz', ['course' => $course->id]);
        $root = (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'name' => 'Root 149', 'parentid' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $DB->insert_record('local_catquiz_tests', (object) [
            'componentid' => $quiz->id, 'component' => 'mod_adaptivequiz', 'catscaleid' => $root, 'courseid' => $course->id,
            'name' => 'T149', 'json' => json_encode(['catquiz_selectteststrategy' => 1, 'catquiz_catscales' => $root]),
            'status' => 2, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $attemptid = (int) $DB->insert_record('adaptivequiz_attempt', (object) [
            'instance' => $quiz->id, 'userid' => 2, 'uniqueid' => 0, 'attemptstate' => 'complete',
            'attemptstopcriteria' => '', 'questionsattempted' => 3, 'difficultysum' => 0, 'standarderror' => 1,
            'measure' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $before = $this->footprint($attemptid);

        $output = $PAGE->get_renderer('core');
        $data = (new attemptfeedback($attemptid, 0, null, (int) $course->id))->export_for_template($output);

        $this->assertSame(get_string('noresultforattempt', 'local_catquiz'), $data['noresult'] ?? null);
        $this->assertArrayNotHasKey('feedback', $data, 'No invented result.');
        $this->assertSame($before, $this->footprint($attemptid));
        $html = $output->render_from_template('local_catquiz/attemptfeedback', $data);
        $this->assertStringContainsString(get_string('noresultforattempt', 'local_catquiz'), $html);
    }

    /**
     * Cache miss with stored progress while two persons have attempts running side by side (issue #149): a writer
     * that comes back without cache continues its own stored progress - no new progress, no new CAT attempt - and
     * saving one never touches the other.
     */
    public function test_cache_miss_with_parallel_attempts_of_two_persons(): void {
        global $DB;
        $this->resetAfterTest();
        $alice = $this->getDataGenerator()->create_user();
        $bob = $this->getDataGenerator()->create_user();
        $runs = [71010 => $alice, 71011 => $bob];
        $ids = [];
        foreach ($runs as $hostid => $user) {
            $this->host_attempt($hostid, (int) $user->id);
            $this->setUser($user);
            $progress = progress::load($hostid, 'mod_adaptivequiz', 9, (object) ['catquiz_catscales' => $hostid]);
            $progress->save();
            $ids[$hostid] = $progress->get_id();
        }
        $counts = [$DB->count_records('local_catquiz_progress'), $DB->count_records('local_catquiz_attempts')];

        // Both come back after the cache was lost, one after the other within the same moment.
        cache::make('local_catquiz', 'adaptivequizattempt')->purge();
        $loaded = [];
        foreach ($runs as $hostid => $user) {
            $this->setUser($user);
            $loaded[$hostid] = progress::load($hostid, 'mod_adaptivequiz', 9, (object) ['catquiz_catscales' => 0]);
            $this->assertSame($ids[$hostid], $loaded[$hostid]->get_id(), "Attempt $hostid continues its own progress.");
            $this->assertEquals($hostid, $loaded[$hostid]->get_quiz_settings()->catquiz_catscales, 'The stored settings.');
        }
        $this->assertSame(
            $counts,
            [$DB->count_records('local_catquiz_progress'), $DB->count_records('local_catquiz_attempts')],
            'Nothing created a second time.'
        );

        // Alice saves; Bob's row stays as it was. Then Bob saves; Alice's stays.
        $bobrow = $DB->get_record('local_catquiz_progress', ['id' => $ids[71011]]);
        $this->setUser($alice);
        $loaded[71010]->set_ability(1.5, 99);
        $loaded[71010]->save();
        $this->assertEquals($bobrow, $DB->get_record('local_catquiz_progress', ['id' => $ids[71011]]));
        $alicerow = $DB->get_record('local_catquiz_progress', ['id' => $ids[71010]]);
        $this->setUser($bob);
        $loaded[71011]->save();
        $this->assertEquals($alicerow, $DB->get_record('local_catquiz_progress', ['id' => $ids[71010]]));
        foreach ($runs as $hostid => $user) {
            $row = $DB->get_record('local_catquiz_progress', ['id' => $ids[$hostid]]);
            $this->assertEquals($user->id, $row->userid);
            $this->assertEquals($hostid, json_decode($row->quizsettings)->catquiz_catscales);
        }
    }

    /**
     * A progress cached before its CAT attempt was filed: the reader takes it and leaves the cache as it is; the
     * writer moves it to the key of the CAT attempt (re-audit 28.10.2026).
     */
    public function test_a_reader_does_not_move_a_cached_progress(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->host_attempt(71013, (int) $user->id);
        $this->setUser($user);
        $progress = progress::load(71013, 'mod_adaptivequiz', 9, (object) ['catquiz_catscales' => 1]);
        $progress->save();
        $catattemptid = (int) catquiz::get_cat_attempt_id(71013, 'mod_adaptivequiz');
        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        $key = new \ReflectionMethod(progress::class, 'get_cache_key');
        $prekey = $key->invoke(null, null, 71013, 'mod_adaptivequiz');
        $catkey = $key->invoke(null, $catattemptid, 71013, 'mod_adaptivequiz');
        $cached = $cache->get($catkey);
        $this->assertInstanceOf(progress::class, $cached);
        $cache->delete($catkey);
        $cache->set($prekey, $cached);
        $before = $this->footprint(71013);
        $this->assertSame(['progress' => 1, 'catattempts' => 1, 'cachedbyhost' => true, 'cachedbycat' => false], $before);

        $read = progress::load(71013, 'mod_adaptivequiz', 9);
        $this->assertSame($progress->get_id(), $read->get_id());
        $this->assertSame($before, $this->footprint(71013), 'The reader moved nothing in the cache.');

        progress::load(71013, 'mod_adaptivequiz', 9, (object) ['catquiz_catscales' => 1]);
        $this->assertSame(
            ['progress' => 1, 'catattempts' => 1, 'cachedbyhost' => false, 'cachedbycat' => true],
            $this->footprint(71013),
            'The writer moved it.'
        );
    }

    /**
     * A progress stored before the settings were kept with it: the reader takes the settings of its CAT attempt
     * for this request and writes nothing; only a writer stores them (issue #149).
     */
    public function test_an_old_progress_is_read_without_writing_it(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->host_attempt(71012, (int) $user->id);
        $this->setUser($user);
        $progress = progress::load(71012, 'mod_adaptivequiz', 9, (object) ['catquiz_catscales' => 1]);
        $progress->save();
        $catattemptid = (int) catquiz::get_cat_attempt_id(71012, 'mod_adaptivequiz');
        $DB->set_field(
            'local_catquiz_attempts',
            'json',
            json_encode(['quizsettings' => ['catquiz_catscales' => 7]]),
            ['id' => $catattemptid]
        );
        $DB->set_field('local_catquiz_progress', 'quizsettings', null, ['id' => $progress->get_id()]);
        $DB->set_field('local_catquiz_progress', 'timemodified', 1000, ['id' => $progress->get_id()]);
        cache::make('local_catquiz', 'adaptivequizattempt')->purge();
        $before = $DB->get_record('local_catquiz_progress', ['id' => $progress->get_id()]);

        $read = progress::load(71012, 'mod_adaptivequiz', 9);
        $this->assertEquals(7, $read->get_quiz_settings()->catquiz_catscales, 'The settings of its CAT attempt.');
        $this->assertEquals(
            $before,
            $DB->get_record('local_catquiz_progress', ['id' => $progress->get_id()]),
            'The reader wrote nothing.'
        );
        $this->assertEquals($before->id, progress::load_for_reading($catattemptid)->get_id());
        $this->assertEquals($before, $DB->get_record('local_catquiz_progress', ['id' => $progress->get_id()]));

        // A writer stores them, so that the fallback is needed only once.
        progress::load(71012, 'mod_adaptivequiz', 9, (object) ['catquiz_catscales' => 7]);
        $stored = $DB->get_field('local_catquiz_progress', 'quizsettings', ['id' => $progress->get_id()]);
        $this->assertEquals(7, json_decode($stored)->catquiz_catscales);
    }
}
