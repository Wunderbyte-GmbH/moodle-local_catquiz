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

use cache;

/**
 * The state of the attempts of a session, one key per attempt (issue #146).
 *
 * The session cache "adaptivequizattempt" held a few values under one key for the whole session - when the
 * attempt started, when it ended, the number of items, the ability before it, an error - and the start
 * of an attempt purged the whole cache to begin afresh. With two attempts open in one session, the start
 * of the second took the state of the first along: its progress, its feedback, its item pool. Each value
 * now has a key per attempt (the activity attempt id), and nothing is purged.
 *
 * Values written under the old session-wide keys - by an attempt that was running during the upgrade -
 * are still read when the attempt has none of its own.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class attempt_cache {
    /** @var string The session cache. */
    private const CACHE = 'adaptivequizattempt';

    /** @var string Marks a session whose cache has the layout of issue #146. */
    public const LAYOUT_KEY = 'sessionlayout';

    /** @var int The layout: pool in its own cache, state per attempt. */
    public const LAYOUT = 146;

    /**
     * The key of a value of an attempt.
     *
     * @param string $name
     * @param int $adaptiveattemptid Id of the activity attempt.
     * @return string
     */
    public static function key(string $name, int $adaptiveattemptid): string {
        return $name . '_' . $adaptiveattemptid;
    }

    /**
     * Stores a value of an attempt.
     *
     * @param string $name
     * @param int $adaptiveattemptid
     * @param mixed $value
     */
    public static function set(string $name, int $adaptiveattemptid, $value): void {
        if ($adaptiveattemptid <= 0) {
            return;
        }
        self::cache()->set(self::key($name, $adaptiveattemptid), $value);
    }

    /**
     * A value of an attempt; for an attempt without one, the value under the old session-wide key.
     *
     * @param string $name
     * @param int $adaptiveattemptid
     * @return mixed false when there is none.
     */
    public static function get(string $name, int $adaptiveattemptid) {
        $cache = self::cache();
        if ($adaptiveattemptid > 0) {
            $value = $cache->get(self::key($name, $adaptiveattemptid));
            if ($value !== false) {
                return $value;
            }
        }
        return $cache->get($name);
    }

    /**
     * The session cache.
     *
     * @return \cache_session|\cache
     */
    public static function cache() {
        return cache::make('local_catquiz', self::CACHE);
    }

    /**
     * Clears the session cache once, if it still has the layout from before issue #146.
     *
     * Before, every attempt start purged the cache; the item pools it held - hydrated, several megabytes
     * with a large pool - went with it. They are no longer read, and a session from before the upgrade
     * would keep them to its end, loaded with every request. At the first attempt start after the
     * upgrade they are cleared, as every attempt start did before; the state of a running attempt is
     * restored from the database (progress) or written again. Later starts clear nothing.
     *
     * @return bool Whether the cache was cleared.
     */
    public static function upgrade_session_layout(): bool {
        $cache = self::cache();
        if ((int) $cache->get(self::LAYOUT_KEY) === self::LAYOUT) {
            return false;
        }
        $cache->purge();
        $cache->set(self::LAYOUT_KEY, self::LAYOUT);
        return true;
    }
}
