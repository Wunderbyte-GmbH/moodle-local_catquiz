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

use core\task\scheduled_task;
use local_catquiz\calculator\remote_item_parameter_calculator;
use local_catquiz\catquiz;

/**
 * Task to recalculate item parameters based on remote responses.
 *
 * @package    local_catquiz
 * @copyright  2024 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recalculate_remote_item_parameters extends scheduled_task {
    /**
     * Get a descriptive name for this task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskrecalculateremoteparameters', 'local_catquiz');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        $repo = new catquiz();
        $config = get_config('local_catquiz');
        $labels = array_filter(explode("\n", $config->central_scale_labels ?? ''));
        $scales = $repo->get_scales_by_labels($labels);
        if (empty($scales)) {
            mtrace('No active scales found - nothing to do.');
            return;
        }

        $calculator = new remote_item_parameter_calculator();
        foreach ($scales as $scale) {
            mtrace("Processing scale {$scale->name} (ID: {$scale->id})...");
            $calculator->calculate_for_scale($scale->id);
        }

        mtrace('Parameter recalculation completed.');
    }
}
