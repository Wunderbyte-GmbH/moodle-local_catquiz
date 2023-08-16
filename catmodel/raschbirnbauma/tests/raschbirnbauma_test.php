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
 * @package    catmodel_raschbirnbauma
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 namespace catmodel_raschbirnbauma;

use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

/**
 * @package catmodel_raschbirnbauma
 * @covers \catmodel_raschbirnbauma\raschbirnbauma
 */
class raschbirnbauma_test extends TestCase {


    /**
     * This test calls the get_log_jacobain function with the model and test its output with verified data.
     * @dataProvider get_log_jacobian_provider
     * @return void
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     */
    public function test_get_log_jacobian(float $pp, float $k, array $ip, float $expected) {

        $result = raschbirnbauma::get_log_jacobian($pp, $k)[0]($ip);

        // We only verify for four commas after the dot.
        $expected = (float)sprintf("%.6f", $expected);
        $result = (float)sprintf("%.6f", $result);

        $this->assertEquals($expected, $result);
    }

    /**
     * This test calls the get_log_jacobain function with the model and test its output with verified data.
     * @dataProvider get_log_hessian_provider
     * @return void
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     */
    public function test_get_log_hessian(float $pp, float $k, array $ip, float $expected) {

        $result = raschbirnbauma::get_log_hessian($pp, $k)[0][0]($ip);

        // We only verify for four commas after the dot.
        $expected = (float)sprintf("%.6f", $expected);
        $result = (float)sprintf("%.6f", $result);

        $this->assertEquals($expected, $result);
    }

    /**
     * Return Data for log jacobian test
     * @return (int|float[]|float)[][]
     */
    public function get_log_jacobian_provider() {
        return [
            // Test case 1.
            [
                'pp' => -3,
                'k' => 1,
                'ip' => ["difficulty" => -2.5],
                'expected' => -0.6224593,
            ],
            // Test case 2.
            [
                'pp' => -3,
                'k' => 0,
                'ip' => ["difficulty" => -2.5],
                'expected' => 0.3775407,
            ],
            // Test case 3.
            [
                'pp' => -2,
                'k' => 1,
                'ip' => ["difficulty" => -2.5],
                'expected' => -0.3775407,
            ],
            // Test case 4.
            [
                'pp' => -2,
                'k' => 0,
                'ip' => ["difficulty" => -2.5],
                'expected' => 0.6224593,
            ],
            // Test case 5.
            [
                'pp' => 0.5,
                'k' => 1,
                'ip' => ["difficulty" => 0.5],
                'expected' => -0.5,
            ],
            // Test case 6.
            [
                'pp' => 0.5,
                'k' => 0,
                'ip' => ["difficulty" => 0.5],
                'expected' => 0.5,
            ],
            // Test case 7.
            [
                'pp' => 1.5,
                'k' => 1,
                'ip' => ["difficulty" => -1],
                'expected' => -0.07585818,
            ],
            // Test case 8.
            [
                'pp' => 1.5,
                'k' => 0,
                'ip' => ["difficulty" => -1],
                'expected' => 0.9241418,
            ],
            // Test case 9.
            [
                'pp' => 3.5,
                'k' => 1,
                'ip' => ["difficulty" => 1.5],
                'expected' => -0.1192029,
            ],
            // Test case 10.
            [
                'pp' => 3.5,
                'k' => 0,
                'ip' => ["difficulty" => 1.5],
                'expected' => 0.8807971,
            ],
        ];
    }

     /**
      * Return Data for log hessian test
      * @return (int|float[]|float)[][]
      */
    public function get_log_hessian_provider() {

        return [
            // Test case 1.
            [
                'pp' => -3,
                'k' => 1,
                'ip' => ["difficulty" => -2.5],
                'expected' => -0.2350037,
            ],
            // Test case 2.
            [
                'pp' => -3,
                'k' => 0,
                'ip' => ["difficulty" => -2.5],
                'expected' => -0.2350037,
            ],
            // Test case 3.
            [
                'pp' => -2,
                'k' => 1,
                'ip' => ["difficulty" => -2.5],
                'expected' => -0.2350037,
            ],
            // Test case 4.
            [
                'pp' => -2,
                'k' => 0,
                'ip' => ["difficulty" => -2.5],
                'expected' => -0.2350037,
            ],
            // Test case 5.
            [
                'pp' => 0.5,
                'k' => 1,
                'ip' => ["difficulty" => 0.5],
                'expected' => -0.25,
            ],
            // Test case 6.
            [
                'pp' => 0.5,
                'k' => 0,
                'ip' => ["difficulty" => 0.5],
                'expected' => -0.25,
            ],
            // Test case 7.
            [
                'pp' => 1.5,
                'k' => 1,
                'ip' => ["difficulty" => -1],
                'expected' => -0.07010372,
            ],
            // Test case 8.
            [
                'pp' => 1.5,
                'k' => 0,
                'ip' => ["difficulty" => -1],
                'expected' => -0.07010372,
            ],
            // Test case 9.
            [
                'pp' => 3.5,
                'k' => 1,
                'ip' => ["difficulty" => 1.5],
                'expected' => -0.1049936,
            ],
            // Test case 10.
            [
                'pp' => 3.5,
                'k' => 0,
                'ip' => ["difficulty" => 1.5],
                'expected' => -0.1049936,
            ],
        ];
    }
}
