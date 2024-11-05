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
 * Class lasttimeplayedpenalty.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\preselect_task;

use local_catquiz\local\result;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\wb_middleware;
use stdClass;

/**
 * Test strategy lasttimeplayedpenalty.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lasttimeplayedpenalty extends preselect_task implements wb_middleware {

    /**
     * This is used as factor in the exp function
     *
     * The value of ln(99) / 0.9 should ensure that after 10% of the penalty
     * timerange has passed, the resulting probability exceeds 0.01.
     *
     * Assuming a timerange of 10 days, the weight factor will be as follows:
     *   on day 01: 0,01,
     *   on day 10: 0,5
     *   on day 19: 0,99
     *
     * @see https://github.com/Wunderbyte-GmbH/moodle-local_catquiz/issues/424#issuecomment-2092820159
     */
    const EXP_FACTOR = 5.1056887223718;

    /**
     * Run preselect task.
     *
     * @param array $context
     * @param callable $next
     *
     * @return result
     *
     */
    public function run(array &$context, callable $next): result {
        $now = time();
        $context['questions'] = array_map(function($q) use ($now, $context) {
            $q->lasttimeplayedpenaltyfactor = $this->get_penalty_factor($q, $now, $context['penalty_threshold']);
            return $q;
        }, $context['questions']);

        return $next($context);
    }

    /**
     * Get required context keys.
     *
     * @return array
     *
     */
    public function get_required_context_keys(): array {
        return [
            'questions',
            'penalty_threshold',
        ];
    }

    /**
     * Calculates the penalty factor for the given question according to the time it was last played.
     *
     * For performance reasons, $now is passed as parameter
     *
     * @param stdClass $question
     * @param int $currenttime
     * @param float $penaltytimerange
     *
     * @return float
     */
    public function get_penalty_factor($question, int $currenttime, float $penaltytimerange): float {
        $lastplayed = $question->userlastattempttime;

        // Do all calculations in days.
        $timedifference = ($lastplayed - $currenttime);

        return 1 / (1 + exp(self::EXP_FACTOR / $penaltytimerange * ($timedifference + $penaltytimerange)));
    }
}
