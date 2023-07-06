<?php

namespace local_catquiz\teststrategy\context\loader;

use cache;
use local_catquiz\catscale;
use local_catquiz\teststrategy\context\contextloaderinterface;

class questions_loader implements contextloaderinterface {

    public function provides(): array {
        return ['questions'];
    }

    public function requires(): array {
        return [
            'catscaleid',
            'contextid',
            'includesubscales'
        ];
    }

    public function load(array $context): array {
        $catscale = new catscale($context['catscaleid']);
        $questions = $catscale->get_testitems(
            $context['contextid'],
            $context['includesubscales']
        );

        $cache = cache::make('local_catquiz', 'playedquestions');
        $playedquestions = $cache->get('playedquestions') ?: [];
        $filtered = array_filter($questions, fn ($q) => !in_array($q, $playedquestions));
        $context['questions'] = $filtered;

        return $context;
    }

}