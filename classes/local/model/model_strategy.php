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
 * 
 *
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\local\model;

defined('MOODLE_INTERNAL') || die();

/**
 * Objects of this class are responsible for running the estimation process and
 * returning as result the new person abilities and item difficulties.
 * 
 * A strategy does the following:
 *   1. Create an inital list of person abilities PA
 *   2. Calls "estimate_item_difficulties(PA)" on each CAT model -> gets a list of item difficulties
 *   3. Updates the person abilities by utilizing the list of item difficulties calculated in step 2.
 *
 * Steps 2. and 3. are repeated until some stop condition is met: this could be a maximum number of
 * iterations or if the change of item difficulties between two iterations is below a certain threshold.
 * 
 * If the process is finished, a list of person abilities and item difficulties is returned.
 *
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class model_strategy {

    const MAX_ITERATIONS = 5; // TODO: get value from DB?

    /**
     * @var model_responses Contains necessary data for estimation
     */
    private model_responses $responses;


    // TODO: get from DB
    // context
    // max iterations
    // models (exclude, include)

    // separat: manueller override testitems: nur difficulty von 1 bestimmten modell anzeigen
    // testitem_model_status: STATUS params (green, yellow, ...) itemid <-> model, model used in last iteration

    /**
     * @var array<model_model>
     */
    private array $models;

    /**
     * @var model_person_ability_estimator
     */
    private model_person_ability_estimator $ability_estimator;
    
    private int $iterations = 0;

    /**
     * Model-specific instantiation can go here.
     */
    public function __construct(
        model_responses $responses,
        array $models,
        model_person_ability_estimator $ability_estimator
    ) {
        $this->responses = $responses;
        $this->models = $models;
        $this->ability_estimator = $ability_estimator;
    }

    /**
     * Starts the estimation process
     * 
     * @return array<model_item_param_list, model_person_param_list>
     */
    public function run_estimation(): array {
        // TODO: only if not in DB yet. Otherwise, get it from the DB
        $person_abilities = $this->responses->get_initial_person_abilities();

        // Re-calculate until the parameters are good enough
        while (!$this->should_stop()) {
            /**
             * @var array<model_item_param_list>
             */
            $item_difficulties = [];
            foreach ($this->models as $name => $model) {
                $item_difficulties[$name] = $model
                    ->estimate_item_params($person_abilities);
            }
            // The person ability estimator decides which items to use
            $person_abilities = $this
                ->ability_estimator
                ->get_person_abilities($item_difficulties);

            $this->iterations++;
        }

        return [$item_difficulties, $person_abilities];
    }

    private function should_stop(): bool {
        return $this->iterations >= self::MAX_ITERATIONS;
    }
}
