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
 * Class strategyfastestscore.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\preselect_task;

use local_catquiz\local\result;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\wb_middleware;

/**
 * Add a score to each question and sort questions descending by score
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class strategyfastestscore extends preselect_task implements wb_middleware {

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
        foreach ($context['questions'] as $question) {
            $question->score = (1 - (
                $question->lasttimeplayedpenalty / $context['penalty_threshold']))
                * $question->fisherinformation;
        }

        // In order to have predictable results, in case the values of two
        // elements are exactly the same, sort by question ID.
        uasort($context['questions'], function($q1, $q2) {
            if (! ($q2->score === $q1->score)) {
                return $q2->score <=> $q1->score;
            }
            return $q2->id <=> $q1->id;
        });

        // Save the surrounding questions with higher and lower difficulty.
        $qdiff = $context['questions'];
        uasort($qdiff, fn ($q1, $q2) => $q1->difficulty <=> $q2->difficulty);
        if (count($qdiff) === 1) {
            $context['nextbestquestion_before'] = reset($qdiff);
            $context['nextbestquestion_after'] = reset($qdiff);
        } else {
            // We find the position of the selected question within the
            // difficulty-sorted question list $qdiff so that we can find the
            // neighboring questions.
            $selected = reset($context['questions']);
            $pos = array_search($selected->id, array_keys($qdiff));

            $afterindex = $pos === count($context['questions']) - 1 ? $pos : $pos + 1;
            [$after] = array_slice($qdiff, $afterindex, 1);

            $beforeindex = $pos === 0 ? 0 : $pos - 1;
            [$before] = array_slice($qdiff, $beforeindex, 1);

            $context['nextbestquestion_before'] = $before;
            $context['nextbestquestion_after'] = $after;
        }

        return result::ok(reset($context['questions']));
    }

    /**
     * Get required context keys.
     *
     * @return array
     *
     */
    public function get_required_context_keys(): array {
        return [
            'penalty_threshold',
            'questions',
        ];
    }
}
