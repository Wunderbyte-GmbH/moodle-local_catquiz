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

namespace local_catquiz\teststrategy;

use advanced_testcase;
use local_catquiz\catquiz_handler;
use local_catquiz\local\question\question_answer_evaluation;
use mod_adaptivequiz\local\attempt;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');

/**
 * A feedback area that cannot be built does not end the attempt.
 *
 * The feedback data are updated with every answer, inside the selection of the next question. A
 * classical test on a scale without feedback ranges stopped after its first answer with "Your test
 * was ended by a technical problem": the colour bar of the overview threw, and the exception ended
 * the selection. Found by the routing scenario of catquiz_routing.feature.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\output\attemptfeedback
 */
final class feedback_area_failure_test extends advanced_testcase {
    /**
     * A classical test without feedback ranges runs on after its first answer.
     */
    public function test_a_failing_feedback_area_does_not_end_the_attempt(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        putenv('CATQUIZ_TESTING_SKIP_FEEDBACK');

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_catquiz');
        $generator->create_catquiz_questions([
            'filepath' => 'local/catquiz/tests/fixtures/quiz-adaptivetest-Simulation-small.xml',
            'filename' => 'quiz-adaptivetest-Simulation-small.xml',
            'courseid' => $course->id,
        ]);
        $generator->create_catquiz_importedcatscales(['filepath' => '', 'filename' => 'simulation_small.csv']);
        $module = $this->getDataGenerator()->create_module('adaptivequiz', ['course' => $course->id]);
        // The fixture's settings name feedback ranges for scale ids of another site: none for these scales.
        $generator->create_catquiz_testsettings([
            'courseid' => $course->id, 'adaptivecatquizid' => $module->id, 'catmodel' => 'catquiz',
            'catscalesid' => $DB->get_field('local_catquiz_catscales', 'id', ['name' => 'Simulation']),
            'cateststrategyid' => LOCAL_CATQUIZ_STRATEGY_CLASSIC,
            'catquiz_minquestions' => 3, 'catquiz_maxquestions' => 3,
            'catquiz_standarderror_min' => 0.0, 'catquiz_standarderror_max' => 1000.0,
        ]);
        $adaptivequiz = $DB->get_record('adaptivequiz', ['id' => $module->id]);
        $cm = get_coursemodule_from_instance('adaptivequiz', $module->id);
        $quba = \question_engine::make_questions_usage_by_activity('mod_adaptivequiz', \context_module::instance($cm->id));
        $quba->set_preferred_behaviour('deferredfeedback');
        $attemptid = (new attempt($adaptivequiz, $USER->id))->get_attempt()->id;

        $ids = [];
        for ($i = 0; $i < 3; $i++) {
            $attempt = new attempt($adaptivequiz, $DB->get_field('adaptivequiz_attempt', 'userid', ['id' => $attemptid]));
            $result = catquiz_handler::fetch_question_id($module->id, 'mod_adaptivequiz', $attempt->get_attempt());
            $this->assertNotEmpty($result[0], 'Ended after ' . count($ids) . ' answers: ' . json_encode($result));
            $ids[] = (int) $result[0];

            $slot = $quba->add_question(\question_bank::load_question($result[0]));
            $quba->start_question($slot);
            $quba->process_action($slot, ['answer' => $quba->get_correct_response($slot)['answer']]);
            $quba->finish_all_questions(time());
            (new question_answer_evaluation($quba))->perform($slot);
            \question_engine::save_questions_usage_by_activity($quba);
            $record = $attempt->get_attempt();
            $record->questionsattempted++;
            $DB->update_record('adaptivequiz_attempt', $record);
            if ($i === 0) {
                $attempt->set_quba_id($quba->get_id());
            }
        }

        $this->assertCount(3, array_unique($ids));
        // The area that could not be built is named for the developer, with every answer.
        $messages = array_map(fn($debug) => $debug->message, $this->getDebuggingMessages());
        $this->assertNotEmpty($messages);
        foreach ($messages as $message) {
            $this->assertStringStartsWith('Feedback area comparetotestaverage left out:', $message);
        }
        $this->resetDebugging();
    }
}
