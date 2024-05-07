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
 * Tests the filterforsubscale class.
 *
 * @package    local_catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use basic_testcase;
use local_catquiz\teststrategy\preselect_task\filterforsubscale;
use local_catquiz\teststrategy\preselect_task\filterforsubscaletesting;
use local_catquiz\teststrategy\preselect_task\strategyfastestscore;
use PHPUnit\Framework\ExpectationFailedException;
use SebastianBergmann\RecursionContext\InvalidArgumentException;
use UnexpectedValueException;

/**
 * Tests the filterforsubscale class.
 *
 * @package    local_catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_catquiz\teststrategy\preselect_task\filterforsubscale
 */
class filterforsubscale_test extends basic_testcase {

    /**
     * Test that questions of subscales are removed as needed.
     *
     * @return void
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     */
    public function test_throws_an_exception_for_unknown_teststrategy() {
        $context = [
            'teststrategy' => 'XXX',
            'person_ability' => [1 => 1.0],
        ];
        $this->expectException(UnexpectedValueException::class);
        (new filterforsubscale())->run($context, fn () => 'nevercalled');
    }

    /**
     * Test returns expected question from expected catscale provider
     *
     * @dataProvider provider
     *
     * @param array $context
     * @param mixed $expected
     *
     * @return void
     */
    public function test_returns_expected_question_from_expected_catscale(
        array $context, $expected
    ) {
        $result = (new filterforsubscaletesting())->run($context,
            fn ($context) => (new strategyfastestscore())->run($context,
            fn () => 'nevercalled'));
        $this->assertEquals($expected, $result->unwrap()->id);
    }

    /**
     * Data Provider.
     *
     * @return array
     */
    public static function provider(): array {
        $fisherinformation = [
            1 => 1,
            2 => 1,
            3 => 1,
            4 => 1,
        ];

        $context['questions'] = [
            '1' => (object) [
                'id' => 1,
                'catscaleid' => 1,
                'lasttimeplayedpenaltyfactor' => 1,
                'fisherinformation' => $fisherinformation,
            ],
            '2' => (object) [
                'id' => 2,
                'catscaleid' => 2,
                'lasttimeplayedpenaltyfactor' => 1,
                'fisherinformation' => $fisherinformation,
            ],
            '3' => (object) [
                'id' => 3,
                'catscaleid' => 3,
                'lasttimeplayedpenaltyfactor' => 1,
                'fisherinformation' => $fisherinformation,
            ],
            '4' => (object) [
                'id' => 4,
                'catscaleid' => 4,
                'lasttimeplayedpenaltyfactor' => 1,
                'fisherinformation' => $fisherinformation,
            ],
        ];
        $context['person_ability'] = [
            1 => -2.0,
            2 => -1.0,
            3 => 0,
            4 => 1.0,
        ];
        $context['catscaleid'] = 1;
        $context['penalty_threshold'] = 5;
        $context['fake_subscaleids'] = [
            1 => [2, 3, 4],
            2 => [],
            3 => [],
            4 => [],
        ];

        return [
            'select highest subscale' => [
                'context' => array_merge($context, ['teststrategy' => LOCAL_CATQUIZ_STRATEGY_HIGHESTSUB]),
                'expected_id' => 4,
            ],
            'select lowest subscale' => [
                'context' => array_merge($context, ['teststrategy' => LOCAL_CATQUIZ_STRATEGY_LOWESTSUB]),
                'expected_id' => 1,
            ],
        ];
    }
}
