<?php

namespace local_catquiz\teststrategy\item_score_modifier;

use local_catquiz\local\result;
use local_catquiz\teststrategy\item_score_modifier;
use local_catquiz\wb_middleware;
use moodle_exception;

final class ispilot extends item_score_modifier implements wb_middleware
{
    public function run(array $context, callable $next): result {
        $rand = rand(0, 100);
        if ($rand <= $context['pilot_ratio'] * 100) {
            //TODO: Keep track of which question was selected
            return result::ok(reset($context['pilot_questions']));
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
