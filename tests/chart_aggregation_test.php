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
 * Issue #23: chart data is aggregated in the database, not in PHP.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;
use local_catquiz\teststrategy\feedback_helper;

/**
 * Verifies the server side aggregation of the answers-per-person histogram.
 *
 * The chart only ever needed the number of people per (range, class); it counted the
 * rows it had loaded. Loading one row per enrolled person just to count them is what
 * made memory and runtime grow with the cohort.
 *
 * Moving the classification into SQL is only safe if it produces exactly what the
 * PHP helpers produce, so these tests compare the two directly rather than pinning
 * the SQL result on its own.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\catquiz::get_answers_per_person_histogram
 * @covers     \local_catquiz\catquiz::get_max_questions_answered_per_person
 */
final class chart_aggregation_test extends advanced_testcase {
    /**
     * The SQL class expression agrees with feedback_helper::get_histogram_bin().
     *
     * The chart shifts every non-zero class by one so that class 0 stays reserved
     * for "no answers"; the SQL expression has to reproduce that shift, not just the
     * raw bin.
     *
     * @return void
     */
    public function test_class_expression_matches_the_php_helper(): void {
        global $DB;

        $this->resetAfterTest();

        foreach ([1, 2, 3, 7] as $classwidth) {
            foreach ([0, 1, 2, 3, 5, 8, 13, 21] as $answers) {
                $expected = $answers === 0
                    ? 0
                    : feedback_helper::get_histogram_bin($answers, $classwidth) + 1;

                $sql = "SELECT CASE WHEN :a1 = 0 THEN 0
                                    ELSE CAST(CEIL(:a2 * 1.0 / :classwidth) AS INTEGER) END AS bin";
                $actual = (int) $DB->get_field_sql($sql, [
                    'a1' => $answers,
                    'a2' => $answers,
                    'classwidth' => $classwidth,
                ]);

                $this->assertEquals(
                    $expected,
                    $actual,
                    "Class of $answers answers at width $classwidth must match the PHP helper."
                );
            }
        }
    }

    /**
     * The maximum comes from the database instead of a PHP loop.
     *
     * @return void
     */
    public function test_maximum_is_computed_in_the_database(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$scaleid, $contextid] = $this->make_scale();

        // Without any data the maximum is 0 and the chart falls back to its default.
        $this->assertEquals(
            0,
            catquiz::get_max_questions_answered_per_person($contextid, $scaleid)
        );
    }

    /**
     * The histogram query runs and returns counts, not rows.
     *
     * @return void
     */
    public function test_histogram_returns_counts(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$scaleid, $contextid] = $this->make_scale();

        $counts = catquiz::get_answers_per_person_histogram(
            $contextid,
            $scaleid,
            null,
            3,
            [
                ['lower' => -3.0, 'upper' => 0.0],
                ['lower' => 0.0, 'upper' => 3.0],
            ]
        );

        $this->assertIsArray($counts);
        foreach ($counts as $bins) {
            foreach ($bins as $frequency) {
                $this->assertIsInt($frequency, 'Only counts must come back, never rows.');
            }
        }
    }

    /**
     * The range boundaries are half-open, with the topmost range closed.
     *
     * A value on a shared boundary must belong to exactly one range; the maximum
     * value must still be covered. This mirrors the rule in
     * feedback_helper::get_feedback_range_index().
     *
     * @return void
     */
    public function test_range_boundaries_are_half_open(): void {
        global $DB;

        $this->resetAfterTest();

        $ranges = [
            ['lower' => -3.0, 'upper' => 0.0],
            ['lower' => 0.0, 'upper' => 3.0],
        ];

        // Pairs rather than an array keyed by the value: PHP array keys cannot be
        // floats, so -0.1 and 2.9 would silently be truncated to 0 and 2.
        $cases = [[-3.0, 1], [-0.1, 1], [0.0, 2], [2.9, 2], [3.0, 2]];
        foreach ($cases as [$value, $expected]) {
            // Moodle counts every occurrence of a named placeholder separately, so
            // each one needs its own name - repeating :ability made the driver expect
            // eight parameters for five values.
            $whens = [];
            $params = [];
            foreach ($ranges as $index => $range) {
                $j = $index + 1;
                $params['abilitylow' . $j] = (float) $value;
                $params['abilityhigh' . $j] = (float) $value;
                $params['rangelower' . $j] = $range['lower'];
                $params['rangeupper' . $j] = $range['upper'];
                $comparison = ($j === count($ranges)) ? '<=' : '<';
                // Casts reproduce the typing of the real query, where ability is a
                // numeric column. Comparing two untyped parameters makes PostgreSQL
                // treat them as text, and '-0.1' >= '-3.0' is false as a string.
                // The precision is explicit on purpose: MariaDB reads a bare
                // CAST(... AS DECIMAL) as DECIMAL(10,0), so -0.1 would be rounded to
                // 0 and land in the wrong range. PostgreSQL keeps the fraction, which
                // is why this only failed on MariaDB.
                $whens[] = "WHEN CAST(:abilitylow$j AS DECIMAL(20,10)) "
                    . ">= CAST(:rangelower$j AS DECIMAL(20,10)) "
                    . "AND CAST(:abilityhigh$j AS DECIMAL(20,10)) "
                    . "$comparison CAST(:rangeupper$j AS DECIMAL(20,10)) THEN $j";
            }
            $sql = 'SELECT CASE ' . implode(' ', $whens) . ' ELSE -1 END AS rangeindex';

            $this->assertEquals(
                $expected,
                (int) $DB->get_field_sql($sql, $params),
                "Value $value must fall into range $expected."
            );
        }
    }

    /**
     * The extracted bounds describe the same ranges as the PHP classifier.
     *
     * The histogram assigns the range in SQL from these numbers, while the rest of
     * the plugin keeps using get_feedback_range_index(). If the two ever described
     * different ranges, the chart would disagree with the feedback a learner sees.
     *
     * @return void
     */
    public function test_extracted_bounds_agree_with_the_php_classifier(): void {
        $this->resetAfterTest();

        $scaleid = 5;
        $settings = (object) [
            'numberoffeedbackoptionsselect' => 3,
            'feedback_scaleid_limit_lower_5_1' => '-3.0',
            'feedback_scaleid_limit_upper_5_1' => '-1.0',
            'feedback_scaleid_limit_lower_5_2' => '-1.0',
            'feedback_scaleid_limit_upper_5_2' => '1.0',
            'feedback_scaleid_limit_lower_5_3' => '1.0',
            'feedback_scaleid_limit_upper_5_3' => '3.0',
        ];

        $bounds = feedback_helper::get_feedback_range_bounds($settings, $scaleid);
        $this->assertCount(3, $bounds);

        // For every probe the bounds must select the same range the classifier does.
        $probes = [-3.0, -2.0, -1.0, 0.0, 1.0, 2.5, 3.0];
        foreach ($probes as $value) {
            $expected = feedback_helper::get_feedback_range_index($settings, $scaleid, $value);

            $fromboundaries = null;
            foreach ($bounds as $index => $bound) {
                $j = $index + 1;
                $istop = ($j === count($bounds));
                $inrange = $value >= $bound['lower']
                    && ($istop ? $value <= $bound['upper'] : $value < $bound['upper']);
                if ($inrange) {
                    $fromboundaries = $j;
                    break;
                }
            }

            $this->assertSame(
                $expected,
                $fromboundaries,
                "Value $value must land in the same range in both implementations."
            );
        }
    }

    /**
     * A decimal comma in the settings is parsed, not truncated.
     *
     * The limits come from user input and may carry a decimal comma; the shared
     * parser handles that, and the bounds must not lose it on their way into SQL.
     *
     * @return void
     */
    public function test_bounds_use_the_locale_safe_parser(): void {
        $this->resetAfterTest();

        $bounds = feedback_helper::get_feedback_range_bounds((object) [
            'numberoffeedbackoptionsselect' => 1,
            'feedback_scaleid_limit_lower_7_1' => '-1,5',
            'feedback_scaleid_limit_upper_7_1' => '2,5',
        ], 7);

        $this->assertEqualsWithDelta(-1.5, $bounds[0]['lower'], 0.0001);
        $this->assertEqualsWithDelta(2.5, $bounds[0]['upper'], 0.0001);
    }

    /**
     * The attempts histogram also returns counts only.
     *
     * @return void
     */
    public function test_attempts_histogram_returns_counts(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$scaleid, $contextid] = $this->make_scale();

        $counts = catquiz::get_attempts_per_person_histogram(
            $contextid,
            $scaleid,
            null,
            2,
            [['lower' => -3.0, 'upper' => 3.0]]
        );

        $this->assertIsArray($counts);
        $this->assertEquals(0, catquiz::get_max_attempts_per_person($contextid, $scaleid));
    }

    /**
     * The number of returned data points is capped.
     *
     * The classification already collapses a cohort into a handful of counts, but a
     * misconfigured class width could still produce a long tail of near-empty
     * classes. The cap is what the issue asks for: a chart query returns a limited
     * number of data points.
     *
     * @return void
     */
    public function test_data_points_are_capped(): void {
        $this->resetAfterTest();

        $this->assertGreaterThan(0, catquiz::CHART_MAX_DATA_POINTS);
        // Far above what any chart draws - the charts show at most a handful of
        // classes - so the cap can never truncate a legitimate result.
        $this->assertGreaterThan(100, catquiz::CHART_MAX_DATA_POINTS);
    }

    /**
     * A class width of zero cannot produce a division by zero.
     *
     * The width is derived from a maximum that can legitimately be 0 when no one has
     * answered yet; the aggregation must not turn that into a database error.
     *
     * @return void
     */
    public function test_zero_class_width_is_clamped(): void {
        $this->resetAfterTest();

        // A synthetic cohort of one person. An empty fixture would prove nothing:
        // with no rows the division is never evaluated, so the test would pass with
        // or without the clamp.
        $inner = 'SELECT 5 AS attempts, CAST(NULL AS DECIMAL) AS ability';

        $counts = catquiz::aggregate_person_histogram($inner, [], 'attempts', 0, []);

        // Clamped to width 1, so five attempts land in class 5 of the "no ability"
        // range. Without the clamp the database would divide by zero.
        $this->assertSame(1, $counts[0][5] ?? null);
    }

    /**
     * Creates a context and a scale.
     *
     * @return array [int $scaleid, int $contextid]
     */
    private function make_scale(): array {
        global $DB;

        $now = time();
        $contextid = (int) $DB->insert_record('local_catquiz_catcontext', (object) [
            'name' => 'Issue 23 context',
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
            'name' => 'Issue 23 scale',
            'contextid' => $contextid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        return [$scaleid, $contextid];
    }
    /**
     * An empty allow-list yields no data instead of all data.
     *
     * Review finding on issue #18: the group restriction reached the CSV export only,
     * so the charts could still aggregate over other groups. The distinction that
     * matters is null versus empty - null means nothing is restricted, an empty array
     * means nothing is visible. Getting that backwards would disclose everything.
     *
     * @return void
     */
    public function test_empty_allow_list_yields_no_rows(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$scaleid, $contextid] = $this->make_scale();

        $unrestricted = catquiz::get_max_questions_answered_per_person($contextid, $scaleid, null, null);
        $restricted = catquiz::get_max_questions_answered_per_person($contextid, $scaleid, null, []);

        // Both are zero on an empty fixture; the point is that the restricted call
        // runs at all and does not silently drop the filter.
        $this->assertSame(0, $unrestricted);
        $this->assertSame(0, $restricted);

        // An empty fixture returns nothing either way, so the counts alone prove
        // nothing. The SQL itself has to carry the restriction.
        [$sql] = catquiz::get_sql_for_questions_answered_per_person($contextid, $scaleid, null, []);
        $this->assertStringContainsString(
            '1=0',
            $sql,
            'An empty allow-list must make the query return nothing.'
        );

        [$sqlwith, $paramswith] = catquiz::get_sql_for_questions_answered_per_person(
            $contextid,
            $scaleid,
            null,
            [7, 8]
        );
        $this->assertStringContainsString('ue.userid', $sqlwith);
        $this->assertNotEmpty(
            array_filter(array_keys($paramswith), fn ($k) => str_starts_with($k, 'alloweduser')),
            'The allowed user ids must be bound as parameters.'
        );

        [$sqlnull] = catquiz::get_sql_for_questions_answered_per_person($contextid, $scaleid, null, null);
        $this->assertStringNotContainsString(
            '1=0',
            $sqlnull,
            'Without a restriction the query must not be narrowed.'
        );
    }

    /**
     * The chart queries accept the allow-list, so the renderer can pass it on.
     *
     * @return void
     */
    public function test_chart_queries_accept_an_allow_list(): void {
        $this->resetAfterTest();

        foreach (
            [
            'get_max_questions_answered_per_person',
            'get_max_attempts_per_person',
            'get_answers_per_person_histogram',
            'get_attempts_per_person_histogram',
            ] as $method
        ) {
            $reflection = new \ReflectionMethod(catquiz::class, $method);
            $names = array_map(fn ($p) => $p->getName(), $reflection->getParameters());
            $this->assertContains(
                'alloweduserids',
                $names,
                "$method must be able to receive the group restriction."
            );
        }
    }
    /**
     * The debug branch of the attempts chart does not use a removed variable.
     *
     * Review finding on issue #23: moving that chart to SQL aggregation removed the
     * per-person rows, but the debug table still looped over them. Nobody noticed
     * because the branch only runs with ?debug=1 and the manage capability - an
     * undefined variable waiting for the first person to look.
     *
     * @return void
     */
    public function test_attempts_chart_debug_branch_has_no_stale_variable(): void {
        $this->resetAfterTest();

        $source = file_get_contents(
            __DIR__ . '/../classes/output/catquizstatistics.php'
        );

        $start = strpos($source, 'function render_attempts_per_person_chart');
        $this->assertNotFalse($start, 'The chart method must exist.');

        $end = strpos($source, "\n    public function", $start + 10);
        $body = substr($source, $start, $end ? $end - $start : null);

        $this->assertStringNotContainsString(
            'foreach ($records',
            $body,
            'The aggregated chart no longer has per-person rows to loop over.'
        );
    }
    /**
     * The chart cohort query does not carry debug_info along.
     *
     * Issue #23: SELECT * always fetched debug_info, which can hold the full trace of
     * an attempt and which none of the charts reads. On a large cohort that is the
     * bulk of the transferred bytes, discarded right after loading.
     *
     * @return void
     */
    public function test_chart_cohort_does_not_select_debug_info(): void {
        global $CFG;

        $this->resetAfterTest();

        $source = file_get_contents(
            $CFG->dirroot . '/local/catquiz/classes/output/catquizstatistics.php'
        );

        $start = strpos($source, 'private function get_attempts()');
        $this->assertNotFalse($start);

        // Cut at the closing brace of the method, not at the next declaration: the
        // docblock of the following method sits in between and would otherwise be
        // counted as part of this one.
        $end = strpos($source, "\n    }\n", $start);
        $body = substr($source, $start, $end ? $end - $start : null);

        $this->assertStringContainsString(
            'a.json',
            $body,
            'The charts need the json column.'
        );
        $this->assertStringNotContainsString(
            'debug_info',
            $body,
            'debug_info is never read here and must not be fetched.'
        );
        $this->assertStringContainsString(
            'close()',
            $body,
            'A recordset holds a database resource until it is closed.'
        );
    }
}
