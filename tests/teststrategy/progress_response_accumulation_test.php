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
 * Regression test for the person-ability estimator's INPUT: the response set.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy;

use advanced_testcase;
use question_bank;
use question_engine;

/**
 * Regression test for the person-ability estimator's INPUT: the response set.
 *
 * The estimator maths (catcalc::estimate_person_ability) is exhaustively unit
 * tested and correct. A frozen ability trajectory - the estimate not moving while
 * answers keep coming - is therefore a bug in the layer that ASSEMBLES the
 * estimator's input: progress::update_cached_responses(), which adds the last
 * answered response and de-duplicates by question id. Both existing "trajectory"
 * tests bypass this layer (one covers estimate_person_ability directly, the other
 * stubs progress::get_user_responses), so the accumulation was never guarded.
 *
 * This drives the REAL accumulation against a real question usage, answering and
 * grading one question at a time, and asserts the response set grows strictly by
 * one and always ends on the just-answered question - so it can never freeze.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\teststrategy\progress::update_cached_responses
 */
final class progress_response_accumulation_test extends advanced_testcase {
    /**
     * The response set grows by exactly one per answered question and never
     * plateaus, so the estimator receives fresh input each step.
     */
    public function test_responses_accumulate_and_never_freeze(): void {
        global $DB;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $qgenerator = $generator->get_plugin_generator('core_question');
        $course = $generator->create_course();
        $user = $generator->create_user();
        $this->setUser($user);

        $context = \context_course::instance($course->id);
        $qcat = $qgenerator->create_question_category(['contextid' => $context->id]);

        $questions = [];
        for ($i = 0; $i < 6; $i++) {
            $questions[] = $qgenerator->create_question('truefalse', null, ['category' => $qcat->id]);
        }
        // Mixed pattern: one wrong, the rest correct (truefalse correct = true = 1).
        $answers = [0, 1, 1, 1, 0, 1];

        $quba = question_engine::make_questions_usage_by_activity('mod_adaptivequiz', $context);
        $quba->set_preferred_behaviour('deferredfeedback');

        // Add and start the first question so the usage can be saved and get an id.
        $slots = [];
        $slots[0] = $quba->add_question(question_bank::load_question($questions[0]->id));
        $quba->start_question($slots[0]);
        question_engine::save_questions_usage_by_activity($quba);

        $attemptid = $DB->insert_record('adaptivequiz_attempt', (object) [
            'instance' => 1, 'userid' => $user->id, 'uniqueid' => $quba->get_id(),
            'attemptstate' => 'inprogress', 'attemptstopcriteria' => '', 'questionsattempted' => 0,
            'difficultysum' => 0, 'standarderror' => 0.3, 'measure' => 0, 'timefinished' => null,
            'timecreated' => time(), 'timemodified' => time(),
        ]);

        $progress = progress::load($attemptid, 'mod_adaptivequiz', $context->id, (object) []);

        $playedref = new \ReflectionProperty(progress::class, 'playedquestions');
        $playedref->setAccessible(true);
        $played = [];

        for ($n = 0; $n < count($questions); $n++) {
            if ($n > 0) {
                $slots[$n] = $quba->add_question(question_bank::load_question($questions[$n]->id));
                $quba->start_question($slots[$n]);
            }
            $quba->process_action($slots[$n], ['answer' => $answers[$n]]);
            $quba->finish_question($slots[$n]);
            question_engine::save_questions_usage_by_activity($quba);

            // Advance the played-question count (drives the response-cache key),
            // bypassing the scale-hierarchy machinery not under test here.
            $played[$questions[$n]->id] = (object) ['id' => $questions[$n]->id, 'is_pilot' => false];
            $playedref->setValue($progress, $played);

            $progress->update_cached_responses();
            $responses = $progress->get_user_responses();

            $this->assertCount(
                $n + 1,
                $responses,
                'After answering question ' . ($n + 1) . ' the response set must hold '
                    . ($n + 1) . ' responses, not ' . count($responses)
                    . ' - a frozen count means the estimator input stopped growing.'
            );
            $last = $progress->get_last_response();
            $this->assertEquals(
                (int) $questions[$n]->id,
                (int) $last['questionid'],
                'The newest response must be the question just answered, not a stale one.'
            );
        }
    }
}
