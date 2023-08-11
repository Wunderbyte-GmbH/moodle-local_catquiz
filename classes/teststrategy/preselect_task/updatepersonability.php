<?php

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
 * @package local_catquiz\teststrategy\preselect_task
 */
final class updatepersonability extends preselect_task implements wb_middleware {

    const UPDATE_THRESHOLD = 0.001;

    public function run(array $context, callable $next): result {
        global $CFG, $USER;
        $lastquestion = $context['lastquestion'];
        // If we do not know the answer to the last question, we do not have to
        // update the person ability. Also, pilot questions should not be used
        // to update a student's ability.
        if ($lastquestion === null || !empty($lastquestion->is_pilot)) {
            return $next($context);
        }

        $cache = cache::make('local_catquiz', 'userresponses');
        $cachedresponses = $cache->get('userresponses') ?: [];

        $responses = catcontext::create_response_from_db($context['contextid'], $context['catscaleid']);
        $components = ($responses->as_array())[$USER->id];
        if (count($components) > 1) {
            throw new moodle_exception('User has answers to more than one component.');
        }
        $userresponses = reset($components);
        $cache->set('userresponses', $userresponses);

        // If the last answer was incorrect and the question was excluded due to
        // having only incorrect answers, the response object is the same as in
        // the previous run and we don't have to update the person ability.
        $responseschanged = $this->has_changed($userresponses, $cachedresponses);

        if (!$responseschanged) {
            // Nothing changed since the last question, so we do not need to
            // update the person ability
            return $next($context);
        }

        // We will update the person ability. Select the correct model for each item:
        $modelstrategy = new model_strategy($responses);
        $itemparamlists = [];
        foreach (array_keys($modelstrategy->get_installed_models()) as $model) {
            $itemparamlists[$model] = model_item_param_list::load_from_db($context['contextid'], $model);
        }
        $itemparamlist = $modelstrategy->select_item_model($itemparamlists);

        $updatedability = catcalc::estimate_person_ability($userresponses, $itemparamlist);
        if (is_nan($updatedability)) {
            // In a production environment, we can use fallback values. However,
            // during development we want to see when we get unexpected values
            if ($CFG->debug > 0) {
                throw new moodle_exception('error', 'local_catquiz');
            }
            // If we already have an ability, just continue with that one and do not update it.
            // Otherwise, use 0 as default value
            if (!is_nan($context['person_ability'])) {
                return $next($context);
            } else {
                $context['person_ability'] = 0;
                return $next($context);
            }
        }

        catquiz::update_person_param(
            $USER->id,
            $context['contextid'],
            $context['catscaleid'],
            $updatedability
        );
        if (abs($context['person_ability'] - $updatedability) < self::UPDATE_THRESHOLD) {
            // If we do have more than the minimum questions, we should return
            if ($context['questionsattempted'] >= $context['minimumquestions']) {
                return result::err(status::ABORT_PERSONABILITY_NOT_CHANGED);
            }
        }

        $context['person_ability'] = $updatedability;
        return $next($context);
    }

    public function get_required_context_keys(): array {
        return [
            'contextid',
            'catscaleid',
            'lastquestion',
        ];
    }

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
}
