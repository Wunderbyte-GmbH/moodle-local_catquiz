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

namespace local_catquiz\teststrategy\feedbackgenerator;

use advanced_testcase;
use local_catquiz\teststrategy\feedback_helper;
use local_catquiz\teststrategy\feedbacksettings;
use local_catquiz\teststrategy\progress;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * The written feedback from scale records as the stored attempt delivers them (issue #77).
 *
 * The result page reads the attempt with json_decode(..., true): the scales are arrays then, not objects,
 * keyed by id - or by position when they were stored as a list. The feedback renders all the same, without a
 * warning or a developer notice, main scale first, then by name, a scale without a name last.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\teststrategy\feedbackgenerator\customscalefeedback
 */
final class customscalefeedback_arrays_test extends advanced_testcase {
    /** @var array Warnings and notices PHP raised while rendering. */
    private array $phpissues = [];

    /**
     * How the stored attempt may hold the scales: keyed by id, or as a list.
     *
     * @return array
     */
    public static function stored_shapes(): array {
        return [
            'object keyed by id' => [true],
            'list' => [false],
        ];
    }

    /**
     * Renders the feedback of four scales whose records come out of the stored JSON as arrays.
     *
     * Ids and names run in opposite directions: 101 is "Zeta", 102 is "Alpha"; 103 has no name.
     *
     * @param bool $keyedbyid
     * @dataProvider stored_shapes
     */
    public function test_scales_as_arrays_render_in_order(bool $keyedbyid): void {
        $this->resetAfterTest();
        set_debugging(DEBUG_DEVELOPER);

        $quizsettings = ['numberoffeedbackoptionsselect' => '1'];
        $abilities = [];
        foreach ([100 => 'MAINTEXT', 101 => 'ZETATEXT', 102 => 'ALPHATEXT', 103 => 'NONAMETEXT'] as $scaleid => $text) {
            $quizsettings["feedback_scaleid_limit_lower_{$scaleid}_1"] = '-5';
            $quizsettings["feedback_scaleid_limit_upper_{$scaleid}_1"] = '5';
            $quizsettings["feedbackeditor_scaleid_{$scaleid}_1"] = (object) ['text' => "<p>$text</p>", 'format' => '1'];
            $abilities[$scaleid] = ['value' => '0.5', 'toreport' => true];
        }
        $records = [
            101 => (object) ['id' => 101, 'name' => 'Zeta', 'parentid' => 100],
            100 => (object) ['id' => 100, 'name' => 'Gesamt', 'parentid' => 0],
            103 => (object) ['id' => 103, 'parentid' => 100],
            102 => (object) ['id' => 102, 'name' => 'Alpha', 'parentid' => 100],
        ];
        // As the attempt stores and the result page reads it.
        $stored = json_encode([
            'catscales' => $keyedbyid ? $records : array_values($records),
            'customscalefeedback_abilities' => $abilities,
        ]);
        $decoded = json_decode($stored, true);
        $this->assertIsArray(reset($decoded['catscales']), 'Fixture: the records are arrays now.');

        $feedbackdata = [
            'testid' => 1,
            'catscaleid' => 100,
            'attemptid' => '1',
            'contextid' => '2',
            'catscales' => $decoded['catscales'],
            'customscalefeedback_abilities' => $decoded['customscalefeedback_abilities'],
            'quizsettings' => $quizsettings,
        ];
        $progress = $this->getMockBuilder(progress::class)
            ->onlyMethods(['get_quiz_settings', 'get_num_answered_productive_questions', 'get_fraction_for_scale'])
            ->getMock();
        $progress->method('get_quiz_settings')->willReturn((object) $quizsettings);
        $progress->method('get_num_answered_productive_questions')->willReturn(3);
        $progress->method('get_fraction_for_scale')->willReturn(0.5);
        $generator = $this->getMockBuilder(customscalefeedback::class)
            ->onlyMethods(['get_progress'])
            ->setConstructorArgs([new feedbacksettings(LOCAL_CATQUIZ_STRATEGY_RELSUBS), new feedback_helper()])
            ->getMock();
        $generator->method('get_progress')->willReturn($progress);

        set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
            $this->phpissues[] = "$errstr ($errfile:$errline)";
            return true;
        }, E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE | E_DEPRECATED | E_USER_DEPRECATED);
        try {
            $content = $generator->get_feedback($feedbackdata)['studentfeedback']['content'] ?? '';
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $this->phpissues, 'PHP raised: ' . implode('; ', $this->phpissues));
        $this->assertDebuggingNotCalled();
        $positions = array_map(fn($text) => strpos($content, $text), ['MAINTEXT', 'ALPHATEXT', 'ZETATEXT', 'NONAMETEXT']);
        $this->assertNotContains(false, $positions, 'A scale is missing from the feedback: ' . $content);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions, 'Not: main scale, then by name, the one without a name last.');
    }
}
