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
 * Tests the question pre-select task mayberemovescale.
 *
 * @package    local_catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use basic_testcase;
use local_catquiz\local\result;
use local_catquiz\teststrategy\preselect_task\mayberemovescale;
use local_catquiz\teststrategy\progress;
use PHPUnit\Framework\ExpectationFailedException;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

/**
 * Tests the question pre-select task mayberemovescale.
 *
 * @package    local_catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_catquiz\teststrategy\preselect_task\mayberemovescale
 */
final class mayberemovescale_test extends basic_testcase {

    /**
     * Test that questions of subscales are removed as needed.
     *
     * @dataProvider provider
     *
     * @param mixed $expected
     * @param mixed $attemptcontext
     * @param mixed $played
     *
     * @return mixed
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     *
     */
    public function test_questions_from_subscales_are_removed_as_needed(
        $expected,
        $attemptcontext,
        $played
    ) {
        $progressstub = $this->createStub(progress::class);
        $progressstub->method('get_playedquestions')
            ->willReturn(
                $played
            );
        $attemptcontext['progress'] = $progressstub;

        $returncontext = fn ($context) => result::ok($context);
        $mayberemovescale = new mayberemovescale();
        $result = $mayberemovescale->run($attemptcontext, $returncontext);
        $this->assertEquals($expected, $result->unwrap()['questions']);
    }

    /**
     * Data Provider.
     *
     * @return array
     *
     */
    public static function provider(): array {
        // Create some dummy questions for testing.
        $questions = [
            1 => (object) ['catscaleid' => 1],
            2 => (object) ['catscaleid' => 1],
            3 => (object) ['catscaleid' => 1],
            4 => (object) ['catscaleid' => 1],
            5 => (object) ['catscaleid' => 2],
        ];

        // Create the played questions array.
        // This shows the questions per scale.
        $played = [
            1 => [1, 2, 3, 4],
            2 => [5],
        ];

        // Create the minimal context required by the mayberemovescale middleware.
        $attemptcontext = [
                    'questions' => $questions,
                    'max_attempts_per_scale' => 5,
        ];

        return [
            'nothing to remove' => [
                'expected' => $attemptcontext['questions'],
                'attemptcontext' => $attemptcontext,
                'played' => $played,
            ],
            'all questions of scale 1 are removed' => [
                'expected' => [
                   5 => (object)['catscaleid' => 2],
                ],
                'attemptcontext' => [
                    'max_attempts_per_scale' => 2,
                    'questions' => $questions,
                ],
                'played' => $played,
            ],
        ];
    }
}
