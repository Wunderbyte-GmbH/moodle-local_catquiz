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
final class maybe_return_pilot extends preselect_task implements wb_middleware {

    public function run(array $context, callable $next): result {
        if ($context['pilot_ratio'] === 0) {
            return $next($context);
        }

        $pilotquestions = array_filter($context['questions'], fn($q) => $q->is_pilot);
        $nonpilotquestions = array_udiff($context['questions'], $pilotquestions, fn($a, $b) => $a->id - $b->id);
        // If there are no pilot questions, then return a random productive question
        if (count($pilotquestions) === 0) {
            return $next($context);
        }

        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        $numpilotquestions = $cache->get('num_pilot_questions') ?: 0;
        // If there are only pilot questions, then return a random pilot question
        if (count($nonpilotquestions) === 0) {
            $cache->set('num_pilot_questions', ++$numpilotquestions);
            return $next($context);
        }

        $shouldreturnpilot = rand(0, 100) <= $context['pilot_ratio'] * 100;
        if ($shouldreturnpilot) {
            $cache->set('num_pilot_questions', ++$numpilotquestions);
            $context['questions'] = $pilotquestions;
        } else {
            $context['questions'] = $nonpilotquestions;
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
