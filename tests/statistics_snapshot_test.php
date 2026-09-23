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
 * Issue #16: historical teacher statistics use attempt snapshots.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;

/**
 * Selecting one historical snapshot per person (issue #16).
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\catquiz::get_snapshot_ability_per_person
 */
final class statistics_snapshot_test extends advanced_testcase {
    /**
     * Builds attempt-snapshot records.
     *
     * u1: two attempts (early 0.2 @100, late 0.8 @200).
     * u2: one attempt (1.5 @150).
     * u3: a legacy attempt without a snapshot (null) -> excluded.
     *
     * @return array
     */
    private function attempts(): array {
        return [
            (object) ['userid' => 1, 'endtime' => 100, 'personability_after_attempt' => 0.2],
            (object) ['userid' => 1, 'endtime' => 200, 'personability_after_attempt' => 0.8],
            (object) ['userid' => 2, 'endtime' => 150, 'personability_after_attempt' => 1.5],
            (object) ['userid' => 3, 'endtime' => 180, 'personability_after_attempt' => null],
        ];
    }

    /**
     * The default rule keeps the latest attempt per person and excludes legacy.
     *
     * @return void
     */
    public function test_last_rule_one_value_per_person(): void {
        $result = catquiz::get_snapshot_ability_per_person($this->attempts(), 'last');
        // One value per person; u1 -> 0.8 (latest), u2 -> 1.5, u3 excluded (no snapshot).
        $this->assertEqualsWithDelta(0.8, $result[1], 0.0001);
        $this->assertEqualsWithDelta(1.5, $result[2], 0.0001);
        $this->assertArrayNotHasKey(3, $result);
        $this->assertCount(2, $result);
    }

    /**
     * The value is the historical one, never a later/current parameter.
     *
     * @return void
     */
    public function test_first_rule_uses_historical_value(): void {
        $result = catquiz::get_snapshot_ability_per_person($this->attempts(), 'first');
        // User u1 first attempt value is 0.2, not the later 0.8.
        $this->assertEqualsWithDelta(0.2, $result[1], 0.0001);
    }

    /**
     * The best rule picks the highest historical ability per person.
     *
     * @return void
     */
    public function test_best_rule(): void {
        $result = catquiz::get_snapshot_ability_per_person($this->attempts(), 'best');
        $this->assertEqualsWithDelta(0.8, $result[1], 0.0001);
    }

    /**
     * A person with several attempts is weighted once (person-weighted).
     *
     * @return void
     */
    public function test_multiple_attempts_counted_once(): void {
        $result = catquiz::get_snapshot_ability_per_person($this->attempts(), 'last');
        // User u1 had two attempts but appears once.
        $this->assertCount(2, $result);
    }
}
