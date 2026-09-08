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
 * An attempt without a strategy in its payload must not break the course page.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;
use local_catquiz\teststrategy\feedback_helper;

/**
 * Guards the feedback shortcode against incomplete attempt payloads.
 *
 * Reported from a live course: opening a course page that embeds the catquiz
 * feedback shortcode raised a TypeError in feedbacksettings::__construct(). The
 * strategy id was read from the JSON payload only, and an attempt whose payload
 * predates that field yielded null for an int parameter.
 *
 * The damage was out of proportion to the cause: the exception travelled up through
 * the shortcode filter into the course format renderer, so a single unusable attempt
 * took down the entire course page rather than one card.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\teststrategy\feedback_helper::get_feedback_data
 */
final class feedback_shortcode_robustness_test extends advanced_testcase {
    /**
     * Inserts an attempt whose payload lacks the strategy.
     *
     * @param int $attemptid
     * @param string $json
     * @param int $teststrategy Value of the column, 0 to leave it unset.
     * @param int $courseid
     * @return void
     */
    private function add_attempt(
        int $attemptid,
        string $json,
        int $teststrategy = 0,
        int $courseid = 1
    ): void {
        global $DB;

        $now = time();
        $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => 2,
            'scaleid' => 1,
            'contextid' => 1,
            'courseid' => $courseid,
            'attemptid' => $attemptid,
            'component' => 'mod_adaptivequiz',
            'instanceid' => 1,
            'teststrategy' => $teststrategy,
            'status' => 1,
            'json' => $json,
            'debug_info' => '',
            'timecreated' => $now,
            'timemodified' => $now,
            'endtime' => $now,
        ]);
    }

    /**
     * A payload without a strategy does not raise a TypeError.
     *
     * @return void
     */
    public function test_attempt_without_strategy_in_payload_is_survived(): void {
        global $USER, $COURSE, $DB, $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        // Payload readable, but carrying no teststrategy - the reported case.
        $this->add_attempt(7101, json_encode((object) ['personabilities' => []]), 0, $course->id);

        $data = feedback_helper::get_feedback_data(
            ['courseid' => $course->id, 'numberofrecords' => 5],
            $context,
            $USER,
            $COURSE,
            $DB,
            $CFG
        );

        $this->assertIsArray($data, 'A single unusable attempt must not fail the page.');

        // The attempt is skipped, not silently accepted: the developer message says
        // which one and why. Asserting it also proves the guard was reached at all -
        // without it the call would have raised a TypeError instead.
        $this->assertDebuggingCalled('Attempt 7101 has no test strategy, skipping.');
    }

    /**
     * The query provides the strategy column to fall back to.
     *
     * The column exists for exactly this purpose; reading only the payload discarded
     * information the database already had.
     *
     * @return void
     */
    public function test_query_provides_the_strategy_column(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $this->add_attempt(7102, json_encode((object) ['personabilities' => []]), 4, $course->id);

        $records = catquiz::return_data_from_attemptstable(5, 0, $course->id, 0, -1);

        $this->assertNotEmpty($records, 'The attempt must be found.');
        $record = reset($records);
        $this->assertTrue(
            property_exists($record, 'teststrategy'),
            'Without the column there is nothing to fall back to.'
        );
        $this->assertEquals(4, (int) $record->teststrategy);
    }
}
