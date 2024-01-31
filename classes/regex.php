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
 * Example for regexes
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

/**
 * Examples
 */
class regex {
    public function add_db_prefixes(string $text, string $prefix): string {
        $text = preg_replace('/\{/', $prefix, $text);
        $text = preg_replace('/\}/', '', $text);
        return $text;
    }

    /**
     * Should convert something like m_local_catquiz_tablename to {local_catquiz_tablename}
     *
     * @return string
     */
    public function remove_db_prefixes(string $text): string {
        return $text;
    }
}
