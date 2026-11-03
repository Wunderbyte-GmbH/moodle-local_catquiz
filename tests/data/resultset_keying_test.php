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

namespace local_catquiz\data;

use advanced_testcase;
use cache;
use local_catquiz\catquiz;
use local_catquiz\catscale;
use question_engine;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * Result sets keyed by their first column lose no row (issue #104).
 *
 * get_records_sql() keys its rows by the first column; when that is not unique, all rows but one fall
 * onto the same key - silently in production, with a developer notice in tests. Each test builds the
 * case that used to collapse: several valid abilities in the history of a person, a question in two
 * sibling scales, several test rows of one instance, several attempts of one person.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\catquiz::get_sql_for_model_input
 * @covers \local_catquiz\catquiz::return_sql_for_catscalequestions
 * @covers \local_catquiz\catquiz::return_sql_for_quizattempts
 * @covers \local_catquiz\catquiz::return_data_from_attemptstable
 * @covers \local_catquiz\catscale::get_testitems
 * @covers \local_catquiz\catscale::one_row_per_question
 */
final class resultset_keying_test extends advanced_testcase {
    /** @var int A CAT context that covers now. */
    private int $contextid;

    /** @var int Running number. */
    private int $seq = 0;

    /**
     * A CAT context.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->contextid = (int) $DB->insert_record('local_catquiz_catcontext', (object) [
            'name' => 'Keying context', 'description' => '', 'descriptionformat' => FORMAT_HTML,
            'starttimestamp' => time() - 1000, 'endtimestamp' => time() + 10000,
            'timecreated' => time(), 'timemodified' => time(), 'usermodified' => 0,
        ]);
    }

    /**
     * A scale.
     *
     * @param int $parentid
     * @return int
     */
    private function scale(int $parentid = 0): int {
        global $DB;
        return (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'parentid' => $parentid, 'name' => 'Keying scale ' . (++$this->seq), 'contextid' => $this->contextid,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * A true/false question with a ready version.
     *
     * @return \stdClass
     */
    private function question(): \stdClass {
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();
        return $generator->create_question('truefalse', null, ['category' => $category->id]);
    }

    /**
     * Assigns a question to a scale.
     *
     * @param int $questionid
     * @param int $scaleid
     * @return int item id
     */
    private function item(int $questionid, int $scaleid): int {
        global $DB;
        return (int) $DB->insert_record('local_catquiz_items', (object) [
            'componentid' => $questionid, 'componentname' => 'question', 'catscaleid' => $scaleid,
            'contextid' => $this->contextid, 'status' => LOCAL_CATQUIZ_TESTITEM_STATUS_ACTIVE,
        ]);
    }

    /**
     * Every row of a query, not keyed.
     *
     * @param string $sql
     * @param array $params
     * @return \stdClass[]
     */
    private function all_rows(string $sql, array $params): array {
        global $DB;
        $rows = [];
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $row) {
            $rows[] = $row;
        }
        $rs->close();
        return $rows;
    }

    /**
     * The model input with the ability: every step once, with the newest valid ability of the person -
     * not once per row of its history, and not an older or an invalid value.
     */
    public function test_model_input_joins_the_newest_valid_ability(): void {
        global $DB, $USER;
        $scaleid = $this->scale();
        $question = $this->question();
        $quba = question_engine::make_questions_usage_by_activity('local_catquiz', \context_system::instance());
        $quba->set_preferred_behaviour('deferredfeedback');
        $slot = $quba->add_question(\question_bank::load_question($question->id));
        $quba->start_question($slot);
        $quba->process_action($slot, ['answer' => 1]);
        $quba->finish_all_questions(time());
        question_engine::save_questions_usage_by_activity($quba);

        foreach ([[0.1, 1], [0.7, 1], [1.5, 0]] as [$ability, $valid]) {
            $DB->insert_record('local_catquiz_personparams', (object) [
                'userid' => $USER->id, 'catscaleid' => $scaleid, 'contextid' => $this->contextid, 'ability' => $ability,
                'isvalid' => $valid, 'timecreated' => time(), 'timemodified' => time(),
            ]);
        }

        [$plainsql, $plainparams] = catquiz::get_sql_for_model_input($this->contextid, [$scaleid], null, null);
        [$sql, $params] = catquiz::get_sql_for_model_input($this->contextid, [$scaleid], null, null, false, $scaleid);
        $steps = $this->all_rows($plainsql, $plainparams);
        $rows = $this->all_rows($sql, $params);

        $this->assertNotEmpty($steps);
        $this->assertCount(count($steps), $rows, 'Every step exactly once.');
        $this->assertCount(count($rows), array_unique(array_map(fn($r) => $r->uniqueid, $rows)));
        foreach ($rows as $row) {
            $this->assertEqualsWithDelta(0.7, (float) $row->ability, 1e-9, 'The newest valid ability.');
        }
        $this->assertCount(count($rows), $DB->get_records_sql($sql, $params));
        $this->assertDebuggingNotCalled();
    }

    /**
     * A question in two sibling scales: the manager lists both rows, as many as it counts; the runtime
     * pool has the question once, by a fixed rule - the scale it was assigned to first.
     */
    public function test_question_in_two_sibling_scales(): void {
        global $DB;
        $root = $this->scale();
        $first = $this->scale($root);
        $second = $this->scale($root);
        $question = $this->question();
        // Assigned to the second sibling first: the rule is the item id, not the scale id.
        $this->item($question->id, $second);
        $this->item($question->id, $first);
        $other = $this->question();
        $this->item($other->id, $first);
        $scales = [$root, $first, $second];

        [$select, $from, $where, , $params] = catquiz::return_sql_for_catscalequestions($scales, $this->contextid);
        $listed = $DB->get_records_sql("SELECT $select FROM $from WHERE $where", $params);
        $this->assertCount(3, $listed, 'One row per item.');
        $this->assertEqualsCanonicalizing(
            [$second, $first, $first],
            array_map(fn($r) => (int) $r->catscaleid, array_values($listed))
        );
        [$countfrom, $countwhere, $countparams] = catquiz::return_sql_for_catscalequestions_count($scales, $this->contextid);
        $this->assertSame(3, (int) $DB->count_records_sql("SELECT COUNT(1) FROM $countfrom WHERE $countwhere", $countparams));

        cache::make('local_catquiz', 'runtimepool')->purge();
        $pool = (new catscale($root))->get_testitems($this->contextid, true);
        $this->assertEqualsCanonicalizing([(int) $question->id, (int) $other->id], array_map('intval', array_keys($pool)));
        $this->assertSame($second, (int) $pool[$question->id]->catscaleid, 'The scale it was assigned to first.');
        $this->assertDebuggingNotCalled();
    }

    /**
     * The attempts of the [allquizattempts] table: one row per attempt, with the name of its own test -
     * also with a test row of another component and a template for the same instance id, and with the
     * component in its short spelling.
     */
    public function test_quiz_attempts_have_one_row_each_with_their_own_test(): void {
        global $DB, $USER;
        $scaleid = $this->scale();
        $course = $this->getDataGenerator()->create_course();
        $instanceid = 4711;
        foreach (
            [['mod_adaptivequiz', $instanceid, 'Own test'], ['mod_other', $instanceid, 'Other component'],
                ['mod_adaptivequiz', 0, 'Template']] as [$component, $componentid, $name]
        ) {
            $DB->insert_record('local_catquiz_tests', (object) [
                'component' => $component, 'componentid' => $componentid, 'name' => $name, 'status' => 1,
                'json' => '{}', 'courseid' => $course->id, 'timecreated' => time(), 'timemodified' => time(),
            ]);
        }
        foreach (['mod_adaptivequiz', 'adaptivequiz'] as $i => $component) {
            $DB->insert_record('local_catquiz_attempts', (object) [
                'userid' => $USER->id, 'scaleid' => $scaleid, 'contextid' => $this->contextid, 'courseid' => $course->id,
                'attemptid' => 900 + $i, 'component' => $component, 'instanceid' => $instanceid, 'status' => 0,
                'timecreated' => time(), 'timemodified' => time(),
            ]);
        }

        [$select, $from, $where] = catquiz::return_sql_for_quizattempts();
        $rows = $this->all_rows("SELECT $select FROM $from WHERE $where", []);

        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertSame('Own test', $row->instance, $row->component);
        }
    }

    /**
     * Completed attempts of one person, two of them from before the attempt id was stored: all are returned,
     * the newest first, and a limit takes the newest. A running attempt (no end time) has no result and is
     * not among them (issues #88, #101).
     */
    public function test_every_attempt_of_a_person_is_returned(): void {
        global $DB, $USER;
        $scaleid = $this->scale();
        $course = $this->getDataGenerator()->create_course();
        $ids = [];
        foreach ([[null, 100], [null, 200], [905, 300]] as [$attemptid, $time]) {
            $ids[] = (int) $DB->insert_record('local_catquiz_attempts', (object) [
                'userid' => $USER->id, 'scaleid' => $scaleid, 'contextid' => $this->contextid, 'courseid' => $course->id,
                'attemptid' => $attemptid, 'component' => 'mod_adaptivequiz', 'instanceid' => 1, 'status' => 0,
                'json' => '{}', 'timecreated' => $time, 'timemodified' => $time, 'endtime' => $time,
            ]);
        }
        $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => $USER->id, 'scaleid' => $scaleid, 'contextid' => $this->contextid, 'courseid' => $course->id,
            'attemptid' => 906, 'component' => 'mod_adaptivequiz', 'instanceid' => 1, 'status' => 0,
            'json' => '{}', 'timecreated' => 400, 'timemodified' => 400, 'endtime' => null,
        ]);

        $all = catquiz::return_data_from_attemptstable(0, 0, $course->id, 0, (int) $USER->id);
        $this->assertSame(array_reverse($ids), array_map('intval', array_keys($all)));

        $newest = catquiz::return_data_from_attemptstable(1, 0, $course->id, 0, (int) $USER->id);
        $this->assertSame([905], array_map(fn($r) => (int) $r->attemptid, array_values($newest)));
        $this->assertDebuggingNotCalled();
    }
}
