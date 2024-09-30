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

use MoodleQuickForm;
use stdClass;

/**
 * Abstract class for model classes.
 *
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class model_model {

    /**
     * Holds model instances.
     *
     * @var array $models
     */
    private static $models = [];
    /**
     * Make constructor private to force usage of get_instance()
     */
    protected function __construct() {
    }

    /**
     * Return an instance of the model with the given name
     *
     * @param string $name
     * @return self
     */
    public static function get_instance(string $name): model_model {
        if (!array_key_exists($name, self::$models)) {
            $classname = sprintf('catmodel_%s\%s', $name, $name);
            if (!class_exists($classname)) {
                return null;
            }
            self::$models[$name] = new $classname();
        }
        return self::$models[$name];
    }

    /**
     * Allows subclasses to overwrite the parameters.
     *
     * @param stdClass $record
     * @param array $parameters
     * @return stdClass
     */
    public static function add_parameters_to_record(stdClass $record, array $parameters): stdClass {
        return $record;
    }

    /**
     * Return the name of the current model
     *
     * @return string
     */
    abstract public function get_model_name();

    /**
     * Helper to create a new item param
     *
     * @param int   $itemid
     * @param array $metadata Optional metadata
     * @return model_item_param
     */
    protected function create_item_param(int $itemid, array $metadata = []): model_item_param {
        return new model_item_param($itemid, $this->get_model_name(), $metadata);
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
     * Returns the parameters as associative array, where the key is the parameter name.
     *
     * @param \stdClass $record
     * @return array
     */
    abstract public static function get_parameters_from_record(stdClass $record): array;

    /**
     * Fisher info.
     *
     * @param array $personability
     * @param array $params
     *
     * @return mixed
     *
     */
    abstract public function fisher_info(array $personability, array $params);

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

    /**
     * Return the difficulty as a single float.
     *
     * Can be overwritten by models that need to convert an array of difficulties into a single value.
     * @param array $parameters
     * @return float
     */
    public static function get_difficulty(array $parameters): float {
        return $parameters['difficulty'] ?? 0.0;
    }

    /**
     * Add model specific fields to override model parameters.
     *
     * @param MoodleQuickForm $mform
     * @param model_item_param $param
     * @param string $groupid
     * @return void
     */
    abstract public function definition_after_data_callback(
        MoodleQuickForm &$mform,
        model_item_param $param,
        string $groupid
    ): void;

    /**
     * Get parameter fields
     *
     * @param model_item_param $param
     * @return array
     */
    abstract public function get_parameter_fields(model_item_param $param): array;

    /**
     * Convert arry to record
     *
     * @param array $formarray
     * @return stdClass
     */
    abstract public function form_array_to_record(array $formarray): stdClass;

    /**
     * Get default params
     *
     * @return array
     */
    abstract public function get_default_params(): array;

    /**
     * Returns the parameters as flat array with the key being a translated label.
     * @param \local_catquiz\local\model\model_item_param $param
     * @return array
     */
    abstract public function get_static_param_array(model_item_param $param): array;
}
