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

use core_plugin_manager;
use dml_exception;
use local_catquiz\catcontext;
use MoodleQuickForm;

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

    private int $max_iterations;
    
    private int $iterations = 0;

    private int $contextid;

    /**
     * Model-specific instantiation can go here.
     */
    public function __construct(model_responses $responses, int $contextid, int $max_iterations = self::MAX_ITERATIONS) {
        $this->contextid = $contextid;
        $this->responses = $responses;
        $this->models = $this->create_installed_models();
        $this->ability_estimator = new model_person_ability_estimator_demo($this->responses);
        $this->max_iterations = $max_iterations;
    }
    
    public static function handle_mform(MoodleQuickForm &$mform) {
        $mform->addElement('header', 'strategy', get_string('strategy', 'local_catquiz'));
        $mform->addElement('text', 'max_iterations', get_string('max_iterations', 'local_catquiz'), PARAM_INT);
    }

    /**
     * Updates the $errors via reference
     * 
     * @param array $data
     * @param array $files
     * @return void
     */
    public static function validation($data, $files, &$errors) {
        $max_iterations = intval($data['max_iterations']);
        if ($max_iterations === 0) {
            $errors['max_iterations'] = get_string('noint', 'local_catquiz');
        } else if ($max_iterations <= 0) {
            $errors['max_iterations'] = get_string('notpositive', 'local_catquiz');
        }
    }

    /**
     * Starts the estimation process
     * 
     * @return array<model_item_param_list, model_person_param_list>
     */
    public function run_estimation(): array {
        $person_abilities = $this->get_initial_person_abilities();

        /**
         * @var array<model_item_param_list>
         */
        $item_difficulties = [];
        // Re-calculate until the stop condition is triggered
        while (!$this->should_stop()) {
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

        foreach ($item_difficulties as $item_param_list) {
            $item_param_list->save_to_db($this->contextid);
        }
        $person_abilities->save_to_db($this->contextid);

        return [$item_difficulties, $person_abilities];
    }

    /**
     * @return array<model_item_param_list, model_person_param_list>
     */
    public function get_params_from_db(): array {
        $models = $this->get_installed_models();
        foreach (array_keys($models) as $model_name) {
            $estimated_item_difficulties[$model_name] = model_item_param_list::load_from_db(
                $this->contextid,
                $model_name
            );
        }
        $person_abilities = model_person_param_list::load_from_db($this->contextid);
        return [$estimated_item_difficulties, $person_abilities];
    }

    private function should_stop(): bool {
        return $this->iterations >= $this->max_iterations;
    }

    /**
     * If there are already person params for the given context in the DB, then use them.
     * Otherwise, create a new list
     * 
     * @return model_person_param_list 
     * @throws dml_exception 
     */
    private function get_initial_person_abilities(): model_person_param_list {
        $saved_person_abilities = model_person_param_list::load_from_db($this->contextid);
        if (!empty($saved_person_abilities)) {
            return $saved_person_abilities;
        }
        return $this->responses->get_initial_person_abilities();
    }

    /**
     * Returns classes of installed models, indexed by the model name
     *
     * @return array<string>
     */
    private static function get_installed_models(): array {
        $pm = core_plugin_manager::instance();
        $models = [];
        foreach($pm->get_plugins_of_type('catmodel') as $name => $info) {
                $classname = sprintf('catmodel_%s\%s', $name, $name);
                if (!class_exists($classname)) {
                    continue;
                }
                $models[$name] = $classname;
        }
        return $models;
    }

    /**
     * Returns an array of model instances indexed by their name.
     *
     * @return array<model_model>
     */
    private function create_installed_models(): array {
        /**
         * @var array<model_model>
         */
        $instances = [];

        foreach (self::get_installed_models() as $name => $classname) {
            $modelclass = new $classname($this->responses, $name);
            $instances[$name] = $modelclass;
        }
        return $instances;
    }
}
