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

namespace local_catquiz\local\attempt;

use advanced_testcase;
use context_system;
use question_engine;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/lib.php');

/**
 * Every administration of an attempt stays visible; a repeated one is marked, not dropped (#125).
 *
 * In a real attempt question 4751 sat in slots 7 and 9 of the same usage. The question engine kept
 * both; the progress, keyed by question id, showed one. These tests build such a usage and check
 * the three views: the audit history shows both slots, the second marked as a technical
 * duplicate; the measurement counts the question once; nothing here touches result validity.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\local\attempt\administration_history
 */
final class administration_history_test extends advanced_testcase {
    /**
     * Builds an attempt whose usage holds the given questions, one slot each, all answered.
     *
     * @param array $questionkeys Which question each slot holds, e.g. ['a', 'b', 'a'].
     * @return array{0: int, 1: array} The adaptivequiz attempt id and the question ids by key.
     */
    private function attempt_with_slots(array $questionkeys): array {
        global $DB;

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();
        $questions = [];
        foreach (array_unique($questionkeys) as $key) {
            $questions[$key] = $generator->create_question('truefalse', null, ['category' => $category->id]);
        }

        $quba = question_engine::make_questions_usage_by_activity('mod_adaptivequiz', context_system::instance());
        $quba->set_preferred_behaviour('deferredfeedback');
        foreach ($questionkeys as $key) {
            $quba->add_question(\question_bank::load_question($questions[$key]->id));
        }
        $quba->start_all_questions();
        foreach ($quba->get_slots() as $slot) {
            $quba->process_action($slot, ['answer' => 1]);
        }
        $quba->finish_all_questions();
        question_engine::save_questions_usage_by_activity($quba);

        $attemptid = (int) $DB->insert_record('adaptivequiz_attempt', (object) [
            'instance' => 55001, 'userid' => 2, 'uniqueid' => $quba->get_id(), 'attemptstate' => 'complete',
            'attemptstopcriteria' => '', 'questionsattempted' => count($questionkeys), 'difficultysum' => 0,
            'standarderror' => 1, 'measure' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);

        return [$attemptid, array_map(fn($q) => (int) $q->id, $questions)];
    }

    /**
     * The same question in two slots: both are in the history, the second marked.
     */
    public function test_a_repeated_question_is_kept_and_marked(): void {
        $this->resetAfterTest();
        [$attemptid, $ids] = $this->attempt_with_slots(['a', 'b', 'a']);

        $history = administration_history::for_attempt($attemptid);

        $this->assertCount(3, $history, 'Every administered slot must be in the history.');
        $this->assertSame([1, 2, 3], array_keys($history));
        $this->assertSame($ids['a'], $history[1]->questionid);
        $this->assertSame($ids['a'], $history[3]->questionid);
        $this->assertFalse($history[1]->technicalduplicate);
        $this->assertTrue($history[3]->technicalduplicate, 'The second administration must be marked.');
        $this->assertSame(1, $history[3]->duplicateofslot);
        $this->assertNotSame($history[1]->questionattemptid, $history[3]->questionattemptid);
        $this->assertNotNull($history[3]->state);
    }

    /**
     * For the measurement, a repeated question counts once - its first administration.
     */
    public function test_the_measurement_counts_each_question_once(): void {
        $this->resetAfterTest();
        [$attemptid] = $this->attempt_with_slots(['a', 'b', 'a']);

        $history = administration_history::for_attempt($attemptid);
        $counted = administration_history::counted_administrations($history);

        $this->assertSame([1, 2], array_keys($counted), 'The duplicate must not raise the number of items.');
        $this->assertSame([3], array_keys(administration_history::technical_duplicates($history)));
    }

    /**
     * Different questions are counted as usual, and nothing is marked.
     */
    public function test_different_questions_are_counted_as_usual(): void {
        $this->resetAfterTest();
        [$attemptid] = $this->attempt_with_slots(['a', 'b', 'c']);

        $history = administration_history::for_attempt($attemptid);

        $this->assertCount(3, administration_history::counted_administrations($history));
        $this->assertSame([], administration_history::technical_duplicates($history));
    }

    /**
     * A slot that is shown again - a reload of the active item - is not a duplicate.
     */
    public function test_a_reloaded_slot_is_not_a_duplicate(): void {
        $this->resetAfterTest();
        [$attemptid] = $this->attempt_with_slots(['a']);

        // Rendering the same slot again adds no question attempt; the history is one slot.
        $history = administration_history::for_attempt($attemptid);

        $this->assertCount(1, $history);
        $this->assertSame([], administration_history::technical_duplicates($history));
    }

    /**
     * Pilot items are flagged in the history.
     */
    public function test_pilot_items_are_flagged(): void {
        $this->resetAfterTest();
        [$attemptid, $ids] = $this->attempt_with_slots(['a', 'b']);

        $history = administration_history::for_attempt($attemptid, [$ids['b']]);

        $this->assertFalse($history[1]->ispilot);
        $this->assertTrue($history[2]->ispilot);
    }

    /**
     * Past attempts with a repeated question can be found, with their slots and states.
     */
    public function test_past_duplicates_can_be_found(): void {
        $this->resetAfterTest();
        [$attemptid, $ids] = $this->attempt_with_slots(['a', 'b', 'a']);
        $this->attempt_with_slots(['c', 'd']);

        $found = administration_history::find_duplicate_administrations();

        $this->assertCount(1, $found, 'Exactly the attempt with the repeated question must be found.');
        $this->assertSame($attemptid, $found[0]->attemptid);
        $this->assertSame($ids['a'], $found[0]->questionid);
        $this->assertSame([1, 3], $found[0]->slots);
        $this->assertCount(2, $found[0]->questionattemptids);
        $this->assertCount(2, $found[0]->states);
        $this->assertCount(1, administration_history::find_duplicate_administrations(55001));
        $this->assertCount(0, administration_history::find_duplicate_administrations(99999));
    }
    /**
     * The summary the test taker sees counts a repeated question once, with its first answer.
     */
    public function test_the_questions_summary_counts_each_question_once(): void {
        global $DB;

        $this->resetAfterTest();
        [$attemptid, $ids] = $this->attempt_with_slots(['a', 'b', 'a']);

        // The first administration of 'a' right, the repeated one wrong: only the first may count.
        $history = administration_history::for_attempt($attemptid);
        $DB->set_field('question_attempt_steps', 'fraction', 0, ['questionattemptid' => $history[3]->questionattemptid]);
        $DB->set_field_select(
            'question_attempt_steps',
            'fraction',
            1,
            'questionattemptid = :qa AND fraction IS NOT NULL',
            ['qa' => $history[1]->questionattemptid]
        );

        $rows = \local_catquiz\catquiz::get_attempt_statistics($attemptid);
        $summary = \local_catquiz\local\attempt\attempt_statistics::count_answers($rows, []);

        $this->assertSame(
            2,
            $summary['right'] + $summary['wrong'] + $summary['partial'] + $summary['unanswered'],
            'The repeated question was counted twice.'
        );
        $this->assertSame(0, $summary['wrong'], 'The answer of the repeated administration was counted.');
    }
    /**
     * The measurement takes the first answer of a repeated question and counts it once.
     *
     * The progress keeps responses keyed by question id and ignores a later response to the same
     * question. That is what keeps a technical duplicate from raising N or weighing twice in the
     * estimate; here it is held down with a repeated question answered differently the second time.
     */
    public function test_the_progress_counts_the_first_answer_once(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        [$attemptid, $ids] = $this->attempt_with_slots(['a', 'b', 'a']);
        $history = administration_history::for_attempt($attemptid);

        // First administration of 'a' right, the repeated one wrong.
        $DB->set_field_select(
            'question_attempt_steps',
            'fraction',
            1,
            'questionattemptid = :qa AND fraction IS NOT NULL',
            ['qa' => $history[1]->questionattemptid]
        );
        $DB->set_field_select(
            'question_attempt_steps',
            'fraction',
            0,
            'questionattemptid = :qa AND fraction IS NOT NULL',
            ['qa' => $history[3]->questionattemptid]
        );

        $progress = \local_catquiz\teststrategy\progress::load($attemptid, 'mod_adaptivequiz', 9, (object) []);
        $responses = new \ReflectionProperty($progress, 'responses');
        $responses->setAccessible(true);
        // What update_cached_responses() has collected after slot 1 and slot 2 ...
        $responses->setValue($progress, [
            $ids['a'] => ['questionid' => $ids['a'], 'fraction' => 1.0],
            $ids['b'] => ['questionid' => $ids['b'], 'fraction' => 1.0],
        ]);
        // ... and then the answer to the repeated administration arrives.
        $progress->update_cached_responses();

        $collected = $responses->getValue($progress);
        $this->assertCount(2, $collected, 'The repeated question raised the number of responses.');
        $this->assertEquals(1.0, $collected[$ids['a']]['fraction'], 'The second answer replaced the first.');
    }
    /**
     * Reviewers are told which question was repeated, with its original and duplicate slot.
     */
    public function test_reviewers_see_the_repeated_question(): void {
        global $PAGE;

        $this->resetAfterTest();
        [$withduplicate, $ids] = $this->attempt_with_slots(['a', 'b', 'a']);
        [$without] = $this->attempt_with_slots(['c', 'd']);
        $output = $PAGE->get_renderer('core');

        $data = (new \local_catquiz\output\administration_notice($withduplicate))->export_for_template($output);
        $this->assertTrue($data['hasduplicates']);
        $this->assertCount(1, $data['items']);
        $this->assertSame($ids['a'], $data['items'][0]['questionid']);
        $this->assertSame(1, $data['items'][0]['originalslot']);
        $this->assertSame(3, $data['items'][0]['duplicateslot']);

        $html = $output->render_from_template('local_catquiz/administration_notice', $data);
        $this->assertStringContainsString('data-duplicateslot="3"', $html);

        $none = (new \local_catquiz\output\administration_notice($without))->export_for_template($output);
        $this->assertFalse($none['hasduplicates'], 'An attempt without duplicates must show no notice.');
        $this->assertSame('', trim($output->render_from_template('local_catquiz/administration_notice', $none)));
    }
    /**
     * A duplicate in the question usage does not make an otherwise valid result invalid.
     *
     * The validator decides from the measurement flags and the counts of the progress, never from
     * the question usage. A technical duplicate is the system's mistake; it must not cost the test
     * taker a valid result.
     */
    public function test_a_duplicate_does_not_invalidate_the_result(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        [$attemptid, $ids] = $this->attempt_with_slots(['a', 'b', 'a']);

        // The progress as a real attempt leaves it after answering 'a' and 'b' in scale 5 - the
        // repeated administration of 'a' adds nothing to it, which is the point of this test.
        $progress = \local_catquiz\teststrategy\progress::load($attemptid, 'mod_adaptivequiz', 9, (object) []);
        $played = [];
        foreach (['a', 'b'] as $key) {
            $played[$ids[$key]] = (object) ['id' => $ids[$key], 'catscaleid' => 5, 'is_pilot' => false, 'fisherinformation' => []];
        }
        foreach (
            ['responses' => array_map(fn($q) => ['questionid' => $q->id, 'fraction' => 0.5], $played),
                'playedquestions' => $played, 'lastquestion' => end($played) ?: null,
                'playedquestionsbyscale' => [5 => $played]] as $name => $value
        ) {
            $property = new \ReflectionProperty($progress, $name);
            $property->setAccessible(true);
            $property->setValue($progress, $value);
        }
        $progress->save();

        // The CAT attempt of this test, with a scale result that is valid by every criterion.
        $DB->set_field('local_catquiz_attempts', 'contextid', 9, ['attemptid' => $attemptid]);
        $DB->set_field('local_catquiz_attempts', 'json', json_encode([
            'personabilities_abilities' => [5 => ['value' => 0.4, 'toreport' => true]],
            'se' => [5 => 0.3],
            'primaryscale' => ['id' => 5],
        ]), ['attemptid' => $attemptid]);

        $result = \local_catquiz\local\result\attempt_result_validator::validate($attemptid);

        $this->assertNotEmpty(administration_history::technical_duplicates(administration_history::for_attempt($attemptid)));
        $this->assertTrue($result->is_valid(), 'A technical duplicate made the result invalid.');
        $this->assertTrue($result->get_scale_result(5)->statisticallyvalid);
        $this->assertSame(2, $result->get_scale_result(5)->n, 'The duplicate raised N.');
    }
    /**
     * A reload without a new answer changes nothing in the progress (issue #122).
     *
     * On a reload the last response of the attempt is the one already recorded. It must neither be
     * counted again nor replace what is there.
     */
    public function test_a_reload_without_a_new_answer_changes_nothing(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$attemptid, $ids] = $this->attempt_with_slots(['a']);

        $progress = \local_catquiz\teststrategy\progress::load($attemptid, 'mod_adaptivequiz', 9, (object) []);
        $responses = new \ReflectionProperty($progress, 'responses');
        $responses->setAccessible(true);
        $recorded = [$ids['a'] => ['questionid' => $ids['a'], 'fraction' => 1.0]];
        $responses->setValue($progress, $recorded);

        // The page is loaded again; no answer was given since.
        $progress->update_cached_responses();
        $progress->update_cached_responses();

        $this->assertSame($recorded, $responses->getValue($progress));
    }
}
