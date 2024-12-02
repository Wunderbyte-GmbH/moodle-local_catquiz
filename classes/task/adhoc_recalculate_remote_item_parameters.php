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

use core\task\adhoc_task;
use local_catquiz\calculator\remote_item_parameter_calculator;

/**
 * Adhoc task to recalculate remote item parameters for specific scales.
 *
 * @package    local_catquiz
 * @copyright  2024 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class adhoc_recalculate_remote_item_parameters extends adhoc_task {

    /**
     * Execute the task.
     */
    public function execute() {
        $data = $this->get_custom_data();
        $scaleid = $data->scaleid ?? null;
        $userid = $data->userid ?? null;

        if (!$scaleid) {
            mtrace('No scale ID provided - aborting.');
            return;
        }

        $calculator = new remote_item_parameter_calculator();
        $calculator->calculate_for_scale($scaleid, $userid);
    }
}
