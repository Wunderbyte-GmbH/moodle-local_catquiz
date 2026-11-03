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
use core_question\local\bank\question_version_status;
use local_catquiz\table\catscalequestions_table;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');
require_once($CFG->libdir . '/questionlib.php');

/**
 * Which questions the runtime pool of a test contains (issue #82).
 *
 * Two levels hide a question: the question bank (the newest version hidden or a draft, for every use) and
 * the CAT manager (an item deactivated in one scale). The pool holds a question only when both allow it;
 * the manager table keeps listing all of them and says which level hides it. The pool is cached: every
 * change has to reach it without anybody purging it - the tests here never purge.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\catscale::get_testitems
 * @covers \local_catquiz\catscale::item_availability
 * @covers \local_catquiz\catscale::unavailable_question_ids
 * @covers \local_catquiz\catquiz::return_sql_for_catscalequestions
 * @covers \local_catquiz\table\catscalequestions_table::action_togglestatus
 */
final class item_availability_test extends advanced_testcase {
    /** @var int Running number. */
    private int $seq = 0;

    /** @var int CAT context. */
    private int $contextid;

    /** @var \stdClass Question category. */
    private \stdClass $category;

    /**
     * A CAT context and a question category.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->contextid = (int) $DB->insert_record('local_catquiz_catcontext', (object) [
            'name' => 'Availability context', 'description' => '', 'descriptionformat' => FORMAT_HTML,
            'starttimestamp' => time() - 100, 'endtimestamp' => time() + 10000,
            'timecreated' => time(), 'timemodified' => time(), 'usermodified' => 0,
        ]);
        $this->category = $this->getDataGenerator()->get_plugin_generator('core_question')->create_question_category();
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
            'parentid' => $parentid, 'name' => 'Availability scale ' . (++$this->seq), 'contextid' => $this->contextid,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * A real question of the question bank, its (only) version in the given status.
     *
     * @param string $versionstatus ready, hidden or draft
     * @return \stdClass The question, with id.
     */
    private function question(string $versionstatus = question_version_status::QUESTION_STATUS_READY): \stdClass {
        global $DB;
        $question = $this->getDataGenerator()->get_plugin_generator('core_question')->create_question(
            'truefalse',
            null,
            ['category' => $this->category->id, 'name' => 'Availability item ' . (++$this->seq)]
        );
        $DB->set_field('question_versions', 'status', $versionstatus, ['questionid' => $question->id]);
        return $question;
    }

    /**
     * Assigns a question to a scale.
     *
     * @param int $questionid
     * @param int $scaleid
     * @param int $status LOCAL_CATQUIZ_TESTITEM_STATUS_*
     * @return int item id
     */
    private function item(int $questionid, int $scaleid, int $status = LOCAL_CATQUIZ_TESTITEM_STATUS_ACTIVE): int {
        global $DB;
        return (int) $DB->insert_record('local_catquiz_items', (object) [
            'componentid' => $questionid, 'componentname' => 'question', 'catscaleid' => $scaleid,
            'contextid' => $this->contextid, 'status' => $status,
        ]);
    }

    /**
     * The runtime pool of a scale, as the selection reads it - from the cache when it is there. Never purged.
     *
     * @param int $scaleid
     * @param bool $includesubscales
     * @param array $selectedsubscales
     * @return array Rows keyed by question id.
     */
    private function pool_rows(int $scaleid, bool $includesubscales = false, array $selectedsubscales = []): array {
        return (new catscale($scaleid))->get_testitems($this->contextid, $includesubscales, null, $selectedsubscales);
    }

    /**
     * The question ids of the runtime pool of a scale.
     *
     * @param int $scaleid
     * @param bool $includesubscales
     * @param array $selectedsubscales
     * @return int[]
     */
    private function pool(int $scaleid, bool $includesubscales = false, array $selectedsubscales = []): array {
        $ids = array_map('intval', array_keys($this->pool_rows($scaleid, $includesubscales, $selectedsubscales)));
        sort($ids);
        return $ids;
    }

    /**
     * The rows the manager table lists for a scale.
     *
     * @param int $scaleid
     * @return array
     */
    private function manager_rows(int $scaleid): array {
        global $DB;
        [$select, $from, $where, , $params] = catquiz::return_sql_for_catscalequestions([$scaleid], $this->contextid);
        return array_values($DB->get_records_sql("SELECT $select FROM $from WHERE $where", $params));
    }

    /**
     * Global x local, and a draft.
     *
     * @return array
     */
    public static function visibility_cells(): array {
        return [
            'ready, active' => [question_version_status::QUESTION_STATUS_READY, LOCAL_CATQUIZ_TESTITEM_STATUS_ACTIVE, true],
            'ready, inactive' => [question_version_status::QUESTION_STATUS_READY, LOCAL_CATQUIZ_TESTITEM_STATUS_INACTIVE, false],
            'hidden, active' => [question_version_status::QUESTION_STATUS_HIDDEN, LOCAL_CATQUIZ_TESTITEM_STATUS_ACTIVE, false],
            'hidden, inactive' => [question_version_status::QUESTION_STATUS_HIDDEN, LOCAL_CATQUIZ_TESTITEM_STATUS_INACTIVE, false],
            'draft, active' => [question_version_status::QUESTION_STATUS_DRAFT, LOCAL_CATQUIZ_TESTITEM_STATUS_ACTIVE, false],
        ];
    }

    /**
     * Each cell on its own: the pool of the scale, and the pool of its root with the subscales.
     *
     * @param string $versionstatus
     * @param int $itemstatus
     * @param bool $inpool
     * @dataProvider visibility_cells
     */
    public function test_visibility_cell(string $versionstatus, int $itemstatus, bool $inpool): void {
        $root = $this->scale();
        $leaf = $this->scale($root);
        $question = $this->question($versionstatus);
        $this->item((int) $question->id, $leaf, $itemstatus);

        $expected = $inpool ? [(int) $question->id] : [];
        $this->assertSame($expected, $this->pool($leaf));
        $this->assertSame($expected, $this->pool($root, true));

        // The manager lists it either way, with the state in words.
        $rows = $this->manager_rows($leaf);
        $this->assertCount(1, $rows);
        $this->assertSame($versionstatus, $rows[0]->questionstatus);
        $availability = catscale::item_availability($rows[0]->questionstatus, $rows[0]->testitemstatus);
        $this->assertSame($inpool, $availability === catscale::AVAILABILITY_ACTIVE);
    }

    /**
     * What the manager says for each cell.
     */
    public function test_availability_states(): void {
        $this->assertSame(catscale::AVAILABILITY_ACTIVE, catscale::item_availability('ready', 0));
        $this->assertSame(catscale::AVAILABILITY_SCALEINACTIVE, catscale::item_availability('ready', 1));
        $this->assertSame(catscale::AVAILABILITY_GLOBALHIDDEN, catscale::item_availability('hidden', 0));
        // Global wins: hidden in the bank stays hidden, whatever the scale says.
        $this->assertSame(catscale::AVAILABILITY_GLOBALHIDDEN, catscale::item_availability('hidden', 1));
        $this->assertSame(catscale::AVAILABILITY_GLOBALDRAFT, catscale::item_availability('draft', 0));
        $this->assertSame(catscale::AVAILABILITY_GLOBALHIDDEN, catscale::item_availability(null, 0));
    }

    /**
     * Deactivated in scale A - assigned there first, so with the lower item id - and active in scale B.
     *
     * A's pool is empty, the root's pool has the question from B, and a test of the root with only A selected
     * does not get it.
     */
    public function test_deactivated_in_one_scale_only(): void {
        $root = $this->scale();
        $a = $this->scale($root);
        $b = $this->scale($root);
        $question = $this->question();
        $itema = $this->item((int) $question->id, $a, LOCAL_CATQUIZ_TESTITEM_STATUS_INACTIVE);
        $itemb = $this->item((int) $question->id, $b, LOCAL_CATQUIZ_TESTITEM_STATUS_ACTIVE);
        $this->assertLessThan($itemb, $itema);

        $this->assertSame([], $this->pool($a));
        $this->assertSame([(int) $question->id], $this->pool($b));
        $rootpool = $this->pool_rows($root, true);
        $this->assertSame([(int) $question->id], array_map('intval', array_keys($rootpool)));
        $this->assertSame($b, (int) $rootpool[$question->id]->catscaleid);
        $this->assertSame([], $this->pool($root, true, [$a]));
    }

    /**
     * Versions: the newest version of the bank entry decides; the item plays the version it refers to; one row.
     *
     * @return array
     */
    public static function version_cases(): array {
        return [
            'v1 ready, v2 hidden' => ['ready', 'hidden', false],
            'v1 hidden, v2 ready' => ['hidden', 'ready', true],
            'v1 ready, v2 draft' => ['ready', 'draft', false],
            'v1 ready, v2 ready' => ['ready', 'ready', true],
        ];
    }

    /**
     * The item refers to version 1; a version 2 exists.
     *
     * @param string $v1
     * @param string $v2
     * @param bool $inpool
     * @dataProvider version_cases
     */
    public function test_the_newest_version_decides(string $v1, string $v2, bool $inpool): void {
        global $DB;
        $scale = $this->scale();
        $first = $this->question($v1);
        $second = $this->getDataGenerator()->get_plugin_generator('core_question')->update_question($first);
        $this->assertNotEquals($first->id, $second->id, 'Fixture: a second version.');
        $DB->set_field('question_versions', 'status', $v1, ['questionid' => $first->id]);
        $DB->set_field('question_versions', 'status', $v2, ['questionid' => $second->id]);
        $this->item((int) $first->id, $scale);

        $this->assertSame($inpool ? [(int) $first->id] : [], $this->pool($scale));
        $rows = $this->manager_rows($scale);
        $this->assertCount(1, $rows, 'One row per item, whatever the number of versions.');
        $this->assertSame((int) $first->id, (int) $rows[0]->id);
        $this->assertSame($v2, $rows[0]->questionstatus);
        $this->assertSame($inpool ? [] : [(int) $first->id], catscale::unavailable_question_ids([(int) $first->id]));
    }

    /**
     * Deactivated in the scale after the pool was cached: gone from it, without a purge.
     */
    public function test_deactivating_reaches_the_cached_pool(): void {
        $scale = $this->scale();
        $question = $this->question();
        $this->item((int) $question->id, $scale);
        $this->assertSame([(int) $question->id], $this->pool($scale));

        catscale::add_or_update_testitem_to_scale($scale, (int) $question->id, LOCAL_CATQUIZ_TESTITEM_STATUS_INACTIVE);

        $this->assertSame([], $this->pool($scale));
    }

    /**
     * Set to draft in the question bank after the pool was cached: gone from it, without a purge.
     */
    public function test_draft_in_the_bank_reaches_the_cached_pool(): void {
        $scale = $this->scale();
        $question = $this->question();
        $this->item((int) $question->id, $scale);
        $this->assertSame([(int) $question->id], $this->pool($scale));

        $result = \qbank_editquestion\external\update_question_version_status::execute(
            (int) $question->id,
            question_version_status::QUESTION_STATUS_DRAFT
        );
        $this->assertTrue($result['status']);

        $this->assertSame([], $this->pool($scale));
    }

    /**
     * Deleted in the question bank while in use: the bank only hides it, and tells nobody. The selection asks
     * for the question it hands out - and gets told it is out.
     */
    public function test_hidden_by_deletion_is_named_unavailable(): void {
        global $DB;
        $scale = $this->scale();
        $question = $this->question();
        $this->item((int) $question->id, $scale);
        $this->assertSame([], catscale::unavailable_question_ids([(int) $question->id]));
        // In use: a reference to it, as an activity keeps one.
        $DB->insert_record('question_references', (object) [
            'usingcontextid' => \context_system::instance()->id, 'component' => 'mod_quiz', 'questionarea' => 'slot',
            'itemid' => 1, 'questionbankentryid' => $DB->get_field(
                'question_versions',
                'questionbankentryid',
                ['questionid' => $question->id]
            ),
        ]);

        question_delete_question((int) $question->id);

        $this->assertTrue($DB->record_exists('question', ['id' => $question->id]), 'Fixture: hidden, not deleted.');
        $this->assertSame([(int) $question->id], catscale::unavailable_question_ids([(int) $question->id]));
    }

    /**
     * The toggle of the manager works on the item of the given scale only, reads the state from the database,
     * and does not reactivate a question the bank hides.
     */
    public function test_toggle(): void {
        global $DB;
        $scale = $this->scale();
        $other = $this->scale();
        $question = $this->question();
        $this->item((int) $question->id, $scale);
        $table = new catscalequestions_table('availability_toggle');

        // A scale the question is not in: no new item there.
        $result = $table->action_togglestatus((int) $question->id, json_encode(['catscaleid' => $other]));
        $this->assertSame(0, $result['success']);
        $this->assertFalse($DB->record_exists('local_catquiz_items', ['catscaleid' => $other]));

        // Whatever the client claims, the stored state is toggled.
        $result = $table->action_togglestatus((int) $question->id, json_encode(['catscaleid' => $scale, 'testitemstatus' => 1]));
        $this->assertSame(1, $result['success']);
        $this->assertSame(
            LOCAL_CATQUIZ_TESTITEM_STATUS_INACTIVE,
            (int) $DB->get_field('local_catquiz_items', 'status', ['catscaleid' => $scale])
        );

        // Hidden in the bank: not activated in the scale.
        $DB->set_field('question_versions', 'status', 'hidden', ['questionid' => $question->id]);
        $result = $table->action_togglestatus((int) $question->id, json_encode(['catscaleid' => $scale]));
        $this->assertSame(0, $result['success']);
        $this->assertSame(get_string('itemavailability_globallocked', 'local_catquiz'), $result['message']);
        $this->assertSame(
            LOCAL_CATQUIZ_TESTITEM_STATUS_INACTIVE,
            (int) $DB->get_field('local_catquiz_items', 'status', ['catscaleid' => $scale])
        );
    }

    /**
     * The toggle needs the capability to manage scales.
     */
    public function test_toggle_needs_the_capability(): void {
        $scale = $this->scale();
        $question = $this->question();
        $this->item((int) $question->id, $scale);
        $this->setUser($this->getDataGenerator()->create_user());

        $this->expectException(\required_capability_exception::class);
        (new catscalequestions_table('availability_toggle'))
            ->action_togglestatus((int) $question->id, json_encode(['catscaleid' => $scale]));
    }
}
