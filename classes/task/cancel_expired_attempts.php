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
use mod_adaptivequiz\local\attempt\attempt;
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
     *
     * @var array
     */
    private array $quizzes = [];

    /**
     * Holds the local_catquiz_tests of open attempts.
     *
     * @var array
     */
    private array $maxtimepertest = [];
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
        global $DB;
        mtrace("Running cancel_expired_attempts task.");

        // Get all catquiz attempts that are still in progress.
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

        // Get all local_catquiz_tests records that are used by open attempts.
        $openinstances = array_unique(
            array_map(
                fn($r) => $r->instance,
                $records
            )
        );
        // For each test, get the maximum time per attempt setting.
        foreach ($DB->get_records_list('local_catquiz_tests', 'componentid', $openinstances) as $tr) {
            $settings = json_decode($tr->json);
            // If this setting is not given, it is not limited on the quiz level.
            if (
                !property_exists($settings, 'catquiz_timelimitgroup')
                || !$settings->catquiz_timelimitgroup
            ) {
                $this->maxtimepertest[$tr->componentid] = null;
                continue;
            }

            // The max time per attempt can be given in minutes or hours. We convert it to seconds to
            // compare it to the current time.
            $maxtimeperattempt = $settings->catquiz_timelimitgroup->catquiz_maxtimeperattempt * 60;
            if ($settings->catquiz_timelimitgroup->catquiz_timeselect_attempt == 'h') {
                $maxtimeperattempt *= 60;
            }
            $this->maxtimepertest[$tr->componentid] = $maxtimeperattempt;
        }

        // For each record, check if the attempt is running longer than the default maximum time or the
        // maximum time defined by the quiz. If so, mark it as completed with the exceeded threshold state.
        $now = time();
        $defaultmaxtime = 60 * 60 * get_config('local_catquiz', 'maximum_attempt_duration_hours');
        $completed = 0;
        $statusmessage = get_string('attemptclosedbytimelimit', 'local_catquiz');
        foreach ($records as $record) {
            // If it is set on a quiz setting basis and not triggered, ignore the default setting.
            $quizmaxtime = $this->maxtimepertest[$record->instance];
            $exceedsmaxtime = $this->exceeds_maxtime($record->timecreated, $quizmaxtime, $defaultmaxtime, $now);
            if ($exceedsmaxtime) {
                $attempt = attempt::get_by_id($record->id);
                $quiz = $this->get_adaptivequiz($record->instance);
                $cm = get_coursemodule_from_instance('adaptivequiz', $record->instance);
                $context = context_module::instance($cm->id);
                $attempt->complete($quiz, $context, $statusmessage, $now);
                catquiz::set_final_attempt_status($record->id, status::CLOSED_BY_TIMELIMIT);
                cache_helper::purge_by_event('changesinquizattempts');
                $completed++;
            }
        }
        $duration = time() - $now;
        mtrace(sprintf(
            'Processed %d open attempts in %d seconds and marked %d as completed',
            count($records),
            $duration,
            $completed
        ));
    }

    /**
     * Checks whether the given attempt exceeds the max attempt time
     *
     * @param int $timecreated Timestamp when the attempt was created
     * @param ?int $quizmaxtime Maximum time specified by the quiz
     * @param int $defaultmaxtime Default maximum time
     * @param int $now
     * @return bool
     */
    public function exceeds_maxtime(int $timecreated, ?int $quizmaxtime, int $defaultmaxtime, int $now): bool {
        // Get the timeout that should be used.
        // If a timeout is set per quiz, use this. If not, fall back to the global default.
        $maxtime = $quizmaxtime ?? $defaultmaxtime;
        // The value 0 is treated as "no limit".
        if ($maxtime === 0) {
            return false;
        }
        return $now - $timecreated > ($maxtime * self::BUFFER_TIME_FACTOR);
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
