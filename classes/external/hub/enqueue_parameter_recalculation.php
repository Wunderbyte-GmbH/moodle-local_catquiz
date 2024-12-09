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
 * External API for recalculating remote item parameters.
 *
 * @package    local_catquiz
 * @copyright  2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\external\hub;

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use core\task\manager;
use local_catquiz\task\adhoc_recalculate_remote_item_parameters;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * External API class for recalculating remote item parameters.
 */
class enqueue_parameter_recalculation extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'scaleid' => new external_value(PARAM_INT, 'Scale ID'),
        ]);
    }

    /**
     * Queues an adhoc task to recalculate remote item parameters.
     *
     * @param int $scaleid The ID of the scale to recalculate
     * @return array Array containing success status and message
     */
    public static function execute(int $scaleid): array {
        global $USER;
        self::validate_parameters(self::execute_parameters(), ['scaleid' => $scaleid]);

        $task = new adhoc_recalculate_remote_item_parameters();
        $task->set_custom_data(['scaleid' => $scaleid, 'userid' => $USER->id]);
        manager::queue_adhoc_task($task);

        return [
            'success' => true,
            'message' => get_string('taskqueued', 'local_catquiz'),
        ];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Task queued successfully'),
            'message' => new external_value(PARAM_TEXT, 'Status message'),
        ]);
    }
}
