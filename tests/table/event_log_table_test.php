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

namespace local_catquiz\table;

use advanced_testcase;
use context_system;
use local_catquiz\event\calculation_executed;
use local_catquiz\event\calculation_skipped;

/**
 * The event log of the CAT manager shows old rows: of event classes that are gone, and with data that is
 * missing or deleted since (issue #79).
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\table\event_log_table
 * @covers \local_catquiz\event\calculation_executed
 * @covers \local_catquiz\event\calculation_skipped
 */
final class event_log_table_test extends advanced_testcase {
    /**
     * A log row as the event log query returns it.
     *
     * @param string $eventname
     * @param array $other
     * @return \stdClass
     */
    private function row(string $eventname, array $other): \stdClass {
        // As in the log: the class name is component, target and action.
        $short = substr($eventname, strrpos($eventname, '\\') + 1);
        $action = substr($short, strrpos($short, '_') + 1);
        $target = substr($short, 0, strrpos($short, '_'));
        return (object) [
            'id' => 1, 'eventname' => $eventname, 'component' => 'local_catquiz', 'action' => $action, 'target' => $target,
            'objecttable' => null, 'objectid' => null, 'crud' => 'c', 'edulevel' => 0,
            'contextid' => context_system::instance()->id, 'contextlevel' => CONTEXT_SYSTEM,
            'contextinstanceid' => 0, 'userid' => 2, 'courseid' => 0, 'relateduserid' => null, 'anonymous' => 0,
            'other' => $other, 'timecreated' => time(),
        ];
    }

    /**
     * A row of an event class that no longer exists is shown as a historic event, not a fatal error.
     */
    public function test_removed_event_class(): void {
        $this->resetAfterTest();
        $table = new event_log_table('eventlogtest');
        $row = $this->row('\\local_catquiz\\event\\responses_added', []);

        $this->assertStringContainsString('responses_added', $table->col_eventname(clone $row));
        $this->assertStringContainsString('local_catquiz', $table->col_description(clone $row));
    }

    /**
     * A calculation row without the user, with a deleted user, with a deleted scale: described without warnings.
     */
    public function test_calculation_rows_with_missing_or_deleted_data(): void {
        $this->resetAfterTest();
        $table = new event_log_table('eventlogtest');
        $deleted = $this->getDataGenerator()->create_user();
        delete_user($deleted);

        foreach ([[], ['userid' => $deleted->id], ['userid' => 0]] as $user) {
            $row = $this->row('\\' . calculation_executed::class, ['catscaleid' => 999999, 'contextid' => 1] + $user);
            $description = $table->col_description(clone $row);
            $this->assertStringContainsString(get_string('deletedcatscale', 'local_catquiz'), $description);
        }
        $this->assertNotEmpty($table->col_eventname($this->row('\\' . calculation_executed::class, [])));

        $skipped = $this->row('\\' . calculation_skipped::class, ['catscaleid' => 999999, 'contextid' => 999999]);
        $this->assertStringContainsString(get_string('deletedcatscale', 'local_catquiz'), $table->col_description($skipped));
        $this->assertDebuggingNotCalled();
    }
}
