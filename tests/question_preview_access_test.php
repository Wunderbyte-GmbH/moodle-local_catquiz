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
 * Participants may preview the questions of their own attempt, and no others.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;

/**
 * The preview endpoint has two doors, and both have to be checked.
 *
 * Managers may look at any question. Participants may look at the questions they were
 * actually asked - the question id comes from the client, so without an object check
 * any authenticated user could read the text of any question in the installation by
 * guessing ids.
 *
 * The chain that establishes ownership runs local_catquiz_attempts.attemptid ->
 * adaptivequiz_attempt.id -> uniqueid -> question_attempts.questionusageid. It is
 * built here rather than assumed, because the first link is not what its name
 * suggests: attemptid is the activity's attempt id, not the question usage id.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\external\get_question_preview
 */
final class question_preview_access_test extends advanced_testcase {
    /**
     * Builds an attempt of $userid that contains $questionid.
     *
     * @param int $userid
     * @param int $questionid
     * @return void
     */
    private function make_attempt_with_question(int $userid, int $questionid): void {
        global $DB;

        $now = time();
        $usageid = (int) $DB->insert_record('question_usages', (object) [
            'contextid' => \context_system::instance()->id,
            'component' => 'mod_adaptivequiz',
            'preferredbehaviour' => 'deferredfeedback',
        ]);

        $DB->insert_record('question_attempts', (object) [
            'questionusageid' => $usageid,
            'slot' => 1,
            'behaviour' => 'deferredfeedback',
            'questionid' => $questionid,
            'variant' => 1,
            'maxmark' => 1,
            'minfraction' => 0,
            'maxfraction' => 1,
            'flagged' => 0,
            'questionsummary' => '',
            'timemodified' => $now,
        ]);

        $aqaid = (int) $DB->insert_record('adaptivequiz_attempt', (object) [
            'instance' => 1,
            'userid' => $userid,
            'uniqueid' => $usageid,
            'attemptstate' => 'complete',
            'attemptstopcriteria' => '',
            'questionsattempted' => 1,
            'difficultysum' => 0,
            'standarderror' => 0.5,
            'measure' => 0.0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => $userid,
            'scaleid' => 1,
            'contextid' => 1,
            'courseid' => 1,
            'attemptid' => $aqaid,
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
     * Calls the private ownership check.
     *
     * @param int $questionid
     * @return void
     */
    private function check(int $questionid): void {
        $method = new \ReflectionMethod(
            \local_catquiz\external\get_question_preview::class,
            'require_own_question'
        );
        $method->setAccessible(true);
        $method->invoke(null, $questionid);
    }

    /**
     * A participant may preview a question from their own attempt.
     *
     * @return void
     */
    public function test_own_question_is_allowed(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->make_attempt_with_question((int) $user->id, 4711);

        $this->check(4711);
        $this->assertTrue(true, 'Reaching this line is the assertion: no exception.');
    }

    /**
     * A question from somebody else's attempt is refused.
     *
     * @return void
     */
    public function test_foreign_question_is_refused(): void {
        $this->resetAfterTest();

        $owner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();

        $this->make_attempt_with_question((int) $owner->id, 4712);
        $this->setUser($other);

        $this->expectException(\moodle_exception::class);
        $this->check(4712);
    }

    /**
     * A question the user was never asked is refused, even with an own attempt.
     *
     * Otherwise having taken any test at all would unlock every question.
     *
     * @return void
     */
    public function test_unrelated_question_is_refused(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->make_attempt_with_question((int) $user->id, 4713);

        $this->expectException(\moodle_exception::class);
        $this->check(999999);
    }
}
