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
 * Issue #54: unusable item parameters are recognisable in the backend.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\local;

use advanced_testcase;
use local_catquiz\local\model\model_strategy;

/**
 * Verifies the classification of stored item parameters.
 *
 * The backend must reach the same verdict as the runtime: an item whose parameters
 * violate the model contract is played as a pilot item, and the pool maintainer has
 * to be able to see that. These tests therefore also assert that the classification
 * agrees with model_strategy::validate_item_parameters() itself - if the two ever
 * drifted apart, the display would lie about what the test actually does.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\local\itemparam_validity
 */
final class itemparam_validity_test extends advanced_testcase {
    /**
     * Builds an item record as the question list would return it.
     *
     * @param array $fields
     * @return \stdClass
     */
    private function record(array $fields): \stdClass {
        return (object) array_merge([
            'model' => null,
            'difficulty' => null,
            'discrimination' => null,
            'guessing' => null,
            'json' => null,
        ], $fields);
    }

    /**
     * A well formed 2PL item is usable.
     *
     * @return void
     */
    public function test_valid_two_parameter_item_is_usable(): void {
        $this->resetAfterTest();

        $result = itemparam_validity::classify($this->record([
            'model' => 'raschbirnbaum',
            'difficulty' => 0.5,
            'discrimination' => 1.2,
        ]));

        $this->assertEquals(itemparam_validity::STATE_USABLE, $result['state']);
        $this->assertSame([], $result['reasons']);
    }

    /**
     * A 2PL item with discrimination 0 is unusable and says why.
     *
     * This is the documented production case: such an item is mathematically mute -
     * P(theta) is 0.5 for every theta and the Fisher information is 0 - so the
     * ability estimate freezes no matter how many questions follow.
     *
     * @return void
     */
    public function test_two_parameter_item_without_discrimination_is_unusable(): void {
        $this->resetAfterTest();

        $result = itemparam_validity::classify($this->record([
            'model' => 'raschbirnbaum',
            'difficulty' => 0.5,
            'discrimination' => 0.0,
        ]));

        $this->assertEquals(itemparam_validity::STATE_UNUSABLE, $result['state']);
        $this->assertNotEmpty($result['reasons']);

        $reason = itemparam_validity::get_reason_text($result);
        $this->assertStringContainsString('raschbirnbaum', $reason, 'The model must be named.');
        $this->assertStringContainsString('discrimination', $reason, 'The cause must be named.');
    }

    /**
     * A 1PL item keeps working although a discrimination of 0 is stored.
     *
     * The Rasch model does not use the field, so the stored 0 is harmless. Only when
     * the same item is evaluated through a two parameter model does it turn mute -
     * which is exactly why the verdict has to come from the model contract rather
     * than from looking at the value alone.
     *
     * @return void
     */
    public function test_rasch_item_with_stored_zero_discrimination_stays_usable(): void {
        $this->resetAfterTest();

        $result = itemparam_validity::classify($this->record([
            'model' => 'rasch',
            'difficulty' => -0.3,
            'discrimination' => 0.0,
        ]));

        $this->assertEquals(itemparam_validity::STATE_USABLE, $result['state']);
    }

    /**
     * A polytomous item with a broken json payload is unusable.
     *
     * @return void
     */
    public function test_polytomous_item_with_broken_payload_is_unusable(): void {
        $this->resetAfterTest();

        $broken = itemparam_validity::classify($this->record([
            'model' => 'grmgeneralized',
            'difficulty' => 0.0,
            'discrimination' => 1.0,
            'json' => '{not valid json',
        ]));
        $missing = itemparam_validity::classify($this->record([
            'model' => 'grmgeneralized',
            'difficulty' => 0.0,
            'discrimination' => 1.0,
        ]));

        $this->assertEquals(itemparam_validity::STATE_UNUSABLE, $broken['state']);
        $this->assertEquals(itemparam_validity::STATE_UNUSABLE, $missing['state']);
    }

    /**
     * An item without any parameters is a classic pilot item, not a broken one.
     *
     * Both end up being played as pilot items, but only one of them needs attention,
     * so the display has to keep them apart.
     *
     * @return void
     */
    public function test_item_without_parameters_is_a_classic_pilot_item(): void {
        $this->resetAfterTest();

        $result = itemparam_validity::classify($this->record([]));

        $this->assertEquals(itemparam_validity::STATE_NOPARAMS, $result['state']);
        $this->assertNotEquals(
            itemparam_validity::STATE_UNUSABLE,
            $result['state'],
            'A pilot item without parameters must not be reported as broken.'
        );
    }

    /**
     * A stored 0.0 counts as a parameter, not as "no parameters".
     *
     * This targets the branch that separates a classic pilot item from a broken one.
     * PHP treats 0.0 as falsy, so an emptiness check would report an item that does
     * carry stored values - but no model - as a harmless pilot item and hide the
     * inconsistency instead of surfacing it.
     *
     * @return void
     */
    public function test_stored_zero_is_not_mistaken_for_a_missing_parameter(): void {
        $this->resetAfterTest();

        $withstoredzero = itemparam_validity::classify($this->record([
            'model' => '',
            'difficulty' => 0.0,
            'discrimination' => 0.0,
        ]));
        $withnothing = itemparam_validity::classify($this->record(['model' => '']));

        $this->assertEquals(
            itemparam_validity::STATE_UNUSABLE,
            $withstoredzero['state'],
            'Stored values without a model are an inconsistency, not a pilot item.'
        );
        $this->assertEquals(
            itemparam_validity::STATE_NOPARAMS,
            $withnothing['state'],
            'Without any stored value it is a classic pilot item.'
        );
    }

    /**
     * The classification never contradicts the truth source.
     *
     * @return void
     */
    public function test_classification_agrees_with_the_validation_rule(): void {
        $this->resetAfterTest();

        $records = [
            $this->record(['model' => 'raschbirnbaum', 'difficulty' => 0.5, 'discrimination' => 1.2]),
            $this->record(['model' => 'raschbirnbaum', 'difficulty' => 0.5, 'discrimination' => 0.0]),
            $this->record(['model' => 'rasch', 'difficulty' => 0.1, 'discrimination' => 0.0]),
            $this->record(['model' => 'grmgeneralized', 'difficulty' => 0.0, 'json' => '{broken']),
        ];

        foreach ($records as $record) {
            $expected = empty(model_strategy::validate_item_parameters($record))
                ? itemparam_validity::STATE_USABLE
                : itemparam_validity::STATE_UNUSABLE;

            $this->assertEquals(
                $expected,
                itemparam_validity::classify($record)['state'],
                'The backend verdict must match validate_item_parameters().'
            );
        }
    }

    /**
     * The stamped flag always agrees with the rule it is derived from.
     *
     * Persisting a derived value is what makes server side filtering and sorting
     * possible at all, but it can drift away from the rule - and the item parameters
     * are written from eight different places. This pins that the stamp uses nothing
     * but validate_item_parameters().
     *
     * @return void
     */
    public function test_stamp_agrees_with_the_validation_rule(): void {
        $this->resetAfterTest();

        $records = [
            $this->record(['model' => 'raschbirnbaum', 'difficulty' => 0.5, 'discrimination' => 1.2]),
            $this->record(['model' => 'raschbirnbaum', 'difficulty' => 0.5, 'discrimination' => 0.0]),
            $this->record(['model' => 'rasch', 'difficulty' => 0.1, 'discrimination' => 0.0]),
            $this->record(['model' => 'grmgeneralized', 'difficulty' => 0.0, 'json' => '{broken']),
        ];

        $seenusable = false;
        $seenunusable = false;
        foreach ($records as $record) {
            $expected = empty(model_strategy::validate_item_parameters($record)) ? 1 : 0;
            itemparam_validity::stamp($record);

            $this->assertSame($expected, (int) $record->usable);
            $seenusable = $seenusable || $expected === 1;
            $seenunusable = $seenunusable || $expected === 0;
        }

        // Without both outcomes the assertions above would hold for a stamp that
        // simply always writes the same value.
        $this->assertTrue($seenusable && $seenunusable, 'The sample must contain both outcomes.');
    }

    /**
     * A record written through the normal path carries the correct flag.
     *
     * @return void
     */
    public function test_written_record_carries_the_flag(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $now = time();
        $mute = (object) [
            'itemid' => 1,
            'componentname' => 'question',
            'contextid' => 1,
            'model' => 'raschbirnbaum',
            'difficulty' => 0.5,
            'discrimination' => 0.0,
            'status' => 4,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        itemparam_validity::stamp($mute);
        $id = $DB->insert_record('local_catquiz_itemparams', $mute);

        $this->assertEquals(
            0,
            (int) $DB->get_field('local_catquiz_itemparams', 'usable', ['id' => $id]),
            'A 2PL item with discrimination 0 must be stored as unusable.'
        );
    }

    /**
     * The consistency check reports rows whose stored flag drifted.
     *
     * @return void
     */
    public function test_consistency_check_finds_drift(): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->dirroot . '/local/catquiz/db/upgrade.php');

        $now = time();
        $id = $DB->insert_record('local_catquiz_itemparams', (object) [
            'itemid' => 2,
            'componentname' => 'question',
            'contextid' => 1,
            'model' => 'raschbirnbaum',
            'difficulty' => 0.5,
            'discrimination' => 0.0,
            'usable' => 1,
            'status' => 4,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        // Dry run reports without changing anything.
        $this->assertEquals(1, local_catquiz_upgrade_backfill_usable(true));
        $this->assertEquals(1, (int) $DB->get_field('local_catquiz_itemparams', 'usable', ['id' => $id]));

        $this->assertEquals(1, local_catquiz_upgrade_backfill_usable());
        $this->assertEquals(0, (int) $DB->get_field('local_catquiz_itemparams', 'usable', ['id' => $id]));

        // Nothing left to fix once it has run.
        $this->assertEquals(0, local_catquiz_upgrade_backfill_usable(true));
    }

    /**
     * The per scale aggregate counts only items whose active parameter is unusable.
     *
     * @return void
     */
    public function test_unusable_counts_per_scale(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $now = time();
        $contextid = 77;

        // Registers an item with an active parameter of the given usability.
        $additem = function (int $scaleid, int $usable, int $seq) use ($DB, $now, $contextid): void {
            $itemid = (int) $DB->insert_record('local_catquiz_items', (object) [
                'componentid' => 9000 + $seq,
                'componentname' => 'question',
                'catscaleid' => $scaleid,
                'contextid' => $contextid,
                'activeparamid' => 0,
                'status' => 0,
            ]);
            $paramid = (int) $DB->insert_record('local_catquiz_itemparams', (object) [
                'itemid' => $itemid,
                'componentname' => 'question',
                'contextid' => $contextid,
                'model' => 'rasch',
                'difficulty' => 0.1,
                'usable' => $usable,
                'status' => 4,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
            $DB->set_field('local_catquiz_items', 'activeparamid', $paramid, ['id' => $itemid]);
        };

        $additem(11, 0, 1);
        $additem(11, 0, 2);
        $additem(11, 1, 3);
        $additem(12, 1, 4);

        // An item in piloting: no active parameter at all. That is an expected state,
        // not a broken one, and must not be counted.
        $DB->insert_record('local_catquiz_items', (object) [
            'componentid' => 9099,
            'componentname' => 'question',
            'catscaleid' => 11,
            'contextid' => $contextid,
            'activeparamid' => 0,
            'status' => 0,
        ]);

        $counts = \local_catquiz\catquiz::get_unusable_item_counts_per_scale($contextid);

        $this->assertEquals(2, $counts[11] ?? 0, 'Two unusable items in this scale.');
        $this->assertArrayNotHasKey(12, $counts, 'A scale without unusable items has no row.');
    }

    /**
     * Items without an active parameter appear in the list, statistics included.
     *
     * Items without parameters - or without an *active* parameter - are precisely the
     * ones in piloting, and their attempt numbers are of interest while they are
     * being piloted. The list used to join the parameters with an INNER JOIN, so
     * those items were missing entirely; the statistics were additionally joined via
     * the parameter row's context, which is NULL for them.
     *
     * @return void
     */
    public function test_items_without_active_parameter_are_listed(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $now = time();
        $contextid = (int) $DB->insert_record('local_catquiz_catcontext', (object) [
            'name' => 'Issue 54 context',
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
            'name' => 'Issue 54 scale',
            'contextid' => $contextid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $questionid = (int) $DB->insert_record('question', (object) [
            'name' => 'Pilot item',
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
        $category = $this->getDataGenerator()->get_plugin_generator('core_question')
            ->create_question_category();
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

        // An item in piloting: registered for the scale, but with no active parameter.
        $DB->insert_record('local_catquiz_items', (object) [
            'componentid' => $questionid,
            'componentname' => 'question',
            'catscaleid' => $scaleid,
            'contextid' => $contextid,
            'activeparamid' => 0,
            'status' => 0,
        ]);

        [$select, $from, $where, , $params] = \local_catquiz\catquiz::return_sql_for_catscalequestions(
            [$scaleid],
            $contextid,
            []
        );
        $rows = $DB->get_records_sql("SELECT $select FROM $from WHERE $where", $params);

        $ids = array_map('intval', array_column($rows, 'id'));
        $this->assertContains($questionid, $ids, 'A piloted item must not vanish from the list.');

        $row = null;
        foreach ($rows as $candidate) {
            if ((int) $candidate->id === $questionid) {
                $row = $candidate;
                break;
            }
        }

        $this->assertNotNull($row);
        $this->assertObjectHasProperty('attempts', $row, 'Its statistics must still be reported.');
        $this->assertEquals(
            itemparam_validity::STATE_NOPARAMS,
            itemparam_validity::classify((object) (array) $row)['state'],
            'Without parameters it is a classic pilot item, not a broken one.'
        );
    }
    /**
     * Writing through the model layer stamps the flag, whatever the caller.
     *
     * Eight places write item parameters, and the import and the recalibration reach
     * the table through model_item_param::save() rather than by inserting directly.
     * A test that only calls stamp() would miss whether those paths use it at all -
     * this one writes the way the application does and reads the flag back from the
     * database.
     *
     * @return void
     */
    public function test_model_save_stamps_the_flag(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $now = time();
        $contextid = (int) $DB->insert_record('local_catquiz_catcontext', (object) [
            'name' => 'Write path context',
            'description' => '',
            'descriptionformat' => FORMAT_HTML,
            'starttimestamp' => $now - 100,
            'endtimestamp' => $now + 10000,
            'timecreated' => $now,
            'timemodified' => $now,
            'usermodified' => 0,
        ]);

        // A 2PL item with discrimination 0 is mute and must be stored as unusable.
        $mute = new \local_catquiz\local\model\model_item_param(1, 'raschbirnbaum');
        $mute->set_parameters(['difficulty' => 0.5, 'discrimination' => 0.0]);
        $mute->set_status(LOCAL_CATQUIZ_STATUS_CALCULATED);
        $mute->set_contextid($contextid);
        $mute->save();

        $stored = $DB->get_record('local_catquiz_itemparams', ['id' => $mute->get_id()], 'usable');
        $this->assertNotFalse($stored, 'The parameter must have been written.');
        $this->assertEquals(0, (int) $stored->usable, 'A mute 2PL item must be stored as unusable.');

        // Recalibration updates the same row; the flag has to follow the new values.
        $mute->set_parameters(['difficulty' => 0.5, 'discrimination' => 1.2]);
        $mute->save();

        $this->assertEquals(
            1,
            (int) $DB->get_field('local_catquiz_itemparams', 'usable', ['id' => $mute->get_id()]),
            'After recalibration the flag must reflect the new parameters.'
        );
    }

    /**
     * After any write the consistency check finds nothing to fix.
     *
     * The stronger statement: not "the flag looks right" but "recomputing it from
     * the rule changes nothing".
     *
     * @return void
     */
    public function test_no_drift_after_writing(): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->dirroot . '/local/catquiz/db/upgrade.php');

        $now = time();
        foreach (
            [
            ['model' => 'rasch', 'difficulty' => 0.1, 'discrimination' => 0.0],
            ['model' => 'raschbirnbaum', 'difficulty' => 0.5, 'discrimination' => 0.0],
            ['model' => 'raschbirnbaum', 'difficulty' => 0.5, 'discrimination' => 1.4],
            ] as $index => $values
        ) {
            $record = (object) array_merge([
                'itemid' => 100 + $index,
                'componentname' => 'question',
                'contextid' => 1,
                'status' => 4,
                'timecreated' => $now,
                'timemodified' => $now,
            ], $values);
            \local_catquiz\catquiz::save_item_param($record);
        }

        $this->assertEquals(
            0,
            local_catquiz_upgrade_backfill_usable(true),
            'Recomputing the flag after a write must not change anything.'
        );
    }
    /**
     * The visible column is sortable and the query provides it.
     *
     * Review finding: only the underlying field "usable" was registered, while the
     * header sends the visible name. The click referred to a column the table did not
     * know as sortable, and an ORDER BY on it would have hit a column the query does
     * not select either.
     *
     * @return void
     */
    public function test_visible_validity_column_is_sortable(): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $now = time();
        $contextid = (int) $DB->insert_record('local_catquiz_catcontext', (object) [
            'name' => 'Sortable context',
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
            'name' => 'Sortable scale',
            'contextid' => $contextid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        // The outer select is "*"; the columns are named in the derived table, so
        // that is where the alias has to appear.
        [, $from] = \local_catquiz\catquiz::return_sql_for_catscalequestions([$scaleid], $contextid, []);

        $this->assertStringContainsString(
            'itemparamvalidity',
            $from,
            'The query must provide the column the header sorts by.'
        );

        $source = file_get_contents(
            $CFG->dirroot . '/local/catquiz/classes/output/catscalemanager/questions/questionsdisplay.php'
        );
        $this->assertStringNotContainsString(
            "unset(\$sortcolumns['itemparamvalidity'])",
            $source,
            'Removing the visible column from the sortable set makes the header inert.'
        );
    }

    /**
     * The per scale aggregate is restricted to the context being looked at.
     *
     * Review finding: the call passed no context, so counts from every context of the
     * installation were mixed into one number - not what the page shows.
     *
     * @return void
     */
    public function test_aggregate_is_restricted_to_the_context(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $now = time();
        $scaleid = 4242;

        // The same scale with one unusable item in each of two contexts.
        foreach ([501, 502] as $contextid) {
            $itemid = (int) $DB->insert_record('local_catquiz_items', (object) [
                'componentid' => 8000 + $contextid,
                'componentname' => 'question',
                'catscaleid' => $scaleid,
                'contextid' => $contextid,
                'activeparamid' => 0,
                'status' => 0,
            ]);
            $paramid = (int) $DB->insert_record('local_catquiz_itemparams', (object) [
                'itemid' => $itemid,
                'componentname' => 'question',
                'contextid' => $contextid,
                'model' => 'raschbirnbaum',
                'difficulty' => 0.5,
                'discrimination' => 0.0,
                'usable' => 0,
                'status' => 4,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
            $DB->set_field('local_catquiz_items', 'activeparamid', $paramid, ['id' => $itemid]);
        }

        $all = \local_catquiz\catquiz::get_unusable_item_counts_per_scale();
        $one = \local_catquiz\catquiz::get_unusable_item_counts_per_scale(501);

        $this->assertEquals(2, $all[$scaleid] ?? 0, 'Without a context both are counted.');
        $this->assertEquals(1, $one[$scaleid] ?? 0, 'With a context only that one counts.');
    }
}
