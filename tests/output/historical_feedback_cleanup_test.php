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

namespace local_catquiz;

use advanced_testcase;
use context_course;
use context_module;
use core_question\local\bank\question_edit_contexts;
use local_catquiz\external\render_feedback_tab;
use local_catquiz\importer\testitemimporter;
use local_catquiz\local\attempt\attempt_finalizer;
use local_catquiz\local\question\question_answer_evaluation;
use local_catquiz\output\attemptfeedback;
use local_catquiz\task\cleanup_attempt_progress;
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
 * The result page of a finished attempt after its progress was removed by the retention task (issue #159).
 *
 * A whole attempt is played and finalised, every feedback section is rendered for the participant and for the
 * teacher, the real cleanup task removes the progress, all caches are emptied - and the same sections are rendered
 * again. What was there before is there after: the same values and labels. Nothing is created on the way (no new
 * progress row), nothing is played again.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\output\attemptfeedback
 * @covers \local_catquiz\external\render_feedback_tab
 * @covers \local_catquiz\teststrategy\feedbackgenerator
 * @covers \local_catquiz\task\cleanup_attempt_progress
 */
final class historical_feedback_cleanup_test extends advanced_testcase {
    /** @var \stdClass The activity. */
    private \stdClass $adaptivequiz;

    /** @var int The root scale. */
    private int $rootid;

    /** @var int The CAT context. */
    private int $contextid;

    /** @var \stdClass */
    private \stdClass $course;

    /**
     * Imports the simulation pool.
     */
    public function setUp(): void {
        global $DB;
        parent::setUp();
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
        $this->contextid = (int) catscale::get_context_id($this->rootid);
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
     * Empties every cache, the static acceleration of this request included.
     *
     * @return void
     */
    private function purge_caches(): void {
        \cache_helper::purge_all();
        \cache_factory::reset();
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
     * The settings of the test.
     *
     * @param int $strategy
     * @param int $maximum Maximum number of questions; the long run reaches a reportable result.
     * @return void
     */
    private function settings(int $strategy, int $maximum): void {
        $data = json_decode(file_get_contents(__DIR__ . '/../fixtures/testenvironment.json'));
        $data->catquiz_catscales = $this->rootid;
        $data->catscaleid = $this->rootid;
        $data->catquiz_catcontext = $this->contextid;
        foreach ([$this->rootid, ...catscale::get_subscale_ids($this->rootid)] as $scaleid) {
            $data->{'catquiz_subscalecheckbox_' . $scaleid} = true;
            // Three feedback ranges per scale, so that the comparison and the scale feedback are shown.
            foreach ([1 => [-10, -2], 2 => [-2, 2], 3 => [2, 10]] as $range => [$lower, $upper]) {
                $data->{"feedback_scaleid_limit_lower_{$scaleid}_{$range}"} = $lower;
                $data->{"feedback_scaleid_limit_upper_{$scaleid}_{$range}"} = $upper;
                $data->{"wb_colourpicker_{$scaleid}_{$range}"} = ['3', '5', '6'][$range - 1];
            }
        }
        $data->numberoffeedbackoptionsselect = 3;
        $data->componentid = (string) $this->adaptivequiz->id;
        $data->component = 'mod_adaptivequiz';
        $data->catquiz_selectteststrategy = $strategy;
        $data->maxquestionsgroup->catquiz_minquestions = 8;
        $data->maxquestionsgroup->catquiz_maxquestions = $maximum;
        $data->maxquestionsscalegroup->catquiz_maxquestionspersubscale = $maximum > 15 ? 12 : 4;
        $data->maxquestionsscalegroup->catquiz_minquestionspersubscale = 1;
        $data->catquiz_standarderrorgroup->catquiz_standarderror_min = 0.0001;
        $data->catquiz_standarderrorgroup->catquiz_standarderror_max = 1.0;
        $data->catquiz_pp_min_inc = 0.05;
        $data->catquiz_includepilotquestions = false;
        $data->catquiz_pilotratio = 0;
        $data->json = json_encode($data);
        (new testenvironment($data))->save_or_update();
    }

    /**
     * Plays an attempt to its end, with the feedback after every answer, and finalises it.
     *
     * @return int adaptivequiz_attempt.id
     */
    private function play_and_finish(): int {
        global $DB, $USER;
        putenv('CATQUIZ_TESTING_SKIP_FEEDBACK');
        catquiz_handler::prepare_attempt_caches();
        $cm = get_coursemodule_from_instance('adaptivequiz', $this->adaptivequiz->id);
        $quba = question_engine::make_questions_usage_by_activity('mod_adaptivequiz', context_module::instance($cm->id));
        $quba->set_preferred_behaviour('deferredfeedback');
        $attemptid = (int) (new attempt($this->adaptivequiz, $USER->id))->get_attempt()->id;
        for ($i = 0; $i < 60; $i++) {
            $record = $DB->get_record('adaptivequiz_attempt', ['id' => $attemptid], '*', MUST_EXIST);
            $attempt = new attempt($this->adaptivequiz, $record->userid);
            $result = catquiz_handler::fetch_question_id(
                (int) $this->adaptivequiz->id,
                'mod_adaptivequiz',
                $attempt->get_attempt()
            );
            if (empty($result[0])) {
                break;
            }
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
        $DB->set_field('adaptivequiz_attempt', 'attemptstate', 'complete', ['id' => $attemptid]);
        $DB->set_field('adaptivequiz_attempt', 'timemodified', time() - 10, ['id' => $attemptid]);
        $this->assertTrue(attempt_finalizer::finalize($attemptid, time() - 10));
        return $attemptid;
    }

    /**
     * What the result page shows: the page itself and every section, for the participant and the teacher,
     * reduced to the visible text (values and labels).
     *
     * @param int $attemptid
     * @return array section => text
     */
    private function result_page(int $attemptid): array {
        global $PAGE, $OUTPUT;
        $sections = [];
        $generators = [
            render_feedback_tab::WHOLE_ATTEMPT, 'personabilities', 'customscalefeedback', 'comparetotestaverage',
            'graphicalsummary', 'learningprogress',
        ];
        foreach (['student', 'teacher'] as $audience) {
            foreach ($generators as $name) {
                // Every section is its own request, as the lazy tabs load them.
                $PAGE = new \moodle_page();
                $OUTPUT = new \bootstrap_renderer();
                try {
                    $html = render_feedback_tab::execute($attemptid, $name, $audience)['html'];
                    $sections["$audience/$name"] = $this->text($html);
                } catch (\Throwable $e) {
                    $sections["$audience/$name"] = 'EXCEPTION ' . get_class($e) . ': ' . $e->getMessage()
                        . ' @ ' . $e->getFile() . ':' . $e->getLine();
                }
            }
        }
        return $sections;
    }

    /**
     * The visible text of an HTML fragment, without the ids the templates generate per rendering.
     *
     * @param string $html
     * @return string
     */
    private function text(string $html): string {
        $html = preg_replace('~<script\b.*?</script>~s', '', $html);
        $text = html_entity_decode(strip_tags($html));
        $text = preg_replace('~\b(?:[a-z_]*)?[0-9a-f]{13,}\b~i', '#', $text);
        return trim(preg_replace('~\s+~u', ' ', $text));
    }

    /**
     * Every strategy whose result page differs in its sections.
     *
     * @return array
     */
    public static function strategies(): array {
        return [
            'CLASSIC, with a reportable result' => [LOCAL_CATQUIZ_STRATEGY_CLASSIC, 50, true],
            'LOWESTSUB, with a reportable result' => [LOCAL_CATQUIZ_STRATEGY_LOWESTSUB, 50, true],
            'CLASSIC, short' => [LOCAL_CATQUIZ_STRATEGY_CLASSIC, 15, false],
            'LOWESTSUB, short' => [LOCAL_CATQUIZ_STRATEGY_LOWESTSUB, 15, false],
            'RELSUBS, short' => [LOCAL_CATQUIZ_STRATEGY_RELSUBS, 15, false],
        ];
    }

    /**
     * RED before the fix of issue #159: after the real cleanup the result page lost sections or failed.
     *
     * @param int $strategy
     * @param int $maximum
     * @param bool $reportable Whether the run is meant to reach a reportable result.
     * @dataProvider strategies
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('strategies')]
    public function test_the_result_page_is_the_same_after_the_progress_cleanup(
        int $strategy,
        int $maximum,
        bool $reportable
    ): void {
        global $DB;
        $this->settings($strategy, $maximum);
        $attemptid = $this->play_and_finish();
        $catattemptid = (int) catquiz::get_cat_attempt_id($attemptid, 'mod_adaptivequiz');
        $this->assertTrue($DB->record_exists('local_catquiz_progress', ['attemptid' => $catattemptid]));

        $this->purge_caches();
        $before = $this->result_page($attemptid);
        foreach ($before as $section => $text) {
            $this->assertStringNotContainsString('EXCEPTION', $text, "$section before the cleanup");
        }
        $this->assertNotSame('', $before['student/' . render_feedback_tab::WHOLE_ATTEMPT]);
        if ($reportable) {
            // The case that matters: values to compare - the score of the result with its standard error, and the
            // ability after every question of the attempt.
            $this->assertMatchesRegularExpression(
                '~You reached a score of -?\d+\.\d\d \(with a standard error of ± \d+\.\d\d\)~u',
                $before['student/comparetotestaverage']
            );
            $this->assertMatchesRegularExpression('~ 20 Testfrage \S+ \S+ -?\d+\.\d\d~', $before['student/graphicalsummary']);
        }

        // The real retention task, in the data-sparing mode: every finished attempt is swept.
        set_config('progressretention', 'minimal', 'local_catquiz');
        ob_start();
        (new cleanup_attempt_progress())->execute();
        ob_end_clean();
        $this->assertFalse($DB->record_exists('local_catquiz_progress', ['attemptid' => $catattemptid]));
        $this->purge_caches();

        $attemptsbefore = $DB->count_records('local_catquiz_attempts');
        $after = $this->result_page($attemptid);
        $this->assertSame($before, $after, 'The same values and labels after the cleanup.');
        // The rebuilt progress is read only, even when somebody saves it.
        $historical = \local_catquiz\teststrategy\progress::load_historical($attemptid, 'mod_adaptivequiz');
        $this->assertNotNull($historical);
        $this->assertTrue($historical->is_historical());
        $used = (int) $DB->get_field('local_catquiz_attempts', 'number_of_testitems_used', ['id' => $catattemptid]);
        $this->assertSame($used, $historical->get_num_playedquestions());
        $historical->save();
        $this->assertFalse(\cache::make('local_catquiz', 'adaptivequizattempt')->get('progress_cat_' . $catattemptid));
        // Read only: no progress created again, no attempt row added.
        $this->assertFalse($DB->record_exists('local_catquiz_progress', ['attemptid' => $catattemptid]));
        $this->assertSame($attemptsbefore, $DB->count_records('local_catquiz_attempts'));
    }

    /**
     * Re-audit 02.11.2026 (traceability, gap 2): the chart "answered questions per person" counts every answer of
     * the attempt once - the join compared an item id with a question id and found nothing or several rows.
     */
    public function test_answers_per_person_counts_each_answer_once(): void {
        global $DB, $USER;
        $this->settings(LOCAL_CATQUIZ_STRATEGY_CLASSIC, 15);
        $attemptid = $this->play_and_finish();
        $catattemptid = (int) catquiz::get_cat_attempt_id($attemptid, 'mod_adaptivequiz');
        $used = (int) $DB->get_field('local_catquiz_attempts', 'number_of_testitems_used', ['id' => $catattemptid]);
        $this->assertGreaterThan(0, $used);
        // The person answers in the course of the attempt; enrolled, as the chart counts the enrolled.
        $this->getDataGenerator()->enrol_user($USER->id, $this->course->id);
        $this->assertSame(
            $used,
            catquiz::get_max_questions_answered_per_person($this->contextid, $this->rootid, (int) $this->course->id)
        );
    }
}
