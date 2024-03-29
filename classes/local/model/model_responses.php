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
 * This class facilitates working with responses of a CAT test
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\local\model;

use local_catquiz\catscale;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_item_response;
use local_catquiz\local\model\model_person_param_list;

/**
 * Contains information about a performed quiz or test
 *
 * For example: a list of users performed a test. Objects of this class show how the users performed on this test.
 * E.g.: which users took part, how did they answer the questions, etc.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class model_responses {
    /**
     * @var array $byperson holds the responses indexed by person IDs.
     */
    private array $byperson = [];

    /**
     * @var array $byitem Holds the responses indexed by item IDs.
     */
    private array $byitem = [];

    /**
     * @var array $sumbyperson Sum of responses indexed by person IDs.
     */
    private array $sumbyperson = [];

    /**
     * @var array $sumbyitem Sum of responses indexed by item IDs.
     */
    private $sumbyitem = [];

    /**
     * @var array $excludeditems Componentids of items that can not be calculated.
     */
    private array $excludeditems = [];

    /**
     * @var array $excludedusers Userids of users for which no ability can be calculated.
     */
    private array $excludedusers = [];

    /**
     * Return array of item ids.
     *
     * @return array
     *
     */
    public function get_item_ids(): array {
        return array_keys($this->byitem);
    }

    /**
     * Return the person IDs of the saved responses
     */
    public function get_person_ids(): array {
        return array_keys($this->byperson);
    }

    /**
     * Create from array.
     *
     * @param array $data
     *
     * @return self
     *
     */
    public static function create_from_array(array $data): self {
        $object = new self();
        foreach ($data as $userid => $components) {
            foreach ($components as $component) {
                foreach ($component as $componentid => $results) {
                    $object->set($userid, $componentid, $results['fraction']);
                }
            }
        }

        return $object;
    }

    /**
     * Return the fraction of the item with the given ID.
     *
     * Returns null if that item is not found.
     *
     * @param string $itemid
     *
     * @return ?float
     */
    public function get_item_fraction(string $itemid): ?float {
        if (!array_key_exists($itemid, $this->byitem)) {
            return null;
        }
        return $this->sumbyitem[$itemid] / count($this->byitem[$itemid]);
    }

    /**
     * Remove responses from users that are not in the given list
     *
     * @param array $personids Array if person IDs.
     * @param bool $clone If true, do not modify the existing object but return a copy instead.
     *
     * @return self
     */
    public function limit_to_users(array $personids, bool $clone = false): self {

        // Instead of modifying the existing object, create a copy and modify that.
        if ($clone) {
            $copy = clone($this);
            return $copy->limit_to_users($personids);
        }

        $this->byperson = array_filter($this->byperson, fn ($pid) => in_array($pid, $personids), ARRAY_FILTER_USE_KEY);
        $this->sumbyperson = array_filter($this->sumbyperson, fn ($pid) => in_array($pid, $personids), ARRAY_FILTER_USE_KEY);

        foreach ($personids as $pid) {
            $this->recalculate_person_sum($pid);
        }
        foreach ($this->byitem as $itemid => $responses) {
            $this->byitem[$itemid] = array_filter($responses, fn ($pid) => in_array($pid, $personids), ARRAY_FILTER_USE_KEY);
            $this->recalculate_item_sum($itemid);

            // Remove items that have no responses for the limited list of users.
            if ($this->sumbyitem[$itemid] == 0.0) {
                unset($this->byitem[$itemid]);
                unset($this->sumbyitem[$itemid]);
            }
        }
        return $this;
    }

    /**
     * Returns an array of arrays of item responses indexed by question id.
     * So for each question ID, there is an array of model_item_response entries
     *
     * @return array
     */
    public function get_item_response(): array {
        return $this->byitem;
    }

    /**
     * Returns the responses for the user with the given person ID.
     *
     * If the person is not found, returns null.
     *
     * @param string $personid
     *
     * @return ?array
     */
    public function get_for_user(string $personid): ?array {
        return $this->byperson[$personid] ?? null;
    }

    /**
     * Add a new user response
     *
     * @param string $personid
     * @param string $itemid
     * @param float $response
     * @param ?float $ability
     * @param string $component
     * @return void
     */
    public function set(string $personid, string $itemid, float $response, ?float $ability = null, string $component = 'question') {
        $oldresponse = 0.0;
        if (!empty($this->byitem[$itemid][$personid])) {
            $oldresponse = $this->byitem[$itemid][$personid]->get_response();
        }

        $personparam = new model_person_param($personid);
        if ($ability) {
            $personparam->set_ability($ability);
        }

        $newresponse = new model_item_response($itemid, $response, $personparam);
        $this->byperson[$personid][$itemid] = $newresponse;
        $this->sumbyperson[$personid] = array_key_exists($personid, $this->sumbyperson)
            ? $this->sumbyperson[$personid] + ($response - $oldresponse)
            : $response;
        $this->byitem[$itemid][$personid] = $newresponse;
        $this->sumbyitem[$itemid] = array_key_exists($itemid, $this->sumbyitem)
            ? $this->sumbyitem[$itemid] + ($response - $oldresponse)
            : $response;
        // If the user has only correct or incorrect answers and is not excluded -> exclude.
        $excludeuser = $this->sumbyperson[$personid] == 0 || $this->sumbyperson[$personid] == count($this->byperson[$personid]);
        if ($excludeuser) {
            $this->excludedusers[$personid] = true;
        } else {
            unset($this->excludedusers[$personid]);
        }

        // If the item has only correct or incorrect answers and is not excluded -> exclude.
        $itemsum = $this->sumbyitem[$itemid];
        $excludeitem = $itemsum == 0 || $itemsum == count($this->byitem[$itemid]);
        if ($excludeitem) {
            $this->excludeditems[$itemid] = true;
        } else {
            unset($this->excludeditems[$itemid]);
        }
    }

    /**
     * Returns the person params from all persons that are found in this responses object.
     * The ability is initialized to 0.
     *
     * @return model_person_param_list
     */
    public function get_initial_person_abilities() {
        $personparamlist = new model_person_param_list();
        foreach (array_keys($this->byperson) as $userid) {
            $p = new model_person_param($userid);
            $personparamlist->add($p);
        }
        return $personparamlist;
    }

    /**
     * Get item response for person.
     *
     * @param string $itemid
     * @param string $personid
     *
     * @return ?float
     *
     */
    public function get_item_response_for_person(string $itemid, string $personid) {
        if (empty($this->byperson[$personid][$itemid])) {
            return null;
        }
        return $this->byperson[$personid][$itemid]->get_response();
    }

    /**
     * Returns the last response of the given user.
     *
     * @param int $userid
     * @return ?mixed
     */
    public function get_last_response(int $userid) {
        if (! array_key_exists($userid, $this->byperson)
        ) {
            return null;
        }

        return $this->byperson[$userid][array_key_last($this->byperson[$userid])];
    }

    /**
     * Get item param list.
     *
     * @param int $catscaleid
     * @param int $contextid
     *
     * @return model_item_param_list
     */
    public function get_items_for_scale(int $catscaleid, int $contextid): model_item_param_list {
        $modelstrategy = new model_strategy($this);
        $catscalecontext = catscale::get_context_id($catscaleid);
        $catscaleids = [
            $catscaleid,
            ...catscale::get_subscale_ids($catscaleid),
        ];
        $itemparamlists = [];
        $personparams = model_person_param_list::load_from_db($contextid, $catscaleids);
        foreach (array_keys($modelstrategy->get_installed_models()) as $model) {
            $itemparamlists[$model] = model_item_param_list::load_from_db(
                $catscalecontext,
                $model,
                $catscaleids
            );
        }
        $itemparamlist = $modelstrategy->select_item_model($itemparamlists, $personparams);
        return $itemparamlist;
    }

    /**
     * Recalculate the internal sumbyitem counter for the item with the given ID.
     *
     * @param string $itemid
     *
     * @return void
     */
    private function recalculate_item_sum(string $itemid) {
        $this->sumbyitem[$itemid] = array_sum(array_map(fn ($r) => $r->get_response(), $this->byitem[$itemid]));
    }

    /**
     * Recalculate the internal sumbyperson counter for the person with the given ID.
     *
     * @param string $personid
     *
     * @return void
     */
    private function recalculate_person_sum(string $personid) {
        $this->sumbyperson[$personid] = array_sum(array_map(fn ($r) => $r->get_response(), $this->byperson[$personid]));
    }

}
