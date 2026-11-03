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

namespace local_catquiz\local\attempt;

use advanced_testcase;
use local_catquiz\local\result\attempt_result_validator;
use local_catquiz\local\result\personparam_repository;
use local_catquiz\local\result\scale_result;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/tests/fixtures/measured_progress.php');
require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * The export of one attempt, valid result or not (issue #120).
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\local\attempt\attempt_export
 */
final class attempt_export_test extends advanced_testcase {
    /** @var \stdClass The course. */
    private \stdClass $course;

    /** @var \stdClass The participant who made the attempt. */
    private \stdClass $student;

    /**
     * An attempt of a participant in a real activity.
     *
     * @param string|null $resultstatus What the finaliser wrote; null for an attempt not finalised.
     * @return int adaptivequiz_attempt.id
     */
    private function attempt(?string $resultstatus): int {
        global $DB;

        $this->course = $this->getDataGenerator()->create_course();
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $adaptivequiz = $this->getDataGenerator()->get_plugin_generator('mod_adaptivequiz')->create_instance([
            'course' => $this->course->id, 'highestlevel' => 10, 'lowestlevel' => 1, 'standarderror' => 14,
            'attemptfeedbackeditor' => ['text' => '', 'format' => FORMAT_MOODLE],
        ]);
        $now = time();
        $adaptiveattemptid = (int) $DB->insert_record('adaptivequiz_attempt', (object) [
            'instance' => $adaptivequiz->id, 'userid' => $this->student->id, 'uniqueid' => 0,
            'attemptstate' => 'complete', 'attemptstopcriteria' => 'All questions answered', 'questionsattempted' => 3,
            'difficultysum' => 0, 'standarderror' => 0.3, 'measure' => 0, 'timecreated' => $now, 'timemodified' => $now,
            'resultstatus' => $resultstatus, 'resultvalid' => 0,
        ]);
        $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => $this->student->id, 'attemptid' => $adaptiveattemptid, 'component' => 'mod_adaptivequiz',
            'courseid' => $this->course->id, 'instanceid' => $adaptivequiz->id,
            'contextid' => 9, 'status' => 0, 'endtime' => $now, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        return $adaptiveattemptid;
    }

    /**
     * An invalid result is exported as invalid, with its stored values and reason - nothing invented.
     */
    public function test_invalid_result_is_exported_as_such(): void {
        global $DB;
        $this->resetAfterTest();

        $adaptiveattemptid = $this->attempt('invalid');
        $catattemptid = (int) $DB->get_field('local_catquiz_attempts', 'id', ['attemptid' => $adaptiveattemptid]);
        // Every productive item right: the scale keeps its values but is invalid (issue #140).
        $result = attempt_result_validator::from_personabilities(
            [7 => ['value' => 3.1, 'toreport' => true]],
            [7 => 0.45],
            [7 => 3],
            [7 => 1.0]
        );
        personparam_repository::save_attempt_result($catattemptid, (int) $this->student->id, 9, $result);

        $rows = attempt_export::rows($adaptiveattemptid);

        $this->assertCount(1, $rows);
        $row = $rows[0];
        $this->assertSame(attempt_export::COLUMNS, array_keys($row));
        $this->assertSame('invalid', $row['resultstatus']);
        $this->assertSame(0, $row['resultvalid']);
        $this->assertSame(7, $row['scaleid']);
        $this->assertEqualsWithDelta(3.1, $row['ability'], 1e-9);
        $this->assertEqualsWithDelta(0.45, $row['standarderror'], 1e-9);
        $this->assertSame(3, $row['n']);
        $this->assertSame(0, $row['isvalid']);
        $this->assertStringContainsString(scale_result::REASON_FRACTION_ALL_CORRECT, $row['validationstatus']);
    }

    /**
     * An attempt not finalised: no status, no scale values - empty cells, not zeros.
     */
    public function test_attempt_not_finalised_has_empty_cells(): void {
        $this->resetAfterTest();

        $rows = attempt_export::rows($this->attempt(null));

        $this->assertCount(1, $rows);
        $this->assertSame('', $rows[0]['resultstatus']);
        $this->assertSame('', $rows[0]['resultvalid'], 'Not validated is not "invalid".');
        $this->assertSame('', $rows[0]['ability']);
        $this->assertSame('', $rows[0]['standarderror']);
        $this->assertSame('All questions answered', $rows[0]['stopreason']);
    }

    /**
     * Teachers of the course may export; the participant and other participants may not.
     */
    public function test_only_teachers_may_export(): void {
        $this->resetAfterTest();

        $adaptiveattemptid = $this->attempt('valid');
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');

        \local_catquiz\local\access\context_resolver::reset_cache();
        $this->setUser($teacher);
        $this->assertTrue(attempt_export::can_export($adaptiveattemptid));
        $this->setUser($this->student);
        $this->assertFalse(attempt_export::can_export($adaptiveattemptid));
        $this->setUser($other);
        $this->assertFalse(attempt_export::can_export($adaptiveattemptid));
    }

    /**
     * The export tells what was asked and what counts: slots, repetitions, answered and productive items
     * (issues #141, #125/#126).
     *
     * Five slots for three questions - two of them repetitions, as in the production case of 33 slots
     * at a maximum of 25 - and four answers, one of them a pilot item.
     */
    public function test_counts_slots_repetitions_and_answered_items(): void {
        global $DB;
        $this->resetAfterTest();

        $adaptiveattemptid = $this->attempt('invalid');
        $context = \context_course::instance($this->course->id);
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category(['contextid' => $context->id]);
        $quba = \question_engine::make_questions_usage_by_activity('mod_adaptivequiz', $context);
        $quba->set_preferred_behaviour('deferredfeedback');
        $questions = [];
        for ($i = 0; $i < 3; $i++) {
            $questions[] = \question_bank::load_question(
                $generator->create_question('truefalse', null, ['category' => $category->id])->id
            );
        }
        foreach ([0, 1, 1, 2, 2] as $index) {
            $quba->add_question($questions[$index]);
        }
        $quba->start_all_questions();
        \question_engine::save_questions_usage_by_activity($quba);
        $DB->set_field('adaptivequiz_attempt', 'uniqueid', $quba->get_id(), ['id' => $adaptiveattemptid]);
        $DB->set_field('adaptivequiz_attempt', 'resultreason', 'min_questions_not_reached', ['id' => $adaptiveattemptid]);

        $this->setUser($this->student);
        local_catquiz_measured_progress($adaptiveattemptid, [7 => 3], 9, [], [], 1);

        $row = attempt_export::rows($adaptiveattemptid)[0];

        $this->assertSame(attempt_export::COLUMNS, array_keys($row));
        $this->assertSame(5, $row['questionslots']);
        $this->assertSame(2, $row['duplicateslots']);
        $this->assertSame(4, $row['answeredquestions'], 'Pilot items are questions asked.');
        $this->assertSame(3, $row['answeredproductive'], 'Pilot items do not count for the result.');
        $this->assertSame('min_questions_not_reached', $row['resultreason']);
    }

    /**
     * The production attempt behind #141, anonymised: 33 slots at a maximum of 25 (usage 17666).
     *
     * 25 productive questions and 4 pilot items; each pilot followed by the question before it
     * again (slots 5-6-7, 16-17-18, 23-24-25, 26-27-28). The repetitions are visible in the export
     * and count nowhere: 29 questions answered, 25 of them productive - the maximum, not 33.
     */
    public function test_production_case_33_slots_at_maximum_25(): void {
        global $DB;
        $this->resetAfterTest();

        $adaptiveattemptid = $this->attempt('valid');
        $context = \context_course::instance($this->course->id);
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category(['contextid' => $context->id]);
        $question = fn() => \question_bank::load_question(
            $generator->create_question('truefalse', null, ['category' => $category->id])->id
        );
        $quba = \question_engine::make_questions_usage_by_activity('mod_adaptivequiz', $context);
        $quba->set_preferred_behaviour('deferredfeedback');
        $slots = [];
        for ($i = 1; $i <= 25; $i++) {
            $productive = $question();
            $slots[] = $quba->add_question($productive);
            if (in_array(count($slots), [5, 16, 23, 26])) {
                $quba->add_question($question());
                $slots[] = $quba->add_question($productive);
            }
        }
        $quba->start_all_questions();
        \question_engine::save_questions_usage_by_activity($quba);
        $DB->set_field('adaptivequiz_attempt', 'uniqueid', $quba->get_id(), ['id' => $adaptiveattemptid]);

        $this->setUser($this->student);
        local_catquiz_measured_progress($adaptiveattemptid, [7 => 25], 9, [], [], 4);

        $row = attempt_export::rows($adaptiveattemptid)[0];

        $this->assertSame(33, $row['questionslots']);
        $this->assertSame(4, $row['duplicateslots']);
        $this->assertSame(29, $row['answeredquestions']);
        $this->assertSame(25, $row['answeredproductive'], 'The repetitions are not weighted a second time.');
    }

    /**
     * Without a usage or a progress the counts stay empty - not zero.
     */
    public function test_counts_are_empty_without_usage_and_progress(): void {
        $this->resetAfterTest();

        $row = attempt_export::rows($this->attempt(null))[0];

        foreach (['questionslots', 'duplicateslots', 'answeredquestions', 'answeredproductive'] as $column) {
            $this->assertSame('', $row[$column], $column);
        }
    }

    /**
     * Where an attempt stands, in one word: running, valid, invalid, aborted or not yet judged (issue #141).
     */
    public function test_lifecycle_names_every_state(): void {
        global $DB;
        $this->resetAfterTest();

        $cases = [
            'running' => ['inprogress', null, 0, 0, attempt_export::LIFECYCLE_RUNNING],
            'not judged' => ['complete', null, 0, 0, attempt_export::LIFECYCLE_NOT_FINALISED],
            'invalid' => ['complete', 'invalid', 0, 0, attempt_export::LIFECYCLE_COMPLETED_INVALID],
            'valid' => ['complete', 'valid', 1, 0, attempt_export::LIFECYCLE_COMPLETED_VALID],
            'aborted' => ['complete', 'invalid', 0, LOCAL_CATQUIZ_ATTEMPT_ABORTED, attempt_export::LIFECYCLE_ABORTED],
        ];
        // Status 1 is also "no remaining questions": with answers that is a regular stop, not an abort.
        $cases['no remaining questions'] = ['complete', 'invalid', 0, 1, attempt_export::LIFECYCLE_COMPLETED_INVALID, 3];
        $cases['aborted'][] = 0;
        foreach ($cases as $name => $case) {
            [$state, $resultstatus, $valid, $catstatus, $expected] = $case;
            $id = $this->attempt($resultstatus);
            $DB->update_record('adaptivequiz_attempt', (object) [
                'id' => $id, 'attemptstate' => $state, 'resultvalid' => $valid, 'questionsattempted' => $case[5] ?? 3,
            ]);
            $DB->set_field('local_catquiz_attempts', 'status', $catstatus, ['attemptid' => $id]);

            $this->assertSame($expected, attempt_export::rows($id)[0]['lifecycle'], $name);
        }
    }
}
