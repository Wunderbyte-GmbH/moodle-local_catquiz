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
 * Class updatepersonability.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\preselect_task;

use cache;
use Exception;
use local_catquiz\catcalc;
use local_catquiz\catcontext;
use local_catquiz\catquiz;
use local_catquiz\catscale;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_person_param_list;
use local_catquiz\local\model\model_responses;
use local_catquiz\local\model\model_strategy;
use local_catquiz\local\result;
use local_catquiz\local\status;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\teststrategy\progress;
use local_catquiz\wb_middleware;
use moodle_exception;

/**
 * Update the person ability based on the result of the previous question
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class updatepersonability extends preselect_task implements wb_middleware {

    /**
     *
     * @var mixed $userresponses
     */
    public $userresponses;

    /**
     *
     * @var mixed $arrayresponses
     */
    public $arrayresponses;

    /**
     * Contains IDs of catscales that have at least two different (correct and
     * incorrect) answers.
     *
     * @var array $diverseanswers
     */
    private array $diverseanswers = [];

    /**
     * @var float $parentability
     *
     * Used to temporarily store the ability of a parent scale.
     */
    private float $parentability;

    /**
     * @var float $parentse
     *
     * Used to temporarily store the standard error of a parent scale.
     */
    private float $parentse;

    /**
     * @var float $initialability Initial ability
     */
    protected float $initialability;

    /**
     * @var float $initialse Initial standard error
     */
    protected float $initialse;

    /**
     * @var array $scalestoupdate
     */
    private array $scalestoupdate;

    /**
     * @var progress $progress
     */
    private progress $progress;

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
        $this->progress = $context['progress'];
        $this->initialability = $this->get_initial_ability();
        $this->initialse = $this->get_initial_standarderror();
        $this->parentability = $this->initialability;
        $this->parentse = $this->initialse;
        // If we do not know the answer to the last question, we do not have to
        // update the person ability. Also, pilot questions should not be used
        // to update a student's ability.
        if ($this->progress->is_first_question()
            || $this->progress->get_last_question() === null) {
            $context['skip_reason'] = 'lastquestionnull';
            return $next($context);

        }

        $this->userresponses = (new model_responses())->setdata(
            [$context['userid'] => ['component' => $this->progress->get_user_responses()]],
            false
        );
        $context['lastresponse'] = $this->userresponses->get_last_response($context['userid']);

        if (!empty($this->progress->get_last_question()->is_pilot)) {
            $context['skip_reason'] = 'pilotquestion';
            return $next($context);
        }

        $this->arrayresponses = reset(($this->userresponses->as_array())[$context['userid']]);

        $catscaleid = $this->progress->get_last_question()->catscaleid;
        $this->scalestoupdate = array_reverse(
            [$catscaleid, ...catscale::get_ancestors($catscaleid)]
        );
        try {
            $index = 0;
            foreach ($this->scalestoupdate as $scale) {
                $isancestor = $scale != $catscaleid;
                $parentscale = $index == 0 ? null : $this->scalestoupdate[$index - 1];
                $index++;
                $context = $this->updateability($context, $scale, $isancestor, $parentscale);
            }
        } catch (\Exception $e) {
            if ($e->getMessage() === status::ABORT_PERSONABILITY_NOT_CHANGED) {
                return result::err(status::ABORT_PERSONABILITY_NOT_CHANGED);
            } else {
                throw $e;
            }
        }

        return $next($context);
    }

    /**
     * Update ability.
     *
     * @param array $context
     * @param int $catscaleid
     * @param bool $isancestor
     * @param int $parentscale
     *
     * @return mixed
     *
     */
    private function updateability(array $context, int $catscaleid, $isancestor = false, $parentscale = null) {
        global $CFG;
        $itemparamlist = $this->get_item_param_list(
            $this->userresponses,
            $catscaleid
        );

        // Remove all responses that are not in the item param list and check again.
        $arrayresponsesforscale = [];
        foreach ($itemparamlist as $item) {
            $arrayresponsesforscale[$item->get_id()] = $this->arrayresponses[$item->get_id()];
        }
        $this->diverseanswers[$catscaleid] = $this->has_sufficient_responses($arrayresponsesforscale);

        $parentability = $context['person_ability'][$parentscale] ?? $this->initialability;
        $startvalue = $context['person_ability'][$catscaleid] ?? $this->initialability;
        if ($parentscale && $this->diverseanswers[$parentscale] ?? false) {
            $startvalue = $parentability;
        }

        try {
            $updatedability = catcalc::estimate_person_ability(
                $this->arrayresponses,
                $itemparamlist,
                $startvalue,
                $this->parentability,
                $this->parentse
            );
        } catch (moodle_exception $e) {
            // If we get an excpetion, re-throw it with more information.
            $message = sprintf(
                'Can not update ability for scale %d in context %d: %s',
                $catscaleid,
                catscale::get_context_id($catscaleid),
                $e->getMessage()
            );
            throw new Exception($message);
        }

        if (is_nan($updatedability)) {
            // In a production environment, we can use fallback values. However,
            // during development we want to see when we get unexpected values.
            if ($CFG->debug > 0) {
                throw new moodle_exception('error', 'local_catquiz');
            }
            // If we already have an ability, just continue with that one and do not update it.
            // Otherwise, use 0 as default value.
            $context['skip_reason'] = 'abilityisnan';
            if (!is_nan($context['person_ability'][$catscaleid])) {
                return $context;
            } else {
                $context['person_ability'][$catscaleid] = 0;
                return $context;
            }
        }

        if ($this->diverseanswers[$catscaleid] ?? false) {
            $this->parentability = $updatedability;
            $this->parentse = catscale::get_standarderror($updatedability, $itemparamlist);
        }

        $this->update_person_param($catscaleid, $updatedability);

        $context['prev_ability'][$catscaleid] = $context['person_ability'][$catscaleid];
        $context['person_ability'][$catscaleid] = $updatedability;
        $this->progress->set_ability($updatedability, $catscaleid);

        if ($this->diverseanswers[$catscaleid]) {
            // Get all scales that are a subscale of the current catscale.
            $scales = array_filter(
                array_keys($this->context['person_ability']),
                fn ($id) => in_array($id, catscale::get_subscale_ids($catscaleid))
            );
            // Exclude scales that will be updated anyway.
            $scales = array_filter($scales, fn ($s) => !in_array($s, $this->scalestoupdate));
            foreach ($scales as $scale) {
                // Exclude scales that have wrong and right answers.
                $itemparamlist = $this->userresponses->get_items_for_scale(
                    $scale,
                    $context['contextid']
                );
                if (count($itemparamlist) === 0) {
                    continue;
                }

                // Remove all responses that are not in the item param list and check again.
                $arrayresponsesforscale = [];
                foreach ($itemparamlist as $item) {
                    $arrayresponsesforscale[$item->get_id()] = $this->arrayresponses[$item->get_id()];
                }
                $this->diverseanswers[$scale] = $this->has_sufficient_responses($arrayresponsesforscale);
                if ($this->diverseanswers[$scale]) {
                    continue;
                }
                $startvalue = $this->context['person_ability'][$scale] ?? $this->parentability;
                $ability = catcalc::estimate_person_ability(
                    $arrayresponsesforscale,
                    $itemparamlist,
                    $startvalue,
                    $this->parentability,
                    $this->parentse
                );

                $this->update_person_param($scale, $ability);
            }
        }

        return $context;
    }

    /**
     * Get required context keys.
     *
     * @return array
     *
     */
    public function get_required_context_keys(): array {
        return [
            'contextid',
            'catscaleid',
            'progress',
        ];
    }

    /**
     * Test if we can calculate an ability with the given responses.
     * At least two answers with different outcome are needed.
     *
     * Note: Even if this function returns true, we still have to check on a
     * per-scale basis if we have enough answers in that scale.
     *
     * @param array $arrayresponses
     * @return mixed
     */
    private function has_sufficient_responses($arrayresponses) {
        if (! $arrayresponses) {
            return false;
        }
        $first = $arrayresponses[array_key_first($arrayresponses)];
        foreach ($arrayresponses as $ur) {
            if ($ur['fraction'] !== $first['fraction']) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get item param list.
     *
     * @param mixed $responses
     * @param mixed $catscaleid
     *
     * @return model_item_param_list
     *
     */
    protected function get_item_param_list($responses, $catscaleid): model_item_param_list {
        // We will update the person ability. Select the correct model for each item.
        $modelstrategy = new model_strategy($responses);
        $catscalecontext = catscale::get_context_id($catscaleid);
        $catscaleids = [
            $catscaleid,
            ...catscale::get_subscale_ids($catscaleid),
        ];
        $itemparamlists = [];
        $personparams = model_person_param_list::load_from_db($catscalecontext, $catscaleids);
        foreach (array_keys($modelstrategy->get_installed_models()) as $model) {
            $itemparamlists[$model] = model_item_param_list::load_from_db(
                $catscalecontext,
                $model,
                $catscaleids
            );
        }
        $itemparamlist = $modelstrategy->select_item_model($itemparamlists, $personparams);
        return $itemparamlist;
    }

    /**
     * Update person param.
     *
     * @param int $catscaleid
     * @param float $updatedability
     *
     * @return void
     *
     */
    protected function update_person_param(int $catscaleid, float $updatedability): void {
        catquiz::update_person_param(
            $this->context['userid'],
            catscale::get_context_id($catscaleid),
            $catscaleid,
            $updatedability
        );
    }

    /**
     * If the last answer was correct, increase the ability to the halfway point
     * between the current ability and the maximum value.
     * If the last question is partly correct, e.g. the fraction is 0.6, then
     * the change of the person ability is multiplied by that value.
     *
     * For incorrect answers, the change will update the ability towards the
     * minimum value.
     *
     * @param mixed $catscaleid
     *
     * @return mixed
     *
     */
    public function fallback_ability_update($catscaleid) {
        $fraction = $this->userresponses->get_last_response($this->context['userid'])['fraction'];
        $max = ($fraction < 0.5)
            ? -5 * (1 - $fraction)
            : 5 * $fraction;
        return ($this->context['person_ability'][$catscaleid] + $max) / 2;
    }

    /**
     * Returns the mean value that is used for the ability estimation.
     *
     * @return float
     */
    protected function get_initial_ability() {
        // If we already have a value, use that one.
        if ($abilitymainscale = $this->context['person_ability'][$this->context['catscaleid']]) {
            return $abilitymainscale;
        }
        // If we already have more than 30 abilities for this test, get the mean from there.
        if ($mean = $this->calculate_mean_from_past_attempts()) {
            return $mean;
        }
        return 0.0;
    }

    /**
     * Returns the standarderror value that is used for the ability estimation.
     *
     * @return float
     */
    protected function get_initial_standarderror() {
        // If we already have a value, use that one.
        if ($semainscale = $this->context['se'][$this->context['catscaleid']] ?? null) {
            return $semainscale;
        }
        // If we already have more than 30 abilities for this test, get the standarderror from there.
        if ($se = $this->calculate_se_from_past_attempts()) {
            return $se;
        }
        return 1.0;
    }

    private function calculate_mean_from_past_attempts() {
        // TODO: Implement.
        return null;
    }

    private function calculate_se_from_past_attempts() {
        // TODO: Implement.
        return null;
    }
}
