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

namespace local_catquiz\teststrategy;

use advanced_testcase;
use local_catquiz\catscale;
use local_catquiz\local\attempt_cache;
use local_catquiz\local\status;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once(__DIR__ . '/classic_hierarchy_trait.php');

/**
 * The configuration of issue #64, replayed: CLASSIC, 24 items, root - domain - two subscales.
 *
 * Reported: the attempt ended after the first answered question with "minimum not reached", while 23
 * valid items were left, the maximum was 250 and the per-scale maximum 8. Played here through the
 * real selection and the real estimator, without the worker and without store_debug_info.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\teststrategy\strategy
 * @covers \local_catquiz\teststrategy\preselect_task\updatepersonability
 */
final class issue64_classic_hierarchy_test extends advanced_testcase {
    use classic_hierarchy_trait;

    /** @var array Abilities of the attempt right after the second question was selected, by scale id. */
    private array $abilitiesafterfirst = [];

    /**
     * The pool of the report, without store_debug_info.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('store_debug_info', 0, 'local_catquiz');
        $this->set_up_hierarchy();
    }

    /**
     * Unsets the switches of the testing classes again.
     */
    public function tearDown(): void {
        $this->unset_testing_switches();
        parent::tearDown();
    }

    /**
     * Plays the attempt to its end and keeps the abilities right after the first answer.
     *
     * @param bool $skipfeedback
     * @return array [question ids, status of the end, adaptivequiz attempt id]
     */
    private function play_and_watch(bool $skipfeedback = true): array {
        return $this->play(60, $skipfeedback, null, null, function (int $i, int $attemptid) {
            if ($i === 1) {
                $progress = progress::load($attemptid, 'mod_adaptivequiz', catscale::get_context_id($this->rootid));
                $this->abilitiesafterfirst = $progress->get_abilities();
            }
        });
    }

    /**
     * The fixture is the reported hierarchy: 24 calculated items, two leaves below one domain below the root.
     */
    public function test_fixture_is_the_reported_pool(): void {
        global $DB;
        $domain = (int) $DB->get_field('local_catquiz_catscales', 'id', ['name' => 'SimA', 'parentid' => $this->rootid]);
        $leaves = $DB->get_records_menu('local_catquiz_catscales', ['parentid' => $domain], 'name', 'id, name');
        $this->assertSame(['SimA01', 'SimA02'], array_values($leaves));
        [$insql, $params] = $DB->get_in_or_equal(array_keys($leaves));
        $this->assertSame(24, $DB->count_records_select('local_catquiz_items', "catscaleid $insql", $params));
        $this->assertSame(24, $DB->count_records('local_catquiz_items'));
        // Every item calculated (status 4), with its model.
        $this->assertSame(24, $DB->count_records_select('local_catquiz_itemparams', "status = 4 AND model = 'raschbirnbaum'"));
    }

    /**
     * Q1 -> Q2: the second question is another one, the test runs at least its minimum, and after the
     * first answer every scale of the path has a finite ability.
     *
     * CLASSIC is a fixed form (issue #135): neither the per-scale maximum of 8 nor the target error ends it,
     * so all 24 items are played and the test ends because none is left - after the minimum.
     */
    public function test_second_question_follows_the_first(): void {
        global $DB;
        $this->settings();

        [$ids, $status] = $this->play_and_watch();

        $this->assertGreaterThanOrEqual(2, count($ids), 'Ended after the first question with ' . $status);
        $this->assertNotEquals($ids[0], $ids[1], 'The second question equals the first.');
        $this->assertSame(count($ids), count(array_unique($ids)), 'A question was asked twice.');
        $this->assertGreaterThanOrEqual(3, count($ids), 'Ended before the minimum with ' . $status);

        $leaf = (int) $DB->get_field('local_catquiz_items', 'catscaleid', ['componentid' => $ids[0]]);
        $domain = (int) $DB->get_field('local_catquiz_catscales', 'parentid', ['id' => $leaf]);
        foreach (['root' => $this->rootid, 'domain' => $domain, 'leaf' => $leaf] as $level => $scaleid) {
            $this->assertArrayHasKey($scaleid, $this->abilitiesafterfirst, "No ability for the $level after Q1.");
            $this->assertTrue(is_finite((float) $this->abilitiesafterfirst[$scaleid]), "The $level ability is not finite.");
        }

        // The fixed form: every item, then no item left.
        $this->assertCount(24, $ids);
        $this->assertSame(status::ERROR_NO_REMAINING_QUESTIONS, $status);
    }

    /**
     * The end of the selection is kept with the attempt (issue #64, diagnostics): the status, the candidate
     * count of every stage, minimum, maximum and counters - in the session under the attempt's own key and,
     * once the test has ended, in local_catquiz_attempts, readable after the session is gone. Without
     * store_debug_info.
     */
    public function test_the_end_of_the_selection_is_stored_with_the_attempt(): void {
        global $DB;
        $this->settings();

        [$ids, $status, $attemptid] = $this->play_and_watch(false);

        $this->assertSame(status::ERROR_NO_REMAINING_QUESTIONS, $status);
        $insession = attempt_cache::get('catquizstagecounts', $attemptid);
        $this->assertIsArray($insession);
        $this->assertSame(0, $insession['counts']['removeplayedquestions'] ?? null);

        // Gone from the session, still in the database.
        \cache_helper::purge_by_definition('local_catquiz', 'adaptivequizattempt');
        $json = json_decode((string) $DB->get_field('local_catquiz_attempts', 'json', ['attemptid' => $attemptid]), true);
        $diagnosis = $json['selectiondiagnosis'] ?? null;
        $this->assertIsArray($diagnosis, 'No diagnosis stored with the attempt.');
        $this->assertSame($status, $diagnosis['status']);
        $this->assertSame(250, $diagnosis['maximumquestions']);
        $this->assertSame(3, $diagnosis['minimumquestions']);
        $this->assertSame(8, $diagnosis['maxperscale']);
        $this->assertSame(count($ids), $diagnosis['played']);
        $this->assertSame(0, $diagnosis['counts']['removeplayedquestions']);
        $this->assertArrayHasKey($this->rootid, $diagnosis['abilities']);
        $this->assertTrue(is_finite((float) $diagnosis['abilities'][$this->rootid]));
    }

    /**
     * A test that runs out before its minimum stores why (issue #64): the stage that emptied the pool can be
     * read from the attempt.
     */
    public function test_running_out_before_the_minimum_names_the_stage(): void {
        global $DB;
        $this->settings();
        // Only the first question stays active once it is answered.
        [$ids, $status, $attemptid] = $this->play(5, true, function (int $i, array $ids) use ($DB) {
            if ($i === 0) {
                $DB->set_field_select(
                    'local_catquiz_items',
                    'status',
                    LOCAL_CATQUIZ_TESTITEM_STATUS_INACTIVE,
                    "componentname = 'question' AND componentid <> ?",
                    [$ids[0]]
                );
                \cache_helper::purge_by_event('changesintestitems');
            }
        });

        $this->assertCount(1, $ids);
        $this->assertSame(status::ERROR_NO_REMAINING_QUESTIONS_BEFORE_MINIMUM, $status);
        $json = json_decode((string) $DB->get_field('local_catquiz_attempts', 'json', ['attemptid' => $attemptid]), true);
        $diagnosis = $json['selectiondiagnosis'] ?? null;
        $this->assertIsArray($diagnosis);
        $this->assertSame(status::ERROR_NO_REMAINING_QUESTIONS_BEFORE_MINIMUM, $diagnosis['status']);
        $this->assertSame(0, $diagnosis['counts']['removeplayedquestions']);
        $this->assertSame(1, $diagnosis['answered']);
    }
}
