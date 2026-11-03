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

namespace local_catquiz\local\result;

use advanced_testcase;

/**
 * The ability range a result was measured on is frozen with it at finalisation (issue #137).
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\local\result\personparam_repository
 */
final class range_snapshot_test extends advanced_testcase {
    /**
     * A root scale on [-5, 5] with a subscale.
     *
     * @return int[] [root, sub]
     */
    private function scales(): array {
        global $DB;
        $root = (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'name' => 'Root', 'parentid' => 0, 'minscalevalue' => -5, 'maxscalevalue' => 5,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $sub = (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'name' => 'Sub', 'parentid' => $root, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        \cache::make('local_catquiz', 'catscales')->purge();
        return [$root, $sub];
    }

    /**
     * Files the result of an attempt with a valid root and a valid subscale.
     *
     * @param int $catattemptid
     * @param int $root
     * @param int $sub
     */
    private function finalise(int $catattemptid, int $root, int $sub): void {
        $result = attempt_result_validator::from_personabilities(
            [$root => ['value' => 1.2, 'toreport' => true], $sub => ['value' => 0.4, 'toreport' => true]],
            [$root => 0.3, $sub => 0.4],
            [$root => 12, $sub => 6],
            [$root => 0.5, $sub => 0.5],
            $root
        );
        personparam_repository::save_attempt_result($catattemptid, 2, 9, $result);
    }

    /**
     * Every scale row carries the range of its root at finalisation; a later change of the scale does
     * not reach it, and writing the result again keeps it.
     */
    public function test_range_is_frozen_at_finalisation(): void {
        global $DB;
        $this->resetAfterTest();
        [$root, $sub] = $this->scales();

        $this->finalise(77, $root, $sub);
        $rows = personparam_repository::get_for_attempt(77);
        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertEqualsWithDelta(-5.0, (float) $row->rangelower, 1e-9, "Scale {$row->catscaleid}");
            $this->assertEqualsWithDelta(5.0, (float) $row->rangeupper, 1e-9, "Scale {$row->catscaleid}");
            $this->assertSame(0, (int) $row->rangebackfilled, 'Recorded at finalisation, not taken later.');
        }

        // The scale is widened afterwards.
        $DB->update_record('local_catquiz_catscales', (object) ['id' => $root, 'minscalevalue' => -10, 'maxscalevalue' => 10]);
        \cache::make('local_catquiz', 'catscales')->purge();

        // A revalidation writes the result again: the range of the finalisation stays.
        $this->finalise(77, $root, $sub);
        foreach (personparam_repository::get_for_attempt(77) as $row) {
            $this->assertEqualsWithDelta(-5.0, (float) $row->rangelower, 1e-9);
            $this->assertEqualsWithDelta(5.0, (float) $row->rangeupper, 1e-9);
        }

        // A new attempt is measured on the new range.
        $this->finalise(78, $root, $sub);
        foreach (personparam_repository::get_for_attempt(78) as $row) {
            $this->assertEqualsWithDelta(-10.0, (float) $row->rangelower, 1e-9);
        }
    }

    /**
     * A row from before ranges were recorded gets none by being read or stored as it is: only a
     * revalidation - the recovery of older results - gives it today's range, marked (attempt_revalidator_test).
     */
    public function test_older_rows_keep_no_range(): void {
        global $DB;
        $this->resetAfterTest();
        [$root] = $this->scales();
        $id = $DB->insert_record('local_catquiz_personparams', (object) [
            'attemptid' => 79, 'userid' => 2, 'contextid' => 9, 'catscaleid' => $root, 'ability' => 1.0,
            'isprimary' => 1, 'isvalid' => 1, 'resultsource' => 'current', 'timecreated' => time(), 'timemodified' => time(),
        ]);

        $row = $DB->get_record('local_catquiz_personparams', ['id' => $id]);
        $this->assertNull($row->rangelower);
        $this->assertNull($row->rangeupper);
    }
}
