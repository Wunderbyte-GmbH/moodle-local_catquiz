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
use Countable;
use dml_exception;
use IteratorAggregate;
use local_catquiz\catquiz;
use Traversable;

/**
 * This class holds a list of item param objects.
 * 
 * This is one of the return values from a model param estimation.
 * 
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class model_item_param_list implements ArrayAccess, IteratorAggregate, Countable {
    /**
     * @var array<model_item_param>
     */
    private array $item_params;

    /**
     * Set parameters for class instance.
     */
    public function __construct() {
        $this->item_params = [];
    }

    /**
     * Return count of parameters.
     *
     * @return int
     * 
     */
    public function count(): int {
        return count($this->item_params);
    }

    /**
     * Try to load existing item params from the DB.
     * If none are found, it returns an empty list.
     *
     * @param int $contextid
     * @param string $model_name
     * @param int|null $catscaleid
     * 
     * @return self
     * 
     */
    public static function load_from_db(int $contextid, string $model_name, ?int $catscaleid = NULL): self {
        global $DB;
        $models = model_strategy::get_installed_models();

        if ($catscaleid) {
            $item_rows = catquiz::get_itemparams($contextid, $catscaleid, $model_name);
        } else {
            $item_rows = $DB->get_records(
                'local_catquiz_itemparams',
                [
                    'contextid' => $contextid,
                    'model' => $model_name,
                ],
            );
        }
        $item_parameters = new model_item_param_list();
        foreach ($item_rows as $r) {
            // Skip NaN values here
            if ($r->difficulty === "NaN") {
                continue;
            }
            $i = new model_item_param($r->componentid, $model_name);
            $parameter_names = $models[$model_name]::get_parameter_names();
            $params = [];
            foreach ($parameter_names as $param_name) {
                $params[$param_name] = $r->$param_name;
            }
            $i->set_parameters($params);
            $item_parameters->add($i);
        }

        return $item_parameters;
    }

    /**
     * Return Iterator.
     *
     * @return Traversable
     * 
     */
    public function getIterator(): Traversable {
        return new ArrayIterator($this->item_params);
    }

    /**
     * Add a parameter
     *
     * @param model_item_param $item_param
     * 
     * @return void
     * 
     */
    public function add(model_item_param $item_param) {
        $this->item_params[$item_param->get_id()] = $item_param;
    }

    /**
     * Set parameter by offset.
     *
     * @param mixed $offset
     * @param mixed $value
     * 
     * @return void
     * 
     */
    public function offsetSet($offset, $value): void {
        if (is_null($offset)) {
            $this->item_params[] = $value;
        } else {
            $this->item_params[$offset] = $value;
        }
    }

    /**
     * Check if offset exists.
     *
     * @param mixed $offset
     * 
     * @return bool
     * 
     */
    public function offsetExists($offset): bool {
        return isset($this->item_params[$offset]);
    }

    /**
     * Unset offse.
     *
     * @param mixed $offset
     * 
     * @return void
     * 
     */
    public function offsetUnset($offset): void {
        unset($this->item_params[$offset]);
    }

    /**
     * Return parameter by offset.
     *
     * @param mixed $offset
     * 
     * @return model_item_param|null
     * 
     */
    public function offsetGet($offset): ?model_item_param {
        return isset($this->item_params[$offset]) ? $this->item_params[$offset] : null;
    }

    /**
     * Returns the item difficulties as a float array.
     *
     * @param bool $sorted
     * 
     * @return array
     * 
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
            return is_finite($a) && abs($a) != model_item_param::MAX;
        });
        if ($sorted) {
            sort($data);
        }
        return $data;
    }

    /**
     * Return parameters as array.
     *
     * @return array
     * 
     */
    public function as_array(): array
    {
        $data = [];
        foreach ($this->item_params as $i) {
            $data[$i->get_id()] = $i;
        }
        return $data;
    }

    /**
     * Save parameters to DB.
     *
     * @param int $contextid
     * 
     * @return void
     * 
     */
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
                $record = [
                    'componentid' => $param->get_id(),
                    'componentname' => 'question',
                    'model' => $param->get_model_name(),
                    'contextid' => $contextid,
                    'status' => $param->get_status(),
                ];
                foreach ($param->get_params_array() as $param_name => $value) {
                    if (abs($value) > model_item_param::MAX) {
                        $value = $value < 0 ? model_item_param::MIN : model_item_param::MAX;
                    }
                    $record[$param_name] = $value;
                }

                return $record;
            },
            $this->item_params
        );

        $updated_records = [];
        $new_records = [];
        $now = time();
        $models = model_strategy::get_installed_models();
        foreach ($records as $record) {
            // Do not save or update items that have a NAN as one of their
            // parameter's values
            $parameter_names = $models[$record['model']]::get_parameter_names();
            foreach ($parameter_names as $parameter_name) {
                if (is_nan($record[$parameter_name])) {
                    continue;
                }
            }

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

    /**
     * Check if record exists to update, if not, insert.
     * Some logic to provide the correct values if missing.
     * @param array $newrecord
     * @return int
     * @throws dml_exception
     */
    public static function save_or_update_testitem_in_db(array $newrecord) {
        global $DB;

        $record = $DB->get_record("local_catquiz_itemparams", [
            'componentid' => $newrecord['componentid'],
            'componentname' => $newrecord['componentname'],
            'model' => $newrecord['model'],
        ]);
        $now = time();
        $newrecord['timemodified'] = empty($newrecord['timemodified']) ? $now : $newrecord['timemodified'];
        if (isset($record->timecreated) && $record->timecreated != "0") {
            $newrecord['timecreated'] = !empty($newrecord['timecreated']) ? $newrecord['timecreated'] : $record->timecreated;
        } else {
            $newrecord['timecreated'] = !empty($newrecord['timecreated']) ? $newrecord['timecreated'] : $now;
        }

        $newrecord['status'] = !empty($newrecord['status']) ? $newrecord['status'] : model_item_param::STATUS_UPDATED_MANUALLY;

        if (!$record) {
            // Make sure the record to insert has no id.
            unset($newrecord['id']);
            $id = $DB->insert_record('local_catquiz_itemparams', $newrecord);
        } else {

            $newrecord['id'] = $record->id;
            if ($DB->update_record('local_catquiz_itemparams', $newrecord, true)) {
                $id = $newrecord['id'];
            }
        }
        return $id;
    }

}
