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
 * Class strategydeficitscore.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\preselect_task;

use cache;
use local_catquiz\catscale;
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
final class strategydeficitscore extends preselect_task implements wb_middleware {

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
        global $USER;
        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        $userresponses = $cache->get('userresponses');
        $scalefractions = [];
        $scalecount = [];

        foreach ($this->context['active_scales'] as $scaleid) {
            $scalecount[$scaleid] = array_key_exists($scaleid, $context['playedquestionsperscale'])
                ? count($context['playedquestionsperscale'][$scaleid])
                : 0;
            if (!array_key_exists($scaleid, $context['playedquestionsperscale'])) {
                $scalefractions[$scaleid] = 0.5;
            } else {
                $scalefractions[$scaleid] = array_sum(
                    array_map(
                        fn ($q) => $userresponses[$USER->id]['component'][$q->id]['fraction'],
                        $context['playedquestionsperscale'][$scaleid]
                    )
                ) / count($context['playedquestionsperscale'][$scaleid]);
            }
        }

        foreach ($context['questions'] as $question) {
            $affectedscales = [$question->catscaleid, ...catscale::get_ancestors($question->catscaleid)];
            arsort($affectedscales); // Traverse from root to leave.

            foreach ($affectedscales as $scaleid) {
                if (! in_array($scaleid, $this->context['active_scales'])) {
                    continue;
                }

                $standarderrorplayed = $context['se'][$scaleid];
                $testinfo = $standarderrorplayed === INF ? 0 : 1 / $standarderrorplayed ** 2;
                $question->processterm = max(0.1, $testinfo) / max(1, $scalecount[$scaleid]);
                $scaleability = $context['person_ability'][$scaleid];
                $abilitydifference = ($scaleability - $context['person_ability'][$context['catscaleid']]);
                $question->scaleterm = 1 / (1 + exp($testinfo * $abilitydifference));
                $question->itemterm = (1 / (
                    1 + exp($testinfo * 2 * (0.5 - $scalefractions[$scaleid]) * ($question->difficulty - $scaleability))
                )) ** $scalecount[$scaleid];

                $score = $question->fisherinformation[$scaleid]
                    * $question->processterm
                    * $question->scaleterm
                    * $question->itemterm;
                if (! property_exists($question, 'score') || $score > $question->score) {
                    $question->score = $score;
                }
            }
        }

        // In order to have predictable results, in case the values of two
        // elements are exactly the same, sort by question ID.
        $remainingquestions = array_filter($context['questions'], fn ($q) => property_exists($q, 'score'));
        uasort($remainingquestions, function($q1, $q2) {
            if (! ($q2->score === $q1->score)) {
                return $q2->score <=> $q1->score;
            }
            return $q1->id <=> $q2->id;
        });

        $selectedquestion = reset($remainingquestions);
        return result::ok($selectedquestion);
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
