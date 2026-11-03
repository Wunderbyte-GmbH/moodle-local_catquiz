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
     * Returns the CAT attempt the running test belongs to.
     *
     * The selection context carries the id of the attempt of the component; what is stored with a
     * person parameter is the internal one.
     *
     * @return int|null
     */
    private function cat_attempt_id(): ?int {
        if (empty($this->context['attemptid'])) {
            return null;
        }

        return catquiz::get_cat_attempt_id(
            (int) $this->context['attemptid'],
            $this->context['component'] ?? 'mod_adaptivequiz'
        );
    }

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

        // Issue #135: a strategy without scale dropping (FASTEST) only knows the global precision stop.
        // The flag comes from the strategy's capabilities; without it, FASTEST is recognised by its id.
        $globalonly = $context['global_precision_only']
            ?? ((int) $context['teststrategy'] === LOCAL_CATQUIZ_STRATEGY_FASTEST);
        if ($globalonly) {
            return $this->filter_for_global_precision();
        }

        $lastquestion = $this->progress->get_last_question();
        $scaleid = $lastquestion->catscaleid;
        $updatedscales = [$scaleid, ...catscale::get_ancestors($scaleid)];
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
                        $inheritval,
                        $this->cat_attempt_id()
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
     * The only stop a globally choosing strategy knows besides the maximum and an empty pool (issue #135).
     *
     * The root scale - the one the test measures - ends the test when the minimum number of productive
     * questions is reached, its standard error is at or below the target and its ability changed by
     * no more than the minimum increment. Nothing else is looked at: no subscale is dropped, no
     * per-scale maximum or minimum applies, and a subscale that is measured well enough leaves the
     * pool untouched. Ending on such a local condition stopped attempts after 5 to 14 items with a
     * minimum of 15 (issue #134).
     *
     * @return result
     */
    private function filter_for_global_precision(): result {
        $rootid = (int) $this->context['catscaleid'];
        if ($this->progress->get_num_answered_productive_questions() < (int) $this->context['minimumquestions']) {
            return result::ok($this->context);
        }
        $se = $this->context['se'][$rootid] ?? INF;
        $precise = $se <= $this->context['se_min'];
        $stable = isset($this->context['prev_ability'][$rootid], $this->context['person_ability'][$rootid])
            && abs($this->context['prev_ability'][$rootid] - $this->context['person_ability'][$rootid])
                <= $this->context['pp_min_inc'];
        if ($precise && $stable) {
            // Precise enough - not out of questions (mod_adaptivequiz #20).
            return result::err(status::STOP_TARGET_PRECISION);
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
        if (
            $ismainscale
            && (
                $this->progress->get_num_answered_productive_questions() < $this->context['minimumquestions']
                || $this->progress->get_num_answered_productive_questions($scaleid)
                    < $this->context['min_attempts_per_scale']
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
