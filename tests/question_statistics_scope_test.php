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
 * Issue #21: question statistics are restricted and count attempts, not steps.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;

/**
 * Verifies the statistics subqueries of the question list.
 *
 * Two defects are guarded here. The aggregation used to run without any WHERE, so it
 * summed up every CAT attempt on the site before a single row was discarded by the
 * outer join - an outer LIMIT did nothing to reduce that work. And COUNT(qa.id) was
 * counting rows produced by the join to question_attempt_steps, so a question
 * answered once but with several interaction steps was reported as several attempts.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\catquiz::return_sql_for_catscalequestions
 */
final class question_statistics_scope_test extends advanced_testcase {
    /**
     * Creates a context and a scale.
     *
     * @return array [int $scaleid, int $contextid]
     */
    private function make_scale(): array {
        global $DB;

        $now = time();
        $contextid = (int) $DB->insert_record('local_catquiz_catcontext', (object) [
            'name' => 'Issue 21 context',
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
            'name' => 'Issue 21 scale',
            'contextid' => $contextid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        return [$scaleid, $contextid];
    }

    /**
     * The aggregation is restricted inside the subquery, not only in the outer join.
     *
     * @return void
     */
    public function test_statistics_subquery_is_restricted(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$scaleid, $contextid] = $this->make_scale();

        [, $from] = catquiz::return_sql_for_catscalequestions([$scaleid], $contextid, []);

        // Everything before the outer join belongs to the aggregation itself.
        $subquery = substr($from, 0, strpos($from, ') astat'));

        $this->assertStringContainsString(
            'statcontextid',
            $subquery,
            'The aggregation must restrict the context itself.'
        );
        $this->assertStringContainsString(
            'lca.scaleid',
            $subquery,
            'The aggregation must restrict the scales itself.'
        );
    }

    /**
     * Attempt counts are distinct, so steps cannot inflate them.
     *
     * @return void
     */
    public function test_attempt_count_is_distinct(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$scaleid, $contextid] = $this->make_scale();

        [, $from] = catquiz::return_sql_for_catscalequestions([$scaleid], $contextid, []);

        $this->assertStringNotContainsString(
            'COUNT(qa.id)',
            $from,
            'A plain COUNT over the steps join counts steps, not question attempts.'
        );
        $this->assertStringContainsString('COUNT(DISTINCT qa.id)', $from);
    }

    /**
     * The add-items query counts attempts too, not interaction steps.
     *
     * It reaches the same data through get_sql_for_stat_base_request(), which joins
     * question_attempt_steps as well - so a plain COUNT(*) had exactly the same
     * effect there, in a second place nobody was looking at.
     *
     * @return void
     */
    public function test_add_items_query_counts_attempts_not_steps(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$scaleid, $contextid] = $this->make_scale();

        // Issue #58 moved this aggregate out of the candidate query: computing it for
        // every candidate cost the same whether ten rows were shown or none. It is now
        // fetched for the visible page only, so that is where the counting rule has
        // to be checked.
        [, $from] = catquiz::return_sql_for_addcatscalequestions($scaleid, $contextid);

        $this->assertStringNotContainsString(
            'contextattempts',
            $from,
            'The candidate query must not aggregate attempts for every candidate.'
        );

        // The rule itself is unchanged and still has to hold: question attempts, not
        // interaction steps.
        $reflection = new \ReflectionMethod(catquiz::class, 'get_contextattempts_for_questions');
        $source = file_get_contents($reflection->getFileName());
        $start = strpos($source, 'function get_contextattempts_for_questions');
        $body = substr($source, $start, strpos($source, "\n    }\n", $start) - $start);

        $this->assertStringContainsString(
            'COUNT(DISTINCT qa.id)',
            $body,
            'COUNT(*) over the steps join would report interaction steps as attempts.'
        );
        $this->assertStringNotContainsString('COUNT(*)', $body);
    }

    /**
     * A question answered once counts once, however many steps it produced.
     *
     * This is the functional counterpart to the SQL assertion above: the fixture
     * writes one question attempt with three steps, which the old query reported as
     * three attempts.
     *
     * @return void
     */
    public function test_multiple_steps_count_as_one_attempt(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        [$scaleid, $contextid] = $this->make_scale();
        $now = time();

        $usageid = (int) $DB->insert_record('question_usages', (object) [
            'contextid' => \context_system::instance()->id,
            'component' => 'mod_adaptivequiz',
            'preferredbehaviour' => 'deferredfeedback',
        ]);
        $aqaid = (int) $DB->insert_record('adaptivequiz_attempt', (object) [
            'instance' => 1,
            'userid' => 5,
            'uniqueid' => $usageid,
            'attemptstate' => 'complete',
            'attemptstopcriteria' => '',
            'questionsattempted' => 1,
            'standarderror' => 0.5,
            'measure' => 0.0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => 5,
            'scaleid' => $scaleid,
            'contextid' => $contextid,
            'courseid' => 2,
            'attemptid' => $aqaid,
            'component' => 'mod_adaptivequiz',
            'instanceid' => 1,
            'status' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $questionid = (int) $DB->insert_record('question', (object) [
            'name' => 'Counted once',
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
            'timemodified' => $now,
        ]);

        // One answered question, three steps carrying a fraction.
        foreach ([1, 2, 3] as $seq) {
            $DB->insert_record('question_attempt_steps', (object) [
                'questionattemptid' => $qaid,
                'sequencenumber' => $seq,
                'state' => 'gradedright',
                'fraction' => 1.0,
                'timecreated' => $now + $seq,
                'userid' => 5,
            ]);
        }

        $sql = "SELECT COUNT(DISTINCT qa.id) AS attempts, COUNT(qa.id) AS steps
                  FROM {local_catquiz_attempts} lca
                  JOIN {adaptivequiz_attempt} aqa ON lca.attemptid = aqa.id
                  JOIN {question_attempts} qa ON qa.questionusageid = aqa.uniqueid
                  JOIN {question_attempt_steps} qas
                    ON qas.questionattemptid = qa.id AND qas.fraction IS NOT NULL
                 WHERE lca.contextid = :contextid AND lca.scaleid = :scaleid";
        $row = $DB->get_record_sql($sql, ['contextid' => $contextid, 'scaleid' => $scaleid]);

        $this->assertEquals(1, $row->attempts, 'One answered question is one attempt.');
        // Proves the fixture actually exercises the defect: without DISTINCT the same
        // question attempt is reported three times.
        $this->assertEquals(3, $row->steps);
    }
}
