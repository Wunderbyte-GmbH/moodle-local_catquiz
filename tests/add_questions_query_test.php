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
 * Issue #22: the "add question" dialog excludes assigned questions without scans.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;

/**
 * Verifies the rebuilt query behind the "add question" dialog.
 *
 * It used to concatenate every scale a question belongs to into a string such as
 * '-3--7-' and then filter with LIKE '%-3-%'. That string was never shown anywhere -
 * it existed only to express "not already in this scale" - and a leading wildcard
 * cannot use an index, so the filter forced a scan and the aggregation forced a
 * GROUP BY over the whole question bank.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\catquiz::return_sql_for_addcatscalequestions
 */
final class add_questions_query_test extends advanced_testcase {
    /**
     * Creates a context and a scale.
     *
     * @return array [int $scaleid, int $contextid]
     */
    private function make_scale(): array {
        global $DB;

        $now = time();
        $contextid = (int) $DB->insert_record('local_catquiz_catcontext', (object) [
            'name' => 'Issue 22 context',
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
            'name' => 'Issue 22 scale',
            'contextid' => $contextid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        return [$scaleid, $contextid];
    }

    /**
     * Creates a question with its bank entry and version, returns the question id.
     *
     * @param string $name
     * @return int
     */
    private function make_question(string $name): int {
        global $DB;

        $now = time();
        $category = $this->getDataGenerator()->get_plugin_generator('core_question')
            ->create_question_category();

        $questionid = (int) $DB->insert_record('question', (object) [
            'name' => $name,
            'questiontext' => 'body of ' . $name,
            'questiontextformat' => FORMAT_HTML,
            'qtype' => 'truefalse',
            'generalfeedback' => '',
            'generalfeedbackformat' => FORMAT_HTML,
            'timecreated' => $now,
            'timemodified' => $now,
            'createdby' => 2,
            'modifiedby' => 2,
        ]);
        $entryid = (int) $DB->insert_record('question_bank_entries', (object) [
            'questioncategoryid' => $category->id,
            'idnumber' => null,
            'ownerid' => 2,
        ]);
        $DB->insert_record('question_versions', (object) [
            'questionbankentryid' => $entryid,
            'version' => 1,
            'questionid' => $questionid,
            'status' => 'ready',
        ]);

        return $questionid;
    }

    /**
     * Runs the dialog query and returns the ids it offers.
     *
     * @param int $scaleid
     * @param int $contextid
     * @return int[]
     */
    private function offered_ids(int $scaleid, int $contextid): array {
        global $DB;

        [$select, $from, $where, , $params] = catquiz::return_sql_for_addcatscalequestions($scaleid, $contextid);
        $rows = $DB->get_records_sql("SELECT $select FROM $from WHERE $where", $params);

        return array_map('intval', array_column($rows, 'id'));
    }

    /**
     * The query carries neither GROUP_CONCAT nor a wildcard LIKE any more.
     *
     * @return void
     */
    public function test_query_has_no_group_concat_and_no_wildcard_like(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$scaleid, $contextid] = $this->make_scale();

        [$select, $from, $where, , $params] = catquiz::return_sql_for_addcatscalequestions($scaleid, $contextid);
        $sql = $select . ' ' . $from . ' ' . $where;

        $this->assertStringNotContainsStringIgnoringCase('group_concat', $sql);
        $this->assertStringNotContainsString('catscaleids', $sql);
        $this->assertStringContainsString('NOT EXISTS', $from);
        $this->assertArrayNotHasKey('catscaleid', $params, 'The wildcard parameter must be gone.');
        foreach ($params as $value) {
            $this->assertStringNotContainsString('%-', (string) $value);
        }
    }

    /**
     * A question already assigned to the scale is not offered again.
     *
     * @return void
     */
    public function test_assigned_questions_are_excluded(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        [$scaleid, $contextid] = $this->make_scale();
        $assigned = $this->make_question('Already assigned');
        $free = $this->make_question('Still available');

        $this->assertContains($assigned, $this->offered_ids($scaleid, $contextid));

        $DB->insert_record('local_catquiz_items', (object) [
            'componentid' => $assigned,
            'componentname' => 'question',
            'catscaleid' => $scaleid,
            'contextid' => $contextid,
            'status' => 0,
        ]);

        $offered = $this->offered_ids($scaleid, $contextid);
        $this->assertNotContains($assigned, $offered, 'An assigned question must not be offered again.');
        $this->assertContains($free, $offered, 'Unassigned questions must stay available.');
    }

    /**
     * An assignment to a different scale does not hide the question.
     *
     * @return void
     */
    public function test_assignment_to_another_scale_does_not_exclude(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        [$scaleid, $contextid] = $this->make_scale();
        [$otherscaleid] = $this->make_scale();
        $questionid = $this->make_question('Assigned elsewhere');

        $DB->insert_record('local_catquiz_items', (object) [
            'componentid' => $questionid,
            'componentname' => 'question',
            'catscaleid' => $otherscaleid,
            'contextid' => $contextid,
            'status' => 0,
        ]);

        $this->assertContains(
            $questionid,
            $this->offered_ids($scaleid, $contextid),
            'Only assignments to this very scale may exclude a question.'
        );
    }

    /**
     * Only the current version of a question is offered, and without a window scan.
     *
     * The current version used to be found by numbering every row of
     * question_versions with ROW_NUMBER() and keeping the first - the whole version
     * history of the site, materialised before a single row was discarded.
     *
     * @return void
     */
    public function test_only_the_current_question_version_is_offered(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        [$scaleid, $contextid] = $this->make_scale();
        $now = time();
        $category = $this->getDataGenerator()->get_plugin_generator('core_question')
            ->create_question_category();

        // Two questions sharing one bank entry: an old version and the current one.
        $entryid = (int) $DB->insert_record('question_bank_entries', (object) [
            'questioncategoryid' => $category->id,
            'idnumber' => null,
            'ownerid' => 2,
        ]);

        $ids = [];
        foreach ([1 => 'Old version', 2 => 'Current version'] as $version => $name) {
            $questionid = (int) $DB->insert_record('question', (object) [
                'name' => $name,
                'questiontext' => 'body',
                'questiontextformat' => FORMAT_HTML,
                'qtype' => 'truefalse',
                'generalfeedback' => '',
                'generalfeedbackformat' => FORMAT_HTML,
                'timecreated' => $now,
                'timemodified' => $now,
                'createdby' => 2,
                'modifiedby' => 2,
            ]);
            $DB->insert_record('question_versions', (object) [
                'questionbankentryid' => $entryid,
                'version' => $version,
                'questionid' => $questionid,
                'status' => 'ready',
            ]);
            $ids[$version] = $questionid;
        }

        $offered = $this->offered_ids($scaleid, $contextid);

        $this->assertContains($ids[2], $offered, 'The current version must be offered.');
        $this->assertNotContains($ids[1], $offered, 'A superseded version must not be offered.');

        [, $from] = catquiz::return_sql_for_addcatscalequestions($scaleid, $contextid);
        $this->assertStringNotContainsStringIgnoringCase(
            'ROW_NUMBER',
            $from,
            'The version must be resolved without numbering the whole version table.'
        );
    }

    /**
     * Without a context id the query still runs, using the default context.
     *
     * This path is easy to lose sight of: with contextid 0 the filter identifies the
     * default context through a JSON flag and needs its own bound parameter. Removing
     * the GROUP_CONCAT filter briefly dropped that binding, and every other test kept
     * passing because they all pass a real context id.
     *
     * @return void
     */
    public function test_query_runs_without_a_context_id(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        [$scaleid] = $this->make_scale();
        $this->make_question('Available everywhere');

        [$select, $from, $where, , $params] = catquiz::return_sql_for_addcatscalequestions($scaleid, 0);

        // Executing is the point: a missing parameter only shows up at runtime.
        $rows = $DB->get_records_sql("SELECT $select FROM $from WHERE $where", $params);

        $this->assertIsArray($rows);
    }
    /**
     * The attempt count is the same whether aggregated globally or per page.
     *
     * Issue #58: the count used to be produced by aggregating every question attempt
     * of the context and joining the result onto all candidates. Loading it for the
     * visible ids instead is only an improvement if it yields the same numbers - a
     * faster dialog showing different figures would be a regression, not a fix.
     *
     * @return void
     */
    public function test_paged_attempt_counts_match_the_global_aggregate(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $now = time();
        $contextid = (int) $DB->insert_record('local_catquiz_catcontext', (object) [
            'name' => 'Attempt count context',
            'description' => '',
            'descriptionformat' => FORMAT_HTML,
            'starttimestamp' => $now - 100,
            'endtimestamp' => $now + 10000,
            'timecreated' => $now,
            'timemodified' => $now,
            'usermodified' => 0,
        ]);

        // Two questions, one of them without any attempt at all.
        $questionids = [];
        foreach (['Counted question', 'Untouched question'] as $name) {
            $questionids[] = (int) $DB->insert_record('question', (object) [
                'name' => $name,
                'questiontext' => 'body',
                'questiontextformat' => FORMAT_HTML,
                'qtype' => 'truefalse',
                'generalfeedback' => '',
                'generalfeedbackformat' => FORMAT_HTML,
                'timecreated' => $now,
                'timemodified' => $now,
                'createdby' => 2,
                'modifiedby' => 2,
            ]);
        }

        $counts = catquiz::get_contextattempts_for_questions($questionids, $contextid);

        // Nothing was answered, so nothing is counted. The renderer turns a missing
        // entry into zero, which is what the LEFT JOIN reported before.
        $this->assertSame([], $counts, 'Without attempts there is nothing to count.');

        // An empty id list must not produce a query over everything.
        $this->assertSame(
            [],
            catquiz::get_contextattempts_for_questions([], $contextid),
            'An empty page asks for nothing.'
        );
    }

    /**
     * The candidate query no longer aggregates the attempt statistics.
     *
     * The gain of issue #58 lies exactly here: the aggregate is driven by the number
     * of attempt steps, so keeping it in the main query cost the same whether ten
     * rows were shown or none.
     *
     * @return void
     */
    public function test_candidate_query_has_no_statistics_aggregate(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [, $from] = catquiz::return_sql_for_addcatscalequestions(1, 1);

        $this->assertStringNotContainsString(
            'contextattempts',
            $from,
            'The candidate query must not aggregate attempts for every candidate.'
        );
        $this->assertStringNotContainsString(
            'question_attempt_steps',
            $from,
            'Nor may it reach the attempt steps at all.'
        );
    }
}
