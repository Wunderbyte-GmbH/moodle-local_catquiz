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
use context_module;
use context_system;
use moodle_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * Saving and copying templates of the CAT settings (issue #89).
 *
 * Two actions with explicit ids: overwrite exactly the chosen template, or save the settings as a new one -
 * the source stays as it is. Names of templates are unique (trimmed, without regard to case). The activity
 * keeps a reference to its template, and the files of its written feedback go with a template saved from it.
 * Before, one checkbox did either, depending on whether the typed name matched the old one.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\testenvironment
 * @covers \local_catquiz\catquiz_handler::add_or_update_instance_callback
 * @covers \local_catquiz\catquiz_handler::template_action
 * @covers \local_catquiz\catquiz_handler::instance_form_validation
 */
final class testenvironment_template_test extends advanced_testcase {
    /** @var int A root scale with a context. */
    private int $scaleid;

    /** @var stdClass The activity. */
    private stdClass $activity;

    /**
     * A scale and an activity.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $contextid = (int) $DB->insert_record('local_catquiz_catcontext', (object) [
            'name' => 'Template context', 'description' => '', 'descriptionformat' => FORMAT_HTML,
            'starttimestamp' => time() - 100, 'endtimestamp' => time() + 10000,
            'timecreated' => time(), 'timemodified' => time(), 'usermodified' => 0,
        ]);
        $this->scaleid = (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'name' => 'Template scale', 'parentid' => 0, 'contextid' => $contextid,
            'minscalevalue' => -5, 'maxscalevalue' => 5, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $course = $this->getDataGenerator()->create_course();
        $this->activity = $this->getDataGenerator()->create_module('adaptivequiz', [
            'course' => $course->id,
            'attemptfeedbackeditor' => ['text' => '', 'format' => FORMAT_HTML],
        ]);
    }

    /**
     * A template, as the form of an activity leaves one.
     *
     * @param string $name
     * @param int $min The minimum number of questions, to tell the settings apart.
     * @return int
     */
    private function template(string $name, int $min): int {
        return testenvironment::create_template($this->settings($min), $this->scaleid, $name);
    }

    /**
     * Settings of a test.
     *
     * @param int $min
     * @return stdClass
     */
    private function settings(int $min): stdClass {
        return (object) [
            'catquiz_catscales' => $this->scaleid,
            'maxquestionsgroup' => (object) ['catquiz_minquestions' => $min, 'catquiz_maxquestions' => 20],
        ];
    }

    /**
     * The submitted activity form.
     *
     * @param array $fields
     * @return stdClass
     */
    private function submission(array $fields): stdClass {
        return (object) ($fields + [
            'id' => $this->activity->id,
            'course' => $this->activity->course,
            'coursemodule' => $this->activity->cmid,
            'catquiz_catscales' => $this->scaleid,
            'maxquestionsgroup' => (object) ['catquiz_minquestions' => 7, 'catquiz_maxquestions' => 20],
            'choosetemplate' => 0,
        ]);
    }

    /**
     * The minimum number of questions in a stored test environment.
     *
     * @param int $id
     * @return int
     */
    private function minimum_of(int $id): int {
        global $DB;
        return (int) json_decode($DB->get_field('local_catquiz_tests', 'json', ['id' => $id]))
            ->maxquestionsgroup->catquiz_minquestions;
    }

    /**
     * Overwriting changes exactly the chosen template, under its id; every other template stays bitwise.
     */
    public function test_update_overwrites_exactly_the_chosen_template(): void {
        global $DB;
        $chosen = $this->template('A', 1);
        $other = $this->template('B', 2);
        $before = $DB->get_record('local_catquiz_tests', ['id' => $other]);
        $created = (int) $DB->get_field('local_catquiz_tests', 'timecreated', ['id' => $chosen]);
        $this->waitForSecond();

        $this->assertSame($chosen, testenvironment::update_template($chosen, $this->settings(9), $this->scaleid));

        $this->assertSame(9, $this->minimum_of($chosen));
        $this->assertSame('A', $DB->get_field('local_catquiz_tests', 'name', ['id' => $chosen]), 'No new name given.');
        $this->assertSame($created, (int) $DB->get_field('local_catquiz_tests', 'timecreated', ['id' => $chosen]));
        $this->assertEquals($before, $DB->get_record('local_catquiz_tests', ['id' => $other]));
        $this->assertSame(2, $DB->count_records('local_catquiz_tests', ['componentid' => 0]));

        testenvironment::update_template($chosen, $this->settings(9), $this->scaleid, 'A renamed');
        $this->assertSame('A renamed', $DB->get_field('local_catquiz_tests', 'name', ['id' => $chosen]), 'A rename, no copy.');
        $this->assertSame(2, $DB->count_records('local_catquiz_tests', ['componentid' => 0]));
    }

    /**
     * A new template has an id of its own, no instance, course or parent, its own creation time; the source
     * stays bitwise as it was.
     */
    public function test_copy_leaves_the_source_as_it_is(): void {
        global $DB;
        $source = $this->template('Source', 3);
        $DB->set_field('local_catquiz_tests', 'timecreated', 1000, ['id' => $source]);
        $before = $DB->get_record('local_catquiz_tests', ['id' => $source]);

        $copy = testenvironment::create_template($this->settings(4), $this->scaleid, 'Copy');

        $this->assertNotSame($source, $copy);
        $this->assertEquals($before, $DB->get_record('local_catquiz_tests', ['id' => $source]));
        $record = $DB->get_record('local_catquiz_tests', ['id' => $copy]);
        $this->assertSame(0, (int) $record->componentid);
        $this->assertSame(0, (int) $record->parentid);
        $this->assertSame(0, (int) $record->courseid);
        $this->assertSame('mod_adaptivequiz', $record->component);
        $this->assertSame(LOCAL_CATQUIZ_STATUS_TEST_ACTIVE, (int) $record->status);
        $this->assertGreaterThan(1000, (int) $record->timecreated);
        $this->assertSame(
            (int) $DB->get_field('local_catquiz_catscales', 'contextid', ['id' => $this->scaleid]),
            (int) $record->contextid
        );
        $this->assertSame(4, $this->minimum_of($copy));
    }

    /**
     * Names of templates are unique - trimmed and without regard to case; a template may keep its own name.
     */
    public function test_names_are_unique(): void {
        $first = $this->template('Basis Test', 1);
        $second = $this->template('Other', 2);

        $this->assertTrue(testenvironment::template_name_taken(' basis test '));
        $this->assertFalse(testenvironment::template_name_taken('Basis Test', $first), 'Its own name.');
        $this->assertTrue(testenvironment::template_name_taken(''), 'A template needs a name.');

        foreach (
            [
                fn() => testenvironment::create_template($this->settings(1), $this->scaleid, 'BASIS TEST'),
                fn() => testenvironment::update_template($second, $this->settings(1), $this->scaleid, 'basis test'),
                fn() => testenvironment::update_template(999999, $this->settings(1), $this->scaleid),
            ] as $i => $call
        ) {
            try {
                $call();
                $this->fail("Case $i was accepted.");
            } catch (moodle_exception $e) {
                $this->assertContains($e->errorcode, ['templatenametaken', 'templatenotfound']);
            }
        }
        // An activity's test environment is no template, and cannot be overwritten as one.
        $this->assertFalse(testenvironment::is_template(0));
    }

    /**
     * The form: overwriting needs a chosen template, a new template a name that is free.
     */
    public function test_the_form_checks_the_action(): void {
        $taken = $this->template('Taken', 1);
        $validate = fn(array $data) => array_intersect_key(
            catquiz_handler::instance_form_validation($data + ['numberoffeedbackoptionsselect' => 1]),
            array_flip(['testenvironment_templateaction', 'testenvironment_name'])
        );

        $this->assertArrayHasKey('testenvironment_templateaction', $validate([
            'testenvironment_templateaction' => catquiz_handler::TEMPLATE_ACTION_UPDATE, 'choosetemplate' => 0,
        ]));
        $this->assertArrayHasKey('testenvironment_name', $validate([
            'testenvironment_templateaction' => catquiz_handler::TEMPLATE_ACTION_COPY, 'testenvironment_name' => ' ',
        ]));
        $this->assertArrayHasKey('testenvironment_name', $validate([
            'testenvironment_templateaction' => catquiz_handler::TEMPLATE_ACTION_COPY, 'testenvironment_name' => 'taken',
        ]));
        $this->assertSame([], $validate([
            'testenvironment_templateaction' => catquiz_handler::TEMPLATE_ACTION_UPDATE, 'choosetemplate' => $taken,
            'testenvironment_name' => 'Taken',
        ]));
        $this->assertSame([], $validate(['testenvironment_templateaction' => catquiz_handler::TEMPLATE_ACTION_NONE]));
    }

    /**
     * A form from before the issue: the checkbox with the template's own name updates it, another name copies.
     */
    public function test_the_old_checkbox_is_read_as_before(): void {
        $chosen = $this->template('Old', 1);
        $action = fn(array $data) => catquiz_handler::template_action((object) $data);

        $this->assertSame(catquiz_handler::TEMPLATE_ACTION_UPDATE, $action([
            'testenvironment_addoredittemplate' => 1, 'testenvironment_name' => 'Old', 'choosetemplate' => $chosen,
        ]));
        $this->assertSame(catquiz_handler::TEMPLATE_ACTION_COPY, $action([
            'testenvironment_addoredittemplate' => 1, 'testenvironment_name' => 'New', 'choosetemplate' => $chosen,
        ]));
        $this->assertSame(catquiz_handler::TEMPLATE_ACTION_NONE, $action([
            'testenvironment_addoredittemplate' => 1, 'testenvironment_name' => '',
        ]));
        $this->assertSame(catquiz_handler::TEMPLATE_ACTION_NONE, $action([]));
    }

    /**
     * Saving the activity as a new template: the activity refers to it, and stores no action to repeat; the
     * files of its written feedback are copied to the template, where loading it takes them; the activity
     * keeps its own. Overwriting the template later changes neither the activity nor another template.
     */
    public function test_the_activity_saves_and_overwrites_templates(): void {
        global $DB;
        $modulecontext = context_module::instance($this->activity->cmid);
        $systemcontext = context_system::instance();
        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => $modulecontext->id, 'component' => 'local_catquiz', 'filearea' => 'feedback_files_5_1',
            'itemid' => $this->activity->id, 'filepath' => '/', 'filename' => 'picture.png',
        ], 'image');
        $unrelated = $this->template('Unrelated', 2);
        $unrelatedbefore = $DB->get_record('local_catquiz_tests', ['id' => $unrelated]);

        catquiz_handler::add_or_update_instance_callback($this->submission([
            'testenvironment_templateaction' => catquiz_handler::TEMPLATE_ACTION_COPY,
            'testenvironment_name' => 'From the activity',
        ]));

        $templateid = (int) $DB->get_field(
            'local_catquiz_tests',
            'id',
            ['name' => 'From the activity', 'componentid' => 0]
        );
        $this->assertGreaterThan(0, $templateid);
        $this->assertSame(7, $this->minimum_of($templateid));
        $activitytest = $DB->get_record('local_catquiz_tests', ['componentid' => $this->activity->id]);
        $this->assertSame($templateid, (int) $activitytest->parentid);
        $this->assertSame(
            catquiz_handler::TEMPLATE_ACTION_NONE,
            json_decode($activitytest->json)->testenvironment_templateaction,
            'Stored with the activity, the action would be done again at its next save.'
        );
        $intemplate = fn(string $area, string $file) => $fs->file_exists(
            $systemcontext->id,
            'local_catquiz',
            $area,
            $templateid,
            '/',
            $file
        );
        $this->assertTrue($intemplate('feedback_files_5_1', 'picture.png'));
        $this->assertTrue($fs->file_exists(
            $modulecontext->id,
            'local_catquiz',
            'feedback_files_5_1',
            (int) $this->activity->id,
            '/',
            'picture.png'
        ));

        // The activity is changed and overwrites its template; the template's old files are replaced.
        $fs->delete_area_files($modulecontext->id, 'local_catquiz', 'feedback_files_5_1', $this->activity->id);
        $fs->create_file_from_string([
            'contextid' => $modulecontext->id, 'component' => 'local_catquiz', 'filearea' => 'feedback_files_6_1',
            'itemid' => $this->activity->id, 'filepath' => '/', 'filename' => 'other.png',
        ], 'image');
        catquiz_handler::add_or_update_instance_callback($this->submission([
            'testenvironment_templateaction' => catquiz_handler::TEMPLATE_ACTION_UPDATE,
            'choosetemplate' => $templateid,
            'maxquestionsgroup' => (object) ['catquiz_minquestions' => 8, 'catquiz_maxquestions' => 20],
        ]));

        $this->assertSame(8, $this->minimum_of($templateid));
        $this->assertSame(1, $DB->count_records('local_catquiz_tests', ['name' => 'From the activity']));
        $this->assertEquals($unrelatedbefore, $DB->get_record('local_catquiz_tests', ['id' => $unrelated]));
        $this->assertFalse($intemplate('feedback_files_5_1', 'picture.png'));
        $this->assertTrue($intemplate('feedback_files_6_1', 'other.png'));

        // Saved without an action, the activity stays based on the template it was loaded from.
        catquiz_handler::add_or_update_instance_callback($this->submission(['choosetemplate' => $templateid]));
        $parent = (int) $DB->get_field('local_catquiz_tests', 'parentid', ['componentid' => $this->activity->id]);
        $this->assertSame($templateid, $parent);
        $this->assertSame(8, $this->minimum_of($templateid), 'The template is not changed by saving the activity.');
    }

    /**
     * Without the right to manage templates, no template is written - also not by a crafted submission.
     */
    public function test_no_template_without_the_capability(): void {
        global $DB;
        $teacher = $this->getDataGenerator()->create_and_enrol(get_course($this->activity->course), 'editingteacher');
        $this->setUser($teacher);
        $this->expectException(\required_capability_exception::class);
        try {
            catquiz_handler::add_or_update_instance_callback($this->submission([
                'testenvironment_templateaction' => catquiz_handler::TEMPLATE_ACTION_COPY,
                'testenvironment_name' => 'Crafted',
            ]));
        } finally {
            $this->assertFalse($DB->record_exists('local_catquiz_tests', ['name' => 'Crafted']));
        }
    }
}
