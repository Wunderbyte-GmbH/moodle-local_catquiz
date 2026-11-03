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

namespace local_catquiz\output;

use advanced_testcase;
use cache;
use local_catquiz\teststrategy\progress;

/**
 * Issue #142: feedback snapshots in the session cache stay bounded.
 *
 * The earlier code kept one snapshot per number of played questions (feedbackdata_<attempt>_<n>)
 * and deleted "_0" instead of "_n-1" (`$numplayed = get_num_playedquestions() >= 1`). Each
 * snapshot holds the whole feedback state, so the session grew with the square of the test length.
 *
 * Retention now: one snapshot per attempt, stamped with the number of questions it was taken at.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\output\attemptfeedback
 */
final class feedback_snapshot_retention_test extends advanced_testcase {
    /** @var int Number of played questions the progress stub reports. */
    private int $numplayed = 0;

    /**
     * An attemptfeedback for an attempt id, with a progress stub that reports $this->numplayed.
     *
     * No test environment exists for the id, so the constructor stops after setting the attempt id -
     * exactly the part the cache lifecycle depends on.
     *
     * @param int $attemptid
     * @return attemptfeedback
     */
    private function feedback(int $attemptid): attemptfeedback {
        $feedback = new attemptfeedback($attemptid);
        $progress = $this->createMock(progress::class);
        $progress->method('get_num_playedquestions')->willReturnCallback(fn() => $this->numplayed);
        $property = new \ReflectionProperty(attemptfeedback::class, 'progress');
        $property->setAccessible(true);
        $property->setValue($feedback, $progress);
        return $feedback;
    }

    /**
     * Stores a snapshot through the class's own writer.
     *
     * @param attemptfeedback $feedback
     * @param array $data
     */
    private function save(attemptfeedback $feedback, array $data): void {
        $method = new \ReflectionMethod(attemptfeedback::class, 'save_to_cache');
        $method->setAccessible(true);
        $method->invoke($feedback, $data);
    }

    /**
     * The session cache of the attempt.
     *
     * @return \cache
     */
    private function cache(): \cache {
        return cache::make('local_catquiz', 'adaptivequizattempt');
    }

    /**
     * Per-question keys of the earlier code that are still in the cache.
     *
     * @param int $attemptid
     * @param int $upto
     * @return int[]
     */
    private function legacy_keys_left(int $attemptid, int $upto): array {
        $left = [];
        foreach (range(0, $upto) as $n) {
            if ($this->cache()->get(sprintf('feedbackdata_%d_%d', $attemptid, $n)) !== false) {
                $left[] = $n;
            }
        }
        return $left;
    }

    /**
     * After 0, 1, 2, 42 and 150 questions there is exactly the current snapshot - nothing older.
     *
     * @return void
     */
    public function test_one_snapshot_per_attempt_for_any_number_of_questions(): void {
        $this->resetAfterTest();
        $feedback = $this->feedback(501);

        foreach ([0, 1, 2, 42, 150] as $n) {
            $this->numplayed = $n;
            $this->save($feedback, ['marker' => $n]);

            $entry = $this->cache()->get('feedbackdata_501');
            $this->assertSame($n, $entry['numplayed']);
            $this->assertSame(['marker' => $n], $entry['data']);
            $this->assertSame([], $this->legacy_keys_left(501, $n), "No per-question keys after $n questions.");
            $this->assertSame(['marker' => $n], $feedback->load_feedbackdata());
        }
    }

    /**
     * Regression for the operator precedence: after item 42 the earlier code deleted _0 and left
     * _1 … _41. Keys the earlier code left for a running attempt are removed on the first write.
     *
     * @return void
     */
    public function test_keys_left_by_the_earlier_code_are_removed(): void {
        $this->resetAfterTest();
        foreach (range(1, 42) as $n) {
            $this->cache()->set(sprintf('feedbackdata_%d_%d', 502, $n), ['old' => $n]);
        }
        $this->assertCount(42, $this->legacy_keys_left(502, 42), 'Fixture: the state the earlier code produced.');

        $this->numplayed = 42;
        $this->save($this->feedback(502), ['marker' => 42]);

        $this->assertSame([], $this->legacy_keys_left(502, 42));
    }

    /**
     * Cache hit and cache miss end in the same state: one snapshot at the current number.
     *
     * @return void
     */
    public function test_hit_and_miss_end_in_the_same_state(): void {
        global $DB;
        $this->resetAfterTest();
        $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => 2, 'attemptid' => 503, 'json' => json_encode(['from' => 'database']),
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $feedback = $this->feedback(503);

        // Hit: the snapshot of the current number is returned as stored.
        $this->numplayed = 3;
        $this->save($feedback, ['from' => 'cache']);
        $this->assertSame(['from' => 'cache'], $feedback->load_feedbackdata());

        // Miss: a snapshot of an older number is not used; the attempt is read from the database
        // and becomes the snapshot of the current number - still one entry.
        $this->numplayed = 4;
        $this->assertSame(['from' => 'database'], $feedback->load_feedbackdata());
        $entry = $this->cache()->get('feedbackdata_503');
        $this->assertSame(4, $entry['numplayed']);
        $this->assertSame(['from' => 'database'], $entry['data']);
        $this->assertSame([], $this->legacy_keys_left(503, 4));
    }

    /**
     * A new request for the same attempt (resume, reloaded tab) finds the snapshot; writing the same
     * number again (retry after an exception) replaces it instead of adding one.
     *
     * @return void
     */
    public function test_resume_and_retry(): void {
        $this->resetAfterTest();
        $this->numplayed = 7;
        $this->save($this->feedback(504), ['try' => 1]);

        $resumed = $this->feedback(504);
        $this->assertSame(['try' => 1], $resumed->load_feedbackdata());

        $this->save($resumed, ['try' => 2]);
        $this->assertSame(['numplayed' => 7, 'data' => ['try' => 2]], $this->cache()->get('feedbackdata_504'));
    }

    /**
     * Two attempts in the same session keep their own snapshots.
     *
     * @return void
     */
    public function test_other_attempts_are_not_touched(): void {
        $this->resetAfterTest();
        $this->numplayed = 5;
        $this->save($this->feedback(505), ['attempt' => 505]);
        $this->numplayed = 9;
        $this->save($this->feedback(506), ['attempt' => 506]);
        $this->numplayed = 10;
        $this->save($this->feedback(506), ['attempt' => 506, 'n' => 10]);

        $this->assertSame(['numplayed' => 5, 'data' => ['attempt' => 505]], $this->cache()->get('feedbackdata_505'));
        $this->assertSame(10, $this->cache()->get('feedbackdata_506')['numplayed']);
    }
}
