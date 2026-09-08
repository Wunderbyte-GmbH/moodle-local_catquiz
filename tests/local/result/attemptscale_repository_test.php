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
 * Tests the per-attempt, per-scale result repository (Issue #9).
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\local\result\attemptscale_repository
 */
final class attemptscale_repository_test extends advanced_testcase {
    /**
     * Builds an attempt_result with one measured primary scale, one measured
     * non-primary scale, and one carry-over-only scale.
     *
     * @return attempt_result
     */
    private function build_result(): attempt_result {
        return attempt_result_validator::from_personabilities(
            [
                5 => ['value' => 0.4, 'toreport' => true],
                6 => ['value' => 0.2],
                7 => ['value' => 0.1],
            ],
            [5 => 0.3, 6 => 0.5, 7 => 0.7],
            [5 => 6, 6 => 4, 7 => 0],
            [5 => 0.5, 6 => 0.5, 7 => 0.5],
            5
        );
    }

    /**
     * Exactly one row is written per measured scale; carry-over-only scales are
     * not persisted (so N/fraction/SE are never carried over).
     */
    public function test_save_writes_one_row_per_measured_scale(): void {
        global $DB;
        $this->resetAfterTest();

        attemptscale_repository::save_attempt_result(100, 2, 9, $this->build_result());

        $rows = attemptscale_repository::get_for_attempt(100);
        $byscale = [];
        foreach ($rows as $row) {
            $byscale[(int) $row->catscaleid] = $row;
        }

        $this->assertCount(2, $rows, 'Only the two measured scales are persisted.');
        $this->assertArrayHasKey(5, $byscale);
        $this->assertArrayHasKey(6, $byscale);
        $this->assertArrayNotHasKey(7, $byscale, 'The carry-over-only scale must not be persisted.');

        $this->assertEquals(1, $byscale[5]->isprimary);
        $this->assertEquals(1, $byscale[5]->isvalid);
        $this->assertEquals(6, $byscale[5]->n);
        $this->assertEquals('current', $byscale[5]->resultsource);

        $this->assertEquals(0, $byscale[6]->isprimary);
        $this->assertEquals(0, $byscale[6]->isvalid);
        $this->assertStringContainsString(scale_result::REASON_NOT_PRIMARY, $byscale[6]->validationstatus);
    }

    /**
     * Re-saving the same attempt keeps exactly one row per scale (idempotent
     * upsert on the unique key catattemptid + catscaleid).
     */
    public function test_save_is_idempotent_per_attempt_and_scale(): void {
        $this->resetAfterTest();

        attemptscale_repository::save_attempt_result(100, 2, 9, $this->build_result());
        attemptscale_repository::save_attempt_result(100, 2, 9, $this->build_result());

        $this->assertCount(2, attemptscale_repository::get_for_attempt(100));
    }

    /**
     * The most recent valid result and the last valid primary are found for
     * carry-over and prioritisation.
     */
    public function test_carryover_lookups(): void {
        $this->resetAfterTest();

        // Attempt 100: scale 5 valid + primary.
        attemptscale_repository::save_attempt_result(100, 2, 9, $this->build_result());

        // Attempt 200: scale 5 valid + primary again, more recent.
        $laterresult = attempt_result_validator::from_personabilities(
            [5 => ['value' => 0.9, 'toreport' => true]],
            [5 => 0.2],
            [5 => 8],
            [5 => 0.6],
            5
        );
        attemptscale_repository::save_attempt_result(200, 2, 9, $laterresult);

        $latest = attemptscale_repository::get_latest_valid(2, 9, 5);
        $this->assertNotNull($latest);
        $this->assertEquals(200, $latest->catattemptid, 'The most recent valid result wins.');

        $lastprimary = attemptscale_repository::get_last_primary(2, 9);
        $this->assertNotNull($lastprimary);
        $this->assertEquals(5, $lastprimary->catscaleid);
        $this->assertEquals(200, $lastprimary->catattemptid);

        // No valid result for an untested scale.
        $this->assertNull(attemptscale_repository::get_latest_valid(2, 9, 999));
    }

    /**
     * Teeth test: without the "measured in current attempt" guard, a
     * carry-over-only scale (N = 0) would wrongly be historised, breaking the
     * rule that N/fraction/SE are never carried over.
     */
    public function test_carryover_only_is_not_persisted_contract(): void {
        $this->resetAfterTest();

        $result = attempt_result_validator::from_personabilities(
            [7 => ['value' => 0.1, 'toreport' => true]],
            [7 => 0.7],
            [7 => 0],
            [7 => 0.5]
        );
        attemptscale_repository::save_attempt_result(300, 2, 9, $result);

        $this->assertCount(0, attemptscale_repository::get_for_attempt(300));
    }
}
