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
 * Issue #26: the runtime item pool carries only the columns it needs.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;

/**
 * The lean select has to drop columns and nothing else.
 *
 * One query serves two consumers: the runtime selection, whose result is cached per
 * scale and context, and the manager tables, which display and filter questions. The
 * interface needs the question, category and scale names; the selection does not.
 *
 * Two things can go wrong and only one of them is loud. Dropping a column the
 * selection reads breaks it visibly. Dropping one that changes *which* rows come back
 * does not - the pool would quietly differ, and the selection would still return
 * something plausible.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\catquiz::return_sql_for_catscalequestions
 */
final class lean_pool_select_test extends advanced_testcase {
    /**
     * Creates a scale in its own context.
     *
     * The query resolves the scale tree, so a made-up id ends in
     * get_in_or_equal() being handed an empty array rather than in a useful failure.
     *
     * @return array [scaleid, contextid]
     */
    private function make_scale(): array {
        global $DB;

        $now = time();

        // The context has to exist before the scale can point at it. This line was
        // once a recursive call to make_scale() itself: the test did not fail, it
        // exhausted memory and the run was killed, which surfaced as a bare exit
        // code with no message and stopped the whole suite.
        $contextid = (int) $DB->insert_record('local_catquiz_catcontext', (object) [
            'name' => 'Lean pool context',
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
            'name' => 'Lean pool scale',
            'label' => 'LP1',
            'contextid' => $contextid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        return [$scaleid, $contextid];
    }

    /**
     * The lean select omits exactly the display-only columns.
     *
     * @return void
     */
    public function test_lean_select_omits_only_display_columns(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        [$scaleid, $contextid] = $this->make_scale();

        [$leanselect] = catquiz::return_sql_for_catscalequestions(
            [$scaleid],
            $contextid,
            [],
            $USER->id,
            null,
            null,
            true
        );

        foreach (catquiz::DISPLAYONLY_POOL_COLUMNS as $column) {
            $this->assertStringNotContainsString(
                's.' . $column,
                $leanselect,
                "The runtime pool must not carry $column - it is only shown, never read."
            );
        }

        // Everything else has to stay: a column silently missing from pool_columns()
        // would be dropped here without anyone noticing.
        $expected = array_diff(catquiz::pool_columns(), catquiz::DISPLAYONLY_POOL_COLUMNS);
        foreach ($expected as $column) {
            $this->assertStringContainsString(
                's.' . $column,
                $leanselect,
                "The column $column is needed by the selection and has to be carried."
            );
        }
    }

    /**
     * The full select stays untouched for the manager interface.
     *
     * @return void
     */
    public function test_full_select_is_unchanged(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        [$scaleid, $contextid] = $this->make_scale();

        [$select] = catquiz::return_sql_for_catscalequestions(
            [$scaleid],
            $contextid,
            [],
            $USER->id
        );

        $this->assertSame(
            '*',
            $select,
            'The manager tables select every column; narrowing that would remove the '
                . 'names they display and filter on.'
        );
    }

    /**
     * Every declared pool column really is produced by the query.
     *
     * The list was first transcribed by hand from the SQL heredoc and had 'contextid'
     * where the alias is 'lcipcontextid', plus six columns missing. The lean select
     * then asked for a column that does not exist and the query failed outright -
     * loudly, but only once it was run against a database.
     *
     * @return void
     */
    public function test_declared_columns_match_the_query(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $now = time();
        $contextid = (int) $DB->insert_record('local_catquiz_catcontext', (object) [
            'name' => 'Lean pool context',
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
            'name' => 'Lean pool scale',
            'label' => 'LP1',
            'contextid' => $contextid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        // Running the lean query at all is the assertion: an alias that does not
        // exist makes it fail.
        [$select, $from, $where, , $params] = catquiz::return_sql_for_catscalequestions(
            [$scaleid],
            $contextid,
            [],
            $USER->id,
            null,
            null,
            true
        );

        $rows = $DB->get_records_sql("SELECT $select FROM $from WHERE $where", $params, 0, 1);

        $this->assertIsArray(
            $rows,
            'The lean select names a column the query does not produce.'
        );
    }
}
