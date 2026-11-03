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

/**
 * get_catscale_and_children() works on scales keyed by catscale id.
 *
 * The method addresses $catscales[$id] directly. The default path satisfies that, an array handed
 * in from outside did not have to - a list-keyed array would silently work on the wrong entries
 * (issue #99). The invariant is now established rather than assumed.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\data\dataapi
 */
final class scale_keying_test extends advanced_testcase {
    /**
     * Builds a scale tree: one root, two children, one grandchild.
     *
     * @return array Scales keyed by id, as the method expects them.
     */
    private function tree(): array {
        $scales = [];
        foreach (
            [
            ['id' => 11, 'parentid' => 0, 'name' => 'root', 'contextid' => 7],
            ['id' => 12, 'parentid' => 11, 'name' => 'child a', 'contextid' => 7],
            ['id' => 13, 'parentid' => 11, 'name' => 'child b', 'contextid' => 7],
            ['id' => 14, 'parentid' => 12, 'name' => 'grandchild', 'contextid' => 7],
            ] as $row
        ) {
            $scale = (object) $row;
            $scales[$scale->id] = $scale;
        }

        return $scales;
    }

    /**
     * An id-keyed map is the documented input and returns the whole subtree.
     */
    public function test_id_keyed_map(): void {
        $this->resetAfterTest();

        $result = dataapi::get_catscale_and_children(11, true, $this->tree());

        $this->assertEqualsCanonicalizing([11, 12, 13, 14], array_keys($result));
    }

    /**
     * The same records reindexed with array_values() give the same answer.
     *
     * This is the case the issue is about: without re-keying the method reads $catscales[11] and
     * finds the record at offset 11, which does not exist - or worse, a different scale.
     */
    public function test_reindexed_array_gives_the_same_answer(): void {
        $this->resetAfterTest();

        $keyed = dataapi::get_catscale_and_children(11, true, $this->tree());
        $listed = dataapi::get_catscale_and_children(11, true, array_values($this->tree()));

        $this->assertDebuggingCalled();
        $this->assertEqualsCanonicalizing(array_keys($keyed), array_keys($listed));
    }

    /**
     * Parent, children and grandchildren are all reached.
     */
    public function test_parent_children_and_subchildren(): void {
        $this->resetAfterTest();

        $withsub = dataapi::get_catscale_and_children(11, true, $this->tree());
        $withoutsub = dataapi::get_catscale_and_children(11, false, $this->tree());

        $this->assertArrayHasKey(14, $withsub, 'The grandchild is missing.');
        $this->assertArrayNotHasKey(14, $withoutsub, 'The grandchild must not be reached without subchildren.');
    }

    /**
     * A parent id that is not in the array yields no tree instead of an error.
     */
    public function test_missing_parent_id(): void {
        $this->resetAfterTest();

        $result = dataapi::get_catscale_and_children(999, true, $this->tree());

        $this->assertIsArray($result);
        $this->assertArrayNotHasKey(999, $result);
    }

    /**
     * A duplicate id collapses to one entry rather than producing two.
     */
    public function test_duplicate_id(): void {
        $this->resetAfterTest();

        $scales = array_values($this->tree());
        $scales[] = clone($scales[1]);

        $result = dataapi::get_catscale_and_children(11, true, $scales);

        $this->assertDebuggingCalled();
        $this->assertCount(4, $result, 'A repeated id must not appear twice in the result.');
    }

    /**
     * The flat return shape keeps working.
     */
    public function test_return_as_array(): void {
        $this->resetAfterTest();

        $result = dataapi::get_catscale_and_children(11, true, $this->tree(), true);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }
}
