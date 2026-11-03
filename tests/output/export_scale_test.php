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

/**
 * The statistics export works without a scale parameter (issue #141).
 *
 * In production the CSV export stopped with "required parameter scaleid missing". The scale is now
 * taken from the test or the course where it is unambiguous, and has to be chosen where it is not.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\output\catquizstatistics
 */
final class export_scale_test extends advanced_testcase {
    /**
     * A scale.
     *
     * @param int $parentid
     * @return int
     */
    private function scale(int $parentid = 0): int {
        global $DB;
        return (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'name' => 'Scale ' . random_string(4), 'parentid' => $parentid, 'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * The CAT settings of a test in a course.
     *
     * @param int $testid
     * @param int $courseid
     * @param int $scaleid
     * @return void
     */
    private function test(int $testid, int $courseid, int $scaleid): void {
        global $DB;
        $DB->insert_record('local_catquiz_tests', (object) [
            'componentid' => $testid, 'component' => 'mod_adaptivequiz', 'courseid' => $courseid, 'catscaleid' => $scaleid,
            'json' => '{}', 'status' => 1, 'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * Without a scale: the test's scale, or the course's single one.
     */
    public function test_scale_is_derived_from_test_or_course(): void {
        $this->resetAfterTest();

        $a = $this->scale();
        $b = $this->scale();
        $this->test(501, 41, $a);
        $this->test(502, 41, $a);
        $this->test(503, 42, $b);
        $this->test(504, 42, $a);

        $this->assertSame($a, catquizstatistics::resolve_scale_id(0, 41, 502), 'The test has its scale.');
        $this->assertSame($b, catquizstatistics::resolve_scale_id(0, 42, 503));
        $this->assertSame($a, catquizstatistics::resolve_scale_id(0, 41, null), 'One scale in the course.');
    }

    /**
     * A scale asked for is kept - a subscale included.
     */
    public function test_scale_asked_for_is_kept(): void {
        $this->resetAfterTest();

        $root = $this->scale();
        $sub = $this->scale($root);
        $this->test(501, 41, $root);

        $this->assertSame($sub, catquizstatistics::resolve_scale_id($sub, 41, 501));
    }

    /**
     * Nothing is guessed: several scales in the course, no test or course, an unknown scale.
     */
    public function test_ambiguous_or_unknown_scale_is_refused(): void {
        $this->resetAfterTest();

        $a = $this->scale();
        $b = $this->scale();
        $this->test(503, 42, $b);
        $this->test(504, 42, $a);

        $cases = [
            'several scales in the course' => [0, 42, null],
            'no test, no course' => [0, null, null],
            'test without settings' => [0, 42, 999],
            'unknown scale' => [$b + 1000, 42, null],
        ];
        foreach ($cases as $name => [$scaleid, $courseid, $testid]) {
            try {
                catquizstatistics::resolve_scale_id($scaleid, $courseid, $testid);
                $this->fail("$name: no scale must be guessed.");
            } catch (\moodle_exception $e) {
                $this->assertSame('error:exportscaleunresolved', $e->errorcode, $name);
            }
        }
    }

    /**
     * Both export scripts resolve the scale instead of requiring it.
     */
    public function test_exports_do_not_require_the_scale(): void {
        global $CFG;

        foreach (['export_statistics_csv.php', 'export_feedback_csv.php'] as $script) {
            $source = file_get_contents($CFG->dirroot . '/local/catquiz/' . $script);
            $this->assertStringNotContainsString("required_param('scaleid'", $source, $script);
            $this->assertStringContainsString('catquizstatistics::resolve_scale_id(', $source, $script);
        }
    }
}
