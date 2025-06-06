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
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\preselect_task;

use local_catquiz\catquiz;
use local_catquiz\catscale;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\result;
use local_catquiz\local\status;
use local_catquiz\teststrategy\context\loader\personability_loader;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\teststrategy\progress;
use UnexpectedValueException;

/**
 * Includes or excludes scales based on their information
 *
 * Enables or disables scales depending on their standarderror and number of played questions.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filterbystandarderror extends preselect_task {
    /**
     * @var progress
     */
    private progress $progress;

    /**
     * Run method.
     *
     * @param array $context
     *
     * @return result
     *
     */
    public function run(array &$context): result {
        $this->context = $context;
        $this->progress = $context['progress'];

        if ($this->progress->is_first_question()) {
            return result::ok($context);
        }

        if (!$this->progress->has_new_response()) {
            return result::ok($context);
        }

        if ($this->progress->get_last_question()->is_pilot) {
            return result::ok($context);
        }

        $lastquestion = $this->progress->get_last_question();
        $scaleid = $lastquestion->catscaleid;
        $updatedscales = [$scaleid, ...catscale::get_ancestors($scaleid)];
        if ($context['teststrategy'] == LOCAL_CATQUIZ_STRATEGY_FASTEST) {
            return $this->filter_for_cat($updatedscales);
        }
        foreach ($updatedscales as $scaleid) {
            $drop = $this->check_scale_should_be_dropped($scaleid);

            if ($drop && !in_array($scaleid, $this->progress->get_active_scales())) {
                continue;
            }

            if ($drop) {
                getenv('CATQUIZ_CREATE_TESTOUTPUT') && printf(
                    "%d: [SE] drop %s%s",
                    count($this->progress->get_playedquestions()),
                    (catscale::return_catscale_object($scaleid))->name,
                    PHP_EOL
                );
                $this->progress->drop_scale($scaleid);
                $inheritscales = $this->get_scale_heirs($scaleid);
                switch ($this->context['teststrategy']) {
                    case LOCAL_CATQUIZ_STRATEGY_LOWESTSUB:
                        $inheritval = $this->context['person_ability'][$scaleid] - $this->context['se'][$scaleid];
                        break;
                    case LOCAL_CATQUIZ_STRATEGY_HIGHESTSUB:
                        $inheritval = $this->context['person_ability'][$scaleid] + $this->context['se'][$scaleid];
                        break;
                    default:
                        $inheritval = $this->context['person_ability'][$scaleid];
                        break;
                }
                $fisherinformation = new fisherinformation();
                foreach ($inheritscales as $subscaleid) {
                    catquiz::update_person_param(
                        $this->context['userid'],
                        $this->context['contextid'],
                        $subscaleid,
                        $inheritval
                    );
                    getenv('CATQUIZ_CREATE_TESTOUTPUT') && printf(
                        "%d: [SE] inhere %s - pp: %.5f\n",
                        count($this->progress->get_playedquestions()),
                        (catscale::return_catscale_object($subscaleid))->name,
                        $inheritval
                    );
                    $this->context['person_ability'][$subscaleid] = $inheritval;
                    $this->progress->set_ability($inheritval, $subscaleid);
                    // Now we need to update the fisher information for all questions of that scale.
                    foreach ($this->context['questions'] as $q) {
                        if (!array_key_exists($q->model, $this->context['installed_models'])) {
                            continue;
                        }
                        $q->fisherinformation[$subscaleid] = $fisherinformation->get_fisherinformation(
                            $q,
                            $inheritval
                        );
                    }
                }
                continue;
            }
        }

        return result::ok($context);
    }

    /**
     * Returns the list of scales that should inherit from the given scale.
     *
     * @param int $scaleid
     * @return array
     */
    private function get_scale_heirs($scaleid): array {
        if ($this->context['teststrategy'] == LOCAL_CATQUIZ_STRATEGY_ALLSUBS) {
            return [];
        }
        // Subscales inherit values of parent when their ability wasn't calculated yet (is still the default ability).
        return array_filter(
            array_keys(catscale::get_next_level_subscales_ids_from_parent([$scaleid])),
            fn ($id) => isset($this->context['person_ability'][$id])
            && $this->context['person_ability'][$id] === personability_loader::get_default_ability()
        );
    }

    /**
     * Contains the filtering logic for the CAT test strategy
     *
     * @param array $updatedscales
     * @return result
     * @throws UnexpectedValueException
     */
    private function filter_for_cat(array $updatedscales): result {
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
        return result::ok($this->context);
    }

    /**
     * Checks if a scale should be dropped.
     *
     * @param int $scaleid
     * @return bool
     * @throws UnexpectedValueException
     */
    private function check_scale_should_be_dropped(int $scaleid): bool {
        // All played items that belong to the scale or one of its ancestor scales.

        $playeditems = model_item_param_list::from_array(
            $this->progress->without_pilots()->get_playedquestions(true)[$scaleid]
        );

        // Special treatment for the main scale: exclude it only, if the minimum number of questions
        // per attempt AND questions per scale have been played.
        $ismainscale = $scaleid === intval($this->context['catscaleid']);
        if ($ismainscale
            && (
                count($this->progress->get_playedquestions()) < $this->context['minimumquestions']
                || count($playeditems) < $this->context['min_attempts_per_scale']
            )
        ) {
            return false;
        }

        $hasmaxitems = $this->context['max_attempts_per_scale'] !== -1
            && count($playeditems) >= $this->context['max_attempts_per_scale'];
        $hasminse = $this->context['se'][$scaleid] <= $this->context['se_min'];
        $abilitydifference = $this->context['prev_ability'][$scaleid] - $this->context['person_ability'][$scaleid];
        $abilitydeltabelow = isset($this->context['prev_ability'][$scaleid])
            && abs($abilitydifference) <= $this->context['pp_min_inc'];
        return $hasmaxitems || ($hasminse && $abilitydeltabelow);
    }
}
