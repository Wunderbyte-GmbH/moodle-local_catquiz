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
use moodle_exception;

/**
 * Base class for test strategies.
 */
class teststrategy_fastest extends teststrategy {

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
     * @return object
     */
    public function return_next_testitem() {

        if (empty($this->scaleid)) {
            throw new moodle_exception('noscaleid', 'local_catquiz');
        }

        // Retrieve all questions for scale.
        $questions = array_values(parent::get_all_available_testitems($this->scaleid, true));
        $questions = array_filter($questions, function($q) {
            return (
                !property_exists($q, 'used')
                || $q->used !== true
            );
        });

        if (empty($questions)) {
            throw new moodle_exception('noquestionsincatscale', 'local_catquiz');
        }

        // TODO: Not hardcoded context
        $contextid = 1;
        $person_ability = $this->get_user_ability($contextid);
        foreach ($questions as $question) {
            if (!array_key_exists($question->model, $this->installed_models)) {
                throw new moodle_exception('missingmodel', 'local_catquiz');
            }
            $model = $this->installed_models[$question->model];
            $question->fisher_information = $model::fisher_info($person_ability, [floatval($question->difficulty)]);
        }
        usort($questions, function($q1, $q2) {
            return $q2->fisher_information <=> $q1->fisher_information;
        });
        // now $questions[0] is the one with the maximum fisher information
        $questions[0]->used = true;
        catscale::update_testitems($contextid, true, $questions);
        return $questions[0];
    }

    /**
     * TODO: Use caching for better performance
     * 
     * @param int $contextid
     * @return float 
     */
    private function get_user_ability(int $contextid): float {
        $cache = cache::make('local_catquiz', 'personparams');
        $cachekey = 'personparams';
        if ($person_params = $cache->get($cachekey)) {
            return $person_params->ability;
        }

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

        $cache->set($cachekey, $person_params);
        return $person_params->ability;
    }

    /**
     * Return Description.
     *
     * @return string
     */
    public function get_description(): string {

        return parent::get_description();
    }

}
