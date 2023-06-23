<?php

namespace local_catquiz\teststrategy\item_score_modifier;

use local_catquiz\local\result;
use local_catquiz\teststrategy\item_score_modifier;
use local_catquiz\wb_middleware;

/**
 * Add a score to each question and sort questions descending by score
 * 
 * @package local_catquiz\teststrategy\item_score_modifier
 */
final class add_weighted_score extends item_score_modifier implements wb_middleware
{
    public function run(array $context, callable $next): result {
        foreach ($context['questions'] as $question) {
            $question->score = (1 - ($question->penalty/$context['penalty_threshold'])) * $question->fisher_information;
        }

        uasort($context['questions'], function($q1, $q2) {
            return $q2->score <=> $q1->score;
        });

        return $next($context);
    }

    public function get_required_context_keys(): array {
        return [
            'penalty_threshold',
            'questions',
        ];
    }
}
