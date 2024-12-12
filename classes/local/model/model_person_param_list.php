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
 * @copyright 2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\local\model;
use ArrayAccess;
use ArrayIterator;
use Countable;
use dml_exception;
use IteratorAggregate;
use local_catquiz\catquiz;
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
 * @copyright 2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class model_person_param_list implements ArrayAccess, IteratorAggregate, Countable {

    /**
     * @var array<model_person_param>
     */
    private array $personparams;

    /**
     * Model-specific instantiation can go here.
     */
    public function __construct() {
        $this->personparams = [];
    }

    /**
     * Try to load existing person params from the DB.
     * If none are found, it returns an empty list.
     *
     * @param int $contextid
     * @param array $catscaleids
     * @param array $userids
     * @return self
     * @throws dml_exception
     */
    public static function load_from_db(int $contextid, array $catscaleids, array $userids = []): self {
        $personrows = catquiz::get_person_abilities($contextid, $catscaleids, $userids);

        $personabilities = new model_person_param_list();
        foreach ($personrows as $r) {
            $p = new model_person_param($r->userid, $r->catscaleid);
            $p->set_ability($r->ability);
            $personabilities->add($p);
        }

        return $personabilities;
    }

    /**
     * Return count of prarms.
     *
     * @return int
     *
     */
    public function count(): int {
        return count($this->personparams);
    }

    /**
     * Return Iterator object.
     *
     * @return Traversable
     *
     */
    public function getiterator(): Traversable {
        return new ArrayIterator($this->personparams);
    }

    /**
     * Add param.
     *
     * @param model_person_param $personparam
     *
     * @return self
     *
     */
    public function add(model_person_param $personparam) {
        $this->personparams[] = $personparam;
        return $this;
    }

    /**
     * Filters out NAN and unset elements
     *
     * @return \local_catquiz\local\model\model_person_param[]
     */
    public function only_valid() {
        return array_filter($this->personparams, fn($pp) => is_float($pp->get_ability()));
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
            $this->personparams[] = $value;
        } else {
            $this->personparams[$offset] = $value;
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
        return isset($this->personparams[$offset]);
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
        unset($this->personparams[$offset]);
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
        return isset($this->personparams[$offset]) ? $this->personparams[$offset] : null;
    }

    /**
     * Return person params.
     *
     * @return array<model_person_param>
     */
    public function get_person_params(): array {
        return $this->personparams;
    }

    /**
     * Returns the user IDs.
     *
     * @return array
     */
    public function get_user_ids(): array {
        return array_map(fn ($pp) => $pp->get_userid(), $this->personparams);
    }

    /**
     * Returns the person abilities as a float array.
     *
     * @param bool $sorted
     *
     * @return array
     *
     */
    public function get_values($sorted = false): array {
        $data = array_map(
            function (model_person_param $param) {
                return $param->to_array();
            },
            $this->personparams
        );

        $data = array_filter($data, function ($a) {
            return is_finite($a['ability']) && abs($a['ability']) != model_person_param::MODEL_POS_INF;
        });
        if ($sorted) {
            uasort($data, fn($a1, $a2) => $a1['ability'] <=> $a2['ability']);
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
        $existingparamsrows = $DB->get_records(
            'local_catquiz_personparams',
            [
                'contextid' => $contextid,
                'catscaleid' => $catscaleid,
            ]
        );
        $existingparams = [];
        foreach ($existingparamsrows as $r) {
            $existingparams[$r->userid] = $r;
        };

        $records = array_map(
            function ($param) use ($contextid, $catscaleid) {
                $ability = $param->get_ability();
                if (
                    !is_finite($ability)
                    || abs($ability) >= model_person_param::MODEL_POS_INF
                ) {
                    $updatedability = $ability < 0
                        ? model_person_param::MODEL_NEG_INF
                        : model_person_param::MODEL_POS_INF;
                    $param->set_ability($updatedability);
                }
                return [
                    'userid' => $param->get_userid(),
                    'ability' => $param->get_ability(),
                    'contextid' => $contextid,
                    'catscaleid' => $catscaleid,
                ];
            },
            $this->personparams
        );

        $updatedrecords = [];
        $newrecords = [];
        $now = time();
        foreach ($records as $record) {
            $isexistingparam = array_key_exists($record['userid'], $existingparams);
            // If record already exists, update it. Otherwise, insert a new record to the DB.
            if ($isexistingparam) {
                $record['id'] = $existingparams[$record['userid']]->id;
                $record['timemodified'] = $now;
                $updatedrecords[] = $record;
            } else {
                $record['timecreated'] = $now;
                $record['timemodified'] = $now;
                $newrecords[] = $record;
            }
        }

        if (!empty($newrecords)) {
            $DB->insert_records('local_catquiz_personparams', $newrecords);
        }

        foreach ($updatedrecords as $r) {
            $DB->update_record('local_catquiz_personparams', $r, true);
        }
    }

    /**
     * If any of the given userids are missing, default parameters are added
     *
     * @param array $userids
     * @param int $catscaleid
     * @return void
     */
    public function add_missing_users(array $userids, int $catscaleid) {
        $existingusers = $this->get_user_ids();
        $newusers = array_diff(
            $userids,
            $existingusers
        );
        foreach ($newusers as $userid) {
            $this->add(new model_person_param($userid, $catscaleid));
        }
    }

    /**
     * Returns the list filtered to the given user ID.
     *
     * @param string $userid
     * @return self
     */
    public function get_for_user(string $userid): self {
        return $this->filter(fn ($pp) => $pp->get_userid() === $userid);
    }

    /**
     * Filter the person params with the given function
     *
     * The function is called with each person param. If it returns true, the value is kept.
     *
     * @param callable $fun
     * @return self
     */
    public function filter(callable $fun) {
        $filtered = new self();
        foreach ($this->personparams as $pp) {
            if (!$fun($pp)) {
                continue;
            }
            $filtered->add($pp);
        }
        return $filtered;
    }

    /**
     * Returns the first element in the list of personparams.
     *
     * @return model_person_param
     */
    public function first(): model_person_param {
        return reset($this->personparams);
    }
}
