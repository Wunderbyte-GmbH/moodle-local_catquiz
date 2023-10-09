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
 * Tests strategy
 *
 * @package    local_catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;
use context_course;
use context_module;
use core_question\local\bank\question_edit_contexts;
use local_catquiz\data\catscale_structure;
use local_catquiz\data\dataapi;
use local_catquiz\importer\testitemimporter;
use mod_adaptivequiz\local\question\question_answer_evaluation;
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
require_once(__DIR__ . '../../../lib.php');


/**
 * Tests strategy
 *
 * @package    local_catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_catquiz\teststrategy\strategy
 */
class strategy_test extends advanced_testcase {

    /**
     * @var int The ID of the 'Mathematik' scale that is created during import of the item params
     */
    private int $catscaleid;

    /**
     * @var $quba question_usage This is created so that we can simulate a quiz attempt.
     */
    private question_usage_by_activity $quba;

    /**
     * @var Stores the course we create for this test.
     */
    private mixed $course;

    /**
     * @var An instance of an adaptive quiz
     */
    private mixed $adaptivequiz;

    public function setUp(): void {
        $this->import('mathematik2scales.xml', 'mathematik2scales.csv');
        $this->createtestenvironment()->save_or_update();

        // Needed to simulate question answers.

        $cm = get_coursemodule_from_instance('adaptivequiz', $this->adaptivequiz->id);
        $context = context_module::instance($cm->id);
        $quba = question_engine::make_questions_usage_by_activity('mod_adaptivequiz', $context);
        $quba->set_preferred_behaviour('deferredfeedback');
        $this->quba = $quba;
    }

    public function test_import_worked() {
        global $DB;
        $itemparams = $DB->get_records('local_catquiz_itemparams');
        $this->assertNotEmpty($itemparams);
    }

    /**
     * Check if a teststrategy returns the expected questions in the correct
     * order.
     *
     * This test simulates a full quiz attempt by repeatedly calling the
     * catquiz_handler::fetch_question_id() function. For each returned
     * question, we simulate a correct or incorrect response before getting the
     * next question.
     *
     * @param array $expected The data from the dataprovider.
     *
     * TODO: add group large?
     * TODO: Use different testenvironment.json files for different teststrategies.
     * @dataProvider strategy_returns_expected_questions_provider
     */
    public function test_strategy_returns_expected_questions($expected) {
        global $DB, $USER;
        // This is needed so that the responses to the questions are indeed saved to the database.
        $this->preventResetByRollback();
        $attemptdata = (object)[
            'instance' => 1,
            'questionsattempted' => 0,
            'id' => 1,
        ];
        foreach ($expected as $expectedquestion) {
            $abilityrecord = $DB->get_record(
                'local_catquiz_personparams',
                ['userid' => $USER->id, 'catscaleid' => $this->catscaleid],
                'ability'
            );
            $ability = $abilityrecord ? $abilityrecord->ability : 0;
            $this->assertEquals($expectedquestion['ability_before'], $ability);
            [$nextquestionid, $message] = catquiz_handler::fetch_question_id('1', 'mod_adaptivequiz', $attemptdata);
            $question = question_bank::load_question($nextquestionid);
            $this->assertEquals($expectedquestion['label'], $question->idnumber);
            $this->createresponse($question, $expectedquestion['response']);
            $attemptdata->questionsattempted++;
        }
    }

    /**
     * Data provider to test that the expected questions are returned.
     *
     * @return array
     */
    public static function strategy_returns_expected_questions_provider(): array {
        return [
            'radical CAT' => [
                'questions' => [
                    [
                        'label' => 'W_LE01_A04c',
                        'response' => 'True',
                        'ability_before' => 0,
                        'ability_after' => 0,
                    ],
                    [
                        'label' => 'W_LE01_A04f',
                        'response' => 'True',
                        'ability_before' => 0,
                        'ability_after' => -2.5000,
                    ],
                    [
                        'label' => 'W_LE01_A04a',
                        'response' => 'True',
                        'ability_before' => -2.5000,
                        'ability_after' => -2.5000,
                    ],
                ],
            ],
        ];
    }

    /**
     * Create a response for the given question and save it in the database.
     *
     * @param mixed $question The question
     * @param mixed $response The response
     *
     * @return void
     */
    private function createresponse($question, $response): void {
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $slot = $this->quba->add_question($question);
        $this->quba->start_question($slot);

        $time = time();
        $this->quba->process_all_actions(
            $time,
            $questiongenerator->get_simulated_post_data_for_questions_in_usage(
                $this->quba,
                [$slot => $response], false
            )
        );
        $this->quba->finish_all_questions($time);

        // When performing answer evaluation.
        $evaluationresult = (new question_answer_evaluation($this->quba))->perform($slot);
        question_engine::save_questions_usage_by_activity($this->quba);
    }

    /**
     * Parse a json file to create a test environment that will be used for the attempt.
     *
     * @return testenvironment
     */
    private function createtestenvironment(): testenvironment {
        global $DB;
        $catscale = $DB->get_record('local_catquiz_catscales', ['name' => 'Mathematik']);
        $this->catscaleid = $catscale->id;
        $json = file_get_contents(__DIR__ . '/../fixtures/testenvironment.json');
        $jsondata = json_decode($json);
        $jsondata->catquiz_catscales = $this->catscaleid;
        $jsondata->catscaleid = $this->catscaleid;
        $jsondata->json = json_encode($jsondata);
        $jsondata->componentid = '1';
        $jsondata->component = 'mod_adaptivequiz';
        $testenvironment = new testenvironment($jsondata);
        return $testenvironment;
    }

    /**
     * Import both questions and item params from fixture files.
     *
     * @param string $questionsfile The path to an XML questions file.
     * @param string $itemparamsfile The path to a CSV itemparams file.
     *
     * @return void
     */
    private function import(string $questionsfile, string $itemparamsfile): void {
        $this->resetAfterTest(true);

        // Import questions.
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course();

        $this->adaptivequiz = $this->getDataGenerator()
            ->get_plugin_generator('mod_adaptivequiz')
            ->create_instance([
                'highestlevel' => 10,
                'lowestlevel' => 1,
                'standarderror' => 14,
                'course' => $this->course->id,
            ]);
        $qformat = $this->create_qformat($questionsfile, $this->course);
        $imported = $qformat->importprocess();
        $this->assertTrue($imported);

        $this->import_itemparams($itemparamsfile);
    }


    /**
     * Import the item params from the given CSV file
     *
     * @param string $filename The name of the itemparams file.
     *
     * @return void
     */
    private function import_itemparams($filename) {
        $importer = new testitemimporter();
        $content = file_get_contents(__DIR__ . '/../fixtures/' . $filename);
        $importer->execute_testitems_csv_import(
            (object) [
                'delimiter_name' => 'semicolon',
                'encoding' => null,
                'dateparseformat' => null,
            ],
            $content
        );
    }

    /**
     * Create a new qformat object so that we can import questions.
     *
     * NOTE: copied from qformat_xml_import_export_test.php
     *
     * Create object qformat_xml for test.
     * @param string $filename with name for testing file.
     * @param \stdClass $course
     * @return \qformat_xml XML question format object.
     */
    private function create_qformat($filename, $course) {
        $qformat = new \qformat_xml();
        $qformat->setContexts((new question_edit_contexts(context_course::instance($course->id)))->all());
        $qformat->setCourse($course);
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
}
