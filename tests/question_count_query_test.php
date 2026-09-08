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
 * Issue #21: counting the question list uses a light data source.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;

/**
 * Verifies the light count query of the question list.
 *
 * Counting the list meant counting the rows of the full query - the one carrying the
 * attempt statistics. Those aggregates are computed only to be discarded by COUNT(),
 * which makes the count as expensive as the list.
 *
 * The row set is defined by the joins up to the question bank; the statistics are
 * LEFT JOINs and cannot add or remove a row. The tests here pin exactly that: both
 * counts must agree, including for items in piloting, which have no parameter row and
 * no statistics at all.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\catquiz::return_sql_for_catscalequestions_count
 */
final class question_count_query_test extends advanced_testcase {
    /**
     * Creates a context and a scale.
     *
     * @return array [int $scaleid, int $contextid]
     */
    private function make_scale(): array {
        global $DB;

        $now = time();
        $contextid = (int) $DB->insert_record('local_catquiz_catcontext', (object) [
            'name' => 'Count context',
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
            'name' => 'Count scale',
            'contextid' => $contextid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        return [$scaleid, $contextid];
    }

    /**
     * Registers a question in the scale, optionally with an active parameter.
     *
     * @param int $scaleid
     * @param int $contextid
     * @param string $name
     * @param bool $withparam
     * @return void
     */
    private function add_question(int $scaleid, int $contextid, string $name, bool $withparam): void {
        global $DB;

        $now = time();
        $category = $this->getDataGenerator()->get_plugin_generator('core_question')
            ->create_question_category();

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
        $itemid = (int) $DB->insert_record('local_catquiz_items', (object) [
            'componentid' => $questionid,
            'componentname' => 'question',
            'catscaleid' => $scaleid,
            'contextid' => $contextid,
            'activeparamid' => 0,
            'status' => 0,
        ]);

        if (!$withparam) {
            return;
        }

        $paramid = (int) $DB->insert_record('local_catquiz_itemparams', (object) [
            'itemid' => $itemid,
            'componentname' => 'question',
            'contextid' => $contextid,
            'model' => 'rasch',
            'difficulty' => 0.5,
            'usable' => 1,
            'status' => 4,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $DB->set_field('local_catquiz_items', 'activeparamid', $paramid, ['id' => $itemid]);
    }

    /**
     * Counts the list through the full query, as the table does by default.
     *
     * @param int $scaleid
     * @param int $contextid
     * @return int
     */
    private function heavy_count(int $scaleid, int $contextid): int {
        global $DB;

        [, $from, $where, , $params] = catquiz::return_sql_for_catscalequestions([$scaleid], $contextid, []);

        return (int) $DB->count_records_sql("SELECT COUNT(*) FROM $from WHERE $where", $params);
    }

    /**
     * Counts the list through the light query.
     *
     * @param int $scaleid
     * @param int $contextid
     * @return int
     */
    private function light_count(int $scaleid, int $contextid): int {
        global $DB;

        [$from, $where, $params] = catquiz::return_sql_for_catscalequestions_count([$scaleid], $contextid);

        return (int) $DB->count_records_sql("SELECT COUNT(*) FROM $from WHERE $where", $params);
    }

    /**
     * Both counts agree, including for items in piloting.
     *
     * @return void
     */
    public function test_light_count_matches_the_full_query(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$scaleid, $contextid] = $this->make_scale();

        $this->assertEquals(0, $this->light_count($scaleid, $contextid), 'An empty scale counts zero.');

        $this->add_question($scaleid, $contextid, 'With parameter one', true);
        $this->add_question($scaleid, $contextid, 'With parameter two', true);
        // An item in piloting: no active parameter, therefore no statistics either.
        $this->add_question($scaleid, $contextid, 'In piloting', false);

        $heavy = $this->heavy_count($scaleid, $contextid);

        $this->assertEquals(3, $heavy, 'All three questions belong to the list.');
        $this->assertEquals(
            $heavy,
            $this->light_count($scaleid, $contextid),
            'The light count must report exactly what the full query reports.'
        );
    }

    /**
     * The light query really is lighter - it does not touch the attempt tables.
     *
     * Without this the count could quietly keep the aggregates and the test above
     * would still pass, since it only compares the numbers.
     *
     * @return void
     */
    public function test_light_count_omits_the_statistics_joins(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$scaleid, $contextid] = $this->make_scale();
        [$from] = catquiz::return_sql_for_catscalequestions_count([$scaleid], $contextid);

        $this->assertStringNotContainsString('local_catquiz_attempts', $from);
        $this->assertStringNotContainsString('question_attempt_steps', $from);
        $this->assertStringNotContainsString('adaptivequiz_attempt', $from);
        // The joins that define the row set must of course still be there.
        $this->assertStringContainsString('local_catquiz_items', $from);
        $this->assertStringContainsString('question_versions', $from);
    }

    /**
     * The table uses the light count, and only when it may.
     *
     * The guard matters as much as the optimisation: the library appends its own
     * filter and search after the table is built, and the free text search can match
     * on columns that exist only in the aggregates. A count fixed too early would
     * report a total that does not match the list, and pagination would offer empty
     * pages.
     *
     * @return void
     */
    public function test_light_count_is_only_used_when_it_may_be(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$scaleid, $contextid] = $this->make_scale();
        $table = new \local_catquiz\table\catscalequestions_table('countguard', $scaleid, $contextid);

        $reflection = new \ReflectionMethod($table, 'can_use_light_count');
        $reflection->setAccessible(true);

        // Without a context the table cannot build the light query at all.
        $this->assertFalse($reflection->invoke($table), 'No context, no light count.');

        $table->set_count_context([$scaleid], $contextid);
        $this->assertTrue($reflection->invoke($table), 'With a context it may be used.');

        // An active search narrows the list in a way the light query does not know.
        $table->searchtext = 'anything';
        $this->assertFalse($reflection->invoke($table), 'An active search must disable it.');
    }

    /**
     * A scale that was not asked for is not counted.
     *
     * @return void
     */
    public function test_light_count_is_restricted_to_the_scale(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$scaleid, $contextid] = $this->make_scale();
        [$otherscaleid] = $this->make_scale();

        $this->add_question($scaleid, $contextid, 'Belongs here', true);
        $this->add_question($otherscaleid, $contextid, 'Belongs elsewhere', true);

        $this->assertEquals(1, $this->light_count($scaleid, $contextid));
    }
}
