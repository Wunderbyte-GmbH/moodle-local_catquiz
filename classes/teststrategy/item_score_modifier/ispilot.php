<?php

namespace local_catquiz\teststrategy\item_score_modifier;

use local_catquiz\local\result;
use local_catquiz\local\status;
use local_catquiz\teststrategy\item_score_modifier;
use local_catquiz\wb_middleware;

final class ispilot extends item_score_modifier implements wb_middleware
{
    public function run(array $context, callable $next): result {
        // If there are no pilot questions available, then return a random normal question
        if (count($context['pilot_questions']) === 0) {
            return $next($context);
        }

        // If there are no more normal questions, return a random pilot question
        if (count($context['questions']) === 0) {
            $context['questions'] = $context['pilot_questions'];
            return $next($context);
        }

        $rand = rand(0, 100);
        if ($rand <= $context['pilot_ratio'] * 100) {
            $selectedquestion = reset($context['pilot_questions']);
            $selectedquestion->is_pilot = true;
            return result::ok($selectedquestion);
        }
        return $next($context);
    }

    public function get_required_context_keys(): array {
        return [
            'pilot_questions',
            'pilot_ratio',
        ];
    }
}
