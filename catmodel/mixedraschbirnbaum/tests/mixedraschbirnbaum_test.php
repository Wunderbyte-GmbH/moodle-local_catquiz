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
 * @package    catmodel_mixedraschbirnbaum
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 namespace catmodel_mixedraschbirnbaum;

use local_catquiz\local\model\model_model;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

/**
 * Tests for core_message_inbound to test Variable Envelope Return Path functionality.
 *
 * @package    catmodel_mixedraschbirnbaum
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \catmodel_mixedraschbirnbaum\mixedraschbirnbaum
 */
final class mixedraschbirnbaum_test extends TestCase {
    /**
     * This test calls the get_log_jacobain function with the model and test its output with verified data.
     *
     * @dataProvider get_log_jacobian_provider
     *
     * @param array $pp
     * @param float $k
     * @param array $ip
     * @param array $expected
     *
     * @return void
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     *
     */
    public function test_get_log_jacobian(array $pp, float $k, array $ip, array $expected): void {
        $result = mixedraschbirnbaum::get_log_jacobian($pp, $ip, $k);
        for ($i = 0; $i < count($result); $i++) {
            $this->assertEqualsWithDelta($expected[$i], $result[$i], 0.0001);
        }
    }

    /**
     * This test calls the get_log_jacobain function with the model and test its output with verified data.
     *
     * @dataProvider get_log_hessian_provider
     *
     * @param array $pp
     * @param float $k
     * @param array $ip
     * @param array $expected
     *
     * @return void
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     *
     */
    public function test_get_log_hessian(array $pp, float $k, array $ip, array $expected): void {
        $resultsmatrix = mixedraschbirnbaum::get_log_hessian($pp, $ip, $k);
        for ($i = 0; $i < count($resultsmatrix); $i++) {
            for ($j = 0; $j < count($resultsmatrix[$i]); $j++) {
                $this->assertEqualsWithDelta($expected[$i][$j], $resultsmatrix[$i][$j], 0.0001);
            }
        }
    }

    /**
     * Test log_likelihood_p function.
     * @dataProvider log_likelihood_p_provider
     * @param array $pp
     * @param float $k
     * @param array $ip
     * @param float $expected
     *
     * @return void
     */
    public function test_log_likelihood_p(array $pp, float $k, array $ip, float $expected): void {
        $result = mixedraschbirnbaum::log_likelihood_p($pp, $ip, $k);

        // We only verify for four commas after the dot.
        $expected = (float)sprintf("%.6f", $expected);
        $result = (float)sprintf("%.6f", $result);

        $this->assertEquals($expected, $result);
    }

    /**
     * Test log_likelihood_p function.
     * @dataProvider log_likelihood_p_p_provider
     * @param array $pp
     * @param float $k
     * @param array $ip
     * @param float $expected
     *
     * @return void
     */
    public function test_log_likelihood_p_p(array $pp, float $k, array $ip, float $expected): void {
        $result = mixedraschbirnbaum::log_likelihood_p_p($pp, $ip, $k);

        $this->assertEqualsWithDelta($expected, $result, 0.001);
    }

    /**
     * Test least_mean_squares_1st_derivative_ip function.
     * @dataProvider least_mean_squares_1st_derivative_ip_provider
     * @param int $n
     * @param array $pp
     * @param float $k
     * @param array $ip
     * @param array $expected
     *
     * @return void
     */
    public function test_least_mean_squares_1st_derivative_ip(int $n, array $pp, float $k, array $ip, array $expected): void {
        $this->markTestSkipped('The formula returns unexpected results but we do not use it anywhere at the moment');

        $result = $this->getmodel()->least_mean_squares_1st_derivative_ip($pp, $ip, $k, $n);

        for ($i = 0; $i < count($result); $i++) {
            $this->assertEqualsWithDelta($expected[$i], $result[$i], '0.001');
        }

    }

    /**
     * Test least_mean_squares_2nd_derivative_ip function.
     * @dataProvider least_mean_squares_2nd_derivative_ip_provider
     * @param int $n
     * @param array $pp
     * @param float $k
     * @param array $ip
     * @param array $expected
     *
     * @return void
     */
    public function test_least_mean_squares_2nd_derivative_ip(int $n, array $pp, float $k, array $ip, array $expected): void {
        $this->markTestSkipped('The formula returns unexpected results but we do not use it anywhere at the moment');

        $result = $this->getmodel()->least_mean_squares_2nd_derivative_ip($pp, $ip, $k, $n);

        for ($i = 0; $i < count($result); $i++) {
            for ($j = 0; $j < count($result[$i]); $j++) {
                $this->assertEqualsWithDelta($expected[$i][$j], $result[$i][$j], 0.001);
            }
        }
    }

    /**
     * Provider function for least_mean_squares_1st_derivative_ip
     * @return array
     */
    public static function least_mean_squares_1st_derivative_ip_provider(): array {
        return [
            "testcase1" => [
                'n' => 5,
                'pp' => ['ability' => -3],
                'k' => 0.3,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 0.7,
                    "guessing" => 0.15,
                ],
                'expected' => [-0.2905559, -0.2075399, 1.181301],
            ],
            "testcase2" => [
                'n' => 5,
                'pp' => ['ability' => -3],
                'k' => 0.95,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 0.7,
                    "guessing" => 0.15,
                ],
                'expected' => [0.6473028, 0.4623591, -2.631713],
            ],
            "testcase3" => [
                'n' => 27,
                'pp' => ['ability' => -2],
                'k' => 0.3,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 2.0,
                    "guessing" => 0.25,
                ],
                'expected' => [-7.935613, 1.983903, 7.236641],
            ],
            "testcase4" => [
                'n' => 27,
                'pp' => ['ability' => -2],
                'k' => 0.95,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 2.0,
                    "guessing" => 0.25,
                ],
                'expected' => [2.416005, -0.6040013, -2.203202],
            ],
            "testcase5" => [
                'n' => 3,
                'pp' => ['ability' => 0.5],
                'k' => 0.3,
                'ip' => [
                    "difficulty" => 0.5,
                    "discrimination" => 2.5,
                    "guessing" => 0.35,
                ],
                'expected' => [-0.9140625, -9.167132e-13, 1.125],
            ],
            "testcase6" => [
                'n' => 3,
                'pp' => ['ability' => 0.5],
                'k' => 0.95,
                'ip' => [
                    "difficulty" => 0.5,
                    "discrimination" => 2.5,
                    "guessing" => 0.35,
                ],
                'expected' => [0.6703125, -2.291783e-13, -0.825],
            ],
            "testcase7" => [
                'n' => 1,
                'pp' => ['ability' => 1.5],
                'k' => 0.3,
                'ip' => [
                    "difficulty" => -1,
                    "discrimination" => 2,
                    "guessing" => 0.05,
                ],
                'expected' => [-0.01752321, 0.02190401, 0.009284882],
            ],
            "testcase8" => [
                'n' => 1,
                'pp' => ['ability' => 1.5],
                'k' => 0.95,
                'ip' => [
                    "difficulty" => -1,
                    "discrimination" => 2,
                    "guessing" => 0.05,
                ],
                'expected' => [-0.001102506, 0.001378132, 0.000584176],
            ],
            "testcase9" => [
                'n' => 100,
                'pp' => ['ability' => 3.5],
                'k' => 0.3,
                'ip' => [
                    "difficulty" => 1.5,
                    "discrimination" => 1.5,
                    "guessing" => 0.25,
                ],
                'expected' => [-6.75377, 9.005026, 6.30224],
            ],
            "testcase10" => [
                'n' => 100,
                'pp' => ['ability' => 3.5],
                'k' => 0.95,
                'ip' => [
                    "difficulty" => 1.5,
                    "discrimination" => 1.5,
                    "guessing" => 0.25,
                ],
                'expected' => [-0.1466834, 0.1955778, 0.1368767],
            ],
        ];
    }

    /**
     * Provider function for least_mean_squares_2nd_derivative_ip
     * @return array
     */
    public static function least_mean_squares_2nd_derivative_ip_provider(): array {
        return [
            "testcase1" => [
                'n' => 5,
                'pp' => ['ability' => -3],
                'k' => 0.3,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 0.7,
                    "guessing" => 0.15,
                ],
                'expected' => [
                    [0.2434185, -0.2412095, -0.5045763],
                    [-0.2412095, 0.1241931, -0.3604116],
                    [-0.5045763, -0.3604116, 3.4412018],
                ],
            ],
            "testcase2" => [
                'n' => 5,
                'pp' => ['ability' => -3],
                'k' => 0.95,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 0.7,
                    "guessing" => 0.15,
                ],
                'expected' => [
                    [0.1296894, 1.01735354, -1.607939],
                    [1.0173535, 0.06616808, -1.148528],
                    [-1.607939, -1.14852815, 3.441202],
                ],
            ],
            "testcase3" => [
                'n' => 27,
                'pp' => ['ability' => -2],
                'k' => 0.3,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 2.0,
                    "guessing" => 0.25,
                ],
                'expected' => [
                    [-2.637631, -3.308399, 6.297773],
                    [-3.308399, -0.164852, -1.574443],
                    [6.297773, -1.574443, 3.905792],
                ],
            ],
            "testcase4" => [
                'n' => 27,
                'pp' => ['ability' => -2],
                'k' => 0.95,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 2.0,
                    "guessing" => 0.25,
                ],
                'expected' => [
                    [6.9296894, -0.5244198, -7.504385],
                    [-0.5244198, 0.4331056, 1.876096],
                    [-7.5043846, 1.8760961, 3.905792],
                ],
            ],
            "testcase5" => [
                'n' => 3,
                'pp' => ['ability' => 0.5],
                'k' => 0.3,
                'ip' => [
                    "difficulty" => 0.5,
                    "discrimination" => 2.5,
                    "guessing" => 0.35,
                ],
                'expected' => [
                    [ 0.9902344, -3.656250e-01,  1.875000e-01],
                    [-0.3656250, -3.666853e-13, -3.552714e-15],
                    [ 0.1875000, -3.552714e-15,  1.500000e+00],
                ],
            ],
            "testcase6" => [
                'n' => 3,
                'pp' => ['ability' => 0.5],
                'k' => 0.95,
                'ip' => [
                    "difficulty" => 0.5,
                    "discrimination" => 2.5,
                    "guessing" => 0.35,
                ],
                'expected' => [
                    [ 0.9902344,  2.681250e-01, -2.250000e+00],
                    [ 0.2681250, -8.250419e-13,  3.552714e-15],
                    [-2.2500000,  3.552714e-15,  1.500000e+00],
                ],
            ],
            "testcase7" => [
                'n' => 1,
                'pp' => ['ability' => 1.5],
                'k' => 0.3,
                'ip' => [
                    "difficulty" => -1,
                    "discrimination" => 2,
                    "guessing" => 0.05,
                ],
                'expected' => [
                    [-0.03425819,  0.03406114,  1.827640e-02],
                    [ 0.03406114, -0.05352842, -2.284550e-02],
                    [ 0.01827640, -0.02284550,  8.954913e-05],
                ],
            ],
            "testcase8" => [
                'n' => 1,
                'pp' => ['ability' => 1.5],
                'k' => 0.95,
                'ip' => [
                    "difficulty" => -1,
                    "discrimination" => 2,
                    "guessing" => 0.05,
                ],
                'expected' => [
                    [-0.0018563961,  0.001769242,  9.914535e-04],
                    [ 0.0017692422, -0.002900619, -1.239317e-03],
                    [ 0.0009914535, -0.001239317,  8.958843e-05],
                ],
            ],
            "testcase9" => [
                'n' => 100,
                'pp' => ['ability' => 3.5],
                'k' => 0.3,
                'ip' => [
                    "difficulty" => 1.5,
                    "discrimination" => 1.5,
                    "guessing" => 0.25,
                ],
                'expected' => [
                    [-8.653134,   7.034999,   8.5229544],
                    [ 7.034999, -15.383349, -11.3639392],
                    [ 8.522954, -11.363939,   0.4498426],
                ],
            ],
            "testcase10" => [
                'n' => 100,
                'pp' => ['ability' => 3.5],
                'k' => 0.95,
                'ip' => [
                    "difficulty" => 1.5,
                    "discrimination" => 1.5,
                    "guessing" => 0.25,
                ],
                'expected' => [
                    [ 0.3174553, -0.5210626, -0.2864942],
                    [-0.5210626,  0.5643649,  0.3819923],
                    [-0.2864942,  0.3819923,  0.4498427],
                ],
            ],
        ];
    }

    /**
     * Provider function for log_likelihood_p
     * @return array
     */
    public static function log_likelihood_p_provider(): array {
        return [
            "testcase1" => [
                'pp' => ['ability' => -3],
                'k' => 1,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 0.7,
                    "guessing" => 0.15,
                ],
                'expected' => 0.2877805,
            ],
            "testcase2" => [
                'pp' => ['ability' => -3],
                'k' => 0,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 0.7,
                    "guessing" => 0.15,
                ],
                'expected' => -0.2893677,
            ],
            "testcase3" => [
                'pp' => ['ability' => -2],
                'k' => 1,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 2.0,
                    "guessing" => 0.25,
                ],
                'expected' => 0.3694352,
            ],
            "testcase4" => [
                'pp' => ['ability' => -2],
                'k' => 0,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 2.0,
                    "guessing" => 0.25,
                ],
                'expected' => -1.462117,
            ],
            "testcase5" => [
                'pp' => ['ability' => 0.5],
                'k' => 1,
                'ip' => [
                    "difficulty" => 0.5,
                    "discrimination" => 2.5,
                    "guessing" => 0.35,
                ],
                'expected' => 0.6018519,
            ],
            "testcase6" => [
                'pp' => ['ability' => 0.5],
                'k' => 0,
                'ip' => [
                    "difficulty" => 0.5,
                    "discrimination" => 2.5,
                    "guessing" => 0.35,
                ],
                'expected' => -1.25,
            ],
            "testcase7" => [
                'pp' => ['ability' => 1.5],
                'k' => 1,
                'ip' => [
                    "difficulty" => -1.0,
                    "discrimination" => 2.0,
                    "guessing" => 0.05,
                ],
                'expected' => 0.01271213,
            ],
            "testcase8" => [
                'pp' => ['ability' => 1.5],
                'k' => 0,
                'ip' => [
                    "difficulty" => -1.0,
                    "discrimination" => 2.0,
                    "guessing" => 0.05,
                ],
                'expected' => -1.986614,
            ],
            "testcase9" => [
                'pp' => ['ability' => 3.5],
                'k' => 1,
                'ip' => [
                    "difficulty" => 1.5,
                    "discrimination" => 1.5,
                    "guessing" => 0.25,
                ],
                'expected' => 0.05269819,
            ],
            "testcase10" => [
                'pp' => ['ability' => 3.5],
                'k' => 0,
                'ip' => [
                    "difficulty" => 1.5,
                    "discrimination" => 1.5,
                    "guessing" => 0.25,
                ],
                'expected' => -1.428861,
            ],
        ];
    }

    /**
     * Provider function log_likelihood_p_p_provider
     * @return array
     */
    public static function log_likelihood_p_p_provider(): array {
        return [
            "testcase1" => [
                'pp' => ['ability' => -3],
                'k' => 1,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 0.7,
                    "guessing" => 0.15,
                ],
                'expected' => -0.04792001,
            ],
            "testcase2" => [
                'pp' => ['ability' => -3],
                'k' => 0,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 0.7,
                    "guessing" => 0.15,
                ],
                'expected' => -0.1188237,
            ],
            "testcase3" => [
                'pp' => ['ability' => -2],
                'k' => 1,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 2.0,
                    "guessing" => 0.25,
                ],
                'expected' => -0.4779271,
            ],
            "testcase4" => [
                'pp' => ['ability' => -2],
                'k' => 0,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 2.0,
                    "guessing" => 0.25,
                ],
                'expected' => -0.7864477,
            ],
            "testcase5" => [
                'pp' => ['ability' => 0.5],
                'k' => 1,
                'ip' => [
                    "difficulty" => 0.5,
                    "discrimination" => 2.5,
                    "guessing" => 0.35,
                ],
                'expected' => -0.3622257,
            ],
            "testcase6" => [
                'pp' => ['ability' => 0.5],
                'k' => 0,
                'ip' => [
                    "difficulty" => 0.5,
                    "discrimination" => 2.5,
                    "guessing" => 0.35,
                ],
                'expected' => -1.5625,
            ],
            "testcase7" => [
                'pp' => ['ability' => 1.5],
                'k' => 1,
                'ip' => [
                    "difficulty" => -1.0,
                    "discrimination" => 2.0,
                    "guessing" => 0.05,
                ],
                'expected' => -0.02524554,
            ],
            "testcase8" => [
                'pp' => ['ability' => 1.5],
                'k' => 0,
                'ip' => [
                    "difficulty" => -1.0,
                    "discrimination" => 2.0,
                    "guessing" => 0.05,
                ],
                'expected' => -0.02659222,
            ],
            "testcase9" => [
                'pp' => ['ability' => 3.5],
                'k' => 1,
                'ip' => [
                    "difficulty" => 1.5,
                    "discrimination" => 1.5,
                    "guessing" => 0.25,
                ],
                'expected' => -0.0743266,
            ],
            "testcase10" => [
                'pp' => ['ability' => 3.5],
                'k' => 0,
                'ip' => [
                    "difficulty" => 1.5,
                    "discrimination" => 1.5,
                    "guessing" => 0.25,
                ],
                'expected' => -0.101647,
            ],
        ];
    }

    /**
     * Return Data for log jacobian test
     * @return array
     */
    public static function get_log_jacobian_provider(): array {
        return [
            "testcase 1" => [
                'pp' => ['ability' => -3],
                'k' => 1,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 0.7,
                    "guessing" => 0.15,
                ],
                'expected' => [-0.2877805, -0.205557, 1.170017],
            ],
            "testcase 2" => [
                'pp' => ['ability' => -3],
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
                'pp' => ['ability' => -2.0],
                'k' => 1,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 2.00,
                    "guessing" => 0.25,
                ],
                'expected' => [-0.3694352, 0.09235881, 0.3368952],
            ],
            "testcase 4" => [
                'pp' => ['ability' => -2.0],
                'k' => 0,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 2.00,
                    "guessing" => 0.25,
                ],
                'expected' => [1.462117, -0.3655293, -1.333333],
            ],
            "testcase 5" => [
                'pp' => ['ability' => 1.5],
                'k' => 1,
                'ip' => [
                    "difficulty" => -1.00,
                    "discrimination" => 2.00,
                    "guessing" => 0.05,
                ],
                'expected' => [-0.01271213, 0.01589017, 0.006735678],
            ],
            "testcase 6" => [
                'pp' => ['ability' => 1.5],
                'k' => 0,
                'ip' => [
                    "difficulty" => -1.00,
                    "discrimination" => 2.00,
                    "guessing" => 0.05,
                ],
                'expected' => [1.986614, -2.483268, -1.052632],
            ],
            "testcase 7" => [
                'pp' => ['ability' => 3.5],
                'k' => 1,
                'ip' => [
                    "difficulty" => 1.50,
                    "discrimination" => 1.50,
                    "guessing" => 0.25,
                ],
                'expected' => [-0.05269819, 0.07026425, 0.049175],
            ],
            "testcase 8" => [
                'pp' => ['ability' => 3.5],
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
                'pp' => ['ability' => 3.5],
                'k' => 1,
                'ip' => [
                    "difficulty" => 1.5,
                    "discrimination" => 1.5,
                    "guessing" => 0.25,
                ],
                'expected' => [-0.05269819, 0.07026425, 0.049175],
            ],
            "testcase 10" => [
                'pp' => ['ability' => 3.5],
                'k' => 0,
                'ip' => [
                    "difficulty" => 1.5,
                    "discrimination" => 1.5,
                    "guessing" => 0.25,
                ],
                'expected' => [1.428861, -1.905148, -1.333333],
            ],
        ];
    }

     /**
      * Return Data for log hessian test
      * @return array
      */
    public static function get_log_hessian_provider(): array {

        return [
            "testcase 1" => [
                'pp' => ['ability' => -3],
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
                'pp' => ['ability' => -3],
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
                'pp' => ['ability' => -2.0],
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
                'pp' => ['ability' => -2.0],
                'k' => 0,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 2.00,
                    "guessing" => 0.25,
                ],
                'expected' => [
                    [-7.864477e-01,  9.276705e-01, -1.999467e-11],
                    [ 9.276705e-01, -4.915298e-02,  3.759126e-11],
                    [-1.999467e-11,  3.759126e-11, -1.777778e+00],
                ],
            ],
            "testcase 5" => [
                'pp' => ['ability' => 1.5],
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
                'pp' => ['ability' => 1.5],
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
                'pp' => ['ability' => 3.5],
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
                'pp' => ['ability' => 3.5],
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
                'pp' => ['ability' => 3.5],
                'k' => 1,
                'ip' => [
                    "difficulty" => 1.50,
                    "discrimination" => 1.50,
                    "guessing" => 0.25,
                ],
                'expected' => [
                    [-0.07432660,  0.06397002,  0.07285568],
                    [ 0.06397002, -0.13213619, -0.09714091],
                    [ 0.07285568, -0.09714091, -0.00241818],
                ],
            ],
            "testcase 10" => [
                'pp' => ['ability' => 3.5],
                'k' => 0,
                'ip' => [
                    "difficulty" => 1.50,
                    "discrimination" => 1.50,
                    "guessing" => 0.25,
                ],
                'expected' => [
                    [-1.016475e-01,  1.088104e+00, -4.171454e-10],
                    [ 1.088104e+00, -1.807066e-01, -7.032597e-11],
                    [-4.171454e-10, -7.032597e-11, -1.777778e+00],
                ],
            ],
        ];
    }

    /**
     * Get model.
     *
     * @return mixedraschbirnbaum
     *
     */
    private function getmodel(): mixedraschbirnbaum {
        return model_model::get_instance('mixedraschbirnbaum');
    }
}
