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
 * Recalculates item params for the given context
 */
class adhoc_recalculate_cat_model_params extends \core\task\adhoc_task {
    /**
     * Update all model params of the given context
     * @return void
     */
    public function execute() {
        $contextid = $this->get_custom_data();
        $cm = new catmodel_info();
        $cm->update_params($contextid);
    }
}
