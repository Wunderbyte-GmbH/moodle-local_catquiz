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
 * @package    catmodel_grmgeneralized
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace catmodel_grmgeneralized;

use catmodel_rasch\rasch;
use local_catquiz\local\model\model_model;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;
use local_catquiz\local\model\model_responses;

/**
 * Tests for core_message_inbound to test Variable Envelope Return Path functionality.
 *
 * @package    catmodel_grmgeneralized
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 *
 * @covers \catmodel_grmgeneralized\grmgeneralized
 */
final class grmgeneralized_test extends TestCase {

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
    public function test_get_log_jacobian(array $pp, float $k, array $ip, array $expected):void {
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
    public function test_get_log_hessian(array $pp, float $k, array $ip, array $expected):void {
    }


    /**
     * Test likelihood function.
     * @dataProvider log_likelihood_p_p_provider
     * @param array $pp
     * @param float $k
     * @param array $ip
     * @param float $expected
     * @return void
     */
    public function test_likelihood(array $pp, float $frac, array $ip, float $expected):void {
        $result = grmgeneralized::likelihood($pp, $ip, $frac);

        // We only verify for four commas after the dot.
        $expected = (float)sprintf("%.6f", $expected);
        $result = (float)sprintf("%.6f", $result);

        $this->assertEquals($expected, $result);
    }


    /**
     * Test log_likelihood function.
     * @dataProvider log_likelihood_p_p_provider
     * @param array $pp
     * @param float $k
     * @param array $ip
     * @param float $expected
     * @return void
     */
    public function test_log_likelihood(array $pp, float $frac, array $ip, float $expected):void {
        $result = grmgeneralized::log_likelihood($pp, $ip, $frac);

        // We only verify for four commas after the dot.
        $expected = (float)sprintf("%.6f", $expected);
        $result = (float)sprintf("%.6f", $result);

        $this->assertEquals($expected, $result);
    }


    /**
     * Test log_likelihood_p function.
     * @dataProvider log_likelihood_p_provider
     * @param array $pp
     * @param float $k
     * @param array $ip
     * @param float $expected
     * @return void
     */
    public function test_log_likelihood_p(array $pp, float $frac, array $ip, float $expected):void {
        $result = grmgeneralized::log_likelihood_p($pp, $ip, $frac);

        // We only verify for four commas after the dot.
        $expected = (float)sprintf("%.6f", $expected);
        $result = (float)sprintf("%.6f", $result);

        $this->assertEquals($expected, $result);
    }

    /**
     * Test log_likelihood_p_p function.
     * @dataProvider log_likelihood_p_p_provider
     * @param array $pp
     * @param float $k
     * @param array $ip
     * @param float $expected
     * @return void
     */
    public function test_log_likelihood_p_p(array $pp, float $frac, array $ip, float $expected):void {
        $result = grmgeneralized::log_likelihood_p_p($pp, $ip, $frac);

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
     * @return void
     */
    public function test_least_mean_squares_1st_derivative_ip(int $n, array $pp, float $k, array $ip, array $expected):void {

    }

    /**
     * Test least_mean_squares_1st_derivative_ip function.
     * @dataProvider least_mean_squares_2nd_derivative_ip_provider
     * @param int $n
     * @param array $pp
     * @param float $k
     * @param array $ip
     * @param array $expected
     * @return void
     */
    public function test_least_mean_squares_2nd_derivative_ip(int $n, array $pp, float $k, array $ip, array $expected):void {
    }

    /**
     * Provider function for least_mean_squares_1st_derivative_ip
     * @return array
     */
    public static function least_mean_squares_1st_derivative_ip_provider(): array {
        return [];
    }

    /**
     * Provider function for least_mean_squares_1st_derivative_ip
     * @return array
     */
    public static function least_mean_squares_2nd_derivative_ip_provider(): array {
        return [];
    }

    /**
     * Provider function for likelihood
     * @return array
     */
    public static function likelihood_provider(): array {
        return [
            "testcase1a" => [
                'pp' => ['ability' => -3],
                'frac' => 0.0,
                'ip' => [
                    "discrimination" => 0.7,
                    "difficulty" => [
                        "0.0" => 0,
                        "0,5" => -3.5,
                        "1.0" => -2.5,
                        ],
                ],
                'expected' => 0.851952802,
            ],
            "testcase1b" => [
                'pp' => ['ability' => -3],
                'frac' => 0.5,
                'ip' => [
                    "discrimination" => 0.7,
                    "difficulty" => [
                        "0.0" => 0,
                        "0,5" => -3.5,
                        "1.0" => -2.5,
                        ],
                ],
                'expected' => 0.090723022,
            ],
            "testcase1c" => [
                'pp' => ['ability' => -3],
                'frac' => 1.0,
                'ip' => [
                    "discrimination" => 0.7,
                    "difficulty" => [
                        "0.0" => 0,
                        "0,5" => -3.5,
                        "1.0" => -2.5,
                        ],
                ],
                'expected' => 0.057324176,
            ],
        ];
    }

    /**
     * Provider function for log_likelihood_p
     * @return array
     */
    public static function log_likelihood_provider(): array {
        return [
            "testcase1a" => [
                'pp' => ['ability' => -3],
                'frac' => 0.0,
                'ip' => [
                    "discrimination" => 0.7,
                    "difficulty" => [
                        "0.0" => 0,
                        "0,5" => -3.5,
                        "1.0" => -2.5,
                        ],
                ],
                'expected' => -0.16022415,
            ],
            "testcase1b" => [
                'pp' => ['ability' => -3],
                'frac' => 0.5,
                'ip' => [
                    "discrimination" => 0.7,
                    "difficulty" => [
                        "0.0" => 0,
                        "0,5" => -3.5,
                        "1.0" => -2.5,
                        ],
                ],
                'expected' => -2.399944127,
            ],
            "testcase1c" => [
                'pp' => ['ability' => -3],
                'frac' => 1.0,
                'ip' => [
                    "discrimination" => 0.7,
                    "difficulty" => [
                        "0.0" => 0,
                        "0,5" => -3.5,
                        "1.0" => -2.5,
                        ],
                ],
                'expected' => -2.859032826,
            ],
        ];
    }

    /**
     * Provider function for log_likelihood_p
     * @return array
     */
    public static function log_likelihood_p_provider(): array {
        return [
            "testcase1a" => [
                'pp' => ['ability' => -3],
                'frac' => 0.0,
                'ip' => [
                    "discrimination" => 0.7,
                    "difficulty" => [
                        "0.0" => 0,
                        "0,5" => -3.5,
                        "1.0" => -2.5,
                        ],
                ],
                'expected' => -0.103633039,
            ],
            "testcase1b" => [
                'pp' => ['ability' => -3],
                'frac' => 0.5,
                'ip' => [
                    "discrimination" => 0.7,
                    "difficulty" => [
                        "0.0" => 0,
                        "0,5" => -3.5,
                        "1.0" => -2.5,
                        ],
                ],
                'expected' => 0.556240038,
            ],
            "testcase1c" => [
                'pp' => ['ability' => -3],
                'frac' => 1.0,
                'ip' => [
                    "discrimination" => 0.7,
                    "difficulty" => [
                        "0.0" => 0,
                        "0,5" => -3.5,
                        "1.0" => -2.5,
                        ],
                ],
                'expected' => 0.659873077,
            ],
        ];
    }

    /**
     * Provider function log_likelihood_p_p_provider
     * @return array
     */
    public static function log_likelihood_p_p_provider(): array {
        return [
            "testcase1a" => [
                'pp' => ['ability' => -3],
                'frac' => 0.0,
                'ip' => [
                    "discrimination" => 0.7,
                    "difficulty" => [
                        "0.0" => 0,
                        "0,5" => -3.5,
                        "1.0" => -2.5,
                        ],
                ],
                'expected' => -0.06180332,
            ],
            "testcase1b" => [
                'pp' => ['ability' => -3],
                'frac' => 0.5,
                'ip' => [
                    "discrimination" => 0.7,
                    "difficulty" => [
                        "0.0" => 0,
                        "0,5" => -3.5,
                        "1.0" => -2.5,
                        ],
                ],
                'expected' => -0.088281997,
            ],
            "testcase1c" => [
                'pp' => ['ability' => -3],
                'frac' => 1.0,
                'ip' => [
                    "discrimination" => 0.7,
                    "difficulty" => [
                        "0.0" => 0,
                        "0,5" => -3.5,
                        "1.0" => -2.5,
                        ],
                ],
                'expected' => -0.026478676,
            ],
        ];
    }

    /**
     * Return Data for log jacobian test
     * @return array
     */
    public static function get_log_jacobian_provider(): array {
        return [];
    }

     /**
      * Return Data for log hessian test
      * @return array
      */
    public static function get_log_hessian_provider(): array {

        return [];
    }

    /**
     * Get model.
     *
     * @return grmgeneralized
     */
    private function getmodel(): grmgeneralized {
        return model_model::get_instance('grmgeneralized');
    }
}
