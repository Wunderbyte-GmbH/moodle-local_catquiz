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
 * Test the attemptfeedback class
 *
 * @package    local_catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;
use local_catquiz\output\attemptfeedback;
use PHPUnit\Framework\ExpectationFailedException;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

/**
 * Tests the attemptfeedback class
 *
 * @package    local_catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright  2024 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_catquiz\output\attemptfeedback
 */
class attemptfeedback_test extends advanced_testcase {
    /**
     * Checks if the attemptfeedback class returns the correct course enrolment data.
     *
     * @param array $expected
     * @param array $feedbackdata
     * @param array $quizsettings
     *
     * @return void
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     * @dataProvider strategy_returns_expected_courses_to_enrol_provider
     */
    public function test_it_returns_expected_courses_to_enrol(
        array $expected,
        array $feedbackdata,
        array $quizsettings
    ) {
        // We use a mock object so that we can work with the quiz settings and feedbackdata that we get from the data provider.
        // The rest of the attemptfeedback class is unchanged.
        $attemptfeedback = $this->getMockBuilder(attemptfeedback::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['load_feedbackdata', 'get_quiz_settings'])
            ->getMock();
        $attemptfeedback
            ->method('load_feedbackdata')
            ->willReturn($feedbackdata);
        $attemptfeedback
            ->method('get_quiz_settings')
            ->willReturn($quizsettings);

        $result = $attemptfeedback->get_courses_to_enrol();
        $this->assertEquals($expected, $result);
    }

    /**
     * Data provider for test_strategy_returns_expected_courses_to_enrol
     * @return array
     */
    public static function strategy_returns_expected_courses_to_enrol_provider(): array {
        $scaleid = 1;
        $quizsettings = [
            sprintf('feedback_scaleid_limit_lower_%s_1', $scaleid) => -5,
            sprintf('feedback_scaleid_limit_upper_%s_1', $scaleid) => 0,
            sprintf('feedback_scaleid_limit_lower_%s_2', $scaleid) => 0,
            sprintf('feedback_scaleid_limit_upper_%s_2', $scaleid) => 5,
            sprintf('catquiz_courses_%s_1', $scaleid) => [1, 3],
            sprintf('catquiz_courses_%s_2', $scaleid) => [2, 4],
            sprintf('enrolment_message_checkbox_%s_1', $scaleid) => true,
            sprintf('enrolment_message_checkbox_%s_2', $scaleid) => false,
        ];
        return [
            'Should enrol to two courses' => [
                'expected' => [
                    $scaleid => [
                        'range' => 2,
                        'show_message' => false,
                        'course_ids' => [2, 4],
                    ],
                ],
                'feedbackdata' => [
                    'personabilities_abilities' => [
                        1 => [
                            'value' => 1.2,
                            'name' => 'Simulation',
                            'toreport' => true,
                        ],
                        2 => [
                            'value' => 1.2,
                            'name' => 'Another scale with courses',
                        ],
                    ],
                ],
                'quizsettings' => array_merge(
                    $quizsettings,
                    [
                        'feedback_scaleid_limit_lower_2_1' => 0,
                        'feedback_scaleid_limit_upper_2_1' => 5,
                        'catquiz_courses_2_1' => [3],
                    ]
                ),
            ],
            // The ability is outside the defined ranges.
            'Should enrol to no course' => [
                'expected' => [
                    $scaleid => [
                        'course_ids' => [],
                    ],
                ],
                [
                    'personabilities_abilities' => [
                        1 => [
                            'value' => 6.0,
                            'name' => 'Simulation',
                            'toreport' => true,
                        ],
                    ],
                ],
                'quizsettings' => $quizsettings,
            ],
        ];
    }

    /**
     * Checks if the the attemptfeedback class returns the correct group enrolment data.
     *
     * @param array $expected
     * @param array $feedbackdata
     * @param array $quizsettings
     *
     * @return void
     *
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     * @dataProvider strategy_returns_expected_groups_to_enrol_provider
     */
    public function test_it_returns_expected_groups_to_enrol(
        array $expected,
        array $feedbackdata,
        array $quizsettings
    ) {
        // We use a mock object so that we can work with the quiz settings and feedbackdata that we get from the data provider.
        // The rest of the attemptfeedback class is unchanged.
        $attemptfeedback = $this->getMockBuilder(attemptfeedback::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['load_feedbackdata', 'get_quiz_settings'])
            ->getMock();
        $attemptfeedback
            ->method('load_feedbackdata')
            ->willReturn($feedbackdata);
        $attemptfeedback
            ->method('get_quiz_settings')
            ->willReturn($quizsettings);

        $result = $attemptfeedback->get_groups_to_enrol();
        $this->assertEquals($expected, $result);
    }

    /**
     * Data provider for test_strategy_returns_expected_courses_to_enrol
     * @return array
     */
    public static function strategy_returns_expected_groups_to_enrol_provider(): array {
        $scaleid = 1;
        $quizsettings = [
            sprintf('feedback_scaleid_limit_lower_%s_1', $scaleid) => -5,
            sprintf('feedback_scaleid_limit_upper_%s_1', $scaleid) => 0,
            sprintf('feedback_scaleid_limit_lower_%s_2', $scaleid) => 0,
            sprintf('feedback_scaleid_limit_upper_%s_2', $scaleid) => 5,
            sprintf('catquiz_group_%s_1', $scaleid) => "1,3",
            sprintf('catquiz_group_%s_2', $scaleid) => "2,4",
        ];
        return [
            'Should enrol to two groups' => [
                'expected' => [
                    1 => [2, 4],
                ],
                'feedbackdata' => [
                    'personabilities_abilities' => [
                        1 => [
                            'value' => 1.2,
                            'name' => 'Simulation',
                            'toreport' => true,
                        ],
                    ],
                ],
                'quizsettings' => $quizsettings,
            ],
            // The ability is outside the defined ranges.
            'Should enrol to no group' => [
                'expected' => [
                    1 => [],
                ],
                [
                    'personabilities_abilities' => [
                        1 => [
                            'value' => 6.0,
                            'name' => 'Simulation',
                            'toreport' => true,
                        ],
                    ],
                ],
                'quizsettings' => $quizsettings,
            ],
        ];
    }
}
