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
 * Issue #19: the question detail view loads exactly one question.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;

/**
 * Verifies that the detail view restricts the query instead of filtering in PHP.
 *
 * The regression this guards against: the detail view used the shared list builder
 * and then picked the wanted row out of the result array. On a large, image heavy
 * pool that meant loading every question of the scale - including their texts - to
 * display one of them, which could hit the memory limit.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\catquiz::return_sql_for_catscalequestions
 */
final class question_detail_query_test extends advanced_testcase {
    /**
     * Creates a scale and returns its id together with the context id.
     *
     * @return array [int $scaleid, int $contextid]
     */
    private function make_scale(): array {
        global $DB;

        $now = time();
        $contextid = (int) $DB->insert_record('local_catquiz_catcontext', (object) [
            'name' => 'Issue 19 context',
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
            'name' => 'Issue 19 scale',
            'contextid' => $contextid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        return [$scaleid, $contextid];
    }

    /**
     * The query restricts to the requested question at the SQL level.
     *
     * @return void
     */
    public function test_query_restricts_to_a_single_question(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$scaleid, $contextid] = $this->make_scale();

        [, $from, , , $params] = catquiz::return_sql_for_catscalequestions(
            [$scaleid],
            $contextid,
            [],
            0,
            null,
            4242
        );

        $this->assertStringContainsString(
            'q.id = :detailquestionid',
            $from,
            'The restriction must sit in the query, not in PHP.'
        );
        $this->assertArrayHasKey('detailquestionid', $params);
        $this->assertEquals(4242, $params['detailquestionid']);
    }

    /**
     * Without a question id the query is unrestricted, as the list needs it.
     *
     * @return void
     */
    public function test_list_query_is_not_restricted(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$scaleid, $contextid] = $this->make_scale();

        [, $from, , , $params] = catquiz::return_sql_for_catscalequestions([$scaleid], $contextid, []);

        $this->assertStringNotContainsString('detailquestionid', $from);
        $this->assertArrayNotHasKey('detailquestionid', $params);
    }

    /**
     * The question id must not be bound to the per-user statistics join.
     *
     * The detail view used to pass the question id in the builder's $userid slot.
     * That slot is bound to :userid in a LEFT JOIN over per-user attempt statistics,
     * so the view compared user ids against a question id - silently wrong data
     * whenever a user happened to have that id.
     *
     * @return void
     */
    public function test_question_id_does_not_leak_into_the_user_statistics_join(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$scaleid, $contextid] = $this->make_scale();

        [, $from, , , $params] = catquiz::return_sql_for_catscalequestions(
            [$scaleid],
            $contextid,
            [],
            0,
            null,
            4242
        );

        $this->assertArrayNotHasKey('userid', $params, 'A detail lookup must not bind :userid.');
        $this->assertStringNotContainsString(
            'ustat.userid = :userid',
            $from,
            'Without a user the per-user statistics join must not be built at all.'
        );
    }

    /**
     * A user id and a question id can be combined without colliding.
     *
     * @return void
     */
    public function test_user_and_question_restrictions_use_separate_parameters(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$scaleid, $contextid] = $this->make_scale();

        [, , , , $params] = catquiz::return_sql_for_catscalequestions(
            [$scaleid],
            $contextid,
            [],
            77,
            null,
            4242
        );

        $this->assertEquals(77, $params['userid']);
        $this->assertEquals(4242, $params['detailquestionid']);
    }
}
