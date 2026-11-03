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

namespace local_catquiz;

use advanced_testcase;
use xmldb_field;
use xmldb_table;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/upgradelib.php');
require_once($CFG->dirroot . '/local/catquiz/db/upgrade.php');

/**
 * The upgrades for the frozen range (issue #137): 2026100913 adds it, 2026100916 the mark for a range
 * taken later. Neither invents a range for older results - that is the recovery's job, in its window.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::xmldb_local_catquiz_upgrade
 */
final class upgrade_range_snapshot_test extends advanced_testcase {
    /**
     * A root scale with the range -3..3.
     *
     * @return int
     */
    private function scale(): int {
        global $DB;
        return (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'name' => 'Range', 'parentid' => 0, 'minscalevalue' => -3, 'maxscalevalue' => 3,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * A stored result.
     *
     * @param int $scaleid
     * @param array $fields Overrides.
     * @return int
     */
    private function stored_result(int $scaleid, array $fields = []): int {
        global $DB;
        static $attemptid = 100;
        return (int) $DB->insert_record('local_catquiz_personparams', (object) ($fields + [
            'userid' => 2, 'catscaleid' => $scaleid, 'contextid' => 1, 'attemptid' => ++$attemptid, 'ability' => 0.5,
            'isvalid' => 1, 'resultsource' => 'current', 'status' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]));
    }

    /**
     * From a site before 2026100913: the columns come, an older result keeps no range and no mark, and
     * the steps can run again.
     */
    public function test_upgrade_adds_the_range_and_invents_none(): void {
        global $DB;
        $this->resetAfterTest();
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_catquiz_personparams');

        $rowid = $this->stored_result($this->scale());

        // The schema of 2026100912.
        foreach (['rangebackfilled', 'rangeupper', 'rangelower'] as $name) {
            $dbman->drop_field($table, new xmldb_field($name));
        }
        $this->assertFalse($dbman->field_exists($table, 'rangelower'));
        set_config('version', 2026100912, 'local_catquiz');

        // The step of issue #150 reports the boundary of stored results to the admin; that notice is expected.
        $this->expectOutputRegex('/local_catquiz: results of attempts are stored since /');
        $this->assertTrue(xmldb_local_catquiz_upgrade(2026100912));

        foreach (['rangelower', 'rangeupper', 'rangebackfilled'] as $name) {
            $this->assertTrue($dbman->field_exists($table, $name), $name);
        }
        $row = $DB->get_record('local_catquiz_personparams', ['id' => $rowid]);
        $this->assertNull($row->rangelower, 'The range of an older result is not taken from today\'s scale here.');
        $this->assertNull($row->rangeupper);
        $this->assertSame(0, (int) $row->rangebackfilled);
        // The upgrade runs to its last savepoint - read from upgrade.php, not pinned: every new step moved it.
        global $CFG;
        preg_match_all(
            "/upgrade_plugin_savepoint\\(true, (\\d+), 'local', 'catquiz'\\)/",
            file_get_contents($CFG->dirroot . '/local/catquiz/db/upgrade.php'),
            $savepoints
        );
        $this->assertSame((string) max(array_map('intval', $savepoints[1])), (string) get_config('local_catquiz', 'version'));

        // Run again on a site where the columns exist: nothing breaks.
        set_config('version', 2026100912, 'local_catquiz');
        $this->assertTrue(xmldb_local_catquiz_upgrade(2026100912));
    }
}
