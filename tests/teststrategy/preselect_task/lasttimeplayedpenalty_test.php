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
 * Tests the lasttimeplayedpenalty class
 *
 * @package    local_catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright  2024 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\preselect_task;

use local_catquiz\teststrategy\preselect_task\lasttimeplayedpenalty;
use PHPUnit\Framework\TestCase;

/**
 * Tests the lasttimeplayedpenalty class
 *
 * @package    local_catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright  2024 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_catquiz\teststrategy\preselect_task\lasttimeplayedpenalty
 */
final class lasttimeplayedpenalty_test extends TestCase {

    /**
     * @var int
     *
     * Use 1 day as default timerange
     */
    const TIMERANGE = 60 * 60 * 24;

    /**
     * Checks that the penalty is calculated correctly
     *
     * @param float $expected
     * @param \stdClass $question
     * @param int $currenttime
     * @param float $penaltytimerange
     * @return void
     * @dataProvider penalty_is_calculated_correctly_provider
     */
    public function test_penalty_is_calculated_correctly(
        float $expected,
        \stdClass $question,
        int $currenttime,
        float $penaltytimerange
    ): void {
        $lasttimeplayedpenalty = new lasttimeplayedpenalty();
        $penalty = $lasttimeplayedpenalty->get_penalty_factor($question, $currenttime, $penaltytimerange);
        $this->assertEqualsWithDelta($expected, $penalty, 0.000001);
    }

    /**
     * Dataprovider for the penalty test.
     *
     * @return array
     */
    public static function penalty_is_calculated_correctly_provider(): array {
        $now = time();
        return [
            'Penalty is 0.01 if 10% of timerange has passed' => [
                    'expected' => 0.01,
                    'question' => (object) ['userlastattempttime' => $now - 0.1 * self::TIMERANGE],
                    'currenttime' => $now,
                    'penaltytimerange' => self::TIMERANGE,
            ],
            'Penalty is 0.5 if no time has passed' => [
                    'expected' => 0.5,
                    'question' => (object) ['userlastattempttime' => $now - self::TIMERANGE],
                    'currenttime' => $now,
                    'penaltytimerange' => self::TIMERANGE,
            ],
            'Penalty is 0.99 if 190% of timerange has passed' => [
                    'expected' => 0.99,
                    'question' => (object) ['userlastattempttime' => $now - 1.9 * self::TIMERANGE],
                    'currenttime' => $now,
                    'penaltytimerange' => self::TIMERANGE,
            ],
        ];
    }
}

