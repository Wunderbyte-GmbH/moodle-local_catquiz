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
 * Regression test: feedback range limits must survive a German decimal comma.
 *
 * A configured yellow/green boundary of "1,5" was silently truncated to 1.0 by
 * floatval()/(float), which shifted the colour bands so an ability of 1.10 fell
 * into the GREEN band instead of the intended YELLOW one. This guards the
 * locale-robust parse at the point where the ability is classified into a range.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\teststrategy\feedback_helper::parse_range_limit
 * @covers \local_catquiz\teststrategy\feedback_helper::get_feedback_range_index
 */

namespace local_catquiz\teststrategy;

/**
 * Regression test for locale-robust feedback range parsing.
 *
 * @package    local_catquiz
 * @covers \local_catquiz\teststrategy\feedback_helper::parse_range_limit
 * @covers \local_catquiz\teststrategy\feedback_helper::get_feedback_range_index
 */
final class feedback_range_locale_test extends \advanced_testcase {
    /**
     * The parser must treat a decimal comma as a decimal separator, not truncate.
     *
     * @return void
     */
    public function test_parse_range_limit_accepts_decimal_comma(): void {
        $this->assertSame(1.5, feedback_helper::parse_range_limit('1,5'));
        $this->assertSame(1.5, feedback_helper::parse_range_limit('1.5'));
        $this->assertSame(1.5, feedback_helper::parse_range_limit(1.5));
        $this->assertSame(-0.5, feedback_helper::parse_range_limit('-0,5'));
        $this->assertSame(-3.0, feedback_helper::parse_range_limit('-3'));
        $this->assertSame(3.0, feedback_helper::parse_range_limit('3'));
    }

    /**
     * An ability of 1.10 with a "1,5" yellow/green boundary must classify as the
     * YELLOW range (index 2), not the GREEN range (index 3). With a truncating
     * cast the boundary collapses to 1.0 and 1.10 wrongly lands in green.
     *
     * @return void
     */
    public function test_ability_below_comma_boundary_is_yellow_not_green(): void {
        $scaleid = 141;
        // Boundaries stored as GERMAN decimal strings, exactly the corruption source.
        $settings = [
            'numberoffeedbackoptionsselect' => '3',
            'feedback_scaleid_limit_lower_141_1' => '-3',
            'feedback_scaleid_limit_upper_141_1' => '-0,5',
            'feedback_scaleid_limit_lower_141_2' => '-0,5',
            'feedback_scaleid_limit_upper_141_2' => '1,5',
            'feedback_scaleid_limit_lower_141_3' => '1,5',
            'feedback_scaleid_limit_upper_141_3' => '3',
        ];

        // 1.10 and 1.32 are below 1.5 -> yellow (range index 2).
        $this->assertSame(2, feedback_helper::get_feedback_range_index($settings, $scaleid, 1.10));
        $this->assertSame(2, feedback_helper::get_feedback_range_index($settings, $scaleid, 1.32));
        // A value at/above 1.5 is genuinely green (range index 3).
        $this->assertSame(3, feedback_helper::get_feedback_range_index($settings, $scaleid, 1.60));
        // A value below -0.5 is red (range index 1).
        $this->assertSame(1, feedback_helper::get_feedback_range_index($settings, $scaleid, -1.0));
    }
}
