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
use local_catquiz\local\model\model_item_response;
use local_catquiz\local\model\model_person_param;
use local_catquiz\local\model\model_raschmodel;
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
    use \local_catquiz\derivative_fd_trait;

    /**
     * Tests that the model calculates the item parameters correctly.
     *
     * @dataProvider calculate_params_returns_expected_values_provider
     *
     * @param array $itemresponse
     * @param array $expected
     *
     * @return void
     */
    public function test_calculate_params_returns_expected_values($itemresponse, array $expected): void {
        $raschbirnbaum = $this->getmodel();
        $result = $raschbirnbaum->calculate_params($itemresponse);
        $this->assertEqualsWithDelta($expected['difficulty'], $result['difficulty'], 0.0001);
        $this->assertEqualsWithDelta($expected['discrimination'], $result['discrimination'], 0.0001);
        $this->assertEqualsWithDelta($expected['guessing'], $result['guessing'], 0.0001);
    }

    /**
     * Provider for test_calculate_params_returns_expected_values
     *
     * @return array
     */
    public static function calculate_params_returns_expected_values_provider(): array {
        return [
                [
                    'itemresponse' => [new model_item_response('Item1', 0.3, (new model_person_param('1', 1))->set_ability(0.2))],
                    'expected' => ['difficulty' => 0.2017, 'discrimination' => 0.0057, 'guessing' => 0.0],
                ],
        ];
    }

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
                    [ 0.9902344, -3.656250e-01, 1.875000e-01],
                    [-0.3656250, -3.666853e-13, -3.552714e-15],
                    [ 0.1875000, -3.552714e-15, 1.500000e+00],
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
                    [ 0.9902344, 2.681250e-01, -2.250000e+00],
                    [ 0.2681250, -8.250419e-13, 3.552714e-15],
                    [-2.2500000, 3.552714e-15, 1.500000e+00],
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
                    [-0.03425819, 0.03406114, 1.827640e-02],
                    [ 0.03406114, -0.05352842, -2.284550e-02],
                    [ 0.01827640, -0.02284550, 8.954913e-05],
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
                    [-0.0018563961, 0.001769242, 9.914535e-04],
                    [ 0.0017692422, -0.002900619, -1.239317e-03],
                    [ 0.0009914535, -0.001239317, 8.958843e-05],
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
                    [-8.653134, 7.034999, 8.5229544],
                    [ 7.034999, -15.383349, -11.3639392],
                    [ 8.522954, -11.363939, 0.4498426],
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
                    [-0.5210626, 0.5643649, 0.3819923],
                    [-0.2864942, 0.3819923, 0.4498427],
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
                    [-7.864477e-01, 9.276705e-01, -1.999467e-11],
                    [ 9.276705e-01, -4.915298e-02, 3.759126e-11],
                    [-1.999467e-11, 3.759126e-11, -1.777778e+00],
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
        ];
    }

    /**
     * Verifies the analytic Fisher information against an independent numeric reference.
     *
     * For a dichotomous item the item (Fisher) information is
     *   I(theta) = P'(theta)^2 / (P(theta) * (1 - P(theta)))
     * with P = P(Y = 1). P'(theta) is approximated here by a central finite
     * difference of the model's own likelihood(), so the numeric path shares no
     * code with fisher_info(). This test fails if the 3PL information formula is
     * reverted to the (incorrect) difficulty-based expression.
     *
     * @dataProvider fisher_info_numeric_provider
     *
     * @param array $pp
     * @param array $ip
     *
     * @return void
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function test_fisher_info_numeric(array $pp, array $ip): void {
        $model = $this->getmodel();

        // Numeric reference: I = P'^2 / (P (1 - P)), P' via central difference.
        $h = 1e-6;
        $p = mixedraschbirnbaum::likelihood($pp, $ip, 1.0);
        $pplus = mixedraschbirnbaum::likelihood(['ability' => $pp['ability'] + $h], $ip, 1.0);
        $pminus = mixedraschbirnbaum::likelihood(['ability' => $pp['ability'] - $h], $ip, 1.0);
        $dp = ($pplus - $pminus) / (2 * $h);
        $numeric = ($dp ** 2) / ($p * (1 - $p));

        $analytic = $model->fisher_info($pp, $ip);

        // Tolerance derived from the plugin's own item-parameter precision.
        $delta = 10 ** (-model_raschmodel::PRECISION);
        $this->assertEqualsWithDelta($numeric, $analytic, $delta);
    }

    /**
     * Dynamic but deterministic parameter grid for the numeric Fisher test.
     *
     * @return array
     */
    public static function fisher_info_numeric_provider(): array {
        $abilities = [-2.1, -0.35, 0.0, 0.8, 2.0];
        $items = [
            ['difficulty' => 0.0, 'discrimination' => 2.0, 'guessing' => 0.25],
            ['difficulty' => 0.5, 'discrimination' => 1.5, 'guessing' => 0.20],
            ['difficulty' => -0.3, 'discrimination' => 1.0, 'guessing' => 0.10],
            ['difficulty' => 1.2, 'discrimination' => 0.8, 'guessing' => 0.25],
            ['difficulty' => -0.8, 'discrimination' => 1.7, 'guessing' => 0.00],
        ];
        $cases = [];
        foreach ($items as $i => $ip) {
            foreach ($abilities as $j => $ability) {
                $cases["item{$i}-ability{$j}"] = [
                    'pp' => ['ability' => $ability],
                    'ip' => $ip,
                ];
            }
        }
        return $cases;
    }

    /**
     * Regression guard for the historical 3PL Fisher-information bug.
     *
     * With a = 0, b = 2, c = 0.25, theta = 0 the true information is 0.6, whereas
     * the previous implementation returned difficulty^2 * ... = 0. This pins the
     * exact value and guarantees the result is not the degenerate zero.
     *
     * @return void
     * @throws ExpectationFailedException
     */
    public function test_fisher_info_regression_zero_difficulty(): void {
        $model = $this->getmodel();
        $pp = ['ability' => 0.0];
        $ip = ['difficulty' => 0.0, 'discrimination' => 2.0, 'guessing' => 0.25];
        $info = $model->fisher_info($pp, $ip);
        $this->assertEqualsWithDelta(0.6, $info, 10 ** (-model_raschmodel::PRECISION));
        $this->assertGreaterThan(0.0, $info);
    }

    /**
     * Verifies get_log_jacobian() against the numeric gradient of log_likelihood().
     *
     * @dataProvider derivative_cases_provider
     *
     * @param array $pp person ability parameter
     * @param array $ip item parameters
     * @param float $response observed response (0.0 or 1.0)
     *
     * @return void
     */
    public function test_get_log_jacobian_numeric(array $pp, array $ip, float $response): void {
        $keys = ['difficulty', 'discrimination', 'guessing'];
        $x = [];
        foreach ($keys as $k) {
            $x[$k] = $ip[$k];
        }
        $f = function (array $v) use ($pp, $response, $keys) {
            $ip = array_combine($keys, $v);
            return mixedraschbirnbaum::log_likelihood($pp, $ip, $response);
        };
        $numeric = $this->fd_gradient($f, $x);
        $analytic = mixedraschbirnbaum::get_log_jacobian($pp, $ip, $response);
        $this->assert_gradient_close($numeric, $analytic);
    }

    /**
     * Verifies get_log_hessian() against the numeric Hessian of log_likelihood().
     *
     * @dataProvider derivative_cases_provider
     *
     * @param array $pp person ability parameter
     * @param array $ip item parameters
     * @param float $response observed response (0.0 or 1.0)
     *
     * @return void
     */
    public function test_get_log_hessian_numeric(array $pp, array $ip, float $response): void {
        $keys = ['difficulty', 'discrimination', 'guessing'];
        $x = [];
        foreach ($keys as $k) {
            $x[$k] = $ip[$k];
        }
        $f = function (array $v) use ($pp, $response, $keys) {
            $ip = array_combine($keys, $v);
            return mixedraschbirnbaum::log_likelihood($pp, $ip, $response);
        };
        $numeric = $this->fd_hessian($f, $x);
        $analytic = mixedraschbirnbaum::get_log_hessian($pp, $ip, $response);
        $this->assert_hessian_close($numeric, $analytic);
    }

    /**
     * Dynamic but deterministic (item parameters x ability x response) grid.
     *
     * @return array
     */
    public static function derivative_cases_provider(): array {
        $abilities = [-2.1, -0.35, 0.0, 0.8, 2.0];
        $responses = [0.0, 1.0];
        $items = self::derivative_item_sets();
        $cases = [];
        foreach ($items as $label => $ip) {
            foreach ($abilities as $ai => $ability) {
                foreach ($responses as $response) {
                    $name = sprintf('%s-a%d-y%d', $label, $ai, (int) $response);
                    $cases[$name] = [
                        'pp' => ['ability' => $ability],
                        'ip' => $ip,
                        'response' => $response,
                    ];
                }
            }
        }
        return $cases;
    }
    /**
     * Item parameter sets for the derivative grid.
     *
     * @return array
     */
    private static function derivative_item_sets(): array {
        return [
            'lowc' => ['difficulty' => -0.4, 'discrimination' => 1.0, 'guessing' => 0.10],
            'midc' => ['difficulty' => 0.5, 'discrimination' => 1.5, 'guessing' => 0.20],
            'highc' => ['difficulty' => 1.2, 'discrimination' => 0.8, 'guessing' => 0.25],
        ];
    }

    /**
     * Verifies lors_1st_derivative_ip() against the numeric gradient of
     * lors_residuals() with respect to the item parameters (a, b, c).
     *
     * The LORS residual is independent of the guessing parameter c, so the
     * analytic d/dc entry must be exactly zero; the numeric reference confirms it.
     *
     * @dataProvider lors_cases_provider
     *
     * @param array $pp person ability parameter
     * @param array $ip item parameters (difficulty, discrimination, guessing)
     * @param float $or odds ratio
     * @param float $n number of observations
     *
     * @return void
     */
    public function test_lors_1st_derivative_numeric(array $pp, array $ip, float $or, float $n): void {
        $keys = ['difficulty', 'discrimination', 'guessing'];
        $x = [];
        foreach ($keys as $k) {
            $x[$k] = $ip[$k];
        }
        $f = function (array $v) use ($pp, $keys, $or, $n) {
            $ip = array_combine($keys, $v);
            return mixedraschbirnbaum::lors_residuals($pp, $ip, $or, $n);
        };
        $numeric = $this->fd_gradient($f, $x);
        $analytic = mixedraschbirnbaum::lors_1st_derivative_ip($pp, $ip, $or, $n);
        $this->assert_gradient_close($numeric, $analytic);
        $this->assertEqualsWithDelta(0.0, (float) $analytic[2], 1e-12, 'LORS d/dc must be zero');
    }

    /**
     * Verifies lors_2nd_derivative_ip() against the numeric Hessian of
     * lors_residuals() with respect to the item parameters (a, b, c).
     *
     * @dataProvider lors_cases_provider
     *
     * @param array $pp person ability parameter
     * @param array $ip item parameters (difficulty, discrimination, guessing)
     * @param float $or odds ratio
     * @param float $n number of observations
     *
     * @return void
     */
    public function test_lors_2nd_derivative_numeric(array $pp, array $ip, float $or, float $n): void {
        $keys = ['difficulty', 'discrimination', 'guessing'];
        $x = [];
        foreach ($keys as $k) {
            $x[$k] = $ip[$k];
        }
        $f = function (array $v) use ($pp, $keys, $or, $n) {
            $ip = array_combine($keys, $v);
            return mixedraschbirnbaum::lors_residuals($pp, $ip, $or, $n);
        };
        $numeric = $this->fd_hessian($f, $x);
        $analytic = mixedraschbirnbaum::lors_2nd_derivative_ip($pp, $ip, $or, $n);
        $this->assert_hessian_close($numeric, $analytic);
    }

    /**
     * Dynamic but deterministic (item parameters x ability x odds ratio) grid for LORS.
     *
     * @return array
     */
    public static function lors_cases_provider(): array {
        $abilities = [-1.5, 0.0, 1.2];
        $ors = [0.4, 1.0, 2.5];
        $items = [
            'lowc' => ['difficulty' => -0.4, 'discrimination' => 1.0, 'guessing' => 0.10],
            'midc' => ['difficulty' => 0.5, 'discrimination' => 1.5, 'guessing' => 0.20],
            'highc' => ['difficulty' => 1.2, 'discrimination' => 0.8, 'guessing' => 0.25],
        ];
        $cases = [];
        foreach ($items as $label => $ip) {
            foreach ($abilities as $ai => $ability) {
                foreach ($ors as $oi => $or) {
                    $name = sprintf('%s-a%d-or%d', $label, $ai, $oi);
                    $cases[$name] = [
                        'pp' => ['ability' => $ability],
                        'ip' => $ip,
                        'or' => $or,
                        'n' => 1.0,
                    ];
                }
            }
        }
        return $cases;
    }


    /**
     * Verifies least_mean_squares_1st_derivative_ip() against the numeric
     * gradient of least_mean_squares() with respect to the item parameters.
     *
     * @dataProvider lms_cases_provider
     *
     * @param array $pp person ability parameter
     * @param array $ip item parameters
     * @param float $frac observed fraction correct
     * @param float $n number of observations
     *
     * @return void
     */
    public function test_lms_1st_derivative_numeric(array $pp, array $ip, float $frac, float $n): void {
        $keys = ['difficulty', 'discrimination', 'guessing'];
        $x = [];
        foreach ($keys as $k) {
            $x[$k] = $ip[$k];
        }
        $f = function (array $v) use ($pp, $keys, $frac, $n) {
            $ip = array_combine($keys, $v);
            return mixedraschbirnbaum::least_mean_squares($pp, $ip, $frac, $n);
        };
        $numeric = $this->fd_gradient($f, $x);
        $analytic = mixedraschbirnbaum::least_mean_squares_1st_derivative_ip($pp, $ip, $frac, $n);
        $this->assert_gradient_close($numeric, $analytic);
    }

    /**
     * Verifies least_mean_squares_2nd_derivative_ip() against the numeric
     * Hessian of least_mean_squares() with respect to the item parameters.
     *
     * @dataProvider lms_cases_provider
     *
     * @param array $pp person ability parameter
     * @param array $ip item parameters
     * @param float $frac observed fraction correct
     * @param float $n number of observations
     *
     * @return void
     */
    public function test_lms_2nd_derivative_numeric(array $pp, array $ip, float $frac, float $n): void {
        $keys = ['difficulty', 'discrimination', 'guessing'];
        $x = [];
        foreach ($keys as $k) {
            $x[$k] = $ip[$k];
        }
        $f = function (array $v) use ($pp, $keys, $frac, $n) {
            $ip = array_combine($keys, $v);
            return mixedraschbirnbaum::least_mean_squares($pp, $ip, $frac, $n);
        };
        $numeric = $this->fd_hessian($f, $x);
        $analytic = mixedraschbirnbaum::least_mean_squares_2nd_derivative_ip($pp, $ip, $frac, $n);
        $this->assert_hessian_close($numeric, $analytic);
    }

    /**
     * Dynamic but deterministic (item parameters x ability x fraction x n) grid for LMS.
     *
     * @return array
     */
    public static function lms_cases_provider(): array {
        $abilities = [-1.5, 0.0, 1.2];
        $fracs = [0.2, 0.5, 0.8];
        $ns = [1.0, 4.0];
        $items = self::derivative_item_sets();
        $cases = [];
        foreach ($items as $label => $ip) {
            foreach ($abilities as $ai => $ability) {
                foreach ($fracs as $fi => $frac) {
                    foreach ($ns as $ni => $n) {
                        $name = sprintf('%s-a%d-f%d-n%d', $label, $ai, $fi, $ni);
                        $cases[$name] = [
                            'pp' => ['ability' => $ability],
                            'ip' => $ip,
                            'frac' => $frac,
                            'n' => $n,
                        ];
                    }
                }
            }
        }
        return $cases;
    }

    /**
     * Verifies log_likelihood_p() (person-ability score) against the numeric
     * gradient of log_likelihood() with respect to theta.
     *
     * @dataProvider derivative_cases_provider
     *
     * @param array $pp person ability parameter
     * @param array $ip item parameters
     * @param float $response observed response
     *
     * @return void
     */
    public function test_log_likelihood_p_numeric(array $pp, array $ip, float $response): void {
        $f = function (array $v) use ($ip, $response) {
            return mixedraschbirnbaum::log_likelihood(['ability' => $v[0]], $ip, $response);
        };
        $numeric = $this->fd_gradient($f, [$pp['ability']]);
        $analytic = mixedraschbirnbaum::log_likelihood_p($pp, $ip, $response);
        $this->assert_close(array_values($numeric)[0], $analytic, $this->fd_atol(), $this->fd_atol());
    }

    /**
     * Verifies log_likelihood_p_p() (person-ability curvature) against the
     * numeric second derivative of log_likelihood() with respect to theta.
     *
     * @dataProvider derivative_cases_provider
     *
     * @param array $pp person ability parameter
     * @param array $ip item parameters
     * @param float $response observed response
     *
     * @return void
     */
    public function test_log_likelihood_p_p_numeric(array $pp, array $ip, float $response): void {
        $f = function (array $v) use ($ip, $response) {
            return mixedraschbirnbaum::log_likelihood(['ability' => $v[0]], $ip, $response);
        };
        $numeric = $this->fd_hessian($f, [$pp['ability']]);
        $analytic = mixedraschbirnbaum::log_likelihood_p_p($pp, $ip, $response);
        $this->assert_close(array_values($numeric)[0][0], $analytic, $this->fd_atol(), 10 * $this->fd_atol());
    }

    /**
     * The combined get_ability_derivatives() must return exactly the same values
     * as the separate log_likelihood_p()/log_likelihood_p_p() methods (this guards
     * the memoised PP-Stufe-2 wiring in catcalc::estimate_person_ability()).
     *
     * @return void
     * @throws ExpectationFailedException
     */
    public function test_get_ability_derivatives_matches_separate(): void {
        $ip = ['difficulty' => 0.3, 'discrimination' => 1.2, 'guessing' => 0.15];
        foreach ([0.0, 1.0] as $frac) {
            foreach ([-2.5, -0.7, 0.0, 0.8, 2.5, 40.0, -40.0] as $theta) {
                $pp = ['ability' => $theta];
                $combined = mixedraschbirnbaum::get_ability_derivatives($pp, $ip, (float) $frac);
                $this->assertEqualsWithDelta(
                    mixedraschbirnbaum::log_likelihood_p($pp, $ip, (float) $frac),
                    $combined['jacobian'],
                    1e-9
                );
                $this->assertEqualsWithDelta(
                    mixedraschbirnbaum::log_likelihood_p_p($pp, $ip, (float) $frac),
                    $combined['hessian'],
                    1e-9
                );
            }
        }
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

    /**
     * With guessing c = 0 the 3PL collapses exactly onto the 2PL. The person-ability
     * derivatives (jacobian, hessian, and the combined get_ability_derivatives form)
     * must therefore agree with the 2PL reference bit-for-bit, and stay finite, even
     * at deep saturation where the logistic core L underflows to a denormal.
     *
     * Regression guard: an earlier P/W form divided by P^2, which underflows to 0.0
     * for a denormal P and — after stabilize_denominator — left a spurious +b^2 in the
     * hessian instead of the correct 2PL limit (approx 0). Reverting the ratio-based
     * form makes this test red at the negative-theta / k = 1 saturation points.
     *
     * @covers \catmodel_mixedraschbirnbaum\mixedraschbirnbaum::log_likelihood_p_p
     * @covers \catmodel_mixedraschbirnbaum\mixedraschbirnbaum::get_ability_derivatives
     * @return void
     */
    public function test_c0_reduces_to_2pl_at_saturation(): void {
        $items = [
            [-4.28, 4.43], [-0.76, 2.34], [0.14, 4.46],
            [0.54, 5.91], [4.63, 4.79], [3.37, 0.55],
        ];
        $thetas = [0.0, 5.0, -5.0, 40.0, -40.0, 200.0, -200.0, 800.0, -800.0];
        foreach ($items as [$a, $b]) {
            $ip3 = ['difficulty' => $a, 'discrimination' => $b, 'guessing' => 0.0];
            $ip2 = ['difficulty' => $a, 'discrimination' => $b];
            foreach ($thetas as $th) {
                $pp = ['ability' => $th];
                foreach ([0.0, 1.0] as $k) {
                    $j3 = \catmodel_mixedraschbirnbaum\mixedraschbirnbaum::log_likelihood_p($pp, $ip3, $k);
                    $h3 = \catmodel_mixedraschbirnbaum\mixedraschbirnbaum::log_likelihood_p_p($pp, $ip3, $k);
                    $g3 = \catmodel_mixedraschbirnbaum\mixedraschbirnbaum::get_ability_derivatives($pp, $ip3, $k);
                    $j2 = \catmodel_raschbirnbaum\raschbirnbaum::log_likelihood_p($pp, $ip2, $k);
                    $h2 = \catmodel_raschbirnbaum\raschbirnbaum::log_likelihood_p_p($pp, $ip2, $k);
                    foreach ([$j3, $h3, $g3['jacobian'], $g3['hessian']] as $v) {
                        $this->assertTrue(is_finite($v), "non-finite derivative at a=$a b=$b theta=$th k=$k");
                    }
                    $msg = "3PL(c=0) must equal 2PL at a=$a b=$b theta=$th k=$k";
                    $this->assertEqualsWithDelta($j2, $j3, 1e-9, $msg);
                    $this->assertEqualsWithDelta($h2, $h3, 1e-9, $msg);
                    $this->assertEqualsWithDelta($j2, $g3['jacobian'], 1e-9, $msg);
                    $this->assertEqualsWithDelta($h2, $g3['hessian'], 1e-9, $msg);
                }
            }
        }
    }
}
