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
 * Regression test: the last response must never be served from a stale cache.
 *
 * progress used to cache the last response under the key
 * "lastresponse_<usageid>_<numplayedquestions>". That key assumes the number of
 * played questions is a monotonically growing version indicator of the response
 * history - but load() removes a still unanswered last question from
 * playedquestions, so the counter goes 2 -> 1 and back to 2 once that item IS
 * answered. On the second hit of key "..._2" the cache returned the OLD response,
 * so the freshly given answer never reached the response accumulation and the
 * attempt kept administering items past the configured maximum ("Question 5").
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\teststrategy\progress
 */

namespace local_catquiz\teststrategy;

use advanced_testcase;
use cache;
use ReflectionMethod;
use ReflectionProperty;
use stdClass;

/**
 * Guards the last-response lookup against stale cache entries.
 *
 * @package    local_catquiz
 * @covers \local_catquiz\teststrategy\progress
 */
final class progress_lastresponse_cache_test extends advanced_testcase {
    /**
     * A pre-existing cache entry under the old key must not shadow the real value.
     *
     * The test seeds the cache with a response that differs from what the database
     * would return. With the cache in place the stale entry won.
     *
     * @return void
     */
    public function test_stale_cache_entry_does_not_shadow_last_response(): void {
        $this->resetAfterTest(true);

        $usageid = 424242;

        // Seed the cache exactly the way the removed implementation would have.
        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        $stale = new stdClass();
        $stale->questionid = 111;
        $stale->fraction = 1.0;
        $stale->state = 'gradedright';
        foreach ([0, 1, 2, 3, 4] as $numplayed) {
            $cache->set(sprintf('lastresponse_%d_%d', $usageid, $numplayed), $stale);
        }

        // Build a progress instance without touching the constructor.
        $progress = (new \ReflectionClass(progress::class))->newInstanceWithoutConstructor();
        $this->set_private($progress, 'usageid', $usageid);
        $this->set_private($progress, 'playedquestions', []);

        $method = new ReflectionMethod(progress::class, 'get_last_response_for_attempt');
        $method->setAccessible(true);
        $result = $method->invoke($progress);

        // The usage does not exist, so the database has no response at all. Anything
        // other than "no response" can only have come from the stale cache.
        $this->assertNotEquals(
            111,
            $result->questionid ?? null,
            'The last response must be read fresh, never from the played-questions keyed cache.'
        );
    }

    /**
     * Sets a private property on the progress instance.
     *
     * @param progress $progress
     * @param string $name
     * @param mixed $value
     *
     * @return void
     */
    private function set_private(progress $progress, string $name, $value): void {
        $property = new ReflectionProperty(progress::class, $name);
        $property->setAccessible(true);
        $property->setValue($progress, $value);
    }
}
