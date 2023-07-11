<?php

namespace local_catquiz\teststrategy\item_score_modifier;

use local_catquiz\local\result;
use local_catquiz\local\status;
use local_catquiz\teststrategy\item_score_modifier;
use local_catquiz\wb_middleware;

final class maximumquestionscheck extends item_score_modifier implements wb_middleware
{
    public function run(array $context, callable $next): result {
        // If there are no pilot questions available, then return a random normal question
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
