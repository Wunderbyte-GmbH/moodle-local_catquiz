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
final class catscale_test extends basic_testcase {
    /**
     * Tests if the standarderror is calculated correctly
     *
     * @param model_item_param_list $items
     * @param float $ability
     * @param float $expected
     * @return void
     *
     * @dataProvider standarderror_is_calculated_correctly_provider
     */
    public function test_standarderror_is_calculated_correctly(
        model_item_param_list $items,
        float $ability,
        float $expected
    ): void {
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
                        (new model_item_param('B01-18', 'raschbirnbaum'))
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
                        (new model_item_param('B01-18', 'raschbirnbaum'))
                        ->set_parameters([
                            'difficulty' => 0.05,
                            'discrimination' => 5.95,
                        ])
                    )->add(
                        (new model_item_param('B02-00', 'raschbirnbaum'))
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
                        (new model_item_param('B01-18', 'raschbirnbaum'))
                        ->set_parameters([
                            'difficulty' => 0.05,
                            'discrimination' => 5.95,
                        ])
                    )->add(
                        (new model_item_param('B02-00', 'raschbirnbaum'))
                        ->set_parameters([
                            'difficulty' => -0.35,
                            'discrimination' => 5.94,
                        ])
                    )->add(
                        (new model_item_param('A06-09', 'raschbirnbaum'))
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
                        (new model_item_param('B01-18', 'raschbirnbaum'))
                        ->set_parameters([
                            'difficulty' => 0.05,
                            'discrimination' => 5.95,
                        ])
                    )->add(
                        (new model_item_param('B02-00', 'raschbirnbaum'))
                        ->set_parameters([
                            'difficulty' => -0.35,
                            'discrimination' => 5.94,
                        ])
                    )->add(
                        (new model_item_param('A06-09', 'raschbirnbaum'))
                        ->set_parameters([
                            'difficulty' => -0.8,
                            'discrimination' => 5.78,
                        ])
                    )->add(
                        (new model_item_param('A04-00', 'raschbirnbaum'))
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

    /**
     * Checks if we calculate the testpotential correctly
     *
     * @param float $ability
     * @param model_item_param_list $remainingitems
     * @param int $remaining
     * @param float $expected
     * @return void
     * @dataProvider testpotential_returns_expected_value_provider
     */
    public function test_testpotential_returns_expected_value(
        float $ability,
        model_item_param_list $remainingitems,
        int $remaining,
        float $expected
    ): void {
        $tp = catscale::get_testpotential($ability, $remainingitems, $remaining);
        $this->assertEqualsWithDelta($expected, $tp, 0.01);
    }

    /**
     * Checks if the testpotential function works as expected
     *
     * @return array
     */
    public static function testpotential_returns_expected_value_provider(): array {
        global $CFG;
        global $items;
        if (! defined('TEST_ITEMS')) {
            require($CFG->dirroot . '/local/catquiz/tests/fixtures/items.php');
        }
        $rawitems = $items; // The $items variable is imported with the require_once() call above.

        $remaining1 = array_filter($rawitems['B/B01'], fn($label) => $label !== 'B01-18', ARRAY_FILTER_USE_KEY);
        $remaining2 = [];
        foreach ($rawitems as $scalename => $scale) {
            if (substr($scalename, 0, 1) !== 'A') {
                continue;
            }
            $tmp = array_filter(
                $scale,
                fn($label) => ! in_array($label, ['A06-15']),
                ARRAY_FILTER_USE_KEY
            );
            foreach ($tmp as $key => $value) {
                $remaining2[$key] = $value;
            }
        }
        $remaining3 = array_filter($rawitems['B/B02'], fn($label) => ! in_array($label, ['B02-00']), ARRAY_FILTER_USE_KEY);
        $remaining4 = [];
        foreach ($rawitems as $scalename => $scale) {
            if (substr($scalename, 0, 1) !== 'B') {
                continue;
            }
            $tmp = array_filter(
                $scale,
                fn($label) => ! in_array($label, ['B01-18', 'B02-02', 'B01-17', 'B01-12', 'B02-02']),
                ARRAY_FILTER_USE_KEY
            );
            foreach ($tmp as $key => $value) {
                $remaining4[$key] = $value;
            }
        }

        // The expected values were extracted from the 07_teststrategie_tester.php values via debugger.
        return [
            'B01' => [
                'ability' => -0.67463259413669,
                'remaining_items' => self::builditemlist($remaining1),
                'remaining' => 9,
                'expected' => 14.500478336956,
            ],

            'A' => [
                'ability' => -1.2958684200153,
                'remaining_items' => self::builditemlist($remaining2),
                'remaining' => 9,
                'expected' => 44.972716519499,
            ],
            'B02' => [
                'ability' => -1.0084159069062,
                'remaining_items' => self::builditemlist($remaining3),
                'remaining' => 9,
                'expected' => 1.97667184708,
            ],
            'B' => [
                'ability' => -3.4081080401839,
                'remaining_items' => self::builditemlist($remaining4),
                'remaining' => 5,
                'expected' => 0.14658927814196,
            ],
        ];
    }

    /**
     * Checks if we calculate the testinformation correctly
     *
     * @param float $ability
     * @param model_item_param_list $items
     * @param float $expected
     * @return void
     *
     * @dataProvider testinformation_returns_expected_value_provider
     */
    public function test_testinformation_returns_expected_value(
        float $ability,
        model_item_param_list $items,
        float $expected
    ): void {
        $ti = catscale::get_testinformation($ability, $items);
        $this->assertEqualsWithDelta($expected, $ti, 0.01);
    }

    /**
     * Checks if the testinformation is calculated correctly
     *
     * @return array
     */
    public static function testinformation_returns_expected_value_provider(): array {
        global $CFG;
        global $items;
        if (! defined('TEST_ITEMS')) {
            require($CFG->dirroot . '/local/catquiz/tests/fixtures/items.php');
        }

        $items1 = [$items['B/B01']['B01-18']];
        $items2 = [$items['A/A06']['A06-15']];
        $items3 = [$items['B/B02']['B02-00']];
        $items4 = [
            $items['B/B01']['B01-18'],
            $items['B/B02']['B02-00'],
            $items['B/B01']['B01-17'],
            $items['B/B01']['B01-12'],
            $items['B/B02']['B02-02'],
        ];

        // The expected values were extracted from the 07_teststrategie_tester.php values via debugger.
        return [
            'B01' => [
                'ability' => -0.67463259413669,
                'items' => self::builditemlist($items1),
                'expected' => 0.4623522162517,
            ],

            'A' => [
                'ability' => -1.2958684200153,
                'items' => self::builditemlist($items2),
                'expected' => 0.80716617988095,
            ],
            'B02' => [
                'ability' => -1.0084159069062,
                'items' => self::builditemlist($items3),
                'expected' => 0.67894307851543,
            ],
            'B' => [
                'ability' => -3.4081080401839,
                'items' => self::builditemlist($items4),
                'expected' => 0.0019648297573745,
            ],
        ];
    }

    /**
     * Internal function to create an item list
     *
     * @param array $itemvalues
     * @param string $model
     *
     * @return model_item_param_list
     */
    private static function builditemlist(array $itemvalues, string $model = 'raschbirnbaum'): model_item_param_list {
        $items = new model_item_param_list();
        foreach ($itemvalues as $label => $params) {
                $items->add(
                    (new model_item_param($label, $model))
                        ->set_parameters([
                            'difficulty' => $params['a'],
                            'discrimination' => $params['b'],
                        ])
                );
        }
        return $items;
    }
}
