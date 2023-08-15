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
 * Tests for core_message_inbound to test Variable Envelope Return Path functionality.
 *
 * @package    catmodel_raschbirnbaumc
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace catmodel_raschbirnbaumc;

use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

/**
 * Class test_models for catmodel_raschbirnbaumc.
 *
 * @package    catmodel_raschbirnbaumc
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class test_models extends TestCase {

    /**
     * This test calls the get_log_jacobain function with the model and test its output with verified data.
     * @return void
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     */
    public function test_get_log_jacobian() {
        // Test cases.
        $testcases = [
            // Test case 1.
            [
                'pp' => 0.5,
                'k' => 0.8,
                'ip' => [
                    "difficulty" => 1,
                    "discrimination" => 0.5,
                    "guessing" => 0.5,
                ],
                'expected' => 0.21891174955710097,
            ],
            // Test case 2.
            [
                'pp' => 1.2,
                'k' => 1.5,
                'ip' => [
                    "difficulty" => 1,
                    "discrimination" => 0.5,
                    "guessing" => 0.5,
                ],
                'expected' => -0.08176375200410264,
            ],
        ];

        foreach ($testcases as $testcase) {
            $pp = $testcase['pp'];
            $k = $testcase['k'];
            $ip = $testcase['ip'];
            $expected = $testcase['expected'];

            $result = raschbirnbaumc::get_log_jacobian($pp, $k)[0]($ip);

            // We only verify for four commas after the dot.
            $expected = sprintf("%.4f", $expected);
            $result = sprintf("%.4f", $result);

            $this->assertEquals($expected, $result);
        }
    }
}
