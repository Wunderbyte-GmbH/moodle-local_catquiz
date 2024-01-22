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
 * Class filterbystandarderror.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\preselect_task;

use cache;
use local_catquiz\catquiz;
use local_catquiz\catscale;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\result;
use local_catquiz\local\status;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\wb_middleware;

/**
 * Modifies the $context['active_scales'] content depending on whether a scale
 * should be included or excluded.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filterbystandarderror extends preselect_task implements wb_middleware {

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
        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        $activescales = $cache->get('active_scales');

        $lastquestion = $this->context['lastquestion'];
        if (!$lastquestion) {
            // If this is the first question and the cache is not yet set, set the
            // root scale active.
            $activescales = [$this->context['catscaleid']];
            $this->context['active_scales'] = $activescales;
            $cache->set('active_scales', $activescales);
            return $this->next();
        }

        $scaleid = $lastquestion->catscaleid;
        $updatedscales = [$scaleid, ...catscale::get_ancestors($scaleid)];
        foreach ($updatedscales as $scaleid) {
            // All played items that belong to the scale or one of its ancestor scales.
            $playeditems = model_item_param_list::from_array(
                $this->context['playedquestionsperscale'][$scaleid]
            );

            $has_maxitems = $this->context['max_attempts_per_scale'] !== -1
                && count($playeditems) >= $this->context['max_attempts_per_scale'];
            $has_min_se = $this->context['se'][$scaleid] <= $this->context['se_min'];
            $ability_delta_below = isset($context['prev_ability'][$scaleid])
                && abs($context['prev_ability'][$scaleid] - $context['person_ability'][$scaleid]) <= 0.1; // TODO configure
            $drop = $has_maxitems || ($has_min_se && $ability_delta_below);
            if ($drop && !in_array($scaleid, $activescales)) {
                continue;
            }

            $allitems = model_item_param_list::from_array(
                $this->context['questionsperscale'][$scaleid]
            );
            $remainingitems = clone ($allitems);
            foreach ($remainingitems as $i) {
                if (in_array($i->get_id(), $playeditems->get_item_ids())) {
                    $remainingitems->offsetUnset($i->get_id());
                }
            }

            $remaining = $this->context['max_attempts_per_scale'] === -1
                ? count($remainingitems)
                : $this->context['max_attempts_per_scale'] - count($playeditems);
            $testpotential = catscale::get_testpotential(
                $this->context['person_ability'][$scaleid],
                $remainingitems,
                $remaining
            );
            $testinformation = catscale::get_testinformation(
                $this->context['person_ability'][$scaleid],
                $playeditems
            );

            if ($drop) {
                getenv('CATQUIZ_CREATE_TESTOUTPUT') && printf("drop %s%s", (catscale::return_catscale_object($scaleid))->name, PHP_EOL);
                unset($activescales[array_search($scaleid, $activescales)]);
                // TODO subscales inherit values.
                $inherit_scales = array_filter(array_keys(catscale::get_next_level_subscales_ids_from_parent([$scaleid])), fn ($id) => $this->context['person_ability'][$id] === 0.0);
                $inherit_val = $this->context['person_ability'][$scaleid] - $this->context['se'][$scaleid];
                $fisherinformation = new fisherinformation();
                foreach ($inherit_scales as $subscaleid) {
                    catquiz::update_person_param(
                        $this->context['userid'],
                        $this->context['contextid'],
                        $subscaleid,
                        $inherit_val
                    );
                    getenv('CATQUIZ_CREATE_TESTOUTPUT') && printf("inhere %s%s", (catscale::return_catscale_object($subscaleid))->name, PHP_EOL);
                    $this->context['person_ability'][$subscaleid] = $inherit_val;
                    // Now we need to update the fisher information for all questions of that scale.
                    foreach ($this->context['questions'] as $q) {
                        if (!array_key_exists($q->model, $this->context['installed_models'])) {
                            continue;
                        }
                        $model = $this->context['installed_models'][$q->model];
                        $q->fisherinformation[$subscaleid] = $fisherinformation->get_fisherinformation(
                            $q,
                            $inherit_val,
                            $model
                        );
                    }

                    // Check if it should be enabled
                    $testpotential = catscale::get_testpotential(
                        $inherit_val,
                        model_item_param_list::from_array($this->context['questionsperscale'][$subscaleid]),
                        count($this->context['questionsperscale'][$subscaleid])
                    );
                    if ($testpotential > 1 / $this->context['se_max'] ** 2) {
                        // enable
                        $activescales[] = $subscaleid;
                        getenv('CATQUIZ_CREATE_TESTOUTPUT') && printf("enact %s%s", (catscale::return_catscale_object($subscaleid))->name, PHP_EOL);
                    }
                }
                continue;
            }

            $exclude = $testpotential + $testinformation <= 1 / $this->context['se_max'] ** 2;
            if ($exclude && in_array($scaleid, $activescales)) {
                unset($activescales[array_search($scaleid, $activescales)]);
                getenv('CATQUIZ_CREATE_TESTOUTPUT') && printf("deact %s%s", (catscale::return_catscale_object($scaleid))->name, PHP_EOL);
            }
            if (!$exclude && !in_array($scaleid, $activescales)) {
                $activescales[] = $scaleid;
                getenv('CATQUIZ_CREATE_TESTOUTPUT') && printf("enact %s%s", (catscale::return_catscale_object($scaleid))->name, PHP_EOL);
            }

        }
        $activescales = array_unique($activescales);
        $this->context['active_scales'] = $activescales;
        $cache->set('active_scales', $activescales);

        return $this->next();
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
            'playedquestionsperscale',
            'se_max'
        ];
    }

}
