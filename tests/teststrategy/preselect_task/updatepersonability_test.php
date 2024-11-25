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
 * Tests the updatepersonability class
 *
 * @package    local_catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\preselect_task;

use local_catquiz\local\result;
use local_catquiz\teststrategy\preselect_task\updatepersonability_testing;
use local_catquiz\teststrategy\progress;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

/**
 * Tests the updatepersonability class
 *
 * @package    local_catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_catquiz\teststrategy\preselect_task\updatepersonability
 */
final class updatepersonability_test extends TestCase {
    /**
     * Tests that the ability is not updated in cases where it should not be updated.
     *
     * @dataProvider skippedprovider
     *
     * @param mixed $expected
     * @param mixed $lastquestion
     * @param mixed $context
     * @param array $progressfakes
     *
     * @return void
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     *
     */
    public function test_ability_calculation_is_skipped_correctly($expected, $lastquestion, $context, $progressfakes): void {
        // We can not add a stub in the provider, so we do it here.
        $progressstub = $this->createStub(progress::class);
        $progressstub->method('get_last_question')
            ->willReturn($lastquestion);
        $progressstub->method('get_user_responses')
            ->willReturn($context['fake_response_data'] ?? []);
        $progressstub->method('has_new_response')
            ->willReturn(true);
        foreach ($progressfakes as $methodname => $returnval) {
            $progressstub->method($methodname)
                ->willReturn($returnval);
        }

        $context['progress'] = $progressstub;

        // The updatepersonaiblitytesting class is a slightly modified version
        // of the updatepersonability class that just overrides parts that load
        // data from the DB or cache.
        $updatepersonability = new updatepersonability_testing();
        $result = $updatepersonability->run($context);
        $this->assertEquals($expected, $result->unwrap()['skip_reason']);
    }

    /**
     * Skipped provider.
     *
     * @return array
     *
     */
    public static function skippedprovider(): array {
        global $USER;
        return [
            'last_question_is_null' => [
                'expected' => 'lastquestionnull',
                'lastquestion' => null,
                'context' => [
                    'contextid' => 1,
                    'catscaleid' => 1,
                ],
                'progress_fake_methods' => [
                    'is_first_question' => true,
                ],
            ],
            'is_pilot_question' => [
                'expected' => 'pilotquestion',
                'lastquestion' => (object) ['is_pilot' => true],
                'context' => [
                    'contextid' => 1,
                    'catscaleid' => 1,
                    'userid' => $USER->id,
                    'attemptid' => 123,
                    // Can be null here, because for pilot questions the ability will not be updated.
                    'fake_response_data' => [$USER->id => []],
                ],
                'progress_fake_methods' => [],
            ],
            'has_enough_responses' => [
                'expected' => 'not_skipped',
                'lastquestion' => (object) ['catscaleid' => "2", "id" => "2"],
                'context' => [
                    'skip_reason' => 'not_skipped',
                    'person_ability' => [
                        1 => 1.23,
                        2 => 0.77,
                    ],
                    'contextid' => 1,
                    'attemptid' => 123,
                    'catscaleid' => 1,
                    'userid' => $USER->id,
                    'questions' => [
                        (object) ['catscaleid' => "1"],
                        (object) ['catscaleid' => "2"],
                    ],
                    'fake_response_data' => [
                                1 => ['fraction' => "1.000", 'questionid' => 1],
                                2 => ['fraction' => "0.000", 'questionid' => 2],
                                3 => ['fraction' => "0.000", 'questionid' => 3],
                    ],
                    'fake_item_params' => [
                            1 => ['difficulty' => 2.1],
                            2 => ['difficulty' => -1.4],
                            3 => ['difficulty' => 0.4],
                    ],
                    'questionsattempted' => 0,
                    'minimumquestions' => 10,
                ],
                'progress_fake_methods' => [],
            ],
        ];
    }

    /**
     * Data provider
     *
     * @param float $expected
     * @param bool $wascalculated
     * @param array $fakeabilities
     * @param ?float $abilitymainscale
     * @return void
     * @dataProvider we_use_the_correct_initial_ability_provider
     */
    public function test_we_use_the_correct_initial_ability(
        float $expected,
        bool $wascalculated,
        array $fakeabilities,
        ?float $abilitymainscale = null
    ): void {
        /*
         * The updatepersonaiblity_testing class is a slightly modified version
         * of the updatepersonability class that makes testing easier.
         */
        $updatepersonability = (new updatepersonability_testing())
            ->set_context([
                'fake_ability_was_calculated' => $wascalculated,
                'catscaleid' => 1,
                'fake_existing_abilities' => $fakeabilities,
                'person_ability' => [1 => $abilitymainscale],
            ]);
        $this->assertEquals($expected, $updatepersonability->get_initial_ability());
    }

    /**
     * Checks that the initial ability is correct.
     *
     * @return array
     */
    public static function we_use_the_correct_initial_ability_provider(): array {
        return [
            'Initial ability is estimated' => [
                    'expected' => -1.0,
                    'ability_was_calculated_returns' => false,
                    // Average of -1.0.
                    'fake_existing_abilities_main_scale' => array_map(
                        fn($a) => (object) ['ability' => $a],
                        range(-5, 3, 0.1)
                    ),
            ],
            // We do not have enough person params to estimate.
            'Initial ability is not estimated' => [
                    'expected' => 0.0,
                    'ability_was_calculated_returns' => false,
                    // Average of -1.0.
                    'fake_existing_abilities_main_scale' => array_map(
                        fn($a) => (object) ['ability' => $a],
                        range(-2, 0, 0.1)
                    ),
            ],
            'Initial ability is calculated' => [
                    'expected' => 1.23,
                    'ability_was_calculated_returns' => true,
                    // Average of -1.0.
                    'fake_existing_abilities_main_scale' => array_map(
                        fn($a) => (object) ['ability' => $a],
                        range(-5, 3, 0.1)
                    ),
                    'person_ability_main_scale' => 1.23,
            ],
            'Initial ability is set to default' => [
                'expected' => 0.0,
                'ability_was_calculated_returns' => false,
                'fake_existing_abilities_main_scale' => [],
            ],
        ];
    }
}
