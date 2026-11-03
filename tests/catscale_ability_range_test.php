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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * The ability range of a scale, with bounds of 0 (mod_adaptivequiz issue #14).
 *
 * get_ability_range() tested the bounds for truthiness: a scale object holding a numeric 0 got the
 * default range -5..5, and with it every percentage computed from that range was wrong. Bounds that
 * are set but form no range are reported as they are - the activity refuses to grade on them.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\catscale
 */
final class catscale_ability_range_test extends advanced_testcase {
    /**
     * Stores a scale.
     *
     * @param mixed $min
     * @param mixed $max
     * @param int $parentid
     * @return int
     */
    private static function scale($min, $max, int $parentid = 0): int {
        global $DB;
        return (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'parentid' => $parentid, 'name' => 'Scale ' . uniqid(), 'minscalevalue' => $min, 'maxscalevalue' => $max,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * Stored bounds and the range they give.
     *
     * @return array
     */
    public static function ranges(): array {
        $default = [LOCAL_CATQUIZ_PERSONABILITY_LOWER_LIMIT, LOCAL_CATQUIZ_PERSONABILITY_UPPER_LIMIT];
        return [
            'lower bound 0' => [0, 100, [0, 100]],
            'upper bound 0' => [-4, 0, [-4, 0]],
            'both bounds non-zero' => [-3, 3, [-3, 3]],
            'not set' => [null, null, $default],
            'only one bound set' => [-3, null, $default],
            // Set, but no range: returned as it is, so the activity can refuse to grade on it.
            'both 0' => [0, 0, [0, 0]],
            'upper below lower' => [4, -4, [4, -4]],
        ];
    }

    /**
     * The stored range of the root scale, with 0 as a valid bound.
     *
     * @param mixed $min
     * @param mixed $max
     * @param array $expected [min, max]
     * @dataProvider ranges
     */
    public function test_range_of_a_root_scale($min, $max, array $expected): void {
        $this->resetAfterTest();

        $range = (new catscale(self::scale($min, $max)))->get_ability_range();

        $this->assertEquals($expected, [(float) $range['minscalevalue'], (float) $range['maxscalevalue']]);
    }

    /**
     * A numeric 0, as in-memory scale objects carry it, is a bound and not "not set".
     *
     * Values read from the database arrive as strings ("0.00"), which were truthy even before the
     * fix; a scale object held as numbers (catscale_structure has float bounds) was not.
     */
    public function test_numeric_zero_bound_is_a_bound(): void {
        global $DB;
        $this->resetAfterTest();

        $scaleid = self::scale(0, 100);
        $record = $DB->get_record('local_catquiz_catscales', ['id' => $scaleid]);
        $record->minscalevalue = 0.0;
        $record->maxscalevalue = 100.0;
        \cache::make('local_catquiz', 'catscales')->set($scaleid, $record);

        $range = (new catscale($scaleid))->get_ability_range();

        $this->assertEquals([0, 100], [(float) $range['minscalevalue'], (float) $range['maxscalevalue']]);
    }

    /**
     * A subscale has the range of its root.
     */
    public function test_subscale_has_the_range_of_its_root(): void {
        $this->resetAfterTest();

        $child = self::scale(null, null, self::scale(0, 100));

        $range = (new catscale($child))->get_ability_range();

        $this->assertEquals([0, 100], [(float) $range['minscalevalue'], (float) $range['maxscalevalue']]);
    }
}
