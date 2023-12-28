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
 * Tests the catscale functionality.
 *
 * @package    local_catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use basic_testcase;
use local_catquiz\local\model\model_item_param;
use local_catquiz\local\model\model_item_param_list;

/**
 * Tests the catscale functionality.
 *
 * @package    local_catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_catquiz\catscale
 *
 */
class catscale_test extends basic_testcase {

    /**
     * Tests if the standarderror is calculated correctly
     *
     * @dataProvider standarderror_is_calculated_correctly_provider
     */
    public function test_standarderror_is_calculated_correctly($items, $ability, $expected) {
        $standarderror = catscale::get_standarderror($ability, $items);
        $this->assertEqualsWithDelta($expected, $standarderror, 0.01);
    }

    /**
     * Data provider for test_standarderror_is_calculated_correctly
     *
     * @return array
     */
    public static function standarderror_is_calculated_correctly_provider(): array {
        return [
            [
                'items' => (new model_item_param_list())
                    ->add(
                        (new model_item_param('B01-18', 'raschbirnbaumb'))
                        ->set_parameters([
                            'difficulty' => 0.05,
                            'discrimination' => 5.95,
                        ])
                    ),
                'ability' => -0.3945,
                'expected' => 0.68,
            ],
            [
                'items' => (new model_item_param_list())
                    ->add(
                        (new model_item_param('B01-18', 'raschbirnbaumb'))
                        ->set_parameters([
                            'difficulty' => 0.05,
                            'discrimination' => 5.95,
                        ])
                    )->add(
                        (new model_item_param('B02-00', 'raschbirnbaumb'))
                        ->set_parameters([
                            'difficulty' => -0.35,
                            'discrimination' => 5.94,
                        ])
                    ),
                'ability' => -0.7098,
                'expected' => 0.52,
            ],
            [
                'items' => (new model_item_param_list())
                    ->add(
                        (new model_item_param('B01-18', 'raschbirnbaumb'))
                        ->set_parameters([
                            'difficulty' => 0.05,
                            'discrimination' => 5.95,
                        ])
                    )->add(
                        (new model_item_param('B02-00', 'raschbirnbaumb'))
                        ->set_parameters([
                            'difficulty' => -0.35,
                            'discrimination' => 5.94,
                        ])
                    )->add(
                        (new model_item_param('A06-09', 'raschbirnbaumb'))
                        ->set_parameters([
                            'difficulty' => -0.8,
                            'discrimination' => 5.78,
                        ])
                    ),
                'ability' => -1.0438,
                'expected' => 0.41,
            ],
            [
                'items' => (new model_item_param_list())
                    ->add(
                        (new model_item_param('B01-18', 'raschbirnbaumb'))
                        ->set_parameters([
                            'difficulty' => 0.05,
                            'discrimination' => 5.95,
                        ])
                    )->add(
                        (new model_item_param('B02-00', 'raschbirnbaumb'))
                        ->set_parameters([
                            'difficulty' => -0.35,
                            'discrimination' => 5.94,
                        ])
                    )->add(
                        (new model_item_param('A06-09', 'raschbirnbaumb'))
                        ->set_parameters([
                            'difficulty' => -0.8,
                            'discrimination' => 5.78,
                        ])
                    )->add(
                        (new model_item_param('A04-00', 'raschbirnbaumb'))
                        ->set_parameters([
                            'difficulty' => -1.1,
                            'discrimination' => 6.0,
                        ])
                    ),
                'ability' => -1.3151,
                'expected' => 0.36,
            ],
        ];
    }
}
