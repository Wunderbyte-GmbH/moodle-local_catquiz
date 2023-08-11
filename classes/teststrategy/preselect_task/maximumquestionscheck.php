<?php

namespace local_catquiz\teststrategy\preselect_task;

use local_catquiz\local\result;
use local_catquiz\local\status;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\wb_middleware;

final class maximumquestionscheck extends preselect_task implements wb_middleware {

    public function run(array $context, callable $next): result {
        if ($context['questionsattempted'] >= $context['maximumquestions']) {
            return result::err(status::ERROR_REACHED_MAXIMUM_QUESTIONS);
        }

        return $next($context);
    }

    public function get_required_context_keys(): array {
        return [
            'questionsattempted',
            'maximumquestions',
        ];
    }
}
