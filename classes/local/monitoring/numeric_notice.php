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

namespace local_catquiz\local\monitoring;

use local_catquiz\event\numeric_fallback_applied;

/**
 * Reports numeric fallbacks of the ability estimation as events (issue #106).
 *
 * These used to be developer notices, which end a Behat run and are invisible in production. One
 * event per fallback and figures within a request: the estimation runs per scale and response and
 * would otherwise log the same fact many times.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class numeric_notice {
    /** @var array<string, true> The fallbacks reported in this request. */
    private static array $reported = [];

    /**
     * Reports a fallback once per request.
     *
     * Floats that are no finite number are passed as their text, so that the event data stays storable.
     *
     * @param string $reason One of the constants of numeric_fallback_applied.
     * @param array $figures Figures only: catscaleid, n, mean, sd, effectivesd, value, kept, models.
     * @return bool Whether an event was triggered.
     */
    public static function report(string $reason, array $figures): bool {
        $other = ['reason' => $reason];
        foreach ($figures as $key => $value) {
            $other[$key] = (is_float($value) && !is_finite($value)) ? var_export($value, true) : $value;
        }
        $key = json_encode($other);
        if (isset(self::$reported[$key])) {
            return false;
        }
        self::$reported[$key] = true;
        numeric_fallback_applied::create([
            'context' => \context_system::instance(),
            'other' => $other,
        ])->trigger();
        return true;
    }

    /**
     * Forgets what was reported - for tests, which run many "requests" in one process.
     */
    public static function reset(): void {
        self::$reported = [];
    }
}
