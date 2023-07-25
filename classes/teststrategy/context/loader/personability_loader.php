<?php

namespace local_catquiz\teststrategy\context\loader;
use local_catquiz\teststrategy\context\contextloaderinterface;

class personability_loader implements contextloaderinterface {
    const DEFAULT_ABILITY = 0.0;

    public function provides(): array {
        return ['person_ability'];
    }

    public function requires(): array {
        return [
            'contextid',
            'catscaleid'
        ];
    }

    public function load(array $context): array {
        global $DB, $USER;
        $person_params = $DB->get_record(
            'local_catquiz_personparams',
            [
                'userid' => $USER->id,
                'contextid' => $context['contextid'],
                'catscaleid' => $context['catscaleid'],
            ]
        );

        $context['person_ability'] = empty($person_params)
            ? self::DEFAULT_ABILITY
            : floatval($person_params->ability);

        return $context;
    }

}