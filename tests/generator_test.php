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

/**
 * Tests for the local_catquiz test data generator.
 *
 * Regression guard: create_catquiz_questions() must import questions on the
 * supported platform. Moodle 4.5 has no mod_qbank activity module, so the
 * generator must use the course context there. A prior change created a
 * non-existent 'qbank' module, which made get_plugin_generator('mod_qbank')
 * fail before any question was imported and broke every Behat scenario.
 *
 * @package local_catquiz
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz_generator
 */
final class generator_test extends \advanced_testcase {
    /**
     * The generator imports questions into a valid context on this platform.
     *
     * @return void
     */
    public function test_create_catquiz_questions_imports_questions(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        /** @var \local_catquiz_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('local_catquiz');

        $before = $DB->count_records('question');
        $generator->create_catquiz_questions([
            'filepath' => 'local/catquiz/tests/fixtures/quiz-adaptivetest-Simulation-small.xml',
            'filename' => 'quiz-adaptivetest-Simulation-small.xml',
            'courseid' => $course->id,
        ]);
        $after = $DB->count_records('question');

        $this->assertGreaterThan(
            $before,
            $after,
            'The generator must import questions using a context valid on this Moodle version.'
        );
    }
}
