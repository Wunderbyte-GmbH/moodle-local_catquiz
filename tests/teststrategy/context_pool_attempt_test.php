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
use local_catquiz\local\attempt\attempt_finalizer;
use local_catquiz\local\question\question_answer_evaluation;
use local_catquiz\output\catquizstatistics;
use local_catquiz\testenvironment;
use mod_adaptivequiz\local\attempt;
use question_bank;
use question_engine;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');
require_once($CFG->dirroot . '/question/format.php');
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * Whole CAT attempts in a new calibration context, with the items of the old one still there (issue #157, T10-T12 and
 * the strategy integration).
 *
 * The production pattern: the simulation pool is imported into context X; the new context B gets a copy of the items
 * with parameters (catscale::duplicate_testitemparams_for_scale_with_new_contextid(), as the CAT manager does it),
 * with higher item ids; the root scale moves to B; the items of X keep no parameters - pilot rows with the lower ids.
 * Before the fix, the pool of B took these rows: every question counted as a pilot, the estimate froze, the
 * selection was another.
 *
 * Metamorphic for every strategy: the attempt in B with the items of X present plays exactly as the same attempt
 * without them - the same questions, the same end, the same abilities - and every question played is the item of B,
 * calibrated, no pilot.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\catquiz_handler
 * @covers \local_catquiz\teststrategy\preselect_task\updatepersonability
 * @covers \local_catquiz\catquiz::return_sql_for_catscalequestions
 * @covers \local_catquiz\teststrategy\progress::get_contextid
 */
final class context_pool_attempt_test extends advanced_testcase {
    /** @var \stdClass The activity. */
    private \stdClass $adaptivequiz;

    /** @var int The root scale of the pool. */
    private int $rootid;

    /** @var int The context the pool was imported into. */
    private int $old;

    /** @var int The new calibration context. */
    private int $new;

    /** @var \stdClass */
    private \stdClass $course;

    /**
     * Imports the simulation pool, copies it into a new context with parameters and leaves the old items without.
     */
    public function setUp(): void {
        global $DB;
        parent::setUp();
        // The control runs are rolled back in transactions of their own; they must be the outermost ones.
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->course = $this->getDataGenerator()->create_course();
        $this->adaptivequiz = $this->getDataGenerator()->get_plugin_generator('mod_adaptivequiz')->create_instance([
            'highestlevel' => 10, 'lowestlevel' => 1, 'standarderror' => 14, 'course' => $this->course->id,
        ]);
        $this->assertTrue($this->qformat('simulation.xml', $this->course)->importprocess());
        (new testitemimporter())->execute_testitems_csv_import(
            (object) ['delimiter_name' => 'semicolon', 'encoding' => null, 'dateparseformat' => null],
            file_get_contents(__DIR__ . '/../fixtures/simulation.csv')
        );
        $root = $DB->get_record('local_catquiz_catscales', ['parentid' => 0], '*', MUST_EXIST);
        $root->minscalevalue = -10.0;
        $root->maxscalevalue = 10.0;
        $DB->update_record('local_catquiz_catscales', $root);
        $this->rootid = (int) $root->id;
        $this->old = (int) catscale::get_context_id($this->rootid);
        $this->new = $this->context('Recalibration');
        catscale::duplicate_testitemparams_for_scale_with_new_contextid($this->rootid, $this->old, $this->new);
        $DB->set_field('local_catquiz_catscales', 'contextid', $this->new, ['id' => $this->rootid]);
        // The old items keep no parameters: pilot rows with the lower ids.
        $DB->set_field('local_catquiz_items', 'activeparamid', null, ['contextid' => $this->old]);
        $this->assertGreaterThan(
            $DB->get_field_sql('SELECT MAX(id) FROM {local_catquiz_items} WHERE contextid = ?', [$this->old]),
            $DB->get_field_sql('SELECT MIN(id) FROM {local_catquiz_items} WHERE contextid = ?', [$this->new])
        );
        $this->purge_caches();
    }

    /**
     * Unsets the switches of the testing classes again.
     */
    public function tearDown(): void {
        putenv('CATQUIZ_TESTING_SKIP_FEEDBACK');
        parent::tearDown();
    }

    /**
     * Empties every cache, the copies the cache instances of this request keep (static acceleration) included.
     *
     * cache_helper::purge_all() alone left them: a control run filled the pool, and the run after its rollback read
     * the control's pool from memory - the comparison compared a run with itself.
     *
     * @return void
     */
    private function purge_caches(): void {
        \cache_helper::purge_all();
        \cache_factory::reset();
    }

    /**
     * A CAT context.
     *
     * @param string $name
     * @return int
     */
    private function context(string $name): int {
        global $DB;
        return (int) $DB->insert_record('local_catquiz_catcontext', (object) [
            'name' => $name, 'description' => '', 'descriptionformat' => FORMAT_HTML, 'starttimestamp' => 0,
            'endtimestamp' => time() + YEARSECS, 'timecreated' => time(), 'timemodified' => time(), 'usermodified' => 0,
        ]);
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
     * The settings of the test (those of strategy_semantics_test, under which every strategy starts).
     *
     * @param int $strategy
     * @param array $extra pilotratio
     * @return void
     */
    private function settings(int $strategy, array $extra = []): void {
        $data = json_decode(file_get_contents(__DIR__ . '/../fixtures/testenvironment.json'));
        $data->catquiz_catscales = $this->rootid;
        $data->catscaleid = $this->rootid;
        $data->catquiz_catcontext = $this->new;
        foreach ([$this->rootid, ...catscale::get_subscale_ids($this->rootid)] as $scaleid) {
            $data->{'catquiz_subscalecheckbox_' . $scaleid} = true;
        }
        $data->componentid = (string) $this->adaptivequiz->id;
        $data->component = 'mod_adaptivequiz';
        $data->catquiz_selectteststrategy = $strategy;
        $data->maxquestionsgroup->catquiz_minquestions = 8;
        $data->maxquestionsgroup->catquiz_maxquestions = 15;
        $data->maxquestionsscalegroup->catquiz_maxquestionspersubscale = 4;
        $data->maxquestionsscalegroup->catquiz_minquestionspersubscale = $strategy === LOCAL_CATQUIZ_STRATEGY_ALLSUBS ? 0 : 1;
        $data->catquiz_standarderrorgroup->catquiz_standarderror_min = 0.0001;
        $data->catquiz_standarderrorgroup->catquiz_standarderror_max = 1.0;
        $data->catquiz_pp_min_inc = 0.05;
        $data->catquiz_includepilotquestions = isset($extra['pilotratio']);
        $data->catquiz_pilotratio = $extra['pilotratio'] ?? 0;
        $data->json = json_encode($data);
        (new testenvironment($data))->save_or_update();
    }

    /**
     * Plays an attempt: every question answered in its own slot, as the activity does.
     *
     * @param int $limit
     * @param callable|null $between fn(int $i) after the i-th answer - a change during the attempt.
     * @param bool $feedback With the feedback after every answer, which saves the attempt (save_attempt_to_db()).
     * @return array ['ids' => int[], 'status' => ?string, 'attemptid' => int, 'abilities' => array of the steps,
     *               'played' => rows of the progress]
     */
    private function play(int $limit = 40, ?callable $between = null, bool $feedback = false): array {
        global $DB, $USER;
        putenv($feedback ? 'CATQUIZ_TESTING_SKIP_FEEDBACK' : 'CATQUIZ_TESTING_SKIP_FEEDBACK=true');
        catquiz_handler::prepare_attempt_caches();
        $cm = get_coursemodule_from_instance('adaptivequiz', $this->adaptivequiz->id);
        $quba = question_engine::make_questions_usage_by_activity('mod_adaptivequiz', context_module::instance($cm->id));
        $quba->set_preferred_behaviour('deferredfeedback');
        $attemptid = (int) (new attempt($this->adaptivequiz, $USER->id))->get_attempt()->id;
        $ids = [];
        $abilities = [];
        $status = null;
        for ($i = 0; $i < $limit; $i++) {
            $record = $DB->get_record('adaptivequiz_attempt', ['id' => $attemptid], '*', MUST_EXIST);
            $attempt = new attempt($this->adaptivequiz, $record->userid);
            $result = catquiz_handler::fetch_question_id(
                (int) $this->adaptivequiz->id,
                'mod_adaptivequiz',
                $attempt->get_attempt()
            );
            if ($i > 0) {
                $abilities[] = $this->progress($attemptid)->get_abilities();
            }
            if (empty($result[0])) {
                $status = $result[2] ?? null;
                break;
            }
            $ids[] = (int) $result[0];
            $slot = $quba->add_question(question_bank::load_question($result[0]));
            $quba->start_question($slot);
            $answer = $quba->get_correct_response($slot)['answer'];
            $right = $i % 3 !== 1;
            $quba->process_action($slot, ['answer' => $right ? $answer : ($answer >= 1 ? $answer - 1 : $answer + 1)]);
            $quba->finish_all_questions(time());
            (new question_answer_evaluation($quba))->perform($slot);
            question_engine::save_questions_usage_by_activity($quba);
            $record = $attempt->get_attempt();
            $record->timemodified = time();
            $record->questionsattempted = ($record->questionsattempted ?? 0) + 1;
            $DB->update_record('adaptivequiz_attempt', $record);
            if ($i === 0) {
                $attempt->set_quba_id($quba->get_id());
            }
            if ($between) {
                $between($i);
            }
        }
        return [
            'ids' => $ids, 'status' => $status, 'attemptid' => $attemptid, 'abilities' => $abilities,
            'played' => array_values($this->progress($attemptid)->get_playedquestions()),
        ];
    }

    /**
     * The progress of an attempt, read.
     *
     * @param int $attemptid
     * @return progress
     */
    private function progress(int $attemptid): progress {
        return progress::load($attemptid, 'mod_adaptivequiz', $this->new);
    }

    /**
     * Every question played is an item of the context, calibrated, no pilot - with its own scale.
     *
     * @param array $played
     * @param int $contextid
     * @return void
     */
    private function assert_played_in(array $played, int $contextid): void {
        global $DB;
        $this->assertNotEmpty($played);
        foreach ($played as $question) {
            $item = $DB->get_record('local_catquiz_items', ['id' => $question->itemid], '*', MUST_EXIST);
            $this->assertSame($contextid, (int) $item->contextid, "Question {$question->id}: an item of the context.");
            $this->assertSame((int) $question->id, (int) $item->componentid);
            $this->assertSame((int) $item->catscaleid, (int) $question->catscaleid);
            $this->assertNotEmpty($item->activeparamid);
            $this->assertSame((int) $item->activeparamid, (int) $question->activeparamid);
            $this->assertEmpty($question->is_pilot, "Question {$question->id}: calibrated in the context, no pilot.");
        }
    }

    /**
     * Plays the attempt without the old items, inside a transaction that is rolled back afterwards.
     *
     * @return array As play().
     */
    private function play_without_old_items_and_roll_back(): array {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('local_catquiz_items', ['contextid' => $this->old]);
        $this->purge_caches();
        $result = $this->play();
        try {
            $transaction->rollback(new \moodle_exception('rollback'));
        } catch (\moodle_exception $e) {
            $this->assertSame('rollback', $e->errorcode);
        }
        $this->purge_caches();
        return $result;
    }

    /**
     * Every strategy.
     *
     * @return array
     */
    public static function strategies(): array {
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
     * Strategy integration, metamorphic: the old items change nothing - the same questions, the same end, the same
     * abilities as without them; every question played is the calibrated item of the new context.
     *
     * @param int $strategy
     * @dataProvider strategies
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('strategies')]
    public function test_the_old_context_changes_nothing(int $strategy): void {
        $this->settings($strategy);
        $control = $this->play_without_old_items_and_roll_back();
        $this->assertGreaterThanOrEqual(8, count($control['ids']), 'The control plays at least the minimum.');
        $this->assert_played_in($control['played'], $this->new);

        $run = $this->play();
        $this->assertSame($control['ids'], $run['ids'], 'The same questions.');
        $this->assertSame($control['status'], $run['status'], 'The same end.');
        $this->assertCount(count($control['abilities']), $run['abilities']);
        foreach ($control['abilities'] as $step => $abilities) {
            foreach ($abilities as $scaleid => $ability) {
                $this->assertEqualsWithDelta($ability, $run['abilities'][$step][$scaleid], 1e-9, "Step $step, scale $scaleid.");
            }
        }
        $this->assert_played_in($run['played'], $this->new);
        // A productive answer moves the estimate of the root scale.
        $roots = array_map(fn($a) => $a[$this->rootid] ?? null, $run['abilities']);
        $this->assertGreaterThan(1, count(array_unique(array_map(fn($v) => (string) round((float) $v, 6), $roots))));
    }

    /**
     * T11: an answer to a real pilot of the new context leaves the estimate as it was; the productive ones move it.
     */
    public function test_a_pilot_answer_does_not_move_the_estimate(): void {
        global $DB;
        // One real pilot in the new context.
        $pilot = $DB->get_record_sql(
            'SELECT * FROM {local_catquiz_items} WHERE contextid = ? ORDER BY id ASC',
            [$this->new],
            IGNORE_MULTIPLE
        );
        $DB->set_field('local_catquiz_items', 'activeparamid', null, ['id' => $pilot->id]);
        $this->purge_caches();
        $this->settings(LOCAL_CATQUIZ_STRATEGY_FASTEST, ['pilotratio' => 100]);
        $run = $this->play();
        $index = array_search((int) $pilot->componentid, $run['ids'], true);
        $this->assertNotFalse($index, 'The pilot was played.');
        $played = array_values(array_filter($run['played'], fn($q) => (int) $q->id === (int) $pilot->componentid));
        $this->assertNotEmpty($played[0]->is_pilot);
        $this->assertSame((int) $pilot->id, (int) $played[0]->itemid, 'The pilot item of the new context.');
        // The state after the answer to question k was processed is abilities[k].
        $before = $index > 0 ? $run['abilities'][$index - 1] : null;
        $after = $run['abilities'][$index];
        if ($before !== null) {
            foreach ($before as $scaleid => $ability) {
                $this->assertEqualsWithDelta($ability, $after[$scaleid] ?? $ability, 1e-12, "Scale $scaleid moved on a pilot.");
            }
        }
        $productive = array_values(array_filter($run['played'], fn($q) => empty($q->is_pilot)));
        $this->assertGreaterThanOrEqual(7, count($productive));
        $this->assert_played_in($productive, $this->new);
    }

    /**
     * T10, T12: the scale moves to another context during the attempt. The attempt stays in its context - its
     * questions, the parameters of its estimate, the context of its result -, a new attempt plays in the new one,
     * and the statistics take the test information from the context of the results.
     */
    public function test_a_running_attempt_stays_in_its_context(): void {
        global $DB, $USER;
        $this->settings(LOCAL_CATQUIZ_STRATEGY_FASTEST);
        $third = $this->context('Third calibration');
        catscale::duplicate_testitemparams_for_scale_with_new_contextid($this->rootid, $this->new, $third);
        // Other parameters in the third context: every difficulty shifted.
        $DB->execute('UPDATE {local_catquiz_itemparams} SET difficulty = difficulty + 2.5 WHERE contextid = ?', [$third]);
        $this->purge_caches();

        // The same attempt without the move, rolled back: the attempt with the move has to play exactly like it.
        $transaction = $DB->start_delegated_transaction();
        $control = $this->play(40, null, true);
        try {
            $transaction->rollback(new \moodle_exception('rollback'));
        } catch (\moodle_exception $e) {
            $this->assertSame('rollback', $e->errorcode);
        }
        $this->purge_caches();
        $this->resetDebugging();

        $run = $this->play(40, function (int $i) use ($DB, $third) {
            if ($i === 2) {
                // The CAT manager attaches the scale to the third context, during the attempt.
                $DB->set_field('local_catquiz_catscales', 'contextid', $third, ['id' => $this->rootid]);
                $this->purge_caches();
            }
        }, true);
        // The feedback of the fixture settings warns about its scale limits; it is not what this test is about.
        $this->resetDebugging();
        $this->assertGreaterThan(3, count($run['ids']));
        $this->assertSame($control['ids'], $run['ids'], 'The move changes no question of the running attempt.');
        $this->assertSame($control['status'], $run['status']);
        foreach ($control['abilities'] as $step => $abilities) {
            foreach ($abilities as $scaleid => $ability) {
                $this->assertEqualsWithDelta($ability, $run['abilities'][$step][$scaleid], 1e-9, "Step $step, scale $scaleid.");
            }
        }
        $this->assert_played_in($run['played'], $this->new);
        $this->assertSame($this->new, $this->progress($run['attemptid'])->get_contextid());
        $this->assertSame(
            $this->new,
            (int) $DB->get_field('local_catquiz_attempts', 'contextid', ['attemptid' => $run['attemptid']])
        );

        // The end: the results are stored in the context of the attempt.
        $DB->set_field('adaptivequiz_attempt', 'attemptstate', 'complete', ['id' => $run['attemptid']]);
        $DB->set_field('adaptivequiz_attempt', 'timemodified', time(), ['id' => $run['attemptid']]);
        $this->assertTrue(attempt_finalizer::finalize($run['attemptid'], time()));
        $catattempt = $DB->get_record('local_catquiz_attempts', ['attemptid' => $run['attemptid']], '*', MUST_EXIST);
        $contexts = $DB->get_fieldset_select(
            'local_catquiz_personparams',
            'DISTINCT contextid',
            'attemptid = ? AND resultsource = ?',
            [$catattempt->id, 'current']
        );
        $this->assertSame([$this->new], array_map('intval', $contexts));
        $statistics = new catquizstatistics((int) $this->course->id, (int) $this->adaptivequiz->id, $this->rootid);
        $this->assertSame($this->new, $statistics->get_testinfo_contextid(), 'The context of the results, not of the scale.');
        // The feedback of the attempt compares in the context of its result too.
        $feedback = new \local_catquiz\output\attemptfeedback($run['attemptid']);
        $this->assertSame($this->new, (int) (new \ReflectionProperty($feedback, 'contextid'))->getValue($feedback));

        // A new attempt of another person plays in the third context.
        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'student'));
        $next = $this->play(4);
        $this->assert_played_in($next['played'], $third);
        $this->setUser($USER);
    }
}
