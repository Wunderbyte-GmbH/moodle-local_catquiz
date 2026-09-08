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
 * Issue #24: the scale tree is built without N+1 queries.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;
use local_catquiz\data\catscale_structure;
use local_catquiz\output\catscales;

/**
 * Verifies the scale tree construction.
 *
 * The tree used to ask the database for the subscription state of every single
 * scale and to walk the whole scale list once per level - O(n) queries and O(n^2)
 * work, both growing with the number of scales.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\output\catscales::build_tree
 * @covers     \local_catquiz\subscription::return_subscribed_itemids
 */
final class catscale_tree_test extends advanced_testcase {
    /**
     * Builds a list of scale structures shaped like a tree.
     *
     * @param int $roots
     * @param int $childrenperroot
     * @return array
     */
    private function make_scales(int $roots, int $childrenperroot): array {
        $elements = [];
        $id = 1;
        for ($r = 0; $r < $roots; $r++) {
            $rootid = $id++;
            $elements[] = new catscale_structure([
                'id' => $rootid,
                'name' => "Root $r",
                'parentid' => 0,
            ]);
            for ($c = 0; $c < $childrenperroot; $c++) {
                $childid = $id++;
                $elements[] = new catscale_structure([
                    'id' => $childid,
                    'name' => "Root $r child $c",
                    'parentid' => $rootid,
                ]);
                // One grandchild, so the tree really has three levels.
                $elements[] = new catscale_structure([
                    'id' => $id++,
                    'name' => "Root $r child $c leaf",
                    'parentid' => $childid,
                ]);
            }
        }

        return $elements;
    }

    /**
     * Builds the tree and returns [tree, number of queries used].
     *
     * @param array $elements
     * @return array
     */
    private function build_counted(array $elements): array {
        global $DB;

        $catscaleid = 0;
        $detailview = 0;
        $contextid = 0;
        $renderer = new catscales($catscaleid, $detailview, $contextid);

        $before = $DB->perf_get_reads() + $DB->perf_get_writes();
        $tree = $renderer->build_tree($elements);
        $after = $DB->perf_get_reads() + $DB->perf_get_writes();

        return [$tree, $after - $before];
    }

    /**
     * The query count does not grow with the number of scales.
     *
     * @return void
     */
    public function test_query_count_stays_constant(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [, $smallqueries] = $this->build_counted($this->make_scales(2, 2));
        [, $largequeries] = $this->build_counted($this->make_scales(20, 5));

        $this->assertEquals(
            $smallqueries,
            $largequeries,
            'Building a much larger tree must not cost more queries.'
        );
        // The subscriptions are fetched once; anything above that would be N+1 again.
        $this->assertLessThanOrEqual(1, $largequeries);
    }

    /**
     * The tree keeps its structure and order.
     *
     * @return void
     */
    public function test_tree_structure_and_order(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$tree] = $this->build_counted($this->make_scales(2, 2));

        $this->assertCount(2, $tree, 'Two roots.');
        $this->assertEquals('Root 0', $tree[0]['name']);
        $this->assertEquals('Root 1', $tree[1]['name']);

        $this->assertCount(2, $tree[0]['children'], 'Two children per root.');
        $this->assertEquals('Root 0 child 0', $tree[0]['children'][0]['name']);
        $this->assertEquals('Root 0 child 1', $tree[0]['children'][1]['name']);

        $this->assertCount(1, $tree[0]['children'][0]['children'], 'One grandchild.');
        $this->assertEquals('Root 0 child 0 leaf', $tree[0]['children'][0]['children'][0]['name']);

        // Leaves carry an empty array, not null: the template loops over children.
        $this->assertSame([], $tree[0]['children'][0]['children'][0]['children']);
    }

    /**
     * The subscription state matches what the per-scale lookup would return.
     *
     * @return void
     */
    public function test_subscription_state_matches_single_lookup(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $elements = $this->make_scales(2, 1);
        $subscribedid = (int) $elements[1]->id;

        $DB->insert_record('local_catquiz_subscriptions', (object) [
            'userid' => $USER->id,
            'itemid' => $subscribedid,
            'area' => 'catscale',
            'status' => LOCAL_CATQUIZ_STATUS_SUBSCRIPTION_BOOKED,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        [$tree] = $this->build_counted($elements);

        $flat = [];
        $walk = function (array $branch) use (&$walk, &$flat) {
            foreach ($branch as $node) {
                $flat[(int) $node['id']] = $node['subscribed'];
                $walk($node['children']);
            }
        };
        $walk($tree);

        foreach ($flat as $id => $state) {
            $this->assertSame(
                (bool) subscription::return_subscription_state($USER->id, 'catscale', $id),
                $state,
                "Scale $id must keep the state the single lookup reports."
            );
        }
        $this->assertTrue($flat[$subscribedid], 'The subscribed scale must be marked.');
        $this->assertContains(false, $flat, 'Some scale must be unsubscribed, or this proves nothing.');
    }

    /**
     * Question counts come from one grouped query, not one per scale.
     *
     * @return void
     */
    public function test_question_counts_use_one_query(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Two scales with a different number of items, one scale with none.
        foreach ([[1, 3], [2, 1]] as [$scaleid, $items]) {
            for ($i = 0; $i < $items; $i++) {
                $DB->insert_record('local_catquiz_items', (object) [
                    'componentid' => 1000 + ($scaleid * 100) + $i,
                    'componentname' => 'question',
                    'catscaleid' => $scaleid,
                    'contextid' => 1,
                    'status' => 0,
                ]);
            }
        }

        $before = $DB->perf_get_reads() + $DB->perf_get_writes();
        $counts = catquiz::get_number_of_questions_per_scale();
        $after = $DB->perf_get_reads() + $DB->perf_get_writes();

        $this->assertEquals(1, $after - $before, 'All counts must come from a single query.');
        $this->assertEquals(3, $counts[1]);
        $this->assertEquals(1, $counts[2]);
        $this->assertArrayNotHasKey(3, $counts, 'A scale without items simply has no row.');
    }

    /**
     * The grouped counts match what the per-scale query returns.
     *
     * @return void
     */
    public function test_grouped_counts_match_the_single_query(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        foreach ([[7, 2], [8, 5]] as [$scaleid, $items]) {
            for ($i = 0; $i < $items; $i++) {
                $DB->insert_record('local_catquiz_items', (object) [
                    'componentid' => 2000 + ($scaleid * 100) + $i,
                    'componentname' => 'question',
                    'catscaleid' => $scaleid,
                    'contextid' => 1,
                    'status' => 0,
                ]);
            }
        }

        $counts = catquiz::get_number_of_questions_per_scale();

        foreach ([7, 8, 9] as $scaleid) {
            [$sql, $params] = catquiz::get_sql_for_number_of_questions_in_scale($scaleid);
            $this->assertEquals(
                $DB->count_records_sql($sql, $params),
                $counts[$scaleid] ?? 0,
                "Scale $scaleid must keep the count the single query reports."
            );
        }
    }
}
