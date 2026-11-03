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

namespace local_catquiz\local\result;

use advanced_testcase;

/**
 * The validity of the global scale as a grade (decision of 2026-10-14).
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\local\result\global_scale_validity
 */
final class global_scale_validity_test extends advanced_testcase {
    /**
     * Test settings as the progress carries them - groups as objects or as arrays.
     *
     * @param int $perscale
     * @param int $test
     * @param mixed $semax
     * @param bool $asarrays
     * @return \stdClass
     */
    private static function settings(int $perscale, int $test, $semax, bool $asarrays = false): \stdClass {
        $groups = [
            'maxquestionsscalegroup' => ['catquiz_minquestionspersubscale' => $perscale, 'catquiz_maxquestionspersubscale' => 3],
            'maxquestionsgroup' => ['catquiz_minquestions' => $test, 'catquiz_maxquestions' => 4],
            'catquiz_standarderrorgroup' => ['catquiz_standarderror_min' => 0.9, 'catquiz_standarderror_max' => $semax],
        ];
        return (object) ($asarrays ? $groups : array_map(fn($group) => (object) $group, $groups));
    }

    /**
     * The criteria are read from either shape; unset or empty ones do not apply.
     */
    public function test_criteria(): void {
        $this->assertSame([4, 6, 0.5], global_scale_validity::criteria(self::settings(4, 6, 0.5)));
        $this->assertSame([4, 6, 0.5], global_scale_validity::criteria(self::settings(4, 6, '0.5', true)));
        $this->assertSame([0, 0, null], global_scale_validity::criteria(self::settings(0, 0, '')));
        $this->assertSame([0, 0, null], global_scale_validity::criteria((object) []));
        $this->assertSame([0, 0, null], global_scale_validity::criteria(null));
    }

    /**
     * Each criterion on its own; the maximum number of questions and the minimum standard error do not count.
     */
    public function test_reasons(): void {
        $settings = self::settings(4, 6, 0.5);
        $row = fn(?int $n, ?float $se, ?float $fraction) => (object) ['n' => $n, 'standarderror' => $se, 'fraction' => $fraction];

        $this->assertSame([], global_scale_validity::reasons($row(8, 0.4, 0.5), $settings), 'Eight of at most four: no matter.');
        $this->assertSame([], global_scale_validity::reasons($row(8, 0.1, 0.5), $settings), 'Below the minimum SE: no matter.');
        $this->assertSame(
            [scale_result::REASON_N_MIN, scale_result::REASON_MIN_QUESTIONS],
            global_scale_validity::reasons($row(3, 0.4, 0.5), $settings)
        );
        $this->assertSame([scale_result::REASON_MIN_QUESTIONS], global_scale_validity::reasons($row(5, 0.4, 0.5), $settings));
        $this->assertSame([scale_result::REASON_SE_MAX], global_scale_validity::reasons($row(8, 0.51, 0.5), $settings));
        $this->assertSame([scale_result::REASON_SE_MAX], global_scale_validity::reasons($row(8, null, 0.5), $settings));
        $this->assertSame(
            [scale_result::REASON_FRACTION_ALL_CORRECT],
            global_scale_validity::reasons($row(8, 0.4, 1.0), $settings)
        );
        $this->assertSame(
            [scale_result::REASON_FRACTION_ALL_INCORRECT],
            global_scale_validity::reasons($row(8, 0.4, 0.0), $settings)
        );
        $this->assertContains(scale_result::REASON_NOT_MEASURED, global_scale_validity::reasons($row(0, null, null), null));
        $this->assertSame([], global_scale_validity::reasons($row(1, 5.0, null), null), 'No criterion set: only measured.');
    }
}
