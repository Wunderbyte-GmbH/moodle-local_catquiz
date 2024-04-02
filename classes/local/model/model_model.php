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
 * Abstract class model_model.
 *
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\local\model;

/**
 * Abstract class for model classes.
 *
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class model_model {

    /**
     * @var string Name of model
     */
    protected string $modelname;

    /**
     * Model-specific instantiation can go here.
     *
     * @param model_responses $responses
     * @param string $modelname
     *
     */
    public function __construct(string $modelname) {
        $this->modelname = $modelname;
    }

    /**
     * Return the name of the current model
     *
     * @return string
     */
    public function get_model_name() {
        return $this->modelname;
    }

    /**
     * Helper to create a new item param
     *
     * @param string   $itemid
     * @param array $metadata Optional metadata
     * @return model_item_param
     */
    protected function create_item_param(string $itemid, array $metadata = []): model_item_param {
        return new model_item_param($itemid, $this->modelname, $metadata);
    }

    /**
     * Executes the model-specific code to estimate item-parameters based
     * on the given person abilities.
     *
     * @param model_responses $responses
     * @param model_person_param_list $personparams
     * @param ?model_item_param_list $olditemparams
     * @return model_item_param_list
     */
    abstract public function estimate_item_params(
        model_responses $responses,
        model_person_param_list $personparams,
        ?model_item_param_list $olditemparams = null): model_item_param_list;

    /**
     * Returns the paramter names of the model as strings.
     *
     * @return string[]
     */
    abstract protected static function get_parameter_names(): array;

    /**
     * Fisher info.
     *
     * @param array $personability
     * @param array $params
     *
     * @return mixed
     *
     */
    abstract public static function fisher_info(array $personability, array $params);

    /**
     * Get information criterion
     *
     * @param string $criterion
     * @param model_person_param_list $personabilities
     * @param model_item_param $itemparams
     * @param model_responses $k
     *
     * @return float
     *
     */
    abstract public function get_information_criterion(
        string $criterion,
        model_person_param_list $personabilities,
        model_item_param $itemparams,
        model_responses $k): float;
}
