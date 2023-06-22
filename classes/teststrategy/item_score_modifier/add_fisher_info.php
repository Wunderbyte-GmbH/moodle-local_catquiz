<?php

namespace local_catquiz\teststrategy\item_score_modifier;

use local_catquiz\local\result;
use local_catquiz\teststrategy\item_score_modifier;
use moodle_exception;

final class add_fisher_info implements item_score_modifier
{
    public function update_score(array $context): result {
        foreach ($context['questions'] as $item) {
            if (!array_key_exists($item->model, $context['installed_models'])) {
                throw new moodle_exception('missingmodel', 'local_catquiz');
            }

            $model = $context['installed_models'][$item->model];
            foreach ($model::get_parameter_names() as $param_name) {
                $params[$param_name] = floatval($item->$param_name);
            }

            $item->fisher_information = $model::fisher_info(
                $context['person_ability'],
                $params
            );
        }
        return result::ok($context);
    }

    public function get_required_context_keys(): array {
        return [
            'installed_models',
            'person_ability',
            'questions',
        ];
    }
}
