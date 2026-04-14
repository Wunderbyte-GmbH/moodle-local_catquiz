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
use local_catquiz\external\get_next_question;
use local_catquiz\external\manage_catscale;
use local_catquiz\external\reload_template;
use local_catquiz\external\render_question_with_response;
use local_catquiz\external\start_new_attempt;
use local_catquiz\external\submit_result;
use local_catquiz\external\subscribe;
use local_catquiz\external\update_parameters;
use require_login_exception;

/**
 * Simple render stub for reload_template test.
 */
class external_reload_template_stub {
    /**
     * Accept any constructor params from webservice payload.
     *
     * @param mixed ...$params
     */
    public function __construct(...$params) {
    }

    /**
     * Mimic template export function.
     *
     * @return array
     */
    public function export_for_template(): array {
        return [
            'ok' => true,
        ];
    }
}

/**
 * Behaviour-level tests for each external class execute() implementation.
 */
final class webservice_external_classes_test extends advanced_testcase {
    /**
     * delete_catscale::execute() should remove an existing catscale row.
     *
     * @return void
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
     */
    public function test_execute_action_execute_returns_failure_for_unknown_method(): void {
        $this->resetAfterTest(true);

        $result = execute_action::execute('method_does_not_exist', '{}');

        $this->assertSame(0, $result['success']);
        $this->assertNotEmpty($result['message']);
    }

    /**
     * feedback_tab_clicked::execute() should trigger the feedbacktab_clicked event.
     *
     * @return void
     */
    public function test_feedback_tab_clicked_execute_triggers_event(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $sink = $this->redirectEvents();

        $result = feedback_tab_clicked::execute(42, 'raw feedback', 'translated feedback');

        $events = $sink->get_events();
        $sink->close();

        $this->assertFalse($result['success']);
        $this->assertNotEmpty($events);
        $this->assertInstanceOf(feedbacktab_clicked::class, end($events));
        $last = end($events);
        $this->assertSame(42, $last->other['attemptid']);
        $this->assertSame('raw feedback', $last->other['feedback']);
    }

    /**
     * get_next_question::execute() currently rejects arguments due execute_parameters mismatch.
     *
     * @return void
     */
    public function test_get_next_question_execute_raises_invalid_parameter_exception(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $this->expectException(invalid_parameter_exception::class);
        get_next_question::execute(1, 1, 'mod_adaptivequiz');
    }

    /**
     * manage_catscale::execute() should create a new catscale.
     *
     * @return void
     */
    public function test_manage_catscale_execute_creates_scale(): void {
        global $DB;

        $this->resetAfterTest(true);

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
     * reload_template::execute() should return failed status if action method does not exist.
     *
     * @return void
     */
    public function test_reload_template_execute_returns_failure_for_unknown_method(): void {
        $this->resetAfterTest(true);

        $payload = json_encode([
            'admethodname' => 'method_does_not_exist',
            'adparams' => '',
            'tdparams' => '',
            'classlocation' => '\\local_catquiz\\external_reload_template_stub',
        ]);

        $result = reload_template::execute($payload);

        if (!is_array($result)) {
            $this->fail('reload_template::execute() must return an array result.');
        }

        $this->assertSame(0, $result['success']);
        $this->assertNotEmpty($result['message']);
    }

    /**
     * render_question_with_response::execute() should require login.
     *
     * @return void
     */
    public function test_render_question_with_response_execute_requires_login(): void {
        $this->resetAfterTest(true);

        $this->expectException(require_login_exception::class);
        render_question_with_response::execute(1, 1);
    }

    /**
     * start_new_attempt::execute() should require login.
     *
     * @return void
     */
    public function test_start_new_attempt_execute_requires_login(): void {
        $this->resetAfterTest(true);

        $this->expectException(require_login_exception::class);
        start_new_attempt::execute(1, 1);
    }

    /**
     * submit_result::execute() should require login.
     *
     * @return void
     */
    public function test_submit_result_execute_requires_login(): void {
        $this->resetAfterTest(true);

        $this->expectException(require_login_exception::class);
        submit_result::execute('1', 1, 1);
    }

    /**
     * subscribe::execute() should toggle subscription state.
     *
     * @return void
     */
    public function test_subscribe_execute_toggles_subscription(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $userid = get_admin()->id;
        $area = 'catscale';
        $itemid = 1234;

        $first = subscribe::execute($userid, $area, $itemid);
        $second = subscribe::execute($userid, $area, $itemid);

        $record = $DB->get_record('local_catquiz_subscriptions', [
            'userid' => $userid,
            'area' => $area,
            'itemid' => $itemid,
        ], '*', MUST_EXIST);

        $this->assertSame(1, $first['subscribed']);
        $this->assertSame(0, $second['subscribed']);
        $this->assertSame(0, (int)$record->status);
    }

    /**
     * update_parameters::execute() should return failed status for invalid ids.
     *
     * @return void
     */
    public function test_update_parameters_execute_returns_failure_for_invalid_ids(): void {
        $this->resetAfterTest(true);

        $result = update_parameters::execute(0, 0);

        $this->assertFalse($result['success']);
    }

    /**
     * Create a minimal catscale used in execute() tests.
     *
     * @param string $name
     * @return int
     */
    private function create_dummy_catscale(string $name): int {
        $catscalestructure = new catscale_structure([
            'name' => $name,
            'description' => 'Created for external test',
            'action' => 'create',
            'minscalevalue' => -5.0,
            'maxscalevalue' => 5.0,
            'parentid' => 0,
            'id' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        return dataapi::create_catscale($catscalestructure);
    }
}
