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
        foreach ($context['questions'] as $question) {
            $scalecount = array_key_exists($question->catscaleid, $context['playedquestionsperscale'])
            ? count($context['playedquestionsperscale'][$question->catscaleid])
            : 0;
            if (! array_key_exists($question->catscaleid, $scalefractions)) {
                if (! $scalecount) {
                    $scalefractions[$question->catscaleid] = 0.5;
                } else {
                    $scalefractions[$question->catscaleid] = array_sum(
                        array_map(
                            fn ($q) => $userresponses[$USER->id]['component'][$q->id]['fraction'],
                            $context['playedquestionsperscale'][$question->catscaleid]
                        )
                    ) / count($context['playedquestionsperscale'][$question->catscaleid]);
                }
            }
            $standarderrorplayed = $context['se'][$question->catscaleid];
            $testinfo = $standarderrorplayed === INF ? 0 : 1 / $standarderrorplayed ** 2;
            $question->processterm = max(0.1, $testinfo) / max(1, $scalecount);
            $scaleability = $context['person_ability'][$question->catscaleid];
            $abilitydifference = ($scaleability - $context['person_ability'][$context['catscaleid']]);
            $question->scaleterm = 1 / (1 + exp($testinfo * $abilitydifference));
            $question->itemterm = (1 / (
                1 + exp($testinfo * 2 * (0.5 - $scalefractions[$question->catscaleid]) * ($question->difficulty - $scaleability))
            )) ** $scalecount;
            $question->score = $question->fisherinformation[$context['catscaleid']]
                * $question->processterm
                * $question->scaleterm
                * $question->itemterm;
        }

        // In order to have predictable results, in case the values of two
        // elements are exactly the same, sort by question ID.
        uasort($context['questions'], function($q1, $q2) {
            if (! ($q2->score === $q1->score)) {
                return $q2->score <=> $q1->score;
            }
            return $q1->id <=> $q2->id;
        });

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
