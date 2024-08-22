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
use local_catquiz\importer\testitemimporter;
use local_catquiz\teststrategy\strategy;
use mod_adaptivequiz\local\attempt\attempt;
use mod_adaptivequiz\local\question\question_answer_evaluation;
use PHPUnit\Framework\ExpectationFailedException;
use question_bank;
use question_engine;
use question_usage_by_activity;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');
require_once($CFG->dirroot . '/question/format.php');
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->dirroot . '/local/catquiz/lib.php');
require_once($CFG->dirroot . '/local/catquiz/tests/lib.php');


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
final class strategy_test extends advanced_testcase {

    /**
     * @var int The ID of the 'Mathematik' scale that is created during import of the item params
     */
    private int $catscaleid;

    /**
     * @var question_usage_by_activity $quba question_usage This is created so that we can simulate a quiz attempt.
     */
    private question_usage_by_activity $quba;

    /**
     * @var Stores the course we create for this test.
     */
    private \stdClass $course;

    /**
     * @var An instance of an adaptive quiz
     */
    private \stdClass $adaptivequiz;

    public function setUp(): void {
        $this->import('simulation.xml', 'simulation.csv');
        // Needed to simulate question answers.

        $cm = get_coursemodule_from_instance('adaptivequiz', $this->adaptivequiz->id);
        $context = context_module::instance($cm->id);
        $quba = question_engine::make_questions_usage_by_activity('mod_adaptivequiz', $context);
        $quba->set_preferred_behaviour('deferredfeedback');
        $this->quba = $quba;
    }

    public function test_import_worked() {
        global $DB;
        $questions = $DB->get_records('question');
        $this->assertNotEmpty($questions, 'No questions were imported');
        $itemparams = $DB->get_records('local_catquiz_itemparams');
        $this->assertNotEmpty($itemparams, 'No itemparams were imported');
    }


    /**
     * Check if a second import updates saved items as expected
     *
     * Items are already imported in the setUp() method.
     * Here, we:
     *   1. Import another, small file with item params to add/update item params.
     *   2. Check that the values in the database match our expectations.
     *
     * @param string $filename
     * @param array $expectedscales
     * @return void
     *
     * @dataProvider import_overrides_provider
     */
    public function test_import_overrides(string $filename, array $expectedscales): void {
        // 1. Import items.
        global $DB;
        $this->import_itemparams($filename);
        [$initems, $initemsparams] = $DB->get_in_or_equal(
            array_keys($expectedscales),
            SQL_PARAMS_NAMED,
            'initems'
        );

        // 2. Check the values in the database match our expectations.
        $sql = <<<SQL
            SELECT idnumber, catscaleid
            FROM {question_bank_entries} qbe
            JOIN {question_versions} qv ON qbe.id = qv.questionbankentryid
            JOIN {local_catquiz_items} i ON qv.questionid = i.componentid
            JOIN {local_catquiz_itemparams} ip ON i.id = ip.itemid
            WHERE qbe.idnumber $initems
            ORDER BY timemodified DESC
        SQL;
        $addeditems = $DB->get_records_sql(
            $sql,
            $initemsparams,
            0,
            count($expectedscales)
        );
        foreach ($expectedscales as $label => $expected) {
            $scalenames = array_map(
                fn($id) => catscale::return_catscale_object($id)->name,
                [$addeditems[$label]->catscaleid, ...catscale::get_ancestors($addeditems[$label]->catscaleid)]
            );
            $this->assertEquals($expected, $scalenames);
        }
    }

    /**
     * Data provider for test_import_overrides
     *
     * @return array
     */
    public static function import_overrides_provider(): array {
        return [
            'update with simulation_beta.csv' => [
                'filename' => 'simulation_beta.csv',
                'expected_scales' => [
                    'SIMA01-00' => ['SimA01', 'SimA', 'SimulationBeta'],
                    'SIMA01-01' => ['SimA01', 'SimABeta', 'Simulation'],
                    'SIMA01-02' => ['SimA01', 'SimA', 'Simulation'],
                    'SIMA01-03' => ['SimA01', 'SimABeta', 'SimulationBeta'],
                ],
            ],
        ];
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
     * @param int $strategy The ID of the teststrategy.
     * @param array $questions The expected list of questions.
     * @param float $initialability The initial ability in the main scale.
     * @param float $initialse The initial standarderror in the main scale.
     * @param array $settings Additional testsettings
     * @param array $finalabilities Array with abilities in all scales when the attempt finished.
     *
     * TODO: add group large?
     * TODO: Use different testenvironment.json files for different teststrategies.
     * @dataProvider strategy_returns_expected_questions_provider
     */
    public function test_strategy_returns_expected_questions(
        int $strategy,
        array $questions,
        float $initialability = 0.0,
        float $initialse = 1.0,
        array $settings = [],
        array $finalabilities = []
    ) {
        putenv(
            sprintf(
                'USE_TESTING_CLASS_FOR=%s',
                implode(',', [
                    'local_catquiz\teststrategy\preselect_task\updatepersonability',
                    'local_catquiz\teststrategy\preselect_task\maybe_return_pilot',
                ])
            )
        );
        putenv("CATQUIZ_TESTING_ABILITY=$initialability");
        putenv("CATQUIZ_TESTING_STANDARDERROR=$initialse");
        putenv("CATQUIZ_TESTING_SKIP_FEEDBACK=true");
        global $DB, $USER;
        $hasqubaid = false;
        $this
            ->createtestenvironment($strategy, $settings)
            ->save_or_update();

        catquiz_handler::prepare_attempt_caches();

        // This is needed so that the responses to the questions are indeed saved to the database.
        $this->preventResetByRollback();
        $attempt = attempt::create(1, $USER->id);
        $attemptid = $attempt->read_attempt_data()->id;
        foreach ($questions as $index => $expectedquestion) {
            $attempt = attempt::get_by_id($attemptid);
            $attemptdata = $attempt->read_attempt_data();
            $abilityrecord = $DB->get_record(
                'local_catquiz_personparams',
                ['userid' => $USER->id, 'catscaleid' => $this->catscaleid],
                'ability'
            );
            $ability = $abilityrecord ? $abilityrecord->ability : ($initialability ?: 0);
            if (array_key_exists('ability_before', $expectedquestion)) {
                $this->assertEqualsWithDelta(
                    $expectedquestion['ability_before'],
                    $ability,
                    0.01,
                    'Ability before fetch is not correct for question number ' . ($index + 1)
                );
            }
            [$nextquestionid, $message] = catquiz_handler::fetch_question_id('1', 'mod_adaptivequiz', $attemptdata);
            $abilityrecord = $DB->get_record(
                'local_catquiz_personparams',
                ['userid' => $USER->id, 'catscaleid' => $this->catscaleid],
                'ability'
            );
            $ability = $abilityrecord ? $abilityrecord->ability : ($initialability ?: 0);
            $this->assertEqualsWithDelta(
                $expectedquestion['ability_after'],
                $ability,
                0.01,
                'Ability after fetch is not correct for question number ' . ($index + 1)
            );
            if ($expectedquestion['label'] === 'FINISH') {
                if (!$finalabilities) {
                    return;
                }
                foreach ($finalabilities as $scalename => $ability) {
                    $scale = catscale::return_catscale_by_name($scalename);
                    $pp = $DB->get_record('local_catquiz_personparams', ['catscaleid' => $scale->id]);
                    $this->assertEqualsWithDelta(
                        $ability,
                        $pp->ability,
                        0.01,
                        "Ability for scale $scale->name is not correct for the end result"
                    );
                }
                return;
            }
            if ($nextquestionid == 0) {
                throw new \Exception("Should not be 0");
            }

            $question = question_bank::load_question($nextquestionid);
            $this->assertEquals($expectedquestion['label'], $question->idnumber);
            $this->createresponse($question, $expectedquestion['is_correct_response']);
            $attempt->update_after_question_answered(time());
            if (!$hasqubaid) {
                $attempt->set_quba_id($this->quba->get_id());
                $hasqubaid = true;
            }
        }
    }

    /**
     * Data provider to test that the expected questions are returned.
     *
     * @return array
     */
    public static function strategy_returns_expected_questions_provider(): array {
        return [
            // The expected values for the radical CAT dataset are confirmed.
            'radical CAT 1 normal mode' => [
                'strategy' => LOCAL_CATQUIZ_STRATEGY_FASTEST,
                'questions' => [
                    ['label' => 'SIMB01-18', 'is_correct_response' => false, 'ability_before' => 0.00, 'ability_after' => 0.00],
                    ['label' => 'SIMB02-00', 'is_correct_response' => false, 'ability_after' => -0.39],
                    ['label' => 'SIMA06-09', 'is_correct_response' => false, 'ability_after' => -0.71],
                    ['label' => 'SIMA04-00', 'is_correct_response' => false, 'ability_after' => -1.07],
                    ['label' => 'SIMA02-02', 'is_correct_response' => false, 'ability_after' => -1.35],
                    ['label' => 'SIMA04-10', 'is_correct_response' => false, 'ability_after' => -1.54],
                    ['label' => 'SIMA02-19', 'is_correct_response' => false, 'ability_after' => -1.77],
                    ['label' => 'SIMA05-04', 'is_correct_response' => false, 'ability_after' => -1.99],
                    ['label' => 'SIMA02-08', 'is_correct_response' => false, 'ability_after' => -2.25],
                    ['label' => 'SIMA02-17', 'is_correct_response' => false, 'ability_after' => -2.33],
                    ['label' => 'SIMA05-03', 'is_correct_response' => false, 'ability_after' => -2.61],
                    ['label' => 'SIMA05-07', 'is_correct_response' => false, 'ability_after' => -2.81],
                    ['label' => 'SIMA02-04', 'is_correct_response' => false, 'ability_after' => -2.97],
                    ['label' => 'SIMA05-00', 'is_correct_response' => false, 'ability_after' => -3.07],
                    ['label' => 'SIMA01-19', 'is_correct_response' => false, 'ability_after' => -3.14],
                    ['label' => 'SIMA01-16', 'is_correct_response' => true,  'ability_after' => -3.22],
                    ['label' => 'SIMA01-12', 'is_correct_response' => false, 'ability_after' => -3.48],
                    ['label' => 'SIMA01-13', 'is_correct_response' => true,  'ability_after' => -3.69],
                    ['label' => 'SIMA01-18', 'is_correct_response' => true,  'ability_after' => -3.61],
                    ['label' => 'SIMA01-14', 'is_correct_response' => true,  'ability_after' => -3.54],
                    ['label' => 'SIMA03-03', 'is_correct_response' => true,  'ability_after' => -3.48],
                    ['label' => 'SIMA03-13', 'is_correct_response' => true,  'ability_after' => -3.43],
                    ['label' => 'SIMA03-16', 'is_correct_response' => true,  'ability_after' => -3.40],
                    ['label' => 'SIMA01-17', 'is_correct_response' => true,  'ability_after' => -3.36],
                    ['label' => 'SIMA01-06', 'is_correct_response' => true,  'ability_after' => -3.33],
                    ['label' => 'FINISH',    'is_correct_response' => true,  'ability_after' => -3.31],
                ],
                'initialability' => 0.0,
                'initialse' => 1.0,
                'settings' => [
                    'maxquestions' => 250,
                    'maxquestionspersubscale' => 25,
                ],
                'final_abilities' => [
                    'Simulation' => -3.31,
                    'SimA' => -3.31,
                    'SimA01' => -3.33,
                    'SimA02' => -3.33,
                    'SimA03' => -3.25,
                    'SimA04' => -3.31,
                    'SimA05' => -3.35,
                    'SimA06' => -3.31,
                    'SimB' => -3.31,
                    'SimB01' => -3.31,
                    'SimB02' => -3.31,
                    'SimC' => -3.31, // Inherited from parent.
                ],
            ],
            // The first 50 questions (SIMA01-00 until SIMA03-09) are considered pilot questions.
            // After a pilot question, the ability is not changed.
            'radical CAT 1 piloting mode' => [
                'strategy' => LOCAL_CATQUIZ_STRATEGY_FASTEST,
                'questions' => [
                    ['label' => 'SIMB01-18', 'is_correct_response' => false, 'ability_before' => 0.00, 'ability_after' => 0.00],
                    ['label' => 'SIMB02-00', 'is_correct_response' => false, 'ability_after' => -0.39],
                    ['label' => 'Pilotfrage-1', 'is_correct_response' => false, 'ability_after' => -0.71],
                    ['label' => 'SIMA06-09', 'is_correct_response' => false, 'ability_after' => -0.71],
                    ['label' => 'Pilotfrage-2', 'is_correct_response' => false, 'ability_after' => -1.07],
                    ['label' => 'SIMA04-00', 'is_correct_response' => false, 'ability_after' => -1.07],
                    ['label' => 'SIMA02-02', 'is_correct_response' => false, 'ability_after' => -1.35],
                    ['label' => 'SIMA04-10', 'is_correct_response' => false, 'ability_after' => -1.54],
                    ['label' => 'Pilotfrage-3', 'is_correct_response' => false, 'ability_after' => -1.77],
                    ['label' => 'Pilotfrage-4', 'is_correct_response' => false, 'ability_after' => -1.77],
                    ['label' => 'Pilotfrage-5', 'is_correct_response' => false, 'ability_after' => -1.77],
                    ['label' => 'Pilotfrage-6', 'is_correct_response' => false, 'ability_after' => -1.77],
                    ['label' => 'Pilotfrage-7', 'is_correct_response' => false, 'ability_after' => -1.77],
                    ['label' => 'Pilotfrage-8', 'is_correct_response' => false, 'ability_after' => -1.77],
                    ['label' => 'Pilotfrage-9', 'is_correct_response' => false, 'ability_after' => -1.77],
                    ['label' => 'SIMA02-19', 'is_correct_response' => false, 'ability_after' => -1.77],
                    ['label' => 'SIMA05-04', 'is_correct_response' => false, 'ability_after' => -1.99],
                    ['label' => 'SIMA02-08', 'is_correct_response' => false, 'ability_after' => -2.25],
                    ['label' => 'Pilotfrage-10', 'is_correct_response' => false, 'ability_after' => -2.33],
                    ['label' => 'Pilotfrage-11', 'is_correct_response' => false, 'ability_after' => -2.33],
                    ['label' => 'SIMA02-17', 'is_correct_response' => false, 'ability_after' => -2.33],
                    ['label' => 'Pilotfrage-12', 'is_correct_response' => false, 'ability_after' => -2.61],
                    ['label' => 'SIMA05-03', 'is_correct_response' => false, 'ability_after' => -2.61],
                    ['label' => 'Pilotfrage-13', 'is_correct_response' => false, 'ability_after' => -2.81],
                    ['label' => 'Pilotfrage-14', 'is_correct_response' => false, 'ability_after' => -2.81],
                    ['label' => 'SIMA05-07', 'is_correct_response' => false, 'ability_after' => -2.81],
                    ['label' => 'SIMA02-04', 'is_correct_response' => false, 'ability_after' => -2.97],
                    ['label' => 'SIMA05-00', 'is_correct_response' => false, 'ability_after' => -3.07],
                    ['label' => 'Pilotfrage-15', 'is_correct_response' => false, 'ability_after' => -3.14],
                    ['label' => 'Pilotfrage-16', 'is_correct_response' => false, 'ability_after' => -3.14],
                    ['label' => 'SIMA01-19', 'is_correct_response' => false, 'ability_after' => -3.14],
                    ['label' => 'SIMA01-16', 'is_correct_response' => true,  'ability_after' => -3.22],
                    ['label' => 'SIMA01-12', 'is_correct_response' => false, 'ability_after' => -3.48],
                    ['label' => 'Pilotfrage-17', 'is_correct_response' => false, 'ability_after' => -3.69],
                    ['label' => 'Pilotfrage-18', 'is_correct_response' => false, 'ability_after' => -3.69],
                    ['label' => 'SIMA01-13', 'is_correct_response' => true,  'ability_after' => -3.69],
                    ['label' => 'SIMA01-18', 'is_correct_response' => true,  'ability_after' => -3.61],
                    ['label' => 'SIMA01-14', 'is_correct_response' => true,  'ability_after' => -3.54],
                    ['label' => 'SIMA03-03', 'is_correct_response' => true,  'ability_after' => -3.48],
                    ['label' => 'SIMA03-13', 'is_correct_response' => true,  'ability_after' => -3.43],
                    ['label' => 'SIMA03-16', 'is_correct_response' => true,  'ability_after' => -3.40],
                    ['label' => 'SIMA01-17', 'is_correct_response' => true,  'ability_after' => -3.36],
                    ['label' => 'SIMA01-06', 'is_correct_response' => true,  'ability_after' => -3.33],
                    ['label' => 'FINISH',    'is_correct_response' => true,  'ability_after' => -3.31],
                ],
                'initialability' => 0.0,
                'initialse' => 1.0,
                'settings' => [
                    'maxquestions' => 250,
                    'maxquestionspersubscale' => 25,
                    'pilot_ratio' => 50,
                    'pilot_attempts_threshold' => 0,
                ],
                'final_abilities' => [
                    'Simulation' => -3.31,
                    'SimA' => -3.31,
                    'SimA01' => -3.33,
                    'SimA02' => -3.33,
                    'SimA03' => -3.25,
                    'SimA04' => -3.31,
                    'SimA05' => -3.35,
                    'SimA06' => -3.31,
                    'SimB' => -3.31,
                    'SimB01' => -3.31,
                    'SimB02' => -3.31,
                ],
            ],
            'radical CAT 2' => [
                'strategy' => LOCAL_CATQUIZ_STRATEGY_FASTEST,
                'questions' => [
                    ['label' => 'SIMB01-18', 'is_correct_response' => true,  'ability_before' => 0.00, 'ability_after' => 0.00],
                    ['label' => 'SIMC03-15', 'is_correct_response' => true,  'ability_after' => 0.46],
                    ['label' => 'SIMB03-04', 'is_correct_response' => true,  'ability_after' => 0.85],
                    ['label' => 'SIMB03-06', 'is_correct_response' => true,  'ability_after' => 1.22],
                    ['label' => 'SIMB03-11', 'is_correct_response' => true,  'ability_after' => 1.55],
                    ['label' => 'SIMB02-12', 'is_correct_response' => true,  'ability_after' => 1.64],
                    ['label' => 'SIMB02-07', 'is_correct_response' => true,  'ability_after' => 2.15],
                    ['label' => 'SIMB04-03', 'is_correct_response' => true,  'ability_after' => 2.59],
                    ['label' => 'SIMB04-06', 'is_correct_response' => true,  'ability_after' => 2.92],
                    ['label' => 'SIMC10-09', 'is_correct_response' => true,  'ability_after' => 3.14],
                    ['label' => 'SIMC10-00', 'is_correct_response' => true,  'ability_after' => 3.31],
                    ['label' => 'SIMC10-01', 'is_correct_response' => true,  'ability_after' => 3.46],
                    ['label' => 'SIMC05-17', 'is_correct_response' => true,  'ability_after' => 3.57],
                    ['label' => 'SIMC06-14', 'is_correct_response' => true,  'ability_after' => 3.70],
                    ['label' => 'SIMC07-08', 'is_correct_response' => true,  'ability_after' => 3.82],
                    ['label' => 'SIMC05-03', 'is_correct_response' => true,  'ability_after' => 3.93],
                    ['label' => 'SIMC06-04', 'is_correct_response' => true,  'ability_after' => 4.06],
                    ['label' => 'SIMC06-17', 'is_correct_response' => true,  'ability_after' => 4.20],
                    ['label' => 'SIMC09-10', 'is_correct_response' => true,  'ability_after' => 4.28],
                    ['label' => 'SIMC09-16', 'is_correct_response' => true,  'ability_after' => 4.46],
                    ['label' => 'SIMC08-12', 'is_correct_response' => false, 'ability_after' => 4.62],
                    ['label' => 'SIMC08-11', 'is_correct_response' => false, 'ability_after' => 4.74],
                    ['label' => 'SIMC09-05', 'is_correct_response' => true,  'ability_after' => 4.66],
                    ['label' => 'SIMC08-18', 'is_correct_response' => true,  'ability_after' => 4.70],
                    ['label' => 'SIMC08-16', 'is_correct_response' => false, 'ability_after' => 4.76],
                    ['label' => 'FINISH'   , 'is_correct_response' => false, 'ability_after' => 4.73],
                ],
                'initialability' => 0.0,
                'initialse' => 1.0,
                'settings' => [
                    'maxquestions' => 250,
                    'maxquestionspersubscale' => 25,
                ],
                'final_abilities' => [
                    'Simulation' => 4.73,
                    'SimA' => 4.73,
                    'SimB' => 4.73,
                    'SimB01' => 4.73,
                    'SimB02' => 4.73,
                    'SimB03' => 4.73,
                    'SimB04' => 4.73,
                    'SimC' => 4.73,
                    'SimC03' => 4.73,
                    'SimC05' => 4.73,
                    'SimC06' => 4.74,
                    'SimC07' => 4.73,
                    'SimC08' => 4.54,
                    'SimC09' => 4.79,
                    'SimC10' => 4.73,
                ],
            ],
            'radical CAT 3' => [
                'strategy' => LOCAL_CATQUIZ_STRATEGY_FASTEST,
                'questions' => [
                    ['label' => 'SIMB01-18', 'is_correct_response' => true,  'ability_before' => 0.00, 'ability_after' => 0.00],
                    ['label' => 'SIMC03-15', 'is_correct_response' => true,  'ability_after' => 0.46],
                    ['label' => 'SIMB03-04', 'is_correct_response' => true,  'ability_after' => 0.85],
                    ['label' => 'SIMB03-06', 'is_correct_response' => true,  'ability_after' => 1.22],
                    ['label' => 'SIMB03-11', 'is_correct_response' => true,  'ability_after' => 1.55],
                    ['label' => 'SIMB02-12', 'is_correct_response' => false, 'ability_after' => 1.64],
                    ['label' => 'SIMB01-01', 'is_correct_response' => false, 'ability_after' => 1.76],
                    ['label' => 'SIMB03-05', 'is_correct_response' => true,  'ability_after' => 1.62],
                    ['label' => 'SIMB02-09', 'is_correct_response' => false, 'ability_after' => 1.68],
                    ['label' => 'SIMA02-13', 'is_correct_response' => true,  'ability_after' => 1.63],
                    ['label' => 'SIMC03-12', 'is_correct_response' => false, 'ability_after' => 1.65],
                    ['label' => 'SIMB01-04', 'is_correct_response' => true,  'ability_after' => 1.53],
                    ['label' => 'SIMB03-12', 'is_correct_response' => true,  'ability_after' => 1.54],
                    ['label' => 'SIMC03-13', 'is_correct_response' => true,  'ability_after' => 1.56],
                    ['label' => 'SIMB03-14', 'is_correct_response' => true,  'ability_after' => 1.58],
                    ['label' => 'SIMB01-11', 'is_correct_response' => true,  'ability_after' => 1.60],
                    ['label' => 'SIMB03-08', 'is_correct_response' => false, 'ability_after' => 1.61],
                    ['label' => 'SIMB03-16', 'is_correct_response' => true,  'ability_after' => 1.56],
                    ['label' => 'FINISH',    'is_correct_response' => null,  'ability_after' => 1.57],
                ],
                'initialability' => 0.0,
                'initialse' => 1.0,
                'settings' => [
                    'maxquestions' => 250,
                    'maxquestionspersubscale' => 25,
                ],
                'final_abilities' => [
                    'Simulation' => 1.57,
                    'SimA' => 1.58,
                    'SimA02' => 1.58,
                    'SimB' => 1.63,
                    'SimB01' => 1.48,
                    'SimB02' => 1.58,
                    'SimB03' => 1.81,
                    'SimB04' => 1.63,
                    'SimC' => 1.16,
                    'SimC03' => 1.16,
                ],
            ],
            'radical CAT 4' => [
                'strategy' => LOCAL_CATQUIZ_STRATEGY_FASTEST,
                'questions' => [
                    ['label' => 'SIMB01-18', 'is_correct_response' => true,  'ability_before' => 0.00, 'ability_after' => 0.00],
                    ['label' => 'SIMC03-15', 'is_correct_response' => true,  'ability_after' => 0.46],
                    ['label' => 'SIMB03-04', 'is_correct_response' => false, 'ability_after' => 0.85],
                    ['label' => 'SIMB03-10', 'is_correct_response' => true,  'ability_after' => 0.76],
                    ['label' => 'SIMB01-04', 'is_correct_response' => true,  'ability_after' => 0.88],
                    ['label' => 'SIMB03-16', 'is_correct_response' => false, 'ability_after' => 1.06],
                    ['label' => 'SIMB01-11', 'is_correct_response' => true,  'ability_after' => 0.95],
                    ['label' => 'SIMA02-13', 'is_correct_response' => false, 'ability_after' => 1.03],
                    ['label' => 'SIMB03-12', 'is_correct_response' => false, 'ability_after' => 0.98],
                    ['label' => 'SIMC02-03', 'is_correct_response' => true,  'ability_after' => 0.93],
                    ['label' => 'SIMB02-03', 'is_correct_response' => false, 'ability_after' => 0.98],
                    ['label' => 'SIMC03-11', 'is_correct_response' => true,  'ability_after' => 0.93],
                    ['label' => 'SIMC03-13', 'is_correct_response' => true,  'ability_after' => 0.94],
                    ['label' => 'SIMB03-11', 'is_correct_response' => false, 'ability_after' => 0.98],
                    ['label' => 'SIMB03-09', 'is_correct_response' => false, 'ability_after' => 0.96],
                    ['label' => 'SIMC03-18', 'is_correct_response' => false, 'ability_after' => 0.92],
                    ['label' => 'SIMB03-18', 'is_correct_response' => false, 'ability_after' => 0.88],
                    ['label' => 'SIMB03-07', 'is_correct_response' => true,  'ability_after' => 0.85],
                    ['label' => 'SIMC03-14', 'is_correct_response' => true,  'ability_after' => 0.87],
                    ['label' => 'SIMB01-06', 'is_correct_response' => true,  'ability_after' => 0.88],
                    ['label' => 'SIMB03-06', 'is_correct_response' => false, 'ability_after' => 0.90],
                    ['label' => 'FINISH',    'is_correct_response' => null,  'ability_after' => 0.90],
                ],
                'initialability' => 0.0,
                'initialse' => 1.0,
                'settings' => [
                    'maxquestions' => 250,
                    'maxquestionspersubscale' => 25,
                ],
                'final_abilities' => [
                    'Simulation' => 0.9,
                    'SimA' => 0.89,
                    'SimA02' => 0.89,
                    'SimB' => 0.83,
                    'SimB01' => 0.97,
                    'SimB02' => 0.8,
                    'SimB03' => 0.62,
                    'SimC' => 1.18,
                    'SimC02' => 0.93,
                    'SimC03' => 1.07,
                ],
            ],
            'radical CAT 5' => [
                'strategy' => LOCAL_CATQUIZ_STRATEGY_FASTEST,
                'questions' => [
                    ['label' => 'SIMB01-18', 'is_correct_response' => true,  'ability_before' => 0.00, 'ability_after' => 0.00],
                    ['label' => 'SIMC03-15', 'is_correct_response' => true,  'ability_after' => 0.46],
                    ['label' => 'SIMB03-04', 'is_correct_response' => true,  'ability_after' => 0.85],
                    ['label' => 'SIMB03-06', 'is_correct_response' => true,  'ability_after' => 1.22],
                    ['label' => 'SIMB03-11', 'is_correct_response' => true,  'ability_after' => 1.55],
                    ['label' => 'SIMB02-12', 'is_correct_response' => true,  'ability_after' => 1.64],
                    ['label' => 'SIMB02-07', 'is_correct_response' => true,  'ability_after' => 2.15],
                    ['label' => 'SIMB04-03', 'is_correct_response' => true,  'ability_after' => 2.59],
                    ['label' => 'SIMB04-06', 'is_correct_response' => true,  'ability_after' => 2.92],
                    ['label' => 'SIMC10-09', 'is_correct_response' => true,  'ability_after' => 3.14],
                    ['label' => 'SIMC10-00', 'is_correct_response' => true,  'ability_after' => 3.31],
                    ['label' => 'SIMC10-01', 'is_correct_response' => true,  'ability_after' => 3.46],
                    ['label' => 'SIMC05-17', 'is_correct_response' => false, 'ability_after' => 3.57],
                    ['label' => 'SIMC10-12', 'is_correct_response' => false, 'ability_after' => 3.60],
                    ['label' => 'SIMB04-08', 'is_correct_response' => true,  'ability_after' => 3.50],
                    ['label' => 'SIMC07-15', 'is_correct_response' => true,  'ability_after' => 3.55],
                    ['label' => 'SIMC06-14', 'is_correct_response' => false, 'ability_after' => 3.61],
                    ['label' => 'SIMC05-11', 'is_correct_response' => false, 'ability_after' => 3.57],
                    ['label' => 'SIMC04-17', 'is_correct_response' => true,  'ability_after' => 3.52],
                    ['label' => 'SIMC07-08', 'is_correct_response' => true,  'ability_after' => 3.58],
                    ['label' => 'SIMC07-06', 'is_correct_response' => true,  'ability_after' => 3.64],
                    ['label' => 'SIMC10-16', 'is_correct_response' => true,  'ability_after' => 3.68],
                    ['label' => 'SIMC06-13', 'is_correct_response' => true,  'ability_after' => 3.72],
                    ['label' => 'SIMC05-03', 'is_correct_response' => false, 'ability_after' => 3.77],
                    ['label' => 'SIMC06-09', 'is_correct_response' => false, 'ability_after' => 3.75],
                    ['label' => 'FINISH',    'is_correct_response' => null,  'ability_after' => 3.72],
                ],
                'initialability' => 0.0,
                'initialse' => 1.0,
                'settings' => [
                    'maxquestions' => 250,
                    'maxquestionspersubscale' => 25,
                ],
                'final_abilities' => [
                    'Simulation' => 3.72,
                    'SimA' => 3.72,
                    'SimB' => 3.73,
                    'SimB01' => 3.72,
                    'SimB02' => 3.72,
                    'SimB03' => 3.72,
                    'SimB04' => 3.73,
                    'SimC' => 3.7,
                    'SimC03' => 3.7,
                    'SimC04' => 3.74,
                    'SimC05' => 3.61,
                    'SimC06' => 3.62,
                    'SimC07' => 3.78,
                    'SimC10' => 3.71,
                ],
            ],
            /* 'moderate CAT' => [
            //    'strategy' => LOCAL_CATQUIZ_STRATEGY_BALANCED,
            //    'questions' => [
            //        [
            //            'label' => 'SIMA01-00',
            //            'is_correct_response' => true,
            //            'ability_before' => 0,
            //            'ability_after' => 0.0,
            //        ],
            //        [
            //            'label' => 'SIMA01-01',
            //            'is_correct_response' => false,
            //            'ability_before' => 0.0,
            //            'ability_after' => 0.0,
            //        ],
            //        [
            //            'label' => 'SIMA01-02',
            //            'is_correct_response' => true,
            //            'ability_before' => 0.0,
            //            'ability_after' => -4.4793,
            //        ],
            //    ],
            //],
            */
            // phpcs:enable
            'Infer lowest skillgap P000000 normal mode' => [
                'strategy' => LOCAL_CATQUIZ_STRATEGY_LOWESTSUB,
                'questions' => [
                    [ 'label' => 'SIMB01-18', 'is_correct_response' => false, 'ability_before' => 0.02, 'ability_after' => 0.02],
                    [ 'label' => 'SIMA06-15', 'is_correct_response' => false, 'ability_after' => -0.67],
                    [ 'label' => 'SIMA02-02', 'is_correct_response' => false, 'ability_after' => -1.30],
                    [ 'label' => 'SIMA02-19', 'is_correct_response' => false, 'ability_after' => -1.86],
                    [ 'label' => 'SIMA02-17', 'is_correct_response' => false, 'ability_after' => -2.33],
                    [ 'label' => 'SIMA06-02', 'is_correct_response' => false, 'ability_after' => -3.06],
                    [ 'label' => 'SIMB02-00', 'is_correct_response' => false, 'ability_after' => -3.06],
                    [ 'label' => 'SIMB01-17', 'is_correct_response' => false, 'ability_after' => -3.06],
                    [ 'label' => 'SIMB01-12', 'is_correct_response' => false, 'ability_after' => -3.06],
                    [ 'label' => 'SIMA02-04', 'is_correct_response' => false, 'ability_after' => -3.06],
                    [ 'label' => 'SIMB02-02', 'is_correct_response' => false, 'ability_after' => -3.39],
                    [ 'label' => 'SIMA01-13', 'is_correct_response' => true,  'ability_after' => -3.39],
                    [ 'label' => 'SIMA01-16', 'is_correct_response' => true,  'ability_after' => -3.41],
                    [ 'label' => 'SIMA01-19', 'is_correct_response' => false, 'ability_after' => -3.24],
                    [ 'label' => 'SIMA01-06', 'is_correct_response' => true,  'ability_after' => -3.35],
                    [ 'label' => 'SIMA03-13', 'is_correct_response' => true,  'ability_after' => -3.31],
                    [ 'label' => 'SIMA03-03', 'is_correct_response' => true,  'ability_after' => -3.27],
                    [ 'label' => 'SIMA03-16', 'is_correct_response' => true,  'ability_after' => -3.21],
                    [ 'label' => 'SIMA05-00', 'is_correct_response' => false, 'ability_after' => -3.15],
                    [ 'label' => 'SIMA05-07', 'is_correct_response' => false, 'ability_after' => -3.21],
                    [ 'label' => 'SIMA05-15', 'is_correct_response' => false, 'ability_after' => -3.25],
                    [ 'label' => 'SIMA01-07', 'is_correct_response' => false, 'ability_after' => -3.29],
                    [ 'label' => 'SIMA01-12', 'is_correct_response' => false, 'ability_after' => -3.31],
                    [ 'label' => 'SIMA01-14', 'is_correct_response' => true,  'ability_after' => -3.45],
                    [ 'label' => 'SIMA03-19', 'is_correct_response' => true,  'ability_after' => -3.41],
                    [ 'label' => 'FINISH',    'is_correct_response' => false, 'ability_after' => -3.38],
                ],
                'initial_ability' => 0.02,
                'initial_se' => 2.97,
                'settings' => [
                    'pp_min_inc' => 0.1,
                ],
                'final_abilities' => [
                    'Simulation' => -3.38,
                    'SimA' => -3.38,
                    'SimA01' => -3.47,
                    'SimA02' => -3.40,
                    'SimA03' => -3.28,
                    'SimA04' => -3.65,
                    'SimA05' => -3.43,
                    'SimA06' => -3.38,
                    'SimA07' => -3.65, // Inherited from parent.
                    'SimB' => -3.38,
                    'SimB01' => -3.38,
                    'SimB02' => -3.38,
                    'SimC' => -4.19, // Inherited from parent.
                ],
            ],
            'Infer lowest skillgap P000000 piloting mode' => [
                'strategy' => LOCAL_CATQUIZ_STRATEGY_LOWESTSUB,
                'questions' => [
                    [ 'label' => 'SIMB01-18', 'is_correct_response' => false, 'ability_before' => 0.02, 'ability_after' => 0.02],
                    [ 'label' => 'SIMA06-15', 'is_correct_response' => false, 'ability_after' => -0.67],
                    [ 'label' => 'Pilotfrage-1', 'is_correct_response' => false, 'ability_after' => -1.30],
                    [ 'label' => 'SIMA02-02', 'is_correct_response' => false, 'ability_after' => -1.30],
                    [ 'label' => 'Pilotfrage-2', 'is_correct_response' => false, 'ability_after' => -1.86],
                    [ 'label' => 'SIMA02-19', 'is_correct_response' => false, 'ability_after' => -1.86],
                    [ 'label' => 'SIMA02-17', 'is_correct_response' => false, 'ability_after' => -2.33],
                    [ 'label' => 'SIMA06-02', 'is_correct_response' => false, 'ability_after' => -3.06],
                    [ 'label' => 'Pilotfrage-3', 'is_correct_response' => false, 'ability_after' => -3.06],
                    [ 'label' => 'Pilotfrage-4', 'is_correct_response' => false, 'ability_after' => -3.06],
                    [ 'label' => 'Pilotfrage-5', 'is_correct_response' => false, 'ability_after' => -3.06],
                    [ 'label' => 'Pilotfrage-6', 'is_correct_response' => false, 'ability_after' => -3.06],
                    [ 'label' => 'Pilotfrage-7', 'is_correct_response' => false, 'ability_after' => -3.06],
                    [ 'label' => 'Pilotfrage-8', 'is_correct_response' => false, 'ability_after' => -3.06],
                    [ 'label' => 'Pilotfrage-9', 'is_correct_response' => false, 'ability_after' => -3.06],
                    [ 'label' => 'SIMB02-00', 'is_correct_response' => false, 'ability_after' => -3.06],
                    [ 'label' => 'SIMB01-17', 'is_correct_response' => false, 'ability_after' => -3.06],
                    [ 'label' => 'SIMB01-12', 'is_correct_response' => false, 'ability_after' => -3.06],
                    [ 'label' => 'Pilotfrage-10', 'is_correct_response' => false, 'ability_after' => -3.06],
                    [ 'label' => 'Pilotfrage-11', 'is_correct_response' => false, 'ability_after' => -3.06],
                    [ 'label' => 'SIMA02-04', 'is_correct_response' => false, 'ability_after' => -3.06],
                    [ 'label' => 'Pilotfrage-12', 'is_correct_response' => false, 'ability_after' => -3.39],
                    [ 'label' => 'SIMB02-02', 'is_correct_response' => false, 'ability_after' => -3.39],
                    [ 'label' => 'Pilotfrage-13', 'is_correct_response' => false, 'ability_after' => -3.39],
                    [ 'label' => 'Pilotfrage-14', 'is_correct_response' => false, 'ability_after' => -3.39],
                    [ 'label' => 'FINISH',    'is_correct_response' => false, 'ability_after' => -3.39],
                ],
                'initial_ability' => 0.02,
                'initial_se' => 2.97,
                'settings' => [
                    'pp_min_inc' => 0.1,
                    'pilot_ratio' => 50,
                    'pilot_attempts_threshold' => 0,
                    'maxquestionspersubscale' => 25,
                ],
            ],
            'Infer lowest skillgap P000001' => [
                'strategy' => LOCAL_CATQUIZ_STRATEGY_LOWESTSUB,
                'questions' => [
                    [ 'label' => 'SIMB01-18', 'is_correct_response' => true, 'ability_before' => 0.02, 'ability_after' => 0.02],
                    [ 'label' => 'SIMB03-10', 'is_correct_response' => true, 'ability_after' => 0.76],
                    [ 'label' => 'SIMB03-06', 'is_correct_response' => true, 'ability_after' => 1.32],
                    [ 'label' => 'SIMB01-04', 'is_correct_response' => true, 'ability_after' => 1.90],
                    [ 'label' => 'SIMB01-01', 'is_correct_response' => true, 'ability_after' => 1.94],
                    [ 'label' => 'SIMB03-05', 'is_correct_response' => true, 'ability_after' => 2.50],
                    [ 'label' => 'SIMB04-03', 'is_correct_response' => true, 'ability_after' => 2.55],
                    [ 'label' => 'SIMB04-08', 'is_correct_response' => true, 'ability_after' => 3.28],
                    [ 'label' => 'SIMB04-10', 'is_correct_response' => true, 'ability_after' => 3.86],
                    [ 'label' => 'SIMB03-15', 'is_correct_response' => true, 'ability_after' => 4.19],
                    [ 'label' => 'SIMC10-00', 'is_correct_response' => true, 'ability_after' => 4.21],
                    [ 'label' => 'SIMC07-08', 'is_correct_response' => true, 'ability_after' => 4.23],
                    [ 'label' => 'SIMC10-15', 'is_correct_response' => true, 'ability_after' => 4.38],
                    [ 'label' => 'SIMC07-09', 'is_correct_response' => true, 'ability_after' => 4.48],
                    [ 'label' => 'SIMC10-08', 'is_correct_response' => true, 'ability_after' => 4.61],
                    [ 'label' => 'SIMC08-16', 'is_correct_response' => false, 'ability_after' => 4.84],
                    [ 'label' => 'SIMC08-03', 'is_correct_response' => true, 'ability_after' => 4.71],
                    [ 'label' => 'SIMC09-16', 'is_correct_response' => true, 'ability_after' => 4.79],
                    [ 'label' => 'SIMC09-00', 'is_correct_response' => true, 'ability_after' => 4.86],
                    [ 'label' => 'SIMC04-00', 'is_correct_response' => false, 'ability_after' => 4.91],
                    [ 'label' => 'SIMC04-15', 'is_correct_response' => false, 'ability_after' => 4.73],
                    [ 'label' => 'SIMC09-11', 'is_correct_response' => true, 'ability_after' => 4.66],
                    [ 'label' => 'SIMC06-04', 'is_correct_response' => true, 'ability_after' => 4.69],
                    [ 'label' => 'SIMC06-10', 'is_correct_response' => true, 'ability_after' => 4.70],
                    [ 'label' => 'SIMC05-03', 'is_correct_response' => true, 'ability_after' => 4.71],
                    [ 'label' => 'FINISH', 'is_correct_respons' => null,     'ability_after' => 4.71],
                ],
                'initial_ability' => 0.02,
                'initial_se' => 2.97,
                'settings' => [
                    'pp_min_inc' => 0.1,
                ],
                'final_abilities' => [
                    'Simulation' => 4.71,
                    'SimB' => 4.72,
                    'SimB01' => 4.71,
                    'SimB03' => 4.71,
                    'SimB04' => 4.72,
                    'SimC' => 4.71,
                    'SimC04' => 4.57,
                    'SimC05' => 4.71,
                    'SimC06' => 4.73,
                    'SimC07' => 4.72,
                    'SimC08' => 4.72,
                    'SimC09' => 4.81,
                    'SimC10' => 4.73,
                ],
            ],
            'Infer lowest skillgap P000407' => [
                'strategy' => LOCAL_CATQUIZ_STRATEGY_LOWESTSUB,
                'questions' => [
                    ['label' => 'SIMB01-18', 'is_correct_response' => false, 'ability_before' => 0.02, 'ability_after' => 0.02],
                    ['label' => 'SIMA06-15', 'is_correct_response' => false, 'ability_after' => -0.67],
                    ['label' => 'SIMA02-02', 'is_correct_response' => false, 'ability_after' => -1.30],
                    ['label' => 'SIMA02-19', 'is_correct_response' => false, 'ability_after' => -1.86],
                    ['label' => 'SIMA02-17', 'is_correct_response' => false, 'ability_after' => -2.33],
                    ['label' => 'SIMA06-02', 'is_correct_response' => false, 'ability_after' => -3.06],
                    ['label' => 'SIMB02-00', 'is_correct_response' => false, 'ability_after' => -3.06],
                    ['label' => 'SIMB01-17', 'is_correct_response' => false, 'ability_after' => -3.06],
                    ['label' => 'SIMB01-12', 'is_correct_response' => false, 'ability_after' => -3.06],
                    ['label' => 'SIMA02-04', 'is_correct_response' => false, 'ability_after' => -3.06],
                    ['label' => 'SIMB02-02', 'is_correct_response' => false, 'ability_after' => -3.39],
                    ['label' => 'SIMA01-13', 'is_correct_response' => true , 'ability_after' => -3.39],
                    ['label' => 'SIMA01-16', 'is_correct_response' => true , 'ability_after' => -3.41],
                    ['label' => 'SIMA01-19', 'is_correct_response' => false, 'ability_after' => -3.24],
                    ['label' => 'SIMA01-06', 'is_correct_response' => false, 'ability_after' => -3.35],
                    ['label' => 'SIMA03-04', 'is_correct_response' => true , 'ability_after' => -3.66],
                    ['label' => 'SIMA03-03', 'is_correct_response' => false, 'ability_after' => -3.62],
                    ['label' => 'SIMA03-18', 'is_correct_response' => true , 'ability_after' => -3.72],
                    ['label' => 'SIMA03-16', 'is_correct_response' => true , 'ability_after' => -3.69],
                    ['label' => 'SIMA01-12', 'is_correct_response' => false, 'ability_after' => -3.62],
                    ['label' => 'SIMA01-10', 'is_correct_response' => true , 'ability_after' => -3.69],
                    ['label' => 'SIMA05-15', 'is_correct_response' => false, 'ability_after' => -3.67],
                    ['label' => 'SIMA05-00', 'is_correct_response' => false, 'ability_after' => -3.68],
                    ['label' => 'SIMA03-19', 'is_correct_response' => false, 'ability_after' => -3.69],
                    ['label' => 'SIMA05-07', 'is_correct_response' => false, 'ability_after' => -3.70],
                    ['label' => 'FINISH',    'is_correct_response' => null,  'ability_after' => -3.71],
                ],
                'initial_ability' => 0.02,
                'initial_se' => 2.97,
                'settings' => [
                    'pp_min_inc' => 0.1,
                ],
                'final_abilities' => [
                    'Simulation' => -3.71,
                    'SimA' => -3.71,
                    'SimA01' => -3.71,
                    'SimA02' => -3.71,
                    'SimA03' => -3.63,
                    'SimA05' => -3.72,
                    'SimA06' => -3.71,
                    'SimB' => -3.71,
                    'SimB01' => -3.71,
                    'SimB02' => -3.71,
                ],
            ],
            'Infer lowest skillgap P000642' => [
                'strategy' => LOCAL_CATQUIZ_STRATEGY_LOWESTSUB,
                'questions' => [
                    ['label' => 'SIMB01-18', 'is_correct_response' => true,  'ability_before' => 0.02, 'ability_after' => 0.02],
                    ['label' => 'SIMB03-10', 'is_correct_response' => true,  'ability_after' => 0.76],
                    ['label' => 'SIMB03-06', 'is_correct_response' => true,  'ability_after' => 1.32],
                    ['label' => 'SIMB01-04', 'is_correct_response' => true,  'ability_after' => 1.90],
                    ['label' => 'SIMB01-01', 'is_correct_response' => false, 'ability_after' => 1.94],
                    ['label' => 'SIMB02-12', 'is_correct_response' => true,  'ability_after' => 1.62],
                    ['label' => 'SIMB02-07', 'is_correct_response' => true,  'ability_after' => 2.11],
                    ['label' => 'SIMB04-05', 'is_correct_response' => true,  'ability_after' => 2.55],
                    ['label' => 'SIMB04-08', 'is_correct_response' => true,  'ability_after' => 3.06],
                    ['label' => 'SIMC07-08', 'is_correct_response' => true,  'ability_after' => 3.45],
                    ['label' => 'SIMC06-04', 'is_correct_response' => false, 'ability_after' => 3.82],
                    ['label' => 'SIMC06-14', 'is_correct_response' => false, 'ability_after' => 3.76],
                    ['label' => 'SIMC07-09', 'is_correct_response' => false, 'ability_after' => 3.61],
                    ['label' => 'SIMB04-10', 'is_correct_response' => false, 'ability_after' => 3.59],
                    ['label' => 'SIMC05-17', 'is_correct_response' => false, 'ability_after' => 3.52],
                    ['label' => 'SIMC05-07', 'is_correct_response' => false, 'ability_after' => 3.44],
                    ['label' => 'SIMC05-12', 'is_correct_response' => false, 'ability_after' => 3.31],
                    ['label' => 'SIMC10-12', 'is_correct_response' => true,  'ability_after' => 3.21],
                    ['label' => 'SIMC10-16', 'is_correct_response' => false, 'ability_after' => 3.33],
                    ['label' => 'SIMC05-05', 'is_correct_response' => true,  'ability_after' => 3.32],
                    ['label' => 'SIMC04-17', 'is_correct_response' => false, 'ability_after' => 3.35],
                    ['label' => 'SIMC04-06', 'is_correct_response' => true,  'ability_after' => 3.34],
                    ['label' => 'SIMC04-01', 'is_correct_response' => true,  'ability_after' => 3.36],
                    ['label' => 'SIMC10-01', 'is_correct_response' => true,  'ability_after' => 3.40],
                    ['label' => 'SIMC04-04', 'is_correct_response' => false, 'ability_after' => 3.44],
                    ['label' => 'FINISH',    'is_correct_response' => null,  'ability_after' => 3.43],
                ],
                'initial_ability' => 0.02,
                'initial_se' => 2.97,
                'settings' => [
                    'pp_min_inc' => 0.1,
                    'maxquestions' => 250,
                ],
                'final_abilities' => [
                    'Simulation' => 3.43,
                    'SimB' => 3.35,
                    'SimB01' => 1.39,
                    'SimB02' => 3.43,
                    'SimB03' => 3.43,
                    'SimB04' => 3.60,
                    'SimC' => 3.45,
                    'SimC04' => 3.58,
                    'SimC05' => 2.96,
                    'SimC06' => 3.43, // TODO: Check why this differs from new 07_teststrategie_tester.php output.
                    'SimC07' => 3.90,
                    'SimC10' => 3.71, // TODO: Check why this differs from new 07_teststrategie_tester.php output.
                ],
            ],
            'Infer lowest skillgap P000184' => [
                'strategy' => LOCAL_CATQUIZ_STRATEGY_LOWESTSUB,
                'questions' => [
                    ['label' => 'SIMB01-18', 'is_correct_response' => false, 'ability_before' => 0.02,  'ability_after' => 0.02],
                    ['label' => 'SIMA06-15', 'is_correct_response' => false, 'ability_after' => -0.67],
                    ['label' => 'SIMA02-02', 'is_correct_response' => false, 'ability_after' => -1.30],
                    ['label' => 'SIMA02-19', 'is_correct_response' => false, 'ability_after' => -1.86],
                    ['label' => 'SIMA02-17', 'is_correct_response' => false, 'ability_after' => -2.33],
                    ['label' => 'SIMA06-02', 'is_correct_response' => false, 'ability_after' => -3.06],
                    ['label' => 'SIMB02-00', 'is_correct_response' => false, 'ability_after' => -3.06],
                    ['label' => 'SIMB01-17', 'is_correct_response' => false, 'ability_after' => -3.06],
                    ['label' => 'SIMB01-12', 'is_correct_response' => false, 'ability_after' => -3.06],
                    ['label' => 'SIMA02-04', 'is_correct_response' => false, 'ability_after' => -3.06],
                    ['label' => 'SIMB02-02', 'is_correct_response' => false, 'ability_after' => -3.39],
                    ['label' => 'SIMA01-13', 'is_correct_response' => false, 'ability_after' => -3.39],
                    ['label' => 'SIMA01-00', 'is_correct_response' => false, 'ability_after' => -4.35],
                    ['label' => 'SIMA01-01', 'is_correct_response' => false, 'ability_after' => -4.85],
                    ['label' => 'SIMA01-09', 'is_correct_response' => true , 'ability_after' => -5.05],
                    ['label' => 'SIMA03-00', 'is_correct_response' => false, 'ability_after' => -4.80],
                    ['label' => 'SIMA03-08', 'is_correct_response' => false, 'ability_after' => -4.85],
                    ['label' => 'SIMA03-01', 'is_correct_response' => false, 'ability_after' => -4.87],
                    ['label' => 'SIMA03-05', 'is_correct_response' => true , 'ability_after' => -4.91],
                    ['label' => 'SIMA03-06', 'is_correct_response' => false, 'ability_after' => -4.83],
                    ['label' => 'SIMA03-14', 'is_correct_response' => false, 'ability_after' => -4.84],
                    ['label' => 'SIMA01-10', 'is_correct_response' => false, 'ability_after' => -4.85],
                    ['label' => 'SIMA03-17', 'is_correct_response' => false, 'ability_after' => -4.86],
                    ['label' => 'SIMA03-18', 'is_correct_response' => false, 'ability_after' => -4.87],
                    ['label' => 'SIMA03-02', 'is_correct_response' => true,  'ability_after' => -4.88],
                    ['label' => 'FINISH',    'is_correct_response' => false, 'ability_after' => -4.85],
                ],
                'initial_ability' => 0.02,
                'initial_se' => 2.97,
                'settings' => [
                    'pp_min_inc' => 0.1,
                ],
                'final_abilities' => [
                    'Simulation' => -4.85,
                    'SimA' => -4.85,
                    'SimA01' => -4.81,
                    'SimA02' => -4.85,
                    'SimA03' => -4.9,
                    'SimA06' => -4.85,
                    'SimB' => -4.85,
                    'SimB01' => -4.85,
                    'SimB02' => -4.85,
                ],
            ],
            'Classical Test P000000' => [
                'strategy' => LOCAL_CATQUIZ_STRATEGY_CLASSIC,
                'questions' => [
                    ['label' => 'SIMA01-00', 'is_correct_response' => true,  'ability_before' => 0.02, 'ability_after' => 0.02],
                    ['label' => 'SIMA01-01', 'is_correct_response' => true,  'ability_after' => 0.02],
                    ['label' => 'SIMA01-02', 'is_correct_response' => false, 'ability_after' => 0.02],
                    ['label' => 'SIMA01-03', 'is_correct_response' => true,  'ability_after' => -3.94],
                    ['label' => 'SIMA01-04', 'is_correct_response' => false, 'ability_after' => -3.72],
                    ['label' => 'SIMA01-05', 'is_correct_response' => true,  'ability_after' => -3.93],
                    ['label' => 'SIMA01-06', 'is_correct_response' => true,  'ability_after' => -3.4],
                    ['label' => 'SIMA01-07', 'is_correct_response' => false, 'ability_after' => -3.22],
                    ['label' => 'SIMA01-08', 'is_correct_response' => true,  'ability_after' => -3.41],
                    ['label' => 'SIMA01-09', 'is_correct_response' => true,  'ability_after' => -3.16],
                    ['label' => 'SIMA01-10', 'is_correct_response' => true,  'ability_after' => -3.15],
                    ['label' => 'SIMA01-11', 'is_correct_response' => true,  'ability_after' => -3.12],
                    ['label' => 'SIMA01-12', 'is_correct_response' => false, 'ability_after' => -2.99],
                    ['label' => 'SIMA01-13', 'is_correct_response' => true,  'ability_after' => -3.53],
                    ['label' => 'SIMA01-14', 'is_correct_response' => true,  'ability_after' => -3.48],
                    ['label' => 'SIMA01-15', 'is_correct_response' => false, 'ability_after' => -3.39],
                    ['label' => 'SIMA01-16', 'is_correct_response' => true,  'ability_after' => -3.41],
                    ['label' => 'SIMA01-17', 'is_correct_response' => true,  'ability_after' => -3.33],
                    ['label' => 'SIMA01-18', 'is_correct_response' => true,  'ability_after' => -3.28],
                    ['label' => 'SIMA01-19', 'is_correct_response' => false, 'ability_after' => -3.24],
                ],
                'initial_ability' => 0.02,
                'initial_se' => 2.97,
                'settings' => [
                    'pp_min_inc' => 0.1,
                    'maxquestionspersubscale' => -1,
                ],
            ],
            'Classical Test P000000 piloting' => [
                'strategy' => LOCAL_CATQUIZ_STRATEGY_CLASSIC,
                'questions' => [
                    ['label' => 'SIMA01-00', 'is_correct_response' => true,  'ability_before' => 0.02, 'ability_after' => 0.02],
                    ['label' => 'SIMA01-01', 'is_correct_response' => true,  'ability_after' => 0.02],
                    ['label' => 'Pilotfrage-1', 'is_correct_response' => false, 'ability_after' => 0.02],
                    ['label' => 'SIMA01-02', 'is_correct_response' => false, 'ability_after' => 0.02],
                    ['label' => 'Pilotfrage-2', 'is_correct_response' => true,  'ability_after' => -3.94],
                    ['label' => 'SIMA01-03', 'is_correct_response' => true,  'ability_after' => -3.94],
                    ['label' => 'SIMA01-04', 'is_correct_response' => false, 'ability_after' => -3.72],
                    ['label' => 'SIMA01-05', 'is_correct_response' => true,  'ability_after' => -3.93],
                    ['label' => 'Pilotfrage-3', 'is_correct_response' => true,  'ability_after' => -3.4],
                    ['label' => 'Pilotfrage-4', 'is_correct_response' => true,  'ability_after' => -3.4],
                    ['label' => 'Pilotfrage-5', 'is_correct_response' => true,  'ability_after' => -3.4],
                    ['label' => 'Pilotfrage-6', 'is_correct_response' => true,  'ability_after' => -3.4],
                    ['label' => 'Pilotfrage-7', 'is_correct_response' => true,  'ability_after' => -3.4],
                    ['label' => 'Pilotfrage-8', 'is_correct_response' => true,  'ability_after' => -3.4],
                    ['label' => 'Pilotfrage-9', 'is_correct_response' => true,  'ability_after' => -3.4],
                    ['label' => 'SIMA01-06', 'is_correct_response' => true,  'ability_after' => -3.4],
                    ['label' => 'SIMA01-07', 'is_correct_response' => false, 'ability_after' => -3.22],
                    ['label' => 'SIMA01-08', 'is_correct_response' => true,  'ability_after' => -3.41],
                    ['label' => 'Pilotfrage-10', 'is_correct_response' => true,  'ability_after' => -3.16],
                    ['label' => 'Pilotfrage-11', 'is_correct_response' => true,  'ability_after' => -3.16],
                    ['label' => 'SIMA01-09', 'is_correct_response' => true,  'ability_after' => -3.16],
                ],
                'initial_ability' => 0.02,
                'initial_se' => 2.97,
                'settings' => [
                    'pilot_ratio' => 50,
                    'pilot_attempts_threshold' => 0,
                    'pp_min_inc' => 0.1,
                    'maxquestionspersubscale' => -1,
                ],
            ],
            'Infer greatest strength P000000' => [
                'strategy' => LOCAL_CATQUIZ_STRATEGY_HIGHESTSUB,
                'questions' => [
                    ['label' => 'SIMB01-18', 'is_correct_response' => false, 'ability_before' => 0.02, 'ability_after' => 0.02],
                    ['label' => 'SIMA06-15', 'is_correct_response' => false, 'ability_after'  => -0.67],
                    ['label' => 'SIMA02-02', 'is_correct_response' => false, 'ability_after'  => -1.3],
                    ['label' => 'SIMA02-19', 'is_correct_response' => false, 'ability_after'  => -1.86],
                    ['label' => 'SIMA06-02', 'is_correct_response' => false, 'ability_after'  => -2.33],
                    ['label' => 'SIMA02-17', 'is_correct_response' => false, 'ability_after'  => -2.34],
                    ['label' => 'SIMB02-00', 'is_correct_response' => false, 'ability_after'  => -3.06],
                    ['label' => 'SIMB01-17', 'is_correct_response' => false, 'ability_after'  => -3.06],
                    ['label' => 'SIMB01-12', 'is_correct_response' => false, 'ability_after'  => -3.06],
                    ['label' => 'SIMA02-04', 'is_correct_response' => false, 'ability_after'  => -3.06],
                    ['label' => 'SIMB02-02', 'is_correct_response' => false, 'ability_after'  => -3.39],
                    ['label' => 'SIMB01-13', 'is_correct_response' => false, 'ability_after'  => -3.39],
                    ['label' => 'SIMA06-12', 'is_correct_response' => false, 'ability_after'  => -3.39],
                    ['label' => 'SIMA01-16', 'is_correct_response' => true,  'ability_after'  => -3.41],
                    ['label' => 'SIMA01-19', 'is_correct_response' => false, 'ability_after'  => -3.28],
                    ['label' => 'SIMA01-12', 'is_correct_response' => false, 'ability_after'  => -3.40],
                    ['label' => 'SIMA01-13', 'is_correct_response' => true,  'ability_after'  => -3.66],
                    ['label' => 'SIMA03-03', 'is_correct_response' => true,  'ability_after'  => -3.58],
                    ['label' => 'SIMA03-16', 'is_correct_response' => true,  'ability_after'  => -3.5],
                    ['label' => 'SIMA03-13', 'is_correct_response' => true,  'ability_after'  => -3.42],
                    ['label' => 'SIMA03-19', 'is_correct_response' => true,  'ability_after'  => -3.39],
                    ['label' => 'SIMA05-07', 'is_correct_response' => false, 'ability_after'  => -3.36],
                    ['label' => 'SIMA05-00', 'is_correct_response' => false, 'ability_after'  => -3.38],
                    ['label' => 'SIMA05-15', 'is_correct_response' => false, 'ability_after'  => -3.4],
                    ['label' => 'SIMA05-14', 'is_correct_response' => false, 'ability_after'  => -3.42],
                    ['label' => 'FINISH',    'is_correct_response' => false, 'ability_after'  => -3.44],
                ],
                'initial_ability' => 0.02,
                'initial_se' => 2.97,
                'settings' => [
                    'pp_min_inc' => 0.1,
                    'maxquestions' => 25,
                    'maxquestionspersubscale' => 10,
                    'minquestionspersubscale' => 3,
                ],
                'final_abilities' => [
                    'Simulation' => -3.44,
                    'SimA' => -3.44,
                    'SimA01' => -3.56,
                    'SimA02' => -3.45,
                    'SimA03' => -3.32,
                    'SimA05' => -3.49,
                    'SimA06' => -3.44,
                    'SimB' => -3.44,
                    'SimB01' => -3.44,
                    'SimB02' => -3.44,
                ],
            ],
            'Infer greatest strength with low ability' => [
                'strategy' => LOCAL_CATQUIZ_STRATEGY_HIGHESTSUB,
                'questions' => [
                    ['label' => 'SIMB01-18', 'is_correct_response' => false, 'ability_before' => 0.02, 'ability_after' => 0.02],
                    ['label' => 'SIMA06-15', 'is_correct_response' => false, 'ability_after' => -0.67],
                    ['label' => 'SIMA02-02', 'is_correct_response' => false, 'ability_after' => -1.3],
                    ['label' => 'SIMA02-19', 'is_correct_response' => false, 'ability_after' => -1.86],
                    ['label' => 'SIMA06-02', 'is_correct_response' => false, 'ability_after' => -2.33],
                    ['label' => 'SIMA02-17', 'is_correct_response' => false, 'ability_after' => -2.34],
                    ['label' => 'SIMB02-00', 'is_correct_response' => false, 'ability_after' => -3.06],
                    ['label' => 'SIMB01-17', 'is_correct_response' => false, 'ability_after' => -3.06],
                    ['label' => 'SIMB01-12', 'is_correct_response' => false, 'ability_after' => -3.06],
                    ['label' => 'SIMA02-04', 'is_correct_response' => false, 'ability_after' => -3.06],
                    ['label' => 'SIMB02-02', 'is_correct_response' => false, 'ability_after' => -3.39],
                    ['label' => 'SIMB01-13', 'is_correct_response' => false, 'ability_after' => -3.39],
                    ['label' => 'SIMA06-12', 'is_correct_response' => false, 'ability_after' => -3.39],
                    ['label' => 'SIMA01-16', 'is_correct_response' => false, 'ability_after' => -3.41],
                    ['label' => 'SIMA01-00', 'is_correct_response' => false, 'ability_after' => -4.16],
                    ['label' => 'SIMA01-01', 'is_correct_response' => false, 'ability_after' => -4.84],
                    ['label' => 'SIMA03-00', 'is_correct_response' => false, 'ability_after' => -5.05],
                    ['label' => 'SIMA03-08', 'is_correct_response' => false, 'ability_after' => -5.09],
                    ['label' => 'SIMA01-09', 'is_correct_response' => true,  'ability_after' => -5.13],
                    ['label' => 'SIMA03-01', 'is_correct_response' => false, 'ability_after' => -4.87],
                    ['label' => 'SIMA03-05', 'is_correct_response' => false, 'ability_after' => -4.91],
                    ['label' => 'SIMC01-16', 'is_correct_response' => false, 'ability_after' => -4.95],
                    ['label' => 'SIMA03-06', 'is_correct_response' => false, 'ability_after' => -4.95],
                    ['label' => 'SIMA01-10', 'is_correct_response' => false, 'ability_after' => -4.96],
                    ['label' => 'SIMC02-08', 'is_correct_response' => false, 'ability_after' => -4.97],
                    ['label' => 'FINISH', 'is_correct_response' => null, 'ability_after' => -4.98],
                ],
                'initial_ability' => 0.02,
                'initial_se' => 2.97,
                'settings' => [
                    'pp_min_inc' => 0.1,
                    'maxquestions' => 25,
                    'maxquestionspersubscale' => 10,
                    'minquestionspersubscale' => 3,
                ],
                'final_abilities' => [
                    'Simulation' => -4.98,
                    'SimA' => -4.97,
                    'SimA01' => -4.81,
                    'SimA02' => -4.98,
                    'SimA03' => -5.08,
                    'SimA06' => -4.98,
                    'SimB' => -4.98,
                    'SimB01' => -4.98,
                    'SimB02' => -4.98,
                ],
            ],
            'Infer greatest strength with high ability P000970' => [
                'strategy' => LOCAL_CATQUIZ_STRATEGY_HIGHESTSUB,
                'questions' => [
                    ['label' => 'SIMB01-18', 'is_correct_response' => true, 'ability_before' => 0.02, 'ability_after' => 0.02],
                    ['label' => 'SIMB03-10', 'is_correct_response' => true, 'ability_after' => 0.76],
                    ['label' => 'SIMB03-06', 'is_correct_response' => true, 'ability_after' => 1.32],
                    ['label' => 'SIMB02-12', 'is_correct_response' => true, 'ability_after' => 1.9],
                    ['label' => 'SIMB02-07', 'is_correct_response' => true, 'ability_after' => 2.6],
                    ['label' => 'SIMB01-04', 'is_correct_response' => true, 'ability_after' => 3.18],
                    ['label' => 'SIMB01-01', 'is_correct_response' => true, 'ability_after' => 3.18],
                    ['label' => 'SIMC10-00', 'is_correct_response' => true, 'ability_after' => 3.2],
                    ['label' => 'SIMC07-08', 'is_correct_response' => true, 'ability_after' => 3.78],
                    ['label' => 'SIMC07-09', 'is_correct_response' => true, 'ability_after' => 4.23],
                    ['label' => 'SIMC09-16', 'is_correct_response' => true, 'ability_after' => 4.5],
                    ['label' => 'SIMC09-00', 'is_correct_response' => true, 'ability_after' => 4.94],
                    ['label' => 'SIMC10-15', 'is_correct_response' => true, 'ability_after' => 5.13],
                    ['label' => 'SIMC10-08', 'is_correct_response' => true, 'ability_after' => 5.13],
                    ['label' => 'SIMC09-06', 'is_correct_response' => true, 'ability_after' => 5.17],
                    ['label' => 'SIMC09-11', 'is_correct_response' => true, 'ability_after' => 5.33],
                    ['label' => 'SIMC07-04', 'is_correct_response' => false, 'ability_after' => 5.53],
                    ['label' => 'SIMC08-16', 'is_correct_response' => false, 'ability_after' => 5.05],
                    ['label' => 'SIMC08-12', 'is_correct_response' => true,  'ability_after' => 4.87],
                    ['label' => 'SIMC04-15', 'is_correct_response' => true, 'ability_after' => 4.94],
                    ['label' => 'SIMC04-00', 'is_correct_response' => true, 'ability_after' => 4.97],
                    ['label' => 'SIMC04-14', 'is_correct_response' => true, 'ability_after' => 4.98],
                    ['label' => 'SIMC10-06', 'is_correct_response' => true, 'ability_after' => 4.99],
                    ['label' => 'SIMC04-18', 'is_correct_response' => true, 'ability_after' => 5.00],
                    ['label' => 'SIMC09-14', 'is_correct_response' => false, 'ability_after' => 5.01],
                    ['label' => 'FINISH', 'is_correct_response' => null, 'ability_after' => 4.95],
                ],
                'initial_ability' => 0.02,
                'initial_se' => 2.97,
                'settings' => [
                    'pp_min_inc' => 0.1,
                    'maxquestions' => 25,
                    'maxquestionspersubscale' => 10,
                    'minquestionspersubscale' => 3,
                ],
                'final_abilities' => [
                    'Simulation' => 4.95,
                    'SimB' => 4.95,
                    'SimB01' => 4.95,
                    'SimB02' => 4.95,
                    'SimB03' => 4.95,
                    'SimC' => 4.95,
                    'SimC04' => 5.00,
                    'SimC07' => 4.23,
                    'SimC08' => 4.88,
                    'SimC09' => 5.12,
                    'SimC10' => 4.96,
                ],
            ],
            'Infer relevant scales P000000' => [
                'strategy' => LOCAL_CATQUIZ_STRATEGY_RELSUBS,
                'questions' => [
                    ['label' => 'SIMB01-18', 'is_correct_response' => false, 'ability_before' => 0.02, 'ability_after' => 0.02],
                    ['label' => 'SIMA06-15', 'is_correct_response' => false, 'ability_after' => -0.67],
                    ['label' => 'SIMA02-02', 'is_correct_response' => false, 'ability_after' => -1.3],
                    ['label' => 'SIMA02-19', 'is_correct_response' => false, 'ability_after' => -1.86],
                    ['label' => 'SIMA02-17', 'is_correct_response' => false, 'ability_after' => -2.33],
                    ['label' => 'SIMA06-02', 'is_correct_response' => false, 'ability_after' => -3.06],
                    ['label' => 'SIMB02-00', 'is_correct_response' => false, 'ability_after' => -3.06],
                    ['label' => 'SIMB01-17', 'is_correct_response' => false, 'ability_after' => -3.06],
                    ['label' => 'SIMA02-04', 'is_correct_response' => false, 'ability_after' => -3.06],
                    ['label' => 'SIMB01-12', 'is_correct_response' => false, 'ability_after' => -3.39],
                    ['label' => 'SIMA01-16', 'is_correct_response' => true,  'ability_after' => -3.39],
                    ['label' => 'SIMA01-19', 'is_correct_response' => false, 'ability_after' => -3.27],
                    ['label' => 'SIMA01-12', 'is_correct_response' => false, 'ability_after' => -3.39],
                    ['label' => 'SIMA01-13', 'is_correct_response' => true, 'ability_after' => -3.66],
                    ['label' => 'SIMA01-18', 'is_correct_response' => true, 'ability_after' => -3.58],
                    ['label' => 'SIMA01-14', 'is_correct_response' => true, 'ability_after' => -3.51],
                    ['label' => 'SIMA01-07', 'is_correct_response' => false, 'ability_after' => -3.44],
                    ['label' => 'SIMA03-13', 'is_correct_response' => true, 'ability_after' => -3.47],
                    ['label' => 'SIMA03-03', 'is_correct_response' => true, 'ability_after' => -3.43],
                    ['label' => 'SIMA03-16', 'is_correct_response' => true, 'ability_after' => -3.38],
                    ['label' => 'SIMA03-19', 'is_correct_response' => true, 'ability_after' => -3.33],
                    ['label' => 'SIMA01-08', 'is_correct_response' => true, 'ability_after' => -3.31],
                    ['label' => 'SIMA01-03', 'is_correct_response' => true, 'ability_after' => -3.27],
                    ['label' => 'SIMA06-19', 'is_correct_response' => false, 'ability_after' => -3.25],
                    ['label' => 'SIMA03-07', 'is_correct_response' => true, 'ability_after' => -3.26],
                    ['label' => 'SIMA01-11', 'is_correct_response' => true, 'ability_after' => -3.24],
                    ['label' => 'SIMA02-09', 'is_correct_response' => false, 'ability_after' => -3.21],
                    ['label' => 'SIMA03-11', 'is_correct_response' => true, 'ability_after' => -3.22],
                    ['label' => 'SIMA02-16', 'is_correct_response' => false, 'ability_after' => -3.21],
                    ['label' => 'SIMA03-15', 'is_correct_response' => true, 'ability_after' => -3.22],
                    ['label' => 'SIMA02-10', 'is_correct_response' => false, 'ability_after' => -3.2],
                    ['label' => 'SIMA03-09', 'is_correct_response' => true, 'ability_after' => -3.2],
                    ['label' => 'SIMA02-06', 'is_correct_response' => false, 'ability_after' => -3.19],
                    ['label' => 'SIMA03-14', 'is_correct_response' => true, 'ability_after' => -3.2],
                    ['label' => 'SIMA02-08', 'is_correct_response' => false, 'ability_after' => -3.19],
                    ['label' => 'SIMA03-10', 'is_correct_response' => false, 'ability_after' => -3.19],
                    ['label' => 'FINISH', 'is_correct_response' => null, 'ability_after' => -3.21],
                ],
                'initial_ability' => 0.02,
                'initial_se' => 2.97,
                'settings' => [
                    'maxquestions' => 250,
                    'pp_min_inc' => 0.1,
                    'standarderror_min' => 0.25,
                    'standarderror_max' => 0.5,
                    'minquestionspersubscale' => 3,
                    'fake_use_tr_factor' => false,
                ],
                'final_abilities' => [
                    'Simulation' => -3.21,
                    'SimA' => -3.21,
                    'SimA01' => -3.34,
                    'SimA02' => -3.26,
                    // phpcs:disable
                    'SimA03' => -1.69,
                    // phpcs:enable
                    'SimA06' => -3.21,
                    'SimB' => -3.21,
                    'SimB01' => -3.21,
                    'SimB02' => -3.21,
                ],
            ],
            'Infer relevant scales P000001' => [
                'strategy' => LOCAL_CATQUIZ_STRATEGY_RELSUBS,
                'questions' => [
                    ['label' => 'SIMB01-18', 'is_correct_response' => true , 'ability_before' => 0.02, 'ability_after' => 0.02],
                    ['label' => 'SIMB03-10', 'is_correct_response' => true , 'ability_after' => 0.76],
                    ['label' => 'SIMB03-06', 'is_correct_response' => true , 'ability_after' => 1.32],
                    ['label' => 'SIMB02-12', 'is_correct_response' => true , 'ability_after' => 1.9],
                    ['label' => 'SIMB02-07', 'is_correct_response' => true , 'ability_after' => 2.6],
                    ['label' => 'SIMB01-04', 'is_correct_response' => true , 'ability_after' => 3.18],
                    ['label' => 'SIMB01-01', 'is_correct_response' => true , 'ability_after' => 3.18],
                    ['label' => 'SIMB03-05', 'is_correct_response' => true , 'ability_after' => 3.2],
                    ['label' => 'SIMC10-01', 'is_correct_response' => true , 'ability_after' => 3.2],
                    ['label' => 'SIMC05-03', 'is_correct_response' => true , 'ability_after' => 3.85],
                    ['label' => 'SIMC10-15', 'is_correct_response' => true , 'ability_after' => 4.36],
                    ['label' => 'SIMC09-16', 'is_correct_response' => true , 'ability_after' => 4.45],
                    ['label' => 'SIMC09-00', 'is_correct_response' => true , 'ability_after' => 4.94],
                    ['label' => 'SIMC10-08', 'is_correct_response' => true , 'ability_after' => 5.13],
                    ['label' => 'SIMC05-14', 'is_correct_response' => true , 'ability_after' => 5.17],
                    ['label' => 'SIMC09-06', 'is_correct_response' => false, 'ability_after' => 5.18],
                    ['label' => 'SIMC09-01', 'is_correct_response' => false, 'ability_after' => 4.87],
                    ['label' => 'SIMC08-16', 'is_correct_response' => false, 'ability_after' => 4.68],
                    ['label' => 'SIMC08-03', 'is_correct_response' => true , 'ability_after' => 4.65],
                    ['label' => 'SIMC08-12', 'is_correct_response' => false, 'ability_after' => 4.69],
                    ['label' => 'SIMC08-00', 'is_correct_response' => false, 'ability_after' => 4.63],
                    ['label' => 'SIMC08-04', 'is_correct_response' => true , 'ability_after' => 4.55],
                    ['label' => 'SIMC09-10', 'is_correct_response' => true , 'ability_after' => 4.56],
                    ['label' => 'SIMC04-00', 'is_correct_response' => false, 'ability_after' => 4.6],
                    ['label' => 'SIMC04-15', 'is_correct_response' => false, 'ability_after' => 4.55],
                    ['label' => 'SIMC04-04', 'is_correct_response' => true , 'ability_after' => 4.52],
                    ['label' => 'SIMC04-17', 'is_correct_response' => true , 'ability_after' => 4.53],
                    ['label' => 'SIMC04-16', 'is_correct_response' => true , 'ability_after' => 4.53],
                    ['label' => 'SIMC04-14', 'is_correct_response' => true , 'ability_after' => 4.54],
                    ['label' => 'SIMC05-08', 'is_correct_response' => true , 'ability_after' => 4.54],
                    ['label' => 'SIMC10-06', 'is_correct_response' => true , 'ability_after' => 4.55],
                    ['label' => 'SIMC06-10', 'is_correct_response' => true , 'ability_after' => 4.56],
                    ['label' => 'SIMC06-04', 'is_correct_response' => true , 'ability_after' => 4.57],
                    ['label' => 'SIMC06-07', 'is_correct_response' => true , 'ability_after' => 4.57],
                    ['label' => 'SIMC06-03', 'is_correct_response' => true , 'ability_after' => 4.58],
                    ['label' => 'SIMC07-09', 'is_correct_response' => true , 'ability_after' => 4.58],
                    ['label' => 'SIMC07-04', 'is_correct_response' => true , 'ability_after' => 4.59],
                    ['label' => 'SIMC07-12', 'is_correct_response' => true , 'ability_after' => 4.59],
                    ['label' => 'SIMC07-13', 'is_correct_response' => true , 'ability_after' => 4.6],
                    ['label' => 'SIMC04-18', 'is_correct_response' => false, 'ability_after' => 4.6],
                    ['label' => 'SIMC04-02', 'is_correct_response' => true , 'ability_after' => 4.58],
                    ['label' => 'SIMC07-14', 'is_correct_response' => true , 'ability_after' => 4.59],
                    ['label' => 'SIMB02-13', 'is_correct_response' => true , 'ability_after' => 4.59],
                    ['label' => 'SIMC06-17', 'is_correct_response' => true , 'ability_after' => 4.6],
                    ['label' => 'SIMC10-07', 'is_correct_response' => true , 'ability_after' => 4.6],
                    ['label' => 'SIMC07-19', 'is_correct_response' => true , 'ability_after' => 4.6],
                    ['label' => 'SIMC10-05', 'is_correct_response' => true , 'ability_after' => 4.6],
                    ['label' => 'SIMC06-06', 'is_correct_response' => true , 'ability_after' => 4.61],
                    ['label' => 'SIMC07-08', 'is_correct_response' => true , 'ability_after' => 4.61],
                    ['label' => 'SIMC04-05', 'is_correct_response' => true , 'ability_after' => 4.61],
                    ['label' => 'SIMC06-02', 'is_correct_response' => true , 'ability_after' => 4.61],
                    ['label' => 'SIMC10-16', 'is_correct_response' => true , 'ability_after' => 4.62],
                    ['label' => 'SIMC07-06', 'is_correct_response' => true , 'ability_after' => 4.62],
                    ['label' => 'SIMC10-04', 'is_correct_response' => true , 'ability_after' => 4.62],
                    ['label' => 'SIMC07-07', 'is_correct_response' => true , 'ability_after' => 4.62],
                    ['label' => 'SIMC06-00', 'is_correct_response' => true , 'ability_after' => 4.62],
                    ['label' => 'SIMC04-01', 'is_correct_response' => true , 'ability_after' => 4.62],
                    ['label' => 'SIMC10-13', 'is_correct_response' => true , 'ability_after' => 4.62],
                    ['label' => 'SIMC07-15', 'is_correct_response' => true , 'ability_after' => 4.62],
                    ['label' => 'SIMC06-15', 'is_correct_response' => true , 'ability_after' => 4.63],
                    ['label' => 'SIMC10-17', 'is_correct_response' => true , 'ability_after' => 4.63],
                    ['label' => 'SIMC06-05', 'is_correct_response' => false, 'ability_after' => 4.63],
                    ['label' => 'FINISH', 'is_correct_response' => null, 'ability_after' => 4.62],
                ],
                'initial_ability' => 0.02,
                'initial_se' => 2.97,
                'settings' => [
                    'maxquestions' => 250,
                    'pp_min_inc' => 0.1,
                    'standarderror_min' => 0.25,
                    'standarderror_max' => 0.5,
                    'minquestionspersubscale' => 3,
                    'fake_use_tr_factor' => false,
                ],
                'final_abilities' => [
                    'Simulation' => 4.62,
                    'SimB' => 4.62,
                    'SimB01' => 4.62,
                    'SimB02' => 4.62,
                    'SimB03' => 4.62,
                    'SimC' => 4.62,
                    'SimC04' => 4.29,
                    'SimC05' => 4.63,
                    'SimC06' => 4.98,
                    'SimC07' => 4.64,
                    'SimC08' => 4.43,
                    'SimC09' => 4.69,
                    'SimC10' => 4.65,
                ],
            ],
            'Infer relevant scales P000642' => [
                'strategy' => LOCAL_CATQUIZ_STRATEGY_RELSUBS,
                'questions' => [
                    ['label' => 'SIMB01-18', 'is_correct_response' => true, 'ability_before' => 0.02, 'ability_after' => 0.02],
                    ['label' => 'SIMB03-10', 'is_correct_response' => true, 'ability_after' => 0.76],
                    ['label' => 'SIMB03-06', 'is_correct_response' => true, 'ability_after' => 1.32],
                    ['label' => 'SIMB02-12', 'is_correct_response' => true, 'ability_after' => 1.9],
                    ['label' => 'SIMB02-07', 'is_correct_response' => true, 'ability_after' => 2.6],
                    ['label' => 'SIMB01-04', 'is_correct_response' => true, 'ability_after' => 3.18],
                    ['label' => 'SIMB01-01', 'is_correct_response' => false, 'ability_after' => 3.18],
                    ['label' => 'SIMB04-03', 'is_correct_response' => true, 'ability_after' => 2.55],
                    ['label' => 'SIMB04-06', 'is_correct_response' => true, 'ability_after' => 2.91],
                    ['label' => 'SIMB04-08', 'is_correct_response' => true, 'ability_after' => 3.15],
                    ['label' => 'SIMB04-10', 'is_correct_response' => false, 'ability_after' => 3.46],
                    ['label' => 'SIMB01-11', 'is_correct_response' => true, 'ability_after' => 3.37],
                    ['label' => 'SIMC10-01', 'is_correct_response' => true, 'ability_after' => 3.37],
                    ['label' => 'SIMC06-14', 'is_correct_response' => false, 'ability_after' => 3.51],
                    ['label' => 'SIMC05-17', 'is_correct_response' => false, 'ability_after' => 3.45],
                    ['label' => 'SIMC10-00', 'is_correct_response' => true, 'ability_after' => 3.39],
                    ['label' => 'SIMC05-07', 'is_correct_response' => false, 'ability_after' => 3.45],
                    ['label' => 'SIMC10-12', 'is_correct_response' => true, 'ability_after' => 3.36],
                    ['label' => 'SIMC10-16', 'is_correct_response' => false, 'ability_after' => 3.43],
                    ['label' => 'SIMC05-05', 'is_correct_response' => true, 'ability_after' => 3.42],
                    ['label' => 'SIMC05-12', 'is_correct_response' => false, 'ability_after' => 3.44],
                    ['label' => 'SIMC10-15', 'is_correct_response' => false, 'ability_after' => 3.37],
                    ['label' => 'SIMC06-09', 'is_correct_response' => false, 'ability_after' => 3.37],
                    ['label' => 'SIMC07-15', 'is_correct_response' => true, 'ability_after' => 3.36],
                    ['label' => 'SIMC07-06', 'is_correct_response' => false, 'ability_after' => 3.41],
                    ['label' => 'SIMC07-08', 'is_correct_response' => true, 'ability_after' => 3.4],
                    ['label' => 'SIMC07-09', 'is_correct_response' => false, 'ability_after' => 3.46],
                    ['label' => 'SIMC06-13', 'is_correct_response' => false, 'ability_after' => 3.45],
                    ['label' => 'SIMC04-17', 'is_correct_response' => false, 'ability_after' => 3.45],
                    ['label' => 'SIMC04-06', 'is_correct_response' => true, 'ability_after' => 3.44],
                    ['label' => 'SIMC04-01', 'is_correct_response' => true, 'ability_after' => 3.45],
                    ['label' => 'SIMC04-05', 'is_correct_response' => false, 'ability_after' => 3.47],
                    ['label' => 'SIMC04-16', 'is_correct_response' => true, 'ability_after' => 3.45],
                    ['label' => 'SIMC04-04', 'is_correct_response' => false, 'ability_after' => 3.48],
                    ['label' => 'SIMB04-14', 'is_correct_response' => false, 'ability_after' => 3.47],
                    ['label' => 'SIMB04-09', 'is_correct_response' => false, 'ability_after' => 3.46],
                    ['label' => 'SIMB04-05', 'is_correct_response' => true, 'ability_after' => 3.44],
                    ['label' => 'SIMB01-02', 'is_correct_response' => false, 'ability_after' => 3.44],
                    ['label' => 'SIMB01-06', 'is_correct_response' => true, 'ability_after' => 3.43],
                    ['label' => 'SIMC06-11', 'is_correct_response' => false, 'ability_after' => 3.43],
                    ['label' => 'SIMC05-06', 'is_correct_response' => true, 'ability_after' => 3.42],
                    ['label' => 'SIMC05-02', 'is_correct_response' => false, 'ability_after' => 3.43],
                    ['label' => 'SIMC08-04', 'is_correct_response' => false, 'ability_after' => 3.41],
                    ['label' => 'SIMC08-06', 'is_correct_response' => false, 'ability_after' => 3.41],
                    ['label' => 'SIMC08-14', 'is_correct_response' => false, 'ability_after' => 3.41],
                    ['label' => 'SIMC09-04', 'is_correct_response' => false, 'ability_after' => 3.4],
                    ['label' => 'SIMC09-08', 'is_correct_response' => false, 'ability_after' => 3.4],
                    ['label' => 'SIMC09-13', 'is_correct_response' => false, 'ability_after' => 3.4],
                    ['label' => 'SIMC09-02', 'is_correct_response' => false, 'ability_after' => 3.4],
                    ['label' => 'SIMC08-17', 'is_correct_response' => true, 'ability_after' => 3.4],
                    ['label' => 'SIMB02-06', 'is_correct_response' => false, 'ability_after' => 3.41],
                    ['label' => 'SIMC08-02', 'is_correct_response' => false, 'ability_after' => 3.4],
                    ['label' => 'SIMB03-15', 'is_correct_response' => true, 'ability_after' => 3.4],
                    ['label' => 'SIMB01-09', 'is_correct_response' => true, 'ability_after' => 3.4],
                    ['label' => 'SIMC06-01', 'is_correct_response' => true, 'ability_after' => 3.4],
                    ['label' => 'SIMC06-18', 'is_correct_response' => false, 'ability_after' => 3.41],
                    ['label' => 'SIMC09-09', 'is_correct_response' => false, 'ability_after' => 3.4],
                    ['label' => 'SIMC08-01', 'is_correct_response' => false, 'ability_after' => 3.4],
                    ['label' => 'SIMC06-12', 'is_correct_response' => false, 'ability_after' => 3.4],
                    ['label' => 'SIMC09-07', 'is_correct_response' => true, 'ability_after' => 3.4],
                    ['label' => 'SIMB01-07', 'is_correct_response' => true, 'ability_after' => 3.4],
                    ['label' => 'SIMB01-03', 'is_correct_response' => true, 'ability_after' => 3.4],
                    ['label' => 'SIMB01-00', 'is_correct_response' => true, 'ability_after' => 3.4],
                    ['label' => 'FINISH', 'is_correct_response' => null, 'ability_after' => 3.4],
                ],
                'initial_ability' => 0.02,
                'initial_se' => 2.97,
                'settings' => [
                    'maxquestions' => 250,
                    'pp_min_inc' => 0.1,
                    'standarderror_min' => 0.25,
                    'standarderror_max' => 0.5,
                    'minquestionspersubscale' => 3,
                    'fake_use_tr_factor' => false,
                ],
                'final_abilities' => [
                    'Simulation' => 3.4,
                    'SimB' => 3.2,
                    'SimB01' => 1.53,
                    'SimB02' => 3.06,
                    'SimB03' => 3.21,
                    'SimB04' => 3.4,
                    'SimC' => 3.45,
                    'SimC04' => 3.6,
                    'SimC05' => 3.02,
                    'SimC06' => 2.9,
                    'SimC07' => 3.79,
                    'SimC08' => 3.32,
                    'SimC09' => 3.33,
                    'SimC10' => 3.66,
                ],
            ],
            'Infer relevant scales P000407' => [
                'strategy' => LOCAL_CATQUIZ_STRATEGY_RELSUBS,
                'questions' => [
                    ['label' => 'SIMB01-18', 'is_correct_response' => false, 'ability_before' => 0.02, 'ability_after' => 0.02],
                    ['label' => 'SIMA06-15', 'is_correct_response' => false, 'ability_after' => -0.67],
                    ['label' => 'SIMA02-02', 'is_correct_response' => false, 'ability_after' => -1.3],
                    ['label' => 'SIMA02-19', 'is_correct_response' => false, 'ability_after' => -1.86],
                    ['label' => 'SIMA02-17', 'is_correct_response' => false, 'ability_after' => -2.33],
                    ['label' => 'SIMA06-02', 'is_correct_response' => false, 'ability_after' => -3.06],
                    ['label' => 'SIMB02-00', 'is_correct_response' => false, 'ability_after' => -3.06],
                    ['label' => 'SIMB01-17', 'is_correct_response' => false, 'ability_after' => -3.06],
                    ['label' => 'SIMA02-04', 'is_correct_response' => false, 'ability_after' => -3.06],
                    ['label' => 'SIMB01-12', 'is_correct_response' => false, 'ability_after' => -3.39],
                    ['label' => 'SIMA01-16', 'is_correct_response' => true, 'ability_after' => -3.39],
                    ['label' => 'SIMA01-19', 'is_correct_response' => false, 'ability_after' => -3.27],
                    ['label' => 'SIMA01-12', 'is_correct_response' => false, 'ability_after' => -3.39],
                    ['label' => 'SIMA01-13', 'is_correct_response' => true, 'ability_after' => -3.66],
                    ['label' => 'SIMA01-18', 'is_correct_response' => true, 'ability_after' => -3.58],
                    ['label' => 'SIMA01-14', 'is_correct_response' => false, 'ability_after' => -3.51],
                    ['label' => 'SIMA01-06', 'is_correct_response' => false, 'ability_after' => -3.63],
                    ['label' => 'SIMA01-10', 'is_correct_response' => true, 'ability_after' => -3.75],
                    ['label' => 'SIMA03-13', 'is_correct_response' => false, 'ability_after' => -3.72],
                    ['label' => 'SIMA03-04', 'is_correct_response' => true, 'ability_after' => -3.8],
                    ['label' => 'SIMA03-18', 'is_correct_response' => true, 'ability_after' => -3.77],
                    ['label' => 'SIMA03-03', 'is_correct_response' => false, 'ability_after' => -3.75],
                    ['label' => 'SIMA06-19', 'is_correct_response' => false, 'ability_after' => -3.78],
                    ['label' => 'FINISH', 'is_correct_response' => null, 'ability_after' => -3.79],
                ],
                'initial_ability' => 0.02,
                'initial_se' => 2.97,
                'settings' => [
                    'maxquestions' => 250,
                    'pp_min_inc' => 0.1,
                    'standarderror_min' => 0.25,
                    'standarderror_max' => 0.5,
                    'minquestionspersubscale' => 3,
                    'fake_use_tr_factor' => false,
                ],
                'final_abilities' => [
                    'Simulation' => -3.79,
                    'SimA' => -3.79,
                    'SimA01' => -3.72,
                    'SimA02' => -3.79,
                    'SimA03' => -3.89,
                    'SimA06' => -3.79,
                    'SimB' => -3.79,
                    'SimB01' => -3.79,
                    'SimB02' => -3.79,
                ],
            ],
            'Infer relevant scales P000184' => [
                'strategy' => LOCAL_CATQUIZ_STRATEGY_RELSUBS,
                'questions' => [
                    ['label' => 'SIMB01-18', 'is_correct_response' => false, 'ability_before' => 0.02, 'ability_after' => 0.02],
                    ['label' => 'SIMA06-15', 'is_correct_response' => false, 'ability_after' => -0.67],
                    ['label' => 'SIMA02-02', 'is_correct_response' => false, 'ability_after' => -1.3],
                    ['label' => 'SIMA02-19', 'is_correct_response' => false, 'ability_after' => -1.86],
                    ['label' => 'SIMA02-17', 'is_correct_response' => false, 'ability_after' => -2.33],
                    ['label' => 'SIMA06-02', 'is_correct_response' => false, 'ability_after' => -3.06],
                    ['label' => 'SIMB02-00', 'is_correct_response' => false, 'ability_after' => -3.06],
                    ['label' => 'SIMB01-17', 'is_correct_response' => false, 'ability_after' => -3.06],
                    ['label' => 'SIMA02-04', 'is_correct_response' => false, 'ability_after' => -3.06],
                    ['label' => 'SIMB01-12', 'is_correct_response' => false, 'ability_after' => -3.39],
                    ['label' => 'SIMA01-16', 'is_correct_response' => false, 'ability_after' => -3.39],
                    ['label' => 'SIMA01-00', 'is_correct_response' => false, 'ability_after' => -4.16],
                    ['label' => 'SIMA01-01', 'is_correct_response' => false, 'ability_after' => -4.84],
                    ['label' => 'SIMA01-09', 'is_correct_response' => true, 'ability_after' => -5.05],
                    ['label' => 'SIMA03-00', 'is_correct_response' => false, 'ability_after' => -4.79],
                    ['label' => 'SIMA03-08', 'is_correct_response' => false, 'ability_after' => -4.84],
                    ['label' => 'SIMA03-01', 'is_correct_response' => false, 'ability_after' => -4.87],
                    ['label' => 'SIMA03-05', 'is_correct_response' => true, 'ability_after' => -4.91],
                    ['label' => 'SIMA03-06', 'is_correct_response' => false, 'ability_after' => -4.82],
                    ['label' => 'SIMA01-10', 'is_correct_response' => false, 'ability_after' => -4.84],
                    ['label' => 'SIMA06-17', 'is_correct_response' => false, 'ability_after' => -4.85],
                    ['label' => 'SIMA03-14', 'is_correct_response' => false, 'ability_after' => -4.85],
                    ['label' => 'SIMA03-17', 'is_correct_response' => false, 'ability_after' => -4.87],
                    ['label' => 'SIMA01-04', 'is_correct_response' => false, 'ability_after' => -4.87],
                    ['label' => 'SIMA03-18', 'is_correct_response' => false, 'ability_after' => -4.89],
                    ['label' => 'SIMA03-09', 'is_correct_response' => false, 'ability_after' => -4.9],
                    ['label' => 'SIMA01-15', 'is_correct_response' => false, 'ability_after' => -4.92],
                    ['label' => 'SIMA03-02', 'is_correct_response' => true, 'ability_after' => -4.93],
                    ['label' => 'SIMA01-06', 'is_correct_response' => false, 'ability_after' => -4.89],
                    ['label' => 'SIMA01-13', 'is_correct_response' => false, 'ability_after' => -4.9],
                    ['label' => 'SIMA01-02', 'is_correct_response' => false, 'ability_after' => -4.9],
                    ['label' => 'FINISH', 'is_correct_response' => null, 'ability_after' => -4.92],
                ],
                'initial_ability' => 0.02,
                'initial_se' => 2.97,
                'settings' => [
                    'maxquestions' => 250,
                    'pp_min_inc' => 0.1,
                    'standarderror_min' => 0.25,
                    'standarderror_max' => 0.5,
                    'minquestionspersubscale' => 3,
                    'fake_use_tr_factor' => false,
                ],
                'final_abilities' => [
                    'Simulation' => -4.92,
                    'SimA' => -4.92,
                    'SimA01' => -4.89,
                    'SimA02' => -4.92,
                    'SimA03' => -4.95,
                    'SimA06' => -4.92,
                    'SimB' => -4.92,
                    'SimB01' => -4.92,
                    'SimB02' => -4.92,
                ],
            ],
            'Infer all scales P000001' => [
                'strategy' => LOCAL_CATQUIZ_STRATEGY_ALLSUBS,
                'questions' => [
                    ['label' => 'SIMB01-18', 'is_correct_response' => true, 'ability_before' => 0.02, 'ability_after' => 0.02],
                    ['label' => 'SIMB03-10', 'is_correct_response' => true, 'ability_after' => 0.76],
                    ['label' => 'SIMB03-06', 'is_correct_response' => true, 'ability_after' => 1.32],
                    ['label' => 'SIMB02-12', 'is_correct_response' => true, 'ability_after' => 1.9],
                    ['label' => 'SIMB02-07', 'is_correct_response' => true, 'ability_after' => 2.6],
                    ['label' => 'SIMB01-04', 'is_correct_response' => true, 'ability_after' => 3.18],
                    ['label' => 'SIMB01-01', 'is_correct_response' => true, 'ability_after' => 3.18],
                    ['label' => 'SIMA04-17', 'is_correct_response' => true, 'ability_after' => 3.2],
                    ['label' => 'SIMA02-13', 'is_correct_response' => true, 'ability_after' => 3.2],
                    ['label' => 'SIMC02-18', 'is_correct_response' => true, 'ability_after' => 3.2],
                    ['label' => 'SIMC03-15', 'is_correct_response' => true, 'ability_after' => 3.2],
                    ['label' => 'SIMC03-13', 'is_correct_response' => true, 'ability_after' => 3.2],
                    ['label' => 'SIMC02-03', 'is_correct_response' => true, 'ability_after' => 3.2],
                    ['label' => 'SIMC01-01', 'is_correct_response' => true, 'ability_after' => 3.2],
                    ['label' => 'SIMC01-08', 'is_correct_response' => true, 'ability_after' => 3.2],
                    ['label' => 'SIMB03-05', 'is_correct_response' => true, 'ability_after' => 3.2],
                    ['label' => 'SIMA04-08', 'is_correct_response' => true, 'ability_after' => 3.21],
                    ['label' => 'SIMC03-12', 'is_correct_response' => true, 'ability_after' => 3.21],
                    ['label' => 'SIMA07-00', 'is_correct_response' => true, 'ability_after' => 3.21],
                    ['label' => 'SIMA07-12', 'is_correct_response' => true, 'ability_after' => 3.21],
                    ['label' => 'SIMC01-14', 'is_correct_response' => true, 'ability_after' => 3.21],
                    ['label' => 'SIMC02-15', 'is_correct_response' => true, 'ability_after' => 3.21],
                    ['label' => 'SIMA04-11', 'is_correct_response' => true, 'ability_after' => 3.22],
                    ['label' => 'SIMA06-15', 'is_correct_response' => true, 'ability_after' => 3.22],
                    ['label' => 'SIMA06-01', 'is_correct_response' => true, 'ability_after' => 3.22],
                    ['label' => 'SIMB04-08', 'is_correct_response' => true, 'ability_after' => 3.23],
                    ['label' => 'SIMB04-10', 'is_correct_response' => true, 'ability_after' => 3.86],
                    ['label' => 'SIMB04-14', 'is_correct_response' => true, 'ability_after' => 4.19],
                    ['label' => 'SIMB02-09', 'is_correct_response' => true, 'ability_after' => 4.44],
                    ['label' => 'SIMA07-01', 'is_correct_response' => true, 'ability_after' => 4.44],
                    ['label' => 'SIMA02-03', 'is_correct_response' => true, 'ability_after' => 4.44],
                    ['label' => 'SIMA06-11', 'is_correct_response' => true, 'ability_after' => 4.44],
                    ['label' => 'SIMA05-17', 'is_correct_response' => true, 'ability_after' => 4.44],
                    ['label' => 'SIMC07-16', 'is_correct_response' => true, 'ability_after' => 4.44],
                    ['label' => 'SIMC07-18', 'is_correct_response' => true, 'ability_after' => 4.6],
                    ['label' => 'SIMC07-08', 'is_correct_response' => true, 'ability_after' => 4.61],
                    ['label' => 'SIMC07-09', 'is_correct_response' => true, 'ability_after' => 4.66],
                    ['label' => 'SIMC07-04', 'is_correct_response' => true, 'ability_after' => 4.77],
                    ['label' => 'SIMA05-19', 'is_correct_response' => true, 'ability_after' => 4.93],
                    ['label' => 'SIMC04-11', 'is_correct_response' => false, 'ability_after' => 4.93],
                    ['label' => 'SIMC04-00', 'is_correct_response' => false, 'ability_after' => 4.94],
                    ['label' => 'SIMC04-17', 'is_correct_response' => true, 'ability_after' => 4.36],
                    ['label' => 'SIMC04-05', 'is_correct_response' => true, 'ability_after' => 4.37],
                    ['label' => 'SIMC04-16', 'is_correct_response' => true, 'ability_after' => 4.39],
                    ['label' => 'SIMC04-15', 'is_correct_response' => false, 'ability_after' => 4.42],
                    ['label' => 'SIMC04-04', 'is_correct_response' => true, 'ability_after' => 4.35],
                    ['label' => 'SIMC04-14', 'is_correct_response' => true, 'ability_after' => 4.39],
                    ['label' => 'SIMA01-03', 'is_correct_response' => true, 'ability_after' => 4.42],
                    ['label' => 'SIMC10-18', 'is_correct_response' => true, 'ability_after' => 4.42],
                    ['label' => 'SIMC10-08', 'is_correct_response' => true, 'ability_after' => 4.43],
                    ['label' => 'SIMC10-06', 'is_correct_response' => true, 'ability_after' => 4.49],
                    ['label' => 'SIMC10-15', 'is_correct_response' => true, 'ability_after' => 4.51],
                    ['label' => 'SIMC05-09', 'is_correct_response' => false, 'ability_after' => 4.52],
                    ['label' => 'SIMC05-03', 'is_correct_response' => true, 'ability_after' => 4.5],
                    ['label' => 'SIMC05-14', 'is_correct_response' => true, 'ability_after' => 4.51],
                    ['label' => 'SIMA03-11', 'is_correct_response' => true, 'ability_after' => 4.53],
                    ['label' => 'SIMC06-06', 'is_correct_response' => true, 'ability_after' => 4.53],
                    ['label' => 'SIMC06-04', 'is_correct_response' => true, 'ability_after' => 4.54],
                    ['label' => 'SIMC06-10', 'is_correct_response' => true, 'ability_after' => 4.56],
                    ['label' => 'SIMC06-07', 'is_correct_response' => true, 'ability_after' => 4.58],
                    ['label' => 'SIMC06-03', 'is_correct_response' => true, 'ability_after' => 4.6],
                    ['label' => 'SIMC07-12', 'is_correct_response' => true, 'ability_after' => 4.62],
                    ['label' => 'SIMC04-18', 'is_correct_response' => false, 'ability_after' => 4.64],
                    ['label' => 'SIMC04-02', 'is_correct_response' => true, 'ability_after' => 4.58],
                    ['label' => 'SIMC09-17', 'is_correct_response' => false, 'ability_after' => 4.6],
                    ['label' => 'SIMC09-16', 'is_correct_response' => true, 'ability_after' => 4.59],
                    ['label' => 'SIMC09-00', 'is_correct_response' => true, 'ability_after' => 4.66],
                    ['label' => 'SIMC09-06', 'is_correct_response' => false, 'ability_after' => 4.71],
                    ['label' => 'SIMC09-01', 'is_correct_response' => false, 'ability_after' => 4.66],
                    ['label' => 'SIMC09-10', 'is_correct_response' => true, 'ability_after' => 4.61],
                    ['label' => 'SIMC10-07', 'is_correct_response' => true, 'ability_after' => 4.64],
                    ['label' => 'SIMC08-08', 'is_correct_response' => true, 'ability_after' => 4.64],
                    ['label' => 'SIMC08-12', 'is_correct_response' => false, 'ability_after' => 4.65],
                    ['label' => 'SIMC08-03', 'is_correct_response' => true, 'ability_after' => 4.62],
                    ['label' => 'SIMC08-11', 'is_correct_response' => false, 'ability_after' => 4.64],
                    ['label' => 'SIMA01-15', 'is_correct_response' => true, 'ability_after' => 4.62],
                    ['label' => 'SIMA03-12', 'is_correct_response' => false, 'ability_after' => 4.62],
                    ['label' => 'SIMA03-16', 'is_correct_response' => true, 'ability_after' => 4.61],
                    ['label' => 'SIMA05-01', 'is_correct_response' => true, 'ability_after' => 4.61],
                    ['label' => 'SIMA02-01', 'is_correct_response' => true, 'ability_after' => 4.61],
                    ['label' => 'SIMA01-04', 'is_correct_response' => true, 'ability_after' => 4.61],
                    ['label' => 'SIMC07-14', 'is_correct_response' => true, 'ability_after' => 4.61],
                    ['label' => 'SIMC06-17', 'is_correct_response' => true, 'ability_after' => 4.62],
                    ['label' => 'SIMC10-05', 'is_correct_response' => true, 'ability_after' => 4.62],
                    ['label' => 'SIMC07-19', 'is_correct_response' => true, 'ability_after' => 4.62],
                    ['label' => 'SIMC06-02', 'is_correct_response' => true, 'ability_after' => 4.63],
                    ['label' => 'SIMC10-16', 'is_correct_response' => true, 'ability_after' => 4.63],
                    ['label' => 'SIMC10-04', 'is_correct_response' => true, 'ability_after' => 4.63],
                    ['label' => 'SIMC06-00', 'is_correct_response' => true, 'ability_after' => 4.63],
                    ['label' => 'SIMC10-13', 'is_correct_response' => true, 'ability_after' => 4.63],
                    ['label' => 'SIMC06-15', 'is_correct_response' => true, 'ability_after' => 4.64],
                    ['label' => 'SIMC10-17', 'is_correct_response' => true, 'ability_after' => 4.64],
                    ['label' => 'SIMC06-05', 'is_correct_response' => false, 'ability_after' => 4.64],
                    ['label' => 'FINISH', 'is_correct_response' => null, 'ability_after' => 4.63],
                ],
                'initial_ability' => 0.02,
                'initial_se' => 2.97,
                'settings' => [
                    'maxquestions' => 250,
                    'pp_min_inc' => 0.1,
                    'standarderror_min' => 0.25,
                    'standarderror_max' => 0.5,
                    'minquestionspersubscale' => 3,
                    'maxquestionspersubscale' => 10,
                    'fake_use_tr_factor' => false,
                ],
                'final_abilities' => [
                    'Simulation' => 4.63,
                    'SimA' => 2.16,
                    'SimA01' => 4.63,
                    'SimA02' => 4.63,
                    'SimA03' => -2.24,
                    'SimA05' => 4.63,
                    'SimA06' => 4.63,
                    'SimA07' => 4.63,
                    'SimB' => 4.64,
                    'SimB01' => 4.63,
                    'SimB02' => 4.63,
                    'SimB03' => 4.63,
                    'SimB04' => 4.64,
                    'SimC' => 4.63,
                    'SimC01' => 4.63,
                    'SimC02' => 4.63,
                    'SimC03' => 4.63,
                    'SimC04' => 4.24,
                    'SimC05' => 4.62,
                    'SimC06' => 4.98,
                    'SimC07' => 4.65,
                    'SimC08' => 4.54,
                    'SimC09' => 4.68,
                    'SimC10' => 4.66,
                ],
            ],
            'Infer all scales P000642' => [
                'strategy' => LOCAL_CATQUIZ_STRATEGY_ALLSUBS,
                'questions' => [
                    ['label' => 'SIMB01-18', 'is_correct_response' => true, 'ability_before' => 0.02, 'ability_after' => 0.02],
                    ['label' => 'SIMB03-10', 'is_correct_response' => true, 'ability_after' => 0.76],
                    ['label' => 'SIMB03-06', 'is_correct_response' => true, 'ability_after' => 1.32],
                    ['label' => 'SIMB02-12', 'is_correct_response' => true, 'ability_after' => 1.9],
                    ['label' => 'SIMB02-07', 'is_correct_response' => true, 'ability_after' => 2.6],
                    ['label' => 'SIMB01-04', 'is_correct_response' => true, 'ability_after' => 3.18],
                    ['label' => 'SIMB01-01', 'is_correct_response' => false, 'ability_after' => 3.18],
                    ['label' => 'SIMB04-03', 'is_correct_response' => true, 'ability_after' => 2.55],
                    ['label' => 'SIMB04-06', 'is_correct_response' => true, 'ability_after' => 2.91],
                    ['label' => 'SIMB04-08', 'is_correct_response' => true, 'ability_after' => 3.15],
                    ['label' => 'SIMB04-10', 'is_correct_response' => false, 'ability_after' => 3.46],
                    ['label' => 'SIMB01-11', 'is_correct_response' => true, 'ability_after' => 3.37],
                    ['label' => 'SIMA04-17', 'is_correct_response' => true, 'ability_after' => 3.37],
                    ['label' => 'SIMC02-18', 'is_correct_response' => true, 'ability_after' => 3.37],
                    ['label' => 'SIMC10-00', 'is_correct_response' => true, 'ability_after' => 3.37],
                    ['label' => 'SIMC10-12', 'is_correct_response' => true, 'ability_after' => 3.49],
                    ['label' => 'SIMC10-15', 'is_correct_response' => false, 'ability_after' => 3.61],
                    ['label' => 'SIMC10-16', 'is_correct_response' => false, 'ability_after' => 3.56],
                    ['label' => 'SIMC06-13', 'is_correct_response' => false, 'ability_after' => 3.52],
                    ['label' => 'SIMC06-14', 'is_correct_response' => false, 'ability_after' => 3.5],
                    ['label' => 'SIMC10-01', 'is_correct_response' => true, 'ability_after' => 3.47],
                    ['label' => 'SIMC06-09', 'is_correct_response' => false, 'ability_after' => 3.51],
                    ['label' => 'SIMC01-01', 'is_correct_response' => true, 'ability_after' => 3.49],
                    ['label' => 'SIMB04-14', 'is_correct_response' => false, 'ability_after' => 3.49],
                    ['label' => 'SIMB04-09', 'is_correct_response' => false, 'ability_after' => 3.48],
                    ['label' => 'SIMB04-05', 'is_correct_response' => true, 'ability_after' => 3.43],
                    ['label' => 'SIMB01-02', 'is_correct_response' => false, 'ability_after' => 3.44],
                    ['label' => 'SIMA07-00', 'is_correct_response' => true, 'ability_after' => 3.41],
                    ['label' => 'SIMA02-03', 'is_correct_response' => true, 'ability_after' => 3.41],
                    ['label' => 'SIMC03-17', 'is_correct_response' => true, 'ability_after' => 3.41],
                    ['label' => 'SIMC06-11', 'is_correct_response' => false, 'ability_after' => 3.41],
                    ['label' => 'SIMB01-06', 'is_correct_response' => true, 'ability_after' => 3.4],
                    ['label' => 'SIMA06-15', 'is_correct_response' => true, 'ability_after' => 3.4],
                    ['label' => 'SIMB02-06', 'is_correct_response' => false, 'ability_after' => 3.4],
                    ['label' => 'SIMB03-15', 'is_correct_response' => true, 'ability_after' => 3.39],
                    ['label' => 'SIMA05-17', 'is_correct_response' => true, 'ability_after' => 3.4],
                    ['label' => 'SIMC07-16', 'is_correct_response' => false, 'ability_after' => 3.4],
                    ['label' => 'SIMC07-15', 'is_correct_response' => true, 'ability_after' => 3.39],
                    ['label' => 'SIMC07-09', 'is_correct_response' => false, 'ability_after' => 3.45],
                    ['label' => 'SIMC07-08', 'is_correct_response' => true, 'ability_after' => 3.44],
                    ['label' => 'SIMC07-06', 'is_correct_response' => false, 'ability_after' => 3.5],
                    ['label' => 'SIMC07-11', 'is_correct_response' => true, 'ability_after' => 3.49],
                    ['label' => 'SIMC06-01', 'is_correct_response' => true, 'ability_after' => 3.5],
                    ['label' => 'SIMC06-18', 'is_correct_response' => false, 'ability_after' => 3.51],
                    ['label' => 'SIMC04-11', 'is_correct_response' => true, 'ability_after' => 3.5],
                    ['label' => 'SIMC04-17', 'is_correct_response' => false, 'ability_after' => 3.5],
                    ['label' => 'SIMC04-01', 'is_correct_response' => true, 'ability_after' => 3.49],
                    ['label' => 'SIMC04-05', 'is_correct_response' => false, 'ability_after' => 3.51],
                    ['label' => 'SIMC04-06', 'is_correct_response' => true, 'ability_after' => 3.5],
                    ['label' => 'SIMC04-16', 'is_correct_response' => true, 'ability_after' => 3.5],
                    ['label' => 'SIMC04-04', 'is_correct_response' => false, 'ability_after' => 3.52],
                    ['label' => 'SIMA01-03', 'is_correct_response' => true, 'ability_after' => 3.52],
                    ['label' => 'SIMC05-09', 'is_correct_response' => false, 'ability_after' => 3.52],
                    ['label' => 'SIMC05-17', 'is_correct_response' => false, 'ability_after' => 3.52],
                    ['label' => 'SIMC05-11', 'is_correct_response' => false, 'ability_after' => 3.49],
                    ['label' => 'SIMC05-07', 'is_correct_response' => false, 'ability_after' => 3.47],
                    ['label' => 'SIMC03-19', 'is_correct_response' => true, 'ability_after' => 3.43],
                    ['label' => 'SIMA04-07', 'is_correct_response' => true, 'ability_after' => 3.43],
                    ['label' => 'SIMA07-13', 'is_correct_response' => false, 'ability_after' => 3.43],
                    ['label' => 'SIMA07-12', 'is_correct_response' => true, 'ability_after' => 3.43],
                    ['label' => 'SIMA02-13', 'is_correct_response' => true, 'ability_after' => 3.43],
                    ['label' => 'SIMA04-11', 'is_correct_response' => true, 'ability_after' => 3.43],
                    ['label' => 'SIMA06-01', 'is_correct_response' => true, 'ability_after' => 3.43],
                    ['label' => 'SIMA03-11', 'is_correct_response' => true, 'ability_after' => 3.43],
                    ['label' => 'SIMB01-09', 'is_correct_response' => true, 'ability_after' => 3.43],
                    ['label' => 'SIMC02-08', 'is_correct_response' => true, 'ability_after' => 3.43],
                    ['label' => 'SIMC01-06', 'is_correct_response' => true, 'ability_after' => 3.43],
                    ['label' => 'SIMC09-17', 'is_correct_response' => false, 'ability_after' => 3.43],
                    ['label' => 'SIMC09-04', 'is_correct_response' => false, 'ability_after' => 3.43],
                    ['label' => 'SIMC09-08', 'is_correct_response' => false, 'ability_after' => 3.42],
                    ['label' => 'SIMC09-13', 'is_correct_response' => false, 'ability_after' => 3.42],
                    ['label' => 'SIMC09-02', 'is_correct_response' => false, 'ability_after' => 3.42],
                    ['label' => 'SIMC03-04', 'is_correct_response' => true, 'ability_after' => 3.42],
                    ['label' => 'SIMA05-01', 'is_correct_response' => false, 'ability_after' => 3.42],
                    ['label' => 'SIMA05-12', 'is_correct_response' => true, 'ability_after' => 3.42],
                    ['label' => 'SIMA02-01', 'is_correct_response' => true, 'ability_after' => 3.42],
                    ['label' => 'SIMA06-19', 'is_correct_response' => true, 'ability_after' => 3.42],
                    ['label' => 'SIMA03-12', 'is_correct_response' => true, 'ability_after' => 3.42],
                    ['label' => 'SIMA01-15', 'is_correct_response' => true, 'ability_after' => 3.42],
                    ['label' => 'SIMC02-06', 'is_correct_response' => true, 'ability_after' => 3.42],
                    ['label' => 'SIMC01-17', 'is_correct_response' => true, 'ability_after' => 3.42],
                    ['label' => 'SIMC08-08', 'is_correct_response' => false, 'ability_after' => 3.42],
                    ['label' => 'SIMC08-04', 'is_correct_response' => false, 'ability_after' => 3.42],
                    ['label' => 'SIMC08-06', 'is_correct_response' => false, 'ability_after' => 3.41],
                    ['label' => 'SIMC08-14', 'is_correct_response' => false, 'ability_after' => 3.41],
                    ['label' => 'SIMC08-17', 'is_correct_response' => true, 'ability_after' => 3.41],
                    ['label' => 'SIMC06-12', 'is_correct_response' => false, 'ability_after' => 3.42],
                    ['label' => 'SIMA03-10', 'is_correct_response' => true, 'ability_after' => 3.41],
                    ['label' => 'SIMA01-04', 'is_correct_response' => true, 'ability_after' => 3.41],
                    ['label' => 'SIMB01-07', 'is_correct_response' => true, 'ability_after' => 3.41],
                    ['label' => 'SIMB01-03', 'is_correct_response' => true, 'ability_after' => 3.41],
                    ['label' => 'SIMB01-00', 'is_correct_response' => true, 'ability_after' => 3.41],
                    ['label' => 'FINISH', 'is_correct_response' => null, 'ability_after' => 3.41],
                ],
                'initial_ability' => 0.02,
                'initial_se' => 2.97,
                'settings' => [
                    'maxquestions' => 250,
                    'pp_min_inc' => 0.1,
                    'standarderror_min' => 0.25,
                    'standarderror_max' => 0.5,
                    'minquestionspersubscale' => 3,
                    'fake_use_tr_factor' => false,
                ],
                'final_abilities' => [
                    'Simulation' => 3.41,
                    'SimA' => 1.8,
                    'SimA01' => 3.41,
                    'SimA02' => 3.41,
                    'SimA03' => 3.41,
                    'SimA04' => 3.41,
                    'SimA05' => -0.68,
                    'SimA06' => 3.41,
                    'SimA07' => 0.64,
                    'SimB' => 3.2,
                    'SimB01' => 1.53,
                    'SimB02' => 3.06,
                    'SimB03' => 3.21,
                    'SimB04' => 3.4,
                    'SimC' => 3.47,
                    'SimC01' => 3.41,
                    'SimC02' => 3.41,
                    'SimC03' => 3.41,
                    'SimC04' => 3.62,
                    'SimC05' => 3.35,
                    'SimC06' => 2.9,
                    'SimC07' => 3.79,
                    'SimC08' => 3.33,
                    'SimC09' => 3.4,
                    'SimC10' => 3.66,
                ],
            ],
            'Infer all scales P000407' => [
                'strategy' => LOCAL_CATQUIZ_STRATEGY_ALLSUBS,
                'questions' => [
                    ['label' => 'SIMB01-18', 'is_correct_response' => false, 'ability_before' => 0.02, 'ability_after' => 0.02],
                    ['label' => 'SIMA06-15', 'is_correct_response' => false, 'ability_after' => -0.67],
                    ['label' => 'SIMA02-02', 'is_correct_response' => false, 'ability_after' => -1.3],
                    ['label' => 'SIMA02-19', 'is_correct_response' => false, 'ability_after' => -1.86],
                    ['label' => 'SIMA02-17', 'is_correct_response' => false, 'ability_after' => -2.33],
                    ['label' => 'SIMA06-02', 'is_correct_response' => false, 'ability_after' => -3.06],
                    ['label' => 'SIMB02-00', 'is_correct_response' => false, 'ability_after' => -3.06],
                    ['label' => 'SIMB01-17', 'is_correct_response' => false, 'ability_after' => -3.06],
                    ['label' => 'SIMA02-04', 'is_correct_response' => false, 'ability_after' => -3.06],
                    ['label' => 'SIMB01-12', 'is_correct_response' => false, 'ability_after' => -3.39],
                    ['label' => 'SIMA04-17', 'is_correct_response' => false, 'ability_after' => -3.39],
                    ['label' => 'SIMA04-12', 'is_correct_response' => false, 'ability_after' => -3.39],
                    ['label' => 'SIMA04-10', 'is_correct_response' => false, 'ability_after' => -3.39],
                    ['label' => 'SIMC02-18', 'is_correct_response' => false, 'ability_after' => -3.39],
                    ['label' => 'SIMC01-05', 'is_correct_response' => false, 'ability_after' => -3.39],
                    ['label' => 'SIMC02-16', 'is_correct_response' => false, 'ability_after' => -3.39],
                    ['label' => 'SIMC01-19', 'is_correct_response' => false, 'ability_after' => -3.39],
                    ['label' => 'SIMB02-02', 'is_correct_response' => false, 'ability_after' => -3.39],
                    ['label' => 'SIMA07-00', 'is_correct_response' => false, 'ability_after' => -3.39],
                    ['label' => 'SIMA07-10', 'is_correct_response' => false, 'ability_after' => -3.39],
                    ['label' => 'SIMA06-12', 'is_correct_response' => false, 'ability_after' => -3.39],
                    ['label' => 'SIMC03-17', 'is_correct_response' => false, 'ability_after' => -3.41],
                    ['label' => 'SIMC03-06', 'is_correct_response' => false, 'ability_after' => -3.41],
                    ['label' => 'SIMC03-08', 'is_correct_response' => false, 'ability_after' => -3.41],
                    ['label' => 'SIMC01-16', 'is_correct_response' => false, 'ability_after' => -3.43],
                    ['label' => 'SIMC02-07', 'is_correct_response' => false, 'ability_after' => -3.44],
                    ['label' => 'SIMA07-06', 'is_correct_response' => false, 'ability_after' => -3.47],
                    ['label' => 'SIMB03-02', 'is_correct_response' => false, 'ability_after' => -3.47],
                    ['label' => 'SIMB02-10', 'is_correct_response' => false, 'ability_after' => -3.47],
                    ['label' => 'SIMB03-17', 'is_correct_response' => false, 'ability_after' => -3.48],
                    ['label' => 'SIMA05-17', 'is_correct_response' => false, 'ability_after' => -3.48],
                    ['label' => 'SIMA05-07', 'is_correct_response' => false, 'ability_after' => -3.62],
                    ['label' => 'SIMA05-00', 'is_correct_response' => false, 'ability_after' => -3.71],
                    ['label' => 'SIMB03-13', 'is_correct_response' => false, 'ability_after' => -3.81],
                    ['label' => 'SIMB04-18', 'is_correct_response' => false, 'ability_after' => -3.82],
                    ['label' => 'SIMC07-16', 'is_correct_response' => false, 'ability_after' => -3.82],
                    ['label' => 'SIMC04-11', 'is_correct_response' => false, 'ability_after' => -3.83],
                    ['label' => 'SIMA01-03', 'is_correct_response' => true, 'ability_after' => -3.83],
                    ['label' => 'SIMA01-16', 'is_correct_response' => true, 'ability_after' => -3.86],
                    ['label' => 'SIMA01-19', 'is_correct_response' => false, 'ability_after' => -3.41],
                    ['label' => 'SIMA01-12', 'is_correct_response' => false, 'ability_after' => -3.48],
                    ['label' => 'SIMA01-13', 'is_correct_response' => true, 'ability_after' => -3.67],
                    ['label' => 'SIMA01-14', 'is_correct_response' => false, 'ability_after' => -3.6],
                    ['label' => 'SIMA01-18', 'is_correct_response' => true, 'ability_after' => -3.71],
                    ['label' => 'SIMC10-18', 'is_correct_response' => false, 'ability_after' => -3.64],
                    ['label' => 'SIMC05-09', 'is_correct_response' => false, 'ability_after' => -3.64],
                    ['label' => 'SIMA03-11', 'is_correct_response' => true, 'ability_after' => -3.64],
                    ['label' => 'SIMA03-13', 'is_correct_response' => false, 'ability_after' => -3.63],
                    ['label' => 'SIMA03-00', 'is_correct_response' => true, 'ability_after' => -3.74],
                    ['label' => 'SIMA03-04', 'is_correct_response' => true, 'ability_after' => -3.73],
                    ['label' => 'SIMA03-03', 'is_correct_response' => false, 'ability_after' => -3.71],
                    ['label' => 'SIMA03-16', 'is_correct_response' => true, 'ability_after' => -3.75],
                    ['label' => 'SIMA03-19', 'is_correct_response' => false, 'ability_after' => -3.71],
                    ['label' => 'SIMA03-18', 'is_correct_response' => true, 'ability_after' => -3.72],
                    ['label' => 'SIMC06-06', 'is_correct_response' => false, 'ability_after' => -3.7],
                    ['label' => 'SIMC09-17', 'is_correct_response' => false, 'ability_after' => -3.7],
                    ['label' => 'SIMC04-10', 'is_correct_response' => false, 'ability_after' => -3.7],
                    ['label' => 'SIMC10-02', 'is_correct_response' => false, 'ability_after' => -3.71],
                    ['label' => 'SIMC05-15', 'is_correct_response' => false, 'ability_after' => -3.71],
                    ['label' => 'SIMB04-07', 'is_correct_response' => false, 'ability_after' => -3.71],
                    ['label' => 'SIMC08-08', 'is_correct_response' => false, 'ability_after' => -3.71],
                    ['label' => 'SIMC07-19', 'is_correct_response' => false, 'ability_after' => -3.71],
                    ['label' => 'SIMB04-12', 'is_correct_response' => false, 'ability_after' => -3.71],
                    ['label' => 'SIMC10-14', 'is_correct_response' => false, 'ability_after' => -3.71],
                    ['label' => 'SIMC05-19', 'is_correct_response' => false, 'ability_after' => -3.71],
                    ['label' => 'SIMC04-12', 'is_correct_response' => false, 'ability_after' => -3.71],
                    ['label' => 'SIMC06-01', 'is_correct_response' => false, 'ability_after' => -3.71],
                    ['label' => 'SIMC09-07', 'is_correct_response' => false, 'ability_after' => -3.71],
                    ['label' => 'SIMC06-05', 'is_correct_response' => false, 'ability_after' => -3.71],
                    ['label' => 'SIMC07-14', 'is_correct_response' => false, 'ability_after' => -3.71],
                    ['label' => 'SIMC08-19', 'is_correct_response' => false, 'ability_after' => -3.71],
                    ['label' => 'SIMC08-17', 'is_correct_response' => false, 'ability_after' => -3.71],
                    ['label' => 'SIMC09-02', 'is_correct_response' => false, 'ability_after' => -3.71],
                    ['label' => 'FINISH', 'is_correct_response' => null, 'ability_after' => -3.71],
                ],
                'initial_ability' => 0.02,
                'initial_se' => 2.97,
                'settings' => [
                    'maxquestions' => 250,
                    'pp_min_inc' => 0.1,
                    'standarderror_min' => 0.25,
                    'standarderror_max' => 0.5,
                    'minquestionspersubscale' => 3,
                    'fake_use_tr_factor' => false,
                ],
                'final_abilities' => [
                    'Simulation' => -3.71,
                    'SimA' => -3.7,
                    'SimA01' => -3.6,
                    'SimA02' => -3.71,
                    'SimA03' => -3.79,
                    'SimA04' => -3.71,
                    'SimA05' => -3.72,
                    'SimA06' => -3.71,
                    'SimA07' => -3.71,
                    'SimB' => -3.71,
                    'SimB01' => -3.71,
                    'SimB02' => -3.71,
                    'SimB03' => -3.71,
                    'SimB04' => -3.71,
                    'SimC' => -3.72,
                    'SimC01' => -3.71,
                    'SimC02' => -3.71,
                    'SimC03' => -3.71,
                    'SimC04' => -3.71,
                    'SimC05' => -3.71,
                    'SimC06' => -3.71,
                    'SimC07' => -3.71,
                    'SimC08' => -3.71,
                    'SimC09' => -3.71,
                    'SimC10' => -3.71,
                ],
            ],
            'Infer all scales P000184' => [
                'strategy' => LOCAL_CATQUIZ_STRATEGY_ALLSUBS,
                'questions' => [
                    ['label' => 'SIMB01-18', 'is_correct_response' => false, 'ability_before' => 0.02, 'ability_after' => 0.02],
                    ['label' => 'SIMA06-15', 'is_correct_response' => false, 'ability_after' => -0.67],
                    ['label' => 'SIMA02-02', 'is_correct_response' => false, 'ability_after' => -1.3],
                    ['label' => 'SIMA02-19', 'is_correct_response' => false, 'ability_after' => -1.86],
                    ['label' => 'SIMA02-17', 'is_correct_response' => false, 'ability_after' => -2.33],
                    ['label' => 'SIMA06-02', 'is_correct_response' => false, 'ability_after' => -3.06],
                    ['label' => 'SIMB02-00', 'is_correct_response' => false, 'ability_after' => -3.06],
                    ['label' => 'SIMB01-17', 'is_correct_response' => false, 'ability_after' => -3.06],
                    ['label' => 'SIMA02-04', 'is_correct_response' => false, 'ability_after' => -3.06],
                    ['label' => 'SIMB01-12', 'is_correct_response' => false, 'ability_after' => -3.39],
                    ['label' => 'SIMA04-17', 'is_correct_response' => false, 'ability_after' => -3.39],
                    ['label' => 'SIMA04-12', 'is_correct_response' => false, 'ability_after' => -3.39],
                    ['label' => 'SIMA04-10', 'is_correct_response' => false, 'ability_after' => -3.39],
                    ['label' => 'SIMC02-18', 'is_correct_response' => false, 'ability_after' => -3.39],
                    ['label' => 'SIMC01-05', 'is_correct_response' => false, 'ability_after' => -3.39],
                    ['label' => 'SIMC02-16', 'is_correct_response' => false, 'ability_after' => -3.39],
                    ['label' => 'SIMC01-19', 'is_correct_response' => false, 'ability_after' => -3.39],
                    ['label' => 'SIMB02-02', 'is_correct_response' => false, 'ability_after' => -3.39],
                    ['label' => 'SIMA07-00', 'is_correct_response' => false, 'ability_after' => -3.39],
                    ['label' => 'SIMA07-10', 'is_correct_response' => false, 'ability_after' => -3.39],
                    ['label' => 'SIMA06-12', 'is_correct_response' => false, 'ability_after' => -3.39],
                    ['label' => 'SIMC03-17', 'is_correct_response' => false, 'ability_after' => -3.41],
                    ['label' => 'SIMC03-06', 'is_correct_response' => false, 'ability_after' => -3.41],
                    ['label' => 'SIMC03-08', 'is_correct_response' => false, 'ability_after' => -3.41],
                    ['label' => 'SIMC01-16', 'is_correct_response' => false, 'ability_after' => -3.43],
                    ['label' => 'SIMC02-07', 'is_correct_response' => false, 'ability_after' => -3.44],
                    ['label' => 'SIMA07-06', 'is_correct_response' => false, 'ability_after' => -3.47],
                    ['label' => 'SIMB03-02', 'is_correct_response' => false, 'ability_after' => -3.47],
                    ['label' => 'SIMB02-10', 'is_correct_response' => false, 'ability_after' => -3.47],
                    ['label' => 'SIMB03-17', 'is_correct_response' => false, 'ability_after' => -3.48],
                    ['label' => 'SIMA05-17', 'is_correct_response' => false, 'ability_after' => -3.48],
                    ['label' => 'SIMA05-07', 'is_correct_response' => false, 'ability_after' => -3.62],
                    ['label' => 'SIMA05-00', 'is_correct_response' => false, 'ability_after' => -3.71],
                    ['label' => 'SIMB03-13', 'is_correct_response' => false, 'ability_after' => -3.81],
                    ['label' => 'SIMB04-18', 'is_correct_response' => false, 'ability_after' => -3.82],
                    ['label' => 'SIMC07-16', 'is_correct_response' => false, 'ability_after' => -3.82],
                    ['label' => 'SIMC04-11', 'is_correct_response' => false, 'ability_after' => -3.83],
                    ['label' => 'SIMA01-03', 'is_correct_response' => false, 'ability_after' => -3.83],
                    ['label' => 'SIMA01-16', 'is_correct_response' => false, 'ability_after' => -4.16],
                    ['label' => 'SIMA01-00', 'is_correct_response' => false, 'ability_after' => -4.43],
                    ['label' => 'SIMA01-01', 'is_correct_response' => false, 'ability_after' => -4.94],
                    ['label' => 'SIMA01-09', 'is_correct_response' => true,  'ability_after' => -5.13],
                    ['label' => 'SIMC10-18', 'is_correct_response' => false, 'ability_after' => -4.82],
                    ['label' => 'SIMC05-09', 'is_correct_response' => false, 'ability_after' => -4.82],
                    ['label' => 'SIMA03-11', 'is_correct_response' => true,  'ability_after' => -4.82],
                    ['label' => 'SIMA03-00', 'is_correct_response' => false, 'ability_after' => -4.79],
                    ['label' => 'SIMA03-08', 'is_correct_response' => false, 'ability_after' => -4.84],
                    ['label' => 'SIMA03-01', 'is_correct_response' => false, 'ability_after' => -4.86],
                    ['label' => 'SIMC06-06', 'is_correct_response' => false, 'ability_after' => -4.9],
                    ['label' => 'SIMC09-17', 'is_correct_response' => false, 'ability_after' => -4.9],
                    ['label' => 'SIMC08-08', 'is_correct_response' => false, 'ability_after' => -4.9],
                    ['label' => 'SIMC04-10', 'is_correct_response' => false, 'ability_after' => -4.9],
                    ['label' => 'SIMC05-15', 'is_correct_response' => false, 'ability_after' => -4.91],
                    ['label' => 'SIMC10-02', 'is_correct_response' => false, 'ability_after' => -4.91],
                    ['label' => 'SIMC07-19', 'is_correct_response' => false, 'ability_after' => -4.91],
                    ['label' => 'SIMB04-12', 'is_correct_response' => false, 'ability_after' => -4.91],
                    ['label' => 'SIMB04-07', 'is_correct_response' => false, 'ability_after' => -4.91],
                    ['label' => 'SIMC10-14', 'is_correct_response' => false, 'ability_after' => -4.91],
                    ['label' => 'SIMC05-19', 'is_correct_response' => false, 'ability_after' => -4.91],
                    ['label' => 'SIMC04-12', 'is_correct_response' => false, 'ability_after' => -4.91],
                    ['label' => 'SIMC09-07', 'is_correct_response' => false, 'ability_after' => -4.91],
                    ['label' => 'SIMC06-01', 'is_correct_response' => false, 'ability_after' => -4.91],
                    ['label' => 'SIMA01-10', 'is_correct_response' => false, 'ability_after' => -4.91],
                    ['label' => 'SIMC06-05', 'is_correct_response' => false, 'ability_after' => -4.92],
                    ['label' => 'SIMC07-14', 'is_correct_response' => false, 'ability_after' => -4.92],
                    ['label' => 'SIMC08-19', 'is_correct_response' => false, 'ability_after' => -4.92],
                    ['label' => 'SIMC08-17', 'is_correct_response' => false, 'ability_after' => -4.92],
                    ['label' => 'SIMC09-02', 'is_correct_response' => false, 'ability_after' => -4.92],
                    ['label' => 'SIMA01-15', 'is_correct_response' => false, 'ability_after' => -4.92],
                    ['label' => 'SIMA01-04', 'is_correct_response' => false, 'ability_after' => -4.94],
                    ['label' => 'SIMA01-06', 'is_correct_response' => false, 'ability_after' => -4.96],
                    ['label' => 'SIMA01-13', 'is_correct_response' => false, 'ability_after' => -4.97],
                    ['label' => 'FINISH', 'is_correct_response' => null, 'ability_after' => -4.97],
                ],
                'initial_ability' => 0.02,
                'initial_se' => 2.97,
                'settings' => [
                    'maxquestions' => 250,
                    'pp_min_inc' => 0.1,
                    'standarderror_min' => 0.25,
                    'standarderror_max' => 0.5,
                    'minquestionspersubscale' => 3,
                    'fake_use_tr_factor' => false,
                ],
                'final_abilities' => [
                    'Simulation' => -4.97,
                    'SimA' => -4.96,
                    'SimA01' => -4.88,
                    'SimA02' => -4.96,
                    'SimA03' => -5.29,
                    'SimA04' => -4.96,
                    'SimA05' => -4.96,
                    'SimA06' => -4.96,
                    'SimA07' => -4.96,
                    'SimB' => -4.97,
                    'SimB01' => -4.97,
                    'SimB02' => -4.97,
                    'SimB03' => -4.97,
                    'SimB04' => -4.97,
                    'SimC' => -4.98,
                    'SimC01' => -4.97,
                    'SimC02' => -4.97,
                    'SimC03' => -4.97,
                    'SimC04' => -4.97,
                    'SimC05' => -4.97,
                    'SimC06' => -4.97,
                    'SimC07' => -4.97,
                    'SimC08' => -4.97,
                    'SimC09' => -4.97,
                    'SimC10' => -4.97,
                ],
            ],
        ];
    }

    /**
     * Test if the correct person ability is calculated, given a set of responses.
     * This does not test a specific strategy but just that the overall value is correct.
     * @dataProvider given_responses_lead_to_expected_abilities_provider
     *
     * @param int $strategy The test strategy to use
     * @param array $responsepattern The given responses
     * @param float $abilityafter The expected ability
     * @return void
     */
    public function test_given_responses_lead_to_expected_abilities(
        int $strategy,
        array $responsepattern,
        float $abilityafter
    ) {
        $this->markTestIncomplete('Calculated value is not yet correct');
        global $DB, $USER;
        $this
            ->createtestenvironment($strategy)
            ->save_or_update();

        catquiz_handler::prepare_attempt_caches();

        // This is needed so that the responses to the questions are indeed saved to the database.
        $this->preventResetByRollback();
        $attemptdata = (object)[
            'instance' => 1,
            'questionsattempted' => 0,
            'id' => 1,
        ];
        foreach ($responsepattern as $label => $iscorrect) {
            [$nextquestionid, $message] = catquiz_handler::fetch_question_id('1', 'mod_adaptivequiz', $attemptdata);
            $question = question_bank::load_question($nextquestionid);
            $this->assertEquals($label, $question->idnumber);
            $this->createresponse($question, $iscorrect);
            $attemptdata->questionsattempted++;
        }
        $abilityrecord = $DB->get_record(
            'local_catquiz_personparams',
            ['userid' => $USER->id, 'catscaleid' => $this->catscaleid],
            'ability'
        );

        $ability = $abilityrecord ? $abilityrecord->ability : 0;
        $this->assertEquals(
            $abilityafter,
            $ability,
            'Ability after fetch is not correct'
        );
    }

    /**
     * Data provider to test that the expected questions are returned.
     *
     * @return array
     */
    public static function given_responses_lead_to_expected_abilities_provider(): array {
        global $CFG;
        $responsepattern = loadresponsesdata(
            $CFG->dirroot . '/local/catquiz/tests/fixtures/responses.2PL.csv'
        );
        return [
            'Classical test' => [
                'strategy' => LOCAL_CATQUIZ_STRATEGY_CLASSIC,
                'response_pattern' => $responsepattern,
                'ability_after' => 0.123,
            ],
        ];
    }

    /**
     * Create a response for the given question and save it in the database.
     *
     * @param mixed $question The question
     * @param bool $iscorrect Shows if the response is correct or not
     *
     * @return void
     */
    private function createresponse($question, $iscorrect): void {
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $slot = $this->quba->add_question($question);
        $this->quba->start_question($slot);

        $time = time();
        $response = $correctresponse = $this->quba->get_correct_response($slot)['answer'];

        // Choose another valid but incorrect response.
        if (! $iscorrect) {
            if ($correctresponse >= 1) {
                $response = $correctresponse - 1;
            } else {
                $response = $correctresponse + 1;
            }
        }
        $this->quba->process_action($slot, ['answer' => $response]);
        $this->quba->finish_all_questions($time);

        // When performing answer evaluation.
        $evaluationresult = (new question_answer_evaluation($this->quba))->perform($slot);
        question_engine::save_questions_usage_by_activity($this->quba);
    }

    /**
     * Parse a json file to create a test environment that will be used for the attempt.
     *
     * @param int $strategyid
     * @param array $settings Optional, additional test settings.
     * @return testenvironment
     */
    private function createtestenvironment(int $strategyid, array $settings): testenvironment {
        global $DB;
        $catscale = $DB->get_record('local_catquiz_catscales', ['parentid' => 0]);

        // Update allowed min/max ability values for tests.
        $catscale->minscalevalue = -10.0;
        $catscale->maxscalevalue = 10.0;
        $DB->update_record('local_catquiz_catscales', $catscale);

        $this->catscaleid = $catscale->id;
        $json = file_get_contents(__DIR__ . '/../fixtures/testenvironment.json');
        $jsondata = json_decode($json);
        $jsondata->catquiz_catscales = $this->catscaleid;
        $jsondata->catscaleid = $this->catscaleid;

        // Include all subscales in the test.
        foreach ([$catscale->id, ...catscale::get_subscale_ids($catscale->id)] as $scaleid) {
            $propertyname = sprintf('catquiz_subscalecheckbox_%d', $scaleid);
            $jsondata->$propertyname = true;
        }
        $jsondata->componentid = '1';
        $jsondata->component = 'mod_adaptivequiz';
        $jsondata->catquiz_selectteststrategy = $strategyid;
        $jsondata->maxquestionsgroup->catquiz_maxquestions = $settings['maxquestions'] ?? 25;
        $jsondata->maxquestionsgroup->catquiz_minquestions = 0;
        $jsondata->maxquestionsscalegroup->catquiz_maxquestionspersubscale = $settings['maxquestionspersubscale'] ?? 10;
        $jsondata->maxquestionsscalegroup->catquiz_minquestionspersubscale = $settings['minquestionspersubscale'] ?? 1;

        if (!empty($settings['standarderror_max'])) {
            $jsondata->catquiz_standarderrorgroup->catquiz_standarderror_max = $settings['standarderror_max'];
        }
        if (!empty($settings['standarderror_min'])) {
            $jsondata->catquiz_standarderrorgroup->catquiz_standarderror_min = $settings['standarderror_min'];
        }
        if (isset($settings['fake_use_tr_factor'])) {
            $jsondata->fake_use_tr_factor = $settings['fake_use_tr_factor'];
        }

        $jsondata->catquiz_pp_min_inc = $settings['pp_min_inc'] ?? 0.01;
        if ($pilotratio = $settings['pilot_ratio'] ?? null) {
            $jsondata->catquiz_includepilotquestions = true;
            $jsondata->catquiz_pilotratio = $pilotratio;
        }
        $jsondata->json = json_encode($jsondata);
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
        global $DB;
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

        $qformat = $this->create_qformat('pilotquestions.xml', $this->course);
        $imported = $qformat->importprocess();
        $this->assertTrue($imported);

        $this->import_itemparams($itemparamsfile);

        $catscale = $DB->get_record('local_catquiz_catscales', ['parentid' => 0]);
        $this->catscaleid = $catscale->id;

        // Include all subscales in the test.
        $scales = catscale::get_subscale_ids($catscale->id);
        $lastscale = end($scales);

    }


    /**
     * Import the item params from the given CSV file
     *
     * @param string $filename The name of the itemparams file.
     *
     * @return void
     */
    private function import_itemparams($filename) {
        global $DB;
        $questions = $DB->get_records('question');
        if (! $questions) {
            exit('No questions were imported');
        }
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
