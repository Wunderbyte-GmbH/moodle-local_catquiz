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
 * The activity question limit follows the CAT limit.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;

/**
 * Two fields, one source.
 *
 * mod_adaptivequiz draws the progress bar from its own maximumquestions field. On a
 * test run through catquiz that field is typically left at a large placeholder so the
 * activity's hard stop never fires before the CAT logic does - and the bar then counts
 * against the wrong reference, showing "5 / 1000" on a test that ends after twenty
 * questions.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\catquiz_handler
 */
final class activity_question_limit_test extends advanced_testcase {
    /**
     * Creates an adaptivequiz row with a given limit.
     *
     * @param int $limit
     * @return int
     */
    private function make_activity(int $limit): int {
        // Through the generator rather than a raw insert: the table has NOT NULL
        // columns that a hand-written record misses - attemptfeedback among them - and
        // the test would then fail on its own fixture instead of on the behaviour.
        //
        // attemptfeedbackeditor is passed because adaptivequiz_add_instance() reads it
        // unconditionally; the generator does not set it.
        $course = $this->getDataGenerator()->create_course();

        $instance = $this->getDataGenerator()->create_module('adaptivequiz', [
            'course' => $course->id,
            'maximumquestions' => $limit,
            'attemptfeedbackeditor' => ['text' => '', 'format' => FORMAT_HTML],
        ]);

        return (int) $instance->id;
    }

    /**
     * Calls the private sync helper.
     *
     * @param \stdClass $quizdata
     * @return void
     */
    private function sync(\stdClass $quizdata): void {
        $method = new \ReflectionMethod(catquiz_handler::class, 'sync_activity_question_limit');
        $method->setAccessible(true);
        $method->invoke(null, $quizdata);
    }

    /**
     * The placeholder is replaced by the CAT limit.
     *
     * @return void
     */
    public function test_limit_follows_the_cat_setting(): void {
        global $DB;

        $this->resetAfterTest();

        $id = $this->make_activity(1000);

        $this->sync((object) [
            'id' => $id,
            'maxquestionsgroup' => (object) ['catquiz_maxquestions' => 20],
        ]);

        $this->assertSame(
            20,
            (int) $DB->get_field('adaptivequiz', 'maximumquestions', ['id' => $id]),
            'The progress bar counts against this field; it has to name the limit '
                . 'the test actually uses.'
        );
    }

    /**
     * Without a CAT limit the activity's own value is left alone.
     *
     * Writing zero would remove the stop entirely - a defect dressed as a sync.
     *
     * @return void
     */
    public function test_unset_cat_limit_leaves_the_activity_alone(): void {
        global $DB;

        $this->resetAfterTest();

        $id = $this->make_activity(50);

        $this->sync((object) [
            'id' => $id,
            'maxquestionsgroup' => (object) ['catquiz_maxquestions' => 0],
        ]);

        $this->assertSame(
            50,
            (int) $DB->get_field('adaptivequiz', 'maximumquestions', ['id' => $id]),
            'An unset CAT limit must not clear the activity limit.'
        );
    }

    /**
     * A missing settings group is tolerated.
     *
     * The callback also runs for tests that were configured before the group existed.
     *
     * @return void
     */
    public function test_missing_group_is_tolerated(): void {
        global $DB;

        $this->resetAfterTest();

        $id = $this->make_activity(50);

        $this->sync((object) ['id' => $id]);

        $this->assertSame(
            50,
            (int) $DB->get_field('adaptivequiz', 'maximumquestions', ['id' => $id])
        );
    }
    /**
     * The sync is actually called when the activity is saved.
     *
     * The other tests invoke the helper directly and would keep passing if the call
     * in add_or_update_instance_callback() were removed - the method would still be
     * correct, and simply never run. That is exactly the failure this test exists to
     * catch, so it looks at the wiring rather than at the behaviour.
     *
     * @return void
     */
    public function test_sync_is_wired_into_the_save_callback(): void {
        global $CFG;

        $this->resetAfterTest();

        $source = file_get_contents(
            $CFG->dirroot . '/local/catquiz/classes/catquiz_handler.php'
        );

        $start = strpos($source, 'function add_or_update_instance_callback');
        $this->assertNotFalse($start, 'The activity is saved here.');

        $end = strpos($source, "\n    /**", $start);
        $body = substr($source, $start, $end - $start);

        $this->assertStringContainsString(
            'self::sync_activity_question_limit($quizdata)',
            $body,
            'Without this call the limit is never written and the progress bar keeps '
                . 'counting against the placeholder.'
        );
    }
}
