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

namespace local_catquiz\local;

use advanced_testcase;
use local_catquiz\catquiz;

/**
 * A stored ability says which attempt it came from - or that it came from none.
 *
 * local_catquiz_personparams.attemptid has a foreign key to local_catquiz_attempts.id but was
 * never written; the column was always null and its comment described a different table
 * (issue #97). It is filled now, and null has a meaning of its own: a recalibration of the scale
 * estimates every person from the whole response set and belongs to no single attempt.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\catquiz
 */
final class personparam_attempt_test extends advanced_testcase {
    /**
     * Files a CAT attempt for an attempt of mod_adaptivequiz.
     *
     * @param int $attemptid Id of the attempt of the component.
     * @param int $userid The person.
     * @param int $contextid The CAT context.
     * @return int The internal CAT attempt id.
     */
    private function make_cat_attempt(int $attemptid, int $userid, int $contextid): int {
        global $DB;

        $now = time();

        return (int) $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => $userid,
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

    /**
     * The external attempt id of a component resolves to the internal CAT attempt.
     */
    public function test_the_component_attempt_resolves_to_the_cat_attempt(): void {
        $this->resetAfterTest();

        $catattemptid = $this->make_cat_attempt(4711, 2, 9);

        $this->assertSame(
            $catattemptid,
            catquiz::get_cat_attempt_id(4711, 'mod_adaptivequiz'),
            'The attempt of the component does not resolve to its CAT attempt.'
        );
        $this->assertNull(
            catquiz::get_cat_attempt_id(4711, 'mod_quiz'),
            'The same number in another component must not resolve to this CAT attempt.'
        );
        $this->assertNull(
            catquiz::get_cat_attempt_id(9999, 'mod_adaptivequiz'),
            'An attempt without a CAT attempt must not resolve to anything.'
        );
    }

    /**
     * An ability that comes from an attempt names that attempt.
     */
    public function test_an_ability_from_an_attempt_names_it(): void {
        global $DB;

        $this->resetAfterTest();

        $catattemptid = $this->make_cat_attempt(4712, 2, 9);
        catquiz::update_person_param(2, 9, 1, 0.75, $catattemptid);

        // The table is appended to; the current value is the newest valid row.
        $record = catquiz::get_current_person_param(2, 9, 1);

        $this->assertEquals(0.75, (float) $record->ability);
        $this->assertEquals(
            $catattemptid,
            (int) $record->attemptid,
            'The stored ability does not point at the attempt it came from.'
        );
    }

    /**
     * An ability that comes from no single attempt says so.
     *
     * This is the recalibration case: model_person_param_list::save_to_db() estimates every person
     * of a context from the whole response set. Inventing an attempt there would be worse than
     * leaving the reference empty.
     */
    public function test_an_ability_without_an_attempt_stays_unassigned(): void {
        global $DB;

        $this->resetAfterTest();

        catquiz::update_person_param(2, 9, 1, 0.5);

        // The table is appended to; the current value is the newest valid row.
        $record = catquiz::get_current_person_param(2, 9, 1);

        $this->assertNull($record->attemptid, 'An ability from no attempt must not name one.');
    }

    /**
     * Updating an ability from a later attempt moves the reference along.
     */
    public function test_a_later_attempt_replaces_the_reference(): void {
        global $DB;

        $this->resetAfterTest();

        $first = $this->make_cat_attempt(4713, 2, 9);
        $second = $this->make_cat_attempt(4714, 2, 9);

        catquiz::update_person_param(2, 9, 1, 0.1, $first);
        catquiz::update_person_param(2, 9, 1, 0.9, $second);

        // The table is appended to; the current value is the newest valid row.
        $record = catquiz::get_current_person_param(2, 9, 1);

        $this->assertEquals(0.9, (float) $record->ability);
        $this->assertEquals($second, (int) $record->attemptid);
    }
}
