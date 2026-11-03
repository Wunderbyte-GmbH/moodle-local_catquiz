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

namespace local_catquiz\output;

use advanced_testcase;
use local_catquiz\teststrategy\feedback_helper;
use local_catquiz\teststrategy\feedbackgenerator;
use local_catquiz\teststrategy\feedbackgenerator\graphicalsummary;
use local_catquiz\teststrategy\feedbacksettings;
use local_catquiz\teststrategy\progress;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * One feedback area that cannot be built does not take the rest of the feedback down (issue #152).
 *
 * The quiz history of an attempt in the older format threw - and with it the whole feedback page, every other
 * area of it. Now the area says it could not be shown, the reason goes to debugging(), and the rest stays.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\output\attemptfeedback::feedback_of
 * @covers \local_catquiz\teststrategy\feedbackgenerator\graphicalsummary::get_studentfeedback
 */
final class feedback_area_isolation_test extends advanced_testcase {
    /**
     * A generator whose feedback throws.
     *
     * @param array $audiences
     * @return feedbackgenerator
     */
    private function failing(array $audiences): feedbackgenerator {
        $generator = $this->getMockBuilder(graphicalsummary::class)
            ->setConstructorArgs([new feedbacksettings(LOCAL_CATQUIZ_STRATEGY_LOWESTSUB), new feedback_helper()])
            ->onlyMethods(['get_feedback', 'get_audiences'])
            ->getMock();
        $generator->method('get_feedback')->willThrowException(new \TypeError('count(): Argument #1 must be of type Countable'));
        $generator->method('get_audiences')->willReturn($audiences);
        return $generator;
    }

    /**
     * The area that throws gives a notice in its place, for its audience, and tells the developer why.
     */
    public function test_a_failing_area_gives_a_notice_instead_of_the_page(): void {
        $this->resetAfterTest();
        // Without an attempt: the method reads nothing of it.
        $feedback = (new \ReflectionClass(attemptfeedback::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod($feedback, 'feedback_of');

        $out = $method->invoke($feedback, $this->failing([feedbackgenerator::AUDIENCE_STUDENT]), []);

        $this->assertSame(get_string('quizgraphicalsummary', 'local_catquiz'), $out['studentfeedback']['heading']);
        $this->assertStringContainsString('local_catquiz-feedbackarea-notice', $out['studentfeedback']['content']);
        $this->assertStringContainsString(get_string('feedbackareanotshown', 'local_catquiz'), $out['studentfeedback']['content']);
        $this->assertSame([], $out['teacherfeedback']);
        $messages = array_map(fn($debug) => $debug->message, $this->getDebuggingMessages());
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('graphicalsummary could not be built: count()', $messages[0]);
        $this->resetDebugging();

        $out = $method->invoke($feedback, $this->failing(['teacher']), []);
        $this->assertSame([], $out['studentfeedback']);
        $this->assertStringContainsString('local_catquiz-feedbackarea-notice', $out['teacherfeedback']['content']);
        $this->resetDebugging();
    }

    /**
     * The quiz history of an attempt without a row: no chart, no table, no error - the description stays.
     */
    public function test_an_empty_quiz_history(): void {
        global $DB;
        $this->resetAfterTest();
        $scaleid = (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'parentid' => 0, 'name' => 'Global 152', 'contextid' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $progress = $this->getMockBuilder(progress::class)->disableOriginalConstructor()
            ->onlyMethods(['get_quiz_settings', 'get_played_pilot_questions'])->getMock();
        $progress->method('get_quiz_settings')->willReturn((object) ['catquiz_catscales' => $scaleid]);
        $progress->method('get_played_pilot_questions')->willReturn([]);
        $generator = $this->getMockBuilder(graphicalsummary::class)
            ->setConstructorArgs([new feedbacksettings(LOCAL_CATQUIZ_STRATEGY_LOWESTSUB), new feedback_helper()])
            ->onlyMethods(['get_progress'])->getMock();
        $generator->method('get_progress')->willReturn($progress);

        $out = $generator->get_studentfeedback(['graphicalsummary_data' => []]);

        $this->assertStringContainsString('Global 152', $out['content']);
        $this->assertStringNotContainsString('chart-area', $out['content']);
        $this->assertStringNotContainsString('<table', $out['content']);
    }
}
