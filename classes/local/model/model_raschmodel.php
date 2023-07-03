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
 * TODO: add description
 *
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class model_raschmodel extends model_model {

    /**
     * @var model_responses Contains necessary data for estimation
     */
    protected model_responses $responses;

    protected string $model_name;

    protected static $methods = [
        'get_callable_likelihood' => 'likelihood',
        'get_callable_likelihood_counter' => 'counter_likelihood',
        'get_callable_log_likelihood' => 'log_likelihood',
        'get_callable_log_likelihood_counter' => 'log_counter_likelihood',
    ];

    public static function __callStatic(string $method, array $parameters) {
        return call_user_func_array(self::$methods[$method], ...$parameters);
    }

    /**
     * Model-specific instantiation can go here.
     */
    public function __construct(model_responses $responses, string $model_name) {
        $this->responses = $responses;
        $this->model_name = $model_name;
    }

    /**
     * Return the name of the current model
     *
     * @return string
     */
    public function get_model_name() {
        return $this->model_name;
    }

    /**
     * Helper to create a new item param
     * 
     * @param int   $itemid
     * @param array $metadata Optional metadata
     * @return model_item_param 
     */
    protected function create_item_param(int $itemid, array $metadata = []): model_item_param {
        return new model_item_param($itemid, $this->model_name, $metadata);
    }

    /**
     * Executes the model-specific code to estimate item-parameters based
     * on the given person abilities.
     * 
     * @param model_person_param_list $person_params
     * @return model_item_param_list
     */
    public function estimate_item_params(model_person_param_list $person_params): model_item_param_list
    {
        $estimated_item_params = new model_item_param_list();
        foreach ($this->responses->get_item_response($person_params) as $item_id => $item_response) {
            // Calculate the difficulty -> returns a float value
            $parameters = $this->calculate_params($item_response);
            // Now create a new item difficulty object (param)
            $param = $this
                ->create_item_param($item_id, ['from_raschbirnbauma' => 'hello hello'])
                ->set_parameters($parameters);
            // ... and append it to the list of calculated item difficulties
            $estimated_item_params->add($param);
        }
        return $estimated_item_params;
    }

    /**
     * Returns the item parameters as associative array, with the parameter name as key.
     *
     * @param mixed $item_response
     * @return array
     */
    abstract protected function calculate_params($item_response): array;

    /**
     * Returns the paramter names of the model as strings
     * 
     * @return string[]
     */
    abstract protected static function get_parameter_names(): array;

    /**
     * @param float $person_ability 
     * @param array<float> $params 
     * @return mixed 
     */
    abstract public static function fisher_info(float $person_ability, array $params);
}
