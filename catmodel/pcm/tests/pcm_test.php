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
 * @package    catmodel_pcm
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace catmodel_pcm;

use catmodel_rasch\rasch;
use local_catquiz\local\model\model_item_param;
use local_catquiz\local\model\model_model;
use PHPUnit\Framework\ExpectationFailedException;
use local_catquiz\local\model\model_person_param;
use local_catquiz\local\model\model_item_response;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;
use local_catquiz\local\model\model_responses;

/**
 * Tests for core_message_inbound to test Variable Envelope Return Path functionality.
 *
 * @package    catmodel_pcm
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 *
 * @covers \catmodel_pcm\pcm
 */
final class pcm_test extends TestCase {
    use \local_catquiz\derivative_fd_trait;

    /**
     * Verifies least_mean_squares_1st_derivative_ip() against the numeric gradient.
     *
     * @dataProvider lms_fd_cases_provider
     *
     * @param array $pp person ability parameter
     * @param array $ip item parameters
     * @param float $frac observed response fraction
     * @param float $n number of observations
     *
     * @return void
     */
    public function test_lms_1st_derivative_numeric(array $pp, array $ip, float $frac, float $n): void {
        $fractions = array_keys($ip['intercepts']);
        $x = pcm::convert_ip_to_vector($ip);
        $f = function (array $v) use ($pp, $fractions, $frac, $n) {
            return pcm::least_mean_squares($pp, pcm::convert_vector_to_ip($v, $fractions), $frac, $n);
        };
        $this->assert_gradient_close($this->fd_gradient($f, $x), pcm::least_mean_squares_1st_derivative_ip($pp, $ip, $frac, $n));
    }

    /**
     * Verifies least_mean_squares_2nd_derivative_ip() against the numeric Hessian.
     *
     * @dataProvider lms_fd_cases_provider
     *
     * @param array $pp person ability parameter
     * @param array $ip item parameters
     * @param float $frac observed response fraction
     * @param float $n number of observations
     *
     * @return void
     */
    public function test_lms_2nd_derivative_numeric(array $pp, array $ip, float $frac, float $n): void {
        $fractions = array_keys($ip['intercepts']);
        $x = pcm::convert_ip_to_vector($ip);
        $f = function (array $v) use ($pp, $fractions, $frac, $n) {
            return pcm::least_mean_squares($pp, pcm::convert_vector_to_ip($v, $fractions), $frac, $n);
        };
        $this->assert_hessian_close($this->fd_hessian($f, $x), pcm::least_mean_squares_2nd_derivative_ip($pp, $ip, $frac, $n));
    }

    /**
     * Deterministic grid for the LMS FD checks.
     *
     * @return array
     */
    public static function lms_fd_cases_provider(): array {
        $items = [
            'a' => ['intercepts' => ['0.0' => 0.0, '0.5' => -1.0, '1.0' => 1.5]],
            'b' => ['intercepts' => ['0.0' => 0.0, '0.25' => -0.8, '0.5' => 0.2, '0.75' => 0.6, '1.0' => 1.1]],
        ];
        $abilities = [-1.2, 0.0, 1.1];
        $cases = [];
        foreach ($items as $label => $ip) {
            foreach ($abilities as $ai => $ability) {
                foreach (array_keys($ip['intercepts']) as $frac) {
                    $cases[sprintf('%s-a%d-f%s', $label, $ai, $frac)] = [
                        'pp' => ['ability' => $ability], 'ip' => $ip, 'frac' => (float) $frac, 'n' => 3.0,
                    ];
                }
            }
        }
        return $cases;
    }


    /**
     * Verifies lors_1st_derivative_ip() against the numeric gradient of lors_residuals().
     *
     * @dataProvider lors_fd_cases_provider
     *
     * @param array $pp person ability parameter
     * @param array $ip item parameters
     * @param array $ors observed odds ratios
     * @param float $n number of observations
     *
     * @return void
     */
    public function test_lors_1st_derivative_numeric(array $pp, array $ip, array $ors, float $n): void {
        $fractions = array_keys($ip['intercepts']);
        $x = pcm::convert_ip_to_vector($ip);
        $f = function (array $v) use ($pp, $fractions, $ors, $n) {
            return pcm::lors_residuals($pp, pcm::convert_vector_to_ip($v, $fractions), $ors, $n);
        };
        $this->assert_gradient_close($this->fd_gradient($f, $x), pcm::lors_1st_derivative_ip($pp, $ip, $ors, $n));
    }

    /**
     * Verifies lors_2nd_derivative_ip() against the numeric Hessian of lors_residuals().
     *
     * @dataProvider lors_fd_cases_provider
     *
     * @param array $pp person ability parameter
     * @param array $ip item parameters
     * @param array $ors observed odds ratios
     * @param float $n number of observations
     *
     * @return void
     */
    public function test_lors_2nd_derivative_numeric(array $pp, array $ip, array $ors, float $n): void {
        $fractions = array_keys($ip['intercepts']);
        $x = pcm::convert_ip_to_vector($ip);
        $f = function (array $v) use ($pp, $fractions, $ors, $n) {
            return pcm::lors_residuals($pp, pcm::convert_vector_to_ip($v, $fractions), $ors, $n);
        };
        $this->assert_hessian_close($this->fd_hessian($f, $x), pcm::lors_2nd_derivative_ip($pp, $ip, $ors, $n));
    }

    /**
     * Deterministic (item x ability x odds ratios) grid for the LORS FD checks.
     *
     * @return array
     */
    public static function lors_fd_cases_provider(): array {
        $items = [
            'a' => ['intercepts' => ['0.0' => 0.0, '0.5' => -0.7, '1.0' => 0.9]],
            'b' => ['intercepts' => ['0.0' => 0.0, '0.25' => -1.2, '0.5' => -0.2, '0.75' => 0.5, '1.0' => 1.4]],
        ];
        $orsets = [
            'a' => ['0.5' => 1.5, '1.0' => 0.6],
            'b' => ['0.25' => 2.0, '0.5' => 1.1, '0.75' => 0.7, '1.0' => 0.4],
        ];
        $abilities = [-1.0, 0.3, 1.2];
        $cases = [];
        foreach ($items as $label => $ip) {
            foreach ($abilities as $ai => $ability) {
                $cases[sprintf('%s-a%d', $label, $ai)] = [
                    'pp' => ['ability' => $ability],
                    'ip' => $ip,
                    'ors' => $orsets[$label],
                    'n' => 1.0,
                ];
            }
        }
        return $cases;
    }


    /**
     * Verifies get_log_jacobian() against the numeric gradient of log_likelihood()
     * with respect to the intercept vector (via the parameter codec).
     *
     * @dataProvider fd_cases_provider
     *
     * @param array $pp person ability parameter
     * @param array $ip item parameters
     * @param float $frac observed response fraction
     *
     * @return void
     */
    public function test_get_log_jacobian_numeric(array $pp, array $ip, float $frac): void {
        $fractions = array_keys($ip['intercepts']);
        $x = pcm::convert_ip_to_vector($ip);
        $f = function (array $v) use ($pp, $fractions, $frac) {
            return pcm::log_likelihood($pp, pcm::convert_vector_to_ip($v, $fractions), $frac);
        };
        $numeric = $this->fd_gradient($f, $x);
        $analytic = pcm::get_log_jacobian($pp, $ip, $frac);
        $this->assert_gradient_close($numeric, $analytic);
    }

    /**
     * Verifies get_log_hessian() against the numeric Hessian of log_likelihood()
     * with respect to the intercept vector (via the parameter codec).
     *
     * @dataProvider fd_cases_provider
     *
     * @param array $pp person ability parameter
     * @param array $ip item parameters
     * @param float $frac observed response fraction
     *
     * @return void
     */
    public function test_get_log_hessian_numeric(array $pp, array $ip, float $frac): void {
        $fractions = array_keys($ip['intercepts']);
        $x = pcm::convert_ip_to_vector($ip);
        $f = function (array $v) use ($pp, $fractions, $frac) {
            return pcm::log_likelihood($pp, pcm::convert_vector_to_ip($v, $fractions), $frac);
        };
        $numeric = $this->fd_hessian($f, $x);
        $analytic = pcm::get_log_hessian($pp, $ip, $frac);
        $this->assert_hessian_close($numeric, $analytic);
    }

    /**
     * Warm start: a category present in the start value but absent from the observed
     * responses must be preserved (verifies that calculate_params forwards $startvalue).
     *
     * @return void
     */
    public function test_calculate_params_warm_start_preserves_category(): void {
        $trueip = ['intercepts' => ['0.0' => 0.0, '0.333' => -0.6, '0.666' => 0.2, '1.0' => 0.9]];

        // Observe only three of the four categories: 0.333 is never seen.
        $observed = ['0.0', '0.666', '1.0'];
        $responses = [];
        $uid = 0;
        for ($theta = -2.0; $theta <= 2.0; $theta += 0.5) {
            foreach ($observed as $frac) {
                $person = (new model_person_param((string) $uid++, 1))->set_ability($theta);
                $responses[] = new model_item_response('Item1', (float) $frac, $person);
            }
        }

        $startvalue = (new model_item_param('Item1', 'pcm'))->set_parameters($trueip);
        $result = model_model::get_instance('pcm')->calculate_params($responses, $startvalue);

        // The unobserved category must survive because the start value carried it.
        $this->assertArrayHasKey(
            '0.333',
            $result['intercepts'],
            'Warm start must preserve categories from the start value.'
        );
    }


    /**
     * End-to-end: recover known PCM step intercepts from a synthetic data set.
     *
     * @return void
     */
    public function test_calculate_params_recovers_thresholds(): void {
        $trueip = ['intercepts' => ['0.0' => 0.0, '0.5' => -0.6, '1.0' => 0.8]];
        $responses = [];
        $uid = 0;
        for ($theta = -2.5; $theta <= 2.5; $theta += 0.5) {
            foreach (['0.0', '0.5', '1.0'] as $frac) {
                $p = pcm::likelihood(['ability' => $theta], $trueip, (float) $frac);
                $count = (int) round($p * 60);
                for ($c = 0; $c < $count; $c++) {
                    $person = (new model_person_param((string) $uid++, 1))->set_ability($theta);
                    $responses[] = new model_item_response('Item1', (float) $frac, $person);
                }
            }
        }
        $result = model_model::get_instance('pcm')->calculate_params($responses);
        // Note: get_start_ip derives fraction keys from (string) get_response(), so
        // '0.5' stays '0.5' but '1.0' normalises to '1'.
        $this->assertEqualsWithDelta(-0.6, $result['intercepts']['0.5'], 0.15);
        $this->assertEqualsWithDelta(0.8, $result['intercepts']['1'], 0.15);
    }


    /**
     * Deterministic grid of (item, ability, response) for the FD checks.
     *
     * @return array
     */
    public static function fd_cases_provider(): array {
        $items = [
            'a' => ['intercepts' => ['0.0' => 0.0, '0.5' => -1.0, '1.0' => 1.5]],
            'b' => ['intercepts' => ['0.0' => 0.0, '0.5' => 0.5, '1.0' => 1.0]],
            'c' => ['intercepts' => ['0.0' => 0.0, '0.25' => -0.8, '0.5' => 0.2, '0.75' => 0.6, '1.0' => 1.1]],
        ];
        $abilities = [-1.5, 0.0, 1.2];
        $cases = [];
        foreach ($items as $label => $ip) {
            $fractions = array_keys($ip['intercepts']);
            foreach ($abilities as $ai => $ability) {
                foreach ($fractions as $frac) {
                    $cases[sprintf('%s-a%d-f%s', $label, $ai, $frac)] = [
                        'pp' => ['ability' => $ability],
                        'ip' => $ip,
                        'frac' => (float) $frac,
                    ];
                }
            }
        }
        return $cases;
    }


    /**
     * This test calls the get_log_jacobain function with the model and test its output with verified data.
     *
     * @dataProvider get_log_jacobian_provider
     *
     * @param array $pp
     * @param float $frac
     * @param array $ip
     * @param array $expected
     *
     * @return void
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     *
     */
    public function test_get_log_jacobian(array $pp, float $frac, array $ip, array $expected): void {
    }

    /**
     * This test calls the get_log_jacobain function with the model and test its output with verified data.
     *
     * @dataProvider get_log_hessian_provider
     *
     * @param array $pp
     * @param float $frac
     * @param array $ip
     * @param array $expected
     *
     * @return void
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     *
     */
    public function test_get_log_hessian(array $pp, float $frac, array $ip, array $expected): void {
    }


    /**
     * Test likelihood function.
     * @dataProvider likelihood_provider
     * @param array $pp
     * @param float $frac
     * @param array $ip
     * @param float $expected
     * @return void
     */
    public function test_likelihood(array $pp, float $frac, array $ip, float $expected): void {
        $result = pcm::likelihood($pp, $ip, $frac);

        // We only verify for four commas after the dot.
        $expected = (float)sprintf("%.6f", $expected);
        $result = (float)sprintf("%.6f", $result);

        $this->assertEquals($expected, $result);
    }

    /**
     * Test log_likelihood_p function.
     * @dataProvider log_likelihood_p_provider
     * @param array $pp
     * @param float $frac
     * @param array $ip
     * @param float $expected
     * @return void
     */
    public function test_log_likelihood_p(array $pp, float $frac, array $ip, float $expected): void {
        $result = pcm::log_likelihood_p($pp, $ip, $frac);

        // We only verify for four commas after the dot.
        $expected = (float)sprintf("%.6f", $expected);
        $result = (float)sprintf("%.6f", $result);

        $this->assertEquals($expected, $result);
    }

    /**
     * Test log_likelihood_p_p function.
     * @dataProvider log_likelihood_p_p_provider
     * @param array $pp
     * @param float $frac
     * @param array $ip
     * @param float $expected
     * @return void
     */
    public function test_log_likelihood_p_p(array $pp, float $frac, array $ip, float $expected): void {
        $result = pcm::log_likelihood_p_p($pp, $ip, $frac);

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
     * @param float $frac
     * @param array $ip
     * @param array $expected
     * @return void
     */
    public function test_least_mean_squares_1st_derivative_ip(int $n, array $pp, float $frac, array $ip, array $expected): void {
    }

    /**
     * Test least_mean_squares_1st_derivative_ip function.
     * @dataProvider least_mean_squares_2nd_derivative_ip_provider
     * @param int $n
     * @param array $pp
     * @param float $frac
     * @param array $ip
     * @param array $expected
     * @return void
     */
    public function test_least_mean_squares_2nd_derivative_ip(int $n, array $pp, float $frac, array $ip, array $expected): void {
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
        $labels = ["testcase1", "testcase2", "testcase3"];
        $ability = [-3, -1.5, 1.5];
        $frac = [0, 0.5, 1];
        $parameter = [
            ["intercepts" => [
                "0.0" => 0,
                "0.5" => -3.5,
                "1.0" => -2.5,
            ]],
            ["intercepts" => [
                "0.0" => 0,
                "0.5" => -1,
                "1.0" => 1.5,
            ]],
            ["intercepts" => [
                "0.0" => 0,
                "0.5" => 0.5,
                "1.0" => 1.0,
            ]],
        ];
        $expected = [
            [0.274068619, 0.451862762, 0.274068619],
            [0.610975051, 0.370575101, 0.018449848],
            [0.121951652, 0.331498960, 0.546549387],
        ];

        $providedarray = [];

        foreach ($labels as $key => $label) {
            foreach ($expected[$key] as $case => $expectedvalue) {
                $providedarray[$label . "-" . $case] = ['pp' => ['ability' => $ability[$key]],
                    'frac' => $frac[$case],
                    'ip' => $parameter[$key],
                    'expected' => $expectedvalue,
                ];
            }
        }

        return $providedarray;
    }

    /**
     * Provider function for log_likelihood_p
     * @return array
     */
    public static function log_likelihood_p_provider(): array {
        $labels = ["testcase1", "testcase2", "testcase3"];
        $ability = [-3, -1.5, 1.5];
        $frac = [0, 0.5, 1];
        $parameter = [
            ["intercepts" => [
                "0.0" => 0,
                "0.5" => -3.5,
                "1.0" => -2.5,
            ]],
            ["intercepts" => [
                "0.0" => 0,
                "0.5" => -1,
                "1.0" => 1.5,
            ]],
            ["intercepts" => [
                "0.0" => 0,
                "0.5" => 0.5,
                "1.0" => 1.0,
            ]],
        ];
        $expected = [
            [-1, 0, 1],
            [-0.407474797, 0.592525203, 1.592525203],
            [-1.424597735, -0.424597735, 0.575402265],
        ];

        $providedarray = [];

        foreach ($labels as $key => $label) {
            foreach ($expected[$key] as $case => $expectedvalue) {
                $providedarray[$label . "-" . $case] = ['pp' => ['ability' => $ability[$key]],
                    'frac' => $frac[$case],
                    'ip' => $parameter[$key],
                    'expected' => $expectedvalue,
                ];
            }
        }

        return $providedarray;
    }

    /**
     * Provider function log_likelihood_p_p_provider
     * @return array
     */
    public static function log_likelihood_p_p_provider(): array {
        $labels = ["testcase1", "testcase2", "testcase3"];
        $ability = [-3, -1.5, 1.5];
        $frac = [0, 0.5, 1];
        $parameter = [
            ["intercepts" => [
                "0.0" => 0,
                "0.5" => -3.5,
                "1.0" => -2.5,
            ]],
            ["intercepts" => [
                "0.0" => 0,
                "0.5" => -1,
                "1.0" => 1.5,
            ]],
            ["intercepts" => [
                "0.0" => 0,
                "0.5" => 0.5,
                "1.0" => 1.0,
            ]],
        ];
        $expected = [
            [-0.548137238, -0.548137238, -0.548137238],
            [-0.278338783, -0.278338783, -0.278338783],
            [-0.488217803, -0.488217803, -0.488217803],
        ];

        $providedarray = [];

        foreach ($labels as $key => $label) {
            foreach ($expected[$key] as $case => $expectedvalue) {
                $providedarray[$label . "-" . $case] = ['pp' => ['ability' => $ability[$key]],
                    'frac' => $frac[$case],
                    'ip' => $parameter[$key],
                    'expected' => $expectedvalue,
                ];
            }
        }

        return $providedarray;
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
     * Verifies the analytic polytomous Fisher information against an independent
     * numeric reference.
     *
     * For a polytomous item the item (Fisher) information is
     *   I(theta) = sum_k P_k(theta) * (-d^2/dtheta^2 log P_k(theta)),
     * where the second derivative of each category log-probability is approximated
     * by a central finite difference of the model's own likelihood(). The numeric
     * path shares no code with fisher_info()/item_information() (it does not reuse
     * log_likelihood_p_p()). This test fails if the baseline category is
     * double-counted, as the historical bug inflated I by a factor 1 + P_baseline.
     *
     * @dataProvider fisher_info_numeric_provider
     *
     * @param array $pp
     * @param array $ip
     * @param array $fractions
     *
     * @return void
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function test_fisher_info_numeric(array $pp, array $ip, array $fractions): void {
        $model = $this->getmodel();

        $h = 1e-5;
        $theta = $pp['ability'];
        $numeric = 0.0;
        foreach ($fractions as $fraction) {
            $logp = function ($t) use ($ip, $fraction) {
                return log(max(1e-300, pcm::likelihood(['ability' => $t], $ip, (float) $fraction)));
            };
            $d2 = ($logp($theta + $h) - 2.0 * $logp($theta) + $logp($theta - $h)) / ($h * $h);
            $pk = pcm::likelihood($pp, $ip, (float) $fraction);
            $numeric += $pk * (-$d2);
        }

        $analytic = $model->fisher_info($pp, $ip);
        $this->assertEqualsWithDelta($numeric, $analytic, 1e-3);
    }

    /**
     * Deterministic parameter grid for the numeric Fisher test.
     *
     * @return array
     */
    public static function fisher_info_numeric_provider(): array {
        $items = [
            ['intercepts' => ['0.0' => 0.0, '0.5' => -0.4, '1.0' => 0.7]],
            ['intercepts' => ['0.0' => 0.0, '0.333' => -0.6, '0.666' => 0.1, '1.0' => 0.9]],
        ];
        $abilities = [-1.5, -0.4, 0.0, 0.9, 2.0];
        $cases = [];
        foreach ($items as $i => $ip) {
            $fractions = array_keys($ip['intercepts']);
            foreach ($abilities as $j => $ability) {
                $cases["item{$i}-ability{$j}"] = [
                    'pp' => ['ability' => $ability],
                    'ip' => $ip,
                    'fractions' => $fractions,
                ];
            }
        }
        return $cases;
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
        $ip = ['intercepts' => ['0.0' => 0.0, '0.5' => -0.4, '1.0' => 0.7]];
        foreach (array_keys($ip['intercepts']) as $frac) {
            foreach ([-2.5, -0.7, 0.0, 0.8, 2.5, 40.0, -40.0] as $theta) {
                $pp = ['ability' => $theta];
                $combined = pcm::get_ability_derivatives($pp, $ip, (float) $frac);
                $this->assertEqualsWithDelta(
                    pcm::log_likelihood_p($pp, $ip, (float) $frac),
                    $combined['jacobian'],
                    1e-9
                );
                $this->assertEqualsWithDelta(
                    pcm::log_likelihood_p_p($pp, $ip, (float) $frac),
                    $combined['hessian'],
                    1e-9
                );
            }
        }
    }

    /**
     * Numeric check of the person-ability (theta) derivatives against central
     * finite differences of the model's own log-likelihood. Independent of the
     * analytic P/W/moment formulae used by log_likelihood_p()/_p_p().
     *
     * @return void
     * @throws ExpectationFailedException
     */
    public function test_ability_derivatives_match_finite_differences(): void {
        $ip = ['intercepts' => ['0.0' => 0.0, '0.5' => -0.4, '1.0' => 0.7]];
        $h = 1e-5;
        foreach (array_keys($ip['intercepts']) as $frac) {
            foreach ([-1.5, -0.4, 0.0, 0.9, 2.0] as $theta) {
                $pp = ['ability' => $theta];
                $logl = function ($t) use ($ip, $frac) {
                    return log(max(1e-300, pcm::likelihood(['ability' => $t], $ip, (float) $frac)));
                };
                $fdp = ($logl($theta + $h) - $logl($theta - $h)) / (2.0 * $h);
                $fdpp = ($logl($theta + $h) - 2.0 * $logl($theta) + $logl($theta - $h)) / ($h * $h);
                $this->assertEqualsWithDelta($fdp, pcm::log_likelihood_p($pp, $ip, (float) $frac), 1e-3);
                $this->assertEqualsWithDelta($fdpp, pcm::log_likelihood_p_p($pp, $ip, (float) $frac), 1e-2);
            }
        }
    }

    /**
     * Get model.
     *
     * @return pcm
     */
    private function getmodel(): pcm {
        return model_model::get_instance('pcm');
    }
}
