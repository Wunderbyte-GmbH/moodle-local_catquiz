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
require_once($CFG->dirroot . '/local/catquiz/db/upgradelib.php');

/**
 * Upgrade step 2024080200 on data with duplicates: a question in several scales (issue #104, K6).
 *
 * The step gave each parameter an item, reading (parameter, item) pairs keyed by the parameter: with the
 * question in two scales, which item it got depended on the order the database returned the pairs in.
 * The fixture is the state before the step - parameters without an item, a question in three scales whose
 * items were not created in id order of their scales.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers ::local_catquiz_upgrade_assign_itemparam_items
 */
final class itemparam_item_upgrade_test extends advanced_testcase {
    /**
     * An item of a question in a scale.
     *
     * @param int $questionid
     * @param int $scaleid
     * @return int
     */
    private function item(int $questionid, int $scaleid): int {
        global $DB;
        return (int) $DB->insert_record('local_catquiz_items', (object) [
            'componentid' => $questionid, 'componentname' => 'question', 'catscaleid' => $scaleid,
            'contextid' => 1, 'status' => 0,
        ]);
    }

    /**
     * A parameter of a question as it was before the step: no item.
     *
     * @param int $questionid
     * @param string $model
     * @param int $contextid Distinct where the unique index (itemid, contextid, model) of today's schema
     *                       would see two parameters without item - before the step it did not exist.
     * @return int
     */
    private function param(int $questionid, string $model, int $contextid = 1): int {
        global $DB;
        return (int) $DB->insert_record('local_catquiz_itemparams', (object) [
            'itemid' => 0, 'componentid' => $questionid, 'componentname' => 'question', 'contextid' => $contextid,
            'model' => $model, 'difficulty' => 0.5, 'discrimination' => 1.0, 'guessing' => 0, 'json' => '{}',
            'status' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * Every parameter gets the lowest item of its question, whatever order the database returns; a second
     * run changes nothing; parameters with an item and questions without one stay as they are.
     */
    public function test_each_parameter_gets_the_lowest_item(): void {
        global $DB;
        $this->resetAfterTest();
        // Question 501 in three scales; question 502 in one; question 503 in none.
        $items = [$this->item(501, 30), $this->item(501, 10), $this->item(501, 20)];
        $single = $this->item(502, 10);
        $p1 = $this->param(501, 'rasch');
        $p2 = $this->param(501, 'raschbirnbaum');
        $p3 = $this->param(502, 'rasch', 2);
        $orphan = $this->param(503, 'rasch', 3);
        $set = $this->param(501, 'mixedraschbirnbaum');
        $DB->set_field('local_catquiz_itemparams', 'itemid', $items[2], ['id' => $set]);

        $this->assertSame(3, local_catquiz_upgrade_assign_itemparam_items());

        $itemof = fn(int $id) => (int) $DB->get_field('local_catquiz_itemparams', 'itemid', ['id' => $id]);
        $this->assertSame(min($items), $itemof($p1), 'The lowest item of the question, not the last row delivered.');
        $this->assertSame(min($items), $itemof($p2));
        $this->assertSame($single, $itemof($p3));
        $this->assertSame(0, $itemof($orphan), 'A question without item keeps 0.');
        $this->assertSame($items[2], $itemof($set), 'A parameter with an item is not touched.');
        $this->assertSame(0, local_catquiz_upgrade_assign_itemparam_items(), 'Idempotent.');
        $this->assertSame(5, $DB->count_records('local_catquiz_itemparams'), 'No row added or lost.');
    }
}
