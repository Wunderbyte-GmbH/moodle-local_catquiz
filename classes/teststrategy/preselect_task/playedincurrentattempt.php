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
 * Class playedincurrentattempt.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\preselect_task;

use local_catquiz\local\result;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\teststrategy\progress;

/**
 * Class playedincurrentattempt for test strategy.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class playedincurrentattempt extends preselect_task {
    /**
     * PENALTY
     *
     * @var int
     */
    const PENALTY = 100;

    /**
     * @var progress
     */
    private progress $progress;

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
        $this->progress = $context['progress'];
        $playedquestions = $this->progress->get_playedquestions();
        foreach ($context['questions'] as $q) {
            if (array_key_exists($q->id, $playedquestions)) {
                $context['questions'][$q->id]->playedinattemptpenalty = self::PENALTY;
            } else {
                $context['questions'][$q->id]->playedinattemptpenalty = 0;
            }
        }

        return result::ok($context);
    }
}
