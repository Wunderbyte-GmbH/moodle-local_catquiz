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
 * @package catmodel_raschbirnbaumc
 * @covers \catmodel_raschbirnbaumc\raschbirnbaumc
 */
class raschbirnbaumc_test extends TestCase {


    /**
     * This test calls the get_log_jacobain function with the model and test its output with verified data.
     * @dataProvider get_log_jacobian_provider
     * @return void
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     */
    public function test_get_log_jacobian(float $pp, float $k, array $ip, array $expected) {

        $result = [];
        $callbacks = raschbirnbaumc::get_log_jacobian($pp, $k);

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
        $callbacksmatrix = raschbirnbaumc::get_log_hessian($pp, $k);

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
            "testcase 1" => [
                'pp' => -3,
                'k' => 1,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 0.7,
                    "guessing" => 0.15,
                ],
                'expected' => [-0.2877805, -0.205557, 1.170017],
            ],
            "testcase 2" => [
                'pp' => -3,
                'k' => 0,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 0.7,
                    "guessing" => 0.15,
                ],
                'expected' => [0.2893677, 0.2066912, -1.176471],
            ],
            // Add the remaining test cases...
            "testcase 3" => [
                'pp' => 0.5,
                'k' => 1,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 2.00,
                    "guessing" => 0.25,
                ],
                'expected' => [-0.3694352, 0.09235881, 0.3368952],
            ],
            "testcase 4" => [
                'pp' => 0.5,
                'k' => 0,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 2.00,
                    "guessing" => 0.25,
                ],
                'expected' => [1.462117, -0.3655293, -1.333333],
            ],
            "testcase 5" => [
                'pp' => 1.5,
                'k' => 1,
                'ip' => [
                    "difficulty" => -1.00,
                    "discrimination" => 2.00,
                    "guessing" => 0.05,
                ],
                'expected' => [-0.01271213, 0.01589017, 0.006735678],
            ],
            "testcase 6" => [
                'pp' => 1.5,
                'k' => 0,
                'ip' => [
                    "difficulty" => -1.00,
                    "discrimination" => 2.00,
                    "guessing" => 0.05,
                ],
                'expected' => [1.986614, -2.483268, -1.052632],
            ],
            "testcase 7" => [
                'pp' => 3.5,
                'k' => 1,
                'ip' => [
                    "difficulty" => 1.50,
                    "discrimination" => 1.50,
                    "guessing" => 0.25,
                ],
                'expected' => [-0.05269819, 0.07026425, 0.049175],
            ],
            "testcase 8" => [
                'pp' => 3.5,
                'k' => 0,
                'ip' => [
                    "difficulty" => 1.50,
                    "discrimination" => 1.50,
                    "guessing" => 0.25,
                ],
                'expected' => [1.428861, -1.905148, -1.333333],
            ],
            // Add the remaining test cases...
            "testcase 9" => [
                'pp' => 4.5,
                'k' => 1,
                'ip' => [
                    "difficulty" => 0.00,
                    "discrimination" => 1.00,
                    "guessing" => 0.10,
                ],
                'expected' => [0.06338283, 0.02728076, -0.02040469],
            ],
            "testcase 10" => [
                'pp' => 4.5,
                'k' => 0,
                'ip' => [
                    "difficulty" => 0.00,
                    "discrimination" => 1.00,
                    "guessing" => 0.10,
                ],
                'expected' => [0.06338283, 0.02728076, -0.02040469],
            ],
        ];
    }

     /**
      * Return Data for log hessian test
      * @return (int|float[]|float)[][]
      */
    public function get_log_hessian_provider() {

        return [
            "testcase 1" => [
                'pp' => -3,
                'k' => 1,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 0.7,
                    "guessing" => 0.15,
                ],
                'expected' => [
                    [-0.04792001, -0.44534354, 0.6752734],
                    [-0.44534354, -0.02444899, 0.4823382],
                    [0.67527344, 0.48233817, -1.3689409],
                ],
            ],
            "testcase 2" => [
                'pp' => -3,
                'k' => 0,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 0.7,
                    "guessing" => 0.15,
                ],
                'expected' => [
                    [-0.1188237, 0.3285083, -3.153033e-11],
                    [0.3285083, -0.06062435, 4.604317e-11],
                    [-3.153033e-11, 4.604317e-11, -1.384083],
                ],
            ],
            "testcase 3" => [
                'pp' => 0.5,
                'k' => 1,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 2.00,
                    "guessing" => 0.25,
                ],
                'expected' => [
                    [-0.47792710, -0.06523584, 0.6170413],
                    [-0.06523584, -0.02987044, -0.1542603],
                    [0.61704127, -0.15426032, -0.1134984],
                ],
            ],
            "testcase 4" => [
                'pp' => 0.5,
                'k' => 0,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 2.00,
                    "guessing" => 0.25,
                ],
                'expected' => [
                    [-2.659223e-02, 1.026547e+00, -2.186663e-08],
                    [1.026547e+00, -4.155035e-02, -1.338774e-08],
                    [-2.186663e-08, -1.338774e-08, -1.108033e+00],
                ],
            ],
            "testcase 5" => [
                'pp' => 1.5,
                'k' => 1,
                'ip' => [
                    "difficulty" => -1.00,
                    "discrimination" => 2.00,
                    "guessing" => 0.05,
                ],
                'expected' => [
                    [-0.02524554, 0.02520086, 0.01346682],
                    [0.02520086, -0.03944616, -0.01683352],
                    [0.01346682, -0.01683352, -4.536984e-05],
                ],
            ],
            "testcase 6" => [
                'pp' => 1.5,
                'k' => 0,
                'ip' => [
                    "difficulty" => -1.00,
                    "discrimination" => 2.00,
                    "guessing" => 0.05,
                ],
                'expected' => [
                    [-2.659223e-02, 1.026547e+00, -2.186663e-08],
                    [1.026547e+00, -4.155035e-02, -1.338774e-08],
                    [-2.186663e-08, -1.338774e-08, -1.108033e+00],
                ],
            ],
            "testcase 7" => [
                'pp' => 3.5,
                'k' => 1,
                'ip' => [
                    "difficulty" => 1.50,
                    "discrimination" => 1.50,
                    "guessing" => 0.25,
                ],
                'expected' => [
                    [-0.07432660, 0.06397002, 0.07285568],
                    [0.06397002, -0.13213619, -0.09714091],
                    [0.07285568, -0.09714091, -0.00241818],
                ],
            ],
            "testcase 8" => [
                'pp' => 3.5,
                'k' => 0,
                'ip' => [
                    "difficulty" => 1.50,
                    "discrimination" => 1.50,
                    "guessing" => 0.25,
                ],
                'expected' => [
                    [-1.016475e-01, 1.088104e+00, -4.171454e-10],
                    [1.088104e+00, -1.807066e-01, -7.032597e-11],
                    [-4.171454e-10, -7.032597e-11, -1.777778e+00],
                ],
            ],
            // Add the remaining test cases...
            "testcase 9" => [
                'pp' => 4.5,
                'k' => 1,
                'ip' => [
                    "difficulty" => 0.00,
                    "discrimination" => 1.00,
                    "guessing" => 0.10,
                ],
                'expected' => [
                    [0.03519739, -0.02254274, -0.02163859],
                    [-0.02254274, -0.03207032, -0.02379477],
                    [-0.02163859, -0.02379477, -0.00700323],
                ],
            ],
            "testcase 10" => [
                'pp' => 4.5,
                'k' => 0,
                'ip' => [
                    "difficulty" => 0.00,
                    "discrimination" => 1.00,
                    "guessing" => 0.10,
                ],
                'expected' => [
                    [0.03519739, -0.02254274, -0.02163859],
                    [-0.02254274, -0.03207032, -0.02379477],
                    [-0.02163859, -0.02379477, -0.00700323],
                ],
            ],
        ];
    }
}
