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
 * Issue #14: custom feedback ranges are overlap-free and validated.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;
use local_catquiz\teststrategy\feedback_helper;
use local_catquiz\feedback\feedbackclass;

/**
 * Half-open feedback ranges and save-time validation (issue #14).
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\teststrategy\feedback_helper::get_feedback_range_index
 * @covers     \local_catquiz\feedback\feedbackclass::validation_range_limits_nogaps
 */
final class feedback_ranges_test extends advanced_testcase {
    /** @var int Scale id used in the settings keys. */
    private const SCALE = 5;

    /**
     * Three contiguous ranges 0..1, 1..2, 2..3 for scale SCALE.
     *
     * @return array
     */
    private function settings(): array {
        $s = self::SCALE;
        return [
            'numberoffeedbackoptionsselect' => 3,
            "feedback_scaleid_limit_lower_{$s}_1" => 0.0,
            "feedback_scaleid_limit_upper_{$s}_1" => 1.0,
            "feedback_scaleid_limit_lower_{$s}_2" => 1.0,
            "feedback_scaleid_limit_upper_{$s}_2" => 2.0,
            "feedback_scaleid_limit_lower_{$s}_3" => 2.0,
            "feedback_scaleid_limit_upper_{$s}_3" => 3.0,
        ];
    }

    /**
     * Inserts a real root scale and returns validation data keyed to its id.
     *
     * validation_range_limits_nogaps only validates scales that exist in the
     * scale tree, so a real scale record is required.
     *
     * @return array [array $data, int $scaleid]
     */
    private function validation_data(): array {
        global $DB;
        $scaleid = (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'name' => 'Range scale',
            'parentid' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
            'usermodified' => 2,
        ]);
        $data = [
            'catquiz_catscales' => $scaleid,
            'numberoffeedbackoptionsselect' => 3,
            "feedback_scaleid_limit_lower_{$scaleid}_1" => 0.0,
            "feedback_scaleid_limit_upper_{$scaleid}_1" => 1.0,
            "feedback_scaleid_limit_lower_{$scaleid}_2" => 1.0,
            "feedback_scaleid_limit_upper_{$scaleid}_2" => 2.0,
            "feedback_scaleid_limit_lower_{$scaleid}_3" => 2.0,
            "feedback_scaleid_limit_upper_{$scaleid}_3" => 3.0,
        ];
        return [$data, $scaleid];
    }

    /**
     * A shared boundary belongs to exactly one range (the upper one).
     *
     * @return void
     */
    public function test_shared_boundary_goes_to_one_range(): void {
        $s = $this->settings();
        // Interior values.
        $this->assertSame(1, feedback_helper::get_feedback_range_index($s, self::SCALE, 0.5));
        $this->assertSame(2, feedback_helper::get_feedback_range_index($s, self::SCALE, 1.5));
        $this->assertSame(3, feedback_helper::get_feedback_range_index($s, self::SCALE, 2.5));
        // Shared boundaries resolve to the higher range only (half-open).
        $this->assertSame(2, feedback_helper::get_feedback_range_index($s, self::SCALE, 1.0));
        $this->assertSame(3, feedback_helper::get_feedback_range_index($s, self::SCALE, 2.0));
    }

    /**
     * The lowest lower bound is included; the top upper bound is included.
     *
     * @return void
     */
    public function test_endpoints(): void {
        $s = $this->settings();
        $this->assertSame(1, feedback_helper::get_feedback_range_index($s, self::SCALE, 0.0));
        $this->assertSame(3, feedback_helper::get_feedback_range_index($s, self::SCALE, 3.0));
    }

    /**
     * Values outside every range yield null.
     *
     * @return void
     */
    public function test_outside_ranges(): void {
        $s = $this->settings();
        $this->assertNull(feedback_helper::get_feedback_range_index($s, self::SCALE, -0.5));
        $this->assertNull(feedback_helper::get_feedback_range_index($s, self::SCALE, 3.5));
        $this->assertNull(feedback_helper::get_feedback_range_index($s, self::SCALE, null));
    }

    /**
     * A valid contiguous ascending configuration produces no errors.
     *
     * @return void
     */
    public function test_validation_accepts_contiguous_ranges(): void {
        $this->resetAfterTest();
        [$data] = $this->validation_data();
        $errors = [];
        feedbackclass::validation_range_limits_nogaps($errors, $data);
        $this->assertSame([], $errors);
    }

    /**
     * A gap between two ranges is rejected.
     *
     * @return void
     */
    public function test_validation_rejects_gap(): void {
        $this->resetAfterTest();
        [$data, $scaleid] = $this->validation_data();
        // Introduce a gap: range 2 no longer starts where range 1 ends.
        $data["feedback_scaleid_limit_lower_{$scaleid}_2"] = 1.5;
        $errors = [];
        feedbackclass::validation_range_limits_nogaps($errors, $data);
        $this->assertArrayHasKey("feedback_scaleid_limit_lower_{$scaleid}_2", $errors);
    }

    /**
     * An overlap between two ranges is rejected.
     *
     * @return void
     */
    public function test_validation_rejects_overlap(): void {
        $this->resetAfterTest();
        [$data, $scaleid] = $this->validation_data();
        // Overlap: range 2 starts below where range 1 ends (lower_2 < upper_1).
        $data["feedback_scaleid_limit_lower_{$scaleid}_2"] = 0.5;
        $errors = [];
        feedbackclass::validation_range_limits_nogaps($errors, $data);
        // The contiguity check (upper_1 == lower_2) rejects the overlap.
        $this->assertArrayHasKey("feedback_scaleid_limit_lower_{$scaleid}_2", $errors);
    }

    /**
     * A non-ascending first range is rejected.
     *
     * @return void
     */
    public function test_validation_rejects_non_ascending_first_range(): void {
        $this->resetAfterTest();
        [$data, $scaleid] = $this->validation_data();
        // Make range 1 non-ascending (upper <= lower).
        $data["feedback_scaleid_limit_upper_{$scaleid}_1"] = 0.0;
        $data["feedback_scaleid_limit_lower_{$scaleid}_2"] = 0.0;
        $errors = [];
        feedbackclass::validation_range_limits_nogaps($errors, $data);
        $this->assertArrayHasKey("feedback_scaleid_limit_upper_{$scaleid}_1", $errors);
    }

    /**
     * Measurement-uncertainty gating: the whole CI must lie within one range.
     *
     * @return void
     */
    public function test_uncertainty_gating(): void {
        $s = $this->settings();
        // Value 1.5 sits in the middle of range 2 ([1,2)); a small SE keeps the
        // whole interval inside range 2 -> definite classification.
        $this->assertSame(
            2,
            feedback_helper::get_feedback_range_index_with_uncertainty($s, self::SCALE, 1.5, 0.1, 1.0)
        );
        // Value 1.95 with SE 0.1 and k=1 -> interval [1.85, 2.05] straddles the
        // 2|3 boundary -> uncertain -> null.
        $this->assertNull(
            feedback_helper::get_feedback_range_index_with_uncertainty($s, self::SCALE, 1.95, 0.1, 1.0)
        );
        // Disabled (k = 0) collapses to the plain point classification.
        $this->assertSame(
            2,
            feedback_helper::get_feedback_range_index_with_uncertainty($s, self::SCALE, 1.95, 0.1, 0.0)
        );
        // No SE also collapses to the point classification.
        $this->assertSame(
            2,
            feedback_helper::get_feedback_range_index_with_uncertainty($s, self::SCALE, 1.95, null, 1.0)
        );
    }
}
