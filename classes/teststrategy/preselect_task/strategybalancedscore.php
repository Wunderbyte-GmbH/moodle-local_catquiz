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
 * Class strategybalancedscore.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\preselect_task;

use local_catquiz\local\result;
use local_catquiz\teststrategy\preselect_task;

/**
 * Add a score to each question and sort questions descending by score
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class strategybalancedscore extends preselect_task {
    /**
     * Run preselect task.
     *
     * @param array $context
     *
     * @return result
     *
     */
    public function run(array &$context): result {
        foreach ($context['questions'] as $question) {
            // If no question has any attempt yet, they all get the same score of 0.
            if ($context['generalnumberofattempts_max'] === 0) {
                $question->score = 0;
                continue;
            }
            $numberofgeneralattemptspenaltyweighted = (1 - (
                $question->numberofgeneralattempts / $context['generalnumberofattempts_max']));
            $question->score = $numberofgeneralattemptspenaltyweighted * $question->lasttimeplayedpenaltyfactor;
        }

        // In order to have predictable results, in case the values of two
        // elements are exactly the same, sort by question ID.
        uasort($context['questions'], function ($q1, $q2) {
            if (! ($q2->score === $q1->score)) {
                return $q2->score <=> $q1->score;
            }
            return $q1->id <=> $q2->id;
        });

        return result::ok(reset($context['questions']));
    }
}
