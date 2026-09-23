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
 * Issue #56: retention of attempt progress is a deliberate, configurable choice.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\local;

use advanced_testcase;
use local_catquiz\task\cleanup_attempt_progress;

/**
 * Verifies the retention levels, the site cap and the cleanup task.
 *
 * The table used to keep a JSON blob of personal answer data for every attempt for
 * ever, because nothing ever deleted it - while being too thin to reconstruct a
 * trajectory. These tests pin both halves of the fix.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\local\progress_retention
 * @covers     \local_catquiz\task\cleanup_attempt_progress
 */
final class progress_retention_test extends advanced_testcase {
    /**
     * The default is the data-sparing level, not the previous behaviour.
     *
     * @return void
     */
    public function test_default_is_minimal(): void {
        $this->resetAfterTest();

        $this->assertEquals(progress_retention::MINIMAL, progress_retention::site_level());
        $this->assertTrue(progress_retention::should_delete());
        $this->assertFalse(progress_retention::should_trace());
    }

    /**
     * An unreadable value falls back to the data-sparing level.
     *
     * A typo in the configuration must not silently turn into the most retentive
     * option - that is the direction in which a mistake causes harm.
     *
     * @return void
     */
    public function test_unknown_value_falls_back_to_minimal(): void {
        $this->resetAfterTest();

        set_config('progressretention', 'something-else', 'local_catquiz');

        $this->assertEquals(progress_retention::MINIMAL, progress_retention::site_level());
    }

    /**
     * An activity may retain less than the site allows, never more.
     *
     * @return void
     */
    public function test_site_setting_caps_the_activity(): void {
        $this->resetAfterTest();

        set_config('progressretention', progress_retention::KEEP, 'local_catquiz');

        // Below the cap: honoured.
        $this->assertEquals(
            progress_retention::MINIMAL,
            progress_retention::effective_level(progress_retention::MINIMAL)
        );
        // At the cap: honoured.
        $this->assertEquals(
            progress_retention::KEEP,
            progress_retention::effective_level(progress_retention::KEEP)
        );
        // Above the cap: capped, not honoured.
        $this->assertEquals(
            progress_retention::KEEP,
            progress_retention::effective_level(progress_retention::TRACE),
            'An activity must not record more than the site permits.'
        );
        // No activity setting: the site default applies.
        $this->assertEquals(progress_retention::KEEP, progress_retention::effective_level(null));
    }

    /**
     * Trace mode is only reached when the site permits it.
     *
     * @return void
     */
    public function test_trace_requires_the_site_to_allow_it(): void {
        $this->resetAfterTest();

        set_config('progressretention', progress_retention::TRACE, 'local_catquiz');
        $this->assertTrue(progress_retention::should_trace());
        $this->assertFalse(progress_retention::should_delete());

        set_config('progressretention', progress_retention::MINIMAL, 'local_catquiz');
        $this->assertFalse(progress_retention::should_trace(progress_retention::TRACE));
    }

    /**
     * The cleanup task removes finished attempts beyond the retention period only.
     *
     * @return void
     */
    public function test_cleanup_removes_only_expired_finished_attempts(): void {
        global $DB;

        $this->resetAfterTest();
        // The retention period only applies in a retaining mode: with "minimal" every
        // finished attempt is swept, and there would be no period to test.
        set_config('progressretention', progress_retention::KEEP, 'local_catquiz');
        set_config('progressretentiondays', 30, 'local_catquiz');

        $now = time();
        $old = $now - (40 * DAYSECS);
        $recent = $now - (5 * DAYSECS);

        // Three attempts: expired and finished, recent and finished, still running.
        $cases = [
            ['attemptid' => 9001, 'endtime' => $old],
            ['attemptid' => 9002, 'endtime' => $recent],
            ['attemptid' => 9003, 'endtime' => null],
        ];
        foreach ($cases as $case) {
            $DB->insert_record('local_catquiz_attempts', (object) [
                'userid' => 2,
                'scaleid' => 1,
                'contextid' => 1,
                'courseid' => 1,
                'attemptid' => $case['attemptid'],
                'component' => 'mod_adaptivequiz',
                'instanceid' => 1,
                'status' => 1,
                'timecreated' => $old,
                'timemodified' => $now,
                'endtime' => $case['endtime'],
            ]);
            $DB->insert_record('local_catquiz_progress', (object) [
                'attemptid' => $case['attemptid'],
                'contextid' => 1,
                'json' => '{}',
                'timecreated' => $old,
                'timemodified' => $now,
            ]);
        }

        // The task reports what it removed, which is useful in cron output but
        // counts as unexpected output in PHPUnit. Captured rather than removed.
        ob_start();
        (new cleanup_attempt_progress())->execute();
        $output = ob_get_clean();

        $this->assertStringContainsString('removed 1', $output);
        $this->assertFalse(
            $DB->record_exists('local_catquiz_progress', ['attemptid' => 9001]),
            'An attempt beyond the retention period must be removed.'
        );
        $this->assertTrue(
            $DB->record_exists('local_catquiz_progress', ['attemptid' => 9002]),
            'A recent attempt must be kept.'
        );
        $this->assertTrue(
            $DB->record_exists('local_catquiz_progress', ['attemptid' => 9003]),
            'A running attempt must never lose its progress.'
        );
    }

    /**
     * The task is idempotent and does nothing when retention is unlimited.
     *
     * @return void
     */
    public function test_cleanup_is_idempotent_and_respects_unlimited(): void {
        global $DB;

        $this->resetAfterTest();
        // As above: "unlimited" is only meaningful in a retaining mode.
        set_config('progressretention', progress_retention::KEEP, 'local_catquiz');

        $now = time();
        $old = $now - (40 * DAYSECS);
        $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => 2,
            'scaleid' => 1,
            'contextid' => 1,
            'courseid' => 1,
            'attemptid' => 9101,
            'component' => 'mod_adaptivequiz',
            'instanceid' => 1,
            'status' => 1,
            'timecreated' => $old,
            'timemodified' => $now,
            'endtime' => $old,
        ]);
        $DB->insert_record('local_catquiz_progress', (object) [
            'attemptid' => 9101,
            'contextid' => 1,
            'json' => '{}',
            'timecreated' => $old,
            'timemodified' => $now,
        ]);

        // Zero means unlimited: nothing is removed.
        set_config('progressretentiondays', 0, 'local_catquiz');
        ob_start();
        (new cleanup_attempt_progress())->execute();
        ob_end_clean();
        $this->assertTrue($DB->record_exists('local_catquiz_progress', ['attemptid' => 9101]));

        set_config('progressretentiondays', 30, 'local_catquiz');
        ob_start();
        (new cleanup_attempt_progress())->execute();
        ob_end_clean();
        $this->assertFalse($DB->record_exists('local_catquiz_progress', ['attemptid' => 9101]));

        // Running again finds nothing left and must not fail.
        ob_start();
        (new cleanup_attempt_progress())->execute();
        ob_end_clean();
        $this->assertFalse($DB->record_exists('local_catquiz_progress', ['attemptid' => 9101]));
    }
    /**
     * Only the levels the site permits are offered in the activity form.
     *
     * Showing an option that would be capped away on save would be misleading: the
     * form would accept a choice that silently does not take effect.
     *
     * @return void
     */
    public function test_form_offers_only_permitted_levels(): void {
        $this->resetAfterTest();

        set_config('progressretention', progress_retention::KEEP, 'local_catquiz');

        $offered = array_values(array_filter(
            progress_retention::levels(),
            fn ($level) => progress_retention::effective_level($level) === $level
        ));

        $this->assertContains(progress_retention::MINIMAL, $offered);
        $this->assertContains(progress_retention::KEEP, $offered);
        $this->assertNotContains(
            progress_retention::TRACE,
            $offered,
            'A level above the site cap must not be offered.'
        );
    }

    /**
     * The activity setting decides whether the progress row survives the attempt.
     *
     * @return void
     */
    public function test_activity_setting_controls_deletion(): void {
        $this->resetAfterTest();

        // Site allows tracing, so the activity may choose freely.
        set_config('progressretention', progress_retention::TRACE, 'local_catquiz');

        $this->assertTrue(
            progress_retention::should_delete(progress_retention::MINIMAL),
            'An activity may be stricter than the site.'
        );
        $this->assertFalse(progress_retention::should_delete(progress_retention::KEEP));
        $this->assertFalse(progress_retention::should_delete(progress_retention::TRACE));

        // Site is strict: the activity cannot loosen it.
        set_config('progressretention', progress_retention::MINIMAL, 'local_catquiz');
        $this->assertTrue(
            progress_retention::should_delete(progress_retention::TRACE),
            'A strict site setting must win over a permissive activity.'
        );
    }
    /**
     * In the data-sparing mode the task sweeps finished attempts without a period.
     *
     * The deletion deliberately does not happen during finalisation: the feedback
     * path loads the progress again right afterwards, and a row removed there made
     * load() fall through to create_new() without quiz settings. The task takes
     * over, which costs minutes rather than a request.
     *
     * @return void
     */
    public function test_minimal_mode_sweeps_finished_attempts(): void {
        global $DB;

        $this->resetAfterTest();
        set_config('progressretention', progress_retention::MINIMAL, 'local_catquiz');
        // No retention period configured on purpose - "minimal" must not depend on one.
        set_config('progressretentiondays', 0, 'local_catquiz');

        $now = time();
        foreach ([['id' => 9201, 'endtime' => $now - 60], ['id' => 9202, 'endtime' => null]] as $case) {
            $DB->insert_record('local_catquiz_attempts', (object) [
                'userid' => 2,
                'scaleid' => 1,
                'contextid' => 1,
                'courseid' => 1,
                'attemptid' => $case['id'],
                'component' => 'mod_adaptivequiz',
                'instanceid' => 1,
                'status' => 1,
                'timecreated' => $now - 120,
                'timemodified' => $now,
                'endtime' => $case['endtime'],
            ]);
            $DB->insert_record('local_catquiz_progress', (object) [
                'attemptid' => $case['id'],
                'contextid' => 1,
                'json' => '{}',
                'timecreated' => $now - 120,
                'timemodified' => $now,
            ]);
        }

        ob_start();
        (new cleanup_attempt_progress())->execute();
        ob_end_clean();

        $this->assertFalse(
            $DB->record_exists('local_catquiz_progress', ['attemptid' => 9201]),
            'A finished attempt must be swept in the data-sparing mode.'
        );
        $this->assertTrue(
            $DB->record_exists('local_catquiz_progress', ['attemptid' => 9202]),
            'A running attempt must keep its progress even in the data-sparing mode.'
        );
    }
    /**
     * Trace mode actually records, instead of failing on the estimation path.
     *
     * Reported from a live attempt: set_ability() called a get_step() that does not
     * exist, so switching trace mode on broke every running test. The method sits on
     * the hot path of the ability estimation, not in a rarely used branch - which is
     * exactly why no existing test touched it.
     *
     * @return void
     */
    public function test_trace_mode_records_without_failing(): void {
        $this->resetAfterTest();
        set_config('progressretention', progress_retention::TRACE, 'local_catquiz');

        $progress = \local_catquiz\teststrategy\progress::load(9301, 'mod_adaptivequiz', 1, new \stdClass());

        // The call that broke live attempts.
        $progress->set_ability(0.5, 7);
        $progress->set_ability(0.8, 7);

        $trace = $progress->get_ability_trace();

        $this->assertArrayHasKey(7, $trace, 'Trace mode must record per scale.');
        $this->assertCount(2, $trace[7], 'Every estimate is appended, not overwritten.');
        $this->assertEqualsWithDelta(0.5, $trace[7][0]['ability'], 0.0001);
        $this->assertEqualsWithDelta(0.8, $trace[7][1]['ability'], 0.0001);
        $this->assertArrayHasKey('step', $trace[7][0], 'Each entry carries its step.');
    }

    /**
     * The other modes keep the scalar and record nothing.
     *
     * @return void
     */
    public function test_other_modes_do_not_record_a_trace(): void {
        $this->resetAfterTest();
        set_config('progressretention', progress_retention::KEEP, 'local_catquiz');

        $progress = \local_catquiz\teststrategy\progress::load(9302, 'mod_adaptivequiz', 1, new \stdClass());
        $progress->set_ability(0.5, 7);

        $this->assertSame(
            [],
            $progress->get_ability_trace(),
            'Without trace mode nothing is recorded, and the stored JSON stays as before.'
        );
    }
}
