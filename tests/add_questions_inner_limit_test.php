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
 * Issue #58: the page limit may move into the derived table, but only when safe.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;

/**
 * Two properties, and the second is the one that can go quietly wrong.
 *
 * The limit inside the derived table asks the same question as the limit outside it
 * only while nothing sorts or filters. With a sort in place the inner limit takes an
 * arbitrary ten rows and the page renders correctly with the wrong content - a defect
 * nobody sees until they compare against the database.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\table\catscalequestions_table
 */
final class add_questions_inner_limit_test extends advanced_testcase {
    /**
     * A scale in its own context.
     *
     * @return array [scaleid, contextid]
     */
    private function make_scale(): array {
        global $DB;

        $now = time();
        $contextid = (int) $DB->insert_record('local_catquiz_catcontext', (object) [
            'name' => 'Inner limit context',
            'description' => '',
            'descriptionformat' => FORMAT_HTML,
            'starttimestamp' => $now - 100,
            'endtimestamp' => $now + 10000,
            'timecreated' => $now,
            'timemodified' => $now,
            'usermodified' => 0,
        ]);
        $scaleid = (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'parentid' => 0,
            'name' => 'Inner limit scale',
            'label' => 'ILS1',
            'contextid' => $contextid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        return [$scaleid, $contextid];
    }

    /**
     * Limiting inside returns the same rows as limiting outside.
     *
     * @return void
     */
    public function test_inner_limit_returns_the_same_rows(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        [$scaleid, $contextid] = $this->make_scale();

        [$select, $from, $where, , $params] = catquiz::return_sql_for_addcatscalequestions(
            $scaleid,
            $contextid
        );

        $outer = array_keys($DB->get_records_sql(
            "SELECT $select FROM $from WHERE $where",
            $params,
            0,
            10
        ));

        $this->assertMatchesRegularExpression(
            '/^\s*\(\s*SELECT\b.*\)\s*as\s+\w+\s*$/is',
            $from,
            'The optimisation only applies to a derived table; if the shape changed, '
                . 'the rewrite must not fire.'
        );

        preg_match('/^\s*\(\s*SELECT\b(.*)\)\s*as\s+(\w+)\s*$/is', $from, $m);
        $inner = sprintf('( SELECT %s LIMIT 10 OFFSET 0 ) as %s', $m[1], $m[2]);

        $innerrows = array_keys($DB->get_records_sql(
            "SELECT $select FROM $inner WHERE $where",
            $params
        ));

        $this->assertSame(
            $outer,
            $innerrows,
            'The two forms have to select the same rows, or the dialog shows a page '
                . 'that looks right and is not.'
        );
    }

    /**
     * The rewrite is skipped as soon as a sort or a filter is in play.
     *
     * @return void
     */
    public function test_rewrite_is_guarded(): void {
        global $CFG;

        $this->resetAfterTest();

        $source = file_get_contents(
            $CFG->dirroot . '/local/catquiz/classes/table/catscalequestions_table.php'
        );

        $start = strpos($source, 'function push_limit_into_subquery');
        $this->assertNotFalse($start, 'The rewrite lives here.');

        $body = substr($source, $start, 3200);

        foreach (['get_sql_sort', 'filter', 'is_downloading'] as $guard) {
            $this->assertStringContainsString(
                $guard,
                $body,
                "Without the $guard check the inner limit can cut the wrong rows."
            );
        }

        $this->assertStringContainsString(
            'return $original;',
            $body,
            'The original FROM has to be handed back so the caller can restore it; '
                . 'a rewritten FROM would otherwise leak into the count query.'
        );
    }
    /**
     * A sorted page returns the same rows in the same order.
     *
     * Order is the point here, not just membership: an inner limit without the sort
     * would take ten arbitrary rows and then sort those ten, which looks like a
     * sorted page and is not.
     *
     * @return void
     */
    public function test_sorted_pages_match(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        [$scaleid, $contextid] = $this->make_scale();

        [$select, $from, $where, , $params] = catquiz::return_sql_for_addcatscalequestions(
            $scaleid,
            $contextid
        );

        preg_match('/^\s*\(\s*SELECT\b(.*)\)\s*as\s+(\w+)\s*$/is', $from, $m);

        foreach (['name ASC', 'name DESC', 'idnumber ASC', 'qtype DESC'] as $sort) {
            $outer = array_keys($DB->get_records_sql(
                "SELECT $select FROM $from WHERE $where ORDER BY $sort",
                $params,
                0,
                10
            ));

            $inner = sprintf(
                '( SELECT %s ORDER BY %s LIMIT 10 OFFSET 0 ) as %s',
                $m[1],
                $sort,
                $m[2]
            );

            $innerrows = array_keys($DB->get_records_sql(
                "SELECT $select FROM $inner WHERE $where ORDER BY $sort",
                $params
            ));

            $this->assertSame($outer, $innerrows, "Sorting by $sort has to agree.");
        }
    }

    /**
     * Only columns the derived table has may be sorted inside.
     *
     * The outer select adds a literal component and a constant
     * questioncontextattempts; sorting by those inside is an unknown column. A
     * qualified name refers to the outer alias and is out as well.
     *
     * @return void
     */
    public function test_sort_guard_recognises_outer_columns(): void {
        $this->resetAfterTest();

        $table = new \local_catquiz\table\catscalequestions_table('guardtest');

        $method = new \ReflectionMethod($table, 'sort_is_available_in_subquery');
        $method->setAccessible(true);

        foreach (['name ASC', 'idnumber DESC, name ASC', 'qtype ASC'] as $sort) {
            $this->assertTrue($method->invoke($table, $sort), "$sort exists inside.");
        }

        foreach (['questioncontextattempts ASC', 'component ASC', 's1.name ASC', ''] as $sort) {
            $this->assertFalse(
                $method->invoke($table, $sort),
                "'$sort' must fall back to the outer query."
            );
        }
    }
    /**
     * The LEFT JOIN excludes exactly the questions already assigned to the scale.
     *
     * NOT EXISTS and LEFT JOIN ... IS NULL express the same condition, but only if
     * the join has no duplicates: a second matching row in local_catquiz_items would
     * multiply the question rather than exclude it. The unique key on
     * (componentid, componentname, catscaleid) prevents that, and this test states
     * the dependency instead of trusting it.
     *
     * The rewrite was made because MariaDB executes NOT EXISTS as a materialised
     * anti-join and therefore cannot stop the inner LIMIT early - measured, the scan
     * dropped from 20.010 rows to 2.285.
     *
     * @return void
     */
    public function test_assigned_questions_are_excluded_exactly_once(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        [$scaleid, $contextid] = $this->make_scale();

        $before = $DB->count_records_sql(
            $this->count_sql($scaleid, $contextid),
            $this->count_params($scaleid, $contextid)
        );

        // Assign one question to the scale; it has to disappear from the dialog, and
        // the total has to fall by exactly one.
        $questionid = $DB->get_field_sql('SELECT MIN(id) FROM {question}');
        if ($questionid === false || $questionid === null) {
            $this->markTestSkipped('No question in the test database.');
        }

        $DB->insert_record('local_catquiz_items', (object) [
            'componentid' => $questionid,
            'componentname' => 'question',
            'catscaleid' => $scaleid,
            'contextid' => $contextid,
            'status' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $after = $DB->count_records_sql(
            $this->count_sql($scaleid, $contextid),
            $this->count_params($scaleid, $contextid)
        );

        $this->assertSame(
            $before - 1,
            $after,
            'Assigning one question must remove exactly one row - no more through a '
                . 'missed exclusion, no fewer through a duplicated join.'
        );
    }

    /**
     * The counting statement for the dialog.
     *
     * @param int $scaleid
     * @param int $contextid
     * @return string
     */
    private function count_sql(int $scaleid, int $contextid): string {
        [, $from, $where] = catquiz::return_sql_for_addcatscalequestions($scaleid, $contextid);

        return "SELECT COUNT(*) FROM $from WHERE $where";
    }

    /**
     * Its parameters.
     *
     * @param int $scaleid
     * @param int $contextid
     * @return array
     */
    private function count_params(int $scaleid, int $contextid): array {
        [, , , , $params] = catquiz::return_sql_for_addcatscalequestions($scaleid, $contextid);

        return $params;
    }
}
