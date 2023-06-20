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

namespace local_catquiz\teststrategy;

use cache;
use local_catquiz\catscale;
use local_catquiz\local\model\model_strategy;
use local_catquiz\local\result;
use local_catquiz\local\status;
use moodle_exception;

/**
 * Base class for test strategies.
 */
class teststrategy_fastest extends teststrategy {

    /**
     * After this time, the penalty for a question goes back to 0
     * Currently, it is set to 30 days
     */
    const PENALTY_TIME_RANGE = 60*60*24*30;
    /**
     * Exclude questions that were attempted within the last 60 seconds
     */
    const PENALTY_THRESHOLD = 60*60*24*30-60;
    /**
     *
     * @var int $id // strategy id defined in lib.
     */
    public int $id = STRATEGY_FASTEST;

    // Classnames of installed CAT models indexed by model name
    private array $installed_models = [];

    public function __construct() {
        $this->installed_models = model_strategy::get_installed_models();
    }

    /**
     * Strategy specific way of returning the next testitem.
     * 
     * Returns an array with a 
     *
     * @return result
     */
    public function return_next_testitem() {

        if (empty($this->scaleid)) {
            throw new moodle_exception('noscaleid', 'local_catquiz');
        }

        // Retrieve all questions for scale.
        $questions = parent::get_all_available_testitems($this->scaleid);
        $now = time();
        $questions = array_map(function($q) use ($now) {
            $q->penalty = $this->get_penalty($q, $now);
            return $q;
        }, $questions);
        $questions = array_filter($questions, function ($q) {
            return (
                !property_exists($q, 'penalty')
                || $q->penalty < self::PENALTY_THRESHOLD
            );
        });

        if (empty($questions)) {
            return result::err(status::ERROR_NO_REMAINING_QUESTIONS);
        }

        // TODO: Not hardcoded context
        $contextid = 1;
        $person_ability = $this->get_user_ability($contextid);
        foreach ($questions as $question) {
            if (!array_key_exists($question->model, $this->installed_models)) {
                throw new moodle_exception('missingmodel', 'local_catquiz');
            }
            $model = $this->installed_models[$question->model];
            $params = [];
            foreach ($model::get_parameter_names() as $param_name) {
                $params[$param_name] = floatval($question->$param_name);
            }
            $question->fisher_information = $model::fisher_info($person_ability, $params);
            $question->score = (1 - ($question->penalty/self::PENALTY_THRESHOLD)) * $question->fisher_information;
        }
        uasort($questions, function($q1, $q2) {
            return $q2->score <=> $q1->score;
        });
        // Select the question with the maximum score
        $selected_question = $questions[array_keys($questions)[0]];
        $selected_question->lastattempttime = $now;
        catscale::update_testitem($contextid, $selected_question);
        return result::ok($selected_question);
    }

    /**
     * @param int $contextid
     * @return float 
     */
    private function get_user_ability(int $contextid): float {
        global $DB, $USER;
        $person_params = $DB->get_record(
            'local_catquiz_personparams',
            [
                'userid' => $USER->id,
                'contextid' => $contextid,
            ]
        );

        // Use default ability of 0 if we have no ability for that user
        if (empty($person_params)) {
            return 0.0;
        }

        return floatval($person_params->ability);
    }

    /**
     * Return Description.
     *
     * @return string
     */
    public function get_description(): string {

        return parent::get_description();
    }

    /**
     * Calculates the penalty for the given question according to the time it was played
     * 
     * The penalty should decline linearly with the time that passed since the last attempt.
     * After 30 days, the penalty should be 0 again.
     * 
     * For performance reasons, $now is passed as parameter
     * @param mixed $question 
     * @param int $now 
     * @return int 
     */
    private function get_penalty($question, $now): int {
        $seconds_passed = $now - $question->lastattempttime;
        return max(0, self::PENALTY_TIME_RANGE - $seconds_passed);
    }
}
