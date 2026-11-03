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
 * An item added to a scale belongs to the context of the scale (issues #82, #104 H3, #148).
 *
 * The CAT manager adds items with catscale::add_or_update_testitem_to_scale(). It wrote no context: the page of
 * the item looks the item of the scale up in the context of the scale and found none ("Attempt to read property
 * activeparamid on false", found by Behat catquiz_item_visibility), and a context change of the scale, which
 * moves the items of the old context, passed over it.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\catscale::add_or_update_testitem_to_scale
 * @covers \local_catquiz\catquiz::get_item
 */
final class item_scale_context_test extends advanced_testcase {
    /** @var int */
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
            'name' => 'Item context', 'description' => '', 'descriptionformat' => FORMAT_HTML,
            'starttimestamp' => time() - 100, 'endtimestamp' => time() + 10000,
            'timecreated' => time(), 'timemodified' => time(), 'usermodified' => 0,
        ]);
    }

    /**
     * A scale; a subscale without a context of its own inherits that of its parent.
     *
     * @param int $parentid
     * @param bool $owncontext
     * @return int
     */
    private function scale(int $parentid = 0, bool $owncontext = true): int {
        global $DB;
        return (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'parentid' => $parentid, 'name' => 'Item scale ' . (++$this->seq),
            'contextid' => $owncontext ? $this->contextid : null, 'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * A question.
     *
     * @return int
     */
    private function question(): int {
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        return (int) $generator->create_question('truefalse', null, ['category' => $generator->create_question_category()->id])->id;
    }

    /**
     * Added to two scales, the question has an item in each, in the context of each - found by the scale.
     */
    public function test_an_added_item_is_found_in_the_context_of_its_scale(): void {
        $first = $this->scale();
        $second = $this->scale();
        $questionid = $this->question();

        $firstitem = catscale::add_or_update_testitem_to_scale($first, $questionid)->unwrap();
        $seconditem = catscale::add_or_update_testitem_to_scale($second, $questionid)->unwrap();

        foreach ([$first => $firstitem, $second => $seconditem] as $scaleid => $itemid) {
            $item = catquiz::get_item($this->contextid, $questionid, 'question', $scaleid);
            $this->assertNotFalse($item, "The item of scale $scaleid in the context of the scale.");
            $this->assertSame((int) $itemid, (int) $item->id);
            $this->assertSame($this->contextid, (int) $item->contextid);
        }
    }

    /**
     * A subscale without a context of its own: the item gets the context the scale has through its parent.
     */
    public function test_a_subscale_passes_on_the_context_of_its_parent(): void {
        $parent = $this->scale();
        $child = $this->scale($parent, false);
        $questionid = $this->question();

        catscale::add_or_update_testitem_to_scale($child, $questionid);

        $this->assertSame($this->contextid, (int) catquiz::get_item($this->contextid, $questionid, 'question', $child)->contextid);
    }

    /**
     * A row written before, without a context, gets it when the manager touches the item again - the status
     * stays what the manager sets.
     */
    public function test_an_item_without_context_gets_it_on_the_next_change(): void {
        global $DB;
        $scaleid = $this->scale();
        $questionid = $this->question();
        $itemid = (int) $DB->insert_record('local_catquiz_items', (object) [
            'componentid' => $questionid, 'componentname' => 'question', 'catscaleid' => $scaleid,
            'status' => LOCAL_CATQUIZ_TESTITEM_STATUS_ACTIVE,
        ]);
        $this->assertFalse(catquiz::get_item($this->contextid, $questionid, 'question', $scaleid));

        catscale::add_or_update_testitem_to_scale($scaleid, $questionid, LOCAL_CATQUIZ_TESTITEM_STATUS_INACTIVE);

        $item = $DB->get_record('local_catquiz_items', ['id' => $itemid], '*', MUST_EXIST);
        $this->assertSame($this->contextid, (int) $item->contextid);
        $this->assertSame(LOCAL_CATQUIZ_TESTITEM_STATUS_INACTIVE, (int) $item->status);
        $this->assertSame(1, $DB->count_records('local_catquiz_items', ['componentid' => $questionid]), 'No second row.');
    }
}
