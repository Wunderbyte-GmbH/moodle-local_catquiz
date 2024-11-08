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
 * Tests the feedback_helper.
 *
 * @package    local_catquiz
 * @author     Magdalena Holczik
 * @copyright  2023 onwards <info@wunderbyte.at>
 * @license    http =>//www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;
use local_catquiz\teststrategy\feedback_helper;
use PHPUnit\Framework\ExpectationFailedException;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

/**
 * Tests the feedback_helper.
 *
 * @package    local_catquiz
 * @author     Magdalena Holczik
 * @copyright  2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http =>//www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_catquiz\teststrategy\feedback_helper
 */
final class feedback_helper_test extends advanced_testcase {

    /**
     * Test if correct string (label for chart) is returned correctly according to defined timerange and timestamp.
     *
     * @param int $timerange
     * @param int $timestamp
     * @param string $expected
     *
     * @return void
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     * @dataProvider return_datestring_label_provider
     */
    public function test_return_datestring_label(
        int $timerange,
        int $timestamp,
        string $expected
    ): void {
        $datestringlabel = feedback_helper::return_datestring_label($timerange, $timestamp);
        $this->assertEquals($expected, $datestringlabel);
    }

    /**
     * Data provider for test_datestring_label_provider.
     *
     * @return array
     */
    public static function return_datestring_label_provider(): array {
        return [
            'day' => [
                'timerange' => LOCAL_CATQUIZ_TIMERANGE_DAY,
                'timestamp' => 1714030970,
                'expected' => "25.04.2024",
                ],
            'week' => [
                'timerange' => LOCAL_CATQUIZ_TIMERANGE_WEEK,
                'timestamp' => 1708843353,
                'expected' => "week 08",
                ],
            'week17' => [
                'timerange' => LOCAL_CATQUIZ_TIMERANGE_WEEK,
                'timestamp' => 1714030970,
                'expected' => "week 17",
                ],
            'month' => [
                'timerange' => LOCAL_CATQUIZ_TIMERANGE_MONTH,
                'timestamp' => 1714030970,
                'expected' => "April 24",
                ],
            'quarter1' => [
                'timerange' => LOCAL_CATQUIZ_TIMERANGE_QUARTEROFYEAR,
                'timestamp' => 1708843353,
                'expected' => "Q1 2024",
                ],
            'quarter2' => [
                'timerange' => LOCAL_CATQUIZ_TIMERANGE_QUARTEROFYEAR,
                'timestamp' => 1714030970,
                'expected' => "Q2 2024",
                ],
        ];
    }

    /**
     * Test if the correct bin is calculated
     *
     * @param float $value
     * @param int $classwidth
     * @param int $expected
     *
     * @return void
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     * @dataProvider get_histogram_bin_provider
     */
    public function test_get_histogram_bin(
        float $value,
        int $classwidth,
        int $expected
    ): void {
        $this->assertEquals($expected, feedback_helper::get_histogram_bin($value, $classwidth));
    }

    /**
     * Data provider for histogram bin test
     *
     * @return array
     */
    public static function get_histogram_bin_provider(): array {
        return [
            '0 is assigned bin 0' => [0, 87, 0],
            'classwidth is assigned to bin 0' => [87, 87, 0],
            'classwidth plus 1 is assigned to bin 1' => [88, 87, 1],
            'class width times n is assigned bin n-1' => [609, 87, 6],
        ];
    }
}
