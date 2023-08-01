<?php

namespace local_catquiz\teststrategy\preselect_task;

use cache;
use local_catquiz\catquiz;
use local_catquiz\local\result;
use local_catquiz\local\status;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\wb_middleware;

final class firstquestionselector extends preselect_task implements wb_middleware
{
    const STARTWITHEASIESTQUESTION = 'startwitheasiestquestion';
    const  STARTWITHFIRSTOFSECONDQUINTIL = 'startwithfirstofsecondquintil';
    const  STARTWITHFIRSTOFSECONDQUARTIL = 'startwithfirstofsecondquartil';
    const  STARTWITHMOSTDIFFICULTSECONDQUARTIL = 'startwithmostdifficultsecondquartil';
    const  STARTWITHAVERAGEABILITYOFTEST = 'startwithaverageabilityoftest';
    const  STARTWITHCURRENTABILITY = 'startwithcurrentability';

    public function run(array $context, callable $next): result
    {
        // Don't do anything if this is not the first question of the current
        // attempt
        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        if (!$cache->get('isfirstquestionofattempt')) {
            return $next($context);
        }

        if ($context['questions_ordered_by'] !== 'difficulty') {
            return result::err(status::ERROR_FETCH_NEXT_QUESTION);
        }

        // If we select the first question based on its difficulty, then it can
        // never be a pilot question.
        $questions_with_difficulty = array_filter($context['questions'], fn($q) => !$q->is_pilot);
        if (count($questions_with_difficulty) === 0) {
            return result::err(status::ERROR_FETCH_NEXT_QUESTION);
        } elseif (count($questions_with_difficulty) === 1) {
            return result::ok($questions_with_difficulty[array_keys($questions_with_difficulty)[0]]);
        }
        $context['questions'] = $questions_with_difficulty;

        switch ($context['selectfirstquestion']) {
            case self::STARTWITHEASIESTQUESTION:
                // We expect the questions to be already sorted in ascending
                // order of difficulty, so the first one is the easiest one
                // Check it is sorted
                $question = $this->get_easiest_question($context['questions']);
                return result::ok($question);

            case self::STARTWITHFIRSTOFSECONDQUINTIL:
                $question = $this->get_first_question_of_second_quintile($context['questions']);
                return result::ok($question);
            case self::STARTWITHFIRSTOFSECONDQUARTIL:
                $question = $this->get_first_question_of_second_quartile($context['questions']);
                return result::ok($question);
            case self::STARTWITHMOSTDIFFICULTSECONDQUARTIL:
                $question = $this->get_last_question_of_second_quartile($context['questions']);
                return result::ok($question);
            case self::STARTWITHAVERAGEABILITYOFTEST:
                $ability = $this->get_average_ability_of_test($context['testid']);
                $context['person_ability'] = $ability;
                return $next($context);
            case self::STARTWITHCURRENTABILITY:
                return $next($context);

            default:
                throw new \Exception(sprintf("Unknown option to select first question: %s"), $context['selectfirstquestion']);
        }
    }

    public function get_required_context_keys(): array
    {
        return [
            'selectfirstquestion',
            'questions_ordered_by',
            'testid',
        ];
    }

    private function get_easiest_question($questions) {
        return $questions[array_key_first($questions)];
    }

    private function get_first_question_of_second_quintile($questions) {
        $index = $this->get_index_for_quantile(0.2, count($questions));
        return $questions[array_keys($questions)[$index]];
    }
    private function get_first_question_of_second_quartile($questions) {
        $index = $this->get_index_for_quantile(0.25, count($questions));
        return $questions[array_keys($questions)[$index]];
    }
    private function get_last_question_of_second_quartile($questions) {
        $index_3rd_quartile = $this->get_index_for_quantile(0.5, count($questions));
        // We want to return the question that is right before the first of the third quartile
        $index = $index_3rd_quartile - 1;
        return $questions[array_keys($questions)[$index]];
    }

    private function get_index_for_quantile(float $quantile, int $len) {
        $index = $quantile * $len;
        $index -= 1; // Because we use zero-based indexing
        if ($index == (int) $index) {
            // Theoretically, the quartile value is the average of the question difficulties
            // at index i and i+1: ($questions[$index] + $questions[$index+1])/2
            // But we need to return a real question at an existing index position.
            // In this case, we err on the easy side and return the question at the lower index
            return $index;
        }
        return ceil($index);
    }
    private function get_average_ability_of_test(int $testid) {
        $personparams = catquiz::get_personparams_for_adaptivequiz_test($testid);
        $abilities = array_map(fn ($param) => floatval($param->ability), $personparams);
        sort($abilities);
        $index = 0.5 * count($abilities);
        $index -= 1; // Because we use zero-based indexing
        if ((int) $index == $index) {
            return ($abilities[array_keys($abilities)[$index]] + $abilities[array_keys($abilities)[$index + 1]])/2;
        }
        return $abilities[array_keys($abilities)[$index]];
    }
}
