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
use ReflectionMethod;

/**
 * get_global_scale() keeps its own int|array contract.
 *
 * The method is documented as taking a single id or a list of ids, but read $catscaleids[0] before
 * it had established that an array was passed at all - so the documented call with a plain integer
 * addressed an offset on an integer (issue #103).
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\catquiz
 */
final class catquiz_global_scale_test extends advanced_testcase {
    /**
     * Calls the private method under test.
     *
     * @param mixed $catscaleids A scale id or a list of them.
     * @param bool $assocarray Whether the association map is wanted.
     * @return array
     */
    private function call($catscaleids, bool $assocarray = false): array {
        $method = new ReflectionMethod(catquiz::class, 'get_global_scale');
        $method->setAccessible(true);

        return (array) $method->invoke(null, $catscaleids, $assocarray);
    }

    /**
     * Builds a two-level scale tree and returns the ids.
     *
     * @return array{0: int, 1: int} Root id and child id.
     */
    private function make_tree(): array {
        global $DB;

        $now = time();
        $rootid = (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'parentid' => 0, 'name' => 'root', 'contextid' => 1,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        $childid = (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'parentid' => $rootid, 'name' => 'child', 'contextid' => 1,
            'timecreated' => $now, 'timemodified' => $now,
        ]);

        return [$rootid, $childid];
    }

    /**
     * A single id is accepted, as the signature promises.
     */
    public function test_a_single_id(): void {
        $this->resetAfterTest();
        [$rootid, $childid] = $this->make_tree();

        $this->assertEquals([$rootid], array_values($this->call($childid)));
    }

    /**
     * The same id wrapped in an array gives the same answer.
     */
    public function test_a_single_id_in_an_array(): void {
        $this->resetAfterTest();
        [$rootid, $childid] = $this->make_tree();

        $this->assertEquals($this->call($childid), $this->call([$childid]));
    }

    /**
     * Several ids resolve to their global scales.
     */
    public function test_several_ids(): void {
        $this->resetAfterTest();
        [$rootid, $childid] = $this->make_tree();

        $result = array_values($this->call([$rootid, $childid]));

        $this->assertEqualsCanonicalizing([$rootid, $rootid], $result);
    }

    /**
     * An empty array asks for everything rather than for nothing in particular.
     */
    public function test_an_empty_array(): void {
        $this->resetAfterTest();
        [$rootid, $childid] = $this->make_tree();

        $result = $this->call([]);

        $this->assertArrayHasKey($childid, $result);
        $this->assertEquals($rootid, $result[$childid]);
    }

    /**
     * The two return shapes differ as documented and neither warns.
     */
    public function test_both_shapes_are_warning_free(): void {
        $this->resetAfterTest();
        [$rootid, $childid] = $this->make_tree();

        $flat = $this->call([$childid], false);
        $assoc = $this->call([$childid], true);

        $this->assertEquals([$rootid], array_values($flat));
        $this->assertEquals($rootid, $assoc[$childid]);
        $this->assertDebuggingNotCalled();
    }
}
