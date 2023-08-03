<?php

namespace local_catquiz\teststrategy\preselect_task;

use cache;
use local_catquiz\local\result;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\wb_middleware;

/**
 * Randomly returns a pilot question according to the `pilot_ratio` parameter
 * 
 * @package local_catquiz\teststrategy\preselect_task
 */
final class maybe_return_pilot extends preselect_task implements wb_middleware
{
    public function run(array $context, callable $next): result {
        if ($context['pilot_ratio'] === 0) {
            return $next($context);
        }

        $pilot_questions = array_filter($context['questions'], fn($q) => $q->is_pilot);
        $nonpilot_questions = array_udiff($context['questions'], $pilot_questions, fn($a, $b) => $a->id - $b->id);
        // If there are no pilot questions, then return a random productive question
        if (count($pilot_questions) === 0) {
            return $next($context);
        }

        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        $num_pilot_questions = $cache->get('num_pilot_questions') ?: 0;
        // If there are only pilot questions, then return a random pilot question
        if (count($nonpilot_questions) === 0) {
            $cache->set('num_pilot_questions', ++$num_pilot_questions);
            return $next($context);
        }

        $should_return_pilot = rand(0, 100) <= $context['pilot_ratio'] * 100;
        if ($should_return_pilot) {
            $cache->set('num_pilot_questions', ++$num_pilot_questions);
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
