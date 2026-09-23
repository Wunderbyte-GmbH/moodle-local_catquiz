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
 * Regression test for the maximum questions stop condition.
 *
 * The configured test length means ANSWERED productive items. Two earlier
 * attempts at this check counted the wrong thing:
 *  - `questionsattempted` on the adaptivequiz attempt record drifts across a
 *    resume, which let the test administer one item too many;
 *  - `playedquestions` counts *displayed* items, and progress::load() removes the
 *    still unanswered last question from it on a resume - so it is neither a
 *    stable administered-count nor an answered-count.
 * Counting the responses removes both special cases.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\teststrategy\preselect_task\maximumquestionscheck
 */

namespace local_catquiz\teststrategy\preselect_task;

use advanced_testcase;
use local_catquiz\local\status;
use stdClass;

/**
 * Guards the maximum questions stop condition.
 *
 * @package    local_catquiz
 * @covers \local_catquiz\teststrategy\preselect_task\maximumquestionscheck
 */
final class maximumquestionscheck_test extends advanced_testcase {
    /**
     * Builds a duck-typed progress stub.
     *
     * @param int $displayed Number of items displayed to the user.
     * @param int $answered Number of those items that carry a response.
     * @param int $pilotsanswered How many of the answers belong to pilot items.
     *
     * @return object
     */
    private function progress_stub(int $displayed, int $answered, int $pilotsanswered = 0) {
        $played = [];
        $responses = [];
        for ($i = 1; $i <= $displayed; $i++) {
            $q = new stdClass();
            $q->id = $i;
            $q->is_pilot = ($i <= $pilotsanswered);
            $played[$i] = $q;
            if ($i <= $answered) {
                $responses[$i] = ['fraction' => 1.0];
            }
        }
        return new class ($played, $responses) {
            /** @var array Displayed questions, keyed by question id. */
            private array $played;
            /** @var array Responses, keyed by question id. */
            private array $responses;

            /**
             * Constructor.
             *
             * @param array $played
             * @param array $responses
             */
            public function __construct(array $played, array $responses) {
                $this->played = $played;
                $this->responses = $responses;
            }

            /**
             * Mirrors progress::get_num_answered_productive_questions().
             *
             * @return int
             */
            public function get_num_answered_productive_questions(): int {
                $count = 0;
                foreach (array_keys($this->responses) as $qid) {
                    $q = $this->played[$qid] ?? null;
                    if ($q !== null && !empty($q->is_pilot)) {
                        continue;
                    }
                    $count++;
                }
                return $count;
            }

            /**
             * Simulates a resume: progress::load() drops the pending item.
             *
             * @return void
             */
            public function simulate_resume(): void {
                foreach ($this->played as $qid => $q) {
                    if (!isset($this->responses[$qid])) {
                        unset($this->played[$qid]);
                    }
                }
            }
        };
    }

    /**
     * Stops exactly when the configured number of answers is reached.
     *
     * @return void
     */
    public function test_stops_when_configured_answers_reached(): void {
        $this->resetAfterTest(true);

        $task = new maximumquestionscheck();
        $context = [
            'maximumquestions' => 4,
            'questionsattempted' => 4,
            'progress' => $this->progress_stub(4, 4),
        ];
        $result = $task->run($context);
        $this->assertTrue($result->iserr());
        $this->assertEquals(status::ERROR_REACHED_MAXIMUM_QUESTIONS, $result->get_status());
    }

    /**
     * A displayed but not yet answered item must NOT count towards the length.
     *
     * @return void
     */
    public function test_pending_item_does_not_count(): void {
        $this->resetAfterTest(true);

        $task = new maximumquestionscheck();
        $context = [
            'maximumquestions' => 4,
            'questionsattempted' => 3,
            'progress' => $this->progress_stub(4, 3),
        ];
        $this->assertFalse(
            $task->run($context)->iserr(),
            'A pending item must not end the attempt early.'
        );
    }

    /**
     * The full resume lifecycle must end after exactly four answers.
     *
     * Q1 answered, Q2 displayed, resume (the pending Q2 is dropped from the
     * displayed items), Q2..Q4 answered -> stop, and never a fifth item.
     *
     * @return void
     */
    public function test_resume_lifecycle_stops_after_configured_answers(): void {
        $this->resetAfterTest(true);

        $task = new maximumquestionscheck();

        $progress = $this->progress_stub(2, 1);
        $progress->simulate_resume();
        $context = [
            'maximumquestions' => 4,
            'questionsattempted' => 1,
            'progress' => $progress,
        ];
        $this->assertFalse($task->run($context)->iserr(), 'Only one answer so far.');

        $context['progress'] = $this->progress_stub(4, 4);
        $context['questionsattempted'] = 3;
        $this->assertTrue(
            $task->run($context)->iserr(),
            'After four answers the attempt must stop, even if the external counter lags.'
        );
    }

    /**
     * Answered pilot items do not count towards the productive test length.
     *
     * @return void
     */
    public function test_pilot_answers_do_not_count(): void {
        $this->resetAfterTest(true);

        $task = new maximumquestionscheck();
        $context = [
            'maximumquestions' => 4,
            'questionsattempted' => 5,
            'progress' => $this->progress_stub(5, 5, 1),
        ];
        $this->assertTrue($task->run($context)->iserr());

        $context['progress'] = $this->progress_stub(4, 4, 1);
        $this->assertFalse($task->run($context)->iserr());
    }

    /**
     * A maximum of -1 means "no limit", and a missing progress falls back to the
     * external counter.
     *
     * @return void
     */
    public function test_unlimited_and_fallback(): void {
        $this->resetAfterTest(true);

        $task = new maximumquestionscheck();

        $unlimited = ['maximumquestions' => -1, 'questionsattempted' => 99];
        $this->assertFalse($task->run($unlimited)->iserr());

        $fallback = ['maximumquestions' => 4, 'questionsattempted' => 4];
        $this->assertTrue($task->run($fallback)->iserr());
    }
}
