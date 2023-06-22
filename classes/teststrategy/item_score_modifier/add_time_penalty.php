<?php

namespace local_catquiz\teststrategy\item_score_modifier;

use local_catquiz\local\result;
use local_catquiz\local\status;
use local_catquiz\teststrategy\item_score_modifier;
use moodle_exception;
use stdClass;

final class add_time_penalty implements item_score_modifier
{
    public function update_score(array $context): result {
        // Time penalty --- snip ---
        $now = time();
        $context['questions'] = array_map(function($q) use ($now, $context) {
            $q->penalty = $this->get_penalty($q, $now, $context['penalty_time_range']);
            return $q;
        }, $context['questions']);

        $context['questions'] = array_filter(
            $context['questions'],
            function ($q) use ($context) {
                return (!property_exists($q, 'penalty')
                    || $q->penalty < $context['penalty_threshold']
                );
            }
        );

        if (empty($context['questions'])) {
            return result::err(status::ERROR_NO_REMAINING_QUESTIONS);
        }

        return result::ok($context);
    }

    public function get_required_context_keys(): array {
        return [
            'installed_models',
            'person_ability',
            'questions',
            'penalty_threshold',
            'penalty_time_range',
        ];
    }

    /**
     * Calculates the penalty for the given question according to the time it was played
     * 
     * The penalty should decline linearly with the time that passed since the last attempt.
     * After 30 days, the penalty should be 0 again.
     * 
     * For performance reasons, $now is passed as parameter
     * @param mixed $question 
     * @param int $now 
     * @return int 
     */
    private function get_penalty($question, $now, $penalty_time_range): int {
        $seconds_passed = $now - $question->lastattempttime;
        return max(0, $penalty_time_range - $seconds_passed);
    }
}
