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
use context_course;
use context_module;
use core_question\local\bank\question_edit_contexts;
use local_catquiz\catquiz_handler;
use local_catquiz\catscale;
use local_catquiz\importer\testitemimporter;
use local_catquiz\local\question\question_answer_evaluation;
use local_catquiz\local\status;
use local_catquiz\testenvironment;
use mod_adaptivequiz\local\attempt;
use question_bank;
use question_engine;
use question_usage_by_activity;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');
require_once($CFG->dirroot . '/question/format.php');
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * Whole attempts through the real selection, per strategy: when does a test end (issues #134, #135)?
 *
 * The simulation pool - 470 items in a hierarchy of scales below one root - and the real estimator.
 * Each test plays an attempt the way the activity does: fetch a question, answer it in its own slot,
 * count it, fetch the next, until the selection ends the test. What is checked is the length and the
 * reason of the end: FASTEST is not ended by a subscale and not cut down by a per-scale maximum,
 * CLASSIC is a fixed form, ALLSUBS still covers every scale.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\teststrategy\strategy
 * @covers \local_catquiz\teststrategy\preselect_task\filterbystandarderror
 */
final class strategy_semantics_test extends advanced_testcase {
    /** @var question_usage_by_activity The usage the answers go into. */
    private question_usage_by_activity $quba;

    /** @var \stdClass The activity. */
    private \stdClass $adaptivequiz;

    /** @var int The root scale of the pool. */
    private int $rootid;

    /**
     * Imports the simulation pool and creates the activity.
     */
    public function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $this->adaptivequiz = $this->getDataGenerator()->get_plugin_generator('mod_adaptivequiz')->create_instance([
            'highestlevel' => 10, 'lowestlevel' => 1, 'standarderror' => 14, 'course' => $course->id,
        ]);
        $this->assertTrue($this->qformat('simulation.xml', $course)->importprocess());
        $importer = new testitemimporter();
        $importer->execute_testitems_csv_import(
            (object) ['delimiter_name' => 'semicolon', 'encoding' => null, 'dateparseformat' => null],
            file_get_contents(__DIR__ . '/../fixtures/simulation.csv')
        );
        $root = $DB->get_record('local_catquiz_catscales', ['parentid' => 0], '*', MUST_EXIST);
        $root->minscalevalue = -10.0;
        $root->maxscalevalue = 10.0;
        $DB->update_record('local_catquiz_catscales', $root);
        $this->rootid = (int) $root->id;

        $cm = get_coursemodule_from_instance('adaptivequiz', $this->adaptivequiz->id);
        $this->quba = question_engine::make_questions_usage_by_activity('mod_adaptivequiz', context_module::instance($cm->id));
        $this->quba->set_preferred_behaviour('deferredfeedback');
    }

    /**
     * Unsets the switches of the testing classes again.
     */
    public function tearDown(): void {
        $switches = ['USE_TESTING_CLASS_FOR', 'CATQUIZ_TESTING_ABILITY', 'CATQUIZ_TESTING_STANDARDERROR',
            'CATQUIZ_TESTING_SKIP_FEEDBACK'];
        foreach ($switches as $name) {
            putenv($name);
        }
        parent::tearDown();
    }

    /**
     * An XML importer for a fixture file.
     *
     * @param string $filename
     * @param \stdClass $course
     * @return \qformat_xml
     */
    private function qformat(string $filename, \stdClass $course): \qformat_xml {
        $qformat = new \qformat_xml();
        $qformat->setContexts((new question_edit_contexts(context_course::instance($course->id)))->all());
        $qformat->setCourse($course);
        if (class_exists('\core_question\local\bank\question_bank_helper')) {
            $qbank = \core_question\local\bank\question_bank_helper::get_default_open_instance_system_type($course, true);
            $category = question_get_default_category(context_module::instance($qbank->id)->id, true);
        } else {
            $category = question_get_default_category(context_course::instance($course->id)->id, true);
        }
        // Moodle 4.5 has no default category yet; the file names its categories itself.
        if ($category) {
            $qformat->setCategory($category);
        }
        $qformat->setFilename(__DIR__ . '/../fixtures/' . $filename);
        $qformat->setRealfilename($filename);
        $qformat->setMatchgrades('error');
        $qformat->setCatfromfile(1);
        $qformat->setContextfromfile(1);
        $qformat->setStoponerror(1);
        $qformat->setCattofile(1);
        $qformat->setContexttofile(1);
        $qformat->set_display_progress(false);
        return $qformat;
    }

    /**
     * The settings of the test, on top of the fixture's test environment.
     *
     * @param int $strategy
     * @param array $settings minquestions, maxquestions, maxperscale, minperscale, semin, semax, ppmininc
     * @return void
     */
    private function settings(int $strategy, array $settings): void {
        $data = json_decode(file_get_contents(__DIR__ . '/../fixtures/testenvironment.json'));
        $data->catquiz_catscales = $this->rootid;
        $data->catscaleid = $this->rootid;
        foreach ($settings['scales'] ?? [$this->rootid, ...catscale::get_subscale_ids($this->rootid)] as $scaleid) {
            $data->{'catquiz_subscalecheckbox_' . $scaleid} = true;
        }
        $data->componentid = '1';
        $data->component = 'mod_adaptivequiz';
        $data->catquiz_selectteststrategy = $strategy;
        $data->maxquestionsgroup->catquiz_minquestions = $settings['minquestions'];
        $data->maxquestionsgroup->catquiz_maxquestions = $settings['maxquestions'];
        $data->maxquestionsscalegroup->catquiz_maxquestionspersubscale = $settings['maxperscale'] ?? 0;
        $data->maxquestionsscalegroup->catquiz_minquestionspersubscale = $settings['minperscale'] ?? 0;
        $data->catquiz_standarderrorgroup->catquiz_standarderror_min = $settings['semin'];
        $data->catquiz_standarderrorgroup->catquiz_standarderror_max = $settings['semax'] ?? 1.0;
        $data->catquiz_pp_min_inc = $settings['ppmininc'] ?? 0.05;
        $data->catquiz_includepilotquestions = false;
        $data->json = json_encode($data);
        (new testenvironment($data))->save_or_update();
    }

    /**
     * Plays an attempt to its end: every question answered in its own slot, as the activity does.
     *
     * @param int $limit Safety stop; no test here comes near it.
     * @param callable|null $correct fn(int $i): bool - whether the i-th answer is right (default alternating).
     * @param callable|null $afterfirst fn(int $questionid) once the first question is answered - a change during the
     *     attempt.
     * @return array [question ids, status of the end, scale ids of the questions]
     */
    private function play(int $limit = 80, ?callable $correct = null, ?callable $afterfirst = null): array {
        global $DB, $USER;
        putenv('CATQUIZ_TESTING_SKIP_FEEDBACK=true');
        catquiz_handler::prepare_attempt_caches();

        $attemptid = (new attempt($this->adaptivequiz, $USER->id))->get_attempt()->id;
        $ids = [];
        $scales = [];
        $status = null;
        for ($i = 0; $i < $limit; $i++) {
            $record = $DB->get_record('adaptivequiz_attempt', ['id' => $attemptid], '*', MUST_EXIST);
            $attempt = new attempt($this->adaptivequiz, $record->userid);
            $result = catquiz_handler::fetch_question_id(1, 'mod_adaptivequiz', $attempt->get_attempt());
            if (empty($result[0])) {
                $status = $result[2] ?? null;
                break;
            }
            $ids[] = (int) $result[0];
            $scales[] = (int) $DB->get_field('local_catquiz_items', 'catscaleid', ['componentid' => $result[0]]);

            $slot = $this->quba->add_question(question_bank::load_question($result[0]));
            $this->quba->start_question($slot);
            $right = $correct ? $correct($i) : ($i % 2 === 0);
            $answer = $this->quba->get_correct_response($slot)['answer'];
            $this->quba->process_action($slot, ['answer' => $right ? $answer : ($answer >= 1 ? $answer - 1 : $answer + 1)]);
            $this->quba->finish_all_questions(time());
            (new question_answer_evaluation($this->quba))->perform($slot);
            question_engine::save_questions_usage_by_activity($this->quba);

            $record = $attempt->get_attempt();
            $record->timemodified = time();
            $record->questionsattempted = ($record->questionsattempted ?? 0) + 1;
            $DB->update_record('adaptivequiz_attempt', $record);
            if ($i === 0) {
                $attempt->set_quba_id($this->quba->get_id());
                if ($afterfirst) {
                    $afterfirst($ids[0]);
                }
            }
        }
        return [$ids, $status, $scales];
    }

    /**
     * FASTEST, minimum 15, maximum 35, target error 0.35, a per-scale maximum of 3 (issues #134, #135).
     *
     * The production configuration of #134. Subscales reach their target error after a few items each;
     * the per-scale maximum is reached after three. Neither ends the test or shrinks the pool: the
     * attempt runs to at least the minimum, every question is new, and it ends on the precision of
     * the root or on the maximum - never on "no further questions".
     */
    public function test_fastest_runs_to_the_minimum_and_ends_globally(): void {
        $this->settings(LOCAL_CATQUIZ_STRATEGY_FASTEST, [
            'minquestions' => 15, 'maxquestions' => 35, 'semin' => 0.35, 'maxperscale' => 3, 'minperscale' => 2,
        ]);

        [$ids, $status] = $this->play();

        $this->assertGreaterThanOrEqual(15, count($ids), 'Ended before the minimum with ' . $status);
        $this->assertLessThanOrEqual(35, count($ids));
        $this->assertSame(count($ids), count(array_unique($ids)), 'A question was asked twice.');
        $this->assertContains($status, [status::STOP_TARGET_PRECISION, status::ERROR_REACHED_MAXIMUM_QUESTIONS]);
    }

    /**
     * FASTEST past the minimum stops on precision - right after the minimum when the root is precise.
     *
     * With a lax target error the root is precise early; the test still plays the minimum and stops
     * right there, on the precision of the root.
     */
    public function test_fastest_stops_on_root_precision_after_the_minimum(): void {
        $this->settings(LOCAL_CATQUIZ_STRATEGY_FASTEST, [
            'minquestions' => 8, 'maxquestions' => 35, 'semin' => 5.0, 'ppmininc' => 10.0,
        ]);

        [$ids, $status] = $this->play();

        $this->assertCount(8, $ids);
        $this->assertSame(status::STOP_TARGET_PRECISION, $status);
    }

    /**
     * FASTEST never precise enough plays up to the maximum, a hard global stop.
     */
    public function test_fastest_ends_at_the_maximum(): void {
        $this->settings(LOCAL_CATQUIZ_STRATEGY_FASTEST, [
            'minquestions' => 5, 'maxquestions' => 12, 'semin' => 0.0001, 'maxperscale' => 2,
        ]);

        [$ids, $status] = $this->play();

        $this->assertCount(12, $ids);
        $this->assertSame(status::ERROR_REACHED_MAXIMUM_QUESTIONS, $status);
    }

    /**
     * FASTEST over several subscales, end to end (issue #135, DoD 11): the attempt draws from more than one
     * subscale, a subscale that reached the per-scale maximum keeps its items in the pool and gets more, and
     * only the global maximum ends the test.
     */
    public function test_fastest_with_several_subscales(): void {
        global $DB;
        $this->settings(LOCAL_CATQUIZ_STRATEGY_FASTEST, [
            'minquestions' => 10, 'maxquestions' => 25, 'semin' => 0.0001, 'maxperscale' => 2, 'minperscale' => 1,
        ]);

        [$ids, $status, $scales] = $this->play();

        $this->assertCount(25, $ids, 'Neither a subscale nor its maximum ended the test early.');
        $this->assertSame(status::ERROR_REACHED_MAXIMUM_QUESTIONS, $status);
        $this->assertSame(count($ids), count(array_unique($ids)), 'A question was asked twice.');
        $perscale = array_count_values($scales);
        $subscales = array_filter(array_keys($perscale), fn($scaleid) => $scaleid !== $this->rootid);
        $this->assertGreaterThanOrEqual(2, count($subscales), 'Items from several subscales.');
        $this->assertGreaterThan(2, max($perscale), 'The per-scale maximum of 2 did not cut a subscale from the pool.');
        $this->assertGreaterThanOrEqual(2, $DB->count_records('local_catquiz_catscales', ['parentid' => $this->rootid]));
    }

    /**
     * CLASSIC is a fixed form: neither a per-scale maximum nor a precision shortens it (issue #135).
     *
     * Fixed order by item id; with a maximum of 3 per scale and a lax target error the form is still
     * played up to the maximum number of questions.
     */
    public function test_classic_plays_the_whole_form(): void {
        global $DB;
        $this->settings(LOCAL_CATQUIZ_STRATEGY_CLASSIC, [
            'minquestions' => 0, 'maxquestions' => 20, 'semin' => 5.0, 'maxperscale' => 3, 'ppmininc' => 10.0,
        ]);

        [$ids, $status] = $this->play();

        $this->assertCount(20, $ids);
        $this->assertSame(status::ERROR_REACHED_MAXIMUM_QUESTIONS, $status);
        // Exactly the first twenty items of the pool by item id: none skipped, none taken out.
        $form = array_map('intval', array_keys($DB->get_records_menu(
            'local_catquiz_items',
            ['componentname' => 'question'],
            'id ASC',
            'componentid, id',
            0,
            20
        )));
        $this->assertSame($form, $ids, 'The fixed form was changed.');
    }

    /**
     * CLASSIC in the three level hierarchy plays at least its minimum - a second question that differs from
     * the first, no early end while items are left (issue #64).
     */
    public function test_classic_reaches_its_minimum_in_the_hierarchy(): void {
        global $DB;
        $this->settings(LOCAL_CATQUIZ_STRATEGY_CLASSIC, [
            'minquestions' => 8, 'maxquestions' => 15, 'semin' => 0.0001, 'maxperscale' => 0,
        ]);

        [$ids, $status] = $this->play();

        $this->assertNotEquals($ids[0], $ids[1] ?? null, 'The second question equals the first.');
        $this->assertGreaterThanOrEqual(8, count($ids), 'Ended before the minimum with items left: ' . $status);
        $this->assertSame(count($ids), count(array_unique($ids)), 'A question was asked twice.');
        $this->assertSame(status::ERROR_REACHED_MAXIMUM_QUESTIONS, $status);
        $attempted = (int) $DB->get_field('adaptivequiz_attempt', 'questionsattempted', ['instance' => $this->adaptivequiz->id]);
        $this->assertGreaterThanOrEqual(8, $attempted);
    }

    /**
     * Deactivates the CAT items during an attempt, as a manager can; the change invalidates the pool.
     *
     * @param string $where Which items, SQL on local_catquiz_items.
     * @param array $params
     */
    private function deactivate_items(string $where, array $params): void {
        global $DB;
        $DB->set_field_select('local_catquiz_items', 'status', LOCAL_CATQUIZ_TESTITEM_STATUS_INACTIVE, $where, $params);
        \cache_helper::purge_by_event('changesintestitems');
    }

    /**
     * A pool that runs out before the minimum ends with its own status, and that status is stored with the
     * CAT attempt (issue #64): not a general error, not "undefined".
     *
     * Settings no attempt can meet do not start any more (issue #60): the pool runs out during the attempt -
     * every other item is deactivated after the first question.
     */
    public function test_running_out_before_the_minimum_is_stored(): void {
        global $DB;
        $leaf = (int) $DB->get_field('local_catquiz_catscales', 'id', ['name' => 'SimA01']);
        $domain = (int) $DB->get_field('local_catquiz_catscales', 'parentid', ['id' => $leaf]);
        $this->settings(LOCAL_CATQUIZ_STRATEGY_CLASSIC, [
            'minquestions' => 3, 'maxquestions' => 60, 'semin' => 0.0001, 'scales' => [$this->rootid, $domain, $leaf],
        ]);

        [$ids, $status] = $this->play(80, null, fn(int $first) => $this->deactivate_items(
            "componentname = 'question' AND componentid <> ?",
            [$first]
        ));

        $this->assertCount(1, $ids);
        $this->assertSame(status::ERROR_NO_REMAINING_QUESTIONS_BEFORE_MINIMUM, $status);
        $hostattempt = (int) $DB->get_field('adaptivequiz_attempt', 'id', ['instance' => $this->adaptivequiz->id]);
        $stored = (int) $DB->get_field('local_catquiz_attempts', 'status', ['attemptid' => $hostattempt]);
        $this->assertSame(status::to_int(status::ERROR_NO_REMAINING_QUESTIONS_BEFORE_MINIMUM), $stored);
    }

    /**
     * A pool that a filter empties after the check for remaining questions - here the lowest-skill-gap
     * selection, with every scale closed by its maximum of one question - ends like an empty pool: before
     * the minimum with its own status, stored with the CAT attempt (issue #64). It used to end as a
     * general error, stored as "undefined".
     *
     * Settings no attempt can meet do not start any more (issue #60). After the first question only the
     * items of its scale stay active: the pool is not empty, but that scale is closed by its maximum, and the
     * filter empties it.
     */
    public function test_a_pool_emptied_late_ends_before_the_minimum(): void {
        global $DB;
        $this->settings(LOCAL_CATQUIZ_STRATEGY_LOWESTSUB, [
            'minquestions' => 2, 'maxquestions' => 60, 'semin' => 0.0001, 'maxperscale' => 1,
        ]);

        [$ids, $status] = $this->play(80, null, function (int $first) use ($DB) {
            $scaleid = (int) $DB->get_field('local_catquiz_items', 'catscaleid', ['componentid' => $first]);
            $this->deactivate_items("componentname = 'question' AND catscaleid <> ?", [$scaleid]);
            $this->assertGreaterThan(1, $DB->count_records('local_catquiz_items', ['catscaleid' => $scaleid]));
        });

        $this->assertCount(1, $ids);
        $this->assertSame(status::ERROR_NO_REMAINING_QUESTIONS_BEFORE_MINIMUM, $status);
        $hostattempt = (int) $DB->get_field('adaptivequiz_attempt', 'id', ['instance' => $this->adaptivequiz->id]);
        $stored = (int) $DB->get_field('local_catquiz_attempts', 'status', ['attemptid' => $hostattempt]);
        $this->assertSame(status::to_int(status::ERROR_NO_REMAINING_QUESTIONS_BEFORE_MINIMUM), $stored);
    }

    /**
     * Fifty earlier participants with the same ability give the prior a spread of zero (issue #106): the
     * attempt still goes on - a second question, different from the first, up to the minimum - every
     * ability stays a finite number, and the fallback is an event, not a developer notice.
     */
    public function test_identical_past_abilities_do_not_stop_the_selection(): void {
        global $DB, $USER;
        // The CAT context the test runs in: the one of its scale.
        $contextid = (int) catscale::get_context_id($this->rootid);
        $scales = [$this->rootid, ...catscale::get_subscale_ids($this->rootid)];
        for ($i = 0; $i < 50; $i++) {
            $userid = (int) $this->getDataGenerator()->create_user()->id;
            foreach ($scales as $scaleid) {
                $DB->insert_record('local_catquiz_personparams', (object) [
                    'userid' => $userid, 'catscaleid' => $scaleid, 'contextid' => $contextid, 'ability' => 0.2,
                    'standarderror' => 0.5, 'isvalid' => 1, 'timecreated' => time(), 'timemodified' => time(),
                ]);
            }
        }
        foreach ([LOCAL_CATQUIZ_STRATEGY_FASTEST, LOCAL_CATQUIZ_STRATEGY_CLASSIC] as $strategy) {
            \local_catquiz\local\monitoring\numeric_notice::reset();
            $this->setUser($this->getDataGenerator()->create_user());
            $DB->delete_records('adaptivequiz_attempt', ['instance' => $this->adaptivequiz->id]);
            $this->settings($strategy, ['minquestions' => 6, 'maxquestions' => 8, 'semin' => 0.0001]);

            $sink = $this->redirectEvents();
            [$ids, $status] = $this->play();
            $reasons = array_map(
                fn($e) => $e->other['reason'],
                array_filter($sink->get_events(), fn($e) => $e instanceof \local_catquiz\event\numeric_fallback_applied)
            );
            $sink->close();

            $this->assertGreaterThanOrEqual(6, count($ids), "Strategy $strategy ended early: $status");
            $this->assertNotEquals($ids[0], $ids[1]);
            $this->assertSame(count($ids), count(array_unique($ids)));
            $this->assertSame(status::ERROR_REACHED_MAXIMUM_QUESTIONS, $status, "Strategy $strategy");
            $this->assertContains(\local_catquiz\event\numeric_fallback_applied::NO_SPREAD, $reasons, 'Reported.');
            $this->assertNotContains(\local_catquiz\event\numeric_fallback_applied::ABILITY_NOT_FINITE, $reasons);
            $own = $DB->get_fieldset_select('local_catquiz_personparams', 'ability', 'userid = ?', [$USER->id]);
            $this->assertNotEmpty($own);
            foreach ($own as $ability) {
                $this->assertTrue(is_numeric($ability) && is_finite((float) $ability), "Strategy $strategy: $ability");
            }
            // Its result would give the next strategy a past ability of another value.
            $DB->delete_records('local_catquiz_personparams', ['userid' => $USER->id]);
        }
        $this->assertDebuggingNotCalled();
    }

    /**
     * ALLSUBS still covers every scale before it repeats one (issue #135, DoD 14).
     *
     * With a minimum of 1 per scale every scale of the test has its question before any scale gets
     * a third.
     */
    public function test_allsubs_keeps_the_coverage_per_scale(): void {
        $this->settings(LOCAL_CATQUIZ_STRATEGY_ALLSUBS, [
            'minquestions' => 0, 'maxquestions' => 40, 'semin' => 0.0001, 'minperscale' => 1, 'maxperscale' => 0,
        ]);

        [$ids, , $scales] = $this->play();

        $this->assertNotEmpty($ids);
        $counts = [];
        $leaves = array_unique($scales);
        foreach ($scales as $scaleid) {
            $counts[$scaleid] = ($counts[$scaleid] ?? 0) + 1;
            if ($counts[$scaleid] === 3) {
                $missing = array_diff($leaves, array_keys($counts));
                $this->assertSame([], array_values($missing), 'A scale got a third question before another had one.');
            }
        }
    }

    /**
     * The six strategies.
     *
     * @return array
     */
    public static function all_strategies(): array {
        return [
            'FASTEST' => [LOCAL_CATQUIZ_STRATEGY_FASTEST],
            'CLASSIC' => [LOCAL_CATQUIZ_STRATEGY_CLASSIC],
            'ALLSUBS' => [LOCAL_CATQUIZ_STRATEGY_ALLSUBS],
            'LOWESTSUB' => [LOCAL_CATQUIZ_STRATEGY_LOWESTSUB],
            'HIGHESTSUB' => [LOCAL_CATQUIZ_STRATEGY_HIGHESTSUB],
            'RELSUBS' => [LOCAL_CATQUIZ_STRATEGY_RELSUBS],
        ];
    }

    /**
     * Every strategy, a whole attempt: never beyond the maximum, no question twice, and ending before
     * the minimum only with the status that says so (issues #134, #135, #141).
     *
     * @param int $strategy
     * @dataProvider all_strategies
     */
    public function test_every_strategy_keeps_minimum_maximum_and_uniqueness(int $strategy): void {
        // ALLSUBS covers every selected scale: a minimum of 1 per scale in all of them does not fit into 15
        // questions, and since issue #60 (example 2, coveragebudget) such settings do not start at all.
        $minperscale = $strategy === LOCAL_CATQUIZ_STRATEGY_ALLSUBS ? 0 : 1;
        $this->settings($strategy, [
            'minquestions' => 8, 'maxquestions' => 15, 'semin' => 0.0001, 'minperscale' => $minperscale, 'maxperscale' => 4,
        ]);

        [$ids, $status] = $this->play();

        $this->assertLessThanOrEqual(15, count($ids), 'An item beyond the maximum.');
        $this->assertSame(count($ids), count(array_unique($ids)), 'A question was asked twice.');
        if (count($ids) < 8) {
            $this->assertSame(status::ERROR_NO_REMAINING_QUESTIONS_BEFORE_MINIMUM, $status, 'Ended before the minimum.');
        }
        if (count($ids) === 15) {
            $this->assertSame(status::ERROR_REACHED_MAXIMUM_QUESTIONS, $status);
        }
    }

    /**
     * The order in which the shared pool cache was filled does not change an attempt (issue #147): the same
     * answers give the same questions, the same end and the same abilities, whether the cache was filled first
     * without an order or by difficulty.
     */
    public function test_the_pool_cache_order_does_not_change_the_attempt(): void {
        global $DB, $USER;
        $this->settings(LOCAL_CATQUIZ_STRATEGY_FASTEST, ['minquestions' => 8, 'maxquestions' => 12, 'semin' => 0.0001]);
        $contextid = (int) catscale::get_context_id($this->rootid);
        // Ties an order could decide: difficulties in pairs +d, -d, the item with +d the one with the lower id, all
        // with the same discrimination. At an ability of 0 both of a pair give the same information; the pool by id
        // lists +d first, the pool by difficulty -d. Today the scores break ties by question id
        // (strategy*score), and no step reads the order of the pool: this test is green also with the cache before
        // the fix - the effect of #147 on an attempt is nil (doc/runtime-pool-cache.md). It guards that a step which
        // lets the order decide still gets the order it asks for.
        $itemids = array_keys($DB->get_records('local_catquiz_items', ['contextid' => $contextid], 'id', 'id'));
        foreach (array_values($itemids) as $k => $itemid) {
            $difficulty = ($k % 2 === 0 ? 1 : -1) * (0.3 + intdiv($k, 2) * 0.4);
            $DB->execute(
                'UPDATE {local_catquiz_itemparams} SET difficulty = :d, discrimination = 1, guessing = 0 WHERE itemid = :i',
                ['d' => $difficulty, 'i' => $itemid]
            );
        }
        \cache_helper::purge_all();
        $scales = [$this->rootid, ...catscale::get_subscale_ids($this->rootid)];
        $cm = get_coursemodule_from_instance('adaptivequiz', $this->adaptivequiz->id);
        $runs = [];
        foreach ([null, 'difficulty'] as $order) {
            \cache::make('local_catquiz', 'runtimepool')->purge();
            $this->setUser($this->getDataGenerator()->create_user());
            // Whoever comes first fills the shared cache - here in this order.
            (new catscale($this->rootid))->get_testitems($contextid, true, $order, $scales);
            $this->quba = question_engine::make_questions_usage_by_activity('mod_adaptivequiz', context_module::instance($cm->id));
            $this->quba->set_preferred_behaviour('deferredfeedback');
            [$ids, $status] = $this->play();
            // The latest ability of each scale; personparams keep their history, one row per update.
            $abilities = [];
            $rows = $DB->get_records('local_catquiz_personparams', ['userid' => $USER->id], 'id', 'id, catscaleid, ability');
            foreach ($rows as $row) {
                $abilities[(int) $row->catscaleid] = round((float) $row->ability, 6);
            }
            ksort($abilities);
            $runs[$order ?? 'none'] = [$ids, $status, $abilities];
        }
        $this->assertNotEmpty($runs['none'][0]);
        $this->assertSame($runs['none'], $runs['difficulty']);
    }
}
