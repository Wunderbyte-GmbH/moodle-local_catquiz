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
use local_catquiz\local\model\model_item_param_list;
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
    const UPDATE_THRESHOLD = 0.001;

    /**
     * Run preselect task.
     *
     * @param array $context
     * @param callable $next
     *
     * @return result
     *
     */
    public function run(array $context, callable $next): result {
        global $CFG, $USER;
        $lastquestion = $context['lastquestion'];
        // If we do not know the answer to the last question, we do not have to
        // update the person ability. Also, pilot questions should not be used
        // to update a student's ability.
        if ($lastquestion === null) {
            $context['skip_reason'] = 'lastquestionnull';
            return $next($context);

        }

        if (!empty($lastquestion->is_pilot)) {
            $context['skip_reason'] = 'pilotquestion';
            return $next($context);
        }

        $cachedresponses = $this->load_cached_responses();

        $responses = $this->load_responses($context);
        $components = ($responses->as_array())[$context['userid']];
        if (count($components) > 1) {
            throw new moodle_exception('User has answers to more than one component.');
        }
        $userresponses = reset($components);
        $this->update_cached_responses($userresponses);

        // If the last answer was incorrect and the question was excluded due to
        // having only incorrect answers, the response object is the same as in
        // the previous run and we don't have to update the person ability.
        $responseschanged = $this->has_changed($userresponses, $cachedresponses);

        if (!$responseschanged) {
            // Nothing changed since the last question, so we do not need to
            // update the person ability.
            $context['skip_reason'] = 'nothingchanged';
            return $next($context);
        }

        if (!$this->has_sufficient_responses($userresponses)) {
            $context['skip_reason'] = 'notenoughresponses';
            return $next($context);
        }

        $itemparamlist = $this->get_item_param_list($responses, $context);
        $updatedability = $this->get_updated_ability($userresponses, $itemparamlist);

        if (is_nan($updatedability)) {
            // In a production environment, we can use fallback values. However,
            // during development we want to see when we get unexpected values.
            if ($CFG->debug > 0) {
                throw new moodle_exception('error', 'local_catquiz');
            }
            // If we already have an ability, just continue with that one and do not update it.
            // Otherwise, use 0 as default value.
            $context['skip_reason'] = 'abilityisnan';
            if (!is_nan($context['person_ability'])) {
                return $next($context);
            } else {
                $context['person_ability'] = 0;
                return $next($context);
            }
        }

        $this->update_person_param($context, $updatedability);
        if (abs($context['person_ability'] - $updatedability) < self::UPDATE_THRESHOLD) {
            // If we do have more than the minimum questions, we should return.
            if ($context['questionsattempted'] >= $context['minimumquestions']) {
                return result::err(status::ABORT_PERSONABILITY_NOT_CHANGED);
            }
        }

        $context['person_ability'] = $updatedability;
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
            'contextid',
            'catscaleid',
            'lastquestion',
        ];
    }

    /**
     * Has changed.
     *
     * @param array $newresponses
     * @param array $oldresponses
     * @param string $field
     *
     * @return bool
     *
     */
    private function has_changed(array $newresponses, array $oldresponses, $field = 'fraction'): bool {
        if (count($newresponses) !== count($oldresponses)) {
            return true;
        }

        $diff = array_udiff($newresponses, $oldresponses, function($a, $b) use ($field) {
            if ($a[$field] < $b[$field]) {
                return -1;
            } else if ($a[$field] > $b[$field]) {
                return 1;
            } else {
                return 0;
            }
        });

        return count($diff) !== 0;
    }

    /**
     * Test if we can calculate an ability with the given responses.
     * At least two answers with different outcome are needed.
     * 
     * @param mixed $userresponses
     * @return bool
     */
    private function has_sufficient_responses($userresponses) {
        $first = $userresponses[array_key_first($userresponses)];
        foreach ($userresponses as $ur) {
            if ($ur['fraction'] !== $first['fraction']) {
                return true;
            }
        }
        return false;
    }

    protected function load_responses($context) {
        return catcontext::create_response_from_db($context['contextid'], $context['catscaleid']);
    }

    protected function load_cached_responses() {
        $cache = cache::make('local_catquiz', 'userresponses');
        return $cache->get('userresponses') ?: [];
    }

    protected function update_cached_responses($userresponses) {
        $cache = cache::make('local_catquiz', 'userresponses');
        $cache->set('userresponses', $userresponses);
    }

    protected function get_item_param_list($responses, $context) {
        // We will update the person ability. Select the correct model for each item.
        $modelstrategy = new model_strategy($responses);
        $itemparamlists = [];
        foreach (array_keys($modelstrategy->get_installed_models()) as $model) {
            $itemparamlists[$model] = model_item_param_list::load_from_db($context['contextid'], $model);
        }
        $itemparamlist = $modelstrategy->select_item_model($itemparamlists);
        return $itemparamlist;
    }

    protected function get_updated_ability($userresponses, $itemparamlist) {
        return catcalc::estimate_person_ability($userresponses, $itemparamlist);
    }

    protected function update_person_param($context, $updatedability) {
        catquiz::update_person_param(
            $context['userid'],
            $context['contextid'],
            $context['catscaleid'],
            $updatedability
        );
    }
}
