<?php

namespace local_catquiz\teststrategy\context\loader;

use cache;
use local_catquiz\teststrategy\context\contextloaderinterface;

class lastquestion_loader implements contextloaderinterface {
    public function provides(): array {
        return ['lastquestion'];
    }

    public function requires(): array {
        return [];
    }

    public function load(array $context): array {
        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        $lastquestion = $cache->get('lastquestion') ?: null;
        $context['lastquestion'] = $lastquestion;
        return $context;
    }

}
