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
use local_catquiz\local\attempt_cache;
use local_catquiz\local\question\question_answer_evaluation;
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
 * Two attempts open in one session (issue #146).
 *
 * The start of an attempt purged the session cache of the attempts - the item pool, the progress and
 * the feedback of every attempt open in the session went with it. A second attempt started while the
 * first one runs must leave the first as it is: it goes on where it was, without a question twice, and
 * - when the second shows nothing in between - with the same questions as alone.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\catquiz_handler::prepare_attempt_caches
 * @covers \local_catquiz\catscale::get_testitems
 * @covers \local_catquiz\local\attempt_cache
 */
final class parallel_attempts_test extends advanced_testcase {
    /** @var \stdClass[] The two activities, by the component id of their settings. */
    private array $activities = [];

    /** @var int The root scale of the pool. */
    private int $rootid;

    /**
     * Imports the simulation pool and creates two activities with the same settings.
     */
    public function setUp(): void {
        global $DB;
        parent::setUp();
        // The reference run is rolled back in a transaction of its own; it must be the outermost one.
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        foreach ([1, 2] as $componentid) {
            $this->activities[$componentid] = $this->getDataGenerator()->get_plugin_generator('mod_adaptivequiz')
                ->create_instance(['highestlevel' => 10, 'lowestlevel' => 1, 'standarderror' => 14, 'course' => $course->id]);
        }
        $this->assertTrue($this->qformat('simulation.xml', $course)->importprocess());
        (new testitemimporter())->execute_testitems_csv_import(
            (object) ['delimiter_name' => 'semicolon', 'encoding' => null, 'dateparseformat' => null],
            file_get_contents(__DIR__ . '/../fixtures/simulation.csv')
        );
        $root = $DB->get_record('local_catquiz_catscales', ['parentid' => 0], '*', MUST_EXIST);
        $root->minscalevalue = -10.0;
        $root->maxscalevalue = 10.0;
        $DB->update_record('local_catquiz_catscales', $root);
        $this->rootid = (int) $root->id;
        foreach ([1, 2] as $componentid) {
            $this->settings($componentid);
        }
    }

    /**
     * Unsets the switches of the testing classes again.
     */
    public function tearDown(): void {
        putenv('CATQUIZ_TESTING_SKIP_FEEDBACK');
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
     * The settings of a test, on top of the fixture's test environment.
     *
     * @param int $componentid
     */
    private function settings(int $componentid): void {
        $data = json_decode(file_get_contents(__DIR__ . '/../fixtures/testenvironment.json'));
        $data->catquiz_catscales = $this->rootid;
        $data->catscaleid = $this->rootid;
        foreach ([$this->rootid, ...catscale::get_subscale_ids($this->rootid)] as $scaleid) {
            $data->{'catquiz_subscalecheckbox_' . $scaleid} = true;
        }
        $data->componentid = (string) $componentid;
        $data->component = 'mod_adaptivequiz';
        $data->catquiz_selectteststrategy = LOCAL_CATQUIZ_STRATEGY_FASTEST;
        $data->maxquestionsgroup->catquiz_minquestions = 8;
        $data->maxquestionsgroup->catquiz_maxquestions = 12;
        $data->maxquestionsscalegroup->catquiz_maxquestionspersubscale = 0;
        $data->maxquestionsscalegroup->catquiz_minquestionspersubscale = 0;
        $data->catquiz_standarderrorgroup->catquiz_standarderror_min = 0.3;
        $data->catquiz_standarderrorgroup->catquiz_standarderror_max = 1.0;
        $data->catquiz_pp_min_inc = 0.05;
        $data->catquiz_includepilotquestions = false;
        $data->json = json_encode($data);
        (new testenvironment($data))->save_or_update();
    }

    /**
     * Starts an attempt at an activity, as the activity does.
     *
     * @param int $componentid
     * @return \stdClass The run: activity, attempt id, usage, questions so far, end.
     */
    private function start(int $componentid): \stdClass {
        global $USER;
        putenv('CATQUIZ_TESTING_SKIP_FEEDBACK=true');
        $adaptivequiz = $this->activities[$componentid];
        $cm = get_coursemodule_from_instance('adaptivequiz', $adaptivequiz->id);
        $quba = question_engine::make_questions_usage_by_activity('mod_adaptivequiz', context_module::instance($cm->id));
        $quba->set_preferred_behaviour('deferredfeedback');
        $attemptid = (int) (new attempt($adaptivequiz, $USER->id))->get_attempt()->id;
        catquiz_handler::prepare_attempt_caches($attemptid);
        return (object) [
            'componentid' => $componentid, 'adaptivequiz' => $adaptivequiz, 'attemptid' => $attemptid,
            'quba' => $quba, 'ids' => [], 'status' => null, 'ended' => false,
        ];
    }

    /**
     * Plays questions of a run, every question answered in its own slot, as the activity does.
     *
     * @param \stdClass $run
     * @param int $questions How many at most; 0 to the end.
     */
    private function play(\stdClass $run, int $questions = 0): void {
        global $DB;
        for ($n = 0; !$run->ended && ($questions === 0 || $n < $questions); $n++) {
            $record = $DB->get_record('adaptivequiz_attempt', ['id' => $run->attemptid], '*', MUST_EXIST);
            $attempt = new attempt($run->adaptivequiz, $record->userid);
            $result = catquiz_handler::fetch_question_id($run->componentid, 'mod_adaptivequiz', $attempt->get_attempt());
            if (empty($result[0]) || count($run->ids) >= 30) {
                $run->status = $result[2] ?? null;
                $run->ended = true;
                break;
            }
            $i = count($run->ids);
            $run->ids[] = (int) $result[0];

            $slot = $run->quba->add_question(question_bank::load_question($result[0]));
            $run->quba->start_question($slot);
            $answer = $run->quba->get_correct_response($slot)['answer'];
            $right = $i % 3 !== 1;
            $run->quba->process_action($slot, ['answer' => $right ? $answer : ($answer >= 1 ? $answer - 1 : $answer + 1)]);
            $run->quba->finish_all_questions(time());
            (new question_answer_evaluation($run->quba))->perform($slot);
            question_engine::save_questions_usage_by_activity($run->quba);

            $record = $attempt->get_attempt();
            $record->timemodified = time();
            $record->questionsattempted = ($record->questionsattempted ?? 0) + 1;
            $DB->update_record('adaptivequiz_attempt', $record);
            if ($i === 0) {
                $attempt->set_quba_id($run->quba->get_id());
            }
        }
    }

    /**
     * The session keys of the state of a run that a new attempt must leave alone.
     *
     * @param \stdClass $run
     * @return array key => value
     */
    private function state_of(\stdClass $run): array {
        global $DB;
        $catattemptid = (int) $DB->get_field('local_catquiz_attempts', 'id', ['attemptid' => $run->attemptid]);
        $cache = attempt_cache::cache();
        $keys = [
            'progress_cat_' . $catattemptid,
            attempt_cache::key('abilitybeforeattempt', $run->attemptid),
            attempt_cache::key('totalnumberoftestitems', $run->attemptid),
            attempt_cache::key('starttime', $run->attemptid),
        ];
        $state = [];
        foreach ($keys as $key) {
            $state[$key] = $cache->get($key);
        }
        return $state;
    }

    /**
     * A second attempt started - and nothing shown in it - while the first runs: the first keeps its state
     * and goes on with the same questions as alone.
     */
    public function test_starting_a_second_attempt_leaves_the_first_as_it_is(): void {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        \cache_helper::purge_all();
        $alone = $this->start(1);
        $this->play($alone);
        try {
            $transaction->rollback(new \moodle_exception('rollback'));
        } catch (\moodle_exception $e) {
            $this->assertSame('rollback', $e->errorcode);
        }
        \cache_helper::purge_all();
        $this->assertGreaterThanOrEqual(8, count($alone->ids));

        $first = $this->start(1);
        $this->play($first, 3);
        $before = $this->state_of($first);
        foreach ($before as $key => $value) {
            $this->assertNotFalse($value, $key . ' is there before the second attempt starts.');
        }

        $second = $this->start(2);

        $this->assertEquals($before, $this->state_of($first), 'The start of the second attempt took nothing of the first.');
        $this->play($first);
        $this->assertSame($alone->ids, $first->ids);
        $this->assertSame($alone->status, $first->status);
        $this->assertNotSame($first->attemptid, $second->attemptid);
    }

    /**
     * Both attempts played in turns: neither shows a question twice, and the first goes on where it was -
     * its progress has every question it showed.
     */
    public function test_two_attempts_played_in_turns(): void {
        global $DB;
        \cache_helper::purge_all();
        $first = $this->start(1);
        $this->play($first, 3);
        $second = $this->start(2);
        $this->play($second, 2);
        $this->play($first, 2);
        $this->play($second, 2);
        $this->play($first);
        $this->play($second);

        foreach ([$first, $second] as $run) {
            $this->assertTrue($run->ended);
            $this->assertGreaterThanOrEqual(8, count($run->ids));
            $this->assertSame($run->ids, array_values(array_unique($run->ids)), 'No question twice.');
            $catattemptid = (int) $DB->get_field('local_catquiz_attempts', 'id', ['attemptid' => $run->attemptid]);
            // From the database: nothing of it was lost to the other attempt.
            $progress = progress::load_for_reading($catattemptid);
            $this->assertNotNull($progress);
            $this->assertSame(
                count($run->ids),
                $progress->get_num_playedquestions(),
                'Every question shown is in the progress of attempt ' . $catattemptid . '.'
            );
        }
    }
}
