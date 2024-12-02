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
        global $DB;
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
        foreach ($records as $record) {
            if (!$this->exceeds_maxtime($record)) {
                continue;
            }
            $attempt = attempt::get_by_id($record->id);
            $quiz = $this->get_adaptivequiz($record->instance);
            $cm = get_coursemodule_from_instance('adaptivequiz', $record->instance);
            $context = context_module::instance($cm->id);
            $attempt->complete($quiz, $context, $statusmessage, $this->currenttime);
            catquiz::set_final_attempt_status($record->id, status::CLOSED_BY_TIMELIMIT);
            cache_helper::purge_by_event('changesinquizattempts');
            $completed++;
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
     * Initialize the task properties
     */
    private function initialize() {
        $this->currenttime = time();
        $this->defaultmaxtime = 60 * 60 * intval(get_config('local_catquiz', 'maximum_attempt_duration_hours'));
        $this->load_max_times_per_test();
    }

    /**
     * Load maximum times for all tests
     */
    private function load_max_times_per_test() {
        global $DB;

        $records = $DB->get_records('adaptivequiz', ['catmodel' => 'catquiz']);
        $openinstances = array_map(fn($r) => $r->id, $records);

        foreach ($DB->get_records_list('local_catquiz_tests', 'componentid', $openinstances) as $tr) {
            $settings = json_decode($tr->json);
            if (
                !property_exists($settings, 'catquiz_timelimitgroup')
                || !$settings->catquiz_timelimitgroup
            ) {
                $this->maxtimepertest[$tr->componentid] = null;
                continue;
            }

            $maxtimeperattempt = $settings->catquiz_timelimitgroup->catquiz_maxtimeperattempt * 60;
            if ($settings->catquiz_timelimitgroup->catquiz_timeselect_attempt == 'h') {
                $maxtimeperattempt *= 60;
            }
            $this->maxtimepertest[$tr->componentid] = $maxtimeperattempt;
        }
    }


    /**
     * Checks whether the given attempt exceeds the max attempt time
     *
     * @param stdClass $record The attempt record
     * @return bool
     */
    public function exceeds_maxtime(stdClass $record): bool {
        $quizmaxtime = $this->maxtimepertest[$record->instance];

        // If the maximum attempt time is set to 0, it means it has no limit.
        if ($quizmaxtime === 0) {
            return false;
        }
        if ($this->defaultmaxtime === 0) {
            return false;
        }
        $maxtime = max($quizmaxtime * self::BUFFER_TIME_FACTOR, $this->defaultmaxtime);
        return $this->currenttime - $record->timecreated > $maxtime;
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
