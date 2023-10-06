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
 * Tests strategy
 *
 * @package    catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use basic_testcase;
use local_catquiz\teststrategy\strategy\teststrategy_fastest;
use PHPUnit\Framework\ExpectationFailedException;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

/**
 * @package local_catquiz
 * @covers \local_catquiz\teststrategy\strategy\strategy
 */
class strategy_test extends basic_testcase
{

    /**
     * Test adding new questions per subscale works as expected.
     *
     * @dataProvider provider
     * @return void
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     */
    public function test_update_played_questions_per_scale_works($expected, $questions, $newquestion) {
        $fastest = new teststrategy_fastest();
        $result = $fastest->update_playedquestionsperscale($newquestion, $questions);
        $this->assertEquals($expected, $result);
    }

    public function provider() {
        $question1 = (object)[
            'id' => 1,
            'catscaleid' => 1
        ];
        $question2 = $question1;
        $question2 = (object)[
            'id' => 2,
            'catscaleid' => 1,
        ];
        $question3 = (object)[
            'id' => 3,
            'catscaleid' => 2,
        ];
        return [
            'first question is added' => [
                'expected' => [1 => [$question1]],
                'playedquestions' => [],
                'lastquestion' => $question1,
            ],
            'add second question in same scale' => [
                'expected' => [1 => [$question1, $question2]],
                'playedquestions' => [1 => [$question1]],
                'lastquestion' => $question2,
            ],
            'add second question in different scale' => [
                'expected' => [1 => [$question1], 2 => [$question3]],
                'playedquestions' => [1 => [$question1]],
                'lastquestion' => $question3,
            ],
        ];
    }
}
