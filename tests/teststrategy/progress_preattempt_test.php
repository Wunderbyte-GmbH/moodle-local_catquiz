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

namespace local_catquiz\teststrategy;

use advanced_testcase;

/**
 * Tests the pre-attempt ability capture on the progress object (Issue #9, Phase 2).
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\teststrategy\progress::capture_preattempt_abilities
 * @covers \local_catquiz\teststrategy\progress::get_preattempt_abilities
 */
final class progress_preattempt_test extends advanced_testcase {
    /**
     * Capture stores the pre-attempt abilities, is idempotent (does not
     * overwrite an already-captured scale), and survives a save/reload round
     * trip.
     */

    /**
     * Returns the internal CAT attempt id for an attempt of mod_adaptivequiz.
     *
     * Progress is filed under local_catquiz_attempts.id since issue #95; the tests work with the
     * external id of the component and resolve it the same way production does.
     *
     * @param int $attemptid Id of the attempt of the component.
     * @return int
     */
    private static function catattemptid(int $attemptid): int {
        global $DB;

        return (int) $DB->get_field('local_catquiz_attempts', 'id', [
            'attemptid' => $attemptid,
            'component' => 'mod_adaptivequiz',
        ]);
    }

    /**
     * Files the CAT attempt the progress belongs to.
     *
     * Since issue #95 progress references local_catquiz_attempts.id, so that row has to exist
     * before the first progress access - which is what the CAT model does in production.
     *
     * @param int $attemptid Id of the attempt of the component.
     * @param int $contextid The CAT context.
     * @return int The internal CAT attempt id.
     */
    private static function make_cat_attempt(int $attemptid, int $contextid): int {
        global $DB, $USER;

        // The fixture may already have filed one; local_catquiz_attempts.attemptid is unique.
        $existing = $DB->get_field('local_catquiz_attempts', 'id', [
            'attemptid' => $attemptid,
            'component' => 'mod_adaptivequiz',
        ]);
        if ($existing) {
            return (int) $existing;
        }

        $now = time();

        return (int) $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => $USER->id ?? 2,
            'scaleid' => 1,
            'contextid' => $contextid,
            'courseid' => 1,
            'attemptid' => $attemptid,
            'component' => 'mod_adaptivequiz',
            'instanceid' => 1,
            'status' => 1,
            'json' => '{}',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    public function test_capture_is_idempotent_and_persists(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $attemptid = 4711;
        $contextid = 9;

        self::make_cat_attempt($attemptid, $contextid);

        $progress = progress::load($attemptid, 'mod_adaptivequiz', $contextid, (object) []);
        $progress->capture_preattempt_abilities([5 => 0.4, 6 => -0.2]);
        // A second capture must not overwrite already-captured scales.
        $progress->capture_preattempt_abilities([5 => 9.9, 7 => 1.1]);

        $captured = $progress->get_preattempt_abilities();
        $this->assertSame(0.4, $captured[5]);
        $this->assertSame(-0.2, $captured[6]);
        $this->assertSame(1.1, $captured[7], 'A newly seen scale is captured.');
        $this->assertArrayNotHasKey(8, $captured);

        $progress->save();

        // Reload from the DB and confirm the values round-trip.
        self::make_cat_attempt($attemptid, $contextid);
        $reloaded = progress::load($attemptid, 'mod_adaptivequiz', $contextid);
        $this->assertEquals([5 => 0.4, 6 => -0.2, 7 => 1.1], $reloaded->get_preattempt_abilities());
    }

    /**
     * Backward compatibility: a progress serialised before this field existed
     * loads with an empty pre-attempt map rather than failing.
     */
    public function test_missing_field_defaults_to_empty(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $attemptid = 4712;
        $contextid = 9;

        // A minimal legacy progress JSON without 'preattemptabilities'.
        self::make_cat_attempt($attemptid, $contextid);
        $legacy = progress::load($attemptid, 'mod_adaptivequiz', $contextid, (object) []);
        $legacy->save();
        $json = json_decode(
            $DB->get_field('local_catquiz_progress', 'json', ['attemptid' => self::catattemptid($attemptid)]),
            true
        );
        unset($json['preattemptabilities']);
        $DB->set_field('local_catquiz_progress', 'json', json_encode($json), ['attemptid' => self::catattemptid($attemptid)]);
        // Force a reload from the DB rather than the cached object.
        \cache::make('local_catquiz', 'adaptivequizattempt')->purge();

        self::make_cat_attempt($attemptid, $contextid);

        $reloaded = progress::load($attemptid, 'mod_adaptivequiz', $contextid);
        $this->assertSame([], $reloaded->get_preattempt_abilities());
    }
}
