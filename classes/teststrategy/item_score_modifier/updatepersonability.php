<?php

namespace local_catquiz\teststrategy\item_score_modifier;

use cache;
use local_catquiz\catcalc;
use local_catquiz\catcontext;
use local_catquiz\catquiz;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\result;
use local_catquiz\local\status;
use local_catquiz\teststrategy\item_score_modifier;
use local_catquiz\wb_middleware;
use moodle_exception;

/**
 * Update the person ability based on the result of the previous question
 *
 * @package local_catquiz\teststrategy\item_score_modifier
 */
final class updatepersonability extends item_score_modifier implements wb_middleware
{
    const UPDATE_THRESHOLD = 0.001;

    public function run(array $context, callable $next): result {
        global $USER;
        $item_param_list = model_item_param_list::load_from_db($context['contextid'], 'raschbirnbauma'); // TODO not hardcoded
        $responses = catcontext::create_response_from_db($context['contextid'])
            ->as_array(); // Get the list of responses by this user
        $components = $responses[$USER->id];
        if (count($components) > 1) {
            throw new moodle_exception('User has answers to more than one component.');
        }
        $userresponses = reset($components);

        // If the last answer was incorrect and the question was excluded due to
        // having only incorrect answers, the response object is the same as in
        // the previous run and we don't have to update the person ability.
        $cache = cache::make('local_catquiz', 'userresponses');
        $cachedresponses = $cache->get('userresponses') ?: [];
        $cache->set('userresponses', $userresponses);
        $responses_changed = $this->has_changed($userresponses, $cachedresponses);

        if ($responses_changed) {
            // Nothing changed since the last question, so we do not need to
            // update the person ability
            return $next($context);
        }

        $updated_ability = catcalc::estimate_person_ability($userresponses, $item_param_list);
        catquiz::update_person_param($USER->id, $context['contextid'], $updated_ability);
        if (abs($context['person_ability'] - $updated_ability) < self::UPDATE_THRESHOLD) {
            // If we do have more than the minimum questions, we should return
            if ($context['questionsattempted'] >= $context['minimumquestions']) {
                return result::err(status::ABORT_PERSONABILITY_NOT_CHANGED);
            }
        }

        $context['person_ability'] = $updated_ability;
        return $next($context);
    }

    public function get_required_context_keys(): array {
        return [
            'contextid'
        ];
    }

    private function has_changed(array $newresponses, array $oldresponses, $field = 'fraction'): bool {
        if (count($newresponses) !== count($oldresponses)) {
            return true;
        }

        $diff = array_udiff($newresponses, $oldresponses, function($a, $b) use ($field) {
            if ($a[$field] < $b[$field]) {
                return -1;
            } elseif ($a[$field] > $b[$field]) {
                return 1;
            } else {
                return 0;
            }
        });

        return count($diff) !== 0;
    }
}
