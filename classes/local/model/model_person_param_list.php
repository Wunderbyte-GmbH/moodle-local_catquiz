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
use local_catquiz\local\model\model_person_param;
use Traversable;

/**
 * This class holds a list of person param objects.
 *
 * Can also be used to set values specific to the parameter estimation (e.g. model).
 *
 * This is one of the return values from a model param estimation.
 * 
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class model_person_param_list implements ArrayAccess, IteratorAggregate, Countable {

    /**
     * @var array<model_person_param>
     */
    private array $person_params;

    /**
     * Model-specific instantiation can go here.
     */
    public function __construct() {
        $this->person_params = [];
    }

    /**
     * Try to load existing person params from the DB.
     * If none are found, it returns an empty list.
     *
     * @param int $contextid
     * @param int $catscaleid
     * @return model_person_param_list
     * @throws dml_exception
     */
    public static function load_from_db(int $contextid, int $catscaleid): self {
        global $DB;
        $person_rows = $DB->get_records(
            'local_catquiz_personparams',
            [
                'contextid' => $contextid,
                'catscaleid' => $catscaleid,
            ]
        );

        $person_abilities = new model_person_param_list();
        foreach ($person_rows as $r) {
            $p = new model_person_param($r->userid);
            $p->set_ability($r->ability);
            $person_abilities->add($p);
        }

        return $person_abilities;
    }

    /**
     * Return count of prarms.
     *
     * @return int
     * 
     */
    public function count(): int {
        return count($this->person_params);
    }

    /**
     * Return Iterator object.
     *
     * @return Traversable
     * 
     */
    public function getIterator(): Traversable {
        return new ArrayIterator($this->person_params);
    }

    /**
     * Add param.
     *
     * @param model_person_param $person_param
     * 
     * @return void
     * 
     */
    public function add(model_person_param $person_param) {
        $this->person_params[$person_param->get_id()] = $person_param;
    }

    /**
     * Set param by offset.
     *
     * @param mixed $offset
     * @param mixed $value
     * 
     * @return void
     * 
     */
    public function offsetSet($offset, $value): void {
        if (is_null($offset)) {
            $this->person_params[] = $value;
        } else {
            $this->person_params[$offset] = $value;
        }
    }

    /**
     * Chek if param by offset exists.
     *
     * @param mixed $offset
     * 
     * @return bool
     * 
     */
    public function offsetExists($offset): bool {
        return isset($this->person_params[$offset]);
    }

    /**
     * Unset param by offset.
     *
     * @param mixed $offset
     * 
     * @return void
     * 
     */
    public function offsetUnset($offset): void {
        unset($this->person_params[$offset]);
    }

    /**
     * REturn param by offset.
     *
     * @param mixed $offset
     * 
     * @return model_person_param|null
     * 
     */
    public function offsetGet($offset): ?model_person_param {
        return isset($this->person_params[$offset]) ? $this->person_params[$offset] : null;
    }

    /**
     * Return person params.
     * 
     * @return array<model_person_param>
     */
    public function get_person_params(): array {
        return $this->person_params;
    }

    /**
     * Returns the person abilities as a float array.
     *
     * @param bool $sorted
     * 
     * @return array
     * 
     */
    public function get_values($sorted = false): array
    {
        $data = array_map(
            function (model_person_param $param) {
                return $param->get_ability();
            },
            $this->person_params
        );

        $data = array_filter($data, function ($a) {
            return is_finite($a) && abs($a) != model_person_param::MODEL_POS_INF;
        });
        if ($sorted) {
            sort($data);
        }
        return $data;
    }

    /**
     * Save params to DB.
     *
     * @param int $contextid
     * @param int $catscaleid
     * 
     * @return void
     * 
     */
    public function save_to_db(int $contextid, int $catscaleid) {
        global $DB;
        // Get existing records for the given contextid and model.
        $existing_params_rows = $DB->get_records(
            'local_catquiz_personparams',
            [
                'contextid' => $contextid,
                'catscaleid' => $catscaleid,
            ]
        );
        $existing_params = [];
        foreach ($existing_params_rows as $r) {
            $existing_params[$r->userid] = $r;
        };

        $records = array_map(
            function ($param) use ($contextid, $catscaleid) {
                $ability = $param->get_ability();
                if (
                    !is_finite($ability)
                    || abs($ability) >= model_person_param::MODEL_POS_INF
                ) {
                    $updated_ability = $ability < 0
                        ? model_person_param::MODEL_NEG_INF
                        : model_person_param::MODEL_POS_INF;
                    $param->set_ability($updated_ability);
                }
                return [
                    'userid' => $param->get_id(),
                    'ability' => $param->get_ability(),
                    'contextid' => $contextid,
                    'catscaleid' => $catscaleid,
                ];
            },
            $this->person_params
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
}
