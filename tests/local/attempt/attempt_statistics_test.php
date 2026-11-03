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
 * The key figures of an attempt for teachers (issue #13; tab "Statistics & export" since 2026-10-14).
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\local\attempt;

use advanced_testcase;
use context_module;
use question_bank;
use question_engine;
use local_catquiz\catquiz;
use local_catquiz\catscale;

/**
 * Answer counts and times of an attempt (issue #13).
 *
 * Verifies that each question is counted at most once even with several graded
 * steps, that unanswered questions are a separate category from wrong ones, that
 * pilot items are counted apart, and the time of the attempt and per item.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\catquiz::get_attempt_statistics
 * @covers     \local_catquiz\local\attempt\attempt_statistics
 */
final class attempt_statistics_test extends advanced_testcase {
    /**
     * Builds a usage: q1 correct, q2 wrong, q3 started but not answered.
     *
     * @return array [int $attemptid, array $qids, int $uniqueid, context_module $context]
     */
    private function build(): array {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->preventResetByRollback();

        $course = $this->getDataGenerator()->create_course();
        $adaptivequiz = $this->getDataGenerator()->get_plugin_generator('mod_adaptivequiz')
            ->create_instance([
                'course' => $course->id,
                'highestlevel' => 10,
                'lowestlevel' => 1,
                'standarderror' => 14,
                'attemptfeedbackeditor' => ['text' => '', 'format' => FORMAT_MOODLE],
            ]);
        $cm = get_coursemodule_from_instance('adaptivequiz', $adaptivequiz->id);
        $context = context_module::instance($cm->id);

        $generator = $this->getDataGenerator()->get_plugin_generator('local_catquiz');
        $generator->create_catquiz_questions([
            'filepath' => 'local/catquiz/tests/fixtures/quiz-adaptivetest-Simulation-small.xml',
            'filename' => 'quiz-adaptivetest-Simulation-small.xml',
            'courseid' => $course->id,
        ]);
        $generator->create_catquiz_importedcatscales([
            'filepath' => 'local/catquiz/tests/fixtures/simulation_small.csv',
            'filename' => 'simulation_small.csv',
        ]);

        $rootscale = $DB->get_record('local_catquiz_catscales', ['parentid' => 0]);
        $scaleids = array_merge([(int) $rootscale->id], catscale::get_subscale_ids((int) $rootscale->id));
        [$insql, $inparams] = $DB->get_in_or_equal($scaleids, SQL_PARAMS_NAMED);
        $questionids = array_values(array_unique(array_map('intval', $DB->get_fieldset_select(
            'local_catquiz_items',
            'componentid',
            "catscaleid $insql",
            $inparams
        ))));
        $questionids = array_slice($questionids, 0, 3);

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);
        $quba = question_engine::make_questions_usage_by_activity('mod_adaptivequiz', $context);
        $quba->set_preferred_behaviour('deferredfeedback');

        // Question q1 correct, question q2 wrong.
        foreach ([0 => true, 1 => false] as $i => $answercorrect) {
            $question = question_bank::load_question($questionids[$i]);
            $slot = $quba->add_question($question);
            $quba->start_question($slot);
            $correct = $quba->get_correct_response($slot);
            $answer = (int) ($correct['answer'] ?? 0);
            if (!$answercorrect) {
                $answer = $answer >= 1 ? $answer - 1 : $answer + 1;
            }
            $quba->process_action($slot, ['answer' => $answer]);
        }
        $quba->finish_all_questions(time());

        // Question q3 started but not answered -> no graded step -> unanswered.
        $q3 = question_bank::load_question($questionids[2]);
        $slot3 = $quba->add_question($q3);
        $quba->start_question($slot3);

        question_engine::save_questions_usage_by_activity($quba);
        $uniqueid = $quba->get_id();

        $attemptid = (int) $DB->insert_record('adaptivequiz_attempt', (object) [
            'instance' => $adaptivequiz->id,
            'userid' => $student->id,
            'uniqueid' => $uniqueid,
            'attemptstate' => 'complete',
            'attemptstopcriteria' => '',
            'questionsattempted' => 3,
            'difficultysum' => 0,
            'standarderror' => 0,
            'measure' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        return [$attemptid, $questionids, $uniqueid, $context];
    }

    /**
     * A question with several graded steps is returned once, with its last value.
     *
     * @return void
     */
    public function test_multiple_graded_steps_counted_once(): void {
        global $DB;
        [$attemptid, $qids, $uniqueid] = $this->build();

        // Find q1's question attempt and its current max graded step.
        $qaid = (int) $DB->get_field('question_attempts', 'id', [
            'questionusageid' => $uniqueid,
            'questionid' => $qids[0],
        ]);
        $maxseq = (int) $DB->get_field_sql(
            'SELECT MAX(sequencenumber) FROM {question_attempt_steps} WHERE questionattemptid = ?',
            [$qaid]
        );
        // Inject a second, later graded step for the SAME question (a regrade),
        // which the old step-counting query would have double counted.
        $DB->insert_record('question_attempt_steps', (object) [
            'questionattemptid' => $qaid,
            'sequencenumber' => $maxseq + 1,
            'state' => 'gradedpartial',
            'fraction' => 0.5,
            'timecreated' => time(),
            'userid' => 2,
        ]);

        $rows = catquiz::get_attempt_statistics($attemptid);
        // Exactly one row per question (three questions), never per step.
        $this->assertCount(3, $rows, 'Each question must appear exactly once.');
        // Question q1 must carry the fraction of its LAST graded step (the injected 0.5).
        $q1row = array_values(array_filter($rows, fn($r) => (int) $r->questionid === $qids[0]))[0];
        $this->assertEqualsWithDelta(0.5, (float) $q1row->fraction, 0.0001);
    }

    /**
     * Categories are separated and pilot items excluded.
     *
     * @return void
     */
    public function test_pilot_excluded_and_categories_separate(): void {
        [$attemptid, $qids] = $this->build();

        // Question q1 correct -> right; q2 wrong but pilot -> counted apart; q3 unanswered.
        $figures = attempt_statistics::for_attempt($attemptid, [$qids[1]]);

        $this->assertSame(1, $figures->right);
        $this->assertSame(0, $figures->wrong, 'The wrong answer was a pilot and is not counted as wrong.');
        $this->assertSame(1, $figures->pilots);
        $this->assertSame(['right' => 0, 'wrong' => 1, 'partial' => 0, 'unanswered' => 0], $figures->pilot, 'The pilot column.');
        $this->assertSame(1, $figures->unanswered, 'The started-but-unanswered question is unanswered, not wrong.');
        $this->assertSame(0, $figures->partial);
    }

    /**
     * The duration of the attempt and the time per answered productive item, with its spread.
     *
     * @return void
     */
    public function test_times(): void {
        global $DB;
        [$attemptid, $qids, $uniqueid] = $this->build();
        $DB->set_field('adaptivequiz_attempt', 'timecreated', 1000, ['id' => $attemptid]);
        $DB->set_field('adaptivequiz_attempt', 'timefinished', 1250, ['id' => $attemptid]);
        // Question q1 took 30 seconds, q2 90 seconds; q3 has no answer and no time of its own.
        foreach ([[$qids[0], 100, 130], [$qids[1], 200, 290], [$qids[2], 300, 300]] as [$questionid, $first, $last]) {
            $qaid = (int) $DB->get_field('question_attempts', 'id', ['questionusageid' => $uniqueid, 'questionid' => $questionid]);
            $steps = $DB->get_records('question_attempt_steps', ['questionattemptid' => $qaid], 'sequencenumber');
            $first0 = true;
            foreach ($steps as $step) {
                $DB->set_field('question_attempt_steps', 'timecreated', $first0 ? $first : $last, ['id' => $step->id]);
                $first0 = false;
            }
        }

        $figures = attempt_statistics::for_attempt($attemptid, []);

        $this->assertSame(250, $figures->duration);
        $this->assertSame(2, $figures->itemcount);
        $this->assertEqualsWithDelta(60.0, $figures->itemmean, 1e-9);
        $this->assertEqualsWithDelta(sqrt(1800), $figures->itemsd, 1e-9);
        $this->assertSame('1:00 min', attempt_statistics::format_seconds($figures->itemmean));

        // A pilot item has no time in the mean.
        $figures = attempt_statistics::for_attempt($attemptid, [$qids[1]]);
        $this->assertSame(1, $figures->itemcount);
        $this->assertEqualsWithDelta(30.0, $figures->itemmean, 1e-9);
        $this->assertNull($figures->itemsd, 'No spread from one value.');
    }

    /**
     * Mean and standard deviation, and how times read.
     *
     * @return void
     */
    public function test_mean_sd_and_format(): void {
        $this->assertSame([null, null], attempt_statistics::mean_and_sd([]));
        [$mean, $sd] = attempt_statistics::mean_and_sd([10, 20, 30]);
        $this->assertEqualsWithDelta(20.0, $mean, 1e-9);
        $this->assertEqualsWithDelta(10.0, $sd, 1e-9);
        $this->assertSame('2:05 min', attempt_statistics::format_seconds(125));
        $this->assertSame('–', attempt_statistics::format_seconds(null));
    }
}
