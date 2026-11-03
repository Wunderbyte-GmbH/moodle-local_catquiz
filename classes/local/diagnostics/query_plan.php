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

namespace local_catquiz\local\diagnostics;

use Throwable;

/**
 * The plan of a query as text, every line of it (issue #104, K5).
 *
 * A plan is a list of rows whose first column is no key: PostgreSQL returns one line per row in a single
 * column, and two nodes may print the same line ("Buffers: shared hit=3"); MariaDB returns one row per
 * table, all of a SELECT with the same id. get_records_sql() keys by the first column, so identical lines
 * fell onto one key and the plan lost rows and their order. Read as a list, every row stays, in order:
 * on PostgreSQL as the one column of the plan (a recordset would wrap EXPLAIN into a cursor, which
 * PostgreSQL refuses), elsewhere as a recordset.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class query_plan {
    /**
     * The plan of a query in the dialect of the current engine.
     *
     * With $analyze the query runs and the plan reports what happened (PostgreSQL EXPLAIN ANALYZE with
     * buffers, MariaDB ANALYZE FORMAT=JSON); without, it is the plan the optimiser chose (plain EXPLAIN).
     *
     * @param string $sql
     * @param array $params
     * @param bool $analyze
     * @return string One line per row of the plan, or the reason it failed.
     */
    public static function explain(string $sql, array $params = [], bool $analyze = true): string {
        global $DB;
        if ($analyze) {
            $prefix = $DB->get_dbfamily() === 'postgres' ? 'EXPLAIN (ANALYZE, BUFFERS)' : 'ANALYZE FORMAT=JSON';
        } else {
            $prefix = 'EXPLAIN';
        }
        try {
            return implode("\n", self::rows("$prefix $sql", $params));
        } catch (Throwable $e) {
            return 'EXPLAIN failed: ' . $e->getMessage();
        }
    }

    /**
     * The rows of a statement whose first column is no key, each as one line, in the order of the database.
     *
     * @param string $sql
     * @param array $params
     * @return string[]
     */
    public static function rows(string $sql, array $params = []): array {
        global $DB;
        if ($DB->get_dbfamily() === 'postgres') {
            return array_map(fn($value) => (string) $value, array_values($DB->get_fieldset_sql($sql, $params)));
        }
        $lines = [];
        $rows = $DB->get_recordset_sql($sql, $params);
        foreach ($rows as $row) {
            $lines[] = implode(' ', array_map(fn($value) => (string) $value, (array) $row));
        }
        $rows->close();
        return $lines;
    }
}
