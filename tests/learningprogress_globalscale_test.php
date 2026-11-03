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
 * Issue #11: the learning-progress trajectory follows the global scale.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;
use local_catquiz\local\statistics\learning_progress_series;

/**
 * The learning-progress series is bound to a chosen scale (issue #11).
 *
 * The chart must follow the global scale, so its series must not change when the
 * primary scale of individual attempts changes. A value of exactly 0.0 is a
 * valid ability and must be kept. An attempt without a value for the scale gives no point
 * of the trajectory - never a substituted value (issue #119: one point per valid result).
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\local\statistics\learning_progress_series
 */
final class learningprogress_globalscale_test extends advanced_testcase {
    /**
     * The person's trajectory on a scale, as the learning-progress chart draws it.
     *
     * @param array $attempts
     * @param int   $scaleid
     * @return array
     */
    private function values_for_scale(array $attempts, int $scaleid): array {
        return learning_progress_series::individual($attempts, $scaleid);
    }

    /**
     * Builds attempt records where scale 1 is the global scale and scale 2 the
     * (changing) primary scale.
     *
     * @return array
     */
    private function attempts(): array {
        return [
            // Global 0.5, primary 1.5. Attempts without stored results: their JSON is read.
            (object) ['id' => 91, 'userid' => 5, 'endtime' => 1000,
                'json' => json_encode(['personabilities' => [1 => 0.5, 2 => 1.5]])],
            // Global exactly 0.0 (must be kept), primary 2.0.
            (object) ['id' => 92, 'userid' => 5, 'endtime' => 2000,
                'json' => json_encode(['personabilities' => [1 => 0.0, 2 => 2.0]])],
            // No global value (no point), primary 3.0.
            (object) ['id' => 93, 'userid' => 5, 'endtime' => 3000,
                'json' => json_encode(['personabilities' => [2 => 3.0]])],
        ];
    }

    /**
     * The global series uses global values, keeps 0.0 and gaps missing entries.
     *
     * @return void
     */
    public function test_global_series_keeps_zero_and_gaps(): void {
        $global = $this->values_for_scale($this->attempts(), 1);
        $this->assertSame([0.5, 0.0], $global);
    }

    /**
     * The primary series is a different trajectory (built from scale 2).
     *
     * @return void
     */
    public function test_primary_series_differs_from_global(): void {
        $attempts = $this->attempts();
        $global = $this->values_for_scale($attempts, 1);
        $primary = $this->values_for_scale($attempts, 2);
        $this->assertSame([1.5, 2.0, 3.0], $primary);
        $this->assertNotSame(
            $global,
            $primary,
            'A changing primary scale must not affect the global trajectory.'
        );
    }

    /**
     * An attempt missing the scale value gives no point, and no other scale's value takes its place.
     *
     * @return void
     */
    public function test_missing_value_is_a_gap(): void {
        $global = $this->values_for_scale($this->attempts(), 1);
        $this->assertCount(2, $global, 'Only attempts with a value for the scale are points.');
        $this->assertNotContains(3.0, $global, 'The primary value must not stand in for the global one.');
    }
}
