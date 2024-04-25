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
 * Tests the feedbackgenerator learningprogress.
 *
 * @package    local_catquiz
 * @author     Magdalena Holczik
 * @copyright  2023 onwards <info@wunderbyte.at>
 * @license    http =>//www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;
use local_catquiz\teststrategy\feedbackgenerator\learningprogress;
use local_catquiz\teststrategy\feedbackgenerator\personabilities;
use local_catquiz\teststrategy\feedbacksettings;
use local_catquiz\teststrategy\progress;
use PHPUnit\Framework\ExpectationFailedException;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

/**
 * Tests the feedbackgenerator learningprogress.
 *
 * @package    local_catquiz
 * @author     David Szkiba, Magdalena Holczik
 * @copyright  2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http =>//www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_catquiz\teststrategy\preselect_task\filterforsubscale
 */
class learningprogress_test extends advanced_testcase {

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
        string $expected) {
        $feedbacksettings = new feedbacksettings(LOCAL_CATQUIZ_STRATEGY_LOWESTSUB);
        $learningprogress = new learningprogress($feedbacksettings);

        $datestringlabel = $learningprogress->return_datestring_label($timerange, $timestamp);

        $this->assertEquals($expected, $datestringlabel);
    }

    /**
     * Data provider for test_datestring_label_provider.
     *
     * @return array
     *
     */
    public static function return_datestring_label_provider(): array {

        return [
            'day' => [
                'timerange' => LOCAL_CATQUIZ_TIMERANGE_DAY,
                'timestamp' => 1714030970,
                'expected' => "25.04.2024"
                ],
            'week' => [
                'timerange' => LOCAL_CATQUIZ_TIMERANGE_WEEK,
                'timestamp' => 1708843353,
                'expected' => "week 08"
                ],
            'week17' => [
                'timerange' => LOCAL_CATQUIZ_TIMERANGE_WEEK,
                'timestamp' => 1714030970,
                'expected' => "week 17"
                ],
            'month' => [
                'timerange' => LOCAL_CATQUIZ_TIMERANGE_MONTH,
                'timestamp' => 1714030970,
                'expected' => "April"
                ],
            'quarter1' => [
                'timerange' => LOCAL_CATQUIZ_TIMERANGE_QUARTEROFYEAR,
                'timestamp' => 1708843353,
                'expected' => "Q1 2024"
                ],
            'quarter2' => [
                'timerange' => LOCAL_CATQUIZ_TIMERANGE_QUARTEROFYEAR,
                'timestamp' => 1714030970,
                'expected' => "Q2 2024"
                ],
        ];
    }

}
