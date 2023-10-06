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
 * Tests model_raschmodel
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
use local_catquiz\local\model\model_item_param;
use local_catquiz\local\model\model_person_param;
use local_catquiz\local\model\model_person_param_list;
use local_catquiz\local\model\model_raschmodel;
use local_catquiz\local\model\model_responses;
use PHPUnit\Framework\ExpectationFailedException;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

/**
 * @package local_catquiz
 * @covers \local_catquiz\local\model\model_raschmodel
 */
class model_raschmodel_test extends basic_testcase {

    /**
     * Test if the information criteria functions return the expected values.
     *
     * @dataProvider can_calculate_information_criteria_provider
     * @return void
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     */
    public function test_information_criteria_functions_return_expected_values(
        string $expected,
        model_person_param_list $personabilities,
        model_item_param $item,
        model_responses $responses,
        callable $function
    ) {
        $result = $function($personabilities, $item, $responses);
        $this->assertEquals($expected, sprintf("%.4f", $result));
    }

    public function can_calculate_information_criteria_provider(): array {
        $personabilities = $this->create_person_param_list([1 => 1, 2 => 0, 3 => -1]);
        $item = new model_item_param(1, 'XXX');
        $responses = (new model_responses())
            ->setdata([
                '1' => [ // The personid is 1.
                    'component' => [
                        '1' => [ // The question is 1.
                            'fraction' => 1.0,
                        ],
                    ],
                ],
                '2' => [
                    'component' => [
                        '1' => [
                            'fraction' => 1.0,
                        ],
                    ],
                ],
                '3' => [
                    'component' => [
                        '1' => [
                            'fraction' => 1.0,
                        ],
                    ]
                ]
            ]);
            $raschbirnbauma = new raschbirnbauma($responses, 'raschbirnbauma');
            $raschbirnbaumb = new raschbirnbaumb($responses, 'raschbirnbaumb');

        return [
            'raschbirnbauma with aic' => [
                'expected' => '32.0547',
                $personabilities,
                $item->set_parameters(['difficulty' => 5]),
                $responses,
                fn ($personparams, $item, $responses) => $raschbirnbauma->calc_aic_item($personparams, $item, $responses)
            ],
            'raschbirnbaumb with aic' => [
                'expected' => '64.0008',
                $personabilities,
                $item->set_parameters(['difficulty' => 5, 'discrimination' => 2]),
                $responses,
                fn ($personparams, $item, $responses) => $raschbirnbaumb->calc_aic_item($personparams, $item, $responses)
            ],
            'raschbirnbauma with dic' => [
                'expected' => '30.0547',
                $personabilities,
                $item->set_parameters(['difficulty' => 5]),
                $responses,
                fn ($personparams, $item, $responses) => $raschbirnbauma->calc_dic_item($personparams, $item, $responses)
            ],
            'raschbirnbaumb with dic' => [
                'expected' => '60.0008',
                $personabilities,
                $item->set_parameters(['difficulty' => 5, 'discrimination' => 2]),
                $responses,
                fn ($personparams, $item, $responses) => $raschbirnbaumb->calc_dic_item($personparams, $item, $responses)
            ],
            'raschbirnbauma with bic' => [
                'expected' => '31.1533',
                $personabilities,
                $item->set_parameters(['difficulty' => 5]),
                $responses,
                fn ($personparams, $item, $responses) => $raschbirnbauma->calc_bic_item($personparams, $item, $responses)
            ],
            'raschbirnbaumb with bic' => [
                'expected' => '62.1980',
                $personabilities,
                $item->set_parameters(['difficulty' => 5, 'discrimination' => 2]),
                $responses,
                fn ($personparams, $item, $responses) => $raschbirnbaumb->calc_bic_item($personparams, $item, $responses)
            ],
            'raschbirnbauma with caic' => [
                'expected' => '31.4410',
                $personabilities,
                $item->set_parameters(['difficulty' => 5]),
                $responses,
                fn ($personparams, $item, $responses) => $raschbirnbauma->calc_caic_item($personparams, $item, $responses)
            ],
            'raschbirnbaumb with caic' => [
                'expected' => '62.7734',
                $personabilities,
                $item->set_parameters(['difficulty' => 5, 'discrimination' => 2]),
                $responses,
                fn ($personparams, $item, $responses) => $raschbirnbaumb->calc_caic_item($personparams, $item, $responses)
            ],
            'raschbirnbauma with aicc' => [
                'expected' => '36.0547',
                $personabilities,
                $item->set_parameters(['difficulty' => 5]),
                $responses,
                fn ($personparams, $item, $responses) => $raschbirnbauma->calc_aicc_item($personparams, $item, $responses)
            ],
            'raschbirnbaumb with aicc' => [
                'expected' => '0.0000',
                $personabilities,
                $item->set_parameters(['difficulty' => 5, 'discrimination' => 2]),
                $responses,
                fn ($personparams, $item, $responses) => $raschbirnbaumb->calc_aicc_item($personparams, $item, $responses)
            ],
            'raschbirnbauma with sabic' => [
                'expected' => '28.4861',
                $personabilities,
                $item->set_parameters(['difficulty' => 5]),
                $responses,
                fn ($personparams, $item, $responses) => $raschbirnbauma->calc_sabic_item($personparams, $item, $responses)
            ],
            'raschbirnbaumb with sabic' => [
                'expected' => '0.0000',
                $personabilities,
                $item->set_parameters(['difficulty' => 5, 'discrimination' => 2]),
                $responses,
                fn ($personparams, $item, $responses) => $raschbirnbaumb->calc_sabic_item($personparams, $item, $responses)
            ],
        ];
    }

    private function create_person_param_list(array $abilities): model_person_param_list {
        $personabilities = new model_person_param_list();
        foreach ($abilities as $id => $ability) {
            $pp = (new model_person_param($id))
                ->set_ability($ability);
            $personabilities->add($pp);
        }
        return $personabilities;
    }
}
