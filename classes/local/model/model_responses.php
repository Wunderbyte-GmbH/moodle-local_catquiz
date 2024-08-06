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
     * @var mixed data
     */
    private $data;

    /**
     *
     * @var array $canbecalculated True for a componentid with correct and
     * incorrect answers.
     */
    private array $canbecalculated = [];

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
        $userids = array_keys($this->data);
        $questionids = [];

        foreach ($userids as $userid) {
            $components = [];
            $components = array_merge($components, array_keys($this->data[$userid]));
            foreach ($components as $component) {
                $questionids = array_merge(
                    $questionids, array_keys($this->data[$userid][$component])
                );
            }
        }

        return array_unique($questionids);
    }

    /**
     * Create item form array.
     *
     * @param array $data
     *
     * @return mixed
     *
     */
    public static function create_from_array(array $data) {
        return (new self)->setData($data);
    }

    /**
     * Helper function to get the person IDs from the response.
     *
     * @return array
     *
     */
    public function get_user_ids(): array {
        return array_keys($this->data);
    }

    /**
     * Returns an array of arrays of item responses indexed by question id.
     * So for each question ID, there is an array of model_item_response entries
     *
     * @param model_person_param_list $personparamlist
     *
     * @return array
     *
     */
    public function get_item_response(model_person_param_list $personparamlist): array {
        $itemresponse = [];

        // Restructure the data
        // From:
        // $data[PERSONID] -> [All responses to different items by this user]
        // To:
        // $data[QUESTIONID] -> [All responses to this question by different users].
        foreach ($personparamlist->get_person_params() as $pp) {
            $components = [];
            if (!array_key_exists($pp->get_userid(), $this->data)) {
                continue;
            }
            $responsesbyuser = $this->data[$pp->get_userid()];
            $components = array_merge($components, $responsesbyuser);
            foreach (array_keys($components) as $component) {
                $questionids = array_keys($this->data[$pp->get_userid()][$component]);
                foreach ($questionids as $questionid) {
                    $fraction = $this->data[$pp->get_userid()][$component][$questionid]['fraction'];
                    $itemresponse[$questionid][] = new model_item_response(
                        $fraction, $pp
                    );
                }
            }
        }
        return $itemresponse;
    }

    /**
     * Set item data.
     *
     * @param mixed $data
     * @param bool $filter
     *
     * @return mixed
     *
     */
    public function setdata($data, bool $filter = true) {
        $this->data = $data;
        if (! $filter) {
            return $this;
        }

        $hascorrectanswer = [];
        $haswronganswer = [];
        foreach ($this->data as $userid => $components) {
            foreach ($components as $component) {

                // If the user has only correct or incorrect answers, no ability can
                // be caluclated and the user will be filtered out.
                $sumoffractions = array_reduce(
                    $component,
                    fn ($carry, $item) => $carry + floatval($item['fraction']),
                    0.0
                );
                if (
                    $sumoffractions === 0.0
                    || intval($sumoffractions) === count($component)
                ) {
                    $this->removeuser($userid);
                    continue;
                }

                foreach ($component as $componentid => $results) {
                    if (floatval($results['fraction']) === 1.0) { // TODO: might need to be updated.
                        $hascorrectanswer[] = $componentid;
                    } else if (floatval($results['fraction']) === 0.0) {
                        $haswronganswer[] = $componentid;
                    }
                }
            }
        }

        // Filter out items that do not have a single correct answer.
        $hascorrectanswer = array_unique($hascorrectanswer);
        $haswronganswer = array_unique($haswronganswer);
        $this->canbecalculated = array_intersect($hascorrectanswer, $haswronganswer);
        $this->excludeditems = array_diff(
            array_unique(array_merge($hascorrectanswer, $haswronganswer)),
            $this->canbecalculated
        );

        foreach ($this->data as $userid => $components) {
            foreach ($components as $componentname => $component) {
                foreach (array_keys($component) as $componentid) {
                    if (!in_array($componentid, $this->canbecalculated)) {
                        unset($this->data[$userid][$componentname][$componentid]);

                        // If that was the only question in that component, remove the component.
                        if (count($this->data[$userid][$componentname]) === 0) {
                            unset($this->data[$userid][$componentname]);

                            // If there are no data left for this user, remove that entry.
                            if (count($this->data[$userid]) === 0) {
                                $this->removeuser($userid);
                            }
                        }
                    }
                }
            }
        }
        $this->data = $data;

        return $this;
    }

    /**
     * Return item as_array.
     *
     * @return array
     *
     */
    public function as_array() {
        return (array) $this->data;
    }

    /**
     * Returns the person params from all persons that are found in this responses object.
     * The ability is initialized to 0.
     *
     * @return model_person_param_list
     */
    public function get_initial_person_abilities() {
        $personparamlist = new model_person_param_list();
        foreach (array_keys($this->as_array()) as $userid) {
            $p = new model_person_param($userid);
            $personparamlist->add($p);
        }
        return $personparamlist;
    }

    /**
     * Get item response for person.
     *
     * @param int $itemid
     * @param int $personid
     *
     * @return mixed
     *
     */
    public function get_item_response_for_person(int $itemid, int $personid) {
        if (empty($this->data[$personid]['component'][$itemid])) {
            return null;
        }
        return floatval($this->data[$personid]['component'][$itemid]['fraction']);
    }

    /**
     * Returns the last response of the given user.
     *
     * @param int $userid
     * @return ?mixed
     */
    public function get_last_response(int $userid) {
        if (! array_key_exists($userid, $this->data)
            || ! array_key_exists('component', $this->data[$userid])
        ) {
            return null;
        }

        $responsesbyuser = $this->data[$userid]['component'];
        return $responsesbyuser[array_key_last($responsesbyuser)];
    }

    /**
     * Returns the itemids for which no difficulty can be calculated.
     * @return array
     */
    public function get_excluded_items() {
        return $this->excludeditems;
    }

    /**
     * Returns the userids for which no ability can be calculated.
     * @return array
     */
    public function get_excluded_users() {
        return $this->excludedusers;
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
     * Removes the user and associated responses.
     *
     * @param string $userid
     *
     * @return void
     */
    private function removeuser(string $userid): void {
        unset($this->data[$userid]);
        $this->excludedusers[] = $userid;
    }
}
