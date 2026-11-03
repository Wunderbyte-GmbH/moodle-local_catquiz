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

namespace local_catquiz\local\attempt;

use advanced_testcase;
use context_system;
use local_catquiz\teststrategy\preselect_task\removeplayedquestions;
use local_catquiz\teststrategy\progress;
use question_engine;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/lib.php');

/**
 * A question already in the question usage is not drawn again, even if the progress missed it (#126).
 *
 * removeplayedquestions excluded only what the progress had recorded. A question handed out on a
 * path that did not register it - the first-question shortcut, a pilot item - was missing there
 * and stayed in the pool. The question usage records every administration regardless of path.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\teststrategy\preselect_task\removeplayedquestions
 */
final class removeplayedquestions_usage_test extends advanced_testcase {
    /**
     * A question in the usage but not in the progress leaves the pool.
     */
    public function test_a_question_in_the_usage_is_excluded(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();
        $administered = $generator->create_question('truefalse', null, ['category' => $category->id]);
        $fresh = $generator->create_question('truefalse', null, ['category' => $category->id]);

        $quba = question_engine::make_questions_usage_by_activity('mod_adaptivequiz', context_system::instance());
        $quba->set_preferred_behaviour('deferredfeedback');
        $quba->add_question(\question_bank::load_question($administered->id));
        $quba->start_all_questions();
        question_engine::save_questions_usage_by_activity($quba);
        $attemptid = (int) $DB->insert_record('adaptivequiz_attempt', (object) [
            'instance' => 55002, 'userid' => 2, 'uniqueid' => $quba->get_id(), 'attemptstate' => 'inprogress',
            'attemptstopcriteria' => '', 'questionsattempted' => 1, 'difficultysum' => 0,
            'standarderror' => 1, 'measure' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);

        // The progress has recorded nothing - as after a path that did not register the question.
        $progress = progress::load($attemptid, 'mod_adaptivequiz', 9, (object) []);
        $this->assertSame([], $progress->get_playedquestions());

        $context = [
            'progress' => $progress,
            'questions' => [
                $administered->id => (object) ['id' => $administered->id],
                $fresh->id => (object) ['id' => $fresh->id],
            ],
        ];
        (new removeplayedquestions())->run($context);

        $this->assertArrayNotHasKey($administered->id, $context['questions'], 'An administered question stayed in the pool.');
        $this->assertArrayHasKey($fresh->id, $context['questions']);
    }
}
