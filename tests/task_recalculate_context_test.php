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
 * Regression tests for the scheduled recalculation task (issue #44).
 *
 * Guards the two load-bearing properties of the bugfix:
 *  - safe defaults: the task ships disabled and with a quarterly cadence;
 *  - context safety: the scheduled task loads the persistent active context of a
 *    scale and never creates or activates a new one. In particular a run without
 *    new responses must not create a context and must not change catscale.contextid
 *    (which previously hid historical statistics/exports).
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;
use core\task\manager;
use local_catquiz\task\recalculate_cat_model_params;

/**
 * Regression tests for the scheduled recalculation task (issue #44).
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_catquiz\task\recalculate_cat_model_params
 */
final class task_recalculate_context_test extends advanced_testcase {
    /**
     * The scheduled task ships disabled and with a quarterly cadence.
     *
     * @return void
     */
    public function test_task_ships_disabled_and_quarterly(): void {
        $this->resetAfterTest(true);

        // Test the shipped default from db/tasks.php (independent of any DB/upgrade
        // state that Moodle deliberately preserves for admin-customised tasks).
        $task = null;
        foreach (manager::load_default_scheduled_tasks_for_component('local_catquiz') as $default) {
            if ($default instanceof recalculate_cat_model_params) {
                $task = $default;
                break;
            }
        }
        $this->assertNotNull($task, 'The recalculation task must be defined in db/tasks.php.');
        $this->assertTrue($task->get_disabled(), 'The recalculation task must ship disabled by default.');
        $this->assertSame('*/3', $task->get_month(), 'The default cadence must be quarterly (every third month).');
        $this->assertSame('1', $task->get_day(), 'The default schedule must run on the first day of the month.');
    }

    /**
     * A run without new responses keeps the context and changes nothing.
     *
     * @return void
     */
    public function test_run_without_new_responses_keeps_context(): void {
        global $DB;
        $this->resetAfterTest(true);
        $now = time();

        // Persistent active context (active window covers "now").
        $contextid = $DB->insert_record('local_catquiz_catcontext', (object) [
            'name' => 'Active context',
            'starttimestamp' => 0,
            'endtimestamp' => $now + WEEKSECS,
            'timecreated' => $now,
            'timemodified' => $now,
            'timecalculated' => $now,
            'usermodified' => 0,
        ]);

        // Top-level scale pointing at that context.
        $scaleid = $DB->insert_record('local_catquiz_catscales', (object) [
            'parentid' => 0,
            'name' => 'Scale 44',
            'contextid' => $contextid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $contextsbefore = $DB->count_records('local_catquiz_catcontext');

        // Run the scheduled task; there are no responses, so it must be a no-op.
        $task = new recalculate_cat_model_params();
        ob_start();
        $task->execute();
        ob_end_clean();

        $this->assertSame(
            $contextsbefore,
            $DB->count_records('local_catquiz_catcontext'),
            'The scheduled task must not create a new context when there are no new responses.'
        );
        $this->assertSame(
            (int) $contextid,
            (int) $DB->get_field('local_catquiz_catscales', 'contextid', ['id' => $scaleid]),
            'The scheduled task must not change catscale.contextid.'
        );
    }
}
