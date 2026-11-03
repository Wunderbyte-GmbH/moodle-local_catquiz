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

namespace local_catquiz\local\access;

use advanced_testcase;
use context_course;
use context_module;
use context_system;
use local_catquiz\shortcodes;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * Access to the feedback files of a test and to the shortcodes that list other people's results (issue #17).
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\local\access\feedback_file_access
 * @covers ::local_catquiz_pluginfile
 * @covers \local_catquiz\shortcodes
 */
final class feedback_file_access_test extends advanced_testcase {
    /**
     * A course with an activity, a participant and a person outside the course.
     *
     * @return array [course, module context, student, stranger, teacher]
     */
    private function setup_course(): array {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $adaptivequiz = $this->getDataGenerator()->get_plugin_generator('mod_adaptivequiz')->create_instance([
            'course' => $course->id, 'highestlevel' => 10, 'lowestlevel' => 1, 'standarderror' => 14,
            'attemptfeedbackeditor' => ['text' => '', 'format' => FORMAT_MOODLE],
        ]);
        $cm = get_coursemodule_from_instance('adaptivequiz', $adaptivequiz->id);
        return [
            $course,
            context_module::instance($cm->id),
            $this->getDataGenerator()->create_and_enrol($course, 'student'),
            $this->getDataGenerator()->create_user(),
            $this->getDataGenerator()->create_and_enrol($course, 'editingteacher'),
        ];
    }

    /**
     * Expects a refusal.
     *
     * @param callable $call
     * @param string $why
     */
    private function assert_refused(callable $call, string $why): void {
        try {
            $call();
        } catch (\moodle_exception $e) {
            $this->assertNotInstanceOf(\coding_exception::class, $e, $e->getMessage());
            return;
        }
        $this->fail('Allowed: ' . $why);
    }

    /**
     * Participants of the course load the files, also with the activity hidden; others do not.
     */
    public function test_files_of_an_activity(): void {
        global $PAGE;
        [$course, $context, $student, $stranger] = $this->setup_course();

        $this->setUser($student);
        feedback_file_access::require_access($context, $course);
        set_coursemodule_visible($context->instanceid, 0);
        \course_modinfo::clear_instance_cache();
        $PAGE = new \moodle_page();
        feedback_file_access::require_access($context, $course);

        $this->setUser($stranger);
        $PAGE = new \moodle_page();
        $this->assert_refused(fn() => feedback_file_access::require_access($context, $course), 'user outside the course');

        $this->setUser(0);
        $PAGE = new \moodle_page();
        $this->assert_refused(fn() => feedback_file_access::require_access($context, $course), 'not logged in');
    }

    /**
     * Template files outside an activity: logged-in users, no guests; other contexts: nobody.
     */
    public function test_files_outside_an_activity(): void {
        [$course, , $student] = $this->setup_course();

        $this->setUser($student);
        feedback_file_access::require_access(context_system::instance(), null);
        $this->assert_refused(
            fn() => feedback_file_access::require_access(context_course::instance($course->id), $course),
            'course context'
        );

        $this->setGuestUser();
        $this->assert_refused(fn() => feedback_file_access::require_access(context_system::instance(), null), 'guest');
    }

    /**
     * The pluginfile callback asks before it looks for the file.
     */
    public function test_pluginfile_refuses_before_serving(): void {
        global $PAGE;
        [$course, $context, , $stranger] = $this->setup_course();
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id, 'component' => 'local_catquiz', 'filearea' => 'feedback_files_1_1',
            'itemid' => 5, 'filepath' => '/', 'filename' => 'figure.png',
        ], 'image');
        $cm = get_coursemodule_from_id('adaptivequiz', $context->instanceid);

        $this->setUser($stranger);
        $PAGE = new \moodle_page();
        $this->assert_refused(
            fn() => local_catquiz_pluginfile(
                $course,
                $cm,
                $context,
                'feedback_files_1_1',
                [5, 'figure.png'],
                false,
                ['dontdie' => true]
            ),
            'pluginfile for a user outside the course'
        );
    }

    /**
     * The list of all attempts and the statistics are empty for participants.
     */
    public function test_shortcodes_listing_other_peoples_results(): void {
        global $COURSE;
        [$course, , $student, , $teacher] = $this->setup_course();
        $COURSE = $course;
        $next = fn() => '';

        $this->setUser($student);
        $this->assertSame('', shortcodes::allquizattempts('allquizattempts', [], null, null, $next));
        $this->assertSame('', shortcodes::catquizstatistics('catquizstatistics', ['globalscale' => 1], null, null, $next));

        $this->setUser($teacher);
        $this->assertSame('', shortcodes::allquizattempts('allquizattempts', [], null, null, $next), 'Site wide: managers only.');
        $this->assertNotSame('', shortcodes::catquizstatistics('catquizstatistics', ['globalscale' => 1], null, null, $next));

        $this->setAdminUser();
        $this->assertNotSame('', shortcodes::allquizattempts('allquizattempts', [], null, null, $next));
    }
}
