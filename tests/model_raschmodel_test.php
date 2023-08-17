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
use local_catquiz\local\model\model_item_param;
use local_catquiz\local\model\model_person_param;
use local_catquiz\local\model\model_person_param_list;
use local_catquiz\local\model\model_responses;
use PHPUnit\Framework\ExpectationFailedException;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

/**
 * @package local_catquiz
 * @covers \local_catquiz\local\model\model_raschmodel
 */
class model_raschmodel_test extends basic_testcase
{
    /**
     * Test if the calc_dic_item function works as expected
     *
     * @dataProvider can_calculate_calc_dic_item_provider
     * @return void
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     */
    public function test_can_calculate_calc_dic_item(
        string $expected,
        model_person_param_list $personabilities,
        model_item_param $item,
        model_responses $responses
    ) {
        $raschmodel = new raschbirnbauma($responses, 'raschbirnbauma');
        $result = $raschmodel->calc_dic_item($personabilities, $item, $responses);
        $this->assertEquals($expected, sprintf("%.4f", $result));
    }

    public function can_calculate_calc_dic_item_provider() {
        $pp1 = (new model_person_param(1))->set_ability(1);
        $pp2 = (new model_person_param(2))->set_ability(0);
        $pp3 = (new model_person_param(3))->set_ability(-1);
        $personabilities = new model_person_param_list();
        $personabilities->add($pp1)->add($pp2)->add($pp3);

        $item = new model_item_param(1, 'demo');
        $item->set_parameters(['difficulty' => 5]);
        $responses = (new model_responses())
            ->setdata([
                '1' => [ // user 1
                    'component' => [
                        '1' => [ // question 1
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

        return [
            'testset 1' => [
                'expected' => '30.0547',
                $personabilities,
                $item,
                $responses
            ]
        ];
    }
}
