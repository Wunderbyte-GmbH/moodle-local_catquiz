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
 * Tests the cancel_expired_tests functionality.
 *
 * @package    local_catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use basic_testcase;
use local_catquiz\task\cancel_expired_attempts;

/**
 * Tests the mathcat functionality.
 *
 * @package    local_catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_catquiz\mathcat
 *
 */
final class cancel_expired_attempts_test extends basic_testcase {
    /**
     * Test if attempt expiration is detected correctly.
     *
     * @param int $attemptcreated
     * @param ?int $quizmaxtime
     * @param int $defaultmaxtime
     * @param int $now
     * @param bool $expected
     * @return void
     * @dataProvider attempt_expiration_is_detected_correctly_provider
     */
    public function test_attempt_expiration_is_detected_correctly(
        int $attemptcreated,
        ?int $quizmaxtime,
        int $defaultmaxtime,
        int $now,
        bool $expected
    ): void {
        $task = new cancel_expired_attempts();

        // Create a mock attempt record.
        $record = new \stdClass();
        $record->timecreated = $attemptcreated;
        $record->instance = 1; // Dummy instance ID.

        // Set up the required properties using reflection.
        $reflection = new \ReflectionClass($task);

        $currenttimeprop = $reflection->getProperty('currenttime');
        $currenttimeprop->setAccessible(true);
        $currenttimeprop->setValue($task, $now);

        $defaultmaxtimeprop = $reflection->getProperty('defaultmaxtime');
        $defaultmaxtimeprop->setAccessible(true);
        $defaultmaxtimeprop->setValue($task, $defaultmaxtime);

        $maxtimepertestprop = $reflection->getProperty('maxtimepertest');
        $maxtimepertestprop->setAccessible(true);
        $maxtimepertestprop->setValue($task, [1 => $quizmaxtime]); // Use dummy instance ID 1.

        $shouldbecompleted = $task->exceeds_maxtime($record);
        $this->assertEquals($expected, $shouldbecompleted);
    }
    /**
     * Data provider for test cases that check if attempt expiration is detected correctly.
     *
     * Generates sample data sets for testing various scenarios of attempt expiration.
     *
     * @return array An array of test data sets.
     */
    public static function attempt_expiration_is_detected_correctly_provider(): array {
        $now = time();
        $starttime = $now - 5 * 60; // Started 5 minutes ago.
        return [
            'quiz setting and expired' => [
                $starttime,
                60, // Allow just 1 minute.
                1, // This should be ignored.
                $now,
                true,
            ],
            'quiz setting and not expired' => [
                $starttime,
                10 * 60, // Allow 10 minutes.
                1, // This should be ignored.
                $now,
                false,
            ],
            'quiz setting and not expired with 0 as no-limit' => [
                $starttime,
                0, // Unlimited.
                1, // This should be ignored.
                $now,
                false,
            ],
            'no quiz setting and default and expired' => [
                $starttime,
                null,
                60, // Allow just 1 minute.
                $now,
                true,
            ],
            'no quiz setting and default and not expired' => [
                $starttime,
                null,
                3600, // Allow 1 hour.
                $now,
                false,
            ],
            'no quiz setting and default and not expired with 0 as no-limit' => [
                $starttime,
                null,
                0, // Unlimited.
                $now,
                false,
            ],
        ];
    }
}
