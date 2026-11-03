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
 * The pool of issue #64 for whole attempts through the real selection: 24 calculated items, Simulation >
 * SimA > {SimA01, SimA02}, and an activity to play them in.
 *
 * Shared by the tests of issues #64, #77 and #82.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait classic_hierarchy_trait {
    /** @var \stdClass The activity. */
    private \stdClass $adaptivequiz;

    /** @var int The root scale "Simulation". */
    private int $rootid;

    /**
     * Imports the questions and the 24 items, creates the activity.
     */
    private function set_up_hierarchy(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $this->adaptivequiz = $this->getDataGenerator()->get_plugin_generator('mod_adaptivequiz')->create_instance([
            'highestlevel' => 10, 'lowestlevel' => 1, 'standarderror' => 14, 'course' => $course->id,
        ]);
        $this->assertTrue($this->qformat('quiz-adaptivetest-Simulation-small.xml', $course)->importprocess());
        (new testitemimporter())->execute_testitems_csv_import(
            (object) ['delimiter_name' => 'semicolon', 'encoding' => null, 'dateparseformat' => null],
            file_get_contents(__DIR__ . '/../fixtures/simulation_24_hierarchy.csv')
        );
        $this->rootid = (int) $DB->get_field('local_catquiz_catscales', 'id', ['parentid' => 0, 'name' => 'Simulation']);
        // The import leaves the ability range open; the default range, as a new scale gets it in the form.
        $DB->update_record('local_catquiz_catscales', (object) [
            'id' => $this->rootid,
            'minscalevalue' => LOCAL_CATQUIZ_PERSONABILITY_LOWER_LIMIT,
            'maxscalevalue' => LOCAL_CATQUIZ_PERSONABILITY_UPPER_LIMIT,
        ]);
        \cache_helper::purge_by_event('changesincatscales');
    }

    /**
     * Unsets the switches of the testing classes.
     */
    private function unset_testing_switches(): void {
        $switches = ['USE_TESTING_CLASS_FOR', 'CATQUIZ_TESTING_ABILITY', 'CATQUIZ_TESTING_STANDARDERROR',
            'CATQUIZ_TESTING_SKIP_FEEDBACK'];
        foreach ($switches as $name) {
            putenv($name);
        }
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
     * The test settings of the report: CLASSIC over all scales, minimum 3, maximum 250, 8 per scale, SE 0.05 / 0.30.
     *
     * @param array $extra Further settings, set on the test environment as they are.
     * @return void
     */
    private function settings(array $extra = []): void {
        $data = json_decode(file_get_contents(__DIR__ . '/../fixtures/testenvironment.json'));
        $data->catquiz_catscales = $this->rootid;
        $data->catscaleid = $this->rootid;
        foreach ([$this->rootid, ...catscale::get_subscale_ids($this->rootid)] as $scaleid) {
            $data->{'catquiz_subscalecheckbox_' . $scaleid} = true;
        }
        // The activity the attempts belong to: the feedback finds the test environment through it.
        $data->componentid = (string) $this->adaptivequiz->id;
        $data->component = 'mod_adaptivequiz';
        $data->catquiz_selectteststrategy = LOCAL_CATQUIZ_STRATEGY_CLASSIC;
        $data->maxquestionsgroup->catquiz_minquestions = 3;
        $data->maxquestionsgroup->catquiz_maxquestions = 250;
        $data->maxquestionsscalegroup->catquiz_maxquestionspersubscale = 8;
        $data->maxquestionsscalegroup->catquiz_minquestionspersubscale = 0;
        $data->catquiz_standarderrorgroup->catquiz_standarderror_min = 0.05;
        $data->catquiz_standarderrorgroup->catquiz_standarderror_max = 0.30;
        $data->catquiz_includepilotquestions = false;
        // One feedback range over the whole scale for every scale, as a test with feedback has it.
        $data->numberoffeedbackoptionsselect = '1';
        foreach ([$this->rootid, ...catscale::get_subscale_ids($this->rootid)] as $scaleid) {
            $data->{'feedback_scaleid_limit_lower_' . $scaleid . '_1'} = '-100';
            $data->{'feedback_scaleid_limit_upper_' . $scaleid . '_1'} = '100';
            $data->{'catquiz_scalereportcheckbox_' . $scaleid} = '1';
            $data->{'wb_colourpicker_' . $scaleid . '_1'} = '3';
        }
        foreach ($extra as $name => $value) {
            $data->$name = $value;
        }
        $data->json = json_encode($data);
        (new testenvironment($data))->save_or_update();
    }

    /**
     * Plays an attempt of the current user to its end, every question in its own slot, as the activity does.
     *
     * @param int $limit Questions at most.
     * @param bool $skipfeedback Leave out the feedback generators (faster); false runs the whole path.
     * @param callable|null $afteranswer fn(int $i, array $ids) after the i-th answer - a change during the attempt.
     * @param callable|null $correct fn(int $i): bool - whether the i-th answer is right (default: right first,
     *     then alternating).
     * @param callable|null $afterfetch fn(int $i, int $attemptid) after the i-th question was fetched.
     * @return array [question ids, status of the end, adaptivequiz attempt id]
     */
    private function play(
        int $limit = 60,
        bool $skipfeedback = true,
        ?callable $afteranswer = null,
        ?callable $correct = null,
        ?callable $afterfetch = null
    ): array {
        global $DB, $USER;
        if ($skipfeedback) {
            putenv('CATQUIZ_TESTING_SKIP_FEEDBACK=true');
        } else {
            putenv('CATQUIZ_TESTING_SKIP_FEEDBACK');
        }
        catquiz_handler::prepare_attempt_caches();
        $cm = get_coursemodule_from_instance('adaptivequiz', $this->adaptivequiz->id);
        $quba = question_engine::make_questions_usage_by_activity('mod_adaptivequiz', context_module::instance($cm->id));
        $quba->set_preferred_behaviour('deferredfeedback');

        $attemptid = (int) (new attempt($this->adaptivequiz, $USER->id))->get_attempt()->id;
        $ids = [];
        $status = null;
        for ($i = 0; $i < $limit; $i++) {
            $record = $DB->get_record('adaptivequiz_attempt', ['id' => $attemptid], '*', MUST_EXIST);
            $attempt = new attempt($this->adaptivequiz, $record->userid);
            $result = catquiz_handler::fetch_question_id(
                (int) $this->adaptivequiz->id,
                'mod_adaptivequiz',
                $attempt->get_attempt()
            );
            if ($afterfetch) {
                $afterfetch($i, $attemptid);
            }
            if (empty($result[0])) {
                $status = $result[2] ?? null;
                break;
            }
            $ids[] = (int) $result[0];

            $slot = $quba->add_question(question_bank::load_question($result[0]));
            $quba->start_question($slot);
            $right = $correct ? $correct($i) : ($i === 0 || $i % 2 === 1);
            $answer = $quba->get_correct_response($slot)['answer'];
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
            if ($afteranswer) {
                $afteranswer($i, $ids);
            }
        }
        return [$ids, $status, $attemptid];
    }
}
