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

namespace local_catquiz\local\pool;

use advanced_testcase;
use local_catquiz\catscale;
use local_catquiz\local\integrity\item_param_integrity;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\teststrategy\context\loader\pilotquestions_loader;
use local_catquiz\teststrategy\feedback_helper;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * The runtime item pool of a CAT context holds the items of that context, and only those (issue #157, T1-T9 at the
 * level of the pool, with the invariants of the issue).
 *
 * Before: the query read the items of every context of a question (the context filter lay on the parameters only, so
 * items without parameters came from any context), and one row per question was kept by the lowest item id. In
 * production (root 22, context 1605) 528 calibrated questions came out as pilot rows of contexts 1601 and 1603.
 *
 * Every case asserts the invariants on the pool rows: the item's context is the context asked for; an active
 * reference resolves to parameters of this item, in this context, for this question; a pilot has no reference; one
 * row per question; scale, parameters and pilot status of a row are those of its item.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\local\integrity\item_param_integrity
 * @covers \local_catquiz\catquiz::return_sql_for_catscalequestions
 * @covers \local_catquiz\catquiz::get_itemparams
 * @covers \local_catquiz\catscale::get_testitems
 * @covers \local_catquiz\catscale::one_row_per_question
 */
final class context_item_pool_test extends advanced_testcase {
    /** @var int[] CAT contexts by name. */
    private array $contexts = [];

    /** @var int Root scale. */
    private int $root;

    /** @var int[] Subscales S1, S2, S3. */
    private array $subs = [];

    /** @var \stdClass Question category. */
    private \stdClass $category;

    /**
     * Contexts A, B, C; root R with S1, S2, S3.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $now = time();
        foreach (['A', 'B', 'C'] as $name) {
            $this->contexts[$name] = (int) $DB->insert_record('local_catquiz_catcontext', (object) [
                'name' => $name, 'description' => '', 'descriptionformat' => FORMAT_HTML, 'starttimestamp' => 0,
                'endtimestamp' => $now + 10000, 'timecreated' => $now, 'timemodified' => $now, 'usermodified' => 0,
            ]);
        }
        $this->root = (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'parentid' => 0, 'name' => 'R', 'contextid' => $this->contexts['B'], 'minscalevalue' => -5,
            'maxscalevalue' => 5, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        foreach (['S1', 'S2', 'S3'] as $name) {
            $this->subs[$name] = (int) $DB->insert_record('local_catquiz_catscales', (object) [
                'parentid' => $this->root, 'name' => $name, 'minscalevalue' => -5, 'maxscalevalue' => 5,
                'timecreated' => $now, 'timemodified' => $now,
            ]);
        }
        $this->category = $this->getDataGenerator()->get_plugin_generator('core_question')->create_question_category();
        \cache_helper::purge_all();
    }

    /**
     * A question of the bank.
     *
     * @return int
     */
    private function question(): int {
        return (int) $this->getDataGenerator()->get_plugin_generator('core_question')
            ->create_question('truefalse', null, ['category' => $this->category->id])->id;
    }

    /**
     * An item; with parameters, those are active unless a broken reference is asked for.
     *
     * @param int $questionid
     * @param string $scale S1, S2, S3
     * @param string $context A, B, C
     * @param array|null $param null: pilot; otherwise difficulty, model, and optionally 'broken' => missing |
     *     otheritem | othercontext | otherquestion | noquestion (componentid 0, as older rows and the seed tools write it).
     * @param int $status
     * @return int[] [itemid, paramid]
     */
    private function item(
        int $questionid,
        string $scale,
        string $context,
        ?array $param,
        int $status = LOCAL_CATQUIZ_TESTITEM_STATUS_ACTIVE
    ): array {
        global $DB;
        $itemid = (int) $DB->insert_record('local_catquiz_items', (object) [
            'componentid' => $questionid, 'componentname' => 'question', 'catscaleid' => $this->subs[$scale],
            'contextid' => $this->contexts[$context], 'status' => $status,
        ]);
        if ($param === null) {
            return [$itemid, 0];
        }
        $broken = $param['broken'] ?? null;
        $record = (object) [
            'itemid' => $broken === 'otheritem' ? $itemid + 100000 : $itemid,
            'componentid' => $broken === 'otherquestion' ? $questionid + 100000 : ($broken === 'noquestion' ? 0 : $questionid),
            'componentname' => 'question',
            'contextid' => $this->contexts[$broken === 'othercontext' ? ($context === 'A' ? 'B' : 'A') : $context],
            'model' => $param['model'] ?? 'raschbirnbaum', 'difficulty' => $param['difficulty'],
            'discrimination' => $param['discrimination'] ?? 1.2, 'guessing' => 0, 'json' => '{}',
            'status' => LOCAL_CATQUIZ_STATUS_UPDATED_MANUALLY, 'usable' => 1, 'timecreated' => time(),
            'timemodified' => time(),
        ];
        $paramid = (int) $DB->insert_record('local_catquiz_itemparams', $record);
        $DB->set_field(
            'local_catquiz_items',
            'activeparamid',
            $broken === 'missing' ? $paramid + 100000 : $paramid,
            ['id' => $itemid]
        );
        return [$itemid, $paramid];
    }

    /**
     * The runtime pool of a context, as the tests read it.
     *
     * @param string $context
     * @param array $selected Selected subscales (names).
     * @return array
     */
    private function pool(string $context, array $selected = []): array {
        return (new catscale($this->root))->get_testitems(
            $this->contexts[$context],
            true,
            null,
            array_map(fn($s) => $this->subs[$s], $selected)
        );
    }

    /**
     * The invariants of issue #157 on every row of a pool.
     *
     * @param array $pool
     * @param string $context
     * @return void
     */
    private function assert_invariants(array $pool, string $context): void {
        global $DB;
        $contextid = $this->contexts[$context];
        foreach ($pool as $questionid => $row) {
            $item = $DB->get_record('local_catquiz_items', ['id' => $row->itemid], '*', MUST_EXIST);
            $this->assertSame($contextid, (int) $item->contextid, 'Context isolation: the item of the context asked for.');
            $this->assertSame((int) $questionid, (int) $item->componentid, 'One row per question, of its own item.');
            $this->assertSame((int) $item->catscaleid, (int) $row->catscaleid, 'The scale of the item.');
            if (empty($item->activeparamid)) {
                $this->assertEmpty($row->model, 'A pilot has no parameters.');
                continue;
            }
            $param = $DB->get_record('local_catquiz_itemparams', ['id' => $item->activeparamid], '*', MUST_EXIST);
            $this->assertSame((int) $item->id, (int) $param->itemid);
            $this->assertSame($contextid, (int) $param->contextid);
            $this->assertContains((int) $param->componentid, [0, (int) $item->componentid], 'No other question.');
            $this->assertSame($param->model, $row->model);
            $this->assertEqualsWithDelta((float) $param->difficulty, (float) $row->difficulty, 1e-9);
            $this->assertSame($contextid, (int) $row->lcipcontextid);
        }
    }

    /**
     * Whether the selection treats a pool row as a pilot.
     *
     * @param \stdClass $row
     * @return bool
     */
    private function is_pilot(\stdClass $row): bool {
        return (new pilotquestions_loader())->ispilot(clone $row, LOCAL_CATQUIZ_THRESHOLD_DEFAULT);
    }

    /**
     * T1, T2: the item of the context, with its own parameters - whichever context was created first, whatever the
     * order of the ids and whether the cache was warm.
     *
     * @param bool $olderfirst The item of A (pilot) gets the lower id.
     * @dataProvider orders
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('orders')]
    public function test_the_item_of_the_context_with_its_parameters(bool $olderfirst): void {
        $q = $this->question();
        if ($olderfirst) {
            [$aitem] = $this->item($q, 'S1', 'A', null);
            [$bitem, $bparam] = $this->item($q, 'S1', 'B', ['difficulty' => 0.7]);
        } else {
            [$bitem, $bparam] = $this->item($q, 'S1', 'B', ['difficulty' => 0.7]);
            [$aitem] = $this->item($q, 'S1', 'A', null);
        }
        foreach (['cold', 'warm'] as $run) {
            $b = $this->pool('B');
            $this->assertSame([$q], array_keys($b), $run);
            $this->assertSame($bitem, (int) $b[$q]->itemid, $run);
            $this->assertSame($bparam, (int) $b[$q]->activeparamid, $run);
            $this->assertFalse($this->is_pilot($b[$q]), "$run: calibrated in B, no pilot.");
            $this->assert_invariants($b, 'B');
            $a = $this->pool('A');
            $this->assertSame($aitem, (int) $a[$q]->itemid, $run);
            $this->assertTrue($this->is_pilot($a[$q]), "$run: still a pilot in A.");
            $this->assert_invariants($a, 'A');
        }
        // The test information of B counts the question; that of A does not.
        $steps = [-1.0, 0.0, 1.0];
        $this->assertSame(1, (new feedback_helper())->testinfo_for_scale($this->root, $this->contexts['B'], $steps)['computed']);
        $this->assertSame(0, (new feedback_helper())->testinfo_for_scale($this->root, $this->contexts['A'], $steps)['computed']);
    }

    /**
     * Both orders of the ids.
     *
     * @return array
     */
    public static function orders(): array {
        return ['A first (lower id)' => [true], 'B first (lower id)' => [false]];
    }

    /**
     * T3, T4: a pilot of B takes nothing of A's calibration; two calibrations each keep their own parameters - also
     * for the estimation (model_item_param_list) and the Fisher information.
     */
    public function test_each_context_keeps_its_own_calibration(): void {
        $pilotinb = $this->question();
        $this->item($pilotinb, 'S1', 'A', ['difficulty' => -1.0]);
        $this->item($pilotinb, 'S1', 'B', null);
        $both = $this->question();
        [$aitem] = $this->item($both, 'S2', 'A', ['difficulty' => -2.0, 'discrimination' => 0.6]);
        [$bitem] = $this->item($both, 'S2', 'B', ['difficulty' => 1.5, 'discrimination' => 1.8]);

        $b = $this->pool('B');
        $this->assertTrue($this->is_pilot($b[$pilotinb]), 'T3: the pilot of B stays a pilot.');
        $this->assertEmpty($b[$pilotinb]->model);
        $this->assertSame($bitem, (int) $b[$both]->itemid);
        $this->assertEqualsWithDelta(1.5, (float) $b[$both]->difficulty, 1e-9);
        $this->assert_invariants($b, 'B');
        $a = $this->pool('A');
        $this->assertEqualsWithDelta(-1.0, (float) $a[$pilotinb]->difficulty, 1e-9, 'A has its own calibration.');
        $this->assertSame($aitem, (int) $a[$both]->itemid);
        $this->assertEqualsWithDelta(-2.0, (float) $a[$both]->difficulty, 1e-9);
        $this->assert_invariants($a, 'A');

        // The estimation reads the same parameters per context.
        $listb = model_item_param_list::load_from_db($this->contexts['B'], null, [$this->root, ...array_values($this->subs)]);
        $this->assertTrue($listb->offsetExists($both));
        $this->assertFalse($listb->offsetExists($pilotinb), 'No parameters of A for the pilot of B.');
        $this->assertEqualsWithDelta(1.5, $listb[$both]->get_difficulty(), 1e-9);
        $lista = model_item_param_list::load_from_db($this->contexts['A'], null, [$this->root, ...array_values($this->subs)]);
        $this->assertEqualsWithDelta(-2.0, $lista[$both]->get_difficulty(), 1e-9);

        // The Fisher information differs measurably: at 1.5 the item of B is informative, that of A is not.
        $info = fn(string $ctx) => array_values(
            (new feedback_helper())->testinfo_for_scale($this->root, $this->contexts[$ctx], [1.5])['values']
        )[0];
        $this->assertGreaterThan(5 * $info('A'), $info('B'));
    }

    /**
     * T4/T10: the standard error of a scale is computed with the parameters of the context asked for - that of the
     * attempt -, not of the context the scale has now (model_responses::get_items_for_scale()).
     */
    public function test_the_standard_error_reads_the_context_asked_for(): void {
        $q = $this->question();
        $this->item($q, 'S1', 'A', ['difficulty' => -2.0]);
        $this->item($q, 'S1', 'B', ['difficulty' => 1.5]);
        $responses = \local_catquiz\local\model\model_responses::create_from_array(
            [2 => ['component' => [$q => ['fraction' => 1.0]]]],
            $this->root
        );
        // The scale is attached to B now; the attempt plays in A.
        $items = $responses->get_items_for_scale($this->root, $this->contexts['A']);
        $this->assertTrue($items->offsetExists($q));
        $this->assertEqualsWithDelta(-2.0, $items[$q]->get_difficulty(), 1e-9);
        $items = $responses->get_items_for_scale($this->root, $this->contexts['B']);
        $this->assertEqualsWithDelta(1.5, $items[$q]->get_difficulty(), 1e-9);
    }

    /**
     * Re-audit 02.11.2026 (traceability, gap 1): the standard error and the start check read the ACTIVE parameters,
     * as the pool and the estimate do - not every parameter of a model in the context. A second, not active parameter
     * of an item and the parameter of an item whose reference does not resolve stay out.
     */
    public function test_the_standard_error_reads_the_active_parameters_only(): void {
        global $DB;
        $active = $this->question();
        [$itemid] = $this->item($active, 'S1', 'A', ['difficulty' => -2.0]);
        // A second parameter of the same item under another model, not active.
        $DB->insert_record('local_catquiz_itemparams', (object) [
            'itemid' => $itemid, 'componentid' => $active, 'componentname' => 'question', 'contextid' => $this->contexts['A'],
            'model' => 'rasch', 'difficulty' => 3.0, 'discrimination' => 1.0, 'guessing' => 0, 'json' => '{}',
            'status' => LOCAL_CATQUIZ_STATUS_UPDATED_MANUALLY, 'usable' => 1, 'timecreated' => time() + 10,
            'timemodified' => time() + 10,
        ]);
        $broken = $this->question();
        $this->item($broken, 'S1', 'A', ['difficulty' => 0.5, 'broken' => 'missing']);
        $responses = \local_catquiz\local\model\model_responses::create_from_array(
            [2 => ['component' => [$active => ['fraction' => 1.0], $broken => ['fraction' => 0.0]]]],
            $this->root
        );
        $items = $responses->get_items_for_scale($this->root, $this->contexts['A']);
        $this->assertTrue($items->offsetExists($active));
        $this->assertEqualsWithDelta(-2.0, $items[$active]->get_difficulty(), 1e-9, 'The active parameter.');
        $this->assertFalse($items->offsetExists($broken), 'No parameter behind a reference that does not resolve.');
    }

    /**
     * T5: a reference that does not resolve is neither a pilot nor another calibration: the item leaves the pool and is
     * reported, by kind.
     *
     * @param string $broken
     * @dataProvider broken_references
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('broken_references')]
    public function test_a_broken_reference_is_reported_not_piloted(string $broken): void {
        $sink = $this->redirectEvents();
        $q = $this->question();
        $this->item($q, 'S1', 'A', ['difficulty' => 0.3]);
        [$bitem] = $this->item($q, 'S1', 'B', ['difficulty' => 0.9, 'broken' => $broken]);
        $fine = $this->question();
        $this->item($fine, 'S1', 'B', ['difficulty' => 0.1]);

        $b = $this->pool('B');
        $this->assertSame([$fine], array_keys($b), 'The item with the broken reference is out - not a pilot, not A.');
        $this->assert_invariants($b, 'B');
        $check = item_param_integrity::check($this->contexts['B']);
        $this->assertSame(1, $check['count']);
        $this->assertSame(1, $check['bykind'][$broken]);
        $this->assertSame([$bitem], $check['sample'][$broken]);
        $events = array_values(array_filter(
            $sink->get_events(),
            fn($e) => $e instanceof \local_catquiz\event\item_param_integrity_failed
        ));
        $this->assertCount(1, $events, 'Reported once, when the pool was built.');
        $this->assertSame($this->contexts['B'], $events[0]->other['catcontextid']);
        // The cached pool is not built again: no second report.
        $this->pool('B');
        $this->assertCount(1, array_filter(
            $sink->get_events(),
            fn($e) => $e instanceof \local_catquiz\event\item_param_integrity_failed
        ));
        // The estimation does not take the parameters either.
        $list = model_item_param_list::load_from_db($this->contexts['B'], null, [$this->root, ...array_values($this->subs)]);
        $this->assertFalse($list->offsetExists($q));
        $this->assertCount(1, $list, 'Only the parameters of the item that resolves - none of another question.');
        $this->assertTrue($list->offsetExists($fine));
        // The manager list shows the item, marked.
        [$select, $from, $where, , $params] = \local_catquiz\catquiz::return_sql_for_catscalequestions(
            [$this->root, ...array_values($this->subs)],
            $this->contexts['B'],
            []
        );
        $rows = $GLOBALS['DB']->get_records_sql("SELECT $select FROM $from WHERE $where", $params);
        $marked = array_values(array_filter($rows, fn($r) => (int) $r->itemid === $bitem));
        $this->assertCount(1, $marked);
        $this->assertSame(1, (int) $marked[0]->invalidparamref);
    }

    /**
     * The kinds of broken references.
     *
     * @return array
     */
    public static function broken_references(): array {
        return [
            'missing' => [item_param_integrity::MISSING],
            'of another item' => [item_param_integrity::OTHER_ITEM],
            'in another context' => [item_param_integrity::OTHER_CONTEXT],
            'of another question' => [item_param_integrity::OTHER_QUESTION],
        ];
    }

    /**
     * T6: a question in two sibling scales of the context: one row - no question twice in the usage -, no leak, the
     * scale and the parameters of one and the same item; the calibrated item before a pilot of the other scale.
     */
    public function test_a_question_in_two_sibling_scales(): void {
        $q = $this->question();
        $this->item($q, 'S1', 'A', null);
        [$s1item] = $this->item($q, 'S1', 'B', ['difficulty' => 0.2]);
        [$s2item] = $this->item($q, 'S2', 'B', ['difficulty' => 0.8]);
        $b = $this->pool('B');
        $this->assertSame([$q], array_keys($b));
        $this->assertContains((int) $b[$q]->itemid, [$s1item, $s2item]);
        $this->assert_invariants($b, 'B');

        $p = $this->question();
        $this->item($p, 'S1', 'B', null);
        [$calibrated] = $this->item($p, 'S2', 'B', ['difficulty' => 0.4]);
        \cache_helper::purge_by_event('changesintestitems');
        $b = $this->pool('B');
        $this->assertSame($calibrated, (int) $b[$p]->itemid, 'The calibrated item of the context before the pilot.');
        $this->assert_invariants($b, 'B');
    }

    /**
     * T7: the selected subscales of a test bound the pool - an item of a scale not selected adds nothing.
     */
    public function test_a_scale_not_selected_does_not_count(): void {
        $q = $this->question();
        [$s1item] = $this->item($q, 'S1', 'B', ['difficulty' => 0.2]);
        $this->item($q, 'S3', 'B', ['difficulty' => -3.0]);
        $only = $this->question();
        $this->item($only, 'S3', 'B', ['difficulty' => 1.0]);
        $b = $this->pool('B', ['S1', 'S2']);
        $this->assertSame([$q], array_keys($b));
        $this->assertSame($s1item, (int) $b[$q]->itemid);
        $this->assertSame($this->subs['S1'], (int) $b[$q]->catscaleid);
        $this->assert_invariants($b, 'B');
    }

    /**
     * T8: real pilots stay pilots; a hidden question, a deactivated item, a model not installed stay out - and no item of
     * another context stands in for them.
     */
    public function test_pilots_and_exclusions_without_stand_ins(): void {
        global $DB;
        $pilot = $this->question();
        $this->item($pilot, 'S1', 'B', null);
        $hidden = $this->question();
        $this->item($hidden, 'S1', 'A', ['difficulty' => 0.1]);
        $this->item($hidden, 'S1', 'B', ['difficulty' => 0.1]);
        $DB->set_field(
            'question_versions',
            'status',
            \core_question\local\bank\question_version_status::QUESTION_STATUS_HIDDEN,
            ['questionid' => $hidden]
        );
        $inactive = $this->question();
        $this->item($inactive, 'S1', 'A', ['difficulty' => 0.1]);
        $this->item($inactive, 'S1', 'B', ['difficulty' => 0.1], LOCAL_CATQUIZ_TESTITEM_STATUS_INACTIVE);
        $nomodel = $this->question();
        $this->item($nomodel, 'S1', 'A', null);
        $this->item($nomodel, 'S1', 'B', ['difficulty' => 0.1, 'model' => 'notinstalled']);
        $b = $this->pool('B');
        $this->assertSame([$pilot], array_keys($b));
        $this->assertTrue($this->is_pilot($b[$pilot]));
        $this->assert_invariants($b, 'B');
    }

    /**
     * CI 02.11.2026: parameters without a question (componentid 0) resolve through their item, and an item whose
     * activeparamid is 0 instead of null is a pilot. Both are written by older code and by the seed tools; the first
     * version of the #157 rule took them for broken references and dropped calibrated items and pilots from the pool.
     */
    public function test_a_row_without_question_or_active_zero_is_not_broken(): void {
        global $DB;
        $calibrated = $this->question();
        $this->item($calibrated, 'S1', 'B', ['difficulty' => 0.7, 'broken' => 'noquestion']);
        $pilot = $this->question();
        [$pilotitem] = $this->item($pilot, 'S1', 'B', null);
        $DB->set_field('local_catquiz_items', 'activeparamid', 0, ['id' => $pilotitem]);
        $b = $this->pool('B');
        $this->assertEqualsCanonicalizing([$calibrated, $pilot], array_keys($b));
        $this->assertFalse($this->is_pilot($b[$calibrated]), 'Its parameters count.');
        $this->assertEqualsWithDelta(0.7, (float) $b[$calibrated]->difficulty, 1e-9);
        $this->assertTrue($this->is_pilot($b[$pilot]));
        $this->assert_invariants($b, 'B');
        $this->assertSame(0, item_param_integrity::check($this->contexts['B'])['count']);
    }

    /**
     * T9: two tests of the same root scale in two contexts of one course: disjoint pools under keys of their own,
     * cold, warm and after a purge of the pools.
     */
    public function test_two_contexts_have_disjoint_pools(): void {
        $qs = [];
        for ($i = 0; $i < 4; $i++) {
            $qs[$i] = $this->question();
            $this->item($qs[$i], 'S1', 'A', ['difficulty' => -1.0 + $i]);
            $this->item($qs[$i], 'S1', 'C', ['difficulty' => 1.0 - $i]);
        }
        $this->assertNotSame(
            catscale::pool_key($this->contexts['A'], true, $this->root, []),
            catscale::pool_key($this->contexts['C'], true, $this->root, [])
        );
        $first = [];
        foreach (['cold', 'warm', 'purged'] as $run) {
            if ($run === 'purged') {
                \cache::make('local_catquiz', 'runtimepool')->purge();
            }
            $a = $this->pool('A');
            $c = $this->pool('C');
            $this->assert_invariants($a, 'A');
            $this->assert_invariants($c, 'C');
            $itemsa = array_map(fn($r) => (int) $r->itemid, $a);
            $itemsc = array_map(fn($r) => (int) $r->itemid, $c);
            $this->assertEmpty(array_intersect($itemsa, $itemsc), "$run: disjoint items.");
            $this->assertEqualsWithDelta(-1.0, (float) $a[$qs[0]]->difficulty, 1e-9);
            $this->assertEqualsWithDelta(1.0, (float) $c[$qs[0]]->difficulty, 1e-9);
            $state = [$itemsa, $itemsc];
            $first = $first ?: $state;
            $this->assertSame($first, $state, "$run: the same pools.");
        }
    }

    /**
     * Metamorphic: a further context with lower ids, other parameters or none, in any order of insertion, leaves the
     * pool, the parameters and the test information of the context asked for as they are.
     */
    public function test_other_contexts_do_not_change_the_pool(): void {
        global $DB;
        $qs = [];
        for ($i = 0; $i < 6; $i++) {
            $qs[] = $this->question();
        }
        $build = function (array $order) use ($qs) {
            foreach ($order as [$i, $context, $param]) {
                $this->item($qs[$i], 'S' . (1 + $i % 2), $context, $param);
            }
        };
        $target = [];
        foreach ($qs as $i => $q) {
            $target[] = [$i, 'B', $i === 5 ? null : ['difficulty' => -1.0 + 0.4 * $i]];
        }
        $foreign = [];
        foreach ($qs as $i => $q) {
            $foreign[] = [$i, 'A', $i % 2 ? null : ['difficulty' => 2.0 - $i]];
            $foreign[] = [$i, 'C', ['difficulty' => -2.5]];
        }
        $snapshot = function () {
            $pool = $this->pool('B');
            $this->assert_invariants($pool, 'B');
            $rows = [];
            foreach ($pool as $q => $row) {
                $rows[$q] = [(int) $row->catscaleid, (string) $row->model, round((float) $row->difficulty, 6),
                    $this->is_pilot($row)];
            }
            ksort($rows);
            $info = (new feedback_helper())->testinfo_for_scale($this->root, $this->contexts['B'], [-1.0, 0.0, 1.0]);
            return [$rows, $info['computed'], array_map(fn($v) => round($v, 9), $info['values'] ?? [])];
        };

        $build($target);
        $alone = $snapshot();
        $this->assertCount(6, $alone[0]);
        $this->assertSame(5, $alone[1]);
        $orders = [
            'foreign first' => array_merge($foreign, $target),
            'foreign last' => array_merge($target, $foreign),
            'interleaved' => array_merge(...array_map(null, $foreign, array_pad($target, count($foreign), null))),
        ];
        foreach ($orders as $name => $order) {
            $DB->delete_records('local_catquiz_items');
            $DB->delete_records('local_catquiz_itemparams');
            \cache::make('local_catquiz', 'runtimepool')->purge();
            \cache::make('local_catquiz', 'catquiz_item_params')->purge();
            $build(array_values(array_filter($order)));
            $this->assertSame($alone, $snapshot(), $name);
        }
    }
}
