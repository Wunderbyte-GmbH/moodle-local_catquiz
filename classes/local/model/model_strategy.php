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
use local_catquiz\local\model\model_item_param_list;
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
    const DEFAULT_MODEL = 'web_raschbirnbauma';

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

    private ?string $model_override;

    private model_person_param_list $initial_person_abilities;

    /**
     * Model-specific instantiation can go here.
     */
    public function __construct(
        model_responses $responses,
        array $options = [],
        ?model_person_param_list $saved_person_abilities = NULL
    ) {
        $this->responses = $responses;
        $this->models = $this->create_installed_models();
        $this->ability_estimator = new model_person_ability_estimator_demo($this->responses);
        $this->set_options($options);

        if ($saved_person_abilities === NULL || count($saved_person_abilities) === 0) {
            $saved_person_abilities = $responses->get_initial_person_abilities();
        } else if(count($saved_person_abilities) < count($initial = $responses->get_initial_person_abilities())) {
            $new_users = array_diff(array_keys($initial->get_person_params()), array_keys($saved_person_abilities->get_person_params()));
            foreach ($new_users as $userid) {
                $saved_person_abilities->add(new model_person_param($userid));
            }
        }
        $this->initial_person_abilities = $saved_person_abilities;
    }

    private function set_options(array $options): self {
        $this->max_iterations = array_key_exists('max_iterations', $options)
            ? $options['max_iterations']
            : self::MAX_ITERATIONS;
        $strategy_options = array_key_exists('strategy', $options)
            ? $options['strategy']
            : [];

        $this->model_override = array_key_exists('model_override', $options)
            ? $options['model_override']
            : self::DEFAULT_MODEL;

        return $this;
    }

    public static function handle_mform(MoodleQuickForm &$mform) {
        $mform->addElement('header', 'strategy', get_string('strategy', 'local_catquiz'));
        $mform->addElement('text', 'max_iterations', get_string('max_iterations', 'local_catquiz'), PARAM_INT);
        $mform->addElement('text', 'model_override', get_string('model_override', 'local_catquiz'), PARAM_TEXT);
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
        $person_abilities = $this->initial_person_abilities;

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

            $filtered_item_difficulties = $this->select_item_model($item_difficulties);
            $person_abilities = $this
                ->ability_estimator
                ->get_person_abilities($filtered_item_difficulties);

            $this->iterations++;
        }

        $item_difficulties_with_status = $this->set_status(
            $item_difficulties,
            $filtered_item_difficulties
        );

        return [$item_difficulties_with_status, $person_abilities];
    }

    /**
     * Set the status field in the caluclated item params
     *
     * In the filtered items, the status is set to "SET_BY_STRATEGY". Here, this
     * status is copied back to the corresponding calculated items.
     *
     * @param model_item_param_list[] $calculated_item_difficulties
     * @param model_item_param_list $selected_item_difficulties
     * @return model_item_param_list[]
     */
    private function set_status(
        array $calculated_item_difficulties,
        model_item_param_list $selected_item_difficulties
    ) {
        foreach ($selected_item_difficulties as $selected_item) {
            $model = $selected_item->get_model_name();
            $id = $selected_item->get_id();
            $calculated_item_difficulties[$model][$id]->set_status($selected_item->get_status());
        }
        return $calculated_item_difficulties;
    }

    /**
     * For each item, selects the model that should be used
     * 
     * Merges the given item param lists into a single list
     *
     * @param array<model_item_param_list> $item_difficulties_lists List of calculated item difficulties, one for each model
     * @return model_item_param_list A single list of item difficulties that is a combination of the input lists
     */
    public function select_item_model(array $item_difficulties_lists): model_item_param_list {
        $new_item_difficulties = new model_item_param_list();
        $item_ids = $this->responses->get_item_ids();

        /**
         * Select items according to the following rules:
         * 1. If there is an item-level model override, select the item from that model
         * 2. If there is a strategy-level override, select the item from that model
         * 3. Otherwise, select the item from the default model
         *
         * TODO: Remove, just for illustration:
         * This could be defined at the DB level with the following data in the `json` field:
         * {
         *      "default": true, // this is the default context
         *      "max_iterations": 10,
         *      "overrides": {
         *          "model": "demo",
         *          "item": [
         *              {"id": 1, "model": "demo2",},
         *              {"id": 35, "model": "raschbirnbauma"}
         *          ]
         *      }
         * }
         */
        foreach ($item_ids as $item_id) {
            if ($model = $this->get_item_override($item_id)) {
                $item = $item_difficulties_lists[$model][$item_id];
            } else if ($model = $this->get_model_override()) {
                $item = $item_difficulties_lists[$model][$item_id];
            } else {
                $item = $item_difficulties_lists[$this->get_default_model()][$item_id];
            }

            // If the item was filtered out by the selected model, do not add it to the list of items for the next round
            if ($item === null) {
                continue;
            }
            $item->set_status(model_item_param::STATUS_SET_BY_STRATEGY);
            $new_item_difficulties->add($item);
        }

        return $new_item_difficulties;
    }

    private function get_item_override(int $item_id): ?string {
        return NULL; // TODO implement
    }

    private function get_model_override(): ?string {
        return $this->model_override; // TODO implement
    }

    private function get_default_model(): string {
        return self::DEFAULT_MODEL;
    }

    /**
     * @return array
     */
    public function get_params_from_db(int $contextid, int $catscaleid): array {
        $models = $this->get_installed_models();
        foreach (array_keys($models) as $model_name) {
            $estimated_item_difficulties[$model_name] = model_item_param_list::load_from_db(
                $contextid,
                $model_name,
                $catscaleid
            );
        }
        $person_abilities = model_person_param_list::load_from_db($contextid, $catscaleid);
        return [$estimated_item_difficulties, $person_abilities];
    }

    private function should_stop(): bool {
        return $this->iterations >= $this->max_iterations;
    }

    /**
     * Returns classes of installed models, indexed by the model name
     *
     * @return array<string>
     */
    public static function get_installed_models(): array {
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
        $ignorelist = [];

        foreach (self::get_installed_models() as $name => $classname) {
            $modelclass = new $classname($this->responses, $name);
            if (in_array($name, $ignorelist)) {
                continue;
            }
            $instances[$name] = $modelclass;
        }
        return $instances;
    }
}
