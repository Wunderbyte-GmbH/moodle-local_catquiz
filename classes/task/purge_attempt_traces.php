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
use local_catquiz\local\monitoring\timeline;

/**
 * Removes attempt traces older than the configured number of days (issue #136).
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class purge_attempt_traces extends scheduled_task {
    /**
     * Returns the name shown in the scheduled task settings.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('purgeattempttraces', 'local_catquiz');
    }

    /**
     * Deletes traces past the retention period. 0 days keeps them until deleted by hand.
     *
     * @return void
     */
    public function execute(): void {
        global $DB;

        $days = (int) get_config('local_catquiz', 'attempttraceretentiondays');
        if ($days <= 0) {
            return;
        }
        $DB->delete_records_select(timeline::TABLE, 'timecreated < :before', ['before' => time() - $days * DAYSECS]);
    }
}
