<?php

namespace local_catquiz\teststrategy\preselect_task;

use local_catquiz\local\result;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\wb_middleware;

/**
 * Shuffles the array of questions so that a random one will be selected
 *
 * @package local_catquiz\teststrategy\preselect_task
 */
final class strategyrandom extends preselect_task implements wb_middleware
{
    public function run(array $context, callable $next): result {
        shuffle($context['questions']);
        return result::ok(reset($context['questions']));
    }

    public function get_required_context_keys(): array {
        return [
            'questions',
        ];
    }
}
