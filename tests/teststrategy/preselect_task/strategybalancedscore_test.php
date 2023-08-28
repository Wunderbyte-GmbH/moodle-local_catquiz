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
 * Tests the moderate CAT strategy.
 *
 * @package    catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use basic_testcase;
use local_catquiz\local\result;
use local_catquiz\teststrategy\preselect_task\strategybalancedscore;
use PHPUnit\Framework\ExpectationFailedException;
use UnexpectedValueException;

/**
 * @package local_catquiz
 * @covers \local_catquiz\teststrategy\preselect_task\strategybalancedscore
 */
class strategybalancedscore_test extends basic_testcase
{
    public function test_balanced_strategy_throws_an_exception_for_unknown_option() {
        $context['questions'] = [
        1 => (object) [
            'id' => 1,
            'lasttimeplayedpenalty' => 2,
            'numberofgeneralattempts' => 1,
        ]];
        $context['generalnumberofattempts_max'] = 100;
        $context['penalty_threshold'] = 100;
        $context['pilotingstrategy'] = 'unknownpilotingstrategyXXX';

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage("Unknown piloting strategy");
        (new strategybalancedscore())->run($context, fn () => 'nevercalled');
    }

    /**
     * Tests the correct question is returned according to the balanced strategy criteria.
     *
     * @dataProvider provider
     * @return void
     * @throws ExpectationFailedException
     */
    public function test_balanced_strategy_returns_the_expected_question($context, $expected) {
        $result = (new strategybalancedscore())->run($context, fn () => 'nevercalled');
        $this->assertEquals($expected, $result);
    }

    public function provider() {
        $context = [];
        $q1 = (object) [
            'id' => 1,
            'lasttimeplayedpenalty' => 2,
            'numberofgeneralattempts' => 1,
        ];
        $q2 = (object) [
                'id' => 2,
                'lasttimeplayedpenalty' => 99,
                'numberofgeneralattempts' => 1,
        ];
        $q3 = (object) [
                'id' => 3,
                'lasttimeplayedpenalty' => 1,
                'numberofgeneralattempts' => 100,
        ];
        $q4 = (object) [
                'id' => 4,
                'lasttimeplayedpenalty' => 100,
                'numberofgeneralattempts' => 100,
        ];
        $context['questions'] = [
            $q1->id => $q1,
            $q2->id => $q2,
            $q3->id => $q3,
            $q4->id => $q4,
        ];
        $context['generalnumberofattempts_max'] = 100;
        $context['penalty_threshold'] = 100;

        return [
            'favor questions with less attempts' => [
                'context' => array_merge($context, ['pilotingstrategy' => PILOTINGSTRATEGY_FAVOR_LESS]),
                'expected' => result::ok($q1),
            ],
            'favor questions with more attempts' => [
                'context' => array_merge($context, ['pilotingstrategy' => PILOTINGSTRATEGY_FAVOR_MANY]),
                'expected' => result::ok($q3),
            ],
            'independent of attempts' => [
                'context' => array_merge($context, ['pilotingstrategy' => PILOTINGSTRATEGY_INDEPENDENT]),
                'expected' => result::ok($q3),
            ],
        ];
    }
}
