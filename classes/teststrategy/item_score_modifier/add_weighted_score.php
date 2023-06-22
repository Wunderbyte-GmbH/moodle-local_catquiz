<?php

namespace local_catquiz\teststrategy\item_score_modifier;

use local_catquiz\local\result;
use local_catquiz\teststrategy\item_score_modifier;
use moodle_exception;

/**
 * Add a score to each question and sort questions descending by score
 * 
 * @package local_catquiz\teststrategy\item_score_modifier
 */
final class add_weighted_score implements item_score_modifier
{
    public function update_score(array $context): result {
        foreach ($context['questions'] as $question) {
            $question->score = (1 - ($question->penalty/$context['penalty_threshold'])) * $question->fisher_information;
        }

        uasort($context['questions'], function($q1, $q2) {
            return $q2->score <=> $q1->score;
        });

        return result::ok($context);
    }

    public function get_required_context_keys(): array {
        return [
            'penalty_threshold',
            'questions',
        ];
    }
}
