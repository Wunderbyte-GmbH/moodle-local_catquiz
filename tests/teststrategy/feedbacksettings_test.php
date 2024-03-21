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
 * Tests feedbacksettings
 *
 * @package    local_catquiz
 * @author     Magdalena Holczik <magdalena.holczik@wunderbyte.at>
 * @copyright  2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;
use advanced_testcase;
use local_catquiz\teststrategy\feedbacksettings;

/**
 * Tests feedbacksettings
 *
 * @package    local_catquiz
 * @author     Magdalena Holczik <magdalena.holczik@wunderbyte.at>
 * @copyright  2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_catquiz\teststrategy\strategy
 */
class feedbacksettings_test extends advanced_testcase {

    /**
     * Test to overwrite selectedscalesarray.
     *
     * @param array $selectedscales
     * @param array $quizsettings
     * @param int $primaryscaleidint
     * @param array $expected
     *
     *
     * @dataProvider apply_selection_of_scales_provider
     *
     */
    public function test_apply_selection_of_scales(array $selectedscales, array $quizsettings, int $primaryscaleidint, array $expected) {

        $feedbacksettings = new feedbacksettings($quizsettings['teststrategy'], $primaryscaleidint);
        $output = $feedbacksettings->apply_selection_of_scales($selectedscales, $quizsettings);

        // $output = $customscalefeedback->get_studentfeedback($feedbackdata);
        $this->assertEquals($expected, $output);
    }

    /**
     * Data provider for test_get_studentfeedback.
     *
     * @return array
     *
     */
    public static function apply_selection_of_scales_provider(): array {
        return [
                'lowestupdatewithstrongest' => [
                    'selectedscales' => [
                        '271' => [
                            'value' => -3,
                            'primary' => true,
                            'toreport' => true,
                            'primarybecause' => 'lowestskill',
                        ],
                        '272' => [
                            'value' => -2,
                        ],
                        '273' => [
                            'value' => -1,
                        ],
                        '274' => [
                            'value' => 0,
                        ],
                        '275' => [
                            'value' => 1,
                        ],
                        '276' => [
                            'value' => 2,
                        ],
                        '277' => [
                            'value' => 3,
                        ],
                    ],
                    'quizsettings' => [
                        'catquiz_catscales' => 271,
                        'teststrategy' => '4',
                    ],
                    'primaryscaleidint' => LOCAL_CATQUIZ_PRIMARYCATSCALE_STRONGEST,
                    'expected' => [
                        '271' => [
                            'value' => -3,
                            'toreport' => true,
                            'primarybecause' => 'lowestskill',
                        ],
                        '272' => [
                            'value' => -2,
                        ],
                        '273' => [
                            'value' => -1,
                        ],
                        '274' => [
                            'value' => 0,
                        ],
                        '275' => [
                            'value' => 1,
                        ],
                        '276' => [
                            'value' => 2,
                        ],
                        '277' => [
                            'value' => 3,
                            'primary' => true,
                        ],
                    ],
                ],
        ];
    }
}
