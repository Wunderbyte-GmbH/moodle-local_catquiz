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
 * @package    catmodel_raschbirnbaum
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace catmodel_raschbirnbaum;

use local_catquiz\local\model\model_model;
use local_catquiz\local\model\model_item_response;
use local_catquiz\local\model\model_person_param;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

/**
 * Tests for core_message_inbound to test Variable Envelope Return Path functionality.
 *
 * @package    catmodel_raschbirnbaum
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 *
 * @covers \catmodel_raschbirnbaum\raschbirnbaum
 */
final class raschbirnbaum_test extends TestCase {

    /**
     * Tests that the model calculates the item parameters correctly.
     *
     * @dataProvider calculate_params_returns_expected_values_provider
     *
     * @param array $itemresponse
     * @param array $expected
     */
    public function test_calculate_params_returns_expected_values($itemresponse, array $expected) {
        $raschbirnbaum = $this->getmodel();
        $result = $raschbirnbaum->calculate_params($itemresponse);
        $this->assertEqualsWithDelta($expected['difficulty'], $result['difficulty'], 0.0001);
        $this->assertEqualsWithDelta($expected['discrimination'], $result['discrimination'], 0.0001);
    }

    /**
     * Provder for test_calculate_params_returns_expected_values
     *
     * @return array
     */
    public static function calculate_params_returns_expected_values_provider(): array {
        return [
                [
                    'itemresponse' => [new model_item_response('Item1', 0.3, (new model_person_param(1))->set_ability(0.2))],
                    'expected' => ['difficulty' => 1.4974, 'discrimination' => 3.0],
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

        $result = [];
        $result = raschbirnbaum::get_log_jacobian($pp, $ip, $k);

        $result = array_map(fn ($a) => (float)sprintf("%.6f", $a), $result);

        // Limit the values.
        $expected = array_map(fn ($a) => (float)sprintf("%.6f", $a), $expected);

        $this->assertEquals($expected, $result);
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

        $result = [];
        $resultmatrix = raschbirnbaum::get_log_hessian($pp, $ip, $k);

        foreach ($resultmatrix as $results) {
            $result[] = array_map(fn ($a) => (float)sprintf("%.6f", $a), $results);
        }

        // Limit the values.
        $expectedmatrix = [];
        foreach ($expected as $rows) {
            $expectedmatrix[] = array_map(fn ($a) => (float)sprintf("%.6f", $a), $rows);
        }

        $this->assertEquals($expectedmatrix, $result);
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
        $result = raschbirnbaum::log_likelihood_p($pp, $ip, $k);

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
        $result = raschbirnbaum::log_likelihood_p_p($pp, $ip, $k);

        // We only verify for four commas after the dot.
        $expected = (float)sprintf("%.6f", $expected);
        $result = (float)sprintf("%.6f", $result);

        $this->assertEquals($expected, $result);
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
    public function test_least_mean_squares_1st_derivative_ip(
        int $n,
        array $pp,
        float $k,
        array $ip,
        array $expected
    ): void {

        $result = $this->getmodel()->least_mean_squares_1st_derivative_ip($pp, $ip, $k, $n);

        $result = array_map(fn ($a) => (float)sprintf("%.6f", $a), $result);

        // Limit the values.
        $expected = array_map(fn ($a) => (float)sprintf("%.6f", $a), $expected);

        $this->assertEquals($expected, $result);

    }

    /**
     * Test least_mean_squares_1st_derivative_ip function.
     * @dataProvider least_mean_squares_2nd_derivative_ip_provider
     * @param int $n
     * @param array $pp
     * @param float $k
     * @param array $ip
     * @param array $expected
     *
     * @return void
     */
    public function test_least_mean_squares_2nd_derivative_ip(
        int $n,
        array $pp,
        float $k,
        array $ip,
        array $expected
    ): void {

        $resultmatrix = [];
        $result = $this->getmodel()->least_mean_squares_2nd_derivative_ip($pp, $ip, $k, $n);

        foreach ($result as $row) {
            $resultmatrix[] = array_map(fn ($a) => (float)sprintf("%.6f", $a), $row);
        }

        // Limit the values.
        $expectedmatrix = [];
        foreach ($expected as $row) {
            $expectedmatrix[] = array_map(fn ($a) => (float)sprintf("%.6f", $a), $row);
        }

        $this->assertEquals($expectedmatrix, $resultmatrix);

    }

    /**
     * Provider function for least_mean_squares_1st_derivative_ip
     *
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
                ],
                'expected' => [-0.1924646, -0.1374747],
            ],
            "testcase2" => [
                'n' => 5,
                'pp' => ['ability' => -3],
                'k' => 0.95,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 0.7,
                ],
                'expected' => [0.9108986, 0.6506418],
            ],
            "testcase3" => [
                'n' => 27,
                'pp' => ['ability' => -2],
                'k' => 0.3,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 2.0,
                ],
                'expected' => [-9.153136, 2.288284],
            ],
            "testcase4" => [
                'n' => 27,
                'pp' => ['ability' => -2],
                'k' => 0.95,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 2.0,
                ],
                'expected' => [4.649022, -1.162255],
            ],
            "testcase5" => [
                'n' => 3,
                'pp' => ['ability' => 0.5],
                'k' => 0.3,
                'ip' => [
                    "difficulty" => 0.5,
                    "discrimination" => 2.5,
                ],
                'expected' => [-0.75, 4.583566e-13],
            ],
            "testcase6" => [
                'n' => 3,
                'pp' => ['ability' => 0.5],
                'k' => 0.95,
                'ip' => [
                    "difficulty" => 0.5,
                    "discrimination" => 2.5,
                ],
                'expected' => [1.6875, -9.167132e-13],
            ],
            "testcase7" => [
                'n' => 1,
                'pp' => ['ability' => 1.5],
                'k' => 0.3,
                'ip' => [
                    "difficulty" => -1,
                    "discrimination" => 2,
                ],
                'expected' => [-0.01843658, 0.02304573],
            ],
            "testcase8" => [
                'n' => 1,
                'pp' => ['ability' => 1.5],
                'k' => 0.95,
                'ip' => [
                    "difficulty" => -1,
                    "discrimination" => 2,
                ],
                'expected' => [-0.001151634, 0.001439542],
            ],
            "testcase9" => [
                'n' => 100,
                'pp' => ['ability' => 3.5],
                'k' => 0.3,
                'ip' => [
                    "difficulty" => 1.5,
                    "discrimination" => 1.5,
                ],
                'expected' => [-8.844336, 11.792448],
            ],
            "testcase10" => [
                'n' => 100,
                'pp' => ['ability' => 3.5],
                'k' => 0.95,
                'ip' => [
                    "difficulty" => 1.5,
                    "discrimination" => 1.5,
                ],
                'expected' => [-0.03488714, 0.04651618],
            ],
        ];
    }

    /**
     * Provider function for least_mean_squares_1st_derivative_ip
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
                ],
                'expected' => [
                    [0.31148358, -0.05246115],
                    [-0.05246115, 0.15892019],
                ],
            ],
            "testcase2" => [
                'n' => 5,
                'pp' => ['ability' => -3],
                'k' => 0.95,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 0.7,
                ],
                'expected' => [
                    [0.1776847, 1.42820128],
                    [1.4282013, 0.09065545],
                ],
            ],
            "testcase3" => [
                'n' => 5,
                'pp' => ['ability' => -3],
                'k' => 0.3,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 0.7,
                ],
                'expected' => [
                    [0.31148358, -0.05246115],
                    [-0.05246115, 0.15892019],
                ],
            ],
            "testcase4" => [
                'n' => 5,
                'pp' => ['ability' => -3],
                'k' => 0.95,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 0.7,
                ],
                'expected' => [
                    [0.1776847, 1.42820128],
                    [1.4282013, 0.09065545],
                ],
            ],
            "testcase5" => [
                'n' => 27,
                'pp' => ['ability' => -2],
                'k' => 0.3,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 2.0,
                ],
                'expected' => [
                    [-0.109892, -4.54909505],
                    [-4.549095, -0.00686825],
                ],
            ],
            "testcase6" => [
                'n' => 27,
                'pp' => ['ability' => -2],
                'k' => 0.95,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 2.0,
                ],
                'expected' => [
                    [12.6465358, -0.8371231],
                    [-0.8371231, 0.7904085],
                ],
            ],
            "testcase7" => [
                'n' => 3,
                'pp' => ['ability' => 0.5],
                'k' => 0.3,
                'ip' => [
                    "difficulty" => 0.5,
                    "discrimination" => 2.5,
                ],
                'expected' => [
                    [2.34375, -0.30000],
                    [-0.30000, 1.833426e-13],
                ],
            ],
            "testcase8" => [
                'n' => 3,
                'pp' => ['ability' => 0.5],
                'k' => 0.95,
                'ip' => [
                    "difficulty" => 0.5,
                    "discrimination" => 2.5,
                ],
                'expected' => [
                    [2.34375, 0.67500],
                    [0.67500, -1.833426e-12],
                ],
            ],
            "testcase9" => [
                'n' => 1,
                'pp' => ['ability' => 1.5],
                'k' => 0.3,
                'ip' => [
                    "difficulty" => -1,
                    "discrimination" => 2,
                ],
                'expected' => [
                    [-0.03602602, 0.03581423],
                    [0.03581423, -0.05629065],
                ],
            ],
            "testcase10" => [
                'n' => 1,
                'pp' => ['ability' => 1.5],
                'k' => 0.95,
                'ip' => [
                    "difficulty" => -1,
                    "discrimination" => 2,
                ],
                'expected' => [
                    [-0.001918863, 0.001822762],
                    [0.001822762, -0.002998223],
                ],
            ],
            "testcase11" => [
                'n' => 100,
                'pp' => ['ability' => 3.5],
                'k' => 0.3,
                'ip' => [
                    "difficulty" => 1.5,
                    "discrimination" => 1.5,
                ],
                'expected' => [
                    [-11.089734, 8.890088],
                    [8.890088, -19.715082],
                ],
            ],
            "testcase12" => [
                'n' => 100,
                'pp' => ['ability' => 3.5],
                'k' => 0.95,
                'ip' => [
                    "difficulty" => 1.5,
                    "discrimination" => 1.5,
                ],
                'expected' => [
                    [0.8710517, -1.184660],
                    [-1.1846604, 1.548536],
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
                'expected' => 0.4106323,
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
                'expected' => 0.5378828,
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
                'expected' => 1.25,
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
                'expected' => 0.0133857,
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
                'expected' => 0.07113881,
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
                'expected' => -0.1188237,
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
                'expected' => -0.7864477,
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
                'expected' => -1.5625,
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
                'expected' => -0.02659223,
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
                'expected' => -0.101647,
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
            // Test case 1.
            "testcase 1" => [
                'pp' => ['ability' => -3],
                'k' => 1,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 0.7,
                ],
                'expected' => [-0.4106323, -0.2933088],
            ],
            // Test case 2.
            "testcase 2" => [
                'pp' => ['ability' => -3],
                'k' => 0,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 0.7,
                ],
                'expected' => [0.2893677, 0.2066912],
            ],
            // Test case 3.
            "testcase 3" => [
                'pp' => ['ability' => -2],
                'k' => 1,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 2,
                ],
                'expected' => [-0.5378828, 0.1344707],
            ],
            // Test case 4.
            "testcase 4" => [
                'pp' => ['ability' => -2],
                'k' => 0,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 2,
                ],
                'expected' => [1.462117, -0.365529],
            ],
            // Test case 5.
            "testcase 5" => [
                'pp' => ['ability' => 0.5],
                'k' => 1,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 2,
                ],
                'expected' => [-0.004945, 0.007418],
            ],
            // Test case 6.
            "testcase 6" => [
                'pp' => ['ability' => 0.5],
                'k' => 0,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 2,
                ],
                'expected' => [1.995055, -2.992582],
            ],
            // Test case 7.
            "testcase 7" => [
                'pp' => ['ability' => 1.5],
                'k' => 1,
                'ip' => [
                    "difficulty" => -1,
                    "discrimination" => 2,
                ],
                'expected' => [-0.0133857, 0.01673213],
            ],
            // Test case 8.
            "testcase 8" => [
                'pp' => ['ability' => 1.5],
                'k' => 0,
                'ip' => [
                    "difficulty" => -1,
                    "discrimination" => 2,
                ],
                'expected' => [1.986614, -2.483268],
            ],
            // Test case 9.
            "testcase 9" => [
                'pp' => ['ability' => 3.5],
                'k' => 1,
                'ip' => [
                    "difficulty" => 1.5,
                    "discrimination" => 1.5,
                ],
                'expected' => [-0.07113881, 0.09485175],
            ],
            // Test case 10.
            "testcase 10" => [
                'pp' => ['ability' => 3.5],
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
      * @return array
      */
    public static function get_log_hessian_provider(): array {

        return [
            // Test case 1.
            "testcase 1" => [
                'pp' => ['ability' => -3],
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
                'pp' => ['ability' => -3],
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
                'pp' => ['ability' => -2],
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
                'pp' => ['ability' => -2],
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
                'pp' => ['ability' => 0.5],
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
                'pp' => ['ability' => 0.5],
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
                'pp' => ['ability' => 1.5],
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
                'pp' => ['ability' => 1.5],
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
                'pp' => ['ability' => 3.5],
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
                'pp' => ['ability' => 3.5],
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

    /**
     * Get model.
     *
     * @return raschbirnbaum
     */
    private function getmodel(): raschbirnbaum {
        return model_model::get_instance('raschbirnbaum');
    }
}
