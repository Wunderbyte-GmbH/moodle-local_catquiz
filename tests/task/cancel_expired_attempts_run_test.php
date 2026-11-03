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
use local_catquiz\task\cancel_expired_attempts;

/**
 * The task that closes attempts past their time actually closes them, and says why (mod_adaptivequiz #20).
 *
 * The existing test only checks the arithmetic. This one runs the task: the attempt is completed
 * through the activity's own completion, with the code "timelimit" for the notice on the result page.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\task\cancel_expired_attempts
 */
final class cancel_expired_attempts_run_test extends advanced_testcase {
    /**
     * An attempt older than the default limit is closed; a fresh one stays open.
     */
    public function test_expired_attempt_is_closed_with_the_time_limit_code(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('maximum_attempt_duration_hours', 1, 'local_catquiz');

        $course = $this->getDataGenerator()->create_course();
        $adaptivequiz = $this->getDataGenerator()->create_module('adaptivequiz', [
            'course' => $course->id, 'attemptfeedbackeditor' => ['text' => '', 'format' => FORMAT_HTML],
        ]);
        $DB->set_field('adaptivequiz', 'catmodel', 'catquiz', ['id' => $adaptivequiz->id]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $attempts = [];
        foreach (['expired' => time() - 3 * HOURSECS, 'fresh' => time() - 60] as $name => $created) {
            $attempts[$name] = (int) $DB->insert_record('adaptivequiz_attempt', (object) [
                'instance' => $adaptivequiz->id, 'userid' => $user->id, 'uniqueid' => 7000 + count($attempts),
                'attemptstate' => 'inprogress', 'attemptstopcriteria' => '', 'questionsattempted' => 2,
                'difficultysum' => 0, 'standarderror' => 0.5, 'measure' => 0, 'timecreated' => $created, 'timemodified' => $created,
            ]);
        }

        $this->expectOutputRegex('/marked 1 as completed/');
        (new cancel_expired_attempts())->execute();

        $expired = $DB->get_record('adaptivequiz_attempt', ['id' => $attempts['expired']]);
        $this->assertSame('complete', $expired->attemptstate);
        $this->assertSame(get_string('attemptclosedbytimelimit', 'local_catquiz'), $expired->attemptstopcriteria);
        $this->assertNotEmpty($expired->timefinished);
        $this->assertSame('timelimit', $expired->stopcode, 'The result page explains the end with this code.');
        $this->assertSame('inprogress', $DB->get_field('adaptivequiz_attempt', 'attemptstate', ['id' => $attempts['fresh']]));
    }
}
