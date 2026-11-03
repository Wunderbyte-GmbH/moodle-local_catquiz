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

namespace local_catquiz\local\statistics;

use advanced_testcase;
use local_catquiz\catscale;
use local_catquiz\teststrategy\feedback_helper;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * The test information of a scale, stage by stage (issue #150, re-audit 28.10.2026, AP-04, R11-R14).
 *
 * Root scale R with subscale A, CAT contexts "old" and "new"; in "new":
 * - q1 (raschbirnbaum) and q2 (rasch): calibrated, ready, active - they give the information;
 * - q3: no active parameter (pilot);
 * - q4: calibrated, the newest version of the question hidden;
 * - q5: calibrated, the item deactivated in its scale;
 * - q6: calibrated with a model that is not installed;
 * - q7: calibrated - and an item of the question in "old" without parameters, with the lower id;
 * - q8: its active parameter lies in "old".
 * The stages count what each condition leaves. q7 is the case found on 28.10.2026 (issue #157): the pool read the
 * items of every context and took one row per question, the one with the lowest item id - the row of "old" without
 * parameters won over the calibrated one, and the question counted as a pilot in "new", for the test information and
 * for the selection of the test alike. Now the pool holds the items of its context only; the diagnosis shows the pool
 * the old rule built next to it (before/after).
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\local\statistics\statistics_diagnosis::testinfo_stages
 * @covers \local_catquiz\teststrategy\feedback_helper::testinfo_for_scale
 */
final class testinfo_stages_test extends advanced_testcase {
    /** @var int[] */
    private array $contexts = [];

    /** @var int */
    private int $root;

    /** @var int */
    private int $sub;

    /** @var int[] Question ids by name. */
    private array $questions = [];

    /**
     * The fixture of the class comment.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $now = time();
        foreach (['old', 'new'] as $name) {
            $this->contexts[$name] = (int) $DB->insert_record('local_catquiz_catcontext', (object) [
                'name' => $name, 'description' => '', 'descriptionformat' => FORMAT_HTML, 'starttimestamp' => 0,
                'endtimestamp' => $now + 10000, 'timecreated' => $now, 'timemodified' => $now, 'usermodified' => 0,
            ]);
        }
        $this->root = (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'parentid' => 0, 'name' => 'R', 'contextid' => $this->contexts['new'], 'minscalevalue' => -5,
            'maxscalevalue' => 5, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $this->sub = (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'parentid' => $this->root, 'name' => 'A', 'minscalevalue' => -5, 'maxscalevalue' => 5,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();
        foreach (['q1', 'q2', 'q3', 'q4', 'q5', 'q6', 'q7', 'q8'] as $name) {
            $this->questions[$name] = (int) $generator->create_question('truefalse', null, ['category' => $category->id])->id;
        }
        // The item of q7 in "old", created first: the lower id.
        $this->item('q7', 'old', null);
        $this->item('q1', 'new', 'raschbirnbaum');
        $this->item('q2', 'new', 'rasch');
        $this->item('q3', 'new', null);
        $this->item('q4', 'new', 'raschbirnbaum');
        $this->item('q5', 'new', 'raschbirnbaum', LOCAL_CATQUIZ_TESTITEM_STATUS_INACTIVE);
        $this->item('q6', 'new', 'notinstalled');
        $this->item('q7', 'new', 'raschbirnbaum');
        $this->item('q8', 'new', 'raschbirnbaum', LOCAL_CATQUIZ_TESTITEM_STATUS_ACTIVE, 'old');
        $DB->set_field(
            'question_versions',
            'status',
            \core_question\local\bank\question_version_status::QUESTION_STATUS_HIDDEN,
            ['questionid' => $this->questions['q4']]
        );
        \cache_helper::purge_all();
    }

    /**
     * An item of the subscale, with an active parameter or none.
     *
     * @param string $question
     * @param string $context
     * @param string|null $model Null: no parameter.
     * @param int $status
     * @param string|null $paramcontext The context of the parameter, if not that of the item.
     * @return int
     */
    private function item(
        string $question,
        string $context,
        ?string $model,
        int $status = LOCAL_CATQUIZ_TESTITEM_STATUS_ACTIVE,
        ?string $paramcontext = null
    ): int {
        global $DB;
        $itemid = (int) $DB->insert_record('local_catquiz_items', (object) [
            'componentid' => $this->questions[$question], 'componentname' => 'question', 'catscaleid' => $this->sub,
            'contextid' => $this->contexts[$context], 'status' => $status,
        ]);
        if ($model !== null) {
            $paramid = $DB->insert_record('local_catquiz_itemparams', (object) [
                'itemid' => $itemid, 'componentid' => $this->questions[$question], 'componentname' => 'question',
                'contextid' => $this->contexts[$paramcontext ?? $context], 'model' => $model, 'difficulty' => 0.5,
                'discrimination' => 1.2, 'guessing' => 0, 'json' => '{}', 'status' => LOCAL_CATQUIZ_STATUS_CALCULATED,
                'usable' => 1, 'timecreated' => time(), 'timemodified' => time(),
            ]);
            $DB->set_field('local_catquiz_items', 'activeparamid', $paramid, ['id' => $itemid]);
        }
        return $itemid;
    }

    /**
     * R11, R12: each condition, counted; R14: the cached pool is the cold one, without anything of a person.
     */
    public function test_the_stages_of_the_test_information(): void {
        $steps = [-2.0, 0.0, 2.0];
        $report = statistics_diagnosis::testinfo_stages($this->root, $this->contexts['new'], $steps);
        $this->assertSame([
            'items of the scale and its subscales in the context' => 8,
            '... with an active parameter (activeparamid)' => 7,
            '... of the item and in this context' => 6,
            '... question exists' => 6,
            '... newest version of the question ready' => 5,
            '... item active in its scale' => 4,
            '... model installed' => 3,
        ], $report['stages']);
        $this->assertEquals([
            'notinstalled' => ['items' => 1, 'installed' => false],
            'rasch' => ['items' => 1, 'installed' => true],
            'raschbirnbaum' => ['items' => 2, 'installed' => true],
        ], $report['by_model_after_the_conditions']);
        $this->assertSame(
            [$this->contexts['old'] => '1'],
            array_map('strval', $report['items of the scales in other CAT contexts, by context'])
        );

        // Issue #157: q8 points to parameters in "old" - out of the pool, reported, never a pilot.
        $integrity = $report['references that do not resolve (issue #157, left out of the pool)'];
        $this->assertSame(1, $integrity['count']);
        $this->assertSame(1, $integrity['bykind']['othercontext']);
        // Before and after: the old rule took the pilot row of "old" for q7.
        $this->assertSame([
            'questions' => 4, 'with parameters' => 2, 'without parameters (as pilots)' => 2,
            'rows of an item of another CAT context' => 1,
            'by context of the row' => [$this->contexts['old'] => 1, $this->contexts['new'] => 3],
        ], $report['pool before 28.10.2026 (items of every context, lowest item id)']);

        $pool = $report['pool'];
        $this->assertSame(4, $pool['questions in the pool (one row per question, cold: without the cache)'], 'q1, q2, q3, q7.');
        $this->assertSame(3, $pool['... rows with parameters']);
        $this->assertSame(0, $pool['... rows of an item of another CAT context'], 'q7: the calibrated row of "new".');
        $this->assertSame(0, $pool['... questions with parameters in this context, but a row without']);
        $this->assertSame('no entry', $pool['cache'], 'The diagnosis reads the cache, it does not fill it.');
        $this->assertSame([
            'items whose parameters can be read (model_item_param)' => 3,
            'items with a finite information at every step' => 3,
            'items with a finite information, by step' => ['-2' => 3, '0' => 3, '2' => 3],
        ], $report['information']);

        // The statistics see the same: three items with parameters, q7 with those of "new".
        $info = (new feedback_helper())->testinfo_for_scale($this->root, $this->contexts['new'], $steps);
        $this->assertSame([4, 3, 3], [$info['items'], $info['withparameters'], $info['computed']]);

        // Warm: the cached pool is the cold one, and holds nothing of the person who filled it.
        $this->setUser($this->getDataGenerator()->create_user());
        (new catscale($this->root))->get_testitems($this->contexts['new'], true);
        $warm = statistics_diagnosis::testinfo_stages($this->root, $this->contexts['new'], $steps)['pool']['cache'];
        $this->assertSame(['questions' => 4, 'same as cold' => true], $warm);
        $cached = \cache::make('local_catquiz', 'runtimepool')
            ->get(catscale::pool_key($this->contexts['new'], true, $this->root, []));
        foreach ($cached as $row) {
            $this->assertEmpty($row->userid ?? null, 'No person in the application cache.');
            $this->assertEmpty($row->userattempts ?? null);
        }
    }

    /**
     * The CLI prints the stages (anonymised: counts only).
     */
    public function test_the_report_holds_the_stages_per_context(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $DB->insert_record('local_catquiz_tests', (object) [
            'componentid' => 1, 'component' => 'mod_adaptivequiz', 'catscaleid' => $this->root,
            'contextid' => $this->contexts['new'], 'courseid' => $course->id, 'name' => 'T', 'status' => 1,
            'json' => json_encode(['catquiz_selectteststrategy' => LOCAL_CATQUIZ_STRATEGY_CLASSIC]),
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $report = statistics_diagnosis::report($this->root, (int) $course->id);
        $stages = $report['testinfo']['by_context'][$this->contexts['new']]['stages'];
        $this->assertSame(8, $stages['stages']['items of the scale and its subscales in the context']);
        $json = json_encode($report);
        foreach ($this->questions as $questionid) {
            $this->assertStringNotContainsString('"' . $questionid . '"', $json);
        }
    }
}
