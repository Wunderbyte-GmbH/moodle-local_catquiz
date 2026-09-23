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

namespace local_catquiz\local\calculation;

/**
 * The two explicit calculation modes (issue #43).
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class calculation_mode {
    /** @var string Incremental recalculation: fixed person params, single IP pass, same context. */
    public const INCREMENTAL_RECALCULATION = 'incremental';

    /** @var string Disruptive recalculation: iterative PP/IP, new context on success. */
    public const DISRUPTIVE_RECALCULATION = 'disruptive';

    /**
     * Returns all valid modes.
     *
     * @return string[]
     */
    public static function all(): array {
        return [self::INCREMENTAL_RECALCULATION, self::DISRUPTIVE_RECALCULATION];
    }

    /**
     * Whether the given mode is valid.
     *
     * @param string $mode
     * @return bool
     */
    public static function is_valid(string $mode): bool {
        return in_array($mode, self::all(), true);
    }
}
