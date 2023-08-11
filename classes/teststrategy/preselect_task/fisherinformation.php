<?php

namespace local_catquiz\teststrategy\preselect_task;

use local_catquiz\local\result;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\wb_middleware;
use moodle_exception;

final class fisherinformation extends preselect_task implements wb_middleware {

    const PROPERTYNAME = 'fisherinformation';

    public function run(array $context, callable $next): result {
        foreach ($context['questions'] as $item) {
            if (!array_key_exists($item->model, $context['installed_models'])) {
                throw new moodle_exception('missingmodel', 'local_catquiz');
            }

            $model = $context['installed_models'][$item->model];
            foreach ($model::get_parameter_names() as $paramname) {
                $params[$paramname] = floatval($item->$paramname);
            }

            $item->{self::PROPERTYNAME} = $model::fisher_info(
                $context['person_ability'],
                $params
            );
        }
        return $next($context);
    }

    public function get_required_context_keys(): array {
        return [
            'installed_models',
            'person_ability',
            'questions',
        ];
    }
}
