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

use core_plugin_manager;
use local_catquiz\catcalc;
use local_catquiz\local\model\model_item_param_list;

defined('MOODLE_INTERNAL') || die();

/**
 * A demo class to estimate person abilities
 *
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class model_person_ability_estimator_demo extends model_person_ability_estimator {

    public function get_person_abilities(model_item_param_list $item_param_list): model_person_param_list
    {
        $models = $this->create_installed_models();
        $person_param_list = new model_person_param_list();
        $responses = $this->responses->as_array();
        foreach ($responses as $userid => $item_response) {
            // Just a demo
            foreach ($item_param_list as $item) {
                $gradient = $models[$item->get_model_name()]->get_gradient();
            }
            foreach(array_keys($item_response) as $component) {
                $ability = catcalc::estimate_person_ability(
                    $item_response[$component],
                    $item_param_list
                );
                $p = new model_person_param($userid);
                $p->set_ability($ability);
                $person_param_list->add($p);
            }
        }
        return $person_param_list;
    }

    private function create_installed_models(): array {
        /**
         * @var array<model_model>
         */
        $instances = [];

        foreach (self::get_installed_models() as $name => $classname) {
            $modelclass = new $classname($this->responses, $name);
            $instances[$name] = $modelclass;
        }
        return $instances;
    }

    private static function get_installed_models(): array {
        $pm = core_plugin_manager::instance();
        $models = [];
        foreach($pm->get_plugins_of_type('catmodel') as $name => $info) {
                $classname = sprintf('catmodel_%s\%s', $name, $name);
                if (!class_exists($classname)) {
                    continue;
                }
                $models[$name] = $classname;
        }
        return $models;
    }
}