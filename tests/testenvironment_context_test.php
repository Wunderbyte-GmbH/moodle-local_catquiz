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

/**
 * The context of a test environment follows its main scale (issue #127).
 *
 * update_object() overwrote the stored scale before comparing it, so the check for a change of
 * scale compared the new scale with itself. A test whose main scale changed stayed in the context
 * of the old scale.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\testenvironment
 */
final class testenvironment_context_test extends advanced_testcase {
    /**
     * Files a scale in the given CAT context.
     *
     * @param int $contextid
     * @return int The scale id.
     */
    private function scale_in_context(int $contextid): int {
        global $DB;

        return (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'name' => 'scale ' . $contextid, 'description' => '', 'parentid' => 0, 'contextid' => $contextid,
            'minmaxgroup' => '', 'minscalevalue' => -5, 'maxscalevalue' => 5, 'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * Files a test environment on the given scale and context.
     *
     * @param int $scaleid
     * @param int $contextid
     * @return int The test id.
     */
    private function test_on(int $scaleid, int $contextid): int {
        global $DB;

        return (int) $DB->insert_record('local_catquiz_tests', (object) [
            'componentid' => 1, 'component' => 'mod_adaptivequiz', 'catscaleid' => $scaleid, 'contextid' => $contextid,
            'courseid' => 1, 'name' => 'test', 'status' => 1, 'json' => json_encode(['catquiz_catscales' => $scaleid]),
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * Changing the main scale moves the test into the context of the new scale.
     */
    public function test_a_change_of_scale_moves_the_context(): void {
        global $DB;

        $this->resetAfterTest();
        $first = $this->scale_in_context(111000);
        $second = $this->scale_in_context(111001);
        $testid = $this->test_on($first, 111000);

        (new testenvironment((object) [
            'id' => $testid,
            'catscaleid' => $second,
            'json' => json_encode(['catquiz_catscales' => $second]),
        ]))->save_or_update();

        $stored = $DB->get_record('local_catquiz_tests', ['id' => $testid]);
        $this->assertEquals($second, $stored->catscaleid);
        $this->assertEquals(111001, $stored->contextid, 'The test stayed in the context of its old scale.');
    }

    /**
     * Saving without a change of scale leaves the context as it is.
     */
    public function test_saving_without_a_change_keeps_the_context(): void {
        global $DB;

        $this->resetAfterTest();
        $scale = $this->scale_in_context(111000);
        // The context was set deliberately apart from the scale's; an unchanged scale must not touch it.
        $testid = $this->test_on($scale, 111005);

        (new testenvironment((object) [
            'id' => $testid,
            'catscaleid' => $scale,
            'json' => json_encode(['catquiz_catscales' => $scale]),
        ]))->save_or_update();

        $this->assertEquals(111005, $DB->get_field('local_catquiz_tests', 'contextid', ['id' => $testid]));
    }

    /**
     * A new test environment gets the context of its scale.
     */
    public function test_a_new_test_gets_the_context_of_its_scale(): void {
        global $DB;

        $this->resetAfterTest();
        $scale = $this->scale_in_context(111002);

        $test = new testenvironment((object) [
            'componentid' => 2,
            'component' => 'mod_adaptivequiz',
            'catscaleid' => $scale,
            'name' => 'new',
            'courseid' => 1,
            'json' => json_encode(['catquiz_catscales' => $scale]),
        ]);
        $test->save_or_update();

        $stored = $DB->get_record('local_catquiz_tests', ['componentid' => 2, 'component' => 'mod_adaptivequiz']);
        $this->assertEquals(111002, $stored->contextid);
    }
}
