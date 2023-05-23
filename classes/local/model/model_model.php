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
 * Abstract class for model classes.
 *
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class model_model {

    /**
     * @var model_responses Contains necessary data for estimation
     */
    protected model_responses $responses;

    protected string $model_name;

    /**
     * Model-specific instantiation can go here.
     */
    public function __construct(model_responses $responses, string $model_name) {
        $this->responses = $responses;
        $this->model_name = $model_name;
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
            $item_difficulty = $this->calculate_difficulty($item_response);
            // Now create a new item difficulty object (param)
            $param = $this
                ->create_item_param($item_id, ['from_raschbirnbauma' => 'hello hello'])
                ->set_difficulty($item_difficulty);
            // ... and append it to the list of calculated item difficulties
            $estimated_item_params->add($param);
        }
        return $estimated_item_params;
    }

    abstract protected function calculate_difficulty($item_response);

    public function get_gradient() {
        return [];
    }
}
