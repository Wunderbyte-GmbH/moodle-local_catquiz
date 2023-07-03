<?php

namespace local_catquiz\teststrategy\item_score_modifier;

use local_catquiz\local\result;
use local_catquiz\local\status;
use local_catquiz\teststrategy\item_score_modifier;
use local_catquiz\wb_middleware;

final class ispilot extends item_score_modifier implements wb_middleware
{
    public function run(array $context, callable $next): result {
        // If there are no pilot questions available, then this class can not
        // perform its task.
        if (count($context['pilot_questions']) === 0) {
            return result::err(status::ERROR_FETCH_NEXT_QUESTION);
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
