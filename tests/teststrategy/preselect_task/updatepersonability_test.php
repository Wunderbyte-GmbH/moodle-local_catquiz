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
class updatepersonability_test extends TestCase {

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
     * @return mixed
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     *
     */
    public function test_ability_calculation_is_skipped_correctly($expected, $lastquestion, $context, $progressfakes) {
        global $USER;
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

        $returncontext = fn($context) => result::ok($context);
        // The updatepersonaiblitytesting class is a slightly modified version
        // of the updatepersonability class that just overrides parts that load
        // data from the DB or cache.
        $updatepersonability = new updatepersonability_testing();
        $result = $updatepersonability->process($context, $returncontext);
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
}
