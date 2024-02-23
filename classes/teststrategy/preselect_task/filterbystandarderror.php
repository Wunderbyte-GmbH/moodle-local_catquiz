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

use local_catquiz\catquiz;
use local_catquiz\catscale;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\result;
use local_catquiz\local\status;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\teststrategy\progress;
use local_catquiz\wb_middleware;

/**
 * Includes or excludes scales based on their information
 *
 * Enables or disables scales depending on their standarderror and number of played questions.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filterbystandarderror extends preselect_task implements wb_middleware {

    /**
     * @var progress
     */
    private progress $progress;

    /**
     * @var callable $next
     */
    private $next;

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
        $this->next = $next;
        $this->progress = $context['progress'];

        if ($this->progress->is_first_question()) {
            // If this is the first question and the cache is not yet set, set the
            // root scale active.
            $this->progress->set_active_scales([$this->context['catscaleid']]);
            return $next($context);
        }

        if (!$this->progress->has_new_response()) {
            return $next($context);
        }

        $lastquestion = $this->progress->get_last_question();
        $scaleid = $lastquestion->catscaleid;
        $updatedscales = [$scaleid, ...catscale::get_ancestors($scaleid)];
        if ($context['teststrategy'] == LOCAL_CATQUIZ_STRATEGY_FASTEST) {
            return $this->filter_for_radical_cat($updatedscales);
        }
        foreach ($updatedscales as $scaleid) {
            $drop = $this->check_scale_should_be_dropped($scaleid);

            if ($drop && !in_array($scaleid, $this->progress->get_active_scales())) {
                continue;
            }

            $allitems = model_item_param_list::from_array(
                $this->context['questionsperscale'][$scaleid]
            );
            $remainingitems = clone ($allitems);
            $playeditems = model_item_param_list::from_array(
                $this->progress->get_playedquestions(true)[$scaleid]
            );
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
                getenv('CATQUIZ_CREATE_TESTOUTPUT') && printf(
                    "drop %s%s",
                    (catscale::return_catscale_object($scaleid))->name, PHP_EOL
                );
                $this->progress->drop_scale($scaleid);
                $inheritscales = $this->get_scale_heirs($scaleid);
                $inheritval = $this->context['person_ability'][$scaleid] - $this->context['se'][$scaleid];
                $fisherinformation = new fisherinformation();
                foreach ($inheritscales as $subscaleid) {
                    catquiz::update_person_param(
                        $this->context['userid'],
                        $this->context['contextid'],
                        $subscaleid,
                        $inheritval
                    );
                    getenv('CATQUIZ_CREATE_TESTOUTPUT') && printf(
                        "inhere %s%s",
                        (catscale::return_catscale_object($subscaleid))->name, PHP_EOL
                    );
                    $this->context['person_ability'][$subscaleid] = $inheritval;
                    // Now we need to update the fisher information for all questions of that scale.
                    foreach ($this->context['questions'] as $q) {
                        if (!array_key_exists($q->model, $this->context['installed_models'])) {
                            continue;
                        }
                        $model = $this->context['installed_models'][$q->model];
                        $q->fisherinformation[$subscaleid] = $fisherinformation->get_fisherinformation(
                            $q,
                            $inheritval,
                            $model
                        );
                    }

                    // Check if it should be enabled.
                    $testpotential = catscale::get_testpotential(
                        $inheritval,
                        model_item_param_list::from_array($this->context['questionsperscale'][$subscaleid]),
                        count($this->context['questionsperscale'][$subscaleid])
                    );
                    if ($testpotential > 1 / $this->context['se_max'] ** 2) {
                        // Enable the scale.
                        $this->progress->add_active_scale($subscaleid);
                        getenv('CATQUIZ_CREATE_TESTOUTPUT') && printf(
                            "enact %s%s", (catscale::return_catscale_object($subscaleid))->name, PHP_EOL
                        );
                    }
                }
                continue;
            }

            $exclude = $testpotential + $testinformation <= 1 / $this->context['se_max'] ** 2;
            if ($exclude && $this->progress->is_active_scale($scaleid)) {
                $this->progress->drop_scale($scaleid);
                getenv('CATQUIZ_CREATE_TESTOUTPUT') && printf(
                    "deact %s%s", (catscale::return_catscale_object($scaleid))->name, PHP_EOL
                );
            }
            if (!$exclude && !$this->progress->is_active_scale($scaleid)) {
                $this->progress->add_active_scale($scaleid);
                getenv('CATQUIZ_CREATE_TESTOUTPUT') && printf(
                    "enact %s%s", (catscale::return_catscale_object($scaleid))->name, PHP_EOL
                );
            }

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
            'progress',
            'se_max',
            'progress',
            'pp_min_inc',
        ];
    }

    /**
     * Returns the list of scales that should inherit from the given scale.
     *
     * @param int $scaleid
     * @return array
     */
    private function get_scale_heirs($scaleid): array {
        // Subscales inherit values of parent when their ability wasn't calculated yet (is still 0.0).
        return array_filter(
            array_keys(catscale::get_next_level_subscales_ids_from_parent([$scaleid])),
            fn ($id) => isset($this->context['person_ability'][$id])
            && $this->context['person_ability'][$id] === 0.0
        );
    }

    private function filter_for_radical_cat(array $updatedscales): result {
        $drop = false;
        foreach (array_reverse($updatedscales) as $scaleid) {
            if (!$this->check_scale_should_be_dropped($scaleid)) {
                continue;
            }
            $drop = true;
            $inheritval = $this->context['person_ability'][$scaleid];
            $inheritscales = $this->get_scale_heirs($scaleid);
            foreach ($inheritscales as $subscaleid) {
                catquiz::update_person_param(
                    $this->context['userid'],
                    $this->context['contextid'],
                    $subscaleid,
                    $inheritval
                );
                $this->context['person_ability'][$subscaleid] = $inheritval;
            }
        }
        if ($drop) {
            return result::err(status::ERROR_NO_REMAINING_QUESTIONS);
        }
        return ($this->next)($this->context);
    }

    private function check_scale_should_be_dropped(int $scaleid): bool {
        // All played items that belong to the scale or one of its ancestor scales.
        $playeditems = model_item_param_list::from_array(
            $this->progress->get_playedquestions(true)[$scaleid]
        );

        $hasmaxitems = $this->context['max_attempts_per_scale'] !== -1
            && count($playeditems) >= $this->context['max_attempts_per_scale'];
        $hasminse = $this->context['se'][$scaleid] <= $this->context['se_min'];
        $abilitydifference = $this->context['prev_ability'][$scaleid] - $this->context['person_ability'][$scaleid];
        $abilitydeltabelow = isset($this->context['prev_ability'][$scaleid])
            && abs($abilitydifference) <= $this->context['pp_min_inc'];
        return $hasmaxitems || ($hasminse && $abilitydeltabelow);
    }
}
