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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/tests/fixtures/concurrent_processes.php');

/**
 * Requests that really run at the same time file one CAT attempt and one progress (issues #95, #101).
 *
 * Several processes - each with a database connection of its own - start at the same instant and load
 * the progress of the same activity attempt, as a double click or two tabs do. The unique indexes on
 * local_catquiz_attempts.attemptid and local_catquiz_progress.attemptid decide who is first; everybody
 * else has to take that row over instead of failing with an error page. The work runs in forked
 * processes, not one after another: the races are real ones, against the real database.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\teststrategy\progress::load
 * @covers \local_catquiz\teststrategy\progress::save
 */
final class concurrent_get_or_create_test extends advanced_testcase {
    use \local_catquiz_concurrent_processes;

    /** @var int Processes per round. */
    private const PROCESSES = 8;

    /** @var int Rounds, each with an attempt of its own. */
    private const ROUNDS = 6;

    /**
     * An activity attempt of a person, as the activity files it.
     *
     * @param int $attemptid
     * @param int $userid
     */
    private function activity_attempt(int $attemptid, int $userid): void {
        global $DB;
        $DB->import_record('adaptivequiz_attempt', (object) [
            'id' => $attemptid, 'instance' => 1, 'userid' => $userid, 'uniqueid' => 0, 'attemptstate' => 'inprogress',
            'attemptstopcriteria' => '', 'questionsattempted' => 0, 'difficultysum' => 0, 'standarderror' => 1,
            'measure' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * The first load and save of an attempt in several requests at once: one CAT attempt, one progress, no
     * request fails, and every request works on the same rows.
     */
    public function test_one_cat_attempt_and_one_progress_under_concurrent_requests(): void {
        global $DB;
        $this->require_concurrent_processes();
        $this->resetAfterTest();
        // The processes have connections of their own: they see only what is committed, and what they write
        // is not undone by a rollback. So no test transaction - the slow reset afterwards takes it all away.
        $this->preventResetByRollback();
        $owner = $this->getDataGenerator()->create_user();
        // The requests are not the owner's: somebody else (a teacher, a cron task) touches the attempt first.
        $this->setAdminUser();

        for ($round = 0; $round < self::ROUNDS; $round++) {
            $attemptid = 71000 + $round;
            $this->activity_attempt($attemptid, (int) $owner->id);

            $answers = $this->at_the_same_time(self::PROCESSES, function () use ($attemptid) {
                $progress = progress::load($attemptid, 'mod_adaptivequiz', 9, (object) []);
                $progress->save();
                return [
                    'catattempt' => \local_catquiz\catquiz::get_cat_attempt_id($attemptid, 'mod_adaptivequiz'),
                    'progress' => (int) $GLOBALS['DB']->get_field(
                        'local_catquiz_progress',
                        'id',
                        ['attemptid' => \local_catquiz\catquiz::get_cat_attempt_id($attemptid, 'mod_adaptivequiz')]
                    ),
                ];
            });

            $errors = array_filter(array_column($answers, 'error'));
            $this->assertSame([], $errors, "Round $round: no request may fail.");
            $catattempts = $DB->get_records('local_catquiz_attempts', ['attemptid' => $attemptid]);
            $this->assertCount(1, $catattempts, "Round $round: one CAT attempt.");
            $catattempt = reset($catattempts);
            $this->assertSame((int) $owner->id, (int) $catattempt->userid, "Round $round: the owner's.");
            $progressrows = $DB->get_records('local_catquiz_progress', ['attemptid' => $catattempt->id]);
            $this->assertCount(1, $progressrows, "Round $round: one progress.");
            foreach ($answers as $answer) {
                $this->assertSame(
                    (int) $catattempt->id,
                    (int) $answer['ok']['catattempt'],
                    "Round $round: the same CAT attempt."
                );
                $this->assertSame((int) reset($progressrows)->id, $answer['ok']['progress'], "Round $round: the same progress.");
            }
        }
    }
}
