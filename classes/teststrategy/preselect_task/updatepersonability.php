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
     * UPDATE_THRESHOLD
     *
     * @var int
     */
    const UPDATE_THRESHOLD = 0.05;


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
     *
     * @var mixed $lastquestion
     */
    public $lastquestion;

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
        $this->lastquestion = $context['lastquestion'];
        // If we do not know the answer to the last question, we do not have to
        // update the person ability. Also, pilot questions should not be used
        // to update a student's ability.
        if ($this->lastquestion === null) {
            $context['skip_reason'] = 'lastquestionnull';
            return $next($context);

        }

        $this->userresponses = $this->update_cached_responses($context);
        $context['lastresponse'] = $this->userresponses->get_last_response($context['userid']);

        if (!empty($this->lastquestion->is_pilot)) {
            $context['skip_reason'] = 'pilotquestion';
            return $next($context);
        }

        if (in_array($context['userid'], $this->userresponses->get_excluded_users())) {
            $context['skip_reason'] = 'notenoughdataforuser';
            return $next($context);
        }

        $components = ($this->userresponses->as_array())[$context['userid']];
        if (count($components) > 1) {
            throw new moodle_exception('User has answers to more than one component.');
        }
        $this->arrayresponses = reset($components);

        $catscaleid = $this->lastquestion->catscaleid;
        try {
            $context = $this->updateability($context, $catscaleid);
            foreach (catscale::get_ancestors($catscaleid) as $ancestorscale) {
                $context = $this->updateability($context, $ancestorscale, true);
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
     *
     * @return mixed
     *
     */
    private function updateability(array $context, int $catscaleid, $isancestor = false) {
        global $CFG;
        $itemparamlist = $this->get_item_param_list(
            $this->userresponses,
            $context['contextid'],
            $catscaleid
        );

        // Remove all responses that are not in the item param list and check again.
        $arrayresponsesforscale = [];
        foreach ($itemparamlist as $item) {
            $arrayresponsesforscale[$item->get_id()] = $this->arrayresponses[$item->get_id()];
        }

        if (! $this->has_sufficient_responses($arrayresponsesforscale)
            || count($itemparamlist) === 0) {
            $updatedability = $this->fallback_ability_update($catscaleid);
            $context['updateabilityfallback'] = true;
        } else {
            $updatedability = $this->get_updated_ability($this->arrayresponses, $itemparamlist);
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

        $this->update_person_param($context, $catscaleid, $updatedability);
        $hasminimumquestions = $context['questionsattempted'] >= $context['minimumquestions'];
        $abilitynotchanged = abs($context['person_ability'][$catscaleid] - $updatedability) < self::UPDATE_THRESHOLD;
        // We do not exclude questions of ancestor scales if the person ability in such a scale did not change.
        if (! $isancestor && $hasminimumquestions && $abilitynotchanged) {
            // The questions of this scale should be excluded in the remaining quiz attempt.
            $context['questions'] = array_filter(
                $context['questions'],
                fn ($q) => $q->catscaleid !== $catscaleid
            );
            if (count($context['questions']) === 0) {
                throw new \Exception(status::ABORT_PERSONABILITY_NOT_CHANGED);
            }
            $this->mark_subscale_as_removed($catscaleid);
            $context['excludedsubscales'][] = $catscaleid;
        }

        $context['person_ability'][$catscaleid] = $updatedability;
        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        $cachedabilities = $cache->get('personabilities') ?: [];
        $cachedabilities[$catscaleid] = $updatedability;
        $cache->set('personabilities', $cachedabilities);
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
            'lastquestion',
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
    private function has_sufficient_responses($arrayresponses = []) {
        if (! $arrayresponses) {
            $arrayresponses = $this->arrayresponses;
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
     * Update cached responses.
     *
     * @param mixed $context
     *
     * @return mixed
     *
     */
    protected function update_cached_responses($context) {
        global $CFG;
        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        $userresponses = $cache->get('userresponses');
        $this->lastquestion = $context['lastquestion'];
        $lastresponse = catcontext::getresponsedatafromdb(
            $context['contextid'],
            [$this->lastquestion->catscaleid],
            $this->lastquestion->id,
            $context['userid']
        );
        if (! $lastresponse) {
            // TODO: This should not happen, so maybe log this as event somewhere?
            return (new model_responses())->setdata($userresponses, false);
        }
        $userresponses[$context['userid']]['component'][$context['lastquestion']->id]
            = $lastresponse[$context['userid']]['component'][$context['lastquestion']->id];
        $cache->set('userresponses', $userresponses);

        $userresponses = (new model_responses())->setdata($userresponses, false);
        return $userresponses;
    }

    /**
     * Get item param list.
     *
     * @param mixed $responses
     * @param mixed $contextid
     * @param mixed $catscaleid
     *
     * @return mixed
     *
     */
    protected function get_item_param_list($responses, $contextid, $catscaleid) {
        // We will update the person ability. Select the correct model for each item.
        $modelstrategy = new model_strategy($responses);
        $catscalecontext = catscale::get_context_id($catscaleid);
        $catscaleids = [
            $catscaleid,
            ...catscale::get_subscale_ids($catscaleid),
        ];
        $itemparamlists = [];
        $personparams = model_person_param_list::load_from_db($contextid, $catscaleids);
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
     * Get updated ability.
     *
     * @param mixed $userresponses
     * @param mixed $itemparamlist
     *
     * @return mixed
     *
     */
    protected function get_updated_ability($userresponses, $itemparamlist) {
        return catcalc::estimate_person_ability($userresponses, $itemparamlist);
    }

    /**
     * Update person param.
     *
     * @param mixed $context
     * @param mixed $catscaleid
     * @param mixed $updatedability
     *
     * @return mixed
     *
     */
    protected function update_person_param($context, $catscaleid, $updatedability) {
        catquiz::update_person_param(
            $context['userid'],
            $context['contextid'],
            $catscaleid,
            $updatedability
        );
    }

    /**
     * Add the given catscaleid to the list of excluded catscales.
     *
     * By storing this information in the cache, we can remember excluded
     * subscales for the whole quiz attempt.
     *
     * @param mixed $catscaleid
     *
     * @return mixed
     *
     */
    protected function mark_subscale_as_removed($catscaleid) {
        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        $excludedscales = $cache->get('excludedscales') ?: [];
        $excludedscales[] = $catscaleid;
        $excludedscales = array_unique($excludedscales);
        $cache->set('excludedscales', $excludedscales);
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
}
