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
* @author Georg Maißer
* @copyright 2023 Wunderbyte GmbH <info@wunderbyte.at>
* @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

namespace local_catquiz;

use core_plugin_manager;
use Exception;
use local_catquiz\data\catquiz_base;

/**
 * This class
 */
class catmodel_info {


    /**
     * Undocumented function
     *
     * @param integer $catcontext
     * @param integer $testitemid
     * @param string $component
     * @param string $model
     * @return array
     */
    public static function get_item_parameters(
        int $catcontext = 0,
        int $testitemid = 0,
        string $component = '',
        string $model = ''):array {

        $returnarray = [];

        // Retrieve all the responses in the given context.
        $responses = catquiz_base::get_question_results_by_person_withoutid(0, 0, $testitemid);

        // Right now, we need different responses.
        $responses = [
            [0, 1, 1, 0, 1]];

        // Get all Models.
        $models = self::get_installed_models();

        // We run through all the models.
        $classes = [];

        foreach ($models as $model) {
            $classname = 'catmodel_' . $model->name . '\\' . $model->name;

            if (class_exists($classname)) {
                $modelclass = new $classname($responses); // The constructure takes our array of responses.
                $classes[] = $modelclass;
                $itemparams = $modelclass->get_item_parameters([]);
                $returnarray[] = [
                    'modelname' => $model->name,
                    'itemparameters' => $itemparams,
                ];
            }
        }

        return $returnarray;
    }


    /**
     * Returns an array of installed models.
     *
     * @return array
     */
    public static function get_installed_models():array {

        $pm = core_plugin_manager::instance();
        return $pm->get_plugins_of_type('catmodel');
    }

};







