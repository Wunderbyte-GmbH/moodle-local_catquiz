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
 * Tests the strategyfastestscore class.
 *
 * @package    local_catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use basic_testcase;
use local_catquiz\teststrategy\preselect_task\strategyfastestscore;

/**
 * Tests the strategyfastestscore class.
 *
 * @package    local_catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_catquiz\teststrategy\preselect_task\strategyfastestscore
 */
final class strategyfastestscore_test extends basic_testcase {
    /**
     * Test the strategyfastestscore class
     *
     * @dataProvider provider
     *
     * @param array $context
     * @param mixed $expected
     *
     * @return void
     * @return void
     */
    public function test_returns_expected_question_from_expected_catscale(
        array $context,
        $expected
    ): void {
        $next = fn () => 'nevercalled';
        $result = (new strategyfastestscore())->run(
            $context,
            $next
        );
        $this->assertEquals($expected['id'], $result->unwrap()->id);
    }

    /**
     * Data Provider.
     *
     * @return array
     */
    public static function provider(): array {
        $context['questions'] = [
            '1' => (object) [
                'id' => 1,
                'catscaleid' => 1,
                'lasttimeplayedpenaltyfactor' => 1,
                'fisherinformation' => [1 => 0],
                'difficulty' => -1,
            ],
            '2' => (object) [
                'id' => 2,
                'catscaleid' => 1,
                'lasttimeplayedpenaltyfactor' => 1,
                'fisherinformation' => [1 => 1],
                'difficulty' => 0,
            ],
            '3' => (object) [
                'id' => 3,
                'catscaleid' => 1,
                'lasttimeplayedpenaltyfactor' => 0,
                'fisherinformation' => [1 => 1],
                'difficulty' => 1,
            ],
            '4' => (object) [
                'id' => 4,
                'catscaleid' => 1,
                'lasttimeplayedpenaltyfactor' => 0,
                'fisherinformation' => [1 => 0],
                'difficulty' => 2,
            ],
        ];
        $context['penalty_threshold'] = 5;
        $context['catscaleid'] = 1;

        return [
            'test' => [
                'context' => $context,
                'expected' => [
                    'id' => 2,
                ],
            ],
        ];
    }
}
