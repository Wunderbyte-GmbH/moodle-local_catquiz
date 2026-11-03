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

namespace local_catquiz;

use advanced_testcase;
use xmldb_key;
use xmldb_table;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/db/upgradelib.php');

/**
 * The upgrade that re-keys local_catquiz_progress works on existing data, on every database.
 *
 * PHPUnit installs the schema fresh from install.xml, so an upgrade step never meets a real row
 * unless a test puts one there. The first version of this step went out broken for exactly that
 * reason: it stopped on MySQL and MariaDB at the first installation that had data - a unique key
 * on attemptid rejected the intermediate values, and MySQL refuses a DELETE whose subquery reads
 * the same table. This test rebuilds the situation before the upgrade and runs it.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers ::local_catquiz_rekey_progress_attempts
 */
final class progress_rekey_upgrade_test extends advanced_testcase {
    /**
     * Files a CAT attempt for an attempt of the component.
     *
     * @param int $componentattemptid Id of the attempt of the component.
     * @return int The internal CAT attempt id.
     */
    private function cat_attempt(int $componentattemptid): int {
        global $DB;

        return (int) $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => 2, 'scaleid' => 1, 'contextid' => 1, 'courseid' => 1,
            'attemptid' => $componentattemptid, 'component' => 'mod_adaptivequiz',
            'instanceid' => 1, 'status' => 1, 'json' => '{}',
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * Writes a progress row the way the old runtime did: with the id of the component's attempt.
     *
     * @param int|null $componentattemptid What the old code stored in attemptid.
     * @return int Id of the progress row.
     */
    private function old_progress(?int $componentattemptid): int {
        global $DB;

        return (int) $DB->insert_record('local_catquiz_progress', (object) [
            'userid' => 2, 'component' => 'mod_adaptivequiz', 'attemptid' => $componentattemptid,
            'json' => '{}', 'quizsettings' => '{}',
        ]);
    }

    /**
     * Puts the table back into its shape before the upgrade: no unique key on attemptid.
     */
    private function drop_the_new_key(): void {
        global $DB;

        $DB->get_manager()->drop_key(
            new xmldb_table('local_catquiz_progress'),
            new xmldb_key('attemptid', XMLDB_KEY_FOREIGN_UNIQUE, ['attemptid'], 'local_catquiz_attempts', ['id'])
        );
    }

    /**
     * Existing rows are moved to the CAT attempt, orphans and duplicates are handled, and the key holds.
     */
    public function test_existing_rows_are_rekeyed(): void {
        global $DB;

        $this->resetAfterTest();
        $this->drop_the_new_key();

        // A row with a CAT attempt, two orphans, and a duplicate pair for the same attempt.
        $catid = $this->cat_attempt(50001);
        $kept = $this->old_progress(50001);
        $dupold = $this->old_progress(50002);
        $dupnew = $this->old_progress(50002);
        $dupcat = $this->cat_attempt(50002);
        $orphan1 = $this->old_progress(59998);
        $orphan2 = $this->old_progress(59999);

        // A test running during the upgrade: its attempt exists, its CAT attempt does not yet.
        $DB->import_record('adaptivequiz_attempt', (object) [
            'id' => 50003, 'instance' => 1, 'userid' => 2, 'uniqueid' => 9003,
            'attemptstate' => 'inprogress', 'attemptstopcriteria' => '', 'questionsattempted' => 2,
            'difficultysum' => 0, 'standarderror' => 1, 'measure' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $running = $this->old_progress(50003);

        // A finished attempt as the result page stored it: bare module name in the CAT attempt,
        // frankenstyle name in the progress. This is what every real installation holds.
        $barecat = (int) $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => 2, 'scaleid' => 1, 'contextid' => 1, 'courseid' => 1,
            'attemptid' => 50004, 'component' => 'adaptivequiz',
            'instanceid' => 1, 'status' => 0, 'json' => '{}',
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $bare = $this->old_progress(50004);

        $result = local_catquiz_rekey_progress_attempts();

        $this->assertEquals(
            $barecat,
            (int) $DB->get_field('local_catquiz_progress', 'attemptid', ['id' => $bare]),
            'A CAT attempt stored with the bare module name was not matched.'
        );

        $runningcat = $DB->get_field('local_catquiz_progress', 'attemptid', ['id' => $running]);
        $this->assertNotNull($runningcat, 'A test running during the upgrade lost its progress.');
        $this->assertEquals(
            2,
            (int) $DB->get_field('local_catquiz_attempts', 'status', ['id' => $runningcat]),
            'The running test must get a CAT attempt with running status.'
        );

        $this->assertEquals($catid, (int) $DB->get_field('local_catquiz_progress', 'attemptid', ['id' => $kept]));
        $this->assertFalse(
            $DB->record_exists('local_catquiz_progress', ['id' => $dupold]),
            'The older of two rows for the same CAT attempt must go.'
        );
        $this->assertEquals($dupcat, (int) $DB->get_field('local_catquiz_progress', 'attemptid', ['id' => $dupnew]));
        $this->assertNull($DB->get_field('local_catquiz_progress', 'attemptid', ['id' => $orphan1]));
        $this->assertNull(
            $DB->get_field('local_catquiz_progress', 'attemptid', ['id' => $orphan2]),
            'Several rows without a CAT attempt must coexist - that is what broke on MySQL with 0.'
        );

        $this->assertSame(['rekeyed' => 3, 'started' => 1, 'orphans' => 2, 'duplicates' => 1, 'mismatched' => 0], $result);
    }

    /**
     * Progress of one person is never filed under another person's CAT attempt or attempt (issue #95):
     * the row stays unassigned, is counted, and the other person's rows are untouched.
     */
    public function test_progress_of_another_person_is_not_rekeyed(): void {
        global $DB;

        $this->resetAfterTest();
        $this->drop_the_new_key();

        // CAT attempt of user 2; progress with the same pair, but of user 3.
        $catid = $this->cat_attempt(52001);
        $foreign = (int) $DB->insert_record('local_catquiz_progress', (object) [
            'userid' => 3, 'component' => 'mod_adaptivequiz', 'attemptid' => 52001, 'json' => '{}', 'quizsettings' => '{}',
        ]);
        // A running attempt of user 2; progress with its number, but of user 3.
        $DB->import_record('adaptivequiz_attempt', (object) [
            'id' => 52002, 'instance' => 1, 'userid' => 2, 'uniqueid' => 9004,
            'attemptstate' => 'inprogress', 'attemptstopcriteria' => '', 'questionsattempted' => 1,
            'difficultysum' => 0, 'standarderror' => 1, 'measure' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $foreignrunning = (int) $DB->insert_record('local_catquiz_progress', (object) [
            'userid' => 3, 'component' => 'mod_adaptivequiz', 'attemptid' => 52002, 'json' => '{}', 'quizsettings' => '{}',
        ]);
        $cats = $DB->count_records('local_catquiz_attempts');

        $result = local_catquiz_rekey_progress_attempts();

        $this->assertNull($DB->get_field('local_catquiz_progress', 'attemptid', ['id' => $foreign]));
        $this->assertNull($DB->get_field('local_catquiz_progress', 'attemptid', ['id' => $foreignrunning]));
        $this->assertSame(2, $result['mismatched']);
        $this->assertSame($cats, $DB->count_records('local_catquiz_attempts'), 'No CAT attempt was filed for the wrong person.');
        $this->assertSame(2, (int) $DB->get_field('local_catquiz_attempts', 'userid', ['id' => $catid]));
    }

    /**
     * The key is back afterwards and enforces one progress per CAT attempt.
     */
    public function test_the_unique_key_is_restored(): void {
        global $DB;

        $this->resetAfterTest();
        $this->drop_the_new_key();

        $catid = $this->cat_attempt(51001);
        $this->old_progress(51001);

        local_catquiz_rekey_progress_attempts();

        $this->expectException(\dml_exception::class);
        $DB->insert_record('local_catquiz_progress', (object) [
            'userid' => 2, 'component' => 'mod_adaptivequiz', 'attemptid' => $catid,
            'json' => '{}', 'quizsettings' => '{}',
        ]);
    }
}
