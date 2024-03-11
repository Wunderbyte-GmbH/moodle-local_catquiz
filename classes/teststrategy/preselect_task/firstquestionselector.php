<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Class firstquestionselector.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\preselect_task;

use cache;
use local_catquiz\catquiz;
use local_catquiz\local\result;
use local_catquiz\local\status;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\teststrategy\progress;
use local_catquiz\wb_middleware;
use moodle_exception;

/**
 * Test strategy firstquestionselector.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class firstquestionselector extends preselect_task implements wb_middleware {

    /**
     * STARTWITHEASIESTQUESTION
     *
     * @var string
     */
    const STARTWITHEASIESTQUESTION = 'startwitheasiestquestion';

    /**
     * STARTWITHFIRSTOFSECONDQUINTIL
     *
     * @var string
     */
    const  STARTWITHFIRSTOFSECONDQUINTIL = 'startwithfirstofsecondquintil';

    /**
     * STARTWITHFIRSTOFSECONDQUARTIL
     *
     * @var string
     */
    const  STARTWITHFIRSTOFSECONDQUARTIL = 'startwithfirstofsecondquartil';

    /**
     * STARTWITHMOSTDIFFICULTSECONDQUARTIL
     *
     * @var string
     */
    const  STARTWITHMOSTDIFFICULTSECONDQUARTIL = 'startwithmostdifficultsecondquartil';

    /**
     * STARTWITHAVERAGEABILITYOFTEST
     *
     * @var string
     */
    const  STARTWITHAVERAGEABILITYOFTEST = 'startwithaverageabilityoftest';

    /**
     * STARTWITHCURRENTABILITY
     *
     * @var string
     */
    const  STARTWITHCURRENTABILITY = 'startwithcurrentability';

    /**
     * @var progress
     */
    private progress $progress;

    /**
     * Run preselect task.
     *
     * @param array $context
     * @param callable $next
     *
     * @return result
     *
     */
    public function run(array &$context, callable $next): result {
        $this->progress = $context['progress'];
        // Don't do anything if this is not the first question of the current attempt.
        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        if (!$this->progress->is_first_question()) {
            return $next($context);
        }

        if ($context['questions_ordered_by'] !== 'difficulty') {
            return result::err(status::ERROR_FETCH_NEXT_QUESTION);
        }

        // If we select the first question based on its difficulty, then it can
        // never be a pilot question.
        $questionswithdifficulty = array_filter($context['questions'], fn($q) => !$q->is_pilot);
        if (count($questionswithdifficulty) === 0) {
            return result::err(status::ERROR_EMPTY_FIRST_QUESTION_LIST);
        } else if (count($questionswithdifficulty) === 1) {
            return result::ok($questionswithdifficulty[array_keys($questionswithdifficulty)[0]]);
        }
        $context['questions'] = $questionswithdifficulty;

        switch ($context['selectfirstquestion']) {
            case self::STARTWITHEASIESTQUESTION:
                // We expect the questions to be already sorted in ascending
                // order of difficulty, so the first one is the easiest one
                // Check it is sorted.
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
                $personparams = $this->get_personparams_for_adaptivequiz_test($context);
                $averageability = $this->get_median_ability_of_test($personparams);
                foreach (array_keys($context['person_ability']) as $catscaleid) {
                    $context['person_ability'][$catscaleid] = $averageability;
                }
                return $next($context);
            case self::STARTWITHCURRENTABILITY:
                return $next($context);

            default:
                throw new \Exception(sprintf("Unknown option to select first question: %s"), $context['selectfirstquestion']);
        }
    }

    /**
     * Get required context keys.
     *
     * @return array
     *
     */
    public function get_required_context_keys(): array {
        return [
            'selectfirstquestion',
            'questions_ordered_by',
            'testid',
            'progress',
        ];
    }

    /**
     * Returns the median ability of the given person parameters.
     *
     * @param array $personparams
     *
     * @return mixed
     *
     */
    public function get_median_ability_of_test(array $personparams) {
        if (!$personparams) {
            return 0.0;
        }
        $abilities = array_map(fn ($param) => floatval($param->ability), $personparams);
        sort($abilities);
        $index = count($abilities) / 2;
        if ((int) $index == $index) {
            return ($abilities[array_keys($abilities)[$index - 1]] + $abilities[array_keys($abilities)[$index]]) / 2;
        }
        return $abilities[array_keys($abilities)[floor($index)]];
    }

    /**
     * Get easiest question.
     *
     * @param mixed $questions
     *
     * @return mixed
     *
     */
    private function get_easiest_question($questions) {
        return $questions[array_key_first($questions)];
    }

    /**
     * Get first question of second quintile.
     *
     * @param mixed $questions
     *
     * @return mixed
     *
     */
    private function get_first_question_of_second_quintile($questions) {
        $index = $this->get_index_for_quantile(0.2, count($questions));
        return $questions[array_keys($questions)[$index]];
    }

    /**
     * Get first question of second quartile.
     *
     * @param mixed $questions
     *
     * @return mixed
     *
     */
    private function get_first_question_of_second_quartile($questions) {
        $index = $this->get_index_for_quantile(0.25, count($questions));
        return $questions[array_keys($questions)[$index]];
    }

    /**
     * Get last question of second quartile.
     *
     * @param mixed $questions
     *
     * @return mixed
     *
     */
    private function get_last_question_of_second_quartile($questions) {
        $index3rdquartile = $this->get_index_for_quantile(0.5, count($questions));
        // We want to return the question that is right before the first of the third quartile.
        $index = $index3rdquartile - 1;
        return $questions[array_keys($questions)[$index]];
    }

    /**
     * Get index for quantile.
     *
     * @param float $quantile
     * @param int $len
     *
     * @return int
     *
     */
    private function get_index_for_quantile(float $quantile, int $len) {
        $index = $quantile * $len;
        $index -= 1; // Because we use zero-based indexing.
        if ($index == (int) $index) {
            // Theoretically, the quartile value is the average of the question difficulties
            // at index i and i+1: ($questions[$index] + $questions[$index+1])/2
            // But we need to return a real question at an existing index position.
            // In this case, we err on the easy side and return the question at the lower index.
            return $index;
        }
        return ceil($index);
    }

    /**
     * Gets the person params from the database.
     *
     * Is overwritten in a _testing class, so that we do not need a database for testing.
     * @param array $context
     * @return array
     * @throws moodle_exception
     */
    protected function get_personparams_for_adaptivequiz_test(array $context) {
        return catquiz::get_personparams_for_adaptivequiz_test($context['testid']);
    }
}
