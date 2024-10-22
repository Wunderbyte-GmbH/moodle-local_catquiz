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
 * @copyright  2024 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\local\model;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/catquiz/lib.php');

use core_plugin_manager;
use local_catquiz\catscale;
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
    const MAX_ITERATIONS = 2; // TODO: get value from DB?

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
     * @var array<model_item_param_list> $olditemparams
     */
    private array $olditemparams;

    /**
     * The ID of the catscale
     *
     * @var int $catscaleid
     */
    private int $catscaleid;

    /**
     * Tracks the parameters calculated by iteration round and model.
     *
     * @var array<array<model_item_param_list>> $calculatedparams
     */
    private array $calculatedparams;

    /**
     * Tracks the abilites calculated by iteration round.
     * 
     * @var array<model_person_param_list>
     */
    private array $calculatedabilities;

    /**
     * Model-specific instantiation can go here.
     *
     * @param model_responses $responses
     * @param array $options
     * @param array $olditemparams
     *
     */
    public function __construct(
        model_responses $responses,
        array $options = [],
        array $olditemparams = []
    ) {
        $this->responses = $responses;
        $this->models = model_sorter::sort($this->create_installed_models());
        $this->abilityestimator = new model_person_ability_estimator_catcalc($this->responses);
        $this->set_options($options);
        $this->olditemparams = $olditemparams;

        // if ($savedpersonabilities === null || count($savedpersonabilities) === 0) {
        //     $savedpersonabilities = $responses->get_person_abilities();
        // } else if (count($savedpersonabilities) < count($initial = $responses->get_person_abilities())) {
        //     $newusers = array_diff(
        //         array_keys($initial->get_person_params()),
        //         array_keys($savedpersonabilities->get_person_params()));
        //     foreach ($newusers as $userid) {
        //         $savedpersonabilities->add(new model_person_param($userid));
        //     }
        // }
        // $this->initialpersonabilities = $savedpersonabilities;

        foreach (array_keys($this->models) as $model) {
            $this->set_calculated_progress($model, null);
        }
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

        $this->modeloverride = array_key_exists('model_override', $options)
            ? $options['model_override']
            : null;

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
        $personabilities = $this->responses->get_person_abilities();

        /** @var array<model_item_param_list> $itemdifficulties */
        $itemdifficulties = [];
        // Re-calculate until the stop condition is triggered.
        while (!$this->should_stop()) {
            $iterationstart = microtime(true);
            foreach ($this->models as $name => $model) {
                $oldmodelparams = $this->olditemparams[$name] ?? null;
                $startvalues = $this->get_startvalues_for_model($name) ?? null;
                if ($startvalues && $oldmodelparams) {
                    $startvalues->without($oldmodelparams->confirmed(), false);
                }
                $modelstart = microtime(true);
                $itemdifficulties[$name] = $model
                    ->estimate_item_params($this->responses, $personabilities, $startvalues);
                mtrace(
                    sprintf(
                        'Updating %d item params with model %s took %fs',
                        count($itemdifficulties[$name]),
                        $name,
                        microtime(true) - $modelstart
                    )
                );
                $this->set_calculated_progress($name, $itemdifficulties[$name]);
            }

            // Keep track of calculated item parameters

            $selectstart = microtime(true);
            $filtereddiffi = $this->select_item_model($itemdifficulties, $personabilities); // Maybe slow.
            mtrace(sprintf('Selection process of best items took %fs in iteration %d', microtime(true) - $selectstart, $this->iterations + 1));
            $abilitystart = microtime(true);
            $personabilities = $this
                ->abilityestimator
                ->get_person_abilities($filtereddiffi);
            mtrace(sprintf('Updating person abilities took %fs in iteration %d', microtime(true) - $abilitystart, $this->iterations + 1));
            $this->set_calculated_abilities_progress($personabilities);
            $this->responses->set_person_abilities($personabilities);
            $this->iterations++;
            mtrace(sprintf('Updating item paramters in iteration %d took %fs', $this->iterations, microtime(true) - $iterationstart));
        }

        $itemdiffiwstatus = $this->set_status(
            $itemdifficulties, // TODO: merge again with oldparams that are manually confirmed and set the correct status for each model
            $filtereddiffi
        );

        return [$itemdiffiwstatus, $personabilities];
    }

    /**
     * Set the status field in the caluclated item params
     *
     * In the filtered items, the status is set to "SET_BY_STRATEGY". Here, this
     * status is copied back to the corresponding calculated items.
     *
     * @param model_item_param_list[] $calcdifficulties
     * @param model_item_param_list $selecteddiffic
     * @return model_item_param_list[]
     */
    private function set_status(
        array $calcdifficulties,
        model_item_param_list $selecteddiffic
    ) {
        foreach ($selecteddiffic as $selecteditem) {
            $model = $selecteditem->get_model_name();
            $id = $selecteditem->get_componentid();
            $calcdifficulties[$model][$id]->set_status($selecteditem->get_status());
        }
        return $calcdifficulties;
    }

    /**
     * For each item, selects the model that should be used
     *
     * @param array $itemdifflists List of calculated item difficulties, one for each model
     * @param model_person_param_list $personabilities A single list of item difficulties that is a combination of the input lists
     *
     * @return model_item_param_list
     *
     */
    public function select_item_model(
        array $itemdifflists,
        model_person_param_list $personabilities): model_item_param_list {
        $newitemdifficulties = new model_item_param_list();
        $itemids = $this->responses->get_item_ids();
        // TODO set via settings.
        $informationcriterium = 'aic';
        $infocriteriapermodel = [];

        /*
         * Select items according to the following rules:
         * 1. If there is an item-level model override, select the item from that model
         * 2. Otherwise, use the model that maximizes the given information criterium
         */
        foreach ($itemids as $itemid) {
            $item = $this->select_item_from_override($itemid, $itemdifflists);
            if (! is_null($item)) {
                $newitemdifficulties->add($item);
                continue;
            }
            foreach ($this->models as $model) {
                $item = $itemdifflists[$model->get_model_name()][$itemid];
                if (!$item) {
                    continue;
                }
                $val = $model->get_information_criterion($informationcriterium, $personabilities, $item, $this->responses);
                $infocriteriapermodel[$itemid][$model->get_model_name()] = $val;
                $maxmodelname = array_keys($infocriteriapermodel[$itemid], min($infocriteriapermodel[$itemid]))[0];
                $selecteditem = $itemdifflists[$maxmodelname][$itemid];
                $selecteditem->set_status(LOCAL_CATQUIZ_STATUS_CALCULATED);
                $newitemdifficulties->add($selecteditem);
            }
        }

        return $newitemdifficulties;
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
     * Obrain model params from DB
     *
     * @param int $contextid
     * @param int $catscaleid
     *
     * @return array
     *
     */
    public function get_params_from_db(int $contextid, int $catscaleid): array {
        $models = self::get_installed_models();
        $catscaleids = [$catscaleid, ...catscale::get_subscale_ids($catscaleid)];
        foreach (array_keys($models) as $modelname) {
            $estdifficulties[$modelname] = model_item_param_list::load_from_db(
                $contextid,
                $modelname,
                $catscaleids
            );
        }
        $personabilities = model_person_param_list::load_from_db($contextid, $catscaleids);
        return [$estdifficulties, $personabilities];
    }

    /**
     * Returns the responses
     *
     * @return \local_catquiz\local\model\model_responses
     */
    public function get_responses(): model_responses {
        return $this->responses;
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
        /** @var array<model_model> $instances */
        $instances = [];
        $ignorelist = ['grmgeneralized', 'grm', 'pcmgeneralized', 'pcm'];

        foreach (self::get_installed_models() as $name => $classname) {
            if (in_array($name, $ignorelist)) {
                continue;
            }
            $instances[$name] = model_model::get_instance($name);
        }
        return $instances;
    }

    /**
     * Select item from override.
     *
     * @param string $itemid
     * @param array $itemdifflists
     *
     * @return mixed
     *
     */
    private function select_item_from_override(string $itemid, array $itemdifflists) {
        global $CFG;
        if ($item = model_item_param_list::get_item_override($itemid, $itemdifflists)) {
            return $item;
        }

        $item = null;
        if ($model = $this->get_model_override()) {
            $item = $itemdifflists[$model][$itemid];
        }

        // If there are no data for an item override, fail with an
        // exception in the development environment but fall back to the
        // default select strategy in a production environment.
        if (! is_null($model) && is_null($item) && $CFG->debug > 0) {
            throw new \Exception("Item override to a model that has no data");
        }
        return $item;
    }

    private function set_calculated_progress(string $modelname, ?model_item_param_list $itemparams) {
        $this->calculatedparams[$modelname][$this->iterations] = $itemparams;
    }

    private function get_last_calculated_for_model(string $modelname): ?model_item_param_list {
        return end($this->calculatedparams[$modelname]) ?? null;
    }

    private function get_startvalues_for_model(string $modelname): ?model_item_param_list {
        switch ($modelname) {
            case 'rasch':
                return $this->get_last_calculated_for_model('rasch');
            case 'raschbirnbaum':
                return $this->get_last_calculated_for_model('raschbirnbaum')
                    ?? $this->get_last_calculated_for_model('rasch');
            case 'mixedraschbirnbaum':
                return $this->get_last_calculated_for_model('mixedraschbirnbaum')
                    ?? $this->get_last_calculated_for_model('raschbirnbaum');
            default:
                throw new \Exception ("Unknown model $modelname");
        }
    }

    private function set_calculated_abilities_progress(model_person_param_list $abilities) {
        $this->calculatedabilities[$this->iterations] = $abilities;
    }

    private function get_param_progress(string $modelname, string $itemid): array {
        return array_map(fn ($paramlist) => $paramlist->offsetGet($itemid)->get_params_array(), $this->calculatedparams[$modelname]);
    }

    private function get_ability_progress(string $personid): array {
        return array_map(fn ($paramlist) => $paramlist->offsetGet($personid)->get_ability(), $this->calculatedabilities);
    }
}
