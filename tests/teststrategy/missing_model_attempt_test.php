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

namespace local_catquiz\teststrategy;

use advanced_testcase;
use local_catquiz\catscale;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_model;
use local_catquiz\local\model\model_strategy;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once(__DIR__ . '/classic_hierarchy_trait.php');

/**
 * An item whose model is not installed, in an attempt (issue #153, follow-up C of #150).
 *
 * The parameters of an item name their model; when the subplugin of that model is uninstalled, or the
 * parameters were imported for a model this site does not have, the item stays in the runtime pool with
 * a model nobody can compute.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\catscale::get_testitems
 * @covers \local_catquiz\teststrategy\strategy
 * @covers \local_catquiz\catscale::item_availability
 * @covers \local_catquiz\local\model\model_strategy::rows_of_installed_models
 * @covers \local_catquiz\local\model\model_item_param_list::load_from_db
 */
final class missing_model_attempt_test extends advanced_testcase {
    use classic_hierarchy_trait;

    /**
     * The 24 items, a test over all of them without a per-scale maximum.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->set_up_hierarchy();
    }

    /**
     * Unsets the switches of the testing classes again.
     */
    public function tearDown(): void {
        $this->unset_testing_switches();
        parent::tearDown();
    }

    /**
     * Gives the active parameters of an item a model that is not installed.
     *
     * @param int $questionid
     * @return void
     */
    private function without_model(int $questionid): void {
        global $DB;
        $paramids = $DB->get_fieldset_select(
            'local_catquiz_items',
            'activeparamid',
            "componentname = 'question' AND componentid = :q AND activeparamid IS NOT NULL",
            ['q' => $questionid]
        );
        $this->assertNotEmpty($paramids);
        foreach ($paramids as $paramid) {
            $DB->set_field('local_catquiz_itemparams', 'model', 'notinstalled', ['id' => $paramid]);
        }
        \cache_helper::purge_by_event('changesintestitems');
    }

    /**
     * The strategies: by form (CLASSIC) and adaptive.
     *
     * @return array
     */
    public static function strategy_provider(): array {
        return [
            'classical test' => [LOCAL_CATQUIZ_STRATEGY_CLASSIC],
            'adaptive, all subscales' => [LOCAL_CATQUIZ_STRATEGY_ALLSUBS],
        ];
    }

    /**
     * The item is not played, the attempt runs - with the feedback, the whole path - and asks what it asked
     * without the item: the selection of the other items is unchanged.
     *
     * @param int $strategy
     * @dataProvider strategy_provider
     */
    public function test_an_item_without_an_installed_model_is_not_played(int $strategy): void {
        global $DB;
        $this->settings(['catquiz_selectteststrategy' => $strategy]);
        $form = array_map('intval', array_values($DB->get_fieldset_select(
            'local_catquiz_items',
            'componentid',
            "componentname = 'question' ORDER BY id"
        )));
        $missing = $form[1];
        // Without the change: what a first person is asked.
        $this->setUser($this->getDataGenerator()->create_user());
        [$before] = $this->play(6, false);

        $this->without_model($missing);
        $this->setUser($this->getDataGenerator()->create_user());
        [$ids] = $this->play(6, false);

        // Before the fix, both forms ended with an error at the first question.
        $this->assertGreaterThanOrEqual(3, count($ids), 'The attempt ended before its minimum.');
        $this->assertNotContains($missing, $ids, 'An item nobody can compute was played.');
        if ($strategy === LOCAL_CATQUIZ_STRATEGY_CLASSIC) {
            // The form without the item: the next one takes its place.
            $this->assertSame($form[1], $before[1]);
            $this->assertSame(array_slice(array_values(array_diff($form, [$missing])), 0, 6), $ids);
        } else {
            // The adaptive form of this pool never asks the item; it asks the same as before.
            $this->assertNotContains($missing, $before);
            $this->assertSame($before, $ids);
        }
        $pool = (new catscale($this->rootid))->get_testitems(catscale::get_context_id($this->rootid), true);
        $this->assertArrayNotHasKey($missing, $pool);
        $this->assertCount(23, $pool);
    }

    /**
     * The manager reads why the item is not played; the question bank still comes first.
     */
    public function test_the_manager_reads_why(): void {
        $ready = \core_question\local\bank\question_version_status::QUESTION_STATUS_READY;
        $hidden = \core_question\local\bank\question_version_status::QUESTION_STATUS_HIDDEN;
        $active = LOCAL_CATQUIZ_TESTITEM_STATUS_ACTIVE;
        $this->assertSame(catscale::AVAILABILITY_MODELNOTINSTALLED, catscale::item_availability($ready, $active, 'notinstalled'));
        $this->assertSame(catscale::AVAILABILITY_ACTIVE, catscale::item_availability($ready, $active, 'raschbirnbaum'));
        $this->assertSame(catscale::AVAILABILITY_ACTIVE, catscale::item_availability($ready, $active, null), 'A pilot item.');
        $this->assertSame(catscale::AVAILABILITY_GLOBALHIDDEN, catscale::item_availability($hidden, $active, 'notinstalled'));
        $this->assertNotEmpty(get_string('itemavailability_' . catscale::AVAILABILITY_MODELNOTINSTALLED, 'local_catquiz'));
    }

    /**
     * Model resolution: null for a model that is not installed, as the declared type now says; the rows of such a
     * model are left out, rows without a model kept, keys kept.
     */
    public function test_model_resolution(): void {
        $this->assertNull(model_model::get_instance('notinstalled'));
        $this->assertInstanceOf(model_model::class, model_model::get_instance('raschbirnbaum'));
        $rows = [
            7 => (object) ['model' => 'raschbirnbaum'],
            8 => (object) ['model' => 'notinstalled'],
            9 => (object) ['model' => null],
            10 => (object) ['model' => ''],
        ];
        $this->assertSame([7, 9, 10], array_keys(model_strategy::rows_of_installed_models($rows)));
    }

    /**
     * The standard error of a mixed pool is that of its items of installed models - to the bit.
     */
    public function test_the_standard_error_of_a_mixed_pool_is_unchanged(): void {
        global $DB;
        $contextid = catscale::get_context_id($this->rootid);
        $scales = [$this->rootid, ...catscale::get_subscale_ids($this->rootid)];
        $form = array_map('intval', array_values($DB->get_fieldset_select(
            'local_catquiz_items',
            'componentid',
            "componentname = 'question' AND activeparamid IS NOT NULL ORDER BY id"
        )));
        $missing = $form[1];
        $valid = model_item_param_list::load_from_db($contextid, null, $scales);
        $this->assertTrue($valid->offsetExists($missing));
        $valid->offsetUnset($missing);
        $expected = [];
        foreach ([-2.0, 0.0, 1.5] as $ability) {
            $expected[] = catscale::get_standarderror($ability, $valid);
        }

        $this->without_model($missing);
        $mixed = model_item_param_list::load_from_db($contextid, null, $scales);

        $this->assertFalse($mixed->offsetExists($missing));
        $this->assertSame(count($valid), count($mixed));
        $actual = [];
        foreach ([-2.0, 0.0, 1.5] as $ability) {
            $actual[] = catscale::get_standarderror($ability, $mixed);
        }
        $this->assertSame($expected, $actual);
    }
}
