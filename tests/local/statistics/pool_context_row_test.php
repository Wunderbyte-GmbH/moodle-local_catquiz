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
require_once($CFG->dirroot . '/question/engine/lib.php');

/**
 * One row per question in the pool of a CAT context: the one with the active parameters of the context; without
 * such a row, the one without parameters (issue #150, re-audit 28.10.2026). A question played once leaves the
 * pool with every item it has - the pool and the exclusion go by the question id, not the item id.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\catscale::one_row_per_question
 */
final class pool_context_row_test extends advanced_testcase {
    /**
     * The calibrated row of the context wins over a row without parameters of an earlier context; between rows
     * alike the lowest item id, as before.
     */
    public function test_the_row_with_parameters_of_the_context_wins(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $now = time();
        $context = fn(string $name) => (int) $DB->insert_record('local_catquiz_catcontext', (object) [
            'name' => $name, 'description' => '', 'descriptionformat' => FORMAT_HTML, 'starttimestamp' => 0,
            'endtimestamp' => $now + 10000, 'timecreated' => $now, 'timemodified' => $now, 'usermodified' => 0,
        ]);
        $old = $context('old');
        $new = $context('new');
        $root = (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'parentid' => 0, 'name' => 'R', 'contextid' => $new, 'minscalevalue' => -5, 'maxscalevalue' => 5,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        $a = (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'parentid' => $root, 'name' => 'A', 'minscalevalue' => -5, 'maxscalevalue' => 5, 'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $b = (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'parentid' => $root, 'name' => 'B', 'minscalevalue' => -5, 'maxscalevalue' => 5, 'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();
        $item = function (int $questionid, int $scaleid, int $contextid, bool $calibrated) use ($DB, $now): int {
            $itemid = (int) $DB->insert_record('local_catquiz_items', (object) [
                'componentid' => $questionid, 'componentname' => 'question', 'catscaleid' => $scaleid,
                'contextid' => $contextid, 'status' => LOCAL_CATQUIZ_TESTITEM_STATUS_ACTIVE,
            ]);
            if ($calibrated) {
                $paramid = $DB->insert_record('local_catquiz_itemparams', (object) [
                    'itemid' => $itemid, 'componentid' => $questionid, 'componentname' => 'question',
                    'contextid' => $contextid, 'model' => 'raschbirnbaum', 'difficulty' => 0.5, 'discrimination' => 1.2,
                    'guessing' => 0, 'json' => '{}', 'status' => LOCAL_CATQUIZ_STATUS_CALCULATED, 'usable' => 1,
                    'timecreated' => $now, 'timemodified' => $now,
                ]);
                $DB->set_field('local_catquiz_items', 'activeparamid', $paramid, ['id' => $itemid]);
            }
            return $itemid;
        };
        // The case: an earlier context without parameters (lower id), this context calibrated.
        $q1 = (int) $generator->create_question('truefalse', null, ['category' => $category->id])->id;
        $item($q1, $a, $old, false);
        $q1new = $item($q1, $a, $new, true);
        // Issue #104: a question in two sibling scales of the context, both calibrated - the lowest item id.
        $q2 = (int) $generator->create_question('truefalse', null, ['category' => $category->id])->id;
        $q2first = $item($q2, $a, $new, true);
        $item($q2, $b, $new, true);
        // Both rows without parameters: the lowest item id.
        $q3 = (int) $generator->create_question('truefalse', null, ['category' => $category->id])->id;
        $q3first = $item($q3, $a, $new, false);
        $item($q3, $b, $new, false);
        \cache_helper::purge_all();

        $pool = (new catscale($root))->get_testitems($new, true);
        $this->assertSame($q1new, (int) $pool[$q1]->itemid, 'The calibrated row of this context.');
        $this->assertSame('raschbirnbaum', $pool[$q1]->model);
        $this->assertSame($q2first, (int) $pool[$q2]->itemid);
        $this->assertSame($q3first, (int) $pool[$q3]->itemid);
        $this->assertEmpty($pool[$q3]->model);

        $info = (new feedback_helper())->testinfo_for_scale($root, $new, [-1.0, 0.0, 1.0]);
        $this->assertSame([3, 2, 2], [$info['items'], $info['withparameters'], $info['computed']]);
        $stages = statistics_diagnosis::testinfo_stages($root, $new, [-1.0, 0.0, 1.0]);
        $this->assertSame(0, $stages['pool']['... questions with parameters in this context, but a row without']);

        // Played once - here: in the question usage of the attempt -, the question leaves the pool with all its items.
        $quba = \question_engine::make_questions_usage_by_activity('mod_adaptivequiz', \context_system::instance());
        $quba->set_preferred_behaviour('deferredfeedback');
        $quba->add_question(\question_bank::load_question($q2));
        $quba->start_all_questions();
        \question_engine::save_questions_usage_by_activity($quba);
        $attemptid = (int) $DB->insert_record('adaptivequiz_attempt', (object) [
            'instance' => 55003, 'userid' => 2, 'uniqueid' => $quba->get_id(), 'attemptstate' => 'inprogress',
            'attemptstopcriteria' => '', 'questionsattempted' => 1, 'difficultysum' => 0,
            'standarderror' => 1, 'measure' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $context = [
            'progress' => \local_catquiz\teststrategy\progress::load($attemptid, 'mod_adaptivequiz', $new, (object) []),
            'questions' => $pool,
        ];
        (new \local_catquiz\teststrategy\preselect_task\removeplayedquestions())->run($context);
        $this->assertSame([$q1, $q3], array_keys($context['questions']));
        foreach ($context['questions'] as $row) {
            $this->assertNotSame($q2, (int) $row->componentid, 'No item of the played question is left.');
        }
    }
}
