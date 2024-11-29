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
 * Tests the person ability estimator that uses catcalc.
 *
 * @package    local_catquiz
 * @author     Magdalena Holczik <david.szkiba@wunderbyte.at>
 * @copyright  2024 Wunderbyte <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\local\model;

use advanced_testcase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * Contains tests for the model_responses class.
 *
 * @package local_catquiz
 * @covers \local_catquiz\local\model\model_item_param_list
 */
final class model_responses_test extends advanced_testcase {
    /**
     * Tests if reducing the model_responses to items or persons works as expected
     *
     * @dataProvider filtering_values_works_as_expected_provider
     *
     * @param array $indata
     * @param ?array $users
     * @param ?array $items
     * @param array $expectedusers
     * @param array $expecteditems
     *
     * @return void
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     */
    public function test_filtering_values_works_as_expected(
        array $indata,
        ?array $users,
        ?array $items,
        array $expectedusers,
        array $expecteditems
    ): void {
        $mr = new model_responses();
        foreach ($indata as $id) {
            $mr->set($id[0], $id[1], $id[2]);
        }
        if ($users) {
            $mr->limit_to_users($users);
        }
        if ($items) {
            $mr->limit_to_items($items);
        }
        $this->assertEquals($expectedusers, $mr->get_person_ids());
        $this->assertEquals($expecteditems, $mr->get_item_ids());
    }

    /**
     * Data provider for test_filtering_values_works_as_expected
     *
     * @return array
     */
    public static function filtering_values_works_as_expected_provider(): array {
        return [
            'limit persons' => [
                'in_data' => [
                    ['P1', 'A1', 1.0],
                    ['P1', 'A2', 0.0],
                    ['P1', 'A3', 1.0],
                    ['P2', 'A1', 0.0],
                    ['P2', 'A2', 0.0],
                    ['P2', 'A3', 1.0],
                    ['P3', 'A1', 1.0],
                    ['P3', 'A2', 1.0],
                    ['P3', 'A3', 1.0],
                ],
                'limit_to_users' => [
                    'P1',
                    'P2',
                ],
                'limit_to_items' => null,
                'expected_users' => ['P1', 'P2'],
                'expected_items' => ['A1'],
            ],
            'limit items' => [
                'in_data' => [
                    ['P1', 'A1', 1.0],
                    ['P1', 'A2', 1.0],
                    ['P1', 'A3', 1.0],
                    ['P2', 'A1', 1.0],
                    ['P2', 'A2', 1.0],
                    ['P2', 'A3', 1.0],
                    ['P3', 'A1', 1.0],
                    ['P3', 'A2', 1.0],
                    ['P3', 'A3', 1.0],
                ],
                'limit_to_users' => null,
                'limit_to_items' => [
                    'A1',
                ],
                'expected_users' => ['P1', 'P2', 'P3'],
                'expected_items' => ['A1'],
            ],
            'limit users and items' => [
                'in_data' => [
                    ['P1', 'A1', 1.0],
                    ['P1', 'A2', 1.0],
                    ['P1', 'A3', 1.0],
                    ['P2', 'A1', 1.0],
                    ['P2', 'A2', 1.0],
                    ['P2', 'A3', 1.0],
                    ['P3', 'A1', 0.0],
                    ['P3', 'A2', 2.0],
                    ['P3', 'A3', 1.0],
                ],
                'limit_to_users' => ['P1', 'P3'],
                'limit_to_items' => [ 'A1', 'A2'],
                'expected_users' => ['P1', 'P3'],
                'expected_items' => ['A1', 'A2'],
            ],
        ];
    }

    public function test_setting_values_works_as_expected(): void {
        $mr = new model_responses();
        $mr->set('P1', 'A1', 1.0);
        // Now update the response for P1. Sums should be changed.
        $mr->set('P1', 'A1', 0.0);
        $this->assertEquals(0.0, $mr->get_item_fraction('A1'));
        $mr->set('P1', 'A1', 1.0);
        $mr->set('P2', 'A1', 1.0);
        $mr->set('P3', 'A1', 0.0);
        $this->assertEquals(2 / 3, $mr->get_item_fraction('A1'));
    }

    public function test_personparam_can_be_updated(): void {
        $mr = new model_responses();
        $mr->set(1, 'A1', 1.0);
        $mr->set(1, 'A2', 0.0);
        $mr->set(1, 'A3', 0.5);
        $mr->set_person_abilities((new model_person_param_list())->add((new model_person_param(1, 1))->set_ability(1.2)));
        $response1 = $mr->get_for_user(1)['A1'];
        $response2 = $mr->get_for_user(1)['A2'];
        $response3 = $mr->get_for_user(1)['A3'];
        $this->assertEquals(1.2, $response1->get_personparams()->get_ability());
        $this->assertEquals(1.2, $response2->get_personparams()->get_ability());
        $this->assertEquals(1.2, $response3->get_personparams()->get_ability());
    }

    /**
     * Test creating responses from remote responses table
     */
    public function test_create_from_remote_responses() {
                global $DB;
                $this->resetAfterTest(true);

                // Create some test data.
                $testdata = [
                    [
                        'questionhash' => 'q1hash',
                        'attempthash' => '4082047844',
                        'response' => 1.0,
                    ],
                    [
                        'questionhash' => 'q2hash',
                        'attempthash' => '4082047844',
                        'response' => 0.0,
                    ],
                    [
                        'questionhash' => 'q1hash',
                        'attempthash' => '1831571143',
                        'response' => 0.5,
                    ],
                ];

                // Insert test data into remote responses table.
                foreach ($testdata as $data) {
                    $DB->insert_record('local_catquiz_rresponses', $data);
                }

                // Test the method.
                $responses = model_responses::create_from_remote_responses();

                // Verify the responses were loaded correctly.
                $this->assertEquals(1.0, $responses->get_item_response_for_person('q1hash', '4082047844'));
                $this->assertEquals(0.0, $responses->get_item_response_for_person('q2hash', '4082047844'));
                $this->assertEquals(0.5, $responses->get_item_response_for_person('q1hash', '1831571143'));

                // Test item IDs are correct.
                $expecteditemids = ['q1hash', 'q2hash'];
                $actualitemids = $responses->get_item_ids();
                $this->assertEquals(sort($expecteditemids), sort($actualitemids));

                // Test person IDs are correct.
                $expectedpersonids = ['4082047844', '1831571143'];
                $actualpersonids = $responses->get_person_ids();
                $this->assertEquals(sort($expectedpersonids), sort($actualpersonids));
    }
}
