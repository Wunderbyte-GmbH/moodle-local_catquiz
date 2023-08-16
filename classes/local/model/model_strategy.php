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
 * Class model_strategy.
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

/**
 * Model strategy class.
 *
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
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class model_strategy {
    /**
     * MAX_ITERATIONS
     *
     * @var int
     */
    const MAX_ITERATIONS = 5; // TODO: get value from DB?

    /**
     * DEFAULT_MODEL
     *
     * @var string
     */
    const DEFAULT_MODEL = 'web_raschbirnbauma';

    /**
     * @var model_responses Contains necessary data for estimation
     */
    private model_responses $responses;

    // TODO: get from DB
    // context
    // max iterations
    // models (exclude, include).

    // Separat: manueller override testitems: nur difficulty von 1 bestimmten modell anzeigen
    // Testitem_model_status: STATUS params (green, yellow, ...) itemid <-> model, model used in last iteration.

    /**
     * @var array<model_model>
     */
    private array $models;

    /**
     * @var model_person_ability_estimator
     */
    private model_person_ability_estimator $abilityestimator;

    /**
     * @var int maxiterations
     */
    private int $maxiterations;

    /**
     * @var int iterations
     */
    private int $iterations = 0;

    /**
     * @var string|null modeloverride
     */
    private ?string $modeloverride;

    /**
     * @var model_person_param_list
     */
    private model_person_param_list $initialpersonabilities;

    /**
     * Model-specific instantiation can go here.
     *
     * @param model_responses $responses
     * @param array $options
     * @param model_person_param_list|null $savedpersonabilities
     *
     */
    public function __construct(
        model_responses $responses,
        array $options = [],
        ?model_person_param_list $savedpersonabilities = null
    ) {
        $this->responses = $responses;
        $this->models = $this->create_installed_models();
        $this->abilityestimator = new model_person_ability_estimator_demo($this->responses);
        $this->set_options($options);

        if ($savedpersonabilities === null || count($savedpersonabilities) === 0) {
            $savedpersonabilities = $responses->get_initial_person_abilities();
        } else if (count($savedpersonabilities) < count($initial = $responses->get_initial_person_abilities())) {
            $newusers = array_diff(array_keys($initial->get_person_params()), array_keys($savedpersonabilities->get_person_params()));
            foreach ($newusers as $userid) {
                $savedpersonabilities->add(new model_person_param($userid));
            }
        }
        $this->initialpersonabilities = $savedpersonabilities;
    }

    /**
     * Set_options.
     *
     * @param array $options
     *
     * @return self
     *
     */
    private function set_options(array $options): self {
        $this->maxiterations = array_key_exists('max_iterations', $options)
            ? $options['max_iterations']
            : self::MAX_ITERATIONS;
        $strategyoptions = array_key_exists('strategy', $options)
            ? $options['strategy']
            : [];

        $this->modeloverride = array_key_exists('model_override', $options)
            ? $options['model_override']
            : self::DEFAULT_MODEL;

        return $this;
    }

    /**
     * Handle mform.
     *
     * @param MoodleQuickForm $mform
     *
     * @return void
     *
     */
    public static function handle_mform(MoodleQuickForm &$mform) {
        $mform->addElement('header', 'strategy', get_string('strategy', 'local_catquiz'));
        $mform->addElement('text', 'max_iterations', get_string('max_iterations', 'local_catquiz'), PARAM_INT);
        $mform->addElement('text', 'model_override', get_string('model_override', 'local_catquiz'), PARAM_TEXT);
    }

    /**
     * Updates the $errors via reference.
     *
     * @param array $data
     * @param array $files
     * @param mixed $errors
     *
     * @return void
     */
    public static function validation($data, $files, &$errors) {
        $maxiterations = intval($data['max_iterations']);
        if ($maxiterations === 0) {
            $errors['max_iterations'] = get_string('noint', 'local_catquiz');
        } else if ($maxiterations <= 0) {
            $errors['max_iterations'] = get_string('notpositive', 'local_catquiz');
        }
    }

    /**
     * Starts the estimation process
     *
     * @return array<model_item_param_list, model_person_param_list>
     */
    public function run_estimation(): array {
        $personabilities = $this->initialpersonabilities;

        /**
         * @var array<model_item_param_list>
         */
        $itemdifficulties = [];
        // Re-calculate until the stop condition is triggered.
        while (!$this->should_stop()) {
            foreach ($this->models as $name => $model) {
                $itemdifficulties[$name] = $model
                    ->estimate_item_params($personabilities);
            }

            $filtereditemdifficulties = $this->select_item_model($itemdifficulties);
            $personabilities = $this
                ->abilityestimator
                ->get_person_abilities($filtereditemdifficulties);

            $this->iterations++;
        }

        $itemdifficultieswithstatus = $this->set_status(
            $itemdifficulties,
            $filtereditemdifficulties
        );

        return [$itemdifficultieswithstatus, $personabilities];
    }

    /**
     * Set the status field in the caluclated item params
     *
     * In the filtered items, the status is set to "SET_BY_STRATEGY". Here, this
     * status is copied back to the corresponding calculated items.
     *
     * @param model_item_param_list[] $calculateditemdifficulties
     * @param model_item_param_list $selecteditemdifficulties
     * @return model_item_param_list[]
     */
    private function set_status(
        array $calculateditemdifficulties,
        model_item_param_list $selecteditemdifficulties
    ) {
        foreach ($selecteditemdifficulties as $selecteditem) {
            $model = $selecteditem->get_model_name();
            $id = $selecteditem->get_id();
            $calculateditemdifficulties[$model][$id]->set_status($selecteditem->get_status());
        }
        return $calculateditemdifficulties;
    }

    /**
     * For each item, selects the model that should be used
     *
     * Merges the given item param lists into a single list
     *
     * @param array $itemdifficultieslists List of calculated item difficulties, one for each model
     * @return model_item_param_list A single list of item difficulties that is a combination of the input lists
     */
    public function select_item_model(array $itemdifficultieslists): model_item_param_list {
        $newitemdifficulties = new model_item_param_list();
        $itemids = $this->responses->get_item_ids();

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
        foreach ($itemids as $itemid) {
            if ($model = $this->get_item_override($itemid)) {
                $item = $itemdifficultieslists[$model][$itemid];
            } else if ($model = $this->get_model_override()) {
                $item = $itemdifficultieslists[$model][$itemid];
            } else {
                $item = $itemdifficultieslists[$this->get_default_model()][$itemid];
            }

            // If the item was filtered out by the selected model, do not add it to the list of items for the next round.
            if ($item === null) {
                continue;
            }
            $item->set_status(STATUS_SET_BY_STRATEGY);
            $newitemdifficulties->add($item);
        }

        return $newitemdifficulties;
    }

    /**
     * Return item override.
     *
     * @param int $itemid
     *
     * @return string|null
     *
     */
    private function get_item_override(int $itemid): ?string {
        return null; // TODO implement.
    }

    /**
     * Return model override.
     *
     * @return string|null
     *
     */
    private function get_model_override(): ?string {
        return $this->modeloverride; // TODO implement.
    }

    /**
     * Return default model.
     *
     * @return string
     *
     */
    private function get_default_model(): string {
        return self::DEFAULT_MODEL;
    }

    /**
     * Obrain model params from DB
     *
     * @param int $contextid
     * @param int $catscaleid
     *
     * @return array
     *
     */
    public function get_params_from_db(int $contextid, int $catscaleid): array {
        $models = $this->get_installed_models();
        foreach (array_keys($models) as $modelname) {
            $estimateditemdifficulties[$modelname] = model_item_param_list::load_from_db(
                $contextid,
                $modelname,
                $catscaleid
            );
        }
        $personabilities = model_person_param_list::load_from_db($contextid, $catscaleid);
        return [$estimateditemdifficulties, $personabilities];
    }

    /**
     * Check if max number of iterations reached.
     *
     * @return bool
     *
     */
    private function should_stop(): bool {
        return $this->iterations >= $this->maxiterations;
    }

    /**
     * Returns classes of installed models, indexed by the model name
     *
     * @return array<string>
     */
    public static function get_installed_models(): array {
        $pm = core_plugin_manager::instance();
        $models = [];
        foreach ($pm->get_plugins_of_type('catmodel') as $name => $info) {
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
         *
         * @var array<model_model>
         */
        $instances = [];
        $ignorelist = ['raschbirnbaumc'];

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
