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
 * @package    catmodel_rasch
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 namespace catmodel_rasch;

use local_catquiz\local\model\model_model;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;
use local_catquiz\local\model\model_responses;

/**
 * Tests for core_message_inbound to test Variable Envelope Return Path functionality.
 *
 * @package    catmodel_rasch
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \catmodel_rasch\rasch
 */
final class rasch_test extends TestCase {

    /**
     * This test calls the get_log_jacobain function with the model and test its output with verified data.
     *
     * @dataProvider get_log_jacobian_provider
     *
     * @param array $pp
     * @param float $k
     * @param array $ip
     * @param float $expected
     *
     * @return mixed
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     *
     */
    public function test_get_log_jacobian(array $pp, float $k, array $ip, float $expected) {

        $result = rasch::get_log_jacobian($pp, $ip, $k)[0];

        // We only verify for four commas after the dot.
        $expected = (float)sprintf("%.6f", $expected);
        $result = (float)sprintf("%.6f", $result);

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
     * @param float $expected
     *
     * @return mixed
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     *
     */
    public function test_get_log_hessian(array $pp, float $k, array $ip, float $expected) {

        $result = rasch::get_log_hessian($pp, $ip, $k)[0][0];

        // We only verify for four commas after the dot.
        $expected = (float)sprintf("%.6f", $expected);
        $result = (float)sprintf("%.6f", $result);

        $this->assertEquals($expected, $result);
    }

    /**
     * Test log_likelihood_p function.
     *
     * @dataProvider log_likelihood_p_provider
     *
     * @param array $pp
     * @param float $k
     * @param array $ip
     * @param float $expected
     *
     * @return void
     */
    public function test_log_likelihood_p(array $pp, float $k, array $ip, float $expected) {
        $result = rasch::log_likelihood_p($pp, $ip, $k);

        // We only verify for four commas after the dot.
        $expected = (float)sprintf("%.6f", $expected);
        $result = (float)sprintf("%.6f", $result);

        $this->assertEquals($expected, $result);
    }

    /**
     * Test log_likelihood_p function.
     *
     * @dataProvider log_likelihood_p_p_provider
     *
     * @param array $pp
     * @param float $k
     * @param array $ip
     * @param float $expected
     *
     * @return void
     */
    public function test_log_likelihood_p_p(array $pp, float $k, array $ip, float $expected) {
        $result = rasch::log_likelihood_p_p($pp, $ip, $k);

        // We only verify for four commas after the dot.
        $expected = (float)sprintf("%.6f", $expected);
        $result = (float)sprintf("%.6f", $result);

        $this->assertEquals($expected, $result);
    }

    /**
     * Test least_mean_squares_1st_derivative_ip function.
     *
     * @dataProvider least_mean_squares_1st_derivative_ip_provider
     *
     * @param int $n
     * @param array $pp
     * @param float $k
     * @param array $ip
     * @param float $expected
     *
     * @return void
     */
    public function test_least_mean_squares_1st_derivative_ip(int $n, array $pp, float $k, array $ip, float $expected) {

        $result = $this->getmodel()->least_mean_squares_1st_derivative_ip($pp, $ip, $k, $n);

        // We only verify for four commas after the dot.
        $expected = (float)sprintf("%.6f", $expected);
        $result = (float)sprintf("%.6f", $result[0]);

        $this->assertEqualsWithDelta($expected, $result, '0.0001');

    }

    /**
     * Test least_mean_squares_1st_derivative_ip function.
     *
     * @dataProvider least_mean_squares_2nd_derivative_ip_provider
     *
     * @param int $n
     * @param array $pp
     * @param float $k
     * @param array $ip
     * @param float $expected
     *
     * @return void
     */
    public function test_least_mean_squares_2nd_derivative_ip(int $n, array $pp, float $k, array $ip, float $expected) {

        $result = $this->getmodel()->least_mean_squares_2nd_derivative_ip($pp, $ip, $k, $n);

        // We only verify for four commas after the dot.
        $expected = (float)sprintf("%.6f", $expected);
        $result = (float)sprintf("%.6f", $result[0][0]);

        $this->assertEquals($expected, $result);

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
                ],
                'expected' => -0.1822235,
            ],
            "testcase2" => [
                'n' => 5,
                'pp' => ['ability' => -3],
                'k' => 0.95,
                'ip' => [
                    "difficulty" => -2.5,
                ],
                'expected' => 1.345301,
            ],
            "testcase3" => [
                'n' => 27,
                'pp' => ['ability' => -2],
                'k' => 0.3,
                'ip' => [
                    "difficulty" => -2.5,
                ],
                'expected' => -4.092074,
            ],
            "testcase4" => [
                'n' => 27,
                'pp' => ['ability' => -2],
                'k' => 0.95,
                'ip' => [
                    "difficulty" => -2.5,
                ],
                'expected' => 4.156557,
            ],
            "testcase5" => [
                'n' => 3,
                'pp' => ['ability' => 0.5],
                'k' => 0.3,
                'ip' => [
                    "difficulty" => 0.5,
                ],
                'expected' => -0.3,
            ],
            "testcase6" => [
                'n' => 3,
                'pp' => ['ability' => 0.5],
                'k' => 0.95,
                'ip' => [
                    "difficulty" => 0.5,
                ],
                'expected' => 0.675,
            ],
            "testcase7" => [
                'n' => 1,
                'pp' => ['ability' => 1.5],
                'k' => 0.3,
                'ip' => [
                    "difficulty" => -1,
                ],
                'expected' => -0.08750932,
            ],
            "testcase8" => [
                'n' => 1,
                'pp' => ['ability' => 1.5],
                'k' => 0.95,
                'ip' => [
                    "difficulty" => -1,
                ],
                'expected' => 0.003625509,
            ],
            "testcase9" => [
                'n' => 100,
                'pp' => ['ability' => 3.5],
                'k' => 0.3,
                'ip' => [
                    "difficulty" => 1.5,
                ],
                'expected' => -12.19599,
            ],
            "testcase10" => [
                'n' => 100,
                'pp' => ['ability' => 3.5],
                'k' => 0.95,
                'ip' => [
                    "difficulty" => 1.5,
                ],
                'expected' => 1.453173,
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
                ],
                'expected' => 0.5968974,
            ],
            "testcase2" => [
                'n' => 5,
                'pp' => ['ability' => -3],
                'k' => 0.95,
                'ip' => [
                    "difficulty" => -2.5,
                ],
                'expected' => 0.2227782,
            ],
            "testcase3" => [
                'n' => 27,
                'pp' => ['ability' => -2],
                'k' => 0.3,
                'ip' => [
                    "difficulty" => -2.5,
                ],
                'expected' => 1.980019,
            ],
            "testcase4" => [
                'n' => 27,
                'pp' => ['ability' => -2],
                'k' => 0.95,
                'ip' => [
                    "difficulty" => -2.5,
                ],
                'expected' => 4.000263,
            ],
            "testcase5" => [
                'n' => 3,
                'pp' => ['ability' => 0.5],
                'k' => 0.3,
                'ip' => [
                    "difficulty" => 0.5,
                ],
                'expected' => 0.375,
            ],
            "testcase6" => [
                'n' => 3,
                'pp' => ['ability' => 0.5],
                'k' => 0.95,
                'ip' => [
                    "difficulty" => 0.5,
                ],
                'expected' => 0.375,
            ],
            "testcase7" => [
                'n' => 1,
                'pp' => ['ability' => 1.5],
                'k' => 0.3,
                'ip' => [
                    "difficulty" => -1,
                ],
                'expected' => -0.06440366,
            ],
            "testcase8" => [
                'n' => 1,
                'pp' => ['ability' => 1.5],
                'k' => 0.95,
                'ip' => [
                    "difficulty" => -1,
                ],
                'expected' => 0.01290452,
            ],
            "testcase9" => [
                'n' => 100,
                'pp' => ['ability' => 3.5],
                'k' => 0.3,
                'ip' => [
                    "difficulty" => 1.5,
                ],
                'expected' => -7.083667,
            ],
            "testcase10" => [
                'n' => 100,
                'pp' => ['ability' => 3.5],
                'k' => 0.95,
                'ip' => [
                    "difficulty" => 1.5,
                ],
                'expected' => 3.311458,
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
                'expected' => 0.6224593,
            ],
            "testcase2" => [
                'pp' => ['ability' => -3],
                'k' => 0,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 0.7,
                    "guessing" => 0.15,
                ],
                'expected' => -0.3775407,
            ],
            "testcase3" => [
                'pp' => ['ability' => -2],
                'k' => 1,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 2.0,
                    "guessing" => 0.25,
                ],
                'expected' => 0.3775407,
            ],
            "testcase4" => [
                'pp' => ['ability' => -2],
                'k' => 0,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 2.0,
                    "guessing" => 0.25,
                ],
                'expected' => -0.6224593,
            ],
            "testcase5" => [
                'pp' => ['ability' => 0.5],
                'k' => 1,
                'ip' => [
                    "difficulty" => 0.5,
                    "discrimination" => 2.5,
                    "guessing" => 0.35,
                ],
                'expected' => 0.5,
            ],
            "testcase6" => [
                'pp' => ['ability' => 0.5],
                'k' => 0,
                'ip' => [
                    "difficulty" => 0.5,
                    "discrimination" => 2.5,
                    "guessing" => 0.35,
                ],
                'expected' => -0.5,
            ],
            "testcase7" => [
                'pp' => ['ability' => 1.5],
                'k' => 1,
                'ip' => [
                    "difficulty" => -1.0,
                    "discrimination" => 2.0,
                    "guessing" => 0.05,
                ],
                'expected' => 0.07585818,
            ],
            "testcase8" => [
                'pp' => ['ability' => 1.5],
                'k' => 0,
                'ip' => [
                    "difficulty" => -1.0,
                    "discrimination" => 2.0,
                    "guessing" => 0.05,
                ],
                'expected' => -0.9241418,
            ],
            "testcase9" => [
                'pp' => ['ability' => 3.5],
                'k' => 1,
                'ip' => [
                    "difficulty" => 1.5,
                    "discrimination" => 1.5,
                    "guessing" => 0.25,
                ],
                'expected' => 0.1192029,
            ],
            "testcase10" => [
                'pp' => ['ability' => 3.5],
                'k' => 0,
                'ip' => [
                    "difficulty" => 1.5,
                    "discrimination" => 1.5,
                    "guessing" => 0.25,
                ],
                'expected' => -0.8807971,
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
                'expected' => -0.2350037,
            ],
            "testcase2" => [
                'pp' => ['ability' => -3],
                'k' => 0,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 0.7,
                    "guessing" => 0.15,
                ],
                'expected' => -0.2350037,
            ],
            "testcase3" => [
                'pp' => ['ability' => -2],
                'k' => 1,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 2.0,
                    "guessing" => 0.25,
                ],
                'expected' => -0.2350037,
            ],
            "testcase4" => [
                'pp' => ['ability' => -2],
                'k' => 0,
                'ip' => [
                    "difficulty" => -2.5,
                    "discrimination" => 2.0,
                    "guessing" => 0.25,
                ],
                'expected' => -0.2350037,
            ],
            "testcase5" => [
                'pp' => ['ability' => 0.5],
                'k' => 1,
                'ip' => [
                    "difficulty" => 0.5,
                    "discrimination" => 2.5,
                    "guessing" => 0.35,
                ],
                'expected' => -0.25,
            ],
            "testcase6" => [
                'pp' => ['ability' => 0.5],
                'k' => 0,
                'ip' => [
                    "difficulty" => 0.5,
                    "discrimination" => 2.5,
                    "guessing" => 0.35,
                ],
                'expected' => -0.25,
            ],
            "testcase7" => [
                'pp' => ['ability' => 1.5],
                'k' => 1,
                'ip' => [
                    "difficulty" => -1.0,
                    "discrimination" => 2.0,
                    "guessing" => 0.05,
                ],
                'expected' => -0.07010372,
            ],
            "testcase8" => [
                'pp' => ['ability' => 1.5],
                'k' => 0,
                'ip' => [
                    "difficulty" => -1.0,
                    "discrimination" => 2.0,
                    "guessing" => 0.05,
                ],
                'expected' => -0.07010372,
            ],
            "testcase9" => [
                'pp' => ['ability' => 3.5],
                'k' => 1,
                'ip' => [
                    "difficulty" => 1.5,
                    "discrimination" => 1.5,
                    "guessing" => 0.25,
                ],
                'expected' => -0.1049936,
            ],
            "testcase10" => [
                'pp' => ['ability' => 3.5],
                'k' => 0,
                'ip' => [
                    "difficulty" => 1.5,
                    "discrimination" => 1.5,
                    "guessing" => 0.25,
                ],
                'expected' => -0.1049936,
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
            [
                'pp' => ['ability' => -3],
                'k' => 1,
                'ip' => ["difficulty" => -2.5],
                'expected' => -0.6224593,
            ],
            // Test case 2.
            [
                'pp' => ['ability' => -3],
                'k' => 0,
                'ip' => ["difficulty" => -2.5],
                'expected' => 0.3775407,
            ],
            // Test case 3.
            [
                'pp' => ['ability' => -2],
                'k' => 1,
                'ip' => ["difficulty" => -2.5],
                'expected' => -0.3775407,
            ],
            // Test case 4.
            [
                'pp' => ['ability' => -2],
                'k' => 0,
                'ip' => ["difficulty" => -2.5],
                'expected' => 0.6224593,
            ],
            // Test case 5.
            [
                'pp' => ['ability' => 0.5],
                'k' => 1,
                'ip' => ["difficulty" => 0.5],
                'expected' => -0.5,
            ],
            // Test case 6.
            [
                'pp' => ['ability' => 0.5],
                'k' => 0,
                'ip' => ["difficulty" => 0.5],
                'expected' => 0.5,
            ],
            // Test case 7.
            [
                'pp' => ['ability' => 1.5],
                'k' => 1,
                'ip' => ["difficulty" => -1],
                'expected' => -0.07585818,
            ],
            // Test case 8.
            [
                'pp' => ['ability' => 1.5],
                'k' => 0,
                'ip' => ["difficulty" => -1],
                'expected' => 0.9241418,
            ],
            // Test case 9.
            [
                'pp' => ['ability' => 3.5],
                'k' => 1,
                'ip' => ["difficulty" => 1.5],
                'expected' => -0.1192029,
            ],
            // Test case 10.
            [
                'pp' => ['ability' => 3.5],
                'k' => 0,
                'ip' => ["difficulty" => 1.5],
                'expected' => 0.8807971,
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
            [
                'pp' => ['ability' => -3],
                'k' => 1,
                'ip' => ["difficulty" => -2.5],
                'expected' => -0.2350037,
            ],
            // Test case 2.
            [
                'pp' => ['ability' => -3],
                'k' => 0,
                'ip' => ["difficulty" => -2.5],
                'expected' => -0.2350037,
            ],
            // Test case 3.
            [
                'pp' => ['ability' => -2],
                'k' => 1,
                'ip' => ["difficulty" => -2.5],
                'expected' => -0.2350037,
            ],
            // Test case 4.
            [
                'pp' => ['ability' => -2],
                'k' => 0,
                'ip' => ["difficulty" => -2.5],
                'expected' => -0.2350037,
            ],
            // Test case 5.
            [
                'pp' => ['ability' => 0.5],
                'k' => 1,
                'ip' => ["difficulty" => 0.5],
                'expected' => -0.25,
            ],
            // Test case 6.
            [
                'pp' => ['ability' => 0.5],
                'k' => 0,
                'ip' => ["difficulty" => 0.5],
                'expected' => -0.25,
            ],
            // Test case 7.
            [
                'pp' => ['ability' => 1.5],
                'k' => 1,
                'ip' => ["difficulty" => -1],
                'expected' => -0.07010372,
            ],
            // Test case 8.
            [
                'pp' => ['ability' => 1.5],
                'k' => 0,
                'ip' => ["difficulty" => -1],
                'expected' => -0.07010372,
            ],
            // Test case 9.
            [
                'pp' => ['ability' => 3.5],
                'k' => 1,
                'ip' => ["difficulty" => 1.5],
                'expected' => -0.1049936,
            ],
            // Test case 10.
            [
                'pp' => ['ability' => 3.5],
                'k' => 0,
                'ip' => ["difficulty" => 1.5],
                'expected' => -0.1049936,
            ],
        ];
    }

    /**
     * Get model.
     *
     * @return rasch
     *
     */
    private function getmodel(): rasch {
        return model_model::get_instance('rasch');
    }
}
