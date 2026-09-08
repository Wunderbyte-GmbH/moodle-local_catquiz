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
 * Render stub used by the reload_template web service test.
 *
 * Lives in its own file because Moodle requires one class per file; it used to sit
 * next to the test class, which the coding standard rejects.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

/**
 * Simple render stub for reload_template test.
 */
class external_reload_template_stub {
    /**
     * Accept any constructor params from webservice payload.
     *
     * @param mixed ...$params
     */
    public function __construct(...$params) {
    }

    /**
     * Mimic template export function.
     *
     * @return array
     */
    public function export_for_template(): array {
        return [
            'ok' => true,
        ];
    }
}
