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
use UnexpectedValueException;

/** model_item_param_list_test
 *
 * @package local_catquiz
 *
 *
 * @covers \local_catquiz\local\model\model_item_param_list
 */
final class model_item_param_list_test extends advanced_testcase {
    /**
     * Test import of files
     *
     * @dataProvider save_or_update_testitem_in_db_provider
     *
     * @param array $record
     * @param array $expected
     * @return void
     * @group large
     */
    public function test_save_or_update_testitem_in_db(array $record, array $expected): void {
        $this->resetAfterTest();
        $result = model_item_param_list::save_or_update_testitem_in_db($record);

        $this->assertEquals($expected['success'], $result['success']);
    }

    /**
     * Provide Data for test of save_or_update_testitem_in_db.
     *
     * @return array
     *
     */
    public static function save_or_update_testitem_in_db_provider(): array {

        return [
            'nameandtreeset' => [
                'record' => [
                        'componentid' => 0,
                        'itemid' => 0,
                        'status' => 4,
                        'qtype' => "Multiple-Choice",
                        'model' => "raschbirnbaum",
                        'difficulty' => -4.45,
                        'discrimination' => 5.92,
                        'guessing' => 0.00,
                        'catscaleid' => null,
                        'catscalename' => "SimA01",
                        'parentscalenames' => "Simulation|SimA",
                        'componentname' => "question",
                    ],
                'expected' => [
                    'success' => 1,
                ],
                 // TODO: Mock DB to check if matching via scaleid and scaleimport works.
            ],
        ];
    }

    // TODO: Test for update_in_scale.

    /**
     * Checks if we get the expected CSV row strings.
     *
     * @return void
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     */
    public function test_can_be_printed_as_csv() {
        $ipl = new model_item_param_list();
        $ipl->add(
            (new model_item_param('A01', 'mixedraschbirnbaum'))
                ->set_parameters(
                    ['difficulty' => 1.2, 'guessing' => 2.3, 'discrimination' => 3.4]
                    )
                );
        $rows = $ipl->as_csv(true);

        $this->assertEquals('difficulty;guessing;discrimination;model', $rows[0]);
        $this->assertEquals('1.2;2.3;3.4;mixedraschbirnbaum', $rows[1]);

        $ipl2 = new model_item_param_list();
        $ipl2->add(
            (new model_item_param('A01', 'rasch'))
                ->set_parameters(
                    ['difficulty' => 1.8]
                    )
                );
        $rows = $ipl2->as_csv(true);

        $this->assertEquals('difficulty;model', $rows[0]);
        $this->assertEquals('1.8;rasch', $rows[1]);
    }
}


