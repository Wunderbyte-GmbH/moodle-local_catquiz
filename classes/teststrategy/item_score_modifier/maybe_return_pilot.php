<?php

namespace local_catquiz\teststrategy\item_score_modifier;

use local_catquiz\local\result;
use local_catquiz\teststrategy\item_score_modifier;
use local_catquiz\wb_middleware;

/**
 * Randomly returns a pilot question according to the `pilot_ratio` parameter
 * 
 * @package local_catquiz\teststrategy\item_score_modifier
 */
final class maybe_return_pilot extends item_score_modifier implements wb_middleware
{
    public function run(array $context, callable $next): result {
        if ($context['pilot_ratio'] === 0) {
            return $next($context);
        }

        $pilot_questions = array_filter($context['questions'], fn($q) => $q->is_pilot);
        $nonpilot_questions = array_udiff($context['questions'], $pilot_questions, fn($a, $b) => $a->id - $b->id);
        // If there are no pilot or non-pilot questions available, then return a random question
        if (count($pilot_questions) === 0 || count($nonpilot_questions) === 0) {
            return $next($context);
        }

        $should_return_pilot = rand(0, 100) <= $context['pilot_ratio'] * 100;
        if ($should_return_pilot) {
            $context['questions'] = $pilot_questions;
        } else {
            $context['questions'] = $nonpilot_questions;
        }
        return $next($context);
    }

    public function get_required_context_keys(): array {
        return [
            'pilot_ratio',
            'questions',
        ];
    }
}
