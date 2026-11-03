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

namespace local_catquiz\local\model;

use advanced_testcase;
use local_catquiz\catquiz;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * The same question as an item of two scales: which parameter is read, which is written (issue #104, H2/H3).
 *
 * The calibration estimates one parameter per question. Read for a set of scales, the list holds the
 * parameter of the question's lowest item - not the one the database happens to return last; saved for a set
 * of scales, the estimate goes to every item of the question in them, one row per item and model. Read for
 * one item - the editor of a question in a scale -, it is that item's parameter.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\local\model\model_item_param_list::load_from_db
 * @covers \local_catquiz\local\model\model_item_param_list::save_to_db
 * @covers \local_catquiz\local\model\model_item_param_list::get_by_questionid
 * @covers \local_catquiz\catquiz::get_item
 */
final class item_param_identity_test extends advanced_testcase {
    /** @var int */
    private int $contextid;

    /** @var int[] Scales A and B. */
    private array $scales = [];

    /** @var int */
    private int $question;

    /** @var int[] Items of the question: B first, so that its id is the lower one. */
    private array $items = [];

    /** @var int[] Their parameters. */
    private array $params = [];

    /**
     * The question in B (lower item id, difficulty 2) and in A (difficulty 1), same model.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->contextid = (int) $DB->insert_record('local_catquiz_catcontext', (object) [
            'name' => 'Identity', 'description' => '', 'descriptionformat' => FORMAT_HTML,
            'starttimestamp' => time() - 100, 'endtimestamp' => time() + 10000,
            'timecreated' => time(), 'timemodified' => time(), 'usermodified' => 0,
        ]);
        $root = (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'name' => 'Root', 'parentid' => 0, 'contextid' => $this->contextid,
            'minscalevalue' => -5, 'maxscalevalue' => 5, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        foreach (['A', 'B'] as $name) {
            $this->scales[$name] = (int) $DB->insert_record('local_catquiz_catscales', (object) [
                'name' => $name, 'parentid' => $root, 'minscalevalue' => -5, 'maxscalevalue' => 5,
                'timecreated' => time(), 'timemodified' => time(),
            ]);
        }
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();
        $this->question = (int) $generator->create_question('truefalse', null, ['category' => $category->id])->id;
        foreach (['B' => 2.0, 'A' => 1.0] as $name => $difficulty) {
            $this->items[$name] = (int) $DB->insert_record('local_catquiz_items', (object) [
                'componentid' => $this->question, 'componentname' => 'question', 'catscaleid' => $this->scales[$name],
                'contextid' => $this->contextid, 'status' => LOCAL_CATQUIZ_TESTITEM_STATUS_ACTIVE,
            ]);
            $this->params[$name] = (int) $DB->insert_record('local_catquiz_itemparams', (object) [
                'itemid' => $this->items[$name], 'componentid' => $this->question, 'componentname' => 'question',
                'contextid' => $this->contextid, 'model' => 'raschbirnbaum', 'difficulty' => $difficulty,
                'discrimination' => 1.0, 'guessing' => 0, 'json' => '{}', 'status' => LOCAL_CATQUIZ_STATUS_CALCULATED,
                'usable' => 1, 'timecreated' => time(), 'timemodified' => time(),
            ]);
            $DB->set_field('local_catquiz_items', 'activeparamid', $this->params[$name], ['id' => $this->items[$name]]);
        }
    }

    /**
     * The difficulty of a stored parameter.
     *
     * @param int $id
     * @return float
     */
    private function difficulty(int $id): float {
        global $DB;
        return (float) $DB->get_field('local_catquiz_itemparams', 'difficulty', ['id' => $id]);
    }

    /**
     * Read for both scales: one parameter for the question, the one of its lowest item - B - also when A's row
     * was changed last (and the database might return it last).
     */
    public function test_reading_takes_the_lowest_item(): void {
        global $DB;
        $DB->set_field('local_catquiz_itemparams', 'timemodified', time() + 10, ['id' => $this->params['A']]);
        $list = model_item_param_list::load_from_db($this->contextid, 'raschbirnbaum', array_values($this->scales));

        $this->assertCount(1, $list->itemparams);
        $this->assertSame(2.0, (float) $list[$this->question]->get_params_array()['difficulty']);
        $fora = model_item_param_list::load_from_db($this->contextid, 'raschbirnbaum', [$this->scales['A']]);
        $this->assertSame(
            1.0,
            (float) $fora[$this->question]->get_params_array()['difficulty'],
            'Read for A alone, the parameter of A.'
        );
    }

    /**
     * Saved for both scales, the estimate goes to both items - no row stays behind with old values, no row is
     * added; saved for one scale, only its item changes; saved into a new context, there is a row per item.
     */
    public function test_saving_writes_every_item_of_the_scales(): void {
        global $DB;
        $list = model_item_param_list::load_from_db($this->contextid, 'raschbirnbaum', array_values($this->scales));
        $param = $list[$this->question];
        $param->set_parameters(['difficulty' => 3.0, 'discrimination' => 1.0]);
        $list->save_to_db($this->contextid, array_values($this->scales));

        $this->assertSame(3.0, $this->difficulty($this->params['A']));
        $this->assertSame(3.0, $this->difficulty($this->params['B']));
        $this->assertSame(2, $DB->count_records('local_catquiz_itemparams', ['contextid' => $this->contextid]));

        $param->set_parameters(['difficulty' => 4.0, 'discrimination' => 1.0]);
        $list->save_to_db($this->contextid, [$this->scales['A']]);
        $this->assertSame(4.0, $this->difficulty($this->params['A']));
        $this->assertSame(3.0, $this->difficulty($this->params['B']), 'B was not calibrated.');

        $newcontext = (int) $DB->insert_record('local_catquiz_catcontext', (object) [
            'name' => 'Updated', 'description' => '', 'descriptionformat' => FORMAT_HTML,
            'starttimestamp' => time() - 100, 'endtimestamp' => time() + 10000,
            'timecreated' => time(), 'timemodified' => time(), 'usermodified' => 0,
        ]);
        $list->save_to_db($newcontext, array_values($this->scales), $this->contextid);
        $rows = $DB->get_records('local_catquiz_itemparams', ['contextid' => $newcontext], 'itemid', 'itemid, difficulty');
        $this->assertEquals([$this->items['B'], $this->items['A']], array_map('intval', array_keys($rows)));
    }

    /**
     * Without scales, as before: a parameter of the lowest item is updated, no second row.
     */
    public function test_saving_without_scales(): void {
        global $DB;
        $list = model_item_param_list::load_from_db($this->contextid, 'raschbirnbaum', array_values($this->scales));
        $list[$this->question]->set_parameters(['difficulty' => 5.0, 'discrimination' => 1.0]);
        $list->save_to_db($this->contextid);

        $this->assertSame(5.0, $this->difficulty($this->params['B']));
        $this->assertSame(1.0, $this->difficulty($this->params['A']));
        $this->assertSame(2, $DB->count_records('local_catquiz_itemparams', ['contextid' => $this->contextid]));
    }

    /**
     * The editor of a question in a scale: that scale's item and parameter.
     */
    public function test_the_editor_reads_the_item_of_its_scale(): void {
        $itema = catquiz::get_item($this->contextid, $this->question, 'question', $this->scales['A']);
        $this->assertSame($this->items['A'], (int) $itema->id);
        $this->assertSame($this->items['B'], (int) catquiz::get_item($this->contextid, $this->question, 'question')->id);

        $fora = model_item_param_list::get_by_questionid($this->contextid, $this->question, (int) $itema->id);
        $this->assertSame(1.0, (float) $fora['raschbirnbaum']->get_params_array()['difficulty']);
        $forb = model_item_param_list::get_by_questionid($this->contextid, $this->question, $this->items['B']);
        $this->assertSame(2.0, (float) $forb['raschbirnbaum']->get_params_array()['difficulty']);
        $any = model_item_param_list::get_by_questionid($this->contextid, $this->question);
        $this->assertSame(2.0, (float) $any['raschbirnbaum']->get_params_array()['difficulty'], 'The lowest item.');
    }
}
