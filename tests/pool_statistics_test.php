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

namespace local_catquiz;

use advanced_testcase;
use cache;
use local_catquiz\teststrategy\preselect_task\numberofgeneralattempts;

/**
 * Issue #136: the usage statistics of the item pool.
 *
 * Decisions of 2026-10-14:
 * - Pilot number ("attempts"): answers in attempts with the same global scale and the
 *   same CAT context. "Answered" is question_attempts.responsesummary IS NOT NULL.
 * - Penalty for recently seen questions: when the person last saw the question,
 *   anywhere on the site (step 0 of the question attempt).
 * - The pilot balancing uses the same number as the pilot rule.
 * - The manager table shows the same definition.
 *
 * The statistics stay in the pool statement, grouped on an expression: grouped on the
 * bare column, MariaDB 10.11 evaluated them once per item (12 s for 140 items on lti).
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\catquiz::get_pool_statistics
 * @covers     \local_catquiz\catquiz::return_sql_for_catscalequestions
 * @covers     \local_catquiz\catscale::get_testitems
 * @covers     \local_catquiz\teststrategy\preselect_task\numberofgeneralattempts
 */
final class pool_statistics_test extends advanced_testcase {
    /** @var int Running number for question usages and attempts. */
    private int $seq = 0;

    /**
     * Creates a CAT context.
     *
     * @return int
     */
    private function make_context(): int {
        global $DB;
        $now = time();
        return (int) $DB->insert_record('local_catquiz_catcontext', (object) [
            'name' => 'Pool statistics context ' . (++$this->seq),
            'description' => '',
            'descriptionformat' => FORMAT_HTML,
            'starttimestamp' => $now - 100,
            'endtimestamp' => $now + 10000,
            'timecreated' => $now,
            'timemodified' => $now,
            'usermodified' => 0,
        ]);
    }

    /**
     * Creates a scale.
     *
     * @param int $parentid
     * @param int $contextid
     * @return int
     */
    private function make_scale(int $parentid, int $contextid): int {
        global $DB;
        $now = time();
        return (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'parentid' => $parentid,
            'name' => 'Pool statistics scale ' . (++$this->seq),
            'contextid' => $contextid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Creates a question with bank entry and version and assigns it to a scale.
     *
     * @param int $scaleid
     * @param int $contextid
     * @return int question id
     */
    private function make_item(int $scaleid, int $contextid): int {
        global $DB;
        $now = time();
        $category = $this->getDataGenerator()->get_plugin_generator('core_question')->create_question_category();
        $questionid = (int) $DB->insert_record('question', (object) [
            'name' => 'Pool item ' . (++$this->seq),
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
            'idnumber' => 'item' . $this->seq,
            'ownerid' => 2,
        ]);
        $DB->insert_record('question_versions', (object) [
            'questionbankentryid' => $entryid,
            'version' => 1,
            'questionid' => $questionid,
            'status' => 'ready',
        ]);
        $DB->insert_record('local_catquiz_items', (object) [
            'componentid' => $questionid,
            'componentname' => 'question',
            'catscaleid' => $scaleid,
            'contextid' => $contextid,
            'status' => 0,
        ]);
        return $questionid;
    }

    /**
     * Creates a question usage with one question attempt and its steps.
     *
     * @param int $questionid
     * @param int $userid
     * @param bool $answered Whether a response exists (responsesummary)
     * @param int $time Time of the presentation (step 0); the answer follows 10 s later
     * @param string $component
     * @return int question usage id
     */
    private function make_question_attempt(
        int $questionid,
        int $userid,
        bool $answered,
        int $time,
        string $component = 'mod_adaptivequiz'
    ): int {
        global $DB;
        $usageid = (int) $DB->insert_record('question_usages', (object) [
            'contextid' => \context_system::instance()->id,
            'component' => $component,
            'preferredbehaviour' => 'deferredfeedback',
        ]);
        $qaid = (int) $DB->insert_record('question_attempts', (object) [
            'questionusageid' => $usageid,
            'slot' => 1,
            'behaviour' => 'deferredfeedback',
            'questionid' => $questionid,
            'variant' => 1,
            'maxmark' => 1.0,
            'minfraction' => 0.0,
            'maxfraction' => 1.0,
            'flagged' => 0,
            'questionsummary' => '',
            'responsesummary' => $answered ? 'True' : null,
            'timemodified' => $answered ? $time + 10 : $time,
        ]);
        $DB->insert_record('question_attempt_steps', (object) [
            'questionattemptid' => $qaid,
            'sequencenumber' => 0,
            'state' => 'todo',
            'fraction' => null,
            'timecreated' => $time,
            'userid' => $userid,
        ]);
        if ($answered) {
            // Several steps for one answer: they must not multiply the count.
            foreach ([1, 2] as $seq) {
                $DB->insert_record('question_attempt_steps', (object) [
                    'questionattemptid' => $qaid,
                    'sequencenumber' => $seq,
                    'state' => $seq === 1 ? 'complete' : 'gradedright',
                    'fraction' => $seq === 2 ? 1.0 : null,
                    'timecreated' => $time + 5 * $seq,
                    'userid' => $userid,
                ]);
            }
        }
        return $usageid;
    }

    /**
     * Creates a CAT attempt that answered (or saw) one question.
     *
     * @param int $questionid
     * @param int $scaleid Global scale of the attempt
     * @param int $contextid
     * @param int $userid
     * @param bool $answered
     * @param int $time
     * @return void
     */
    private function make_cat_attempt(
        int $questionid,
        int $scaleid,
        int $contextid,
        int $userid,
        bool $answered,
        int $time
    ): void {
        global $DB;
        $usageid = $this->make_question_attempt($questionid, $userid, $answered, $time);
        $aqaid = (int) $DB->insert_record('adaptivequiz_attempt', (object) [
            'instance' => 1,
            'userid' => $userid,
            'uniqueid' => $usageid,
            'attemptstate' => 'complete',
            'attemptstopcriteria' => '',
            'questionsattempted' => 1,
            'standarderror' => 0.5,
            'measure' => 0.0,
            'timecreated' => $time,
            'timemodified' => $time,
        ]);
        $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => $userid,
            'scaleid' => $scaleid,
            'contextid' => $contextid,
            'courseid' => 2,
            'attemptid' => $aqaid,
            'component' => 'mod_adaptivequiz',
            'instanceid' => 1,
            'status' => 1,
            'timecreated' => $time,
            'timemodified' => $time,
        ]);
    }

    /**
     * Builds the fixture: a global scale with a subscale, one item, attempts that must
     * and must not count.
     *
     * @return array [globalscaleid, subscaleid, contextid, questionid, lastanswertime]
     */
    private function fixture(): array {
        $contextid = $this->make_context();
        $othercontextid = $this->make_context();
        $globalid = $this->make_scale(0, $contextid);
        $subid = $this->make_scale($globalid, $contextid);
        $otherglobalid = $this->make_scale(0, $contextid);
        $questionid = $this->make_item($subid, $contextid);
        $t = 1700000000;

        // Counted: answered, same context, same global scale.
        $this->make_cat_attempt($questionid, $globalid, $contextid, 11, true, $t);
        $this->make_cat_attempt($questionid, $globalid, $contextid, 12, true, $t + 500);
        // Not counted: seen but not answered.
        $this->make_cat_attempt($questionid, $globalid, $contextid, 13, false, $t + 900);
        // Not counted: other CAT context.
        $this->make_cat_attempt($questionid, $globalid, $othercontextid, 14, true, $t + 1000);
        // Not counted: other global scale.
        $this->make_cat_attempt($questionid, $otherglobalid, $contextid, 15, true, $t + 1100);
        // Not counted: an ordinary quiz.
        $this->make_question_attempt($questionid, 16, true, $t + 1200, 'mod_quiz');

        return [$globalid, $subid, $contextid, $questionid, $t + 510];
    }

    /**
     * Answers in the same context and global scale count, nothing else does.
     *
     * @return void
     */
    public function test_counts_answers_in_context_and_global_scale(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$globalid, $subid, $contextid, $questionid, $lastanswer] = $this->fixture();

        $stats = catquiz::get_pool_statistics([$globalid, $subid], $contextid);

        $this->assertSame(2, $stats[$questionid]->attempts);
        $this->assertSame($lastanswer, $stats[$questionid]->lastattempt);
        // The subscale alone resolves to the same global scale.
        $this->assertSame(2, catquiz::get_pool_statistics([$subid], $contextid)[$questionid]->attempts);
    }

    /**
     * The manager table shows the same numbers.
     *
     * @return void
     */
    public function test_manager_table_uses_the_same_definition(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$globalid, $subid, $contextid, $questionid, $lastanswer] = $this->fixture();

        [$select, $from, $where, , $params] = catquiz::return_sql_for_catscalequestions([$globalid, $subid], $contextid);
        // One row per item, keyed by the item (issue #104); the question is the column id.
        $rows = array_column(array_values($DB->get_records_sql("SELECT $select FROM $from WHERE $where", $params)), null, 'id');

        $this->assertEquals(2, $rows[$questionid]->attempts);
        $this->assertEquals($lastanswer, $rows[$questionid]->astatlastattempttime);
    }

    /**
     * The last presentation counts, anywhere on the site, answered or not.
     *
     * @return void
     */
    public function test_exposure_is_site_wide_and_per_person(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$globalid, $subid, $contextid, $questionid] = $this->fixture();
        $t = 1700000000;

        $lastseen = function (int $userid) use ($DB, $globalid, $subid, $contextid, $questionid) {
            [$select, $from, $where, , $params] = catquiz::return_sql_for_catscalequestions(
                [$globalid, $subid],
                $contextid,
                [],
                $userid,
                null,
                null,
                true
            );
            $rows = $DB->get_records_sql("SELECT $select FROM $from WHERE $where", $params);
            $value = $rows[$questionid]->userlastattempttime;
            return $value === null ? null : (int) $value;
        };

        // User 13 only saw the question (no answer): it is still an exposure.
        $this->assertSame($t + 900, $lastseen(13));
        // User 16 saw it in an ordinary quiz: site-wide, so it counts.
        $this->assertSame($t + 1200, $lastseen(16));
        // User 14 saw it in another CAT context: counts as well.
        $this->assertSame($t + 1000, $lastseen(14));
        // Step 0 is the presentation; the later steps of the answer do not move it.
        $this->assertSame($t, $lastseen(11));
        $this->assertNull($lastseen(99));
    }

    /**
     * The runtime pool carries the statistics.
     *
     * @return void
     */
    public function test_runtime_pool_carries_the_statistics(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        [$globalid, $subid, $contextid, $questionid] = $this->fixture();
        $t = 1700000000;
        $this->make_question_attempt($questionid, (int) $user->id, false, $t + 3000, 'mod_quiz');

        cache::make('local_catquiz', 'adaptivequizattempt')->purge();
        $pool = (new catscale($globalid))->get_testitems($contextid, true);

        $this->assertArrayHasKey($questionid, $pool);
        $this->assertEquals(2, $pool[$questionid]->attempts);
        $this->assertEquals($t + 3000, $pool[$questionid]->userlastattempttime);

        // Same values as the full statement of the manager table.
        [$select, $from, $where, , $params] = catquiz::return_sql_for_catscalequestions(
            [$globalid, $subid],
            $contextid,
            [],
            (int) $user->id
        );
        $full = array_column(array_values($DB->get_records_sql("SELECT $select FROM $from WHERE $where", $params)), null, 'id');
        $this->assertEquals($full[$questionid]->attempts, $pool[$questionid]->attempts);
        $this->assertEquals($full[$questionid]->userlastattempttime, $pool[$questionid]->userlastattempttime);
    }

    /**
     * Both statements group the statistics on an expression and need no steps for the
     * pilot number.
     *
     * Guards the MariaDB fix: grouped on the bare column, MariaDB 10.11 chose a lateral
     * derived table and evaluated the statistics once per item. That plan choice cannot
     * be reproduced on PostgreSQL; it was checked on MariaDB 10.11.14 with a synthetic
     * data set (lateral with the bare column, none with the expression).
     *
     * @return void
     */
    public function test_statistics_group_on_an_expression(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $contextid = $this->make_context();
        $globalid = $this->make_scale(0, $contextid);

        foreach ([true, false] as $lean) {
            [, $from] = catquiz::return_sql_for_catscalequestions([$globalid], $contextid, [], 2, null, null, $lean);
            $this->assertStringContainsString('GROUP BY lca.scaleid, qa.questionid + 0', $from);
            $this->assertStringContainsString('GROUP BY qas.userid, qa.questionid + 0', $from);

            $astat = substr($from, strpos($from, 'SELECT lca.scaleid'));
            $astat = substr($astat, 0, strpos($astat, ') astat'));
            $this->assertStringNotContainsString('question_attempt_steps', $astat, 'The pilot number needs no steps.');
            $this->assertStringContainsString('responsesummary IS NOT NULL', $astat);
        }
    }

    /**
     * The pilot balancing takes the pilot number of the pool, not a site-wide count.
     *
     * @return void
     */
    public function test_pilot_balancing_uses_the_pool_number(): void {
        $this->resetAfterTest();
        $context = [
            'questions' => [
                101 => (object) ['id' => 101, 'attempts' => 4],
                102 => (object) ['id' => 102, 'attempts' => 0],
                103 => (object) ['id' => 103, 'attempts' => 9],
            ],
        ];
        $result = (new numberofgeneralattempts())->run($context);

        $this->assertTrue($result->isok());
        $this->assertSame(4, $context['questions'][101]->numberofgeneralattempts);
        $this->assertSame(0, $context['questions'][102]->numberofgeneralattempts);
        $this->assertSame(9, $context['generalnumberofattempts_max']);
    }

    /**
     * The pool cache keeps CAT context, scope and subscale selection apart (issue #136, DoD
     * "Cache-Schlüssel").
     *
     * One session can hold the pools of several tests. A key that left out one of these parts
     * would hand one test the pool of another: other items, or the statistics of another CAT
     * context. Every pool is checked built (cold) and from the cache (warm), each warm read after
     * all other pools were cached.
     *
     * @return void
     */
    public function test_pool_cache_keys_keep_context_scope_and_subscales_apart(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $contextid = $this->make_context();
        $othercontextid = $this->make_context();
        $globalid = $this->make_scale(0, $contextid);
        $sub1 = $this->make_scale($globalid, $contextid);
        $sub2 = $this->make_scale($globalid, $contextid);
        $onglobal = $this->make_item($globalid, $contextid);
        $insub1 = $this->make_item($sub1, $contextid);
        $insub2 = $this->make_item($sub2, $contextid);
        // The same questions are also items of the other context: a context's pool holds only its own items
        // (issue #157), and this case is about the statistics of the context, not about other items.
        foreach ([[$onglobal, $globalid], [$insub1, $sub1], [$insub2, $sub2]] as [$questionid, $scaleid]) {
            $DB->insert_record('local_catquiz_items', (object) [
                'componentid' => $questionid,
                'componentname' => 'question',
                'catscaleid' => $scaleid,
                'contextid' => $othercontextid,
                'status' => 0,
            ]);
        }
        // Two answers to the item of subscale 1 in the first context, none in the other.
        $this->make_cat_attempt($insub1, $globalid, $contextid, 11, true, 1700000000);
        $this->make_cat_attempt($insub1, $globalid, $contextid, 12, true, 1700000500);

        // Name => [context, with subscales, selected subscales, items, pilot number of $insub1].
        $cases = [
            'global scale only' => [$contextid, false, [], [$onglobal], null],
            'with all subscales' => [$contextid, true, [], [$onglobal, $insub1, $insub2], 2],
            'subscale 1 selected' => [$contextid, true, [$sub1], [$onglobal, $insub1], 2],
            'subscale 2 selected' => [$contextid, true, [$sub2], [$onglobal, $insub2], null],
            'other context' => [$othercontextid, true, [], [$onglobal, $insub1, $insub2], 0],
        ];
        cache::make('local_catquiz', 'adaptivequizattempt')->purge();
        foreach (['built' => $cases, 'from the cache' => array_reverse($cases, true)] as $how => $run) {
            foreach ($run as $name => [$context, $withsubscales, $selected, $items, $pilot]) {
                $pool = (new catscale($globalid))->get_testitems($context, $withsubscales, null, $selected);
                $ids = array_map('intval', array_keys($pool));
                sort($ids);
                sort($items);
                $this->assertSame($items, $ids, "$name, $how: items");
                if ($pilot !== null) {
                    $this->assertEquals($pilot, $pool[$insub1]->attempts, "$name, $how: pilot number");
                }
            }
        }
    }
}
