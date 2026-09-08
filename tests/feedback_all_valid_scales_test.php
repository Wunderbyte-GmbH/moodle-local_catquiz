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
 * All statistically valid scales reach the feedback, not only the primary one.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;
use local_catquiz\local\result\attempt_result_validator;
use local_catquiz\teststrategy\feedback_helper;

/**
 * Rebuilt from attempt 12357 of a production site.
 *
 * There, four subscales met both configured criteria - three questions each, standard
 * error below the maximum of 1.5 - and none carried an error or an exclusion. The
 * feedback table nevertheless listed only the scale the strategy had marked
 * `primary`.
 *
 * The shape below is the one the debug export shows: a value, a standard error, and
 * on exactly one scale the primary/toreport markers. Whether the other three survive
 * decides whether `primary` is being used as a display gate, which it must not be.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\teststrategy\feedback_helper::is_feedback_eligible
 */
final class feedback_all_valid_scales_test extends advanced_testcase {
    /**
     * The four scales of attempt 12357, with 121 as the primary one.
     *
     * @return array
     */
    private function abilities_of_attempt_12357(): array {
        return [
            121 => [
                'value' => -0.96,
                'se' => 0.7476,
                'primary' => true,
                'toreport' => true,
                'primarybecause' => 'lowestskill',
            ],
            118 => ['value' => 0.00, 'se' => 1.0264],
            125 => ['value' => -0.54, 'se' => 0.6986],
            152 => ['value' => -0.68, 'se' => 0.7750],
        ];
    }

    /**
     * Every valid scale is eligible for the feedback, not only the primary one.
     *
     * @return void
     */
    public function test_all_valid_scales_are_eligible(): void {
        $this->resetAfterTest();

        $abilities = $this->abilities_of_attempt_12357();
        $result = attempt_result_validator::from_personabilities($abilities);

        $eligible = [];
        foreach (array_keys($abilities) as $scaleid) {
            if (feedback_helper::is_feedback_eligible($result, (int) $scaleid)) {
                $eligible[] = (int) $scaleid;
            }
        }

        sort($eligible);

        $this->assertSame(
            [118, 121, 125, 152],
            $eligible,
            'Only the primary scale survived. The primary flag selects which scale '
                . 'is highlighted as the biggest gap; it must not decide which '
                . 'scales appear in the list of valid results at all.'
        );
    }

    /**
     * The primary marker still identifies exactly one scale.
     *
     * Widening the display must not blur the selection - the sentence above the table
     * names one scale, and completion depends on the same flag.
     *
     * @return void
     */
    public function test_exactly_one_scale_stays_primary(): void {
        $this->resetAfterTest();

        $abilities = $this->abilities_of_attempt_12357();
        $result = attempt_result_validator::from_personabilities($abilities);

        $primary = [];
        foreach (array_keys($abilities) as $scaleid) {
            $scale = $result->get_scale_result((int) $scaleid);
            if ($scale !== null && $scale->primary) {
                $primary[] = (int) $scaleid;
            }
        }

        $this->assertSame([121], $primary);
    }

    /**
     * A scale that genuinely fails a criterion stays out.
     *
     * Without this the first test would also pass if the gate were removed entirely.
     *
     * @return void
     */
    public function test_a_failing_scale_is_still_excluded(): void {
        $this->resetAfterTest();

        $abilities = $this->abilities_of_attempt_12357();

        // The shape the export shows for a scale below the minimum question count.
        $abilities[123] = [
            'value' => -1.2,
            'se' => 0.9,
            'error' => ['nminscale' => ['nminscaledefined' => 3, 'nscalecurrent' => 1]],
            'excluded' => true,
        ];

        $result = attempt_result_validator::from_personabilities($abilities);

        $this->assertFalse(
            feedback_helper::is_feedback_eligible($result, 123),
            'A scale below the configured minimum must not be displayed.'
        );
    }
    /**
     * A scale without questions of its own is not shown.
     *
     * Attempt 12357 carried seven scales with the identical value -1.07 - the value of
     * their parent, inherited because they were never asked. They passed every check
     * because build_attempt_result() handed the validator an empty question count, so
     * "measured in this attempt" was true for everything and REASON_NOT_MEASURED
     * could not fire.
     *
     * The count now comes from the attempt progress. A scale with zero questions is
     * excluded: showing it presents a value as a result that was never obtained.
     *
     * @return void
     */
    public function test_unmeasured_scales_are_excluded(): void {
        $this->resetAfterTest();

        $abilities = [
            121 => ['value' => -0.96, 'se' => 0.7476, 'primary' => true, 'toreport' => true],
            // The inherited value: same as its parent, no questions of its own.
            119 => ['value' => -1.07],
            120 => ['value' => -1.07],
        ];

        // Passed the way the validator receives it: three for the measured scale,
        // none for the two that were never asked.
        $result = attempt_result_validator::from_personabilities(
            $abilities,
            [121 => 0.7476],
            [121 => 3, 119 => 0, 120 => 0],
            [],
            121
        );

        $this->assertTrue(
            feedback_helper::is_feedback_eligible($result, 121),
            'The measured scale has to stay.'
        );

        foreach ([119, 120] as $scaleid) {
            $this->assertFalse(
                feedback_helper::is_feedback_eligible($result, $scaleid),
                "Scale $scaleid was never asked; its value belongs to the parent."
            );
        }
    }

    /**
     * The question count reaches the validator at all.
     *
     * The test above passes the counts by hand and would keep passing even if
     * build_attempt_result() still supplied an empty array - which is exactly the
     * defect. This one checks the wiring.
     *
     * @return void
     */
    public function test_question_counts_reach_the_validator(): void {
        global $CFG;

        $this->resetAfterTest();

        $source = file_get_contents(
            $CFG->dirroot . '/local/catquiz/classes/teststrategy/feedback_helper.php'
        );

        $start = strpos($source, 'function build_attempt_result');
        $this->assertNotFalse($start);

        $body = substr($source, $start, 1600);

        $this->assertStringContainsString(
            'get_playedquestions',
            $body,
            'Without the per-scale question count every scale counts as measured and '
                . 'inherited values appear as results.'
        );
        $this->assertStringContainsString('$nbyscale', $body);
    }
}
