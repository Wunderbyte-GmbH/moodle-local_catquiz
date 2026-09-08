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

namespace local_catquiz\local\calculation;

use core\lock\lock_config;
use InvalidArgumentException;
use Throwable;

/**
 * Stable central calculation service (issue #43).
 *
 * The single entry point for every calculation workflow. It validates the
 * request, prevents concurrent runs for the same scale via the Moodle Lock API,
 * selects the mode-specific orchestration strategy, runs it and returns a uniform
 * result. The scheduled task, the ad-hoc task and manual actions all call this
 * service; none of them contains its own IRT estimation logic.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class calculation_service {
    /** @var string Lock type for scale-level calculation locking. */
    private const LOCK_TYPE = 'local_catquiz_calculation';

    /**
     * Queues an ad-hoc task that runs the request out of the web request.
     *
     * The web request must only enqueue the task, never run the calculation
     * itself (issue #43). Returns the queued task's id (or null).
     *
     * @param calculation_request $request
     * @return int|null
     */
    public function queue(calculation_request $request): ?int {
        $task = new \local_catquiz\task\adhoc_calculation();
        $task->set_custom_data($request->to_array());
        if ($request->get_requestedby() > 0) {
            $task->set_userid($request->get_requestedby());
        }
        $id = \core\task\manager::queue_adhoc_task($task);
        return $id ?: null;
    }

    /**
     * Returns the strategy for a mode.
     *
     * @param string $mode One of calculation_mode::*.
     * @return calculation_strategy
     */
    public function get_strategy(string $mode): calculation_strategy {
        switch ($mode) {
            case calculation_mode::INCREMENTAL_RECALCULATION:
                return new incremental_recalculation();
            case calculation_mode::DISRUPTIVE_RECALCULATION:
                return new disruptive_recalculation();
            default:
                throw new InvalidArgumentException("calculation_service: no strategy for mode '{$mode}'.");
        }
    }

    /**
     * Executes a calculation request under a per-scale lock.
     *
     * @param calculation_request $request
     * @return calculation_result
     */
    public function execute(calculation_request $request): calculation_result {
        $locktype = self::LOCK_TYPE;
        $resource = 'scale_' . $request->get_scaleid();
        $lockfactory = lock_config::get_lock_factory($locktype);

        // Do not block: if another run holds the lock, report it and return.
        $lock = $lockfactory->get_lock($resource, 0);
        if ($lock === false) {
            $result = new calculation_result(
                $request->get_mode(),
                $request->get_scaleid(),
                $request->get_contextid()
            );
            $result->add_error('another calculation is already running for this scale');
            return $result->finish(calculation_result::STATUS_ERROR);
        }

        try {
            $strategy = $this->get_strategy($request->get_mode());
            $result = $strategy->execute($request);
        } catch (Throwable $e) {
            $result = new calculation_result(
                $request->get_mode(),
                $request->get_scaleid(),
                $request->get_contextid()
            );
            $result->add_error($e->getMessage());
            $result->finish(calculation_result::STATUS_ERROR);
        } finally {
            $lock->release();
        }

        $this->persist_summary($result);
        return $result;
    }

    /**
     * Persists a run summary and echoes it to the task/cron console.
     *
     * Uses the existing event infrastructure plus the Moodle config store for the
     * last-run summary per scale, so no new table is required.
     *
     * @param calculation_result $result
     * @return void
     */
    protected function persist_summary(calculation_result $result): void {
        // Last-run summary per scale (small, single JSON value; no new table).
        set_config(
            'lastcalculation_' . $result->get('scaleid'),
            json_encode($result->to_array()),
            'local_catquiz'
        );
        if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
            mtrace($result->to_console_line());
        }
    }

    /**
     * Returns the persisted last-run summary for a scale, or null.
     *
     * @param int $scaleid
     * @return calculation_result|null
     */
    public static function get_last_summary(int $scaleid): ?calculation_result {
        $json = get_config('local_catquiz', 'lastcalculation_' . $scaleid);
        if ($json === false || $json === null || $json === '') {
            return null;
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return null;
        }
        return calculation_result::from_array($data);
    }

    /**
     * Whether a calculation is currently pending or running for a scale (issue #43).
     *
     * Inspects the adhoc task queue for a queued adhoc_calculation task carrying the
     * given scale id. A task without a start time is pending; one with a start time
     * is running.
     *
     * @param int $scaleid
     * @return string|null 'running', 'pending' or null when nothing is queued
     */
    public static function get_pending_status(int $scaleid): ?string {
        global $DB;
        $records = $DB->get_records_select(
            'task_adhoc',
            $DB->sql_like('classname', ':cn'),
            ['cn' => '%adhoc_calculation']
        );
        foreach ($records as $record) {
            $data = json_decode($record->customdata, true);
            if (is_array($data) && (int) ($data['scaleid'] ?? 0) === $scaleid) {
                return empty($record->timestarted) ? 'pending' : 'running';
            }
        }
        return null;
    }
}
