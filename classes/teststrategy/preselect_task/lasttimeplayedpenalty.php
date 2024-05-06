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
        return 1 / (1 + exp(4.6 * (1 - ($currenttime - $lastplayed) / $penaltytimerange)));
    }
}
