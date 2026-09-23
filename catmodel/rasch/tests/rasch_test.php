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
use local_catquiz\local\model\model_item_response;
use local_catquiz\local\model\model_person_param;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

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
    use \local_catquiz\derivative_fd_trait;

    /**
     * Verifies that calculate_params() recovers a known difficulty from a
     * synthetic data set with many ability points.
     *
     * A single observation does not identify an item parameter, so the previous
     * exact-value assertion only recorded where Newton + trusted region happened
     * to stop for an under-determined input and was sensitive to rounding. This
     * test instead generates responses from a known generating difficulty across
     * a wide ability range and asserts the parameter is recovered.
     *
     * @dataProvider calculate_params_recovery_provider
     *
     * @param float $truedifficulty the generating difficulty
     *
     * @return void
     */
    public function test_calculate_params_recovers_known_difficulty(float $truedifficulty): void {
        $responses = [];
        $i = 0;
        for ($ability = -3.0; $ability <= 3.0; $ability += 0.25) {
            $frac = rasch::logistic($ability - $truedifficulty);
            $person = (new model_person_param((string) $i, 1))->set_ability($ability);
            $responses[] = new model_item_response('Item1', $frac, $person);
            $i++;
        }
        $result = $this->getmodel()->calculate_params($responses);
        $this->assertEqualsWithDelta($truedifficulty, $result['difficulty'], 0.05);
    }

    /**
     * Generating difficulties for the recovery test.
     *
     * @return array
     */
    public static function calculate_params_recovery_provider(): array {
        return [
            'negative' => [-1.2],
            'zero' => [0.0],
            'positive' => [0.7],
            'high' => [1.5],
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
     * @param float $expected
     *
     * @return void
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     *
     */
    public function test_get_log_jacobian(array $pp, float $k, array $ip, float $expected): void {

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
     * @return void
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     *
     */
    public function test_get_log_hessian(array $pp, float $k, array $ip, float $expected): void {

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
    public function test_log_likelihood_p(array $pp, float $k, array $ip, float $expected): void {
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
    public function test_log_likelihood_p_p(array $pp, float $k, array $ip, float $expected): void {
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
    public function test_least_mean_squares_1st_derivative_ip(int $n, array $pp, float $k, array $ip, float $expected): void {

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
    public function test_least_mean_squares_2nd_derivative_ip(int $n, array $pp, float $k, array $ip, float $expected): void {

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
        $keys = ['difficulty'];
        $x = [];
        foreach ($keys as $k) {
            $x[$k] = $ip[$k];
        }
        $f = function (array $v) use ($pp, $response, $keys) {
            $ip = array_combine($keys, $v);
            return rasch::log_likelihood($pp, $ip, $response);
        };
        $numeric = $this->fd_gradient($f, $x);
        $analytic = rasch::get_log_jacobian($pp, $ip, $response);
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
        $keys = ['difficulty'];
        $x = [];
        foreach ($keys as $k) {
            $x[$k] = $ip[$k];
        }
        $f = function (array $v) use ($pp, $response, $keys) {
            $ip = array_combine($keys, $v);
            return rasch::log_likelihood($pp, $ip, $response);
        };
        $numeric = $this->fd_hessian($f, $x);
        $analytic = rasch::get_log_hessian($pp, $ip, $response);
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
            'easy' => ['difficulty' => -1.3],
            'mid' => ['difficulty' => 0.2],
            'hard' => ['difficulty' => 1.7],
        ];
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
        $keys = ['difficulty'];
        $x = [];
        foreach ($keys as $k) {
            $x[$k] = $ip[$k];
        }
        $f = function (array $v) use ($pp, $keys, $frac, $n) {
            $ip = array_combine($keys, $v);
            return rasch::least_mean_squares($pp, $ip, $frac, $n);
        };
        $numeric = $this->fd_gradient($f, $x);
        $analytic = rasch::least_mean_squares_1st_derivative_ip($pp, $ip, $frac, $n);
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
        $keys = ['difficulty'];
        $x = [];
        foreach ($keys as $k) {
            $x[$k] = $ip[$k];
        }
        $f = function (array $v) use ($pp, $keys, $frac, $n) {
            $ip = array_combine($keys, $v);
            return rasch::least_mean_squares($pp, $ip, $frac, $n);
        };
        $numeric = $this->fd_hessian($f, $x);
        $analytic = rasch::least_mean_squares_2nd_derivative_ip($pp, $ip, $frac, $n);
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
     * Verifies lors_1st_derivative_ip() against the numeric gradient of
     * lors_residuals() with respect to the item parameters.
     *
     * @dataProvider lors_cases_provider
     *
     * @param array $pp person ability parameter
     * @param array $ip item parameters
     * @param float $or odds ratio
     * @param float $n number of observations
     *
     * @return void
     */
    public function test_lors_1st_derivative_numeric(array $pp, array $ip, float $or, float $n): void {
        $keys = ['difficulty'];
        $x = [];
        foreach ($keys as $k) {
            $x[$k] = $ip[$k];
        }
        $f = function (array $v) use ($pp, $keys, $or, $n) {
            $ip = array_combine($keys, $v);
            return rasch::lors_residuals($pp, $ip, $or, $n);
        };
        $numeric = $this->fd_gradient($f, $x);
        $analytic = rasch::lors_1st_derivative_ip($pp, $ip, $or, $n);
        $this->assert_gradient_close($numeric, $analytic);
    }

    /**
     * Verifies lors_2nd_derivative_ip() against the numeric Hessian of
     * lors_residuals() with respect to the item parameters.
     *
     * @dataProvider lors_cases_provider
     *
     * @param array $pp person ability parameter
     * @param array $ip item parameters
     * @param float $or odds ratio
     * @param float $n number of observations
     *
     * @return void
     */
    public function test_lors_2nd_derivative_numeric(array $pp, array $ip, float $or, float $n): void {
        $keys = ['difficulty'];
        $x = [];
        foreach ($keys as $k) {
            $x[$k] = $ip[$k];
        }
        $f = function (array $v) use ($pp, $keys, $or, $n) {
            $ip = array_combine($keys, $v);
            return rasch::lors_residuals($pp, $ip, $or, $n);
        };
        $numeric = $this->fd_hessian($f, $x);
        $analytic = rasch::lors_2nd_derivative_ip($pp, $ip, $or, $n);
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
        $items = self::derivative_item_sets();
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
            return rasch::log_likelihood(['ability' => $v[0]], $ip, $response);
        };
        $numeric = $this->fd_gradient($f, [$pp['ability']]);
        $analytic = rasch::log_likelihood_p($pp, $ip, $response);
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
            return rasch::log_likelihood(['ability' => $v[0]], $ip, $response);
        };
        $numeric = $this->fd_hessian($f, [$pp['ability']]);
        $analytic = rasch::log_likelihood_p_p($pp, $ip, $response);
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
        $ip = ['difficulty' => 0.3];
        foreach ([0.0, 1.0] as $frac) {
            foreach ([-2.5, -0.7, 0.0, 0.8, 2.5, 40.0, -40.0] as $theta) {
                $pp = ['ability' => $theta];
                $combined = rasch::get_ability_derivatives($pp, $ip, (float) $frac);
                $this->assertEqualsWithDelta(
                    rasch::log_likelihood_p($pp, $ip, (float) $frac),
                    $combined['jacobian'],
                    1e-9
                );
                $this->assertEqualsWithDelta(
                    rasch::log_likelihood_p_p($pp, $ip, (float) $frac),
                    $combined['hessian'],
                    1e-9
                );
            }
        }
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
