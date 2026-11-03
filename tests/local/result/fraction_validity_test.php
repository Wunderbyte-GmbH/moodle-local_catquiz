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
use local_catquiz\teststrategy\feedback_helper;
use local_catquiz\teststrategy\progress;

/**
 * The fraction rule of the central validator (issue #140).
 *
 * For a scale measured in this attempt, a fraction of 0 (every productive item wrong) or 1 (every one
 * right) makes the result statistically invalid. The scale keeps score, SE, N and fraction for the
 * report. A missing fraction applies no fraction rule; N = 0 is "not measured", not a fraction case.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\local\result\attempt_result_validator
 * @covers \local_catquiz\local\result\scale_result
 */
final class fraction_validity_test extends advanced_testcase {
    /**
     * Fractions and counts with the verdict each must produce.
     *
     * @return array
     */
    public static function fractions(): array {
        $none = null;
        return [
            'all wrong' => [0.0, 4, false, scale_result::REASON_FRACTION_ALL_INCORRECT],
            'all right' => [1.0, 4, false, scale_result::REASON_FRACTION_ALL_CORRECT],
            'barely above 0' => [0.01, 4, true, $none],
            'half' => [0.5, 4, true, $none],
            'barely below 1' => [0.99, 4, true, $none],
            'no fraction known' => [$none, 4, true, $none],
            'not measured: N = 0' => [0.0, 0, false, scale_result::REASON_NOT_MEASURED],
        ];
    }

    /**
     * Each fraction case gives its verdict; the values stay in the result either way.
     *
     * @dataProvider fractions
     * @param float|null $fraction
     * @param int $n
     * @param bool $valid
     * @param string|null $reason The reason that must be given; null for none.
     */
    public function test_fraction_verdict(?float $fraction, int $n, bool $valid, ?string $reason): void {
        $result = attempt_result_validator::from_personabilities(
            [5 => ['value' => 1.7, 'toreport' => true]],
            [5 => 0.4],
            [5 => $n],
            $fraction === null ? [] : [5 => $fraction]
        );
        $scale = $result->get_scale_result(5);

        $this->assertSame($valid, $scale->valid);
        $this->assertSame($valid, $result->is_valid());
        if ($reason !== null) {
            $this->assertContains($reason, $scale->rejectionreasons);
        }
        if ($n === 0) {
            // Not measured is its own case: no fraction reason on top of it.
            $this->assertNotContains(scale_result::REASON_FRACTION_ALL_INCORRECT, $scale->rejectionreasons);
        }
        if ($valid) {
            $this->assertSame([], $scale->rejectionreasons);
        }
        // Reporting is not validity: the numbers stay.
        $this->assertSame(1.7, $scale->score);
        $this->assertSame(0.4, $scale->standarderror);
        $this->assertSame($n, $scale->n);
        $this->assertSame($fraction, $scale->fraction);
    }

    /**
     * Root and two subscales are judged each on their own fraction.
     */
    public function test_root_and_subscales_are_judged_separately(): void {
        $abilities = [
            1 => ['value' => 0.2, 'toreport' => true],
            2 => ['value' => 2.5, 'toreport' => true],
            3 => ['value' => -0.4, 'toreport' => true],
        ];
        $n = [1 => 8, 2 => 4, 3 => 4];
        $fractions = [1 => 0.6, 2 => 1.0, 3 => 0.25];

        $result = attempt_result_validator::from_personabilities($abilities, [], $n, $fractions, 1);

        $this->assertTrue($result->get_scale_result(1)->valid, 'The root has a mixed fraction.');
        $this->assertFalse($result->get_scale_result(2)->statisticallyvalid, 'All right on a subscale.');
        $this->assertTrue($result->get_scale_result(3)->statisticallyvalid);
        $this->assertTrue($result->is_valid());
        $this->assertSame([1, 3], array_values(array_intersect([1, 2, 3], $result->get_reportable_scale_ids())));

        // The same subscale as the primary scale: the attempt has no valid result.
        $result = attempt_result_validator::from_personabilities($abilities, [], $n, $fractions, 2);
        $this->assertFalse($result->is_valid());
    }

    /**
     * The participant sees the reason for an all-right or all-wrong primary scale.
     */
    public function test_rejection_reason_is_shown(): void {
        $this->resetAfterTest();

        foreach ([1.0 => 'error:fraction1', 0.0 => 'error:fraction0'] as $fraction => $key) {
            $abilities = [5 => ['value' => 0.0, 'toreport' => true]];
            $result = attempt_result_validator::from_personabilities($abilities, [], [5 => 3], [5 => (float) $fraction]);
            $this->assertSame(
                get_string($key, 'local_catquiz'),
                feedback_helper::get_rejection_reason_string($result, $abilities)
            );
        }
    }

    /**
     * Files a finished attempt with the given answers and returns its adaptive attempt id.
     *
     * @param array $answers Questions as [questionid, scaleid, fraction|null, pilot]; a null fraction
     *      is a question shown but not answered.
     * @param array|null $json The stored data of the CAT attempt; null for one reported scale 5.
     * @return int
     */
    private function attempt_with_answers(array $answers, ?array $json = null): int {
        global $DB;
        $this->setAdminUser();
        $now = time();
        $adaptiveattemptid = (int) $DB->insert_record('adaptivequiz_attempt', (object) [
            'instance' => 1, 'userid' => 2, 'uniqueid' => 4343, 'attemptstate' => 'complete',
            'attemptstopcriteria' => '', 'questionsattempted' => count($answers), 'difficultysum' => 0,
            'standarderror' => 0.3, 'measure' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => 2, 'attemptid' => $adaptiveattemptid, 'component' => 'mod_adaptivequiz', 'contextid' => 9,
            'status' => 0, 'endtime' => $now, 'timecreated' => $now, 'timemodified' => $now,
            'json' => json_encode($json ?? [
                'personabilities_abilities' => [5 => ['value' => 2.9, 'toreport' => true]],
                'se' => [5 => 0.35],
                'primaryscale' => ['id' => 5],
            ]),
        ]);

        $progress = progress::load($adaptiveattemptid, 'mod_adaptivequiz', 9, (object) []);
        $played = [];
        $responses = [];
        foreach ($answers as [$qid, $scaleid, $fraction, $pilot]) {
            $played[$qid] = (object) ['id' => $qid, 'catscaleid' => $scaleid, 'is_pilot' => $pilot,
                'fisherinformation' => []];
            if ($fraction !== null) {
                $responses[$qid] = ['questionid' => $qid, 'fraction' => $fraction];
            }
        }
        $state = [
            'responses' => $responses,
            'playedquestions' => $played,
            'lastquestion' => end($played) ?: null,
            'playedquestionsbyscale' => [5 => $played],
        ];
        foreach ($state as $name => $value) {
            $property = new \ReflectionProperty($progress, $name);
            $property->setAccessible(true);
            $property->setValue($progress, $value);
        }
        $progress->save();

        return $adaptiveattemptid;
    }

    /**
     * Only productive answered items enter the fraction; the invalid score is stored with its reason.
     *
     * Every productive item is right. A wrong pilot item and a question shown but not answered must
     * not pull the fraction into (0, 1): the attempt is invalid, and the stored row keeps the score,
     * N, the fraction and the reason.
     */
    public function test_pilot_and_pending_items_do_not_rescue_an_all_right_scale(): void {
        global $DB;
        $this->resetAfterTest();

        $adaptiveattemptid = $this->attempt_with_answers([
            [8201, 5, 1.0, false],
            [8202, 5, 1.0, false],
            [8203, 5, 1.0, false],
            [8204, 5, 0.0, true],
            [8205, 5, null, false],
        ]);

        $result = attempt_result_validator::validate($adaptiveattemptid);
        $scale = $result->get_scale_result(5);

        $this->assertFalse($result->is_valid());
        $this->assertSame(3, $scale->n);
        $this->assertSame(1.0, $scale->fraction);
        $this->assertContains(scale_result::REASON_FRACTION_ALL_CORRECT, $scale->rejectionreasons);

        $catattemptid = (int) $DB->get_field('local_catquiz_attempts', 'id', ['attemptid' => $adaptiveattemptid]);
        personparam_repository::save_attempt_result($catattemptid, 2, 9, $result);
        $rows = personparam_repository::get_for_attempt($catattemptid);
        $this->assertCount(1, $rows);
        $row = reset($rows);
        $this->assertEqualsWithDelta(2.9, (float) $row->ability, 1e-9);
        $this->assertSame(0, (int) $row->isvalid);
        $this->assertEqualsWithDelta(1.0, (float) $row->fraction, 1e-9);
        $this->assertStringContainsString(scale_result::REASON_FRACTION_ALL_CORRECT, $row->validationstatus);
    }

    /**
     * Partial credit counts as such: a mix of partial answers is not all right.
     */
    public function test_partial_credit_keeps_the_scale_valid(): void {
        $this->resetAfterTest();

        $adaptiveattemptid = $this->attempt_with_answers([
            [8301, 5, 1.0, false],
            [8302, 5, 0.5, false],
            [8303, 5, 1.0, false],
        ]);

        $result = attempt_result_validator::validate($adaptiveattemptid);

        $this->assertTrue($result->is_valid());
        $this->assertEqualsWithDelta(2.5 / 3, $result->get_scale_result(5)->fraction, 1e-9);
    }

    /**
     * The feedback path reaches the same verdict as finalisation (issue #140).
     *
     * Abilities without any strategy marks - stored before the strategies applied the rule, or
     * built by a generator of its own - are judged on the fraction of the progress, so the feedback
     * cannot show a scale the stored result calls invalid.
     */
    public function test_feedback_path_applies_the_rule_from_the_progress(): void {
        global $DB;
        $this->resetAfterTest();

        $adaptiveattemptid = $this->attempt_with_answers([
            [8401, 5, 1.0, false],
            [8402, 5, 1.0, false],
        ]);
        $catattemptid = (int) $DB->get_field('local_catquiz_attempts', 'id', ['attemptid' => $adaptiveattemptid]);
        $abilities = [5 => ['value' => 2.9, 'toreport' => true, 'primary' => true]];

        $result = feedback_helper::build_attempt_result($abilities, ['progress' => progress::load_for_reading($catattemptid)]);

        $this->assertFalse(feedback_helper::is_displayable($result, 5));
        $this->assertFalse(feedback_helper::is_feedback_eligible($result, 5));
        $this->assertSame(
            attempt_result_validator::validate($adaptiveattemptid)->is_valid(),
            $result->is_valid()
        );
    }

    /**
     * All wrong, and the strategy reports no scale: the attempt still has its result - invalid, with its reason - and
     * it is stored under the CAT attempt (issue #106, E2E 27.10.2026). Before, the empty list of reported scales hid
     * the scales of the feedback, and nothing at all was stored.
     */
    public function test_an_attempt_whose_strategy_reports_no_scale_still_has_its_result(): void {
        global $DB;
        $this->resetAfterTest();

        $adaptiveattemptid = $this->attempt_with_answers([
            [8501, 5, 0.0, false],
            [8502, 5, 0.0, false],
            [8503, 5, 0.0, false],
        ], [
            'personabilities_abilities' => [],
            'customscalefeedback_abilities' => [5 => ['value' => -2.4, 'toreport' => true, 'primary' => true,
                'error' => ['fraction' => ['fraction' => 0, 'expected' => '0 < f < 1']]]],
            'se' => [5 => 0.37],
        ]);

        $result = attempt_result_validator::validate($adaptiveattemptid);
        $scale = $result->get_scale_result(5);

        $this->assertNotNull($scale, 'The scale of the attempt has a result.');
        $this->assertFalse($result->is_valid());
        $this->assertSame(3, $scale->n);
        $this->assertContains(scale_result::REASON_FRACTION_ALL_INCORRECT, $scale->rejectionreasons);

        $catattemptid = (int) $DB->get_field('local_catquiz_attempts', 'id', ['attemptid' => $adaptiveattemptid]);
        personparam_repository::save_attempt_result($catattemptid, 2, 9, $result);
        $rows = personparam_repository::get_for_attempt($catattemptid);
        $this->assertCount(1, $rows, 'Stored under the CAT attempt.');
        $this->assertSame(0, (int) reset($rows)->isvalid);
    }
}
