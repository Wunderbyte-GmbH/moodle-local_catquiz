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
            'questions'
        ];
    }

    public function load(array $context): array {
        $context['pilot_questions'] = [];
        foreach ($context['questions'] as $id => $question) {
            if (intval($question->attempts) < self::ATTEMPTS_THRESHOLD) {
                $context['pilot_questions'][$id] = $question;
                unset($context['questions'][$id]);
            }
        }
        return $context;
    }
}
