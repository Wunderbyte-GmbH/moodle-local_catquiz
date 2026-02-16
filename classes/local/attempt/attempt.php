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

use mod_adaptivequiz\event\attempt_completed;
use mod_adaptivequiz\local\attempt\attempt_state;
use context_module;
use stdClass;

/**
 * This class contains information about the attempt parameters
 *
 * @package    local_catquiz
 * @copyright  2013 onwards Remote-Learner {@link http://www.remote-learner.ca/}
 * @copyright  2022 onwards Vitaly Potenko <potenkov@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attempt {
    /**
     * Database table to store attempt state.
     */
    private const TABLE = 'adaptivequiz_attempt';

    /** @var int $userid user id */
    protected $userid;

    /** @var stdClass $adpqattempt object, properties come from the adaptivequiz_attempt table */
    protected $adpqattempt;

    /**
     * The constructor.
     * @param int $userid
     */
    private function __construct(int $userid) {
        $this->userid = $userid;
    }

    /**
     * Returns an attempt by its id.
     *
     * @param int $id
     * @return self
     */
    public static function get_by_id(int $id): self {
        global $DB;

        $record = $DB->get_record('adaptivequiz_attempt', ['id' => $id], '*', MUST_EXIST);

        $attempt = new self($record->userid);
        $attempt->adpqattempt = $record;

        return $attempt;
    }

    /**
     * Sets the attempt as complete.
     *
     * @param stdClass $adaptivequiz A record from the {adaptivequiz} table.
     * @param context_module $context
     * @param string $statusmessage
     * @param int $time Current timestamp.
     * @return void
     */
    public function complete(stdClass $adaptivequiz, context_module $context, string $statusmessage, int $time): void {
        // Need to keep the record as it is before triggering the event below.
        $attemptrecordsnapshot = clone $this->adpqattempt;

        $this->adpqattempt->attemptstate = attempt_state::COMPLETED;
        $this->adpqattempt->attemptstopcriteria = $statusmessage;
        $this->save($time);

        adaptivequiz_update_grades($adaptivequiz, $this->userid);

        $event = attempt_completed::create([
            'objectid' => $this->adpqattempt->id,
            'context' => $context,
            'userid' => $this->adpqattempt->userid,
        ]);
        $event->add_record_snapshot('adaptivequiz_attempt', $attemptrecordsnapshot);
        $event->add_record_snapshot('adaptivequiz', $adaptivequiz);
        $event->trigger();
    }

    /**
     * Saves the attempt in its current state to the database.
     *
     * @param int $time Current timestamp.
     * @return void
     */
    private function save(int $time): void {
        global $DB;

        $this->adpqattempt->timemodified = $time;

        $DB->update_record(self::TABLE, $this->adpqattempt);
    }
}
