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
 * Class cancel_expired_attempts.
 *
 * @package local_catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\task;

use cache_helper;
use context_module;
use dml_exception;
use local_catquiz\catquiz;
use local_catquiz\local\status;
use mod_adaptivequiz\local\attempt\attempt_state;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("$CFG->dirroot/mod/adaptivequiz/locallib.php");

/**
 * Cancels open CAT quiz attempts that exceeded the timeout.
 *
 * @package local_catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cancel_expired_attempts extends \core\task\scheduled_task {
    /**
     * Allow some extra time before closing an expired attempt.
     * @var float
     */
    const BUFFER_TIME_FACTOR = 1.5;

    /**
     * Holds adaptivequiz records as stdClass entires.
     * @var array
     */
    private array $quizzes = [];

    /**
     * Holds the local_catquiz_tests of open attempts.
     * @var array
     */
    private array $maxtimepertest = [];

    /**
     * Current timestamp
     * @var int
     */
    private int $currenttime;

    /**
     * Default maximum time for attempts
     * @var int
     */
    private int $defaultmaxtime;

    /**
     * Returns task name.
     * @return string
     */
    public function get_name() {
        return get_string('cancelexpiredattempts', 'local_catquiz');
    }

    /**
     * Cancel expired quiz attempts.
     *
     * @return void
     */
    public function execute() {
        global $DB, $CFG;
        mtrace("Running cancel_expired_attempts task.");

        $this->initialize();

        $sql = <<<SQL
        SELECT aa.*
        FROM {adaptivequiz} a
        JOIN {adaptivequiz_attempt} aa
            ON a.id = aa.instance
        WHERE a.catmodel = 'catquiz'
            AND aa.attemptstate = :inprogress
        SQL;
        $params = ['inprogress' => attempt_state::IN_PROGRESS];
        if (!$records = $DB->get_records_sql($sql, $params)) {
            mtrace("No attempts are in progress. Exiting.");
            return;
        }

        $completed = 0;
        $statusmessage = get_string('attemptclosedbytimelimit', 'local_catquiz');
        require_once($CFG->dirroot . '/mod/adaptivequiz/locallib.php');
        foreach ($records as $record) {
            if (!$this->exceeds_maxtime($record)) {
                continue;
            }
            // One attempt that cannot be closed - a broken record, a missing course module - does not stop the
            // others (mod_adaptivequiz #5).
            try {
                $this->complete($record, $statusmessage);
                $completed++;
            } catch (\Throwable $e) {
                mtrace(sprintf('Attempt %d could not be closed: %s', (int) $record->id, $e->getMessage()));
            }
        }
        $duration = time() - $this->currenttime;
        mtrace(sprintf(
            'Processed %d open attempts in %d seconds and marked %d as completed',
            count($records),
            $duration,
            $completed
        ));
    }

    /**
     * Closes one attempt with the completion every other end uses.
     *
     * By its id where the activity offers that (mod_adaptivequiz #5): an attempt without a question has
     * uniqueid 0, and several of those cannot be told apart by it.
     *
     * @param stdClass $record adaptivequiz_attempt record.
     * @param string $statusmessage
     */
    private function complete(stdClass $record, string $statusmessage): void {
        $quiz = $this->get_adaptivequiz($record->instance);
        $cm = get_coursemodule_from_instance('adaptivequiz', $record->instance, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        // The code of mod_adaptivequiz's stop_reason::TIMELIMIT (issue #20), written out: an older activity has
        // neither the class nor the parameter and ignores it.
        if (function_exists('adaptivequiz_complete_attempt_by_id')) {
            adaptivequiz_complete_attempt_by_id((int) $record->id, $quiz, $context, $statusmessage, 'timelimit');
        } else {
            adaptivequiz_complete_attempt(
                (int) $record->uniqueid,
                $quiz,
                $context,
                (int) $record->userid,
                $statusmessage,
                'timelimit'
            );
        }
        catquiz::set_final_attempt_status($record->id, status::CLOSED_BY_TIMELIMIT);
        cache_helper::purge_by_event('changesinquizattempts');
    }

    /**
     * Initialize the task properties
     */
    private function initialize() {
        $this->currenttime = time();
        $this->defaultmaxtime = 60 * 60 * intval(get_config('local_catquiz', 'maximum_attempt_duration_hours') ?? 24);
        $this->load_max_times_per_test();
    }

    /**
     * Load maximum times for all tests
     */
    private function load_max_times_per_test() {
        global $DB;

        $records = $DB->get_records('adaptivequiz', ['catmodel' => 'catquiz']);
        $openinstances = array_map(fn($r) => $r->id, $records);

        if (!$openinstances) {
            return;
        }
        // The test rows of the activity component only (both spellings), the newest per instance: another
        // component's test with the same id lent its limit before (issue #104).
        [$insql, $params] = $DB->get_in_or_equal($openinstances, SQL_PARAMS_NAMED);
        $tests = $DB->get_records_select(
            'local_catquiz_tests',
            "componentid $insql AND component IN ('mod_adaptivequiz', 'adaptivequiz')",
            $params,
            'id ASC'
        );
        foreach ($tests as $tr) {
            // The same reading as the selection and the host (mod_adaptivequiz #5): an empty limit is none - it
            // used to become 60 minutes - and seconds are a unit, too.
            $limit = \local_catquiz\catquiz_handler::time_limits(json_decode((string) $tr->json) ?: null)['attempt'];
            $this->maxtimepertest[$tr->componentid] = $limit;
        }
    }

    /**
     * Checks whether the given attempt exceeds the max attempt time
     *
     * @param stdClass $record The attempt record
     * @return bool
     */
    public function exceeds_maxtime(stdClass $record): bool {
        // An activity without a limit of its own - no CAT settings, or none set - falls back to the default.
        $quizmaxtime = $this->maxtimepertest[$record->instance] ?? null;
        if ($quizmaxtime !== null) {
            // A limit of 0 is no limit.
            if ((int) $quizmaxtime === 0) {
                return false;
            }
            // The test's own limit, with a buffer for the last answer - not the default when that is longer: a
            // 30 minute test was closed only after a day (mod_adaptivequiz #5).
            return $this->currenttime - $record->timecreated > $quizmaxtime * self::BUFFER_TIME_FACTOR;
        }
        if ($this->defaultmaxtime === 0) {
            return false;
        }
        return $this->currenttime - $record->timecreated > $this->defaultmaxtime;
    }

    /**
     * Returns an adaptivequiz with the given ID.
     *
     * @param int $id
     * @return stdClass
     * @throws dml_exception
     */
    private function get_adaptivequiz(int $id): stdClass {
        global $DB;
        if (array_key_exists($id, $this->quizzes)) {
            return $this->quizzes[$id];
        }

        $adaptivequiz = $DB->get_record('adaptivequiz', ['id' => $id], '*', MUST_EXIST);
        $this->quizzes[$adaptivequiz->id] = $adaptivequiz;
        return $adaptivequiz;
    }
}
