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
        $model = 'raschbirnbauma'; // TODO: make dynamic

        if (!$calculate) {
            return $this->get_estimated_parameters_from_db($contextid, $model);
        }
        global $DB;

        list ($sql, $params) = catquiz::get_sql_for_model_input($contextid);
        $data = $DB->get_records_sql($sql, $params);
        $inputdata = $this->db_to_modelinput($data);
        list ($estimated_item_difficulties, $estimated_person_abilities) = $this->run_estimation($inputdata);
        $this->save_estimated_item_parameters_to_db($contextid, $estimated_item_difficulties, $model);
        $this->save_estimated_person_parameters_to_db($contextid, $estimated_person_abilities, $model);
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
    private function save_estimated_item_parameters_to_db(int $contextid, array $estimated_parameters, string $model) {
        global $DB;
        // Get existing records for the given contextid and model.
        $existing_params_rows = $DB->get_records('local_catquiz_itemparams', ['model' => $model, 'contextid' => $contextid,]);
        $existing_params = [];
        foreach ($existing_params_rows as $r) {
            $existing_params[$r->componentid] = $r;
        };

        $records = array_map(
            function ($componentid, $param) use ($contextid, $model) {
                if (!is_finite($param)) {
                    $param = $param < 0 ? self::MODEL_NEG_INF : self::MODEL_POS_INF;
                }
                return [
                    'componentid' => $componentid,
                    'componentname' => 'question',
                    'difficulty' => $param,
                    'model' => $model,
                    'contextid' => $contextid,
                ];
            },
            array_keys($estimated_parameters),
            array_values($estimated_parameters)
        );

        $updated_records = [];
        $new_records = [];
        $now = time();
        foreach ($records as $record) {
            $is_existing_param = array_key_exists($record['componentid'], $existing_params);
            // If record already exists, update it. Otherwise, insert a new record to the DB
            if ($is_existing_param) {
                $record['id'] = $existing_params[$record['componentid']]->id;
                $record['timemodified'] = $now;
                $updated_records[] = $record;
            } else {
                $record['timecreated'] = $now;
                $record['timemodified'] = $now;
                $new_records[] = $record;
            }
        }

        if (!empty($new_records)) {
            $DB->insert_records('local_catquiz_itemparams', $new_records);
            
        }
        // Maybe change to bulk update later
        foreach ($updated_records as $r) {
            $DB->update_record('local_catquiz_itemparams', $r, true);
        }
    }

    private function save_estimated_person_parameters_to_db(int $contextid, array $estimated_parameters, string $model) {
        global $DB;
        // Get existing records for the given contextid and model.
        $existing_params_rows = $DB->get_records('local_catquiz_personparams', ['model' => $model, 'contextid' => $contextid,]);
        $existing_params = [];
        foreach ($existing_params_rows as $r) {
            $existing_params[$r->userid] = $r;
        };

        $records = array_map(
            function ($userid, $param) use ($contextid, $model) {
                if (!is_finite($param)) {
                    $param = $param < 0 ? self::MODEL_NEG_INF : self::MODEL_POS_INF;
                }
                return [
                    'userid' => $userid,
                    'ability' => $param,
                    'model' => $model,
                    'contextid' => $contextid,
                ];
            },
            array_keys($estimated_parameters),
            array_values($estimated_parameters)
        );

        $updated_records = [];
        $new_records = [];
        $now = time();
        foreach ($records as $record) {
            $is_existing_param = array_key_exists($record['userid'], $existing_params);
            // If record already exists, update it. Otherwise, insert a new record to the DB
            if ($is_existing_param) {
                $record['id'] = $existing_params[$record['userid']]->id;
                $record['timemodified'] = $now;
                $updated_records[] = $record;
            } else {
                $record['timecreated'] = $now;
                $record['timemodified'] = $now;
                $new_records[] = $record;
            }
        }

        if (!empty($new_records)) {
            $DB->insert_records('local_catquiz_personparams', $new_records);
        }
        // Maybe change to bulk update later
        foreach ($updated_records as $r) {
            $DB->update_record('local_catquiz_personparams', $r, true);
        }
    }

    /**
     * Returns data in the following format
     * 
     * "1" => Array( //userid
     *     "comp1" => Array( // component
     *         "1" => Array( //questionid
     *             "fraction" => 0,
     *             "max_fraction" => 1,
     *             "min_fraction" => 0,
     *             "qtype" => "truefalse",
     *             "timestamp" => 1646955326
     *         ),
     *         "2" => Array(
     *             "fraction" => 0,
     *             "max_fraction" => 1,
     *             "min_fraction" => 0,
     *             "qtype" => "truefalse",
     *             "timestamp" => 1646955332
     *         ),
     *         "3" => Array(
     *             "fraction" => 1,
     *             "max_fraction" => 1,
     *             "min_fraction" => 0,
     *             "qtype" => "truefalse",
     *             "timestamp" => 1646955338
     */
    private function db_to_modelinput($data) {
        $modelinput = [];
        foreach ($data as $row) {
            $entry = [
                'fraction' => $row->fraction,
                'max_fraction' =>  $row->maxfraction,
                'min_fraction' => $row->minfraction,
                'qtype' => $row->qtype,
                'timestamp' => $row->timecreated,
            ];

            if (!array_key_exists($row->userid, $modelinput)) {
                $modelinput[$row->userid] = ["component" => []];
            }

            $modelinput[$row->userid]['component'][$row->questionid] = $entry;
        }
        return $modelinput;
    }

    private function run_estimation($inputdata) {
        $demo_persons = array_map(
            function($id) {
                return ['id' => $id, 'ability' => 0];
            },
            array_keys($inputdata)
        );

        $cil = catmodel_item_list::create_from_response($inputdata);
        $estimated_item_difficulty = $cil->estimate_initial_item_difficulties();

        $estimated_person_abilities = [];
        foreach($demo_persons as $person){

            $person_id = $person['id'];
            $item_difficulties = $estimated_item_difficulty; // replace by something better
            $person_response = \local_catquiz\helpercat::get_person_response($inputdata, $person_id);
            $person_ability = \local_catquiz\catcalc::estimate_person_ability($person_response, $item_difficulties);

            $estimated_person_abilities[$person_id] = $person_ability;
        }


        $demo_item_responses = \local_catquiz\helpercat::get_item_response($inputdata, $estimated_person_abilities);

        $estimated_item_difficulty_next = [];

        foreach($demo_item_responses as $item_id => $item_response){
            $item_difficulty = \local_catquiz\catcalc::estimate_item_difficulty($item_response);

            $estimated_item_difficulty_next[$item_id] = $item_difficulty;
        }

        return [$estimated_item_difficulty, $estimated_person_abilities];
    }
};