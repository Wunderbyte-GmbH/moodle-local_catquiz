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
 * 
 *
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\local\model;

use local_catquiz\catcalc;

defined('MOODLE_INTERNAL') || die();

/**
 * A demo class to estimate person abilities
 *
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class model_person_ability_estimator_demo extends model_person_ability_estimator {

    public function get_person_abilities(array $item_param_lists): model_person_param_list
    {
        // Select one set of item params to calculate the person abilities
        $item_param_list = $item_param_lists['raschbirnbauma'];
        $person_param_list = new model_person_param_list();
        foreach ($this->responses->as_array() as $userid => $item_response) {
            $ability = catcalc::estimate_person_ability($item_response['component'], $item_param_list);
            $p = new model_person_param($userid);
            $p->set_ability($ability);
            $person_param_list->add($p);
        }
        return $person_param_list;
    }
}