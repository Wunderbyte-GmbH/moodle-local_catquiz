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
 * Cold and warm caches select the same questions (issue #136, AK "Kalter und warmer Cache liefern
 * bei gleichem Zustand dieselbe Auswahl").
 *
 * The same attempt is played twice on the simulation pool: once with every cache emptied before
 * each question (pool, scales, strategy settings, attempt state - everything is rebuilt from the
 * database), once the usual way with the caches kept between questions. The first run is rolled
 * back, so both start from the same database. Question sequence and end must be identical - a
 * cache may only make the selection faster, never different.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\catquiz_handler
 * @covers \local_catquiz\teststrategy\strategy
 */
final class cold_warm_selection_test extends advanced_testcase {
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
        // The cold run is rolled back in a transaction of its own; it must be the outermost one.
        $this->preventResetByRollback();
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
     * @return void
     */
    private function settings(int $strategy): void {
        $data = json_decode(file_get_contents(__DIR__ . '/../fixtures/testenvironment.json'));
        $data->catquiz_catscales = $this->rootid;
        $data->catscaleid = $this->rootid;
        foreach ([$this->rootid, ...catscale::get_subscale_ids($this->rootid)] as $scaleid) {
            $data->{'catquiz_subscalecheckbox_' . $scaleid} = true;
        }
        $data->componentid = '1';
        $data->component = 'mod_adaptivequiz';
        $data->catquiz_selectteststrategy = $strategy;
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
     * Plays an attempt to its end, every question answered in its own slot, as the activity does.
     *
     * @param bool $cold Empty every cache before each question.
     * @return array [question ids, status of the end]
     */
    private function play(bool $cold): array {
        global $DB, $USER;
        putenv('CATQUIZ_TESTING_SKIP_FEEDBACK=true');
        \cache_helper::purge_all();
        catquiz_handler::prepare_attempt_caches();

        $cm = get_coursemodule_from_instance('adaptivequiz', $this->adaptivequiz->id);
        $quba = question_engine::make_questions_usage_by_activity('mod_adaptivequiz', context_module::instance($cm->id));
        $quba->set_preferred_behaviour('deferredfeedback');

        $attemptid = (new attempt($this->adaptivequiz, $USER->id))->get_attempt()->id;
        $ids = [];
        $status = null;
        for ($i = 0; $i < 30; $i++) {
            if ($cold) {
                \cache_helper::purge_all();
            }
            $record = $DB->get_record('adaptivequiz_attempt', ['id' => $attemptid], '*', MUST_EXIST);
            $attempt = new attempt($this->adaptivequiz, $record->userid);
            $result = catquiz_handler::fetch_question_id(1, 'mod_adaptivequiz', $attempt->get_attempt());
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
        }
        return [$ids, $status];
    }

    /**
     * Plays the attempt cold inside a transaction that is rolled back afterwards.
     *
     * @return array [question ids, status of the end]
     */
    private function play_cold_and_roll_back(): array {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $result = $this->play(true);
        try {
            $transaction->rollback(new \moodle_exception('rollback'));
        } catch (\moodle_exception $e) {
            $this->assertSame('rollback', $e->errorcode);
        }
        \cache_helper::purge_all();
        return $result;
    }

    /**
     * The strategies whose selection depends on the pool and the running estimate.
     *
     * @return array
     */
    public static function strategies(): array {
        return [
            'FASTEST' => [LOCAL_CATQUIZ_STRATEGY_FASTEST],
            'CLASSIC' => [LOCAL_CATQUIZ_STRATEGY_CLASSIC],
        ];
    }

    /**
     * Same database state, cold or warm caches: the same questions in the same order, the same end.
     *
     * @dataProvider strategies
     * @param int $strategy
     */
    public function test_cold_and_warm_caches_select_the_same(int $strategy): void {
        global $DB;
        $this->settings($strategy);
        $attempts = $DB->count_records('adaptivequiz_attempt');

        [$coldids, $coldstatus] = $this->play_cold_and_roll_back();
        $this->assertSame($attempts, $DB->count_records('adaptivequiz_attempt'), 'The cold run was rolled back.');
        [$warmids, $warmstatus] = $this->play(false);

        $this->assertGreaterThanOrEqual(8, count($coldids), 'The cold run played the minimum.');
        $this->assertSame($coldids, $warmids);
        $this->assertSame($coldstatus, $warmstatus);
    }
}
