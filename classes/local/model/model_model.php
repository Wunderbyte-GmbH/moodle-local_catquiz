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
     * Model-specific instantiation can go here.
     */
    abstract public function __construct(model_response $response);

    /**
     * Get Item Parameters by feedbing responses.
     *
     * @param model_response $responses
     * @return model_item_param_list
     */
    abstract public function get_item_parameters(model_response $responses): model_item_param_list;


    /**
     * Get the Persons ability based on their responses.
     *
     * @param model_response $responses
     * @return model_person_param_list
     */
    abstract public function get_person_abilities(model_response $responses): model_person_param_list;

    /**
     * Executes the model-specific code to estimate item- and person-parameters based
     * on the given response object.
     * 
     * @param model_response $response
     * @return array<model_item_param_list, model_person_param_list>
     */
    abstract public function run_estimation(model_response $response): array;
}
