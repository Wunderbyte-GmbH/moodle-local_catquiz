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

use core_plugin_manager;
use Exception;
use local_catquiz\data\catquiz_base;

/**
 * This class
 */
class catmodel_info {


    // For some items, the model returns -INF or INF as difficulty.
    // However, we expect it to be numeric, so we encode those
    // values as -1000 and 1000
    const MODEL_NEG_INF = -1000;
    const MODEL_POS_INF = 1000;

    /**
     * Undocumented function
     *
     * @param integer $catcontext
     * @param integer $testitemid
     * @param string $component
     * @param string $model
     * @return array
     */
    public static function get_item_parameters(
        int $catcontext = 0,
        int $testitemid = 0,
        string $component = '',
        string $model = ''):array {

        $returnarray = [];

        // Retrieve all the responses in the given context.
        $responses = catquiz_base::get_question_results_by_person(0, 0, $testitemid);

        // Right now, we need different responses.
        $responses = [
            [0, 1, 1, 0, 1]];

        // Get all Models.
        $models = self::get_installed_models();

        // We run through all the models.
        $classes = [];

        foreach ($models as $model) {
            $classname = 'catmodel_' . $model->name . '\\' . $model->name;

            if (class_exists($classname)) {
                $modelclass = new $classname($responses); // The constructure takes our array of responses.
                $classes[] = $modelclass;
                $itemparams = $modelclass->get_item_parameters([]);
                $returnarray[] = [
                    'modelname' => $model->name,
                    'itemparameters' => $itemparams,
                ];
            }
        }

        return $returnarray;
    }

    /**
     * Returns an array of installed models.
     *
     * @return array
     */
    public static function get_installed_models():array {

        $pm = core_plugin_manager::instance();
        return $pm->get_plugins_of_type('catmodel');
    }

    public function get_context_parameters( int $contextid = 0, bool $calculate = false) {
        $cm_params = new catmodel_params('raschbirnbauma');

        if (!$calculate) {
            return $this->get_estimated_parameters_from_db($contextid, $model);
        }
        list ($estimated_item_difficulties, $estimated_person_abilities) = $this->run_estimation($contextid);
        $cm_params->save_estimated_item_parameters_to_db($estimated_item_difficulties);
        $cm_params->save_estimated_person_parameters_to_db($estimated_person_abilities);
        return [$estimated_item_difficulties, $estimated_person_abilities];
    }
    private function get_estimated_parameters_from_db(int $contextid, string $model) {
        global $DB;

        $item_rows = $DB->get_records('local_catquiz_itemparams',
            [
                'contextid' => $contextid,
                'model' => $model,
            ],
            'difficulty ASC'
        );
        $items = [];
        foreach ($item_rows as $r) {
            $items[$r->componentid] = $r->difficulty;
        }

        $person_rows = $DB->get_records('local_catquiz_personparams',
            [
                'contextid' => $contextid,
                'model' => $model,
            ],
            'ability ASC'
        );
        $persons = [];
        foreach ($person_rows as $r) {
            $persons[$r->userid] = $r->ability;
        }

        return [$items, $persons];
    }

    private function run_estimation(int $contextid) {
        $response = catmodel_response::create_from_db($contextid);
        $demo_persons = array_map(
            function($id) {
                return ['id' => $id, 'ability' => 0];
            },
            array_keys($response->getData())
        );

        $cil = $response->to_item_list();
        $cil->estimate_initial_item_difficulties();

        $cpl = new catmodel_person_list($demo_persons);
        $cpl->estimate_person_abilities($response, $cil->get_item_difficulties());

        $demo_item_responses = $response->get_item_response($cpl->get_estimated_person_abilities());

        $estimated_item_difficulty_next = [];
        foreach($demo_item_responses as $item_id => $item_response){
            $item_difficulty = \local_catquiz\catcalc::estimate_item_difficulty($item_response);

            $estimated_item_difficulty_next[$item_id] = $item_difficulty;
        }

        return [$estimated_item_difficulty_next, $cpl->get_estimated_person_abilities()];
    }
};