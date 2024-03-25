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
 * Class model_person_ability_estimator_catcalc.
 *
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\local\model;

use core_plugin_manager;
use local_catquiz\catcalc;
use local_catquiz\local\model\model_item_param_list;

/**
 * This class uses the catcalc class to estimate person abilities
 *
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class model_person_ability_estimator_catcalc extends model_person_ability_estimator {

    /**
     * Get person abilities.
     *
     * @param model_item_param_list $itemparamlist
     *
     * @return model_person_param_list
     *
     */
    public function get_person_abilities(model_item_param_list $itemparamlist): model_person_param_list {
        $personparamlist = new model_person_param_list();
        $responses = $this->responses->as_array();
        foreach ($responses as $userid => $itemresponse) {
            foreach (array_keys($itemresponse) as $component) {
                $ability = catcalc::estimate_person_ability(
                    $itemresponse[$component],
                    $itemparamlist
                );
                $p = new model_person_param($userid);
                $p->set_ability($ability);
                $personparamlist->add($p);
            }
        }
        return $personparamlist;
    }
}
