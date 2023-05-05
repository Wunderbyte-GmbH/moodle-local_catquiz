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
* @copyright 2023 Wunderbyte GmbH <info@wunderbyte.at>
* @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

namespace local_catquiz\local\model;
use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;
use Traversable;

/**
 * This class holds a list of item param objects
 *  
 * This is one of the return values from a model param estimation.
 */
class model_item_param_list implements ArrayAccess, IteratorAggregate {
    /**
     * @var array<model_item_param>
     */
    private array $item_params;

    public function __construct() {
        $this->item_params = [];
    }

    /**
     * Try to load existing item params from the DB.
     * If none are found, it returns an empty list.
     *
     * @param int $contextid
     * @param string $model_name
     * @return model_item_param_list
     */
    public static function load_from_db(int $contextid, string $model_name): self {
        global $DB;

        $item_rows = $DB->get_records(
            'local_catquiz_itemparams',
            [
                'contextid' => $contextid,
                'model' => $model_name,
            ],
        );
        $item_difficulties = new model_item_param_list();
        foreach ($item_rows as $r) {
            $i = new model_item_param($r->componentid, $model_name);
            $i->set_difficulty($r->difficulty);
            $item_difficulties->add($i);
        }

        return $item_difficulties;
    }

    public function getIterator(): Traversable {
        return new ArrayIterator($this->item_params);
    }

    public function add(model_item_param $item_param) {
        $this->item_params[$item_param->get_id()] = $item_param;
    }

    public function offsetSet($offset, $value): void {
        if (is_null($offset)) {
            $this->item_params[] = $value;
        } else {
            $this->item_params[$offset] = $value;
        }
    }

    public function offsetExists($offset): bool {
        return isset($this->item_params[$offset]);
    }

    public function offsetUnset($offset): void {
        unset($this->item_params[$offset]);
    }

    public function offsetGet($offset) {
        return isset($this->item_params[$offset]) ? $this->item_params[$offset] : null;
    }

    /**
     * Returns the item difficulties as a float array
     * 
     * @return array<float>
     */
    public function get_values($sorted = false): array
    {
        $data = array_map(
            function (model_item_param $param) {
                return $param->get_difficulty();
            },
            $this->item_params
        );

        $data = array_filter($data, function ($a) {
            return is_finite($a) && abs($a) != model_item_param::MODEL_POS_INF;
        });
        if ($sorted) {
            sort($data);
        }
        return $data;
    }
    public function save_to_db(int $contextid) {

        global $DB;

        // Get existing records for the given contextid from the DB, so that we know
        // whether we should create a new item param or update an existing one.
        $existing_params_rows = $DB->get_records(
            'local_catquiz_itemparams',
            ['contextid' => $contextid,]
        );
        $existing_params = [];
        foreach ($existing_params_rows as $r) {
            $existing_params[$r->componentid][$r->model] = $r;
        };

        $records = array_map(
            function ($param) use ($contextid) {
                if (is_infinite($param->get_difficulty())) {
                    $difficulty = $param < 0 ? model_item_param::MODEL_NEG_INF : model_item_param::MODEL_POS_INF;
                } else {
                    $difficulty = $param->get_difficulty();
                }

                return [
                    'componentid' => $param->get_id(),
                    'componentname' => 'question',
                    'difficulty' => $difficulty,
                    'model' => $param->get_model_name(),
                    'contextid' => $contextid,
                    'status' => $param->get_status(),
                ];
            },
            $this->item_params
        );

        $updated_records = [];
        $new_records = [];
        $now = time();
        foreach ($records as $record) {
            $is_existing_param = array_key_exists($record['componentid'], $existing_params)
                && array_key_exists($record['model'], $existing_params[$record['componentid']]);
            // If record already exists, update it. Otherwise, insert a new record to the DB
            if ($is_existing_param) {
                $record['id'] = $existing_params[$record['componentid']][$record['model']]->id;
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
};