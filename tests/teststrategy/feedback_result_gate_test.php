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
 * Issue #7: the feedback path judges scales by the central result object.
 *
 * The generators used to re-implement the gate over the raw flags `toreport`,
 * `excluded` and `hidden`. Those are ambiguous - `excluded` is set both for a
 * measurement problem (SE below the minimum) and for a pure display decision
 * (reporting checkbox off) - so every consumer had to know which combination
 * meant what, and display could drift away from validity.
 *
 * These tests pin the equivalence to the historical filter and the fact that the
 * displayed reason now comes from the machine readable rejection reasons.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\teststrategy\feedback_helper::build_attempt_result
 * @covers \local_catquiz\teststrategy\feedback_helper::is_displayable
 * @covers \local_catquiz\teststrategy\feedback_helper::get_rejection_reason_string
 */

namespace local_catquiz\teststrategy;

use advanced_testcase;
use local_catquiz\teststrategy\feedbacksettings;

/**
 * Guards the central display gate of the feedback path.
 *
 * @package    local_catquiz
 * @covers \local_catquiz\teststrategy\feedback_helper::build_attempt_result
 * @covers \local_catquiz\teststrategy\feedback_helper::is_displayable
 * @covers \local_catquiz\teststrategy\feedback_helper::get_rejection_reason_string
 */
final class feedback_result_gate_test extends advanced_testcase {
    /**
     * The historical filter: reported, not excluded, not hidden.
     *
     * @param array $entry
     *
     * @return bool
     */
    private function legacy_displayable(array $entry): bool {
        return isset($entry['toreport']) && empty($entry['excluded']) && empty($entry['hidden']);
    }

    /**
     * The new gate reproduces the historical filter for every flag combination.
     *
     * @return void
     */
    public function test_gate_matches_the_historical_filter(): void {
        $this->resetAfterTest(true);

        $cases = [
            'clean reported scale' => ['toreport' => true],
            'not reported' => [],
            'hidden' => ['toreport' => true, 'hidden' => true],
            'reporting checkbox off' => [
                'toreport' => true,
                'excluded' => true,
                'error' => ['checkbox' => ['scalereportcheckboxinquizsettings' => false]],
            ],
            'se below minimum' => [
                'toreport' => true,
                'excluded' => true,
                'error' => ['se' => ['semindefined' => 0.35, 'securrent' => 0.1]],
            ],
            'too few items' => [
                'toreport' => true,
                'excluded' => true,
                'error' => ['nminscale' => ['nmin' => 3, 'ncurrent' => 1]],
            ],
        ];

        foreach ($cases as $label => $entry) {
            $entry['value'] = 0.5;
            $personabilities = [7 => $entry];
            $result = feedback_helper::build_attempt_result($personabilities);

            $this->assertSame(
                $this->legacy_displayable($entry),
                feedback_helper::is_displayable($result, 7),
                sprintf('Gate diverges from the historical filter for "%s".', $label)
            );
        }
    }

    /**
     * A scale that is only hidden from the report stays statistically valid, so
     * its result is still persisted - display and validity are separate.
     *
     * @return void
     */
    public function test_reporting_off_is_not_a_measurement_problem(): void {
        $this->resetAfterTest(true);

        $personabilities = [
            7 => [
                'value' => 0.5,
                'toreport' => true,
                'primary' => true,
                'excluded' => true,
                'error' => ['checkbox' => ['scalereportcheckboxinquizsettings' => false]],
            ],
        ];
        $result = feedback_helper::build_attempt_result($personabilities);
        $scale = $result->get_scale_result(7);

        $this->assertFalse($scale->reportable, 'Reporting is off, so nothing is displayed.');
        $this->assertTrue($scale->statisticallyvalid, 'But the measurement itself is sound.');
        $this->assertFalse(feedback_helper::is_displayable($result, 7));
    }

    /**
     * The displayed reason is derived from the rejection reasons and still
     * carries the interpolated detail values.
     *
     * @return void
     */
    public function test_rejection_reason_string_uses_the_reasons(): void {
        $this->resetAfterTest(true);

        $personabilities = [
            7 => [
                'value' => 0.5,
                'toreport' => true,
                'excluded' => true,
                'error' => ['se' => ['semindefined' => 0.35, 'securrent' => 0.1]],
            ],
        ];
        $result = feedback_helper::build_attempt_result($personabilities);

        $this->assertSame(
            get_string('error:semin', 'local_catquiz', ['semindefined' => 0.35, 'securrent' => 0.1]),
            feedback_helper::get_rejection_reason_string($result, $personabilities)
        );
    }

    /**
     * A purely display related reason must not be reported as a measurement
     * problem - the fallback message is used instead.
     *
     * @return void
     */
    public function test_display_only_reasons_do_not_become_measurement_messages(): void {
        $this->resetAfterTest(true);

        $personabilities = [
            7 => [
                'value' => 0.5,
                'toreport' => true,
                'excluded' => true,
                'error' => ['checkbox' => ['scalereportcheckboxinquizsettings' => false]],
            ],
        ];
        $result = feedback_helper::build_attempt_result($personabilities);

        $this->assertSame(
            get_string('noscalesfound', 'local_catquiz'),
            feedback_helper::get_rejection_reason_string($result, $personabilities)
        );
    }

    /**
     * The primary scale marked in the abilities is used as the primary of the
     * result, so the gate does not fall back to "everything reported is primary".
     *
     * @return void
     */
    public function test_primary_scale_is_taken_from_the_abilities(): void {
        $this->resetAfterTest(true);

        $personabilities = [
            7 => ['value' => 0.5, 'toreport' => true],
            9 => ['value' => -0.5, 'toreport' => true, 'primary' => true],
        ];
        $result = feedback_helper::build_attempt_result($personabilities);

        $this->assertSame(9, $result->get_primary_scale()->scaleid);
        $this->assertFalse($result->get_scale_result(7)->primary);
    }

    /**
     * A display-only problem on one scale must not mask a real measurement
     * problem on another.
     *
     * The legacy implementation returned the message of the FIRST excluded scale
     * it happened to encounter. With a reporting-disabled scale first, it reported
     * the generic "no scales found" and the actual reason (SE below the minimum on
     * the second scale) was never shown.
     *
     * @return void
     */
    public function test_measurement_problem_wins_over_display_only_problem(): void {
        $this->resetAfterTest(true);

        $personabilities = [
            // Display-only problem, listed first.
            7 => [
                'value' => 0.5,
                'toreport' => true,
                'excluded' => true,
                'error' => ['checkbox' => ['scalereportcheckboxinquizsettings' => false]],
            ],
            // The real measurement problem.
            9 => [
                'value' => 0.2,
                'toreport' => true,
                'excluded' => true,
                'error' => ['se' => ['semindefined' => 0.35, 'securrent' => 0.1]],
            ],
        ];
        $result = feedback_helper::build_attempt_result($personabilities);

        $this->assertSame(
            get_string('error:semin', 'local_catquiz', ['semindefined' => 0.35, 'securrent' => 0.1]),
            feedback_helper::get_rejection_reason_string($result, $personabilities),
            'The measurement problem must be reported, not the display-only one.'
        );
        // The legacy helper returns the generic message here - this is the
        // behavioural improvement of deriving the message from the reasons.
        $this->assertSame(
            get_string('noscalesfound', 'local_catquiz'),
            feedback_helper::get_exclusion_reason_string($personabilities)
        );
    }

    /**
     * The split flag: reporting off is signalled by FIELD_NOTREPORTED, without
     * abusing 'excluded' (which now means "the measurement is unusable").
     *
     * @return void
     */
    public function test_split_flag_marks_reporting_without_claiming_a_measurement_problem(): void {
        $this->resetAfterTest(true);

        $personabilities = [
            7 => [
                'value' => 0.5,
                'toreport' => true,
                'primary' => true,
                feedbacksettings::FIELD_NOTREPORTED => true,
                'error' => ['checkbox' => ['scalereportcheckboxinquizsettings' => false]],
            ],
        ];
        $result = feedback_helper::build_attempt_result($personabilities);
        $scale = $result->get_scale_result(7);

        $this->assertFalse($scale->reportable, 'Reporting is switched off.');
        $this->assertTrue($scale->statisticallyvalid, 'But nothing is wrong with the measurement.');
        $this->assertFalse(feedback_helper::is_displayable($result, 7));
        $this->assertContains(\local_catquiz\local\result\scale_result::REASON_REPORTING_DISABLED, $scale->rejectionreasons);
    }

    /**
     * 'excluded' on its own still means the measurement is unusable.
     *
     * @return void
     */
    public function test_excluded_alone_still_means_unusable(): void {
        $this->resetAfterTest(true);

        $personabilities = [
            7 => [
                'value' => 0.5,
                'toreport' => true,
                'excluded' => true,
                'error' => ['se' => ['semindefined' => 0.35, 'securrent' => 0.1]],
            ],
        ];
        $result = feedback_helper::build_attempt_result($personabilities);
        $scale = $result->get_scale_result(7);

        $this->assertFalse($scale->statisticallyvalid);
        $this->assertFalse(feedback_helper::is_displayable($result, 7));
    }

    /**
     * Legacy data written before the split marked the reporting case as
     * 'excluded' as well; such a result must not become invalid retroactively.
     *
     * @return void
     */
    public function test_legacy_reporting_flag_stays_statistically_valid(): void {
        $this->resetAfterTest(true);

        $personabilities = [
            7 => [
                'value' => 0.5,
                'toreport' => true,
                'excluded' => true,
                'error' => ['checkbox' => ['scalereportcheckboxinquizsettings' => false]],
            ],
        ];
        $result = feedback_helper::build_attempt_result($personabilities);

        $this->assertTrue($result->get_scale_result(7)->statisticallyvalid);
        $this->assertFalse($result->get_scale_result(7)->reportable);
    }
}
