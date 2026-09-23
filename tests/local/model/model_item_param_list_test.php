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
                    // The import still succeeds, but discrimination 5.92 is beyond
                    // the 5.0 trusted bound, so it now returns success-with-warning.
                    'success' => 2,
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
    public function test_can_be_printed_as_csv(): void {
        $ipl = new model_item_param_list();
        $ipl->add(
            (new model_item_param('A01', 'mixedraschbirnbaum'))
                ->set_parameters(
                    ['difficulty' => 1.2, 'guessing' => 2.3, 'discrimination' => 3.4]
                )
        );
        $rows = $ipl->as_csv(true);

        $this->assertEquals('difficulty;guessing;discrimination;model', $rows[0]);
        $this->assertEquals('A01;1.2;2.3;3.4;mixedraschbirnbaum', $rows[1]);

        $ipl2 = new model_item_param_list();
        $ipl2->add(
            (new model_item_param('A01', 'rasch'))
                ->set_parameters(
                    ['difficulty' => 1.8]
                )
        );
        $rows = $ipl2->as_csv(true);

        $this->assertEquals('difficulty;model', $rows[0]);
        $this->assertEquals('A01;1.8;rasch', $rows[1]);
    }

    /**
     * The calibration warnings flag degenerate or clamped item parameters on import.
     *
     * @covers \local_catquiz\local\model\model_item_param_list::calibration_warnings
     */
    public function test_calibration_warnings(): void {
        $method = new \ReflectionMethod(model_item_param_list::class, 'calibration_warnings');
        $method->setAccessible(true);
        $warn = fn (array $record): array => $method->invoke(null, $record);

        // Non-positive discrimination (the ALiSe pilots carry a = 0.00).
        self::assertCount(1, $warn(['componentid' => 1, 'discrimination' => 0.0]));
        self::assertCount(1, $warn(['componentid' => 1, 'discrimination' => -0.7]));

        // Discrimination at or above the 5.0 cap.
        self::assertCount(1, $warn(['componentid' => 1, 'discrimination' => 5.0]));
        self::assertCount(1, $warn(['componentid' => 1, 'discrimination' => 6.2]));

        // Healthy discrimination: no warning.
        self::assertCount(0, $warn(['componentid' => 1, 'discrimination' => 1.3]));

        // Difficulty at or beyond the +/-10 bound.
        self::assertCount(1, $warn(['componentid' => 1, 'difficulty' => 10.0]));
        self::assertCount(1, $warn(['componentid' => 1, 'difficulty' => -10.5]));

        // Healthy difficulty: no warning.
        self::assertCount(0, $warn(['componentid' => 1, 'difficulty' => 2.5]));

        // Both a degenerate discrimination and a clamped difficulty -> two warnings.
        self::assertCount(2, $warn(['componentid' => 1, 'discrimination' => 0.0, 'difficulty' => 10.0]));

        // Missing or empty values are ignored.
        self::assertCount(0, $warn(['componentid' => 1]));
        self::assertCount(0, $warn(['componentid' => 1, 'discrimination' => '', 'difficulty' => '']));

        // Polytomous difficulty is an array of thresholds; it must be skipped,
        // not cast (casting used to raise an "Array to string conversion").
        self::assertCount(0, $warn(['componentid' => 1, 'difficulty' => [1.0, 2.0, 3.0]]));
        // A non-numeric discrimination is likewise ignored.
        self::assertCount(0, $warn(['componentid' => 1, 'discrimination' => 'n/a']));
        // A scalar discrimination on a polytomous item is still checked.
        self::assertCount(1, $warn(['componentid' => 1, 'discrimination' => 0.0, 'difficulty' => [1.0, 2.0]]));

        // The message carries the item identifier and the offending value.
        $messages = $warn(['componentid' => 42, 'discrimination' => 0.0]);
        self::assertStringContainsString('42', $messages[0]);
        self::assertStringContainsString('0.00', $messages[0]);
    }
}
