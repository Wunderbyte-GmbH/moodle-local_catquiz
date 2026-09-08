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
 * Tests for the central calculation service and its contract (issue #43).
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;
use core\lock\lock_config;
use InvalidArgumentException;
use local_catquiz\local\calculation\calculation_mode;
use local_catquiz\local\calculation\calculation_request;
use local_catquiz\local\calculation\calculation_result;
use local_catquiz\local\calculation\calculation_service;
use local_catquiz\local\calculation\calculation_trigger;
use local_catquiz\local\calculation\disruptive_recalculation;
use local_catquiz\local\calculation\identifiability_aware;
use local_catquiz\local\calculation\incremental_recalculation;

/**
 * Tests for the central calculation service and its contract (issue #43).
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_catquiz\local\calculation\calculation_service
 * @covers \local_catquiz\local\calculation\calculation_request
 * @covers \local_catquiz\local\calculation\calculation_result
 */
final class calculation_service_test extends advanced_testcase {
    /**
     * The request validates its inputs.
     *
     * @return void
     */
    public function test_request_validates_inputs(): void {
        $request = new calculation_request(
            5,
            9,
            calculation_mode::INCREMENTAL_RECALCULATION,
            calculation_trigger::MANUAL,
            7
        );
        $this->assertSame(5, $request->get_scaleid());
        $this->assertSame(9, $request->get_contextid());
        $this->assertSame($request->to_array(), calculation_request::from_array($request->to_array())->to_array());

        $this->expectException(InvalidArgumentException::class);
        new calculation_request(0, 9, calculation_mode::INCREMENTAL_RECALCULATION, calculation_trigger::MANUAL);
    }

    /**
     * A scheduled trigger may not run the disruptive mode.
     *
     * @return void
     */
    public function test_scheduled_disruptive_is_rejected(): void {
        $this->expectException(InvalidArgumentException::class);
        new calculation_request(
            1,
            1,
            calculation_mode::DISRUPTIVE_RECALCULATION,
            calculation_trigger::SCHEDULED
        );
    }

    /**
     * The result round-trips and reports its status.
     *
     * @return void
     */
    public function test_result_roundtrip(): void {
        $result = new calculation_result(calculation_mode::INCREMENTAL_RECALCULATION, 3, 11);
        $this->assertSame(calculation_result::STATUS_SKIPPED, $result->get_status());
        $result->set('changeditems', 4)->finish(calculation_result::STATUS_SUCCESS);
        $this->assertSame(calculation_result::STATUS_SUCCESS, $result->get_status());

        $restored = calculation_result::from_array($result->to_array());
        $this->assertSame(4, $restored->get('changeditems'));
        $this->assertSame(11, $restored->get('sourcecontextid'));
        $this->assertNotEmpty($restored->to_console_line());
    }

    /**
     * The service returns the correct strategy per mode.
     *
     * @return void
     */
    public function test_strategy_selection(): void {
        $service = new calculation_service();
        $this->assertInstanceOf(
            incremental_recalculation::class,
            $service->get_strategy(calculation_mode::INCREMENTAL_RECALCULATION)
        );
        $this->assertInstanceOf(
            disruptive_recalculation::class,
            $service->get_strategy(calculation_mode::DISRUPTIVE_RECALCULATION)
        );
        $this->expectException(InvalidArgumentException::class);
        $service->get_strategy('nonsense');
    }

    /**
     * Creates a scale with an active context and returns [scaleid, contextid].
     *
     * @return array
     */
    private function make_scale_with_context(): array {
        global $DB;
        $now = time();
        $contextid = $DB->insert_record('local_catquiz_catcontext', (object) [
            'name' => 'Active context',
            'starttimestamp' => 0,
            'endtimestamp' => $now + WEEKSECS,
            'timecreated' => $now,
            'timemodified' => $now,
            'timecalculated' => $now,
            'usermodified' => 0,
        ]);
        $scaleid = $DB->insert_record('local_catquiz_catscales', (object) [
            'parentid' => 0,
            'name' => 'Scale 43',
            'contextid' => $contextid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        return [(int) $scaleid, (int) $contextid];
    }

    /**
     * Incremental run without new responses keeps the context and is a no-op.
     *
     * @return void
     */
    public function test_incremental_keeps_context(): void {
        global $DB;
        $this->resetAfterTest(true);
        [$scaleid, $contextid] = $this->make_scale_with_context();
        $contextsbefore = $DB->count_records('local_catquiz_catcontext');

        $service = new calculation_service();
        ob_start();
        $result = $service->execute(new calculation_request(
            $scaleid,
            $contextid,
            calculation_mode::INCREMENTAL_RECALCULATION,
            calculation_trigger::MANUAL,
            0
        ));
        ob_end_clean();

        $this->assertSame(calculation_result::STATUS_SKIPPED, $result->get_status());
        $this->assertSame($contextid, (int) $result->get('targetcontextid'));
        $this->assertSame(
            $contextsbefore,
            $DB->count_records('local_catquiz_catcontext'),
            'Incremental recalculation must not create a new context.'
        );
        $this->assertSame(
            $contextid,
            (int) $DB->get_field('local_catquiz_catscales', 'contextid', ['id' => $scaleid]),
            'Incremental recalculation must not change catscale.contextid.'
        );
        // The summary is persisted and retrievable.
        $this->assertNotNull(calculation_service::get_last_summary($scaleid));
    }

    /**
     * queue() enqueues an ad-hoc task carrying the request.
     *
     * @return void
     */
    public function test_queue_enqueues_adhoc_task(): void {
        $this->resetAfterTest(true);
        [$scaleid, $contextid] = $this->make_scale_with_context();

        $service = new calculation_service();
        $service->queue(new calculation_request(
            $scaleid,
            $contextid,
            calculation_mode::DISRUPTIVE_RECALCULATION,
            calculation_trigger::MANUAL,
            0
        ));

        $tasks = \core\task\manager::get_adhoc_tasks(\local_catquiz\task\adhoc_calculation::class);
        $this->assertCount(1, $tasks);
        $task = reset($tasks);
        $data = (array) $task->get_custom_data();
        $this->assertSame($scaleid, (int) $data['scaleid']);
        $this->assertSame(calculation_mode::DISRUPTIVE_RECALCULATION, $data['mode']);
        $this->assertSame(calculation_trigger::MANUAL, $data['trigger']);
    }

    /**
     * A concurrent run for the same scale is prevented by the lock.
     *
     * The service uses the Moodle Lock API to serialise runs per scale. Cross-
     * process locking cannot be reproduced inside a single PHPUnit process, so
     * this test only asserts the guard when the environment's lock factory
     * actually blocks a re-acquisition; otherwise it is skipped.
     *
     * @return void
     */
    public function test_concurrent_run_is_locked_out(): void {
        $this->resetAfterTest(true);
        [$scaleid, $contextid] = $this->make_scale_with_context();

        $factory = lock_config::get_lock_factory('local_catquiz_calculation');
        $held = $factory->get_lock('scale_' . $scaleid, 0);
        $this->assertNotFalse($held);

        // If a *separate* factory instance on the same DB session can re-acquire
        // the lock (Postgres session advisory locks are re-entrant within one
        // connection), cross-process locking cannot be demonstrated here; skip.
        $probefactory = lock_config::get_lock_factory('local_catquiz_calculation');
        $reacquire = $probefactory->get_lock('scale_' . $scaleid, 0);
        if ($reacquire !== false) {
            $reacquire->release();
            $held->release();
            $this->markTestSkipped('Lock factory is re-entrant within one DB session; cross-process locking not testable.');
        }

        $service = new calculation_service();
        ob_start();
        $result = $service->execute(new calculation_request(
            $scaleid,
            $contextid,
            calculation_mode::INCREMENTAL_RECALCULATION,
            calculation_trigger::MANUAL,
            0
        ));
        ob_end_clean();
        $held->release();

        $this->assertSame(calculation_result::STATUS_ERROR, $result->get_status());
        $this->assertNotEmpty($result->get('errors'));
    }
    /**
     * The identifiability summary (K5) is written into the result criteria and warnings.
     *
     * @covers \\local_catquiz\\local\\calculation\\identifiability_aware
     * @return void
     */
    public function test_identifiability_is_applied_to_result(): void {
        $this->resetAfterTest(true);
        $result = new calculation_result(calculation_mode::DISRUPTIVE_RECALCULATION, 1, 10);
        $applier = new class {
            use identifiability_aware;

            /**
             * Expose the protected trait methods for testing.
             * @param calculation_result $result
             * @param array $summary
             * @return void
             */
            public function apply(calculation_result $result, array $summary): void {
                $this->apply_criteria($result, $summary);
                $this->apply_counts($result, $summary['counts'] ?? null);
            }
        };
        $applier->apply($result, [
            'criteriabefore' => ['aic' => 200.0, 'bic' => 210.0, 'caic' => 213.0],
            'criteriaafter' => ['aic' => 180.0, 'bic' => 191.0, 'caic' => 194.0],
            'iterations' => 3,
            'convergencereason' => 'maximum iterations reached',
            'counts' => ['numresponses' => 40, 'numpersons' => 10, 'numitems' => 4],
            'identifiability' => [
                'warnings' => ['Item q1 (grm): large residual gradient'],
            ],
        ]);
        $array = $result->to_array();
        $this->assertSame(180.0, $array['criteriaafter']['aic']);
        $this->assertSame(200.0, $array['criteriabefore']['aic']);
        $this->assertSame(3, $array['iterations']);
        $this->assertSame('maximum iterations reached', $array['convergencereason']);
        $this->assertSame(40, $array['numresponses']);
        $this->assertContains('Item q1 (grm): large residual gradient', $array['warnings']);
    }
    /**
     * A queued adhoc calculation is reported as pending for its scale (issue #43).
     *
     * @covers \\local_catquiz\\local\\calculation\\calculation_service::get_pending_status
     * @return void
     */
    public function test_pending_status_detects_queued_calculation(): void {
        $this->resetAfterTest(true);
        $scaleid = 4242;
        $this->assertNull(calculation_service::get_pending_status($scaleid));

        $service = new calculation_service();
        $request = new calculation_request(
            $scaleid,
            999,
            calculation_mode::INCREMENTAL_RECALCULATION,
            calculation_trigger::MANUAL,
            0
        );
        $service->queue($request);

        $this->assertSame('pending', calculation_service::get_pending_status($scaleid));
        // A different scale is unaffected.
        $this->assertNull(calculation_service::get_pending_status($scaleid + 1));
    }
}
