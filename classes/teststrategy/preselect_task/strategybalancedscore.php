<?php

namespace local_catquiz\teststrategy\preselect_task;

use local_catquiz\local\result;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\teststrategy\preselect_task\fisherinformation;
use local_catquiz\teststrategy\preselect_task\lasttimeplayedpenalty;
use local_catquiz\wb_middleware;

/**
 * Add a score to each question and sort questions descending by score
 *
 * @package local_catquiz\teststrategy\preselect_task
 */
final class strategybalancedscore extends preselect_task implements wb_middleware {

    public function run(array $context, callable $next): result {
        foreach ($context['questions'] as $question) {
            $lasttimeplayedpenaltyweighted = (1 - (
               $question->{lasttimeplayedpenalty::PROPERTYNAME} / $context['penalty_threshold']));
            $numberofgeneralattemptspenaltyweighted = (1 - (
                $question->{numberofgeneralattempts::PROPERTYNAME} / $context['generalnumberofattempts_max']));
            $question->score = $numberofgeneralattemptspenaltyweighted * $lasttimeplayedpenaltyweighted;
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
