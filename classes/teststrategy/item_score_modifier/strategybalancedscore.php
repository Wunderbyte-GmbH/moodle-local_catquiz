<?php

namespace local_catquiz\teststrategy\item_score_modifier;

use local_catquiz\local\result;
use local_catquiz\teststrategy\item_score_modifier;
use local_catquiz\teststrategy\item_score_modifier\fisherinformation;
use local_catquiz\teststrategy\item_score_modifier\lasttimeplayedpenalty;
use local_catquiz\wb_middleware;

/**
 * Add a score to each question and sort questions descending by score
 * 
 * @package local_catquiz\teststrategy\item_score_modifier
 */
final class strategybalancedscore extends item_score_modifier implements wb_middleware
{
    public function run(array $context, callable $next): result {
        foreach ($context['questions'] as $question) {
            $lasttimeplayedpenalty_weighted = (1 - (
               $question->{lasttimeplayedpenalty::PROPERTYNAME}/$context['penalty_threshold']));
            $numberofgeneralattemptspenalty_weighted = (1 - (
                $question->{numberofgeneralattempts::PROPERTYNAME}/$context['generalnumberofattempts_max']));
            $question->score = $numberofgeneralattemptspenalty_weighted * $lasttimeplayedpenalty_weighted;
        }

        uasort($context['questions'], function($q1, $q2) {
            return $q2->score <=> $q1->score;
        });

        return result::ok(reset($context['questions']));
    }

    public function get_required_context_keys(): array {
        return [
            'penalty_threshold',
            'questions',
            'generalnumberofattempts_max'
        ];
    }
}
