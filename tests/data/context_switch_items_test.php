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
use local_catquiz\catquiz;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * A change of the CAT context keeps the parameters of every item - question and scale (issue #148).
 *
 * The same question Q is an item of two sibling scales A and B, each item with a parameter of its own and
 * the same model. Before, items and parameters were mapped by question only: one item survived per question,
 * all parameters were attached to it, and with equal models the unique index (itemid, contextid, model) failed -
 * after the parameters had already been copied outside of the transaction.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\catquiz::create_items_in_new_context
 * @covers \local_catquiz\catquiz::item_identity
 * @covers \local_catquiz\data\dataapi::update_catscale
 */
final class context_switch_items_test extends advanced_testcase {
    /** @var int The old context. */
    private int $old;

    /** @var int A context created between the two - its id lies between theirs. */
    private int $between;

    /** @var int The new context. */
    private int $new;

    /** @var int The root scale. */
    private int $root;

    /** @var int[] The sibling scales A and B. */
    private array $scales = [];

    /** @var int The question in both scales. */
    private int $question;

    /** @var int[] The items of Q in A and in B, in the old context. */
    private array $items = [];

    /**
     * The tree: a root in the old context, two sibling scales, the same question as an item of each.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->old = $this->context('old');
        $this->between = $this->context('between');
        $this->new = $this->context('new');
        $this->root = $this->scale('Root', 0, $this->old);
        $this->scales = ['A' => $this->scale('A', $this->root, null), 'B' => $this->scale('B', $this->root, null)];
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();
        $this->question = (int) $generator->create_question('truefalse', null, ['category' => $category->id])->id;
        $this->items['A'] = $this->item($this->scales['A'], $this->question, $this->old);
        $this->items['B'] = $this->item($this->scales['B'], $this->question, $this->old);
        $this->param($this->items['A'], $this->question, $this->old, 'raschbirnbaum', 1.0);
        $this->param($this->items['B'], $this->question, $this->old, 'raschbirnbaum', 2.0);
    }

    /**
     * A CAT context.
     *
     * @param string $name
     * @return int
     */
    private function context(string $name): int {
        global $DB;
        return (int) $DB->insert_record('local_catquiz_catcontext', (object) [
            'name' => $name, 'description' => '', 'descriptionformat' => FORMAT_HTML,
            'starttimestamp' => time() - 100, 'endtimestamp' => time() + 10000,
            'timecreated' => time(), 'timemodified' => time(), 'usermodified' => 0,
        ]);
    }

    /**
     * A scale.
     *
     * @param string $name
     * @param int $parentid
     * @param int|null $contextid
     * @return int
     */
    private function scale(string $name, int $parentid, ?int $contextid): int {
        global $DB;
        return (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'name' => $name, 'parentid' => $parentid, 'contextid' => $contextid,
            'minscalevalue' => -5, 'maxscalevalue' => 5, 'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * An item.
     *
     * @param int $scaleid
     * @param int $questionid
     * @param int $contextid
     * @return int
     */
    private function item(int $scaleid, int $questionid, int $contextid): int {
        global $DB;
        return (int) $DB->insert_record('local_catquiz_items', (object) [
            'componentid' => $questionid, 'componentname' => 'question', 'catscaleid' => $scaleid,
            'contextid' => $contextid, 'status' => LOCAL_CATQUIZ_TESTITEM_STATUS_ACTIVE,
        ]);
    }

    /**
     * A parameter, active for its item.
     *
     * @param int $itemid 0 for a parameter fetched without an item.
     * @param int $questionid
     * @param int $contextid
     * @param string $model
     * @param float $difficulty
     * @param int $status
     * @return int
     */
    private function param(
        int $itemid,
        int $questionid,
        int $contextid,
        string $model,
        float $difficulty,
        int $status = LOCAL_CATQUIZ_STATUS_UPDATED_MANUALLY
    ): int {
        global $DB;
        $id = (int) $DB->insert_record('local_catquiz_itemparams', (object) [
            'itemid' => $itemid, 'componentid' => $questionid, 'componentname' => 'question', 'contextid' => $contextid,
            'model' => $model, 'difficulty' => $difficulty, 'discrimination' => 1.0, 'guessing' => 0, 'json' => '{}',
            'status' => $status, 'usable' => 1, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        if ($itemid) {
            $DB->set_field('local_catquiz_items', 'activeparamid', $id, ['id' => $itemid]);
        }
        return $id;
    }

    /**
     * The item of a scale in a context, with its active parameter and all its parameters there.
     *
     * @param int $scaleid
     * @param int $contextid
     * @return stdClass item, active (the active parameter) and params (model => difficulty)
     */
    private function in_context(int $scaleid, int $contextid): stdClass {
        global $DB;
        $item = $DB->get_record('local_catquiz_items', [
            'catscaleid' => $scaleid, 'componentid' => $this->question, 'contextid' => $contextid,
        ], '*', MUST_EXIST);
        $params = [];
        foreach ($DB->get_records('local_catquiz_itemparams', ['itemid' => $item->id, 'contextid' => $contextid]) as $p) {
            $params[$p->model] = (float) $p->difficulty;
        }
        $active = $DB->get_record('local_catquiz_itemparams', ['id' => $item->activeparamid], '*', MUST_EXIST);
        $this->assertSame((int) $item->id, (int) $active->itemid, 'The active parameter is one of the item.');
        $this->assertSame($contextid, (int) $active->contextid);
        return (object) ['item' => $item, 'active' => $active, 'params' => $params];
    }

    /**
     * Not in use: the items are moved, each with its own parameter.
     */
    public function test_each_item_keeps_its_own_parameters(): void {
        (new catquiz())->create_items_in_new_context($this->new, $this->old);

        $a = $this->in_context($this->scales['A'], $this->new);
        $b = $this->in_context($this->scales['B'], $this->new);
        $this->assertSame($this->items['A'], (int) $a->item->id, 'Moved, not copied.');
        $this->assertSame($this->items['B'], (int) $b->item->id);
        $this->assertSame(['raschbirnbaum' => 1.0], $a->params);
        $this->assertSame(['raschbirnbaum' => 2.0], $b->params);
    }

    /**
     * In use by a test: the old items and parameters stay as they were, the new context gets copies.
     */
    public function test_a_context_in_use_keeps_its_items(): void {
        global $DB;
        $DB->insert_record('local_catquiz_tests', (object) [
            'componentid' => 1, 'component' => 'mod_adaptivequiz', 'catscaleid' => $this->root, 'contextid' => $this->old,
            'name' => 'Running', 'json' => '{}', 'status' => 1, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $before = $DB->get_records('local_catquiz_itemparams', ['contextid' => $this->old]);

        (new catquiz())->create_items_in_new_context($this->new, $this->old);

        $this->assertEquals($before, $DB->get_records('local_catquiz_itemparams', ['contextid' => $this->old]));
        $this->assertSame(['raschbirnbaum' => 1.0], $this->in_context($this->scales['A'], $this->old)->params);
        $a = $this->in_context($this->scales['A'], $this->new);
        $b = $this->in_context($this->scales['B'], $this->new);
        $this->assertNotSame($this->items['A'], (int) $a->item->id, 'A copy.');
        $this->assertSame(['raschbirnbaum' => 1.0], $a->params);
        $this->assertSame(['raschbirnbaum' => 2.0], $b->params);
    }

    /**
     * Different models: each item keeps the models it has.
     */
    public function test_different_models(): void {
        $this->param($this->items['A'], $this->question, $this->old, 'rasch', 3.0, LOCAL_CATQUIZ_STATUS_CALCULATED);

        (new catquiz())->create_items_in_new_context($this->new, $this->old);

        $a = $this->in_context($this->scales['A'], $this->new);
        $this->assertEquals(['raschbirnbaum' => 1.0, 'rasch' => 3.0], $a->params);
        $this->assertSame('raschbirnbaum', $a->active->model, 'The higher status is active.');
        $this->assertSame(['raschbirnbaum' => 2.0], $this->in_context($this->scales['B'], $this->new)->params);
    }

    /**
     * Parameters in the new context already: one of an item wins over its old one; one fetched without an item
     * (from a hub) is the question's - every item of it gets a copy of its own.
     */
    public function test_parameters_already_in_the_new_context(): void {
        $this->param(0, $this->question, $this->new, 'raschbirnbaum', 9.0);
        $this->param(0, $this->question, $this->new, 'rasch', 7.0);
        $this->param($this->items['B'], $this->question, $this->new, 'rasch', 8.0, LOCAL_CATQUIZ_STATUS_CONFIRMED_MANUALLY);

        (new catquiz())->create_items_in_new_context($this->new, $this->old);

        $a = $this->in_context($this->scales['A'], $this->new);
        $b = $this->in_context($this->scales['B'], $this->new);
        $this->assertEquals(['raschbirnbaum' => 9.0, 'rasch' => 7.0], $a->params);
        $this->assertEquals(['raschbirnbaum' => 9.0, 'rasch' => 8.0], $b->params, "B's own rasch parameter wins.");
        $this->assertSame('rasch', $b->active->model, 'Its status is the highest.');
        $this->assertNotSame((int) $a->active->id, (int) $b->active->id);
    }

    /**
     * Calling it again changes nothing.
     */
    public function test_it_is_idempotent(): void {
        global $DB;
        $repo = new catquiz();
        $repo->create_items_in_new_context($this->new, $this->old);
        $items = $DB->get_records('local_catquiz_items', null, 'id');
        $params = $DB->get_records('local_catquiz_itemparams', null, 'id', 'id, itemid, contextid, model, difficulty, status');

        $repo->create_items_in_new_context($this->new, $this->old);

        $this->assertEquals($items, $DB->get_records('local_catquiz_items', null, 'id'));
        $this->assertEquals(
            $params,
            $DB->get_records('local_catquiz_itemparams', null, 'id', 'id, itemid, contextid, model, difficulty, status')
        );
    }

    /**
     * An error before the end leaves nothing: neither copied parameters nor moved items.
     */
    public function test_an_error_leaves_nothing_behind(): void {
        global $DB;
        // The transaction of the method has to be the outermost one - inside the test transaction its rollback
        // would only be done at the end of the test.
        $this->preventResetByRollback();
        $items = $DB->get_records('local_catquiz_items', null, 'id');
        $params = $DB->get_records('local_catquiz_itemparams', null, 'id');
        catquiz::$beforecontextcommit = function () {
            throw new \coding_exception('Simulated failure');
        };
        try {
            (new catquiz())->create_items_in_new_context($this->new, $this->old);
            $this->fail('The error was swallowed.');
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('Simulated failure', $e->getMessage());
        } finally {
            catquiz::$beforecontextcommit = null;
        }
        $this->assertEquals($items, $DB->get_records('local_catquiz_items', null, 'id'));
        $this->assertEquals($params, $DB->get_records('local_catquiz_itemparams', null, 'id'));
    }

    /**
     * A context whose id lies between the old and the new one is not touched - not even with the same question.
     */
    public function test_contexts_in_between_are_not_touched(): void {
        global $DB;
        $other = $this->scale('Other', 0, $this->between);
        $item = $this->item($other, $this->question, $this->between);
        $this->param($item, $this->question, $this->between, 'raschbirnbaum', 5.0);
        $before = $DB->get_record('local_catquiz_items', ['id' => $item]);

        (new catquiz())->create_items_in_new_context($this->new, $this->old);

        $this->assertEquals($before, $DB->get_record('local_catquiz_items', ['id' => $item]));
        $this->assertSame(['raschbirnbaum' => 1.0], $this->in_context($this->scales['A'], $this->new)->params);
    }

    /**
     * The productive path: the root scale is given the new context.
     */
    public function test_through_the_scale_update(): void {
        dataapi::update_catscale((object) ['id' => $this->root, 'contextid' => $this->new]);

        $this->assertSame(['raschbirnbaum' => 1.0], $this->in_context($this->scales['A'], $this->new)->params);
        $this->assertSame(['raschbirnbaum' => 2.0], $this->in_context($this->scales['B'], $this->new)->params);
    }
}
