<?php

namespace local_catquiz\teststrategy\preselect_task;

use local_catquiz\local\result;
use local_catquiz\local\status;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\wb_middleware;

final class noremainingquestions extends preselect_task implements wb_middleware {

    public function run(array $context, callable $next): result {
        if (
            count($context['questions']) === 0
            && empty($context['pilot_questions'])
            ) {
                return result::err(status::ERROR_NO_REMAINING_QUESTIONS);
        }

        return $next($context);
    }

    public function get_required_context_keys(): array {
        return [
            'questions',
        ];
    }
}
