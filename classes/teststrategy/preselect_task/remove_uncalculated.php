<?php

namespace local_catquiz\teststrategy\preselect_task;

use local_catquiz\local\result;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\wb_middleware;

/**
 * Removes questions for which no item parameters were calculated yet
 *
 * @package local_catquiz\teststrategy\preselect_task
 */
final class remove_uncalculated extends preselect_task implements wb_middleware
{
    public function run(array $context, callable $next): result {
        $context['questions'] =  array_filter(
            $context['questions'],
            fn($item) => !is_null($item->model)
        );
        return $next($context);
    }

    public function get_required_context_keys(): array {
        return [
            'questions',
        ];
    }
}
