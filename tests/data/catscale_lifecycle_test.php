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
use local_catquiz\testenvironment;

/**
 * Creating, moving and deleting scales: caches follow at once (issue #52), a root scale gets its context in
 * one step (issue #81).
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\data\dataapi
 * @covers \local_catquiz\testenvironment::get_active_scales
 */
final class catscale_lifecycle_test extends advanced_testcase {
    /**
     * Admin, and a clean state.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * A scale through the API.
     *
     * @param string $name
     * @param int $parentid
     * @param int|null $contextid
     * @return int
     */
    private function create(string $name, int $parentid = 0, ?int $contextid = null): int {
        return dataapi::create_catscale(new catscale_structure([
            'name' => $name, 'parentid' => $parentid, 'contextid' => $contextid,
            'timecreated' => time(), 'timemodified' => time(),
        ]));
    }

    /**
     * Deleted after the caches were filled: no list knows the scale any more, without a purge.
     */
    public function test_deleted_scale_leaves_every_list(): void {
        $root = $this->create('Lifecycle root');
        $child = $this->create('Lifecycle child', $root);
        // Fill the caches.
        $this->assertArrayHasKey($child, dataapi::get_all_catscales());
        $this->assertNotEmpty(dataapi::get_catscales_by_parent($root));
        dataapi::get_catscale_and_children($root, true);

        $this->assertTrue(dataapi::delete_catscale($child)['success']);

        $this->assertArrayNotHasKey($child, dataapi::get_all_catscales());
        $this->assertEmpty(dataapi::get_catscales_by_parent($root));
        $ids = array_map(fn($s) => (int) (is_array($s) ? $s['id'] : $s->id), dataapi::get_catscale_and_children($root, true));
        $this->assertNotContains($child, $ids);
    }

    /**
     * A test's active scales drop a deleted scale - also when the stored settings still name it.
     */
    public function test_active_scales_of_a_test_drop_a_deleted_scale(): void {
        global $DB;
        $root = $this->create('Active root');
        $child = $this->create('Active child', $root);
        $testid = $DB->insert_record('local_catquiz_tests', (object) [
            'component' => 'mod_adaptivequiz', 'componentid' => 0, 'name' => 'Active test', 'status' => 1,
            'json' => json_encode(['catquiz_subscalecheckbox_' . $root => '1', 'catquiz_subscalecheckbox_' . $child => '1']),
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $this->assertEqualsCanonicalizing([$root, $child], testenvironment::get_active_scales($testid));

        dataapi::delete_catscale($child);

        $this->assertSame([$root], testenvironment::get_active_scales($testid));
    }

    /**
     * A child the cache does not know yet still keeps its parent from being deleted.
     */
    public function test_parent_with_an_uncached_child_is_kept(): void {
        global $DB;
        $root = $this->create('Kept root');
        dataapi::get_all_catscales();
        $DB->insert_record('local_catquiz_catscales', (object) [
            'name' => 'Written past the API', 'parentid' => $root, 'timecreated' => time(), 'timemodified' => time(),
        ]);

        $this->assertFalse(dataapi::delete_catscale($root)['success']);
        $this->assertTrue($DB->record_exists('local_catquiz_catscales', ['id' => $root]));
    }

    /**
     * A scale cannot be moved below itself or one of its subscales.
     */
    public function test_no_cycles(): void {
        $root = $this->create('Cycle root');
        $child = $this->create('Cycle child', $root);
        $grandchild = $this->create('Cycle grandchild', $child);

        foreach ([$root, $grandchild] as $newparent) {
            try {
                dataapi::update_catscale((object) ['id' => $root, 'parentid' => $newparent]);
                $this->fail('Moved below ' . $newparent);
            } catch (\moodle_exception $e) {
                $this->assertSame('cannotmovescaleintoitself', $e->errorcode);
            }
        }
        // Moving to another root is fine.
        $other = $this->create('Cycle other root');
        $this->assertTrue(dataapi::update_catscale((object) ['id' => $child, 'parentid' => $other]));
        $this->assertSame($other, (int) dataapi::get_all_catscales()[$child]->parentid);
    }

    /**
     * A new root scale gets an existing context of its own; a context that does not exist is refused,
     * and then no scale is stored.
     */
    public function test_root_scale_gets_its_context_in_one_step(): void {
        global $DB;
        $root = $this->create('Context root');
        $contextid = (int) $DB->get_field('local_catquiz_catscales', 'contextid', ['id' => $root]);
        $this->assertGreaterThan(0, $contextid);
        $this->assertTrue($DB->record_exists('local_catquiz_catcontext', ['id' => $contextid]));

        $existing = $this->create('Context root with chosen context', 0, $contextid);
        $this->assertSame($contextid, (int) $DB->get_field('local_catquiz_catscales', 'contextid', ['id' => $existing]));

        $count = $DB->count_records('local_catquiz_catscales');
        try {
            $this->create('Context root with a missing context', 0, 999999);
            $this->fail('A missing context was accepted.');
        } catch (\moodle_exception $e) {
            $this->assertSame('invalidcatcontext', $e->errorcode);
        }
        $this->assertSame($count, $DB->count_records('local_catquiz_catscales'));
    }
}
