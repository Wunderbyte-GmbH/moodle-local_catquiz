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

use core\task\manager;
use local_catquiz\catcontext;
use local_catquiz\task\adhoc_recalculate_cat_model_params;
use local_catquiz\task\recalculate_cat_model_params;
use moodle_exception;
use moodle_url;

/**
 * This class
 */
class catmodel_info {

    /**
     *
     * @param int $contextid
     * @param bool $calculate
     * @return array
     */
    public function get_context_parameters( int $contextid = 0, bool $calculate = false) {
        // Trigger calculation in the background but do not wait for it to finish
        if ($calculate) {
            $this->trigger_parameter_calculation($contextid);
        }

        // Return the data that are currently saved in the DB
        $context = catcontext::load_from_db($contextid);
        $strategy = $context->get_strategy();
        return $strategy->get_params_from_db($contextid);
    }

    public function trigger_parameter_calculation($contextid) {
        $adhoc_recalculate_cat_model_params = new adhoc_recalculate_cat_model_params();
        $adhoc_recalculate_cat_model_params->set_custom_data($contextid);
        manager::queue_adhoc_task($adhoc_recalculate_cat_model_params);
    }

    public function update_params($contextid) {
        $context = catcontext::load_from_db($contextid);
        $strategy = $context->get_strategy();
        list($item_difficulties, $person_abilities) =  $strategy->run_estimation();
        foreach ($item_difficulties as $item_param_list) {
            $item_param_list->save_to_db($contextid);
        }
        $person_abilities->save_to_db($contextid);
        $context->save_or_update((object)['timecalculated' => time()]);
    }
}