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
use local_catquiz\output\attemptfeedback;

/**
 * Which range a routing score falls into, for courses and for groups alike (issue #129).
 *
 * Three ranges for one scale: [-5, -1), [-1, 1) and [1, 5]. Every range is half-open except the
 * top one, which includes its upper bound. Each range routes into its own course and its own group,
 * so a course and a group taken from different ranges for the same score would show at once.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\output\attemptfeedback
 */
final class routing_ranges_test extends advanced_testcase {
    /** @var int The scale the ranges belong to. */
    private const SCALE = 5;

    /**
     * The quiz settings with three ranges, a course and a group per range.
     *
     * @return array
     */
    private static function quizsettings(): array {
        $settings = ['numberoffeedbackoptionsselect' => 3];
        // The lower bounds as the form stores them: one with a decimal comma.
        foreach ([1 => ['-5', '-1'], 2 => ['-1,0', '1'], 3 => ['1', '5']] as $range => [$lower, $upper]) {
            $settings['feedback_scaleid_limit_lower_' . self::SCALE . '_' . $range] = $lower;
            $settings['feedback_scaleid_limit_upper_' . self::SCALE . '_' . $range] = $upper;
            // Element 0 is the "please choose" entry of the form.
            $settings['catquiz_courses_' . self::SCALE . '_' . $range] = [0, 100 + $range];
            $settings['catquiz_group_' . self::SCALE . '_' . $range] = 'group' . $range;
        }
        return $settings;
    }

    /**
     * Scores at and around every bound, with the range they belong to.
     *
     * @return array
     */
    public static function scores(): array {
        return [
            'lowest bound belongs to the first range' => [-5.0, 1],
            'just below a bound: still the lower range' => [-1.000001, 1],
            'a bound belongs to the range it opens' => [-1.0, 2],
            'just below the next bound' => [0.999999, 2],
            'next bound opens the top range' => [1.0, 3],
            'top bound belongs to the top range' => [5.0, 3],
            'below every range' => [-5.000001, null],
            'above every range' => [5.000001, null],
        ];
    }

    /**
     * Courses and groups come from the same range, and that range is the right one.
     *
     * @dataProvider scores
     * @param float $score
     * @param int|null $range The range the score falls into; null for none.
     */
    public function test_courses_and_groups_use_the_same_range(float $score, ?int $range): void {
        $courses = attemptfeedback::courses_to_enrol(self::quizsettings(), [self::SCALE => $score]);
        $groups = attemptfeedback::groups_to_enrol(self::quizsettings(), [self::SCALE => $score]);

        if ($range === null) {
            $this->assertArrayNotHasKey('range', $courses[self::SCALE]);
            $this->assertSame([], $courses[self::SCALE]['course_ids']);
            $this->assertSame([], $groups[self::SCALE]);
            return;
        }
        $this->assertSame($range, $courses[self::SCALE]['range']);
        $this->assertSame([100 + $range], array_values($courses[self::SCALE]['course_ids']));
        $this->assertSame(['group' . $range], $groups[self::SCALE]);
    }
}
