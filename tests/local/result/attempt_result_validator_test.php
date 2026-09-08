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
 * Tests the central CAT result validator (Issue #7).
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\local\result\attempt_result_validator
 * @covers \local_catquiz\local\result\attempt_result
 * @covers \local_catquiz\local\result\scale_result
 */
final class attempt_result_validator_test extends advanced_testcase {
    /**
     * A clean primary scale that was measured in the current attempt is valid,
     * reportable and statistically valid, with no rejection reasons.
     */
    public function test_clean_primary_scale_is_valid(): void {
        $result = attempt_result_validator::from_personabilities(
            [5 => ['value' => 0.4, 'toreport' => true]],
            [5 => 0.3],
            [5 => 6],
            [5 => 0.5]
        );

        $scale = $result->get_scale_result(5);
        $this->assertNotNull($scale);
        $this->assertTrue($scale->valid);
        $this->assertTrue($scale->statisticallyvalid);
        $this->assertTrue($scale->reportable);
        $this->assertTrue($scale->primary);
        $this->assertTrue($scale->measuredincurrentattempt);
        $this->assertSame([], $scale->rejectionreasons);
        $this->assertTrue($result->is_valid());
        $this->assertSame($scale, $result->get_primary_scale());
        $this->assertSame([5], $result->get_reportable_scale_ids());
    }

    /**
     * Decision 8.1: reporting disabled makes a scale non-reportable but does
     * NOT make it statistically invalid. It is not valid for completion only
     * because it is no longer the reported/primary scale.
     */
    public function test_reporting_disabled_is_display_only_not_statistical(): void {
        $result = attempt_result_validator::from_personabilities(
            [5 => [
                'value' => 0.4,
                'toreport' => true,
                'excluded' => true,
                'error' => ['checkbox' => ['scalereportcheckboxinquizsettings' => false]],
            ]],
            [],
            [5 => 6]
        );

        $scale = $result->get_scale_result(5);
        $this->assertTrue($scale->statisticallyvalid, 'Reporting config must not affect statistical validity.');
        $this->assertFalse($scale->reportable);
        $this->assertContains(scale_result::REASON_REPORTING_DISABLED, $scale->rejectionreasons);
        $this->assertFalse($result->has_reportable_result());
    }

    /**
     * Standard-error, N-minimum, fraction and root-only exclusions each make a
     * scale statistically invalid with the matching machine-readable reason.
     */
    public function test_statistical_rejections_map_to_reasons(): void {
        $cases = [
            ['error' => ['se' => ['semaxdefined' => 1.0, 'securrent' => 2.0]], 'reason' => scale_result::REASON_SE_MAX],
            ['error' => ['se' => ['semindefined' => 0.1, 'securrent' => 0.0]], 'reason' => scale_result::REASON_SE_MIN],
            ['error' => ['nminscale' => ['nminscaledefined' => 5, 'nscalecurrent' => 2]], 'reason' => scale_result::REASON_N_MIN],
            ['error' => ['fraction' => ['fraction' => 1]], 'reason' => scale_result::REASON_FRACTION],
            ['error' => ['rootonly' => ['only' => true]], 'reason' => scale_result::REASON_ROOTONLY],
        ];

        foreach ($cases as $case) {
            $result = attempt_result_validator::from_personabilities(
                [7 => ['value' => 0.1, 'toreport' => true, 'excluded' => true, 'error' => $case['error']]],
                [],
                [7 => 4]
            );
            $scale = $result->get_scale_result(7);
            $this->assertFalse($scale->statisticallyvalid, 'Case ' . $case['reason']);
            $this->assertFalse($scale->valid, 'Case ' . $case['reason']);
            $this->assertContains($case['reason'], $scale->rejectionreasons);
            $this->assertFalse($result->is_valid(), 'Case ' . $case['reason']);
        }
    }

    /**
     * A carry-over-only scale (no items in the current attempt) is not valid
     * for completion even though it is statistically fine and reportable.
     */
    public function test_carryover_only_is_not_valid(): void {
        $result = attempt_result_validator::from_personabilities(
            [5 => ['value' => 0.4, 'toreport' => true]],
            [],
            [5 => 0]
        );

        $scale = $result->get_scale_result(5);
        $this->assertFalse($scale->measuredincurrentattempt);
        $this->assertTrue($scale->statisticallyvalid);
        $this->assertFalse($scale->valid);
        $this->assertContains(scale_result::REASON_NOT_MEASURED, $scale->rejectionreasons);
        $this->assertFalse($result->is_valid());
    }

    /**
     * A non-primary scale is not valid for completion and carries the reason.
     */
    public function test_non_primary_scale_is_not_valid(): void {
        $result = attempt_result_validator::from_personabilities(
            [
                5 => ['value' => 0.4, 'toreport' => true],
                6 => ['value' => 0.2],
            ],
            [],
            [5 => 4, 6 => 4],
            [],
            5
        );

        $primary = $result->get_scale_result(5);
        $secondary = $result->get_scale_result(6);
        $this->assertTrue($primary->primary);
        $this->assertTrue($primary->valid);
        $this->assertFalse($secondary->primary);
        $this->assertFalse($secondary->valid);
        $this->assertContains(scale_result::REASON_NOT_PRIMARY, $secondary->rejectionreasons);
    }

    /**
     * The reportable set reproduces the historical definition
     * (toreport, not excluded, not hidden) across a mixed structure.
     */
    public function test_reportable_set_matches_historical_definition(): void {
        $personabilities = [
            1 => ['value' => 0.1, 'toreport' => true],
            2 => ['value' => 0.2, 'toreport' => true, 'hidden' => true],
            3 => ['value' => 0.3, 'toreport' => true, 'excluded' => true, 'error' => ['se' => ['semaxdefined' => 1]]],
            4 => ['value' => 0.4],
            5 => ['value' => 0.5, 'toreport' => true, 'excluded' => true, 'error' => ['checkbox' => ['x' => false]]],
        ];

        $historical = array_keys(array_filter(
            $personabilities,
            fn ($a) => is_array($a) && !empty($a['toreport']) && empty($a['excluded']) && empty($a['hidden'])
        ));

        $result = attempt_result_validator::from_personabilities($personabilities);

        $this->assertEquals($historical, $result->get_reportable_scale_ids());
        $this->assertEquals([1], $result->get_reportable_scale_ids());
    }

    /**
     * Teeth test: without excluding reporting from statistical validity
     * (decision 8.1) a reporting-disabled scale would wrongly be reported as
     * statistically invalid. The assertion in
     * test_reporting_disabled_is_display_only_not_statistical guards this; here
     * we assert the precise contract that the guard protects.
     */
    public function test_reporting_disabled_scale_stays_statistically_valid_contract(): void {
        $result = attempt_result_validator::from_personabilities(
            [9 => [
                'value' => 0.0,
                'toreport' => true,
                'excluded' => true,
                'error' => ['checkbox' => ['scalereportcheckboxinquizsettings' => false]],
            ]],
            [],
            [9 => 5]
        );

        // If the validator treated the checkbox exclusion as a statistical
        // reason (removing the `!$hascheckbox` term), this would be false.
        $this->assertTrue($result->get_scale_result(9)->statisticallyvalid);
    }

    /**
     * The validate() facade reads the per-scale abilities persisted with the
     * attempt and produces the same verdict as the pure builder.
     */
    public function test_validate_reads_stored_attempt(): void {
        global $DB;
        $this->resetAfterTest();

        $json = json_encode([
            'personabilities_abilities' => [
                5 => ['value' => 0.4, 'toreport' => true],
                6 => ['value' => 0.2, 'toreport' => true, 'excluded' => true,
                    'error' => ['nminscale' => ['nminscaledefined' => 5, 'nscalecurrent' => 1]]],
            ],
            'se' => [5 => 0.3, 6 => 0.9],
        ]);

        $now = time();
        $adaptiveattemptid = $DB->insert_record('adaptivequiz_attempt', (object) [
            'instance' => 1, 'userid' => 2, 'uniqueid' => 4242, 'attemptstate' => 'complete',
            'attemptstopcriteria' => '', 'questionsattempted' => 5, 'difficultysum' => 0,
            'standarderror' => 0.3, 'measure' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => 2, 'attemptid' => $adaptiveattemptid, 'component' => 'mod_adaptivequiz',
            'status' => 0, 'endtime' => $now, 'json' => $json, 'timecreated' => $now, 'timemodified' => $now,
        ]);

        $result = attempt_result_validator::validate($adaptiveattemptid);

        $this->assertTrue($result->get_scale_result(5)->statisticallyvalid);
        $this->assertFalse($result->get_scale_result(6)->statisticallyvalid);
        $this->assertContains(scale_result::REASON_N_MIN, $result->get_scale_result(6)->rejectionreasons);
        $this->assertEquals(0.3, $result->get_scale_result(5)->standarderror);
        $this->assertSame([5], $result->get_reportable_scale_ids());
    }
}
