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

namespace local_catquiz\feedback;

use advanced_testcase;
use context_course;
use context_module;
use core_external\external_api;
use local_catquiz\catquiz_handler;
use local_catquiz\data\catscale_structure;
use local_catquiz\data\dataapi;
use local_catquiz\testenvironment;
use MoodleQuickForm;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');
require_once($CFG->libdir . '/formslib.php');

/**
 * The feedback of the CAT settings is edited one scale at a time (issue #51).
 *
 * With a large scale tree the form had three editors per scale - several hundred - and was no longer
 * delivered completely. Only the scale being edited has its fields now; the others carry their values in
 * a hidden field and get their fields as a fragment when they are opened.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\feedback\feedbackclass
 * @covers \local_catquiz\catquiz_handler::add_or_update_instance_callback
 * @covers \local_catquiz\catquiz_handler::instance_form_validation
 * @covers \local_catquiz\testenvironment::apply_jsonsaved_values
 */
final class feedback_lazy_editors_test extends advanced_testcase {
    /**
     * A scale.
     *
     * @param string $name
     * @param int $parentid
     * @return int
     */
    private function scale(string $name, int $parentid = 0): int {
        return dataapi::create_catscale(new catscale_structure([
            'name' => $name, 'parentid' => $parentid, 'minscalevalue' => -5, 'maxscalevalue' => 5,
            'timecreated' => time(), 'timemodified' => time(),
        ]));
    }

    /**
     * A scale tree: a root with the given number of subscales.
     *
     * @param int $subscales
     * @return int[] Root first.
     */
    private function tree(int $subscales): array {
        $ids = [$this->scale('Lazy root')];
        for ($i = 1; $i <= $subscales; $i++) {
            $ids[] = $this->scale('Lazy sub ' . $i, $ids[0]);
        }
        return $ids;
    }

    /**
     * The feedback part of the form, as the request and the saved settings give it.
     *
     * @param array $post The request; the scale and its selected subscales are added.
     * @param int[] $scaleids Root first; all of them selected.
     * @param array $defaults Values the form was set up with.
     * @return MoodleQuickForm
     */
    private function form(array $post, array $scaleids, array $defaults = []): MoodleQuickForm {
        $_POST = $post + ['catquiz_catscales' => $scaleids[0], 'numberoffeedbackoptionsselect' => 2];
        foreach (array_slice($scaleids, 1) as $scaleid) {
            $_POST['catquiz_subscalecheckbox_' . $scaleid] = 1;
        }
        $mform = new MoodleQuickForm('lazyfeedback', 'post', '');
        if ($post) {
            // Sent: the form takes its values from the request, as moodleform hands it over.
            $mform->updateSubmission($_POST, []);
        }
        $mform->_defaultValues = $defaults;
        $elements = [];
        feedbackclass::instance_form_definition($mform, $elements, null);
        $_POST = [];
        return $mform;
    }

    /**
     * The elements of a type in a form.
     *
     * @param MoodleQuickForm $mform
     * @param string $type
     * @return string[] Their names.
     */
    private function elements_of_type(MoodleQuickForm $mform, string $type): array {
        $names = [];
        foreach ($mform->_elements as $element) {
            if ($element->getType() === $type) {
                $names[] = $element->getName();
            }
        }
        return $names;
    }

    /**
     * A course with an adaptive quiz.
     *
     * @return array [course, adaptivequiz, cm]
     */
    private function activity(): array {
        $course = $this->getDataGenerator()->create_course();
        $adaptivequiz = $this->getDataGenerator()->get_plugin_generator('mod_adaptivequiz')->create_instance([
            'course' => $course->id, 'highestlevel' => 10, 'lowestlevel' => 1, 'standarderror' => 14,
            'attemptfeedbackeditor' => ['text' => '', 'format' => FORMAT_MOODLE],
        ]);
        return [$course, $adaptivequiz, get_coursemodule_from_instance('adaptivequiz', $adaptivequiz->id)];
    }

    /**
     * A file saved for a range of a scale of an activity.
     *
     * @param \stdClass $cm
     * @param int $scaleid
     * @return \stored_file
     */
    private function saved_file(\stdClass $cm, int $scaleid): \stored_file {
        return get_file_storage()->create_file_from_string([
            'contextid' => context_module::instance($cm->id)->id, 'component' => 'local_catquiz',
            'filearea' => 'feedback_files_' . $scaleid . '_1', 'itemid' => $cm->instance,
            'filepath' => '/', 'filename' => 'picture.png',
        ], 'picture');
    }

    /**
     * Two hundred subscales: the form has the editors, course choosers and colour pickers of one scale.
     */
    public function test_two_hundred_scales_have_the_fields_of_one(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $scaleids = $this->tree(200);

        $mform = $this->form([], $scaleids);

        $this->assertCount(2, $this->elements_of_type($mform, 'editor'), 'Two ranges of one scale.');
        $this->assertCount(2, $this->elements_of_type($mform, 'autocomplete'));
        $this->assertSame(
            ['feedbackeditor_scaleid_' . $scaleids[0] . '_1', 'feedbackeditor_scaleid_' . $scaleids[0] . '_2'],
            $this->elements_of_type($mform, 'editor'),
            'The scale of the test is edited first.'
        );
        foreach (array_slice($scaleids, 1) as $scaleid) {
            $this->assertTrue($mform->elementExists(feedbackclass::CARRIER . $scaleid));
            $this->assertTrue($mform->elementExists(feedbackclass::EDITBUTTON . $scaleid));
        }
        $this->assertSame((string) $scaleids[0], (string) $mform->getElementValue(feedbackclass::EDITSCALE));
    }

    /**
     * The button of a scale opens it with the values of its carrier, unsaved ones included; the scale
     * edited before keeps what its fields were sent with in its carrier.
     */
    public function test_the_button_of_a_scale_opens_it_with_its_carrier(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$root, $sub] = $this->tree(1);
        $carrier = [
            'feedbackeditor_scaleid_' . $sub . '_1' => ['text' => 'Unsaved for the subscale', 'format' => 1, 'itemid' => 0],
            'feedback_scaleid_limit_upper_' . $sub . '_1' => '1,5',
            'catquiz_courses_' . $sub . '_2' => ['7'],
            // Not a field of this scale: left out.
            'feedbackeditor_scaleid_' . $root . '_1' => ['text' => 'Smuggled', 'format' => 1, 'itemid' => 0],
        ];

        $mform = $this->form([
            feedbackclass::EDITSCALE => $root,
            feedbackclass::EDITBUTTON . $sub => 'Edit',
            feedbackclass::CARRIER . $sub => json_encode($carrier),
            'feedbackeditor_scaleid_' . $root . '_1' => ['text' => 'Typed for the root', 'format' => 1, 'itemid' => 5],
        ], [$root, $sub]);

        $this->assertSame(
            ['feedbackeditor_scaleid_' . $sub . '_1', 'feedbackeditor_scaleid_' . $sub . '_2'],
            $this->elements_of_type($mform, 'editor')
        );
        $this->assertSame((string) $sub, (string) $mform->getElementValue(feedbackclass::EDITSCALE));
        $editor = $mform->getElement('feedbackeditor_scaleid_' . $sub . '_1')->getValue();
        $this->assertSame('Unsaved for the subscale', $editor['text']);
        $this->assertGreaterThan(0, $editor['itemid'], 'Shown with a draft area of its own.');
        $this->assertEqualsWithDelta(
            1.5,
            (float) $mform->getElement('feedback_scaleid_limit_upper_' . $sub . '_1')->getValue(),
            1e-9
        );

        $rootvalues = json_decode($mform->getElementValue(feedbackclass::CARRIER . $root), true);
        $this->assertSame('Typed for the root', $rootvalues['feedbackeditor_scaleid_' . $root . '_1']['text']);
        $this->assertSame(5, $rootvalues['feedbackeditor_scaleid_' . $root . '_1']['itemid']);
    }

    /**
     * The values of the carriers count as fields when saving and validating; the carrier of the scale
     * being edited does not - its fields were in the form.
     */
    public function test_carriers_are_expanded_except_the_edited_one(): void {
        [$root, $sub] = [11, 12];
        $data = feedbackclass::expand_carriers([
            feedbackclass::EDITSCALE => $root,
            feedbackclass::CARRIER . $root => json_encode(['catquiz_courses_' . $root . '_1' => [3]]),
            feedbackclass::CARRIER . $sub => json_encode([
                'catquiz_courses_' . $sub . '_1' => ['4', 'x'],
                'feedback_scaleid_limit_lower_' . $sub . '_2' => '0,5',
                'feedbacklegend_scaleid_' . $sub . '_1' => '<b>Legend</b>',
                'catquiz_group_' . $sub . '_1' => 'kept',
            ]),
            'catquiz_group_' . $sub . '_1' => 'from the form',
        ]);

        $this->assertArrayNotHasKey('catquiz_courses_' . $root . '_1', $data, 'Cleared in the form: stays cleared.');
        $this->assertSame([4], $data['catquiz_courses_' . $sub . '_1']);
        $this->assertSame(0.5, $data['feedback_scaleid_limit_lower_' . $sub . '_2']);
        $this->assertSame('Legend', $data['feedbacklegend_scaleid_' . $sub . '_1']);
        $this->assertSame('from the form', $data['catquiz_group_' . $sub . '_1']);
        $this->assertArrayNotHasKey(feedbackclass::CARRIER . $sub, $data);
        $this->assertArrayNotHasKey(feedbackclass::EDITSCALE, $data);
    }

    /**
     * A gap in the ranges of a scale not being edited is shown at that scale - its fields are not in the
     * form, an error at them would not be seen and the form could not be saved.
     */
    public function test_errors_of_a_scale_not_edited_are_shown_at_its_summary(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$root, $sub] = $this->tree(1);
        $data = [
            'catquiz_catscales' => $root, 'numberoffeedbackoptionsselect' => 2,
            'catquiz_subscalecheckbox_' . $sub => 1, feedbackclass::EDITSCALE => $root,
            'feedback_scaleid_limit_lower_' . $root . '_1' => -5, 'feedback_scaleid_limit_upper_' . $root . '_1' => 0,
            'feedback_scaleid_limit_lower_' . $root . '_2' => 0, 'feedback_scaleid_limit_upper_' . $root . '_2' => 5,
            feedbackclass::CARRIER . $sub => json_encode([
                'feedback_scaleid_limit_lower_' . $sub . '_1' => -5, 'feedback_scaleid_limit_upper_' . $sub . '_1' => 0,
                'feedback_scaleid_limit_lower_' . $sub . '_2' => 1, 'feedback_scaleid_limit_upper_' . $sub . '_2' => 5,
            ]),
        ];

        $errors = catquiz_handler::instance_form_validation($data);

        $this->assertArrayNotHasKey('feedback_scaleid_limit_lower_' . $sub . '_2', $errors);
        $this->assertSame(get_string('nogapallowed', 'local_catquiz'), $errors[feedbackclass::SUMMARY . $sub] ?? null);
        foreach (array_keys($errors) as $field) {
            $this->assertStringNotContainsString('_' . $root . '_', $field, 'The scale of the test has no gap.');
        }
    }

    /**
     * The saved settings are set up without a draft area per editor: the files are copied for the scale
     * being edited, when it is shown.
     */
    public function test_loading_the_settings_copies_files_for_the_scale_shown_only(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$root, $sub] = $this->tree(1);
        [, $adaptivequiz, $cm] = $this->activity();
        foreach ([$root, $sub] as $scaleid) {
            $this->saved_file($cm, $scaleid);
        }
        $json = [
            'catquiz_catscales' => $root,
            'feedbackeditor_scaleid_' . $root . '_1' => '<img src="@@PLUGINFILE@@/picture.png">',
            'feedbackeditor_scaleid_' . $sub . '_1' => '<img src="@@PLUGINFILE@@/picture.png">',
        ];
        $defaults = [];
        (new testenvironment((object) [
            'componentid' => $adaptivequiz->id, 'component' => 'mod_adaptivequiz', 'json' => json_encode($json),
        ]))->apply_jsonsaved_values($defaults);
        $this->assertSame(0, $defaults['feedbackeditor_scaleid_' . $sub . '_1_editor']['itemid']);
        $usercontextid = \context_user::instance($USER->id)->id;
        $drafts = fn() => get_file_storage()->get_area_files($usercontextid, 'user', 'draft', false, 'id', false);
        $this->assertCount(0, $drafts());

        $mform = $this->form([], [$root, $sub], $defaults + ['coursemodule' => $cm->id, 'instance' => $adaptivequiz->id]);

        $this->assertCount(1, $drafts(), 'The picture of the scale shown, not the one of the other.');
        $editor = $mform->getElement('feedbackeditor_scaleid_' . $root . '_1')->getValue();
        $this->assertStringContainsString('draftfile.php', $editor['text']);
        $carrier = json_decode($mform->getElementValue(feedbackclass::CARRIER . $sub), true);
        $this->assertSame('<img src="@@PLUGINFILE@@/picture.png">', $carrier['feedbackeditor_scaleid_' . $sub . '_1']['text']);
        $this->assertSame(0, $carrier['feedbackeditor_scaleid_' . $sub . '_1']['itemid']);
    }

    /**
     * Saving with a scale that was not opened keeps its files and its text as saved.
     */
    public function test_saving_keeps_the_files_of_a_scale_not_opened(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$root, $sub] = $this->tree(1);
        [$course, $adaptivequiz, $cm] = $this->activity();
        $file = $this->saved_file($cm, $sub);
        $text = '<img src="@@PLUGINFILE@@/picture.png">';

        catquiz_handler::add_or_update_instance_callback((object) [
            'id' => $adaptivequiz->id, 'instance' => $adaptivequiz->id, 'coursemodule' => $cm->id,
            'course' => $course->id, 'section' => 1, 'catquiz_catscales' => $root,
            'catquiz_subscalecheckbox_' . $sub => 1,
            feedbackclass::EDITSCALE => $root,
            'feedbackeditor_scaleid_' . $root . '_1' => [
                'text' => 'Root', 'format' => 1, 'itemid' => file_get_unused_draft_itemid(),
            ],
            feedbackclass::CARRIER . $sub => json_encode([
                'feedbackeditor_scaleid_' . $sub . '_1' => ['text' => $text, 'format' => 1, 'itemid' => 0],
            ]),
        ]);

        $this->assertTrue(get_file_storage()->file_exists_by_hash($file->get_pathnamehash()), 'Its picture is kept.');
        $json = json_decode($DB->get_field('local_catquiz_tests', 'json', [
            'componentid' => $adaptivequiz->id, 'component' => 'mod_adaptivequiz',
        ]), true);
        $this->assertSame($text, $json['feedbackeditor_scaleid_' . $sub . '_1']);
        $this->assertSame('Root', $json['feedbackeditor_scaleid_' . $root . '_1']);
        $this->assertArrayNotHasKey(feedbackclass::CARRIER . $sub, $json);
    }

    /**
     * The fragment brings the fields of one scale, with the values of its carrier and its files in a
     * draft area; a scale that is gone gives a notice.
     */
    public function test_the_fragment_has_the_fields_of_one_scale(): void {
        global $PAGE;
        $this->resetAfterTest();
        $PAGE->set_url('/');
        $this->setAdminUser();
        [$root, $sub, $other] = $this->tree(2);
        [$course, $adaptivequiz, $cm] = $this->activity();
        $this->saved_file($cm, $sub);
        $args = [
            'context' => context_course::instance($course->id), 'scaleid' => $sub, 'rootscaleid' => $root,
            'nranges' => 2, 'cmid' => $cm->id, 'instanceid' => $adaptivequiz->id,
            'values' => json_encode([
                'feedbackeditor_scaleid_' . $sub . '_1' => ['text' => '<img src="@@PLUGINFILE@@/picture.png">', 'itemid' => 0],
                'feedbacklegend_scaleid_' . $sub . '_2' => 'Legend two',
            ]),
        ];

        $html = feedbackclass::panel_fragment($args);

        $this->assertStringNotContainsString('<form', $html);
        $this->assertStringContainsString('name="feedbackeditor_scaleid_' . $sub . '_1[text]"', $html);
        $this->assertStringContainsString('name="feedbackeditor_scaleid_' . $sub . '_2[text]"', $html);
        $this->assertStringNotContainsString('feedbackeditor_scaleid_' . $other . '_', $html);
        $this->assertStringNotContainsString('feedbackeditor_scaleid_' . $root . '_', $html);
        $this->assertStringContainsString('draftfile.php', $html, 'The picture, in a draft area.');
        $this->assertStringContainsString('value="Legend two"', $html);
        $this->assertSame(1, preg_match('/<input[^>]*name="feedbackeditor_scaleid_' . $sub . '_1\\[itemid\\]"[^>]*>/', $html, $m));
        $this->assertSame(1, preg_match('/value="(\\d+)"/', $m[0], $itemid));
        $this->assertGreaterThan(0, (int) $itemid[1]);

        $args['scaleid'] = 999999;
        $this->assertStringContainsString(
            get_string('feedbackpanelscalegone', 'local_catquiz'),
            feedbackclass::panel_fragment($args)
        );
    }

    /**
     * Only who may edit the activity gets the fragment, with the session key of the request, and only for
     * the activity named.
     */
    public function test_the_fragment_needs_the_right_to_edit_the_activity(): void {
        $this->resetAfterTest();
        [$root, $sub] = $this->tree(1);
        [$course, $adaptivequiz, $cm] = $this->activity();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $call = function (array $args) use ($course) {
            global $PAGE, $OUTPUT;
            // A request of its own: a page collects the JavaScript of one fragment only.
            $PAGE = new \moodle_page();
            $OUTPUT = new \bootstrap_renderer();
            return external_api::call_external_function('core_get_fragment', [
                'component' => 'local_catquiz', 'callback' => 'feedbackpanel',
                'contextid' => context_course::instance($course->id)->id,
                'args' => array_map(fn($k) => ['name' => $k, 'value' => (string) $args[$k]], array_keys($args)),
            ]);
        };
        $args = ['scaleid' => $sub, 'rootscaleid' => $root, 'nranges' => 2, 'cmid' => $cm->id,
            'instanceid' => $adaptivequiz->id, 'values' => '{}'];

        // Without the session key of the request: refused before anything is looked at.
        $this->setUser($teacher);
        $result = $call($args);
        $this->assertTrue($result['error']);
        $this->assertSame('missingparam', $result['exception']->errorcode);
        $this->assertStringContainsString('sesskey', $result['exception']->message);

        $this->setUser($student);
        $_POST['sesskey'] = sesskey();
        $result = $call($args);
        $this->assertTrue($result['error']);
        $this->assertSame('nopermissions', $result['exception']->errorcode);
        // Also for an activity not created yet, without its own context.
        $result = $call(['cmid' => 0, 'instanceid' => 0] + $args);
        $this->assertTrue($result['error']);
        $this->assertSame('nopermissions', $result['exception']->errorcode);

        $this->setUser($teacher);
        $_POST['sesskey'] = sesskey();
        $result = $call($args);
        $this->assertFalse($result['error']);
        $this->assertStringContainsString('feedbackeditor_scaleid_' . $sub . '_1', $result['data']['html']);

        // Another activity than the one of the instance named: refused.
        $args['instanceid'] = $adaptivequiz->id + 1;
        $this->assertTrue($call($args)['error']);
        $_POST = [];
    }

    /**
     * Adapter issue #2: a wrong session key is refused like a missing one; a teacher of another course gets
     * nothing of this course's activity, also when the course named is his own; a prohibition at the activity
     * wins over the right in the course; a scale that is not a root is no root of a tree.
     */
    public function test_the_fragment_refuses_the_wrong_caller(): void {
        global $DB;
        $this->resetAfterTest();
        [$root, $sub] = $this->tree(1);
        [$course, $adaptivequiz, $cm] = $this->activity();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $othercourse = $this->getDataGenerator()->create_course();
        $otherteacher = $this->getDataGenerator()->create_and_enrol($othercourse, 'editingteacher');
        $call = function (array $args, int $courseid) {
            global $PAGE, $OUTPUT;
            $PAGE = new \moodle_page();
            $OUTPUT = new \bootstrap_renderer();
            return external_api::call_external_function('core_get_fragment', [
                'component' => 'local_catquiz', 'callback' => 'feedbackpanel',
                'contextid' => context_course::instance($courseid)->id,
                'args' => array_map(fn($k) => ['name' => $k, 'value' => (string) $args[$k]], array_keys($args)),
            ]);
        };
        $args = ['scaleid' => $sub, 'rootscaleid' => $root, 'nranges' => 2, 'cmid' => $cm->id,
            'instanceid' => $adaptivequiz->id, 'values' => '{}'];
        try {
            $this->setUser($teacher);
            $_POST['sesskey'] = 'not-the-key';
            $result = $call($args, $course->id);
            $this->assertTrue($result['error']);
            $this->assertSame('invalidsesskey', $result['exception']->errorcode);

            // The teacher of another course, asking in his own course for this activity.
            $this->setUser($otherteacher);
            $_POST['sesskey'] = sesskey();
            $result = $call($args, $othercourse->id);
            $this->assertTrue($result['error']);
            $this->assertSame('nopermissions', $result['exception']->errorcode);

            // A prohibition at the activity wins over the right in the course.
            $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
            assign_capability(
                'moodle/course:manageactivities',
                CAP_PROHIBIT,
                $roleid,
                \context_module::instance($cm->id)->id,
                true
            );
            $this->setUser($teacher);
            $_POST['sesskey'] = sesskey();
            $result = $call($args, $course->id);
            $this->assertTrue($result['error']);
            $this->assertSame('nopermissions', $result['exception']->errorcode);
            unassign_capability('moodle/course:manageactivities', $roleid, \context_module::instance($cm->id)->id);

            // A subscale named as the root: no tree, the notice.
            $result = $call(['rootscaleid' => $sub] + $args, $course->id);
            $this->assertFalse($result['error']);
            $this->assertStringContainsString(
                get_string('feedbackpanelscalegone', 'local_catquiz'),
                $result['data']['html']
            );
        } finally {
            $_POST = [];
        }
    }
}
