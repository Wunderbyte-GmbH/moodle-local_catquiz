<?php

namespace local_catquiz\teststrategy\preselect_task;

use local_catquiz\local\result;
use local_catquiz\local\status;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\wb_middleware;

/**
 * Adds a `numberofgeneralattempts` property to each question
 *
 * This information can be used to update the score, so that eventually all
 * questions will have a similar number of attempts.
 *
 * @package local_catquiz\teststrategy\preselect_task
 */
final class numberofgeneralattempts extends preselect_task implements wb_middleware
{
    const PROPERTYNAME = 'numberofgeneralattempts';
    public function run(array $context, callable $next): result {
        global $DB;

        $sql = "SELECT questionid, COUNT(*) AS count
                FROM {question_attempts}
                GROUP BY questionid";

        $records = $DB->get_records_sql($sql);

        $max_attempts = 0;
        foreach ($context['questions'] as $id => &$question) {
            $attempts = array_key_exists($id, $records) ? intval($records[$id]->count) : null;
            if ($attempts > $max_attempts) {
                $max_attempts = $attempts;
            }
            $question->{self::PROPERTYNAME} = $attempts;
        }
        $context['generalnumberofattempts_max'] = $max_attempts;

        return $next($context);
    }

    public function get_required_context_keys(): array {
        return [
            'questions',
        ];
    }
}
