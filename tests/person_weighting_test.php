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
 * Issue #16: one shared person-weighting rule for charts and exports.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;
use local_catquiz\teststrategy\feedback_helper;

/**
 * Shared person-weighting rule (issue #16).
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\teststrategy\feedback_helper::reduce_to_one_value_per_person
 * @covers     \local_catquiz\teststrategy\feedback_helper::order_attempts_by_timerange
 */
final class person_weighting_test extends advanced_testcase {
    /**
     * The shared rule keeps one value per person, drops null, keeps 0.0.
     *
     * @return void
     */
    public function test_reduce_last_rule(): void {
        $items = [
            ['userid' => 1, 'endtime' => 100, 'value' => 0.2],
            ['userid' => 1, 'endtime' => 200, 'value' => 0.0], // Latest, a valid 0.0.
            ['userid' => 2, 'endtime' => 150, 'value' => 1.5],
            ['userid' => 3, 'endtime' => 180, 'value' => null], // Dropped.
        ];
        $result = feedback_helper::reduce_to_one_value_per_person($items, 'last');
        $this->assertEqualsWithDelta(0.0, $result[1], 0.0001, 'A valid 0.0 must be kept as the latest value.');
        $this->assertEqualsWithDelta(1.5, $result[2], 0.0001);
        $this->assertArrayNotHasKey(3, $result);
        $this->assertCount(2, $result);
    }

    /**
     * The first and best rules select the documented value.
     *
     * @return void
     */
    public function test_reduce_first_and_best(): void {
        $items = [
            ['userid' => 1, 'endtime' => 100, 'value' => 0.2],
            ['userid' => 1, 'endtime' => 200, 'value' => 0.9],
        ];
        $this->assertEqualsWithDelta(0.2, feedback_helper::reduce_to_one_value_per_person($items, 'first')[1], 0.0001);
        $this->assertEqualsWithDelta(0.9, feedback_helper::reduce_to_one_value_per_person($items, 'best')[1], 0.0001);
    }

    /**
     * order_attempts_by_timerange in per-person mode yields one value per person
     * and period, so a person with several attempts in a period counts once.
     *
     * @return void
     */
    public function test_order_attempts_perperson(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/catquiz/lib.php');
        $scaleid = 5;
        // Two people in the same period; person 1 has two attempts.
        $mk = fn ($userid, $endtime, $value) => (object) [
            'userid' => $userid,
            'endtime' => $endtime,
            'json' => json_encode(['personabilities' => [$scaleid => $value]]),
        ];
        // Use endtimes within one day so they share a period bucket.
        $t = 1700000000;
        $attempts = [
            $mk(1, $t + 10, 0.2),
            $mk(1, $t + 20, 0.8), // Latest for person 1.
            $mk(2, $t + 15, 1.4),
        ];
        $timerange = \LOCAL_CATQUIZ_TIMERANGE_DAY;
        $perperson = feedback_helper::order_attempts_by_timerange($attempts, $scaleid, $timerange, false, true);
        // Exactly one period bucket, containing two values (one per person).
        $this->assertCount(1, $perperson);
        $bucket = reset($perperson);
        $this->assertCount(2, $bucket, 'One value per person in the period.');
        sort($bucket);
        $this->assertEqualsWithDelta([0.8, 1.4], $bucket, 0.0001);

        // Attempt-weighted mode keeps all three attempts.
        $attemptweighted = feedback_helper::order_attempts_by_timerange($attempts, $scaleid, $timerange, false, false);
        $bucket2 = reset($attemptweighted);
        $this->assertCount(3, $bucket2, 'Attempt-weighted keeps every attempt.');
    }
}
