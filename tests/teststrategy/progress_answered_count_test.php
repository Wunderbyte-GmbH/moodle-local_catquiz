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
 * Regression test for the authoritative answered-item count, per scale.
 *
 * The result validator derives the per-scale N from this counter. It previously
 * used count(get_playedquestions(...)), which counts DISPLAYED items - so N could
 * include a still unanswered pending item as well as pilot items, and an attempt
 * could be declared valid on a too optimistic N (Issue #7, DoD 4).
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\teststrategy\progress::get_num_answered_productive_questions
 */

namespace local_catquiz\teststrategy;

use advanced_testcase;
use ReflectionClass;
use ReflectionProperty;
use stdClass;

/**
 * Guards the answered-item count against pending and pilot items.
 *
 * @package    local_catquiz
 * @covers \local_catquiz\teststrategy\progress::get_num_answered_productive_questions
 */
final class progress_answered_count_test extends advanced_testcase {
    /**
     * Builds a progress instance with the given state.
     *
     * @param array $played Question stubs keyed by question id.
     * @param array $responses Response stubs keyed by question id.
     * @param array $byscale Question stubs per scale id.
     *
     * @return progress
     */
    private function make_progress(array $played, array $responses, array $byscale): progress {
        $progress = (new ReflectionClass(progress::class))->newInstanceWithoutConstructor();
        $state = [
            'playedquestions' => $played,
            'responses' => $responses,
            'playedquestionsbyscale' => $byscale,
        ];
        foreach ($state as $name => $value) {
            $property = new ReflectionProperty(progress::class, $name);
            $property->setAccessible(true);
            $property->setValue($progress, $value);
        }
        return $progress;
    }

    /**
     * Builds a question stub.
     *
     * @param int $id
     * @param bool $ispilot
     *
     * @return stdClass
     */
    private function question(int $id, bool $ispilot = false): stdClass {
        $q = new stdClass();
        $q->id = $id;
        $q->is_pilot = $ispilot;
        return $q;
    }

    /**
     * Pending and pilot items must not be counted, neither overall nor per scale.
     *
     * @return void
     */
    public function test_pending_and_pilot_items_are_excluded(): void {
        $this->resetAfterTest(true);

        // Scale 10: q1 answered, q2 answered pilot, q3 displayed but pending.
        // Scale 20: q4 answered.
        $played = [
            1 => $this->question(1),
            2 => $this->question(2, true),
            3 => $this->question(3),
            4 => $this->question(4),
        ];
        $responses = [
            1 => ['fraction' => 1.0],
            2 => ['fraction' => 1.0],
            4 => ['fraction' => 0.0],
        ];
        $byscale = [
            10 => [$played[1], $played[2], $played[3]],
            20 => [$played[4]],
        ];
        $progress = $this->make_progress($played, $responses, $byscale);

        // Overall: q1 and q4 count; q2 is a pilot, q3 has no response.
        $this->assertSame(2, $progress->get_num_answered_productive_questions());

        // Scale 10: only q1 - NOT the pilot q2 and NOT the pending q3.
        $this->assertSame(
            1,
            $progress->get_num_answered_productive_questions(10),
            'Per-scale N must exclude pilot and pending items.'
        );

        // Scale 20: q4.
        $this->assertSame(1, $progress->get_num_answered_productive_questions(20));

        // A scale without any played question yields 0.
        $this->assertSame(0, $progress->get_num_answered_productive_questions(99));
    }

    /**
     * The displayed-item count differs from the answered count - which is exactly
     * why the validator must not use it.
     *
     * @return void
     */
    public function test_displayed_count_differs_from_answered_count(): void {
        $this->resetAfterTest(true);

        $played = [
            1 => $this->question(1),
            2 => $this->question(2, true),
            3 => $this->question(3),
        ];
        $responses = [1 => ['fraction' => 1.0]];
        $byscale = [10 => [$played[1], $played[2], $played[3]]];
        $progress = $this->make_progress($played, $responses, $byscale);

        // Three items were displayed for scale 10 ...
        $this->assertCount(3, $progress->get_playedquestions(true, 10));
        // ... but only one of them is an answered productive item.
        $this->assertSame(1, $progress->get_num_answered_productive_questions(10));
    }
}
