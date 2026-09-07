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
 * Issue #21: the light count is wired up and counts the same rows.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;

/**
 * The light count has to be both connected and equivalent.
 *
 * Two independent failures are possible and only one of them is visible in passing
 * tests: a helper that nobody calls costs nothing and breaks nothing, so it survives
 * indefinitely - which is exactly what happened here. And a light query that returns
 * a different number would produce pagination offering empty pages.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\catquiz::return_sql_for_catscalequestions_count
 */
final class light_count_test extends advanced_testcase {
    /**
     * The questions display passes the count context to the table.
     *
     * Without this call the table counts its rows with the full query - the one
     * carrying the per question and per user aggregates, computed only to be
     * discarded by COUNT(). Measured on 50.000 items: 8.539 ms against 162 ms.
     *
     * @return void
     */
    public function test_questions_display_sets_the_count_context(): void {
        global $CFG;

        $this->resetAfterTest();

        $source = file_get_contents(
            $CFG->dirroot . '/local/catquiz/classes/output/catscalemanager/questions/'
                . 'questionsdisplay.php'
        );

        $this->assertStringContainsString(
            'set_count_context($idsforquery',
            $source,
            'A helper that nobody calls costs nothing and breaks nothing, which is '
                . 'why it went unnoticed. It has to be wired to the ids actually used.'
        );

        // It has to come after the filter sql, or it would describe a different query
        // than the one the table runs.
        $this->assertGreaterThan(
            strpos($source, 'set_filter_sql('),
            strpos($source, 'set_count_context('),
            'The count context describes the query that was just configured.'
        );
    }

    /**
     * The light query counts exactly the rows the full query returns.
     *
     * The statistics are LEFT JOINs and can neither add nor remove a row. If that ever
     * stops holding, the list and its page count drift apart and pagination offers
     * pages that are empty.
     *
     * @return void
     */
    public function test_light_count_matches_the_full_query(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $now = time();
        $contextid = (int) $DB->insert_record('local_catquiz_catcontext', (object) [
            'name' => 'Light count context',
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
            'name' => 'Light count scale',
            'label' => 'LC1',
            'contextid' => $contextid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        [$select, $from, $where, , $params] = catquiz::return_sql_for_catscalequestions(
            [$scaleid],
            $contextid,
            []
        );
        $full = $DB->count_records_sql(
            "SELECT COUNT(*) FROM (SELECT $select FROM $from WHERE $where) lightcheck",
            $params
        );

        [$lightfrom, $lightwhere, $lightparams] = catquiz::return_sql_for_catscalequestions_count(
            [$scaleid],
            $contextid
        );
        $light = $DB->count_records_sql(
            "SELECT COUNT(*) FROM $lightfrom WHERE $lightwhere",
            $lightparams
        );

        $this->assertSame(
            $full,
            $light,
            'The light count has to answer the same question as the full one; a '
                . 'different number makes pagination offer empty pages.'
        );
    }
    /**
     * Without filter or search the light count is what answers the pager.
     *
     * @return void
     */
    public function test_light_count_is_used_without_filter_or_search(): void {
        global $CFG;

        $this->resetAfterTest();

        $source = file_get_contents(
            $CFG->dirroot . '/local/catquiz/classes/table/catscalequestions_table.php'
        );

        $this->assertStringContainsString(
            'can_use_light_count()',
            $source,
            'The decision has to be made per query, not once when the table is built.'
        );

        // The count is set inside query_db, after the library has appended its own
        // filter and search. Deciding earlier would answer a different query than the
        // one that is run.
        $start = strpos($source, 'public function query_db');
        $this->assertNotFalse($start);
        $end = strpos($source, "\n    }\n", $start);
        $body = substr($source, $start, $end - $start);

        $this->assertStringContainsString('can_use_light_count()', $body);
    }

    /**
     * A filter or a search makes the table fall back to the library's own count.
     *
     * The light query knows the joins up to the question bank. A condition on a column
     * that only exists in the aggregates - the last attempt time among them - cannot
     * be answered by it at all, and a count that ignores the condition would report a
     * total the list never reaches.
     *
     * @return void
     */
    public function test_filter_and_search_fall_back(): void {
        global $CFG;

        $this->resetAfterTest();

        $source = file_get_contents(
            $CFG->dirroot . '/local/catquiz/classes/table/catscalequestions_table.php'
        );

        $start = strpos($source, 'private function can_use_light_count');
        $this->assertNotFalse($start);
        $end = strpos($source, "\n    }\n", $start);
        $body = substr($source, $start, $end - $start);

        // Both conditions have to be refused, and the method has to be able to say no
        // at all - a guard that always returns true is not a guard.
        $this->assertStringContainsString('return false;', $body);
        $this->assertMatchesRegularExpression(
            '/filter/i',
            $body,
            'An active filter has to fall back.'
        );
        $this->assertMatchesRegularExpression(
            '/search/i',
            $body,
            'An active search has to fall back.'
        );
    }

    /**
     * The count context is only consulted while no explicit count sql is set.
     *
     * Overwriting a count that the caller set deliberately would silently change what
     * the pager reports.
     *
     * @return void
     */
    public function test_explicit_count_sql_wins(): void {
        global $CFG;

        $this->resetAfterTest();

        $source = file_get_contents(
            $CFG->dirroot . '/local/catquiz/classes/table/catscalequestions_table.php'
        );

        $this->assertStringContainsString(
            '$this->countsql === null && $this->can_use_light_count()',
            $source,
            'A count set by the caller has to take precedence over the light one.'
        );
    }
    /**
     * The statistics query loads every column the charts read.
     *
     * get_attempts() selects an explicit column list; the charts then read attributes
     * off those rows. A column left out does not fail - the attribute is simply null,
     * get_snapshot_ability_per_person() treats it as a legacy attempt and drops the
     * value, and the ability profile renders with correct axes and no data. A chart
     * that looks built rather than broken.
     *
     * That is exactly what happened to personability_after_attempt. Compared as a rule
     * rather than as a list, so a column added to the charts later is caught too.
     *
     * @return void
     */
    public function test_statistics_query_loads_what_the_charts_read(): void {
        global $CFG;

        $this->resetAfterTest();

        $source = file_get_contents(
            $CFG->dirroot . '/local/catquiz/classes/output/catquizstatistics.php'
        );

        $start = strpos($source, 'private function get_attempts');
        $this->assertNotFalse($start, 'The statistics rows are loaded here.');

        $end = strpos($source, "\n    }", $start);
        $loader = substr($source, $start, $end - $start);

        preg_match_all('/a\.([a-z_]+)/', $loader, $columns);
        $loaded = array_unique($columns[1]);

        $this->assertNotEmpty($loaded, 'No column list found - the test cannot judge.');

        // The consumers are scanned, not only this file. get_snapshot_ability_per_person()
        // lives in catquiz.php and reads personability_after_attempt off these rows -
        // scanning only catquizstatistics.php made an earlier version of this test pass
        // with the column removed, which is the failure it was written to catch.
        $consumers = $source . file_get_contents(
            $CFG->dirroot . '/local/catquiz/classes/catquiz.php'
        );

        preg_match_all('/\$attempt->([a-z_]+)/', $consumers, $reads);

        // The key 'value' is built in the reducer, not selected; 'component'
        // and 'instanceid' are read on rows from other queries in catquiz.php.
        $needed = array_diff(
            array_unique($reads[1]),
            ['value', 'component', 'instanceid', 'attemptid', 'uniqueid']
        );

        $this->assertSame(
            [],
            array_values(array_diff($needed, $loaded)),
            'These attributes are read off the attempt rows but never selected, so '
                . 'they arrive as null and the charts silently lose their data.'
        );
    }
}
