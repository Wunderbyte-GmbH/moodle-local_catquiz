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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/tests/fixtures/measured_progress.php');

/**
 * The test's minimum number of questions decides whether there is a final result (issue #141).
 *
 * In production an attempt ended after 3 of at least 12 questions and was shown as a great success:
 * the running estimate of three right answers was taken for the result. A running estimate may
 * exist; a final valid result needs the minimum.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\local\result\attempt_result_validator
 * @covers \local_catquiz\teststrategy\feedback_helper
 */
final class min_questions_validity_test extends advanced_testcase {
    /**
     * Answered items, minimum, and the verdict.
     *
     * @return array
     */
    public static function counts(): array {
        return [
            'production case: 3 of 12' => [3, 12, false],
            'min - 1' => [11, 12, false],
            'min' => [12, 12, true],
            'min + 1' => [13, 12, true],
            'max - 1 with min 12' => [24, 12, true],
            'max with min 12' => [25, 12, true],
            'no minimum set' => [3, 0, true],
            'answered unknown' => [null, 12, true],
        ];
    }

    /**
     * The verdict of the whole attempt; below the minimum every scale is invalid and says why.
     *
     * @param int|null $answered
     * @param int $min
     * @param bool $valid
     * @dataProvider counts
     */
    public function test_minimum_decides_the_final_result(?int $answered, int $min, bool $valid): void {
        $result = attempt_result_validator::from_personabilities(
            [5 => ['value' => 1.1, 'toreport' => true], 7 => ['value' => 0.4, 'toreport' => true]],
            [5 => 0.3, 7 => 0.4],
            [5 => 4, 7 => 3],
            [5 => 0.5, 7 => 0.5],
            5,
            $answered,
            $min
        );

        $this->assertSame($valid, $result->is_valid());
        foreach ($result->get_scale_results() as $scale) {
            $this->assertSame(
                !$valid,
                in_array(scale_result::REASON_MIN_QUESTIONS, $scale->rejectionreasons, true),
                "Scale {$scale->scaleid}"
            );
            $this->assertNotNull($scale->score, 'The running estimate stays for diagnostics and export.');
        }
    }

    /**
     * Three right answers of at least twelve: the reason told is the missing minimum - not
     * "congratulations, all answers right".
     */
    public function test_minimum_is_the_reason_told_before_the_fraction(): void {
        $abilities = [5 => ['value' => 3.9, 'toreport' => true]];
        $result = attempt_result_validator::from_personabilities($abilities, [5 => 0.9], [5 => 3], [5 => 1.0], 5, 3, 12);

        $this->assertFalse($result->is_valid());
        $this->assertSame(scale_result::REASON_MIN_QUESTIONS, $result->get_scale_result(5)->rejectionreasons[0]);
        $this->assertSame(
            get_string('error:nminscale', 'local_catquiz'),
            feedback_helper::get_rejection_reason_string($result, $abilities)
        );
    }

    /**
     * An attempt of a test with a minimum of 12, as finalisation sees it.
     *
     * @param int $answers
     * @return int adaptivequiz_attempt.id
     */
    private function attempt(int $answers): int {
        global $DB;
        $this->setAdminUser();
        $now = time();
        $adaptiveattemptid = (int) $DB->insert_record('adaptivequiz_attempt', (object) [
            'instance' => 1, 'userid' => 2, 'uniqueid' => 4344, 'attemptstate' => 'complete',
            'attemptstopcriteria' => '', 'questionsattempted' => $answers, 'difficultysum' => 0,
            'standarderror' => 0.3, 'measure' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => 2, 'attemptid' => $adaptiveattemptid, 'component' => 'mod_adaptivequiz', 'contextid' => 9,
            'status' => 0, 'endtime' => $now, 'timecreated' => $now, 'timemodified' => $now,
            'json' => json_encode([
                'personabilities_abilities' => [5 => ['value' => 3.9, 'toreport' => true]],
                'se' => [5 => 0.9],
                'primaryscale' => ['id' => 5],
            ]),
        ]);
        local_catquiz_measured_progress(
            $adaptiveattemptid,
            [5 => $answers],
            9,
            ['maxquestionsgroup' => ['catquiz_minquestions' => 12, 'catquiz_maxquestions' => 25]],
            // Half credit: no fraction rule applies, the minimum alone decides.
            [5 => 0.5]
        );
        return $adaptiveattemptid;
    }

    /**
     * Finalisation and the feedback page agree: 3 of 12 is no result, 12 of 12 is one.
     */
    public function test_finalisation_and_feedback_follow_the_minimum(): void {
        global $DB;
        $this->resetAfterTest();

        $short = $this->attempt(3);
        $this->assertFalse(attempt_result_validator::validate($short)->is_valid());

        $catattemptid = (int) $DB->get_field('local_catquiz_attempts', 'id', ['attemptid' => $short]);
        $feedback = feedback_helper::build_attempt_result(
            [5 => ['value' => 3.9, 'toreport' => true, 'primary' => true]],
            ['progress' => progress::load_for_reading($catattemptid)]
        );
        $this->assertFalse($feedback->is_valid(), 'The page must not show a result the finalisation rejects.');

        $full = $this->attempt(12);
        $this->assertTrue(attempt_result_validator::validate($full)->is_valid(), 'Minimum reached: a final result.');
    }
}
