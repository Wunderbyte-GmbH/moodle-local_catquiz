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
        $context['questions'] = $catscale->get_testitems(
            $context['contextid'],
            $context['includesubscales']
        );

        return $context;
    }

}