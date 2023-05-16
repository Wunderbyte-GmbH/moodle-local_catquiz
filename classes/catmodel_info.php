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
* Entities Class to display list of entity records.
*
* @package local_catquiz
* @author Georg Maißer
* @copyright 2023 Wunderbyte GmbH <info@wunderbyte.at>
* @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

namespace local_catquiz;

use coding_exception;
use core_plugin_manager;
use local_catquiz\catcontext;
use local_catquiz\data\catquiz_base;
use local_catquiz\local\model\model_calc;
use local_catquiz\local\model\model_item_param;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_model;
use local_catquiz\local\model\model_person_ability_estimator_demo;
use local_catquiz\local\model\model_person_param;
use local_catquiz\local\model\model_person_param_list;
use local_catquiz\local\model\model_responses;
use local_catquiz\local\model\model_strategy;

/**
 * This class
 */
class catmodel_info {


    /**
     * Undocumented function
     *
     * @param integer $catcontext
     * @param integer $testitemid
     * @param string $component
     * @param string $model
     * @return array
     */
    public function get_item_parameters(int $catcontext = 0):array {
        $returnarray = [];

        // Retrieve all the responses in the given context.
        list($itemparamsbymodel, $personparamsbymodel) = $this->get_context_parameters($catcontext);
        foreach ($itemparamsbymodel as $model => $itemparams) {
            $returnarray[$model] = [
                'itemparams' => $itemparams,
                'personparams' => $personparamsbymodel
            ];
        }

        return $returnarray;
    }


    /**
     *
     * @param int $contextid
     * @param bool $calculate
     * @return array
     */
    public function get_context_parameters( int $contextid = 0, bool $calculate = false) {
        $context = catcontext::load_from_db($contextid);
        $strategy = $context->get_strategy();

        if ($calculate) {
            list($item_difficulties, $person_abilities) =  $strategy->run_estimation();
            foreach ($item_difficulties as $item_param_list) {
                $item_param_list->save_to_db($contextid);
            }
            $person_abilities->save_to_db($contextid);
            return [$item_difficulties, $person_abilities];
        }

        return $strategy->get_params_from_db($contextid);
    }
}
