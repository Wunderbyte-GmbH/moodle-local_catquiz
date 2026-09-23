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
 * Behaviour tests for local_catquiz external webservice classes.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;
use invalid_parameter_exception;
use local_catquiz\data\catscale_structure;
use local_catquiz\data\dataapi;
use local_catquiz\event\feedbacktab_clicked;
use local_catquiz\external\delete_catscale;
use local_catquiz\external\execute_action;
use local_catquiz\external\feedback_tab_clicked;
use local_catquiz\external\manage_catscale;
use local_catquiz\external\reload_template;
use local_catquiz\external\render_question_with_response;
use local_catquiz\external\subscribe;
use local_catquiz\external\update_parameters;
use require_login_exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/tests/fixtures/external_reload_template_stub.php');

/**
 * Behaviour-level tests for each external class execute() implementation.
 */
final class webservice_external_classes_test extends advanced_testcase {
    /**
     * Creates a scale that is complete enough for the endpoints under test.
     *
     * Rebuilt after being removed by accident while rewriting the reload_template
     * tests; the callers show what it has to provide - an id of a scale that exists
     * in a valid context.
     *
     * @param string $name
     * @return int
     */
    private function create_dummy_catscale(string $name): int {
        global $DB;

        $now = time();
        $contextid = (int) $DB->insert_record('local_catquiz_catcontext', (object) [
            'name' => $name . ' context',
            'description' => '',
            'descriptionformat' => FORMAT_HTML,
            'starttimestamp' => $now - 100,
            'endtimestamp' => $now + 10000,
            'timecreated' => $now,
            'timemodified' => $now,
            'usermodified' => 0,
        ]);

        return (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'parentid' => 0,
            'name' => $name,
            'label' => substr(preg_replace('/[^a-zA-Z0-9]/', '', $name), 0, 20),
            'contextid' => $contextid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }
    /**
     * delete_catscale::execute() should remove an existing catscale row.
     *
     * @return void
     * @covers \local_catquiz\external\delete_catscale::execute
     */
    public function test_delete_catscale_execute_deletes_scale(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $catscaleid = $this->create_dummy_catscale('Delete me');
        $this->assertTrue($DB->record_exists('local_catquiz_catscales', ['id' => $catscaleid]));

        $result = delete_catscale::execute($catscaleid);

        $this->assertTrue($result['success']);
        $this->assertFalse($DB->record_exists('local_catquiz_catscales', ['id' => $catscaleid]));
    }

    /**
     * execute_action::execute() should return failed status for unknown method.
     *
     * @return void
     * @covers \local_catquiz\external\execute_action::execute
     */
    public function test_execute_action_execute_returns_failure_for_unknown_method(): void {
        $this->resetAfterTest(true);
        // These endpoints now require the CAT manager capability. The tests
        // ran without any user, which only worked while nothing was checked.
        $this->setAdminUser();

        $result = execute_action::execute('method_does_not_exist', '{}');

        $this->assertSame(0, $result['success']);
        $this->assertNotEmpty($result['message']);
    }

    /**
     * feedback_tab_clicked::execute() should trigger the feedbacktab_clicked event.
     *
     * @return void
     * @covers \local_catquiz\external\feedback_tab_clicked::execute
     */
    public function test_feedback_tab_clicked_execute_triggers_event(): void {
        global $USER;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $sink = $this->redirectEvents();

        // Issue #68: the attempt is loaded before anyone is authorised for it, so an
        // id that matches no record is refused. The test used the bare id 42 and only
        // passed while nothing checked it.
        global $DB;
        $now = time();
        $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => $USER->id,
            'scaleid' => 1,
            'contextid' => 1,
            'courseid' => 1,
            'attemptid' => 42,
            'component' => 'mod_adaptivequiz',
            'instanceid' => 1,
            'teststrategy' => 4,
            'status' => 1,
            'json' => '{}',
            'debug_info' => '',
            'timecreated' => $now,
            'timemodified' => $now,
            'endtime' => $now,
        ]);

        $result = feedback_tab_clicked::execute(42, 'personabilities', 'Person abilities');

        $events = $sink->get_events();
        $sink->close();

        $this->assertFalse($result['success']);
        $this->assertNotEmpty($events);
        $this->assertInstanceOf(feedbacktab_clicked::class, end($events));
        $last = end($events);
        $this->assertSame(42, $last->other['attemptid']);
        $this->assertSame('personabilities', $last->other['feedback']);
    }

    /**
     * manage_catscale::execute() should create a new catscale.
     *
     * @return void
     * @covers \local_catquiz\external\manage_catscale::execute
     */
    public function test_manage_catscale_execute_creates_scale(): void {
        global $DB;

        $this->resetAfterTest(true);
        // This endpoint now requires the CAT manager capability. The test ran without
        // any user, which only worked while nothing was checked.
        $this->setAdminUser();

        $result = manage_catscale::execute(
            'WS Created Scale',
            'Created via test',
            'create',
            -5.0,
            5.0,
            0,
            0
        );

        $this->assertArrayHasKey('id', $result);
        $this->assertGreaterThan(0, (int)$result['id']);
        $this->assertTrue($DB->record_exists('local_catquiz_catscales', ['id' => (int)$result['id']]));
    }

    /**
     * manage_catscale::execute() should update an existing catscale.
     *
     * @return void
     * @covers \local_catquiz\external\manage_catscale::execute
     */
    public function test_manage_catscale_execute_updates_scale(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $catscaleid = $this->create_dummy_catscale('Original');

        $result = manage_catscale::execute(
            'Updated Name',
            'Updated description',
            'update',
            -4.0,
            4.0,
            0,
            $catscaleid
        );

        $updated = $DB->get_record('local_catquiz_catscales', ['id' => $catscaleid], '*', MUST_EXIST);

        $this->assertArrayHasKey('id', $result);
        $this->assertNotSame(0, $result['id']);
        $this->assertSame('Updated Name', $updated->name);
    }

    /**
     * An unknown action is reported as a failure, not thrown.
     *
     * @covers \local_catquiz\external\reload_template::execute
     * @return void
     */
    public function test_reload_template_reports_an_unknown_action(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $result = reload_template::execute(
            'questiondatacard',
            'method_does_not_exist',
            '',
            1,
            1,
            1,
            'question'
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    /**
     * A renderer name outside the list is refused.
     *
     * Issue #66: the client names a renderer symbolically. A PHP class name has no
     * place in a public contract - it exposes internals, breaks on rename, and invites
     * the endpoint to build whatever it is handed.
     *
     * @covers \local_catquiz\external\reload_template::execute
     * @return void
     */
    public function test_reload_template_refuses_an_unknown_renderer(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $this->expectException(\moodle_exception::class);
        reload_template::execute('not_a_renderer');
    }

    /**
     * The contract declares typed parameters instead of a JSON blob.
     *
     * validate_parameters() can check these; it could never check what was inside the
     * blob, so PARAM_RAW was the only honest declaration - and no declaration at all
     * in practice.
     *
     * @covers \local_catquiz\external\reload_template::execute_parameters
     * @return void
     */
    public function test_reload_template_declares_typed_parameters(): void {
        $this->resetAfterTest(true);

        $parameters = reload_template::execute_parameters();
        $keys = array_keys($parameters->keys);

        foreach (['renderer', 'testitemid', 'contextid', 'catscaleid', 'component'] as $expected) {
            $this->assertContains($expected, $keys, "The contract must declare $expected.");
        }
        $this->assertNotContains('data', $keys, 'The untyped JSON blob is gone.');
        $this->assertNotContains('classlocation', $keys, 'No class name in the contract.');
        $this->assertNotContains('tdparams', $keys, 'No comma-separated parameter list.');
    }

    /**
     * Inserts an attempt owned by a user.
     *
     * @param int $attemptid
     * @param int $userid
     * @return void
     */
    private function add_attempt_for(int $attemptid, int $userid): void {
        global $DB;

        $now = time();
        $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => $userid,
            'scaleid' => 1,
            'contextid' => 1,
            'courseid' => 1,
            'attemptid' => $attemptid,
            'component' => 'mod_adaptivequiz',
            'instanceid' => 1,
            'teststrategy' => 4,
            'status' => 1,
            'json' => '{}',
            'debug_info' => '',
            'timecreated' => $now,
            'timemodified' => $now,
            'endtime' => $now,
        ]);
    }

    /**
     * A participant may act on their own attempt.
     *
     * @covers \local_catquiz\external\feedback_tab_clicked::execute
     * @return void
     */
    public function test_feedback_tab_clicked_allows_the_own_attempt(): void {
        $this->resetAfterTest(true);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->add_attempt_for(8801, (int) $user->id);

        $sink = $this->redirectEvents();
        feedback_tab_clicked::execute(8801, 'personabilities', 'Person abilities');

        $this->assertNotEmpty(
            $sink->get_events(),
            'Acting on your own attempt has to keep working.'
        );
    }

    /**
     * A participant may not act on somebody else's attempt.
     *
     * validate_context() establishes where a request acts, not whether this user may
     * act on this object. Without the ownership check any authenticated user could
     * pass a foreign attempt id - and was then logged as that attempt's "student".
     *
     * @covers \local_catquiz\external\feedback_tab_clicked::execute
     * @return void
     */
    public function test_feedback_tab_clicked_denies_a_foreign_attempt(): void {
        $this->resetAfterTest(true);

        $owner = $this->getDataGenerator()->create_user();
        $intruder = $this->getDataGenerator()->create_user();
        $this->add_attempt_for(8802, (int) $owner->id);
        $this->setUser($intruder);

        $sink = $this->redirectEvents();

        try {
            feedback_tab_clicked::execute(8802, 'personabilities', 'Person abilities');
            $this->fail('A foreign attempt must be refused.');
        } catch (\moodle_exception $e) {
            // Expected.
            $this->assertSame([], $sink->get_events(), 'A refused call must raise no event.');
        }
    }

    /**
     * An attempt that does not exist is refused rather than allowed.
     *
     * An unknown object is not a permitted one - the check has to fail closed.
     *
     * @covers \local_catquiz\external\feedback_tab_clicked::execute
     * @return void
     */
    public function test_feedback_tab_clicked_denies_an_unknown_attempt(): void {
        $this->resetAfterTest(true);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $sink = $this->redirectEvents();

        $this->expectException(\moodle_exception::class);
        try {
            feedback_tab_clicked::execute(999999, 'personabilities', 'Person abilities');
        } finally {
            $this->assertSame([], $sink->get_events(), 'A refused call must raise no event.');
        }
    }
    /**
     * An identifier that names no feedback generator is refused.
     *
     * Both the identifier and its translation arrive from the client and were written
     * into the event log as if they described what happened. A log entry whose subject
     * the caller chose freely is not audit evidence.
     *
     * @covers \local_catquiz\external\feedback_tab_clicked::execute
     * @return void
     */
    public function test_feedback_tab_clicked_refuses_an_unknown_identifier(): void {
        $this->resetAfterTest(true);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->add_attempt_for(8803, (int) $user->id);

        $sink = $this->redirectEvents();

        $this->expectException(\moodle_exception::class);
        try {
            feedback_tab_clicked::execute(8803, 'not_a_generator', 'anything');
        } finally {
            $this->assertSame([], $sink->get_events(), 'A refused call must raise no event.');
        }
    }
}
