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

use advanced_testcase;

/**
 * A query plan keeps every row, in order (issue #104, K5).
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\local\diagnostics\query_plan
 */
final class query_plan_test extends advanced_testcase {
    /**
     * The same scan twice in one plan: both rows are there, as many as the database returns.
     *
     * PostgreSQL prints the two scans of the UNION ALL as identical lines; MariaDB gives both tables of the
     * join the same id. Keyed by the first column, one of them was lost.
     */
    public function test_identical_plan_rows_are_kept(): void {
        global $DB;
        $sql = 'SELECT c1.id FROM {config} c1 JOIN {config} c2 ON c2.id = c1.id
                UNION ALL
                SELECT c1.id FROM {config} c1 JOIN {config} c2 ON c2.id = c1.id';

        $plan = explode("\n", query_plan::explain($sql, [], false));

        // What the database returns, read without any key: the reference.
        $raw = [];
        $result = $DB->get_dbfamily() === 'postgres'
            ? array_map(fn($line) => [$line], $DB->get_fieldset_sql("EXPLAIN $sql"))
            : $DB->get_recordset_sql("EXPLAIN $sql");
        foreach ($result as $row) {
            $raw[] = implode(' ', array_map(fn($value) => (string) $value, (array) $row));
        }
        $this->assertSame($raw, $plan, 'Every row of the plan, in the order of the database.');
        $this->assertGreaterThan(
            count(array_unique(array_map(fn($line) => strtok($line, ' '), $plan))),
            count($plan),
            'The plan has rows sharing their first column - the case the keyed read lost.'
        );
        $this->assertLessThan(
            count($plan),
            count($DB->get_records_sql("EXPLAIN $sql")),
            'Read keyed by the first column, rows go missing (the old behaviour).'
        );
        // The keyed read complains about its duplicate keys - that is the point of the comparison.
        $this->resetDebugging();
    }

    /**
     * With ANALYZE the query runs; a failing query returns its reason instead of throwing.
     */
    public function test_analyze_and_failure(): void {
        $this->assertNotSame('', query_plan::explain('SELECT id FROM {config} WHERE id > :id', ['id' => 0]));
        $this->assertStringStartsWith('EXPLAIN failed: ', query_plan::explain('SELECT nosuchcolumn FROM {config}'));
    }
}
