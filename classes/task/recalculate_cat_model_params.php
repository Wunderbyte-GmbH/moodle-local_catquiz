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

namespace local_catquiz\task;

use local_catquiz\catmodel_info;

/**
 * Runs through all contexts and recalculates values for all CAT models
 */
class recalculate_cat_model_params extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('task_recalculate_cat_model_params', 'local_catquiz');
    }

    /**
     * Update all model params of all contexts
     * @return void
     */
    public function execute() {
        $cm = new catmodel_info();
        $cm->recalculate_for_all_contexts();
    }
}
