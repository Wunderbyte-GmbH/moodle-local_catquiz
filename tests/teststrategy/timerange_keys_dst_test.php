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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * The keys of the time ranges across time zones and daylight saving time (issue #151, follow-up A of #150).
 *
 * The keys stepped '+1 day' in the time zone of the server and were labelled in the time zone of the person. Across
 * a change of daylight saving time that is a step of 23 or 25 hours: with the person in another zone, a day could be
 * skipped. An attempt of that day made a series longer than the labels, and when the skipped day was the last one,
 * the loop never ended.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\teststrategy\feedback_helper::get_timerangekeys
 */
final class timerange_keys_dst_test extends advanced_testcase {
    /**
     * Server and person in different zones, across both changes of daylight saving time.
     *
     * @return array [server zone, person zone, start (UTC), end (UTC), expected days (Y-m-d in the person's zone)]
     */
    public static function zone_provider(): array {
        return [
            'autumn: server Berlin, person UTC' => [
                'Europe/Berlin', 'UTC', '2026-10-24 23:30', '2026-10-27 12:00',
                ['2026-10-24', '2026-10-25', '2026-10-26', '2026-10-27'],
            ],
            'spring: server UTC, person Berlin' => [
                'UTC', 'Europe/Berlin', '2026-03-28 22:30', '2026-03-31 08:00',
                ['2026-03-28', '2026-03-29', '2026-03-30', '2026-03-31'],
            ],
            'autumn, US: server Berlin, person New York' => [
                'Europe/Berlin', 'America/New_York', '2026-10-31 22:30', '2026-11-03 12:00',
                ['2026-10-31', '2026-11-01', '2026-11-02', '2026-11-03'],
            ],
            'spring, US: server UTC, person New York' => [
                'UTC', 'America/New_York', '2026-03-08 04:30', '2026-03-10 12:00',
                ['2026-03-07', '2026-03-08', '2026-03-09', '2026-03-10'],
            ],
            'same zone (counter-check)' => [
                'Europe/Berlin', 'Europe/Berlin', '2026-10-24 06:00', '2026-10-27 12:00',
                ['2026-10-24', '2026-10-25', '2026-10-26', '2026-10-27'],
            ],
        ];
    }

    /**
     * Sets the zones: the server's (and PHP's), and the person's.
     *
     * @param string $server
     * @param string $person
     * @return void
     */
    private function zones(string $server, string $person): void {
        $this->resetAfterTest();
        $this->setTimezone($server, $server);
        $this->setUser($this->getDataGenerator()->create_user(['timezone' => $person]));
    }

    /**
     * One key per day of the person, in order, the first and the last day included - and every attempt in the range
     * finds its key.
     *
     * @param string $server
     * @param string $person
     * @param string $start
     * @param string $end
     * @param string[] $days
     * @dataProvider zone_provider
     */
    public function test_one_key_per_day_of_the_person(
        string $server,
        string $person,
        string $start,
        string $end,
        array $days
    ): void {
        $this->zones($server, $person);
        $utc = new \DateTimeZone('UTC');
        $from = (new \DateTimeImmutable($start, $utc))->getTimestamp();
        $to = (new \DateTimeImmutable($end, $utc))->getTimestamp();

        $keys = array_values(feedback_helper::get_timerangekeys(LOCAL_CATQUIZ_TIMERANGE_DAY, [$from, $to]));

        $expected = array_map(fn($day) => feedback_helper::return_datestring_label(
            LOCAL_CATQUIZ_TIMERANGE_DAY,
            (new \DateTimeImmutable("$day 12:00", new \DateTimeZone($person)))->getTimestamp()
        ), $days);
        $this->assertSame($expected, $keys);
        for ($t = $from; $t <= $to; $t += 1800) {
            $this->assertContains(
                feedback_helper::return_datestring_label(LOCAL_CATQUIZ_TIMERANGE_DAY, $t),
                $keys,
                'An attempt at ' . gmdate('Y-m-d H:i', $t) . ' UTC has no key.'
            );
        }
    }

    /**
     * The range ends on the day the old steps skipped: the keys end, with that day as the last.
     */
    public function test_the_keys_end_when_the_last_day_was_the_one_skipped(): void {
        $this->zones('Europe/Berlin', 'UTC');
        $utc = new \DateTimeZone('UTC');
        $from = (new \DateTimeImmutable('2026-10-24 23:30', $utc))->getTimestamp();
        $to = (new \DateTimeImmutable('2026-10-25 12:00', $utc))->getTimestamp();

        $keys = array_values(feedback_helper::get_timerangekeys(LOCAL_CATQUIZ_TIMERANGE_DAY, [$from, $to]));

        $this->assertCount(2, $keys);
        $this->assertSame(feedback_helper::return_datestring_label(LOCAL_CATQUIZ_TIMERANGE_DAY, $to), end($keys));
    }

    /**
     * Weeks, months and quarters across the change: complete, in order, without duplicates; a range that ends before
     * it begins gives the key of its end, once.
     */
    public function test_weeks_months_quarters_and_an_empty_range(): void {
        $this->zones('Europe/Berlin', 'UTC');
        $utc = new \DateTimeZone('UTC');
        // The longest range each step is used for (get_timerange_for_attempts()): weeks up to half a year - their
        // label has no year -, months and quarters over two years.
        $ranges = [
            LOCAL_CATQUIZ_TIMERANGE_WEEK => ['2026-01-03 23:30', '2026-07-02 12:00'],
            LOCAL_CATQUIZ_TIMERANGE_MONTH => ['2026-01-03 23:30', '2027-12-30 12:00'],
            LOCAL_CATQUIZ_TIMERANGE_QUARTEROFYEAR => ['2026-01-03 23:30', '2027-12-30 12:00'],
        ];
        foreach ($ranges as $range => [$start, $end]) {
            $from = (new \DateTimeImmutable($start, $utc))->getTimestamp();
            $to = (new \DateTimeImmutable($end, $utc))->getTimestamp();
            $keys = array_values(feedback_helper::get_timerangekeys($range, [$from, $to]));
            $this->assertSame($keys, array_values(array_unique($keys)));
            for ($t = $from; $t <= $to; $t += 6 * HOURSECS) {
                $this->assertContains(feedback_helper::return_datestring_label($range, $t), $keys);
            }
            $this->assertSame(feedback_helper::return_datestring_label($range, $from), $keys[0]);
            $this->assertSame(feedback_helper::return_datestring_label($range, $to), end($keys));
        }
        // Before, a range that ends before it begins never reached its last key either.
        $this->assertSame(
            [feedback_helper::return_datestring_label(LOCAL_CATQUIZ_TIMERANGE_DAY, $from)],
            array_values(feedback_helper::get_timerangekeys(LOCAL_CATQUIZ_TIMERANGE_DAY, [$to, $from]))
        );
    }
}
