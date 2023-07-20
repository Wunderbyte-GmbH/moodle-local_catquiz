<?php

namespace local_catquiz\teststrategy\context\loader;
use local_catquiz\teststrategy\context\contextloaderinterface;

/**
 * Moves pilot questions to a separate context key `pilot_questions` and removes
 * them from the `questions` key.
 *
 * @package local_catquiz\teststrategy\context\loader
 */
class pilotquestions_loader implements contextloaderinterface {
    // Question with less attempts will be considered pilot questions
    const ATTEMPTS_THRESHOLD = 30;

    public function provides(): array {
        return ['pilot_questions'];
    }

    public function requires(): array {
        return [
            'questions',
            'pilot_ratio',
        ];
    }

    public function load(array $context): array {
        $context['pilot_questions'] = [];
        if ($context['pilot_ratio'] === 0) {
            return $context;
        }

        foreach ($context['questions'] as $id => $question) {
            $question->is_pilot = intval($question->attempts) < self::ATTEMPTS_THRESHOLD;
            $context['questions'][$id] = $question;
        }
        return $context;
    }
}
