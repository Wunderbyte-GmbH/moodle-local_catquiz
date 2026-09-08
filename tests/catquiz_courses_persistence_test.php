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

/**
 * Roundtrip regression test for the persistence of the per-scale course
 * selection (catquiz_courses_<scaleid>_<rangeid>).
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;
use stdClass;

/**
 * Roundtrip regression test for the per-scale course selection.
 *
 * Behat scenario 001 (catquiz_settings.feature) fails when, after saving and
 * reopening the activity settings, the previously chosen "Subscription to a
 * course" (catquiz_courses_<scaleid>_<rangeid>) is no longer shown as selected.
 *
 * That end-to-end failure can originate in two very different layers:
 *   1. the PHP/JSON persistence (save settings JSON -> reload testenvironment ->
 *      data_preprocessing -> form default), or
 *   2. the browser form interaction (the visible autocomplete component not
 *      writing the value into the underlying native <select> that is actually
 *      submitted).
 *
 * This test pins down layer 1 in isolation: it drives the real save path
 * (catquiz_handler::add_or_update_instance_callback) and the real reload path
 * (catquiz_handler::data_preprocessing) with no browser involved. If this test
 * is green while Behat 001 is red, the loss is provably in the browser/form
 * interaction, not in the persistence layer.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\catquiz_handler::add_or_update_instance_callback
 * @covers     \local_catquiz\catquiz_handler::data_preprocessing
 * @covers     \local_catquiz\testenvironment::apply_jsonsaved_values
 */
final class catquiz_courses_persistence_test extends advanced_testcase {
    /**
     * Creates a course and an adaptivequiz instance and returns both.
     *
     * @return array [\stdClass $course, \stdClass $adaptivequiz]
     */
    private function make_activity(): array {
        $course = $this->getDataGenerator()->create_course();
        $adaptivequiz = $this->getDataGenerator()->get_plugin_generator('mod_adaptivequiz')
            ->create_instance([
                'course' => $course->id,
                'highestlevel' => 10,
                'lowestlevel' => 1,
                'standarderror' => 14,
                'attemptfeedbackeditor' => ['text' => '', 'format' => FORMAT_MOODLE],
            ]);
        return [$course, $adaptivequiz];
    }

    /**
     * Builds the quiz settings object the adaptivequiz adapter hands to catquiz.
     *
     * @param int $instanceid The adaptivequiz instance id.
     * @param int $courseid    The course the activity lives in.
     * @param int $scaleid     The CAT scale id the course selection belongs to.
     * @param array $courseids The selected course ids for range 1.
     * @return \stdClass
     */
    private function make_quizdata(int $instanceid, int $courseid, int $scaleid, array $courseids): stdClass {
        return (object) [
            'id' => $instanceid,
            'instance' => $instanceid,
            'course' => $courseid,
            'section' => 1,
            'catquiz_catscales' => $scaleid,
            'numberoffeedbackoptions' => 2,
            // The field under test: the per-scale, per-range course subscription.
            'catquiz_courses_' . $scaleid . '_1' => $courseids,
        ];
    }

    /**
     * Reloads the persisted settings the way the settings form does.
     *
     * @param int $instanceid The adaptivequiz instance id.
     * @return array The form default values after data_preprocessing.
     */
    private function reload_form_defaults(int $instanceid): array {
        // The data_preprocessing lookup keys off 'instance' and, with no mform,
        // takes the "first load" branch that restores the persisted JSON values.
        $formdefaults = ['instance' => $instanceid];
        $mform = null;
        catquiz_handler::data_preprocessing($formdefaults, $mform);
        return $formdefaults;
    }

    /**
     * A saved course selection must survive a save -> reload roundtrip.
     *
     * @return void
     */
    public function test_course_selection_survives_save_and_reload(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $adaptivequiz] = $this->make_activity();
        $scaleid = 1;

        $quizdata = $this->make_quizdata($adaptivequiz->id, $course->id, $scaleid, [$course->id]);
        catquiz_handler::add_or_update_instance_callback($quizdata);

        $formdefaults = $this->reload_form_defaults($adaptivequiz->id);

        $key = 'catquiz_courses_' . $scaleid . '_1';
        $this->assertArrayHasKey(
            $key,
            $formdefaults,
            'The persisted course selection must be restored as a form default.'
        );
        $this->assertContains(
            (string) $course->id,
            array_map('strval', (array) $formdefaults[$key]),
            'The chosen course must still be selected after save and reload.'
        );
    }

    /**
     * The selection must also survive a second save (the 001 scenario saves
     * twice: once with an intentional validation error and once corrected).
     *
     * @return void
     */
    public function test_course_selection_survives_resave(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $adaptivequiz] = $this->make_activity();
        $scaleid = 1;

        // First save with the course selected.
        $first = $this->make_quizdata($adaptivequiz->id, $course->id, $scaleid, [$course->id]);
        catquiz_handler::add_or_update_instance_callback($first);

        // Second save: the operator re-submits the (unchanged) course selection,
        // mirroring the corrected re-save after the intentional gap error.
        $second = $this->make_quizdata($adaptivequiz->id, $course->id, $scaleid, [$course->id]);
        catquiz_handler::add_or_update_instance_callback($second);

        $formdefaults = $this->reload_form_defaults($adaptivequiz->id);

        $key = 'catquiz_courses_' . $scaleid . '_1';
        $this->assertArrayHasKey($key, $formdefaults);
        $this->assertContains(
            (string) $course->id,
            array_map('strval', (array) $formdefaults[$key]),
            'The chosen course must survive a second save.'
        );
    }

    /**
     * An empty selection must round-trip as empty (and never resurrect a stale
     * course), so clearing the field is itself persisted correctly.
     *
     * @return void
     */
    public function test_empty_course_selection_roundtrips_as_empty(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $adaptivequiz] = $this->make_activity();
        $scaleid = 1;
        $key = 'catquiz_courses_' . $scaleid . '_1';

        // Save with a selection, then save again with an empty selection.
        catquiz_handler::add_or_update_instance_callback(
            $this->make_quizdata($adaptivequiz->id, $course->id, $scaleid, [$course->id])
        );
        catquiz_handler::add_or_update_instance_callback(
            $this->make_quizdata($adaptivequiz->id, $course->id, $scaleid, [])
        );

        $formdefaults = $this->reload_form_defaults($adaptivequiz->id);

        $restored = array_filter(array_map('strval', (array) ($formdefaults[$key] ?? [])), static function ($v) {
            return $v !== '' && $v !== '0';
        });
        $this->assertSame(
            [],
            array_values($restored),
            'An emptied course selection must round-trip as empty.'
        );
    }
}
