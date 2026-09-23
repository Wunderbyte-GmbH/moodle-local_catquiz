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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * Deterministic coverage for the "Testitem added to CAT scale" event.
 *
 * The Behat scenario catscales_attempt_management used to assert this import
 * event on page 1 of the event-log table. With timecreated DESC and a 10-row
 * page, the many same-second import events land on later pages
 * non-deterministically, which made the assertion flaky. Event emission is a
 * data-layer concern, so it is verified here instead: importing the fixture
 * scale must raise at least one testiteminscale_added event.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\event\testiteminscale_added
 */
final class eventlog_testitemadded_test extends advanced_testcase {
    /**
     * Importing test items into a scale raises the testiteminscale_added event.
     *
     * @return void
     */
    public function test_import_raises_testiteminscale_added_event(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_catquiz');

        $sink = $this->redirectEvents();

        $generator->create_catquiz_questions([
            'filepath' => 'local/catquiz/tests/fixtures/quiz-adaptivetest-Simulation-small.xml',
            'filename' => 'quiz-adaptivetest-Simulation-small.xml',
            'courseid' => $course->id,
        ]);
        $generator->create_catquiz_importedcatscales([
            'filepath' => 'local/catquiz/tests/fixtures/simulation_small.csv',
            'filename' => 'simulation_small.csv',
        ]);

        $events = $sink->get_events();
        $sink->close();

        $added = array_filter(
            $events,
            fn ($e) => $e instanceof \local_catquiz\event\testiteminscale_added
        );

        $this->assertNotEmpty(
            $added,
            'Importing the fixture scale did not raise any testiteminscale_added event.'
        );
    }
}
