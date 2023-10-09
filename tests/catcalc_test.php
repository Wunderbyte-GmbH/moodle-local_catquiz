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
 * Tests the catcalc functionality.
 *
 * @package    catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use basic_testcase;
use catmodel_raschbirnbauma\raschbirnbauma;
use catmodel_raschbirnbaumb\raschbirnbaumb;
use catmodel_raschbirnbaumc\raschbirnbaumc;
use local_catquiz\local\model\model_item_param;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_item_response;
use local_catquiz\local\model\model_person_param;
use local_catquiz\local\model\model_responses;
use PHPUnit\Framework\ExpectationFailedException;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

/**
 * @package local_catquiz
 * @covers \local_catquiz\catcalc
 */
class catcalc_test extends basic_testcase {


    /**
     * Test if the person ability is calculated correctly.
     *
     * @dataProvider provider
     * @return void
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     */
    public function test_estimate_person_ability_returns_expected_values(string $model, float $expected) {
        $personresponses = [
            5 => ['fraction' => 0.0],
            33 => ['fraction' => 1.0],
            50 => ['fraction' => 1.0],
            58 => ['fraction' => 1.0],
        ];
        $itemparamlist = new model_item_param_list();
        $itemparamlist
            ->add((new model_item_param(5, $model))->set_parameters(['difficulty' =>
                0.7758, 'discrimination' => 0.5, 'guessing' => 0.2]))
            ->add((new model_item_param(33, $model))->set_parameters(['difficulty' =>
                -37.7967, 'discrimination' => 1.2, 'guessing' => 1.2]))
            ->add((new model_item_param(50, $model))->set_parameters(['difficulty' =>
                -37.7967, 'discrimination' => 0.3, 'guessing' => 0.7]))
            ->add((new model_item_param(58, $model))->set_parameters(['difficulty' =>
                -37.7967, 'discrimination' => 0, 'guessing' => 0.4]));
        $result = catcalc::estimate_person_ability($personresponses, $itemparamlist);
        $this->assertEquals($expected, sprintf("%.4f", $result));
    }

    public function provider() {
        return [
            'web-raschbirnbauma' => [
                'model' => 'web_raschbirnbauma',
                'expected' => -4.9000,
            ],
            'raschbirnbauma' => [
                'model' => 'raschbirnbauma',
                'expected' => -4.9000,
            ],
            'raschbirnbaumb' => [
                'model' => 'raschbirnbaumb',
                'expected' => -4.9000,
            ],
            'raschbirnbaumc' => [
                'model' => 'raschbirnbaumc',
                'expected' => -4.9000,
            ],
        ];
    }

    /**
     * Test if item parameters are calculated correctly.
     *
     * @dataProvider item_param_provider
     * @return void
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     */
    public function test_estimate_item_parameter_calculation_returns_expected_values(
            string $modelname,
            array $responses,
            array $userabilities,
            float $expected) {
        $itemresponses = [];
        foreach ($userabilities as $userid => $ability) {
            foreach (['q1', 'q2'] as $qid) {
                $ir = new model_item_response(
                    $responses[$userid]['component'][$qid]['fraction'],
                    (new model_person_param($userid))->set_ability($ability)
                );
                $itemresponses[] = $ir;
            }
        }
        switch ($modelname) {
            case 'raschbirnbauma':
                $model = new raschbirnbauma(model_responses::create_from_array($responses), $modelname);
                break;
            case 'raschbirnbaumb':
                $model = new raschbirnbaumb(model_responses::create_from_array($responses), $modelname);
                break;
            case 'raschbirnbaumc':
                $model = new raschbirnbaumc(model_responses::create_from_array($responses), $modelname);
                break;
        };
        $result = catcalc::estimate_item_params($itemresponses, $model);
        $this->assertEquals($expected, sprintf("%.4f", $result['difficulty']));
    }

    public function item_param_provider() {
        $responses = [
            1 => [
                'component' => [
                    'q1' => ['fraction' => 0.0],
                    'q2' => ['fraction' => 1.0],
                ],
            ],
            2 => [
                'component' => [
                    'q1' => ['fraction' => 0.0],
                    'q2' => ['fraction' => 0.0],
                ],
            ],
            3 => [
                'component' => [
                    'q1' => ['fraction' => 1.0],
                    'q2' => ['fraction' => 1.0],
                ],
            ],
            4 => [
                'component' => [
                    'q1' => ['fraction' => 1.0],
                    'q2' => ['fraction' => 0.0],
                ],
            ],
        ];
        $userabilities = [
            1 => 0.5,
            2 => -1,
            3 => 2.3,
            4 => 0.7,
        ];
        return [
            'raschbirnbauma' => [
                'model' => 'raschbirnbauma',
                'responses' => $responses,
                'userabilities' => $userabilities,
                'expected' => 0.6176,
            ],
            'raschbirnbaumb' => [
                'model' => 'raschbirnbaumb',
                'responses' => $responses,
                'userabilities' => $userabilities,
                'expected' => 0.6010,
            ],
            'raschbirnbaumc' => [
                'model' => 'raschbirnbaumc',
                'responses' => $responses,
                'userabilities' => $userabilities,
                'expected' => 0.4270,
            ],
        ];
    }

    public function test_itemparam_jacobian_function_can_be_called() {
        $responses = [
            1 => [
                'component' => [
                    'q1' => ['fraction' => 0.0],
                    'q2' => ['fraction' => 1.0],
                ],
            ],
            2 => [
                'component' => [
                    'q1' => ['fraction' => 0.0],
                    'q2' => ['fraction' => 0.0],
                ],
            ],
            3 => [
                'component' => [
                    'q1' => ['fraction' => 1.0],
                    'q2' => ['fraction' => 1.0],
                ],
            ],
            4 => [
                'component' => [
                    'q1' => ['fraction' => 1.0],
                    'q2' => ['fraction' => 0.0],
                ],
            ],
        ];
        $userabilities = [
            1 => 0.5,
            2 => -1,
            3 => 2.3,
            4 => 0.7,
        ];
        $itemresponses = [];
        foreach ($userabilities as $userid => $ability) {
            foreach (['q1', 'q2'] as $qid) {
                $ir = new model_item_response(
                    $responses[$userid]['component'][$qid]['fraction'],
                    (new model_person_param($userid))->set_ability($ability)
                );
                $itemresponses[] = $ir;
            }
        }
        $model = new raschbirnbauma(model_responses::create_from_array($responses), 'raschbirnbauma');
        $jacobian = catcalc::build_itemparam_jacobian($itemresponses, $model);
        $result = $jacobian(['difficulty' => 0.5]);
        $this->assertIsArray($result);
        $this->assertIsFloat(reset($result));
    }

    public function test_build_callable_array_works_as_expected() {
        $inarray = [
            fn ($x) => $x,
            fn ($x) => 2 * $x,
            fn ($x) => 3 * $x,
            fn () => 4,
        ];

        $result = catcalc::build_callable_array($inarray);
        $this->assertEquals(fn ($x) => [$x, 2 * $x, 3 * $x, 4], $result);
        $this->assertEquals($result(1), [1, 2, 3, 4]);
    }
}
