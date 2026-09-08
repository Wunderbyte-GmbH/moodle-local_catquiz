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
 * Issue #44/#43 context invariant on the executed recalculation path.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;
use context_module;
use local_catquiz\local\calculation\calculation_mode;
use local_catquiz\local\calculation\calculation_request;
use local_catquiz\local\calculation\calculation_result;
use local_catquiz\local\calculation\calculation_service;
use local_catquiz\local\calculation\calculation_trigger;
use question_bank;
use question_engine;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * The core #44 invariant on the path that actually runs an estimation.
 *
 * The existing task/service tests assert the context invariant only on the
 * no-op path (no new responses -> STATUS_SKIPPED, update_params is never
 * reached). That leaves the decisive guarantee untested: when there ARE new
 * responses and the incremental estimation really runs, it must still keep the
 * scale's active context and only touch item parameters, while the disruptive
 * mode versions the parameters into a NEW context.
 *
 * The disruptive case doubles as the teeth: it proves the fixture actually
 * reaches the context-decision branch (a real estimation ran), so the
 * incremental assertion cannot pass vacuously. Revert the in-place guard in
 * catmodel_info::update_params (make the incremental branch create a context)
 * and test_incremental_run_keeps_context turns red.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\local\calculation\incremental_recalculation
 * @covers     \local_catquiz\local\calculation\disruptive_recalculation
 * @covers     \local_catquiz\catmodel_info::update_params
 */
final class incremental_keeps_context_test extends advanced_testcase {
    /** @var \stdClass The course. */
    private $course;

    /** @var \stdClass The adaptivequiz instance. */
    private $adaptivequiz;

    /**
     * Imports the small simulation scale and seeds graded responses so that
     * catmodel_info::needs_update() reports new responses to estimate.
     *
     * @return array [int $scaleid, int $contextid]
     */
    private function seed_scale_with_new_responses(): array {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->preventResetByRollback();

        $this->course = $this->getDataGenerator()->create_course();
        $this->adaptivequiz = $this->getDataGenerator()->get_plugin_generator('mod_adaptivequiz')
            ->create_instance([
                'course' => $this->course->id,
                'highestlevel' => 10,
                'lowestlevel' => 1,
                'standarderror' => 14,
                'attemptfeedbackeditor' => ['text' => '', 'format' => FORMAT_MOODLE],
            ]);

        $generator = $this->getDataGenerator()->get_plugin_generator('local_catquiz');
        $generator->create_catquiz_questions([
            'filepath' => 'local/catquiz/tests/fixtures/quiz-adaptivetest-Simulation-small.xml',
            'filename' => 'quiz-adaptivetest-Simulation-small.xml',
            'courseid' => $this->course->id,
        ]);
        $generator->create_catquiz_importedcatscales([
            'filepath' => 'local/catquiz/tests/fixtures/simulation_small.csv',
            'filename' => 'simulation_small.csv',
        ]);

        $rootscale = $DB->get_record('local_catquiz_catscales', ['parentid' => 0]);
        $scaleid = (int) $rootscale->id;
        $contextid = (int) catscale::get_context_id($scaleid);
        $this->assertGreaterThan(0, $contextid, 'Scale has no context');

        // The questions that belong to this scale (via local_catquiz_items).
        $scaleids = array_merge([$scaleid], catscale::get_subscale_ids($scaleid));
        [$insql, $inparams] = $DB->get_in_or_equal($scaleids, SQL_PARAMS_NAMED);
        $questionids = $DB->get_fieldset_select(
            'local_catquiz_items',
            'componentid',
            "catscaleid $insql",
            $inparams
        );
        $questionids = array_values(array_unique(array_map('intval', $questionids)));
        $this->assertNotEmpty($questionids, 'Scale has no mapped questions');
        // A small, deterministic subset is enough to make the estimation run.
        $questionids = array_slice($questionids, 0, 6);

        // Widen the active context window and pretend it was last calculated in
        // the past, so the responses we add next count as "new".
        $now = time();
        $DB->set_field('local_catquiz_catcontext', 'starttimestamp', 0, ['id' => $contextid]);
        $DB->set_field('local_catquiz_catcontext', 'endtimestamp', $now + YEARSECS, ['id' => $contextid]);
        $DB->set_field('local_catquiz_catcontext', 'timecalculated', $now - DAYSECS, ['id' => $contextid]);

        // A handful of students each answer the subset with mixed correctness.
        $cm = get_coursemodule_from_instance('adaptivequiz', $this->adaptivequiz->id);
        $modcontext = context_module::instance($cm->id);
        for ($u = 0; $u < 4; $u++) {
            $student = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($student->id, $this->course->id, 'student');
            $this->setUser($student);
            $quba = question_engine::make_questions_usage_by_activity('mod_adaptivequiz', $modcontext);
            $quba->set_preferred_behaviour('deferredfeedback');
            foreach ($questionids as $i => $qid) {
                $question = question_bank::load_question($qid);
                $slot = $quba->add_question($question);
                $quba->start_question($slot);
                $correct = $quba->get_correct_response($slot);
                if (!is_array($correct) || !array_key_exists('answer', $correct)) {
                    continue;
                }
                $answer = (int) $correct['answer'];
                // Alternate correctness across users/items to spread the responses.
                if (($u + $i) % 2 === 1) {
                    $answer = $answer >= 1 ? $answer - 1 : $answer + 1;
                }
                $quba->process_action($slot, ['answer' => $answer]);
            }
            $quba->finish_all_questions($now);
            question_engine::save_questions_usage_by_activity($quba);
        }
        $this->setAdminUser();

        // Precondition: the change detector must see the new responses, otherwise
        // the incremental run would legitimately skip (a different code path).
        $cmi = new catmodel_info();
        $context = catcontext::load_from_db($contextid);
        $this->assertTrue(
            $cmi->needs_update($context, $scaleid),
            'Fixture precondition: needs_update must report the freshly added responses.'
        );

        return [$scaleid, $contextid];
    }

    /**
     * Disruptive recalculation versions parameters into a NEW context.
     *
     * This is also the teeth: it proves the fixture reaches the estimation and
     * the context-decision branch, so the incremental test below cannot pass
     * without an estimation having actually run.
     *
     * @return void
     */
    public function test_disruptive_run_creates_new_context(): void {
        global $DB;
        [$scaleid, $contextid] = $this->seed_scale_with_new_responses();
        $contextsbefore = $DB->count_records('local_catquiz_catcontext');

        $service = new calculation_service();
        ob_start();
        $result = $service->execute(new calculation_request(
            $scaleid,
            $contextid,
            calculation_mode::DISRUPTIVE_RECALCULATION,
            calculation_trigger::MANUAL,
            0
        ));
        ob_end_clean();

        $this->assertSame(
            calculation_result::STATUS_SUCCESS,
            $result->get_status(),
            'The disruptive estimation must complete on the seeded responses.'
        );
        $this->assertGreaterThan(
            $contextsbefore,
            $DB->count_records('local_catquiz_catcontext'),
            'Disruptive recalculation must version the parameters into a new context.'
        );
        $this->assertNotSame(
            $contextid,
            (int) $result->get('targetcontextid'),
            'The disruptive target context must differ from the source context.'
        );
    }

    /**
     * Incremental recalculation WITH new responses keeps the context.
     *
     * The estimation really runs (needs_update is true), yet no new context is
     * created, the scale keeps pointing at the same context, and the person
     * parameters are left untouched.
     *
     * @return void
     */
    public function test_incremental_run_keeps_context(): void {
        global $DB;
        [$scaleid, $contextid] = $this->seed_scale_with_new_responses();
        $contextsbefore = $DB->count_records('local_catquiz_catcontext');
        $personparamsbefore = $DB->get_records('local_catquiz_personparams', ['contextid' => $contextid]);

        $service = new calculation_service();
        ob_start();
        $result = $service->execute(new calculation_request(
            $scaleid,
            $contextid,
            calculation_mode::INCREMENTAL_RECALCULATION,
            calculation_trigger::SCHEDULED,
            0
        ));
        ob_end_clean();

        // It actually ran (not the no-response skip path).
        $this->assertSame(
            calculation_result::STATUS_SUCCESS,
            $result->get_status(),
            'With new responses the incremental run must execute, not skip.'
        );
        // The invariant: no new context, and the target stays the source context.
        $this->assertSame(
            $contextsbefore,
            $DB->count_records('local_catquiz_catcontext'),
            'Incremental recalculation must not create a new context when responses exist.'
        );
        $this->assertSame(
            $contextid,
            (int) $result->get('targetcontextid'),
            'The incremental target context must equal the source context.'
        );
        $this->assertSame(
            $contextid,
            (int) $DB->get_field('local_catquiz_catscales', 'contextid', ['id' => $scaleid]),
            'Incremental recalculation must not change catscale.contextid.'
        );
        // Person parameters are treated as fixed on the incremental path.
        $personparamsafter = $DB->get_records('local_catquiz_personparams', ['contextid' => $contextid]);
        $this->assertSame(
            count($personparamsbefore),
            count($personparamsafter),
            'Incremental recalculation must not add or remove person parameters.'
        );
    }
}
