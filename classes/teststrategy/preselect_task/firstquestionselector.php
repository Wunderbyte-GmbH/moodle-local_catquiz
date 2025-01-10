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

use local_catquiz\catcontext;
use local_catquiz\catquiz;
use local_catquiz\catscale;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_person_param_list;
use local_catquiz\local\model\model_responses;
use local_catquiz\local\model\model_strategy;
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
     * @var int
     */
    const MINIMUM_PARAMS_FOR_ESTIMATE = 50;

    /**
     * @var int
     */
    const LEVEL_VERYEASY = -2;

    /**
     * @var int
     */
    const LEVEL_EASY = -1;

    /**
     * @var int
     */
    const LEVEL_NORMAL = 0;

    /**
     * @var int
     */
    const LEVEL_DIFFICULT = 1;

    /**
     * @var int
     */
    const LEVEL_VERYDIFFICULT = 2;


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
        $this->context = $context;
        // Don't do anything if this is not the first question of the current attempt.
        if (!$this->progress->is_first_question()) {
            return $next($context);
        }

        // In the classic test, we do not change how the first question is selected.
        if ($context['teststrategy'] == LOCAL_CATQUIZ_STRATEGY_CLASSIC) {
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

        if ($context['firstquestion_use_existing_data']) {
            // We already have a person param for this user, so use it.
            if ($this->has_ability()) {
                return $next($context);
            }
        }

        // User does not have an ability yet, so we try to take the average ability.
        $meanability = $this->get_mean_ability();

        if ($meanability === null) {
            $startability = $this->set_start_ability($context['selectfirstquestion'], 0.0, 1.0);
            $context['person_ability'][$this->context['catscaleid']] = $startability;
            $context['progress']->set_ability($startability, $context['catscaleid']);
            $context['se'][$this->context['catscaleid']] = 1.0;
            return $next($context);
        }

        $items = $this->get_items();
        $se = catscale::get_standarderror($meanability, $items, 1.0);
        $context['person_ability'][$this->context['catscaleid']] = $this->set_start_ability(
            $context['selectfirstquestion'],
            $meanability,
            $se
        );
        $context['se'][$this->context['catscaleid']] = $se;
        return $next($context);
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

    /**
     * Helper to get the user ability in the main scale
     *
     * @return bool
     */
    protected function has_ability() {
        return boolval(catquiz::get_person_abilities(
            $this->context['contextid'],
            [$this->context['catscaleid']],
            [$this->context['userid']]
        ));
    }

    /**
     * Helper function to calculate the mean ability.
     *
     * If there are not enough abilities for a good estimate, returns null
     */
    protected function get_mean_ability() {
        $abilities = catquiz::get_person_abilities(
            $this->context['contextid'],
            [$this->context['catscaleid']]
        );

        if (!$abilities || count($abilities) < self::MINIMUM_PARAMS_FOR_ESTIMATE) {
            return null;
        }

        $meanability = array_sum(array_map(fn ($pp) => floatval($pp->ability), $abilities)) / count($abilities);
        return $meanability;
    }

    /**
     * Helper function to calculate the start ability
     *
     * @param string $option
     * @param float $mean
     * @param float $se
     *
     * @return float
     */
    private function set_start_ability(string $option, float $mean, float $se) {
        $knownlevels = [
            self::LEVEL_VERYEASY,
            self::LEVEL_EASY,
            self::LEVEL_NORMAL,
            self::LEVEL_DIFFICULT,
            self::LEVEL_VERYDIFFICULT,
        ];
        if (!in_array($option, $knownlevels)) {
            throw new \Exception(sprintf("Unknown option to select first question: %s", $option));
        }
        return $mean + intval($option) * $se;
    }

    /**
     * Helper function to return the item list for the main scale
     *
     * @return model_item_param_list
     */
    private function get_items() {
        // Create item list.
        $catscaleids = [$this->context['catscaleid'], ...catscale::get_subscale_ids($this->context['catscaleid'])];
        $catscalecontext = catscale::get_context_id($this->context['catscaleid']);
        $items = model_item_param_list::get(
            $catscalecontext,
            null,
            $catscaleids
        );
        return $items;
    }
}
