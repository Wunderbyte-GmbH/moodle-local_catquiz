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
 * Tests the matrixcat functionality.
 *
 * @package    local_catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use basic_testcase;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

/**
 * Tests the matrixcat functionality.
 *
 * @package    local_catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_catquiz\matrixcat
 *
 */
class matrixcat_test extends basic_testcase {


    /**
     * Test if callable arrays are built properly.
     *
     * @return void
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     */
    public function test_build_callable_array() {
        $fnarray = [fn ($x) => 1 * $x, fn ($x) => 2 * $x, fn ($x) => 3 * $x];
        $expected = [5, 10, 15];
        $callablearray = matrixcat::build_callable_array($fnarray);
        $result = $callablearray(5);
        $this->assertEquals($expected, $result);
    }

    /**
     * Checks if the multi_sum() function works as expected.
     *
     * @dataProvider multisumprovider
     *
     * @param mixed $expected
     * @param mixed $a
     * @param mixed|null $b
     * @param mixed|null $c
     * @param array $options
     *
     * @return mixed
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     *
     */
    public function test_multi_sum($expected, $a, $b = null, $c = null, $options = []) {
        if (array_key_exists('callable_arg', $options)) {
            $x = $options['callable_arg'];
            $this->assertSame($expected, matrixcat::multi_sum($a($x), $b($x)));
        } else {
            if (is_null($b)) {
                $this->assertSame($expected, matrixcat::multi_sum($a));
            } else if (is_null($c)) {
                $this->assertSame($expected, matrixcat::multi_sum($a, $b));
            } else {
                $this->assertSame($expected, matrixcat::multi_sum($a, $b, $c));
            }
        }
    }

    /**
     * Data Provider.
     *
     * @return array
     */
    public static function multisumprovider(): array {
        $fna = fn ($x) => [[1 + $x, 2], [3, 4 * $x]];
        $fnb = fn ($x) => [[5, 6 - $x], [7 * $x, 8]];

        return [
            'simple' => [
                'expected' => 10,
                'a' => 2,
                'b' => 5,
                'c' => 3,
            ],
            'single array' => [
                'expected' => 12,
                'a' => [1, 4, 7],
            ],
            'multiple arrays' => [
                'expected' => [12, 15, 18],
                'a' => [1, 2, 3],
                'b' => [4, 5, 6],
                'c' => [7, 8, 9],
            ],
            'matrices' => [
                'expected' => [[6, 8], [10, 12]],
                'a' => [[1, 2], [3, 4]],
                'b' => [[5, 6], [7, 8]],
                'c' => null,
            ],
            'callables' => [
                'expected' => [[9, 5], [24, 20]],
                'a' => $fna,
                'b' => $fnb,
                'c' => null,
                'options' => ['callable_arg' => 3],
            ],
        ];
    }
}
