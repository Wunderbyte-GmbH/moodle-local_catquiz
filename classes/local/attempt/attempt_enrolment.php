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

use local_catquiz\catquiz;
use local_catquiz\output\attemptfeedback;
use local_catquiz\teststrategy\progress;

/**
 * Enrols the participant into courses and groups when the attempt is finalised (issue #129).
 *
 * A test ends on many ways - the stopping criteria, no item left, a teacher closing the attempt,
 * the time limit, too long a break - and the result page may never be opened. Enrolment used to
 * happen when that page was built; now it happens once, at finalisation, on every way. The message
 * is stored with the CAT attempt, and the result page shows it.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class attempt_enrolment {
    /** @var string Key of the stored message in the JSON of the CAT attempt. */
    private const KEY = 'enrolmentmessage';

    /**
     * Enrols the owner of the attempt according to its final result and stores the message.
     *
     * @param int $adaptiveattemptid Id of the attempt of the component: adaptivequiz_attempt.id.
     * @return string The message for the participant, empty if there is none.
     */
    public static function enrol(int $adaptiveattemptid): string {
        global $DB;

        $catattemptid = catquiz::get_cat_attempt_id($adaptiveattemptid, 'mod_adaptivequiz');
        $attempt = $DB->get_record('adaptivequiz_attempt', ['id' => $adaptiveattemptid], 'id, userid, instance');
        if ($catattemptid === null || !$attempt) {
            return '';
        }
        $progress = progress::load_for_reading($catattemptid);
        if ($progress === null) {
            return '';
        }

        $scores = attemptfeedback::routing_scores($adaptiveattemptid);
        $message = '';
        if ($scores) {
            $quizsettings = (array) $progress->get_quiz_settings();
            $cm = get_coursemodule_from_instance('adaptivequiz', (int) $attempt->instance, 0, false, IGNORE_MISSING);
            $message = catquiz::enrol_user(
                $quizsettings,
                attemptfeedback::courses_to_enrol($quizsettings, $scores),
                attemptfeedback::groups_to_enrol($quizsettings, $scores),
                (int) $attempt->userid,
                $cm ? (int) $cm->course : null
            );
        }
        self::store($catattemptid, $message);

        return $message;
    }

    /**
     * Returns the message stored for an attempt.
     *
     * @param int $adaptiveattemptid Id of the attempt of the component: adaptivequiz_attempt.id.
     * @return string
     */
    public static function stored_message(int $adaptiveattemptid): string {
        global $DB;

        $json = $DB->get_field('local_catquiz_attempts', 'json', ['attemptid' => $adaptiveattemptid]);
        $data = json_decode((string) $json, true);

        return is_array($data) ? (string) ($data[self::KEY] ?? '') : '';
    }

    /**
     * Stores the message in the JSON of the CAT attempt.
     *
     * @param int $catattemptid Id of the CAT attempt: local_catquiz_attempts.id.
     * @param string $message
     */
    private static function store(int $catattemptid, string $message): void {
        global $DB;

        $data = json_decode((string) $DB->get_field('local_catquiz_attempts', 'json', ['id' => $catattemptid]), true);
        $data = is_array($data) ? $data : [];
        $data[self::KEY] = $message;
        $DB->set_field('local_catquiz_attempts', 'json', json_encode($data), ['id' => $catattemptid]);
    }
}
