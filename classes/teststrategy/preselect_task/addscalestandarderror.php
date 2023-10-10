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
 * Class addscalestandarderror.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\preselect_task;

use cache;
use local_catquiz\catscale;
use local_catquiz\local\result;
use local_catquiz\local\status;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\wb_middleware;

/**
 * Calculates the standarderror for each available catscale.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class addscalestandarderror extends preselect_task implements wb_middleware {

    /**
     * Run method.
     *
     * @param array $context
     * @param callable $next
     *
     * @return result
     *
     */
    public function run(array &$context, callable $next): result {
        if (count($context['questions']) === 0) {
                return result::err(status::ERROR_NO_REMAINING_QUESTIONS);
        }

        if (! $context['has_fisherinformation']) {
            return $next($context);
        }

        $fisherinfoperscale = [];
        // Lists for each scale the IDs of the scale itself and its parent scales.
        $scales = [];
        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        $playedquestionids = array_keys($cache->get('playedquestions') ?: []);
        // Questions that have no item parameters and hence fisher
        // information are ignored here.
        $questions = array_filter($context['original_questions'], fn ($q) => ! empty($q->fisherinformation));
        // Sort descending by fisher information. This way, we can calculate the
        // maximum test information TI per subscale when including only the
        // maximum amount of remining questions.
        uasort($questions, fn ($q1, $q2) => $q2->fisherinformation <=> $q1->fisherinformation);
        $catscales = [$context['catscaleid'], ...catscale::get_subscale_ids($context['catscaleid'])];
        $remainingperscale = [];
        $playedquestionsperscale = $cache->get('playedquestionsperscale') ?: [];
        foreach ($catscales as $scaleid) {
            if (! array_key_exists($scaleid, $playedquestionsperscale)) {
                $remainingperscale[$scaleid] = $context['max_attempts_per_scale'];
                continue;
            }
            $remainingperscale[$scaleid] = $context['max_attempts_per_scale'] - count($playedquestionsperscale[$scaleid]);
        }
        foreach ($questions as $q) {
            if (!array_key_exists($q->catscaleid, $scales)) {
                $scales[$q->catscaleid] = [
                    $q->catscaleid,
                    ...catscale::get_ancestors($q->catscaleid),
                ];
            }
            foreach ($scales[$q->catscaleid] as $scaleid) {
                $key = in_array($q->id, $playedquestionids) ? 'played' : 'remaining';
                if ($key === 'remaining' && $remainingperscale[$scaleid] <= 0) {
                    // Do not add the question to the list of remaining
                    // questions if it can never be answered due to the
                    // max_attempts_per_scale setting.
                    continue;
                }
                if (!array_key_exists($scaleid, $fisherinfoperscale)) {
                    $fisherinfoperscale[$scaleid] = [];
                }
                if (! array_key_exists($key, $fisherinfoperscale[$scaleid])) {
                    $fisherinfoperscale[$scaleid][$key] = $q->fisherinformation;
                    continue;
                }
                $fisherinfoperscale[$scaleid][$key] += $q->fisherinformation;
            }
        }

        $threshold = $context['standarderrorpersubscale'];
        $standarderrorperscale = [];
        foreach ($fisherinfoperscale as $catscaleid => $fisherinfo) {
            $fisherinfoplayed = $fisherinfoperscale[$catscaleid]['played'] ?? 0;
            $fisherinfoall
                = ($fisherinfoperscale[$catscaleid]['played'] ?? 0) + ($fisherinfoperscale[$catscaleid]['remaining'] ?? 0);
            $standarderror['played'] = $fisherinfoplayed === 0 ? INF : (1 / sqrt($fisherinfoplayed));
            $standarderror['remaining'] = $fisherinfoall === 0 ? INF : (1 / sqrt($fisherinfoall));
            $standarderrorperscale[$catscaleid] = $standarderror;

            // If we already have enough information about that scale or if
            // we can never get below the standarderror threshold for that
            // scale, exclude it.
            $attemptsinscale = empty($playedquestionsperscale[$catscaleid])
                ? 0
                : count($playedquestionsperscale[$catscaleid]);
            if (
                $attemptsinscale >= $context['min_attempts_per_scale']
                && (
                    $standarderror['played'] < $threshold
                    || $standarderror['remaining'] > $threshold
                )
            ) {
                // Questions of this scale will not be selected in this attempt.
                $context['questions'] = array_filter($context['questions'], fn ($q) => $q->catscaleid != $catscaleid);
            }
        }

        // Handle case where no questions are left.
        if ($context['questions'] === []) {
            return result::err(status::ERROR_NO_REMAINING_QUESTIONS);
        }

        $context['standarderrorperscale'] = $standarderrorperscale;

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
            'original_questions',
            'has_fisherinformation',
            'standarderrorpersubscale',
        ];
    }
}
