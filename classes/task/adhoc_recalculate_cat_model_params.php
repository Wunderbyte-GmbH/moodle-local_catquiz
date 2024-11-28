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
 * Class adhoc_recalculate_cat_model_params.
 *
 * @package local_catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\task;

use local_catquiz\catmodel_info;

/**
 * Recalculates item params for the given context.
 *
 * @package local_catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class adhoc_recalculate_cat_model_params extends \core\task\adhoc_task {
    /**
     * Update all model params of the given context
     * @return void
     */
    public function execute() {
        $taskdata = (array) $this->get_custom_data();
        $cmi = new catmodel_info();
        $cmi->update_params($taskdata['contextid'], $taskdata['catscaleid'], $taskdata['userid']);
    }
}
