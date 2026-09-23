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
use local_catquiz\teststrategy\feedback_helper;
use local_catquiz\teststrategy\feedbackgenerator\learningprogress;
use local_catquiz\teststrategy\feedbacksettings;
use ReflectionMethod;

/**
 * The learning-progress series is bound to a chosen scale (issue #11).
 *
 * The chart must follow the global scale, so its series must not change when the
 * primary scale of individual attempts changes. A value of exactly 0.0 is a
 * valid ability and must be kept; an attempt without a value for the scale must
 * become a gap (null), never a substituted value.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\teststrategy\feedbackgenerator\learningprogress::extract_scale_progress_values
 */
final class learningprogress_globalscale_test extends advanced_testcase {
    /**
     * Extracts the per-attempt values for a scale via the generator.
     *
     * @param array $attempts
     * @param int   $scaleid
     * @return array
     */
    private function values_for_scale(array $attempts, int $scaleid): array {
        $generator = new learningprogress(
            new feedbacksettings(LOCAL_CATQUIZ_STRATEGY_LOWESTSUB),
            new feedback_helper()
        );
        $method = new ReflectionMethod(learningprogress::class, 'extract_scale_progress_values');
        $method->setAccessible(true);
        return $method->invoke($generator, $attempts, $scaleid);
    }

    /**
     * Builds attempt records where scale 1 is the global scale and scale 2 the
     * (changing) primary scale.
     *
     * @return array
     */
    private function attempts(): array {
        return [
            // Global 0.5, primary 1.5.
            (object) ['json' => json_encode(['personabilities' => [1 => 0.5, 2 => 1.5]])],
            // Global exactly 0.0 (must be kept), primary 2.0.
            (object) ['json' => json_encode(['personabilities' => [1 => 0.0, 2 => 2.0]])],
            // No global value (must become a gap), primary 3.0.
            (object) ['json' => json_encode(['personabilities' => [2 => 3.0]])],
        ];
    }

    /**
     * The global series uses global values, keeps 0.0 and gaps missing entries.
     *
     * @return void
     */
    public function test_global_series_keeps_zero_and_gaps(): void {
        $global = $this->values_for_scale($this->attempts(), 1);
        $this->assertSame([0.5, 0.0, null], $global);
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
     * An attempt missing the scale value yields null, not a shifted series.
     *
     * @return void
     */
    public function test_missing_value_is_a_gap(): void {
        $global = $this->values_for_scale($this->attempts(), 1);
        $this->assertCount(3, $global, 'Every attempt must map to one entry.');
        $this->assertNull($global[2], 'A missing global value must be a gap.');
    }
}
