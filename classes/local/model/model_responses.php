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

use local_catquiz\catquiz;
use local_catquiz\catscale;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_item_response;
use local_catquiz\local\model\model_person_param_list;
use stdClass;

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
     * @var array $byattempt holds the responses indexed by attempt ID
     */
    private array $byattempt = [];

    /**
     * @var array $sumbyperson Sum of responses indexed by person IDs.
     */
    private array $sumbyperson = [];

    /**
     * @var array $sumbyitem Sum of responses indexed by item IDs.
     */
    private $sumbyitem = [];

    /**
     * @var array $sumbyattempt Sum of responses indexed by attempt ID.
     */
    private $sumbyattempt = [];

    /**
     * @var array $excludeditems Componentids of items that can not be calculated.
     */
    private array $excludeditems = [];

    /**
     * @var array $excludedusers Userids of users for which no ability can be calculated.
     */
    private array $excludedusers = [];


    /**
     * @var array $excludedattempts Attempt IDs of attempts for which no ability can be calculated.
     */
    private array $excludedattempts = [];

    /**
     * For each user, we have exactly one personparameter.
     *
     * @var array $personparams This array is indexed by userid.
     */
    private array $personparams = [];

    /**
     * Caches the responses object limited to a set of users.
     *
     * @var array
     */
    private $userlimitcache = [];

    /**
     * Keeps a mapping of userids to attempt IDs.
     *
     * @var array
     */
    private $userattempts = [];

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
     * Gets all the data from the given context ID.
     * @param int $contextid
     * @return \local_catquiz\local\model\model_responses
     */
    public static function create_for_context(int $contextid): self {
        $mainscale = catquiz::get_main_scale($contextid);
        $catscaleids = [$mainscale->id, ...catscale::get_subscale_ids($mainscale->id)];
        $responsedata = self::getresponsedatafromdb($contextid, $catscaleids);
        return self::create_from_array($responsedata, $mainscale->id);
    }

    /**
     * Create from array.
     *
     * @param array $data
     * @param int $mainscale
     *
     * @return self
     *
     */
    public static function create_from_array(array $data, int $mainscale = 0): self {
        $object = new self();
        foreach ($data as $attemptid => $components) {
            foreach ($components as $component) {
                foreach ($component as $componentid => $results) {
                    if (!$results) {
                        continue;
                    }
                    $object->set($attemptid, $componentid, $results['fraction'], null, $mainscale);
                    $object->map_attempt($results['userid'], $attemptid);
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
     * @param array $personids Array of person IDs.
     * @param bool $clone If true, do not modify the existing object but return a copy instead.
     *
     * @return self
     */
    public function limit_to_users(array $personids, bool $clone = false): self {
        // Create a cache key from the personids.
        $cachekey = md5(serialize($personids));

        if (isset($this->userlimitcache[$cachekey])) {
            if ($clone) {
                return clone $this->userlimitcache[$cachekey];
            }
            return $this->userlimitcache[$cachekey];
        }

        // Instead of modifying the existing object, create a copy and modify that.
        if ($clone) {
            $copy = clone($this);
            return $copy->limit_to_users($personids);
        }

        // Convert personids array to hash map for O(1) lookup.
        $personidsmap = array_flip($personids);

        $this->byperson = array_intersect_key($this->byperson, $personidsmap);
        $this->sumbyperson = array_intersect_key($this->sumbyperson, $personidsmap);

        foreach ($this->byitem as $itemid => $responses) {
            $this->byitem[$itemid] = array_intersect_key($responses, $personidsmap);
            $this->recalculate_item_sum($itemid);

            // Remove items that have no responses for the limited list of users.
            // or only correct responses.
            if (
                $this->sumbyitem[$itemid] == 0.0
                || $this->sumbyitem[$itemid] == count($this->byitem[$itemid])
            ) {
                unset($this->byitem[$itemid]);
                unset($this->sumbyitem[$itemid]);
            }
        }

        // Store the result in cache.
        $this->userlimitcache[$cachekey] = $this;

        return $this;
    }

    /**
     * Remove responses from items that are not in the given list
     *
     * @param array $itemids Array of item IDs.
     * @param bool $clone If true, do not modify the existing object but return a copy instead.
     *
     * @return self
     */
    public function limit_to_items(array $itemids, bool $clone = false): self {
        // Instead of modifying the existing object, create a copy and modify that.
        if ($clone) {
            $copy = clone($this);
            return $copy->limit_to_items($itemids);
        }

        $this->byitem = array_filter($this->byitem, fn ($iid) => in_array($iid, $itemids), ARRAY_FILTER_USE_KEY);
        $this->sumbyitem = array_filter($this->sumbyitem, fn ($iid) => in_array($iid, $itemids), ARRAY_FILTER_USE_KEY);

        foreach ($this->byperson as $pid => $responses) {
            $this->byperson[$pid] = array_filter($responses, fn ($iid) => in_array($iid, $itemids), ARRAY_FILTER_USE_KEY);
            $this->recalculate_person_sum($pid);

            // Remove persons that have no responses for the limited list of items.
            if ($this->sumbyperson[$pid] == 0.0) {
                unset($this->byperson[$pid]);
                unset($this->sumbyperson[$pid]);
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
     * Add a new response
     *
     * @param string $attemptid
     * @param string $itemid
     * @param float $response
     * @param ?model_person_param $personparam
     * @param int $catscaleid
     * @return void
     */
    public function set(
        string $attemptid,
        string $itemid,
        float $response,
        ?model_person_param $personparam = null,
        int $catscaleid = 0
    ) {
        $oldresponse = 0.0;
        if (!empty($this->byitem[$itemid][$attemptid])) {
            $oldresponse = $this->byitem[$itemid][$attemptid]->get_response();
        }

        if (!$personparam) {
            $personparam = new model_person_param($attemptid, $catscaleid);
        }

        if (!array_key_exists($attemptid, $this->personparams)) {
            $this->personparams[$attemptid] = $personparam;
        }

        $newresponse = new model_item_response($itemid, $response, $this->personparams[$attemptid]);
        $this->byperson[$attemptid][$itemid] = $newresponse;
        $this->sumbyperson[$attemptid] = array_key_exists($attemptid, $this->sumbyperson)
            ? $this->sumbyperson[$attemptid] + ($response - $oldresponse)
            : $response;
        $this->byattempt[$attemptid][$itemid] = $newresponse;
        $this->sumbyattempt[$attemptid] = array_key_exists($attemptid, $this->sumbyattempt)
            ? $this->sumbyattempt[$attemptid] + ($response - $oldresponse)
            : $response;
        $this->byitem[$itemid][$attemptid] = $newresponse;
        $this->sumbyitem[$itemid] = array_key_exists($itemid, $this->sumbyitem)
            ? $this->sumbyitem[$itemid] + ($response - $oldresponse)
            : $response;

        // If the user has only correct or incorrect answers and is not excluded -> exclude.
        $excludeuser = $this->sumbyperson[$attemptid] == 0 || $this->sumbyperson[$attemptid] == count($this->byperson[$attemptid]);
        if ($excludeuser) {
            $this->excludedusers[$attemptid] = true;
        } else {
            unset($this->excludedusers[$attemptid]);
        }

        // If the attempt has only correct or incorrect answers and is not excluded -> exclude.
        $excludeattempt = $this->sumbyattempt[$attemptid] == 0
            || $this->sumbyattempt[$attemptid] == count($this->byattempt[$attemptid]);
        if ($excludeattempt) {
            $this->excludedattempts[$attemptid] = true;
        } else {
            unset($this->excludedattempts[$attemptid]);
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
    public function get_person_abilities() {
        $personparamlist = new model_person_param_list();
        foreach ($this->byperson as $userresponses) {
            // All responses point to the same personparam. So we can just take the first one.
            $pp = reset($userresponses)->get_personparams();
            $personparamlist->add($pp);
        }
        return $personparamlist;
    }

    /**
     * Set person abilities from the given list
     *
     * @param model_person_param_list $pplist
     * @param bool $byattempt If set to true, index the stored person params by attemptid instead of userid.
     * @return model_responses
     */
    public function set_person_abilities(model_person_param_list $pplist, bool $byattempt = false): self {
        foreach ($pplist as $pp) {
            if ($byattempt) {
                foreach ($this->userattempts[$pp->get_userid()] as $attemptid) {
                    $this->personparams[$attemptid]->set_ability($pp->get_ability());
                }
            } else {
                $this->personparams[$pp->get_userid()]->set_ability($pp->get_ability());
            }
        }
        return $this;
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
     * Returns the last response of the given attempt.
     *
     * @param int $attemptid
     * @return ?mixed
     */
    public function get_last_response(int $attemptid) {
        if (
            !array_key_exists($attemptid, $this->byattempt)
        ) {
            return null;
        }

        return $this->byattempt[$attemptid][array_key_last($this->byattempt[$attemptid])];
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
        $personparams = $this->get_personparams($contextid, $catscaleids);
        foreach (array_keys(model_strategy::get_installed_models()) as $model) {
            $itemparamlists[$model] = model_item_param_list::get(
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

    /**
     * Returns the person parameters for the stored responses
     *
     * @param int $contextid
     * @param array $catscaleids
     * @return model_person_param_list
     */
    private function get_personparams(int $contextid, array $catscaleids): model_person_param_list {
        if (!$this->personparams) {
            $this->personparams = model_person_param_list::load_from_db($contextid, [], $this->get_user_ids());
        }
        $filterfun = function (model_person_param $pp) use ($catscaleids) {
            return in_array($pp->get_catscaleid(), $catscaleids);
        };
        $result = new model_person_param_list();
        $filtered = array_filter($this->personparams, $filterfun);
        foreach ($filtered as $pp) {
            $result->add($pp);
        }
        return $result;
    }

    /**
     * Returns the user IDs of the stored responses.
     *
     * @return array
     */
    public function get_user_ids(): array {
        return array_keys($this->byperson);
    }

    /**
     * Create response from DB.
     *
     * @param int $contextid
     * @param array $catscaleids
     * @param int|null $testitemid
     * @param int|null $userid
     *
     * @return array
     *
     */
    private static function getresponsedatafromdb(
        int $contextid,
        array $catscaleids,
        ?int $testitemid = null,
        ?int $userid = null
    ): array {
        global $DB;

        list($sql, $params) = catquiz::get_sql_for_model_input($contextid, $catscaleids, $testitemid, $userid);
        $data = $DB->get_records_sql($sql, $params);
        $inputdata = self::db_to_modelinput($data);
        return $inputdata;
    }

    private function map_attempt(int $userid, int $attemptid): self {
        if (!array_key_exists($userid, $this->userattempts)) {
            $this->userattempts[$userid] = [$attemptid => $attemptid];
            return $this;
        }

        $this->userattempts[$userid][$attemptid] = $attemptid;
        return $this;
    }

    /**
     * Returns data in the following format
     *
     * "1" => Array( // Attemptid
     *     "comp1" => Array( // component
     *         "1" => Array( //questionid
     *             "fraction" => 0,
     *             "max_fraction" => 1,
     *             "min_fraction" => 0,
     *             "qtype" => "truefalse",
     *             "timestamp" => 1646955326,
     *             "userid" => 2,
     *         ),
     *         "2" => Array(
     *             "fraction" => 0,
     *             "max_fraction" => 1,
     *             "min_fraction" => 0,
     *             "qtype" => "truefalse",
     *             "timestamp" => 1646955332
     *             "userid" => 2,
     *         ),
     *         "3" => Array(
     *             "fraction" => 1,
     *             "max_fraction" => 1,
     *             "min_fraction" => 0,
     *             "qtype" => "truefalse",
     *             "timestamp" => 1646955338
     *             "userid" => 2,
     *
     * @param mixed $data
     *
     * @return array
     */
    private static function db_to_modelinput($data): array {
        $modelinput = [];
        // Check: use only most recent answer for each question.

        foreach ($data as $row) {
            if ($row->state === 'gaveup') {
                $row->fraction = 0.0;
            }
            $entry = [
                'fraction' => $row->fraction,
                'id' => $row->id,
                'userid' => $row->userid,
                'attemptid' => $row->attemptid,
            ];

            if (!array_key_exists($row->attemptid, $modelinput)) {
                $modelinput[$row->attemptid] = ["component" => []];
            }

            if (! array_key_exists($row->questionid, $modelinput[$row->attemptid]['component'])) {
                $modelinput[$row->attemptid]['component'][$row->questionid] = $entry;
                continue;
            }

            // If we are here, there is already an entry. Only update it if this answer is newer than the last one.
            if ($row->id > $modelinput[$row->attemptid]['component'][$row->questionid]['id']) {
                $modelinput[$row->attemptid]['component'][$row->questionid] = $entry;
            }
        }
        return $modelinput;
    }

    /**
     * Returns the object in a format that can be transferred
     *
     * @return stdClass
     */
    public function export_for_transfer(): stdClass {
        return (object) [
        'byperson' => $this->byperson,
        'byitem' => $this->byitem,
        'byattempt' => $this->byattempt,
        'sumbyperson' => $this->sumbyperson,
        'sumbyitem' => $this->sumbyitem,
        'sumbyattempt' => $this->sumbyattempt,
        'excludeditems' => $this->excludeditems,
        'excludedusers' => $this->excludedusers,
        'excludedattempts' => $this->excludedattempts,
        'personparams' => $this->personparams,
        ];
    }

    public static function create_from_transfer(stdClass $transferdata): self {
        // TODO: reconstruct!
        return new self();
    }
}
