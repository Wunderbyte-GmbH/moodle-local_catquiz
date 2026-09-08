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
 * Who/what triggered a calculation run (issue #43).
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class calculation_trigger {
    /** @var string Periodic scheduled task. */
    public const SCHEDULED = 'scheduled';

    /** @var string Manual action from the CAT management area (ad-hoc task). */
    public const MANUAL = 'manual';

    /**
     * Returns all valid triggers.
     *
     * @return string[]
     */
    public static function all(): array {
        return [self::SCHEDULED, self::MANUAL];
    }

    /**
     * Whether the given trigger is valid.
     *
     * @param string $trigger
     * @return bool
     */
    public static function is_valid(string $trigger): bool {
        return in_array($trigger, self::all(), true);
    }
}
