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
 * Class maybe_return_pilot.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\preselect_task;

use local_catquiz\local\result;
use local_catquiz\teststrategy\preselect_task;

/**
 * Randomly returns a pilot question according to the `pilot_ratio` parameter
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class maybe_return_pilot extends preselect_task {
    /**
     * Run preselect task.
     *
     * @param array $context
     * @param callable $next
     *
     * @return result
     *
     */
    public function run(array &$context): result {
        $this->context = $context;
        if ($context['pilot_ratio'] === 0) {
            return result::ok($context);
        }

        // TODO: Replace with check: if fraction in current attempt is 0 or 1, skip.
        if ($this->context['progress']->is_first_question()) {
            return result::ok($context);
        }

        $pilotquestions = array_filter($context['questions'], fn($q) => $q->is_pilot);
        $nonpilotquestions = array_udiff($context['questions'], $pilotquestions, fn($a, $b) => $a->id - $b->id);
        // If there are no pilot questions, then return a random productive question.
        if (count($pilotquestions) === 0) {
            return result::ok($context);
        }

        // If there are only pilot questions, then return a random pilot question.
        if (count($nonpilotquestions) === 0) {
            return result::ok($context);
        }

        if ($this->should_return_pilot()) {
            $context['questions'] = $pilotquestions;
            $addattemptstask = new numberofgeneralattempts();
            $lasttimeplayedpenaltytask = new lasttimeplayedpenalty();
            $scoretask = new strategybalancedscore();
            return $addattemptstask
                ->run($context)
                ->and_then(fn() => $lasttimeplayedpenaltytask->run($context))
                ->and_then(fn() => $scoretask->run($context));
        } else {
            $context['questions'] = $nonpilotquestions;
            return result::ok($context);
        }
    }

    /**
     * Indicates if a pilot question should be returned.
     *
     * This can be overwritten for testing.
     * @return bool
     */
    protected function should_return_pilot(): bool {
        $rand = rand(0, 100);
        $shouldreturnpilot = $rand <= $this->context['pilot_ratio'];
        return $shouldreturnpilot;
    }

    /**
     * Get required context keys.
     *
     * @return array
     *
     */
    public function get_required_context_keys(): array {
        return [
            'pilot_ratio',
            'questions',
        ];
    }
}
