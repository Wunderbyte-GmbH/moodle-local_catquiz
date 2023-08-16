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
 * @package    catmodel_raschbirnbaumb
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 namespace catmodel_raschbirnbaumb;

use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

/**
 * @package catmodel_raschbirnbaumb
 * @covers \catmodel_raschbirnbaumb\raschbirnbaumb
 */
class raschbirnbaumb_test extends TestCase {


    /**
     * This test calls the get_log_jacobain function with the model and test its output with verified data.
     * @dataProvider get_log_jacobian_provider
     * @return void
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     */
    public function test_get_log_jacobian(float $pp, float $k, array $ip, array $expected) {

        $result = [];
        $callbacks = raschbirnbaumb::get_log_jacobian($pp, $k);

        $result = array_map(fn ($a) => (float)sprintf("%.6f", $a($ip)), $callbacks);

        // Limit the values.
        $expected = array_map(fn ($a) => (float)sprintf("%.6f", $a), $expected);

        $this->assertEquals($expected, $result);
    }

    /**
     * This test calls the get_log_jacobain function with the model and test its output with verified data.
     * @dataProvider get_log_hessian_provider
     * @return void
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     */
    public function test_get_log_hessian(float $pp, float $k, array $ip, array $expected) {

        $result = [];
        $callbacksmatrix = raschbirnbaumb::get_log_hessian($pp, $k);

        foreach ($callbacksmatrix as $callbacks) {
            $result[] = array_map(fn ($a) => (float)sprintf("%.6f", $a($ip)), $callbacks);
        }

        // Limit the values.
        $expectedmatrix = [];
        foreach ($expected as $rows) {
            $expectedmatrix[] = array_map(fn ($a) => (float)sprintf("%.6f", $a), $rows);
        }

        $this->assertEquals($expectedmatrix, $result);
    }

    /**
     * Return Data for log jacobian test
     * @return (int|float[]|float)[][]
     */
    public function get_log_jacobian_provider() {
        return [
            // Test case 1.
            "testcase 1" => [
                'pp' => -3,
                'k' => 1,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 0.7,
                ],
                'expected' => [-0.4106323, -0.2933088],
            ],
            // Test case 2.
            "testcase 2" => [
                'pp' => -3,
                'k' => 0,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 0.7,
                ],
                'expected' => [0.2893677, 0.2066912],
            ],
            // Test case 3.
            "testcase 3" => [
                'pp' => -2,
                'k' => 1,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 2,
                ],
                'expected' => [-0.5378828, 0.1344707],
            ],
            // Test case 4.
            "testcase 4" => [
                'pp' => -2,
                'k' => 0,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 2,
                ],
                'expected' => [1.462117, -0.365529],
            ],
            // Test case 5.
            "testcase 5" => [
                'pp' => 0.5,
                'k' => 1,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 2,
                ],
                'expected' => [-0.004945, 0.007418],
            ],
            // Test case 6.
            "testcase 6" => [
                'pp' => 0.5,
                'k' => 0,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 2,
                ],
                'expected' => [1.995055, -2.992582],
            ],
            // Test case 7.
            "testcase 7" => [
                'pp' => 1.5,
                'k' => 1,
                'ip' => [
                    "difficulty" => -1,
                    "discrimination" => 2,
                ],
                'expected' => [-0.0133857, 0.01673213],
            ],
            // Test case 8.
            "testcase 8" => [
                'pp' => 1.5,
                'k' => 0,
                'ip' => [
                    "difficulty" => -1,
                    "discrimination" => 2,
                ],
                'expected' => [1.986614, -2.483268],
            ],
            // Test case 9.
            "testcase 9" => [
                'pp' => 3.5,
                'k' => 1,
                'ip' => [
                    "difficulty" => 1.5,
                    "discrimination" => 1.5,
                ],
                'expected' => [-0.07113881, 0.09485175],
            ],
            // Test case 10.
            "testcase 10" => [
                'pp' => 3.5,
                'k' => 0,
                'ip' => [
                    "difficulty" => 1.5,
                    "discrimination" => 1.5,
                ],
                'expected' => [1.428861, -1.905148],
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
            "testcase 1" => [
                'pp' => -3,
                'k' => 1,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 0.7,
                ],
                'expected' => [
                    [-0.1188237, -0.67149167],
                    [-0.6714917, -0.06062435],
                ],
            ],
            // Test case 2.
            "testcase 2" => [
                'pp' => -3,
                'k' => 0,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 0.7,
                ],
                'expected' => [
                    [-0.1188237, 0.32850833],
                    [0.3285083, -0.06062435],
                ],
            ],
            // Test case 3.
            "testcase 3" => [
                'pp' => -2,
                'k' => 1,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 2,
                ],
                'expected' => [
                    [-0.78644773, -0.07232949],
                    [-0.07232949, -0.04915298],
                ],
            ],
            // Test case 4.
            "testcase 4" => [
                'pp' => -2,
                'k' => 0,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 2,
                ],
                'expected' => [
                    [-0.7864477, 0.927671],
                    [0.927671, -0.04915298],
                ],
            ],
            // Test case 5.
            "testcase 5" => [
                'pp' => 0.5,
                'k' => 1,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 2,
                ],
                'expected' => [
                    [-0.009866, 0.012326],
                    [0.012326, -0.022199],
                ],
            ],
            // Test case 6.
            "testcase 6" => [
                'pp' => 0.5,
                'k' => 0,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 2,
                ],
                'expected' => [
                    [-0.009866, 1.012326],
                    [1.012326, -0.022199],
                ],
            ],
            // Test case 7.
            "testcase 7" => [
                'pp' => 1.5,
                'k' => 1,
                'ip' => [
                    "difficulty" => -1,
                    "discrimination" => 2,
                ],
                'expected' => [
                    [-0.02659223, 0.02654743],
                    [0.02654743, -0.04155035],
                ],
            ],
            // Test case 8.
            "testcase 8" => [
                'pp' => 1.5,
                'k' => 0,
                'ip' => [
                    "difficulty" => -1,
                    "discrimination" => 2,
                ],
                'expected' => [
                    [-0.02659222, 1.02654743],
                    [1.02654743, -0.04155035],

                ],
            ],
            // Test case 9.
            "testcase 9" => [
                'pp' => 3.5,
                'k' => 1,
                'ip' => [
                    "difficulty" => 1.5,
                    "discrimination" => 1.5,
                ],
                'expected' => [
                    [-0.101647, 0.088104],
                    [0.08810411, -0.18070664],
                ],
            ],
            // Test case 10.
            "testcase 10" => [
                'pp' => 3.5,
                'k' => 0,
                'ip' => [
                    "difficulty" => 1.5,
                    "discrimination" => 1.5,
                ],
                'expected' => [
                    [-0.101647, 1.0881041],
                    [1.0881041, -0.1807066],
                ],
            ],
        ];
    }
}
