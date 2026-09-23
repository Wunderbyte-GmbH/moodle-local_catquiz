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
 * Issue #12: the question rendering endpoint validates slot and access.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;
use context_module;
use question_bank;
use question_engine;
use ReflectionMethod;
use local_catquiz\external\render_question_with_response;

/**
 * Slot mapping and access rights of render_question_with_response (issue #12).
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\external\render_question_with_response
 */
final class render_question_with_response_test extends advanced_testcase {
    /**
     * Invokes the private render_question via reflection.
     *
     * @param int $slot
     * @param int $attemptid
     * @param int $questionattemptid
     * @return array
     */
    private function render(int $slot, int $attemptid, int $questionattemptid = 0): array {
        $method = new ReflectionMethod(render_question_with_response::class, 'render_question');
        $method->setAccessible(true);
        return $method->invoke(null, $slot, $attemptid, $questionattemptid);
    }

    /**
     * Builds an adaptivequiz attempt owned by a student with a real question usage.
     *
     * @return array [int $attemptid, int $slot, int $qaid, \stdClass $owner, context_module $context]
     */
    private function build_attempt(): array {
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

        // Minimal test environment: the endpoint only reads catquiz_showquestion
        // and catquiz_questionfeedbacksettings from the saved settings.
        $json = json_encode([
            'catquiz_showquestion' => 1,
            'catquiz_questionfeedbacksettings' => [
                'catquiz_showquestionresponse' => 1,
                'catquiz_showquestioncorrectresponse' => 1,
                'catquiz_showquestionfeedback' => 1,
            ],
        ]);
        $DB->insert_record('local_catquiz_tests', (object) [
            'name' => 'Test env',
            'component' => 'mod_adaptivequiz',
            'componentid' => $adaptivequiz->id,
            'json' => $json,
            'status' => 0,
            'parentid' => 0,
            'courseid' => $course->id,
            'usermodified' => 2,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        // A real question usage with two questions from the imported scale.
        $rootscale = $DB->get_record('local_catquiz_catscales', ['parentid' => 0]);
        $scaleids = array_merge([(int) $rootscale->id], catscale::get_subscale_ids((int) $rootscale->id));
        [$insql, $inparams] = $DB->get_in_or_equal($scaleids, SQL_PARAMS_NAMED);
        $questionids = array_values(array_unique(array_map('intval', $DB->get_fieldset_select(
            'local_catquiz_items',
            'componentid',
            "catscaleid $insql",
            $inparams
        ))));
        $questionids = array_slice($questionids, 0, 2);

        $owner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($owner->id, $course->id, 'student');
        $this->setUser($owner);
        $quba = question_engine::make_questions_usage_by_activity('mod_adaptivequiz', $context);
        $quba->set_preferred_behaviour('deferredfeedback');
        $slot = 0;
        foreach ($questionids as $qid) {
            $question = question_bank::load_question($qid);
            $slot = $quba->add_question($question);
            $quba->start_question($slot);
            $correct = $quba->get_correct_response($slot);
            if (is_array($correct) && array_key_exists('answer', $correct)) {
                $quba->process_action($slot, ['answer' => (int) $correct['answer']]);
            }
        }
        $quba->finish_all_questions(time());
        question_engine::save_questions_usage_by_activity($quba);

        $qaid = (int) $quba->get_question_attempt($slot)->get_database_id();

        $attemptid = (int) $DB->insert_record('adaptivequiz_attempt', (object) [
            'instance' => $adaptivequiz->id,
            'userid' => $owner->id,
            'uniqueid' => $quba->get_id(),
            'attemptstate' => 'complete',
            'attemptstopcriteria' => '',
            'questionsattempted' => count($questionids),
            'difficultysum' => 0,
            'standarderror' => 0,
            'measure' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        return [$attemptid, $slot, $qaid, $owner, $context];
    }

    /**
     * The owner may render their own question and gets real QUBA HTML.
     *
     * @return void
     */
    public function test_owner_can_render_matching_slot(): void {
        [$attemptid, $slot, $qaid, $owner] = $this->build_attempt();
        $this->setUser($owner);
        $result = $this->render($slot, $attemptid, $qaid);
        $this->assertArrayHasKey('body', $result);
        $this->assertNotEmpty($result['body']);
    }

    /**
     * A participant may not render another user's attempt.
     *
     * @return void
     */
    public function test_foreign_attempt_is_denied(): void {
        [$attemptid, $slot, $qaid, , $context] = $this->build_attempt();
        // Another enrolled participant (not the owner) must not read the attempt.
        $courseid = $context->get_course_context()->instanceid;
        $other = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($other->id, $courseid, 'student');
        $this->setUser($other);
        $this->expectException(\required_capability_exception::class);
        $this->render($slot, $attemptid, $qaid);
    }

    /**
     * An out-of-range slot is rejected.
     *
     * @return void
     */
    public function test_invalid_slot_is_rejected(): void {
        [$attemptid, , , $owner] = $this->build_attempt();
        $this->setUser($owner);
        $this->expectException(\moodle_exception::class);
        $this->render(999, $attemptid, 0);
    }

    /**
     * A slot that does not map to the supplied question attempt id is rejected.
     *
     * @return void
     */
    public function test_slot_questionattemptid_mismatch_is_rejected(): void {
        [$attemptid, $slot, $qaid, $owner] = $this->build_attempt();
        $this->setUser($owner);
        $this->expectException(\moodle_exception::class);
        // A wrong question attempt id for this slot must be refused.
        $this->render($slot, $attemptid, $qaid + 9999);
    }
}
