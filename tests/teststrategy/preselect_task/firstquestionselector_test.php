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
 * Tests the question pre-select task firstquestionselector.
 *
 * @package    catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use basic_testcase;
use local_catquiz\teststrategy\preselect_task\firstquestionselector;
use SebastianBergmann\RecursionContext\InvalidArgumentException;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * @package local_catquiz
 * @covers \local_catquiz\teststrategy\preselect_task\firstquestionselector
 */
class firstquestionselector_test extends basic_testcase {

    /**
     * @dataProvider median_ability_of_personparams_is_calculated_correctly_provider
     * @param float $expected
     * @param array<object> $personparams
     * @return void
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     */
    public function test_median_ability_of_personparams_is_calculated_correctly(
        float $expected,
        array $personparams
    ) {
        $firstquestionselector = new firstquestionselector();
        $this->assertEquals(
            $expected,
            $firstquestionselector->get_median_ability_of_test($personparams)
        );
    }

    public function median_ability_of_personparams_is_calculated_correctly_provider() {
        return [
            'single value 1' => [
                'expected' => 0.0,
                'personparams' => [
                    (object) ['ability' => 0.0],
                ]
            ],
            'single value 2' => [
                'expected' => -1.0,
                'personparams' => [
                    (object) ['ability' => -1.0],
                ]
            ],
            'two values 1' => [
                'expected' => -1.5,
                'personparams' => [
                    (object) ['ability' => -1.0],
                    (object) ['ability' => -2.0],
                ]
            ],
            'two values 2' => [
                'expected' => 2.0,
                'personparams' => [
                    (object) ['ability' => 0.0],
                    (object) ['ability' => 4.0],
                ]
            ],
            'three values 1' => [
                'expected' => 2.0,
                'personparams' => [
                    (object) ['ability' => 1.0],
                    (object) ['ability' => 2.0],
                    (object) ['ability' => 3.0],
                ]
            ],
            'three values 2' => [
                'expected' => 3.0,
                'personparams' => [
                    (object) ['ability' => -2.0],
                    (object) ['ability' => 3.0],
                    (object) ['ability' => 4.0],
                ]
            ],
            'three values 3' => [
                'expected' => -1.0,
                'personparams' => [
                    (object) ['ability' => -2.0],
                    (object) ['ability' => -1.0],
                    (object) ['ability' => 3.0],
                ]
            ],
        ];
    }
}
