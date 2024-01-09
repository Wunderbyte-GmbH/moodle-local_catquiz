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
use dml_exception;
use local_catquiz\catscale;
use local_catquiz\local\model\model_responses;
use local_catquiz\local\result;
use local_catquiz\local\status;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\wb_middleware;

/**
 * Calculates the standarderror for each available catscale.
 *
 * Note: adds two different arrays to the `$context`:
 *  1. 'standarderrorperscale[$scaleid]'
 *  2. 'se[$scaleid]'
 *
 * The value in 'standarderrorperscale' is based on the fisherinformation (FI)
 * in the root scale. I.e., it is derived from the person ability in the root
 * scale.
 * The value in 'se' is based on the FI and hence ability in the respective
 * (sub)scale.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class addscalestandarderror extends preselect_task implements wb_middleware {

    /**
     * Playes questions per scale.
     *
     * @var array|null
     */
    protected ?array $playedquestionsperscale = null;

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
        $this->playedquestionsperscale = null;
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
        uasort($questions, fn ($q1, $q2) => $q2->fisherinformation[$q1->catscaleid] <=> $q1->fisherinformation[$q2->catscaleid]);
        $remainingperscale = [];
        $context['playedquestionsperscale'] = $this->getplayedquestionsperscale();
        $remainingperscale = $this->getnumberofremainingquestionsperscale();
        foreach ($questions as $q) {
            if (!array_key_exists($q->catscaleid, $scales)) {
                $scales[$q->catscaleid] = $this->get_with_ancestor_scales($q->catscaleid);
            }
            foreach ($scales[$q->catscaleid] as $scaleid) {
                $key = in_array($q->id, $playedquestionids) ? 'played' : 'remaining';
                if ($key === 'remaining') {
                    if ($remainingperscale[$scaleid] <= 0) {
                        // Do not add the question to the list of remaining...
                        // Questions if it can never be answered due to the...
                        // Max_attempts_per_scale setting.
                        continue;
                    } else {
                        $remainingperscale[$scaleid]--;
                    }
                }
                if (!array_key_exists($scaleid, $fisherinfoperscale)) {
                    $fisherinfoperscale[$scaleid] = [];
                }
                if (! array_key_exists($key, $fisherinfoperscale[$scaleid])) {
                    $fisherinfoperscale[$scaleid][$key] = $q->fisherinformation[$scaleid];
                    continue;
                }
                $fisherinfoperscale[$scaleid][$key] += $q->fisherinformation[$scaleid];
            }
        }

        $standarderrorperscale = [];
        foreach ($fisherinfoperscale as $catscaleid => $fisherinfo) {
            $fisherinfoplayed = $fisherinfoperscale[$catscaleid]['played'] ?? 0;
            $fisherinfoall
                = ($fisherinfoperscale[$catscaleid]['played'] ?? 0) + ($fisherinfoperscale[$catscaleid]['remaining'] ?? 0);
            $standarderror['played'] = $fisherinfoplayed === 0 ? INF : (1 / sqrt($fisherinfoplayed));
            $standarderror['remaining'] = $fisherinfoall === 0 ? INF : (1 / sqrt($fisherinfoall));
            $context['standarderrorperscale'][$catscaleid] = $standarderror;
        }

        $userresponses = (new model_responses())->setdata($cache->get('userresponses'), false);
        foreach ($context['person_ability'] as $catscaleid => $ability) {
            $items = $userresponses->get_items_for_scale($catscaleid, $context['contextid']);
            $se = catscale::get_standarderror($ability, $items, INF);
            $context['se'][$catscaleid] = $se;
        }

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
        ];
    }

    /**
     * Can be overwritten by a _testing class to prevent DB access.
     *
     * @param mixed $scaleid
     * @return array
     * @throws dml_exception
     */
    protected function get_with_ancestor_scales($scaleid): array {
        return [
            $scaleid,
            ...catscale::get_ancestors($scaleid),
        ];
    }

    /**
     * Can be overwritten by a _testing class to prevent DB access.
     *
     * @param mixed $scaleid
     * @return array
     */
    protected function get_with_child_scales($scaleid): array {
        return [
            $scaleid,
            ...catscale::get_subscale_ids($scaleid),
        ];
    }
    /**
     * Returns array played questions per scale.
     *
     * @return array
     */
    protected function getplayedquestionsperscale(): array {
        if (($pq = $this->playedquestionsperscale) !== null) {
            return $pq;
        }
        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        $this->playedquestionsperscale = $cache->get('playedquestionsperscale') ?: [];
        return $this->playedquestionsperscale;
    }
    /**
     * Returns array with number of remaining questions per scale.
     *
     * @return array
     */
    protected function getnumberofremainingquestionsperscale(): array {
        $maxattemptsperscale = $this->context['max_attempts_per_scale'] === -1
            ? INF
            : $this->context['max_attempts_per_scale'];
        $pq = $this->getplayedquestionsperscale();
        $catscales = $this->get_with_child_scales($this->context['catscaleid']);
        foreach ($catscales as $scaleid) {
            if (!array_key_exists($scaleid, $pq)) {
                $remainingperscale[$scaleid] = $maxattemptsperscale;
                continue;
            }
            $remainingperscale[$scaleid] = $maxattemptsperscale - count($pq[$scaleid]);
        }
        return $remainingperscale;
    }
}
