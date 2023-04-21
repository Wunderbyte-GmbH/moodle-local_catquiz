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
* @author David Szkiba
* @copyright 2023 Wunderbyte GmbH <info@wunderbyte.at>
* @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

namespace local_catquiz;

/**
 * This class provides methods to get, set and update CAT parameters (item
 * parameters as well as person parameters) for a model.
 */
class catmodel_params {

    private int $contextid = 0;
    private string $model;

    public function __construct(string $model, int $contextid = 0) {
        $this->model = $model;
    }
    public function get_model() : string {
        return $this->model;
    }
    public function save_estimated_item_parameters_to_db(array $estimated_parameters) { // TODO: can this be type hinted?

        global $DB;

        // Get existing records for the given contextid and model.
        $existing_params_rows = $DB->get_records(
            'local_catquiz_itemparams',
            ['model' => $this->get_model(), 'contextid' => $this->contextid,]
        );
        $existing_params = [];
        foreach ($existing_params_rows as $r) {
            $existing_params[$r->componentid] = $r;
        };

        $records = array_map(
            function ($componentid, $param) {
                if (!is_finite($param)) {
                    $param = $param < 0 ? catmodel_info::MODEL_NEG_INF : catmodel_info::MODEL_POS_INF;
                }
                return [
                    'componentid' => $componentid,
                    'componentname' => 'question',
                    'difficulty' => $param,
                    'model' => $this->get_model(),
                    'contextid' => $this->contextid,
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

        foreach ($updated_records as $r) {
            $DB->update_record('local_catquiz_itemparams', $r, true);
        }
    }

    public function save_estimated_person_parameters_to_db(array $estimated_parameters) {
        global $DB;
        // Get existing records for the given contextid and model.
        $existing_params_rows = $DB->get_records(
            'local_catquiz_personparams',
            ['model' => $this->get_model(), 'contextid' => $this->contextid,]
        );
        $existing_params = [];
        foreach ($existing_params_rows as $r) {
            $existing_params[$r->userid] = $r;
        };

        $records = array_map(
            function ($userid, $param) {
                if (!is_finite($param)) {
                    $param = $param < 0 ? catmodel_info::MODEL_NEG_INF : catmodel_info::MODEL_POS_INF;
                }
                return [
                    'userid' => $userid,
                    'ability' => $param,
                    'model' => $this->get_model(),
                    'contextid' => $this->contextid,
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

        foreach ($updated_records as $r) {
            $DB->update_record('local_catquiz_personparams', $r, true);
        }
    }

};