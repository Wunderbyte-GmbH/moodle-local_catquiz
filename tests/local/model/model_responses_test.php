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

use basic_testcase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * Contains tests for the model_responses class.
 *
 * @package local_catquiz
 * @covers \local_catquiz\local\model\model_item_param_list
 */
final class model_responses_test extends basic_testcase {
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

    /**
     * Test if setting values works as expected
     *
     * @return void
     */
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

    /**
     * Test if person params can be updated
     *
     * @return void
     */
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
     *
     * @return void
     */
    public function test_create_from_remote_responses(): void {
                // Create some test data.
                $testdata = [
                    (object) [
                        'questionhash' => 'q1hash',
                        'attempthash' => '4082047844',
                        'response' => 1.0,
                    ],
                    (object) [
                        'questionhash' => 'q2hash',
                        'attempthash' => '4082047844',
                        'response' => 0.0,
                    ],
                    (object) [
                        'questionhash' => 'q1hash',
                        'attempthash' => '1831571143',
                        'response' => 0.5,
                    ],
                ];

                // Test the method.
                $responses = model_responses::create_from_remote_responses($testdata, 0);

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

    /**
     * Test that prune() correctly removes attempts and items with all correct/incorrect responses.
     *
     * Test data matrix (1.0 = correct, 0.0 = incorrect):
     *
     *            item1  item2  item3  item4  item5  item6  item7
     * attempt1    1.0    0.0    1.0    -      -      -      -    (keep: mixed)
     * attempt2    1.0    1.0     -     -      -      -      -    (remove: all correct)
     * attempt3    0.0    0.0     -     -      -      -      -    (remove: all incorrect)
     * attempt4     -      -      -    1.0    0.0     -      -    (keep: mixed)
     * attempt5     -      -      -     -      -     1.0    0.0
     * attempt6     -      -      -     -      -     1.0    0.0
     * attempt7    0.0    1.0     -    0.0    1.0    1.0    0.0
     *             mix    mix    1.0   mix    mix   1.0    0.0
     *            (keep) (keep)       (keep) (keep) (rem)  (rem)
     *
     * @return void
     */
    public function test_prune(): void {
        // Create a model_responses object with test data.
        $responses = new model_responses();

        // Set up test data.
        // 1. Attempt with mixed responses (should be kept).
        $responses->set('attempt1', 'item1', 1.0);
        $responses->set('attempt1', 'item2', 0.0);
        $responses->set('attempt1', 'item3', 1.0);

        // 2. Attempt with all correct responses (should be removed).
        $responses->set('attempt2', 'item1', 1.0);
        $responses->set('attempt2', 'item2', 1.0);

        // 3. Attempt with all incorrect responses (should be removed).
        $responses->set('attempt3', 'item1', 0.0);
        $responses->set('attempt3', 'item2', 0.0);

        // 4. Another attempt with mixed responses (should be kept).
        $responses->set('attempt4', 'item4', 1.0);
        $responses->set('attempt4', 'item5', 0.0);

        // 5. Item with all correct responses across attempts (should be removed).
        $responses->set('attempt5', 'item6', 1.0);
        $responses->set('attempt6', 'item6', 1.0);

        // 6. Item with all incorrect responses across attempts (should be removed).
        $responses->set('attempt5', 'item7', 0.0);
        $responses->set('attempt6', 'item7', 0.0);

        // 7. Attempt with mixed responses so that items 1 and 2 are kept even after attempts 2 and 3 were removed.
        $responses->set('attempt7', 'item1', 0.0);
        $responses->set('attempt7', 'item2', 1.0);
        $responses->set('attempt7', 'item4', 0.0);
        $responses->set('attempt7', 'item5', 1.0);

        // Execute the prune operation.
        $pruned = $responses->prune();

        // Verify attempts with uniform responses are removed.
        $this->assertNull(
            $pruned->get_for_user('attempt2'),
            'The attempt with all correct responses was not properly removed during pruning'
        );
        $this->assertNull(
            $pruned->get_for_user('attempt3'),
            'The attempt with all incorrect responses was not properly removed during pruning'
        );

        // Verify attempts with mixed responses are retained.
        $this->assertNotNull(
            $pruned->get_for_user('attempt1'),
            'The attempt with mixed responses was incorrectly removed during pruning'
        );
        $this->assertNotNull(
            $pruned->get_for_user('attempt4'),
            'The attempt with mixed responses was incorrectly removed during pruning'
        );

        // Verify items with uniform responses are removed.
        $this->assertNotContains(
            'item6',
            $pruned->get_item_ids(),
            'The item with all correct responses was not properly removed during pruning'
        );
        $this->assertNotContains(
            'item7',
            $pruned->get_item_ids(),
            'The item with all incorrect responses was not properly removed during pruning'
        );

        // Verify items with mixed responses are retained.
        $this->assertContains(
            'item1',
            $pruned->get_item_ids(),
            'The item with mixed responses was incorrectly removed during pruning'
        );
        $this->assertContains(
            'item2',
            $pruned->get_item_ids(),
            'The item with mixed responses was incorrectly removed during pruning'
        );
    }
}
