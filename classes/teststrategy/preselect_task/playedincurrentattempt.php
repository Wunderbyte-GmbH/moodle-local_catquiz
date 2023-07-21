<?php

namespace local_catquiz\teststrategy\preselect_task;

use cache;
use local_catquiz\local\result;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\wb_middleware;

final class playedincurrentattempt extends preselect_task implements wb_middleware
{
    const PROPERTYNAME = 'playedinattemptpenalty';
    const PENALTY = 100;

    public function run(array $context, callable $next): result {
        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        $playedquestions = $cache->get('playedquestions') ?: [];
        foreach ($context['questions'] as $q) {
                    if (array_key_exists($q->id, $playedquestions)) {
                        $context['questions'][$q->id]->{self::PROPERTYNAME} = self::PENALTY;
                    } else {
                        $context['questions'][$q->id]->{self::PROPERTYNAME} = 0;
                    }
        }

        return $next($context);
    }

    public function get_required_context_keys(): array {
        return [
            'questions',
        ];
    }
}
