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

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once(__DIR__ . '/classic_hierarchy_trait.php');

/**
 * A question the question bank hides during an attempt is not asked (issue #82).
 *
 * Deleting a question in use only hides it - question_delete_question() sets the status of its versions and
 * triggers no event - so the cached runtime pool still holds it. The selection checks the question it is about
 * to hand out, drops what the bank hides, and invalidates the pool.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\teststrategy\strategy
 * @covers \local_catquiz\catscale::unavailable_question_ids
 */
final class hidden_question_delivery_test extends advanced_testcase {
    use classic_hierarchy_trait;

    /**
     * The 24 items, a classical test over all of them without a per-scale maximum.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->set_up_hierarchy();
        $this->settings(['maxquestionsscalegroup' => (object) [
            'catquiz_maxquestionspersubscale' => 0, 'catquiz_minquestionspersubscale' => 0,
        ]]);
    }

    /**
     * Unsets the switches of the testing classes again.
     */
    public function tearDown(): void {
        $this->unset_testing_switches();
        parent::tearDown();
    }

    /**
     * Deleted in the bank while in use - a quiz refers to it - the question is hidden without an event. The
     * classical form skips it, and the pool read afterwards no longer holds it, without anybody purging it.
     */
    public function test_a_question_hidden_during_the_attempt_is_not_asked(): void {
        global $DB;
        // The classical form: the items by id.
        $form = array_map('intval', array_values($DB->get_fieldset_select(
            'local_catquiz_items',
            'componentid',
            "componentname = 'question' ORDER BY id"
        )));
        $this->assertCount(24, $form);
        $hidden = $form[1];
        // In use elsewhere: a quiz refers to the question, so deleting it only hides it.
        $DB->insert_record('question_references', (object) [
            'usingcontextid' => \context_system::instance()->id, 'component' => 'mod_quiz', 'questionarea' => 'slot',
            'itemid' => 1,
            'questionbankentryid' => $DB->get_field('question_versions', 'questionbankentryid', ['questionid' => $hidden]),
        ]);

        $contextid = catscale::get_context_id($this->rootid);
        [$ids] = $this->play(3, true, function (int $i) use ($hidden, $contextid, $DB) {
            if ($i !== 0) {
                return;
            }
            // The pool is cached with the question in it.
            $pool = (new catscale($this->rootid))->get_testitems($contextid, true);
            $this->assertArrayHasKey($hidden, $pool);
            question_delete_question($hidden);
            $this->assertSame('hidden', $DB->get_field('question_versions', 'status', ['questionid' => $hidden]));
        }, fn() => true);

        $this->assertSame([$form[0], $form[2], $form[3]], $ids, 'The hidden question was asked or the form changed.');
        $pool = (new catscale($this->rootid))->get_testitems($contextid, true);
        $this->assertArrayNotHasKey($hidden, $pool);
        $this->assertCount(23, $pool);
    }
}
