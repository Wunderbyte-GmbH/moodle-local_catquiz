<?php

namespace local_catquiz\teststrategy\item_score_modifier;

use local_catquiz\local\result;
use local_catquiz\local\status;
use local_catquiz\teststrategy\item_score_modifier;
use local_catquiz\wb_middleware;

final class lasttimeplayedpenalty extends item_score_modifier implements wb_middleware
{
    const PROPERTYNAME = 'lasttimeplayedpenalty';

    public function run(array $context, callable $next): result {
        $now = time();
        $context['questions'] = array_map(function($q) use ($now, $context) {
            $q->{self::PROPERTYNAME} = $this->get_penalty($q, $now, $context['penalty_time_range']);
            return $q;
        }, $context['questions']);

        $context['questions'] = array_filter(
            $context['questions'],
            function ($q) use ($context) {
                return $q->{self::PROPERTYNAME} < $context['penalty_threshold']
                ;
            }
        );

        if (empty($context['questions'])) {
            return result::err(status::ERROR_NO_REMAINING_QUESTIONS);
        }

        return $next($context);
    }

    public function get_required_context_keys(): array {
        return [
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
        $seconds_passed = $now - $question->userlastattempttime;
        return max(0, $penalty_time_range - $seconds_passed);
    }
}
