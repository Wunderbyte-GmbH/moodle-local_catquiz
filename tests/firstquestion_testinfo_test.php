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
use local_catquiz\local\model\model_item_param_list;
use mod_adaptivequiz\local\attempt;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * Guards that the very first question of an attempt is administrable even when
 * the configured start question level puts the initial ability into a
 * low-test-information region.
 *
 * Regression for the "attemptnofirstquestion" abort: with
 * catquiz_selectfirstquestion = "very easy" (-2) the starting ability guess is
 * pushed far to the edge, where the scale's test potential falls below the
 * se_max threshold. filterbytestinfo used to deactivate the one and only active
 * scale on the first question (zero questions played yet), leaving no active
 * scale, so no question could be selected and mod_adaptivequiz raised the
 * generic attemptnofirstquestion exception. A scale must never be deactivated
 * before at least one question has been administered from it.
 *
 * The test uses the exact Behat fixtures and the Behat data generator (not an
 * alternative import helper) and selects as a real student, so it exercises the
 * same path as the failing acceptance scenario.
 *
 * @package    local_catquiz
 * @copyright  2024 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\teststrategy\preselect_task\filterbytestinfo
 */
final class firstquestion_testinfo_test extends advanced_testcase {
    /**
     * The first question can be selected for every valid start level, including
     * the extreme "very easy" (-2) that used to abort the attempt.
     *
     * @param string $selectfirstquestion The configured start level (-2..2).
     *
     * @dataProvider startlevel_provider
     */
    public function test_first_question_is_administrable(string $selectfirstquestion): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        $adaptivequiz = $this->getDataGenerator()->get_plugin_generator('mod_adaptivequiz')
            ->create_instance([
                'course' => $course->id,
                'highestlevel' => 10,
                'lowestlevel' => 1,
                'standarderror' => 14,
                'attemptfeedbackeditor' => ['text' => '', 'format' => FORMAT_MOODLE],
            ]);

        $generator = $this->getDataGenerator()->get_plugin_generator('local_catquiz');
        $generator->create_catquiz_questions([
            'filepath' => 'local/catquiz/tests/fixtures/quiz-adaptivetest-Simulation-small.xml',
            'filename' => 'quiz-adaptivetest-Simulation-small.xml',
            'courseid' => $course->id,
        ]);
        $generator->create_catquiz_importedcatscales([
            'filepath' => 'local/catquiz/tests/fixtures/simulation_small.csv',
            'filename' => 'simulation_small.csv',
        ]);

        $rootscale = $DB->get_record('local_catquiz_catscales', ['parentid' => 0]);

        // Invariant A: the scale resolves to an active context.
        $contextid = catscale::get_context_id($rootscale->id);
        $this->assertGreaterThan(0, $contextid, 'Scale has no context');

        // Invariant B: item parameters exist in that exact context.
        $scaleids = array_merge([$rootscale->id], catscale::get_subscale_ids($rootscale->id));
        $itemparams = model_item_param_list::get($contextid, null, $scaleids);
        $this->assertNotEmpty($itemparams, 'No item parameters in the scale context');

        // Invariant C: the scale's questions are resolvable.
        $scale = new catscale($rootscale->id);
        $this->assertNotEmpty($scale->get_testitems($contextid, true), 'Scale has no resolvable test items');

        $generator->create_catquiz_testsettings([
            'courseid' => $course->id,
            'adaptivecatquizid' => $adaptivequiz->id,
            'catscalesid' => $rootscale->id,
            'cateststrategyid' => LOCAL_CATQUIZ_STRATEGY_LOWESTSUB,
            'catmodel' => 'catquiz',
            'catquiz_selectfirstquestion' => $selectfirstquestion,
            'catquiz_maxquestions' => 4,
            'catquiz_standarderror_min' => 0.4,
            'catquiz_standarderror_max' => 0.6,
            'numberoffeedbackoptions' => 2,
        ]);

        catquiz_handler::prepare_attempt_caches();
        $this->preventResetByRollback();

        // Invariant E: select as the real student, using the same attempt record
        // the adaptivequiz adapter passes to catquiz_handler.
        $this->setUser($student);
        $adaptiveattempt = new attempt($adaptivequiz, $student->id);
        [$questionid, $errormessage] = catquiz_handler::fetch_question_id(
            $adaptivequiz->id,
            'mod_adaptivequiz',
            $adaptiveattempt->get_attempt()
        );

        // Invariant F: the failure, if any, is diagnostic.
        $this->assertNotEquals(
            0,
            $questionid,
            sprintf(
                'No first question could be selected for start level %s: %s',
                $selectfirstquestion,
                $errormessage
            )
        );
    }

    /**
     * All valid start levels, from very easy (-2) to very difficult (2).
     *
     * @return array
     */
    public static function startlevel_provider(): array {
        return [
            'very easy (-2)' => ['-2'],
            'easy (-1)' => ['-1'],
            'medium (0)' => ['0'],
            'difficult (1)' => ['1'],
            'very difficult (2)' => ['2'],
        ];
    }
}
