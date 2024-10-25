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
 * Tests the mathcat functionality.
 *
 * @package    local_catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use basic_testcase;

/**
 * Tests the mathcat functionality.
 *
 * @package    local_catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_catquiz\mathcat
 *
 */
final class mathcat_test extends basic_testcase {


    /**
     * Test if the newton raphson multi stable function returns expected outputs.
     *
     * @return void
     */
    public function test_newton_raphson_multi_stable(): void {
        $result = mathcat::newton_raphson_multi_stable(
            fn ($x) => [0],
            fn ($x) => [0],
            ['difficulty' => 0]
        );
        $expected = ['difficulty' => 0];
        $this->assertEquals($expected, $result);
    }

    /**
     * Test if array_to_vector and vector_to_array work as expected
     *
     * @dataProvider conversion_of_array_to_vector_provider
     *
     * @param array $given
     * @param array $expected
     * @param array $structure
     * @return void
     */
    public function test_conversion_of_array_to_vector($given, $expected, $structure): void {
        $array = $given;
        $arraystructure = mathcat::array_to_vector($array);
        $this->assertEquals($structure, $arraystructure);

        $array = mathcat::vector_to_array($array, $arraystructure);
        $this->assertEquals($expected, $array);
    }

    /**
     * Data provider
     *
     * @return array
     */
    public static function conversion_of_array_to_vector_provider(): array {
        return [
            // Simple cases: int, float, linear indexed array, linear assoc array.
            'int' => ['given' => [9], 'expected' => [9.0], 'structure' => [0 => 0]],
            'float' => ['given' => [9.2], 'expected' => [9.2], 'structure' => [0 => 0]],
            'linear indexed array' => [
                'given' => [7, 8, '9.3'],
                'expected' => [7.0, 8.0, 9.3],
                'structure' => [0 => 0, 1 => 1, 2 => 2],
            ],
            'linear assoc array' => ['given' => ['first' => 3, 'second' => -5, 'third' => 7.5],
                'expected' => ['first' => 3.0, 'second' => -5.0, 'third' => 7.5],
                'structure' => ['first' => 0, 'second' => 1, 'third' => 2]],

            // Complex cases: nested array, modified and reordered array.
            'nested array' => [
                'given' => [
                    5, 'stairways' => 20, 'first floor' => [
                        'kitchen' => 6,
                        'dining' => 15,
                        'wash room' => 4,
                    ],
                    'second foor' => [
                        'sleeping room' => 12,
                        'hobby room' => [
                            'TV corner' => 4.5,
                            'karaoke box' => 5.3,
                        ],
                    ],
                    'basement' => 25.7,
                ],
                'expected' => [
                    5.0,
                    'stairways' => 20.0,
                    'first floor' => [
                        'kitchen' => 6.0,
                        'dining' => 15.0,
                        'wash room' => 4.0,
                    ],
                    'second foor' => [
                        'sleeping room' => 12.0,
                        'hobby room' => [
                            'TV corner' => 4.5,
                            'karaoke box' => 5.3,
                        ],
                    ],
                    'basement' => 25.7,
                ],
                'structure' => [
                    0 => 0,
                    'stairways' => 1,
                    'first floor' => [
                        'kitchen' => 2,
                        'dining' => 3,
                        'wash room' => 4,
                    ],
                    'second foor' => [
                        'sleeping room' => 5,
                        'hobby room' => [
                            'TV corner' => 6,
                            'karaoke box' => 7,
                        ],
                    ],
                    'basement' => 8,
                ],
            ],

            'modified and reordered' => [
                'given' => [0, 'first' => 2, 'second' => [0 => 7, 2 => 8, 1 => 9], 'third' => 5],
                'expected' => [0 => 0.0, 'first' => 2.0, 'second' => [0 => 7.0, 1 => 9.0, 2 => 8.0], 'third' => 5.0],
                'structure' => [0 => 0, 'first' => 1, 'second' => [0 => 2, 1 => 4, 2 => 3], 'third' => 5],
            ],

            // Forbidden cases: strings in array, empty arrays.
            'forbidden because of string' => [
                'given' => ['test' => 'test', 'legid' => 3],
                'expected' => [],
                'structure' => [],
            ],

            'forbidden because of empty array' => [
                'given' => ['test' => []],
                'expected' => [],
                'structure' => [],
            ],
        ];
    }
}
