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
use local_catquiz\local\status;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\wb_middleware;

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
            $q->lasttimeplayedpenalty = $this->get_penalty($q, $now, $context['penalty_time_range']);
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
            'penalty_time_range',
        ];
    }

    /**
     * Calculates the penalty for the given question according to the time it was played.
     *
     * The penalty should decline linearly with the time that passed since the last attempt.
     * After 30 days, the penalty should be 0 again.
     *
     * For performance reasons, $now is passed as parameter
     *
     * @param mixed $question
     * @param mixed $now
     * @param mixed $penaltytimerange
     *
     * @return int
     *
     */
    private function get_penalty($question, $now, $penaltytimerange): int {
        $secondspassed = $now - $question->userlastattempttime;
        return max(0, $penaltytimerange - $secondspassed);
    }
}
