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

namespace local_catquiz\local\monitoring;

use local_catquiz\teststrategy\progress;

/**
 * A read-only picture of a CAT attempt for live monitoring (issue #122).
 *
 * Consumers such as a statistics block get the state of a running attempt here, instead of
 * rebuilding the internal JSON of the progress. Nothing is written, cached or created. Whether the
 * caller may see a given person's attempt is the caller's decision; this class checks nothing.
 *
 * The two attempt ids are named apart on purpose: catattemptid is local_catquiz_attempts.id,
 * adaptiveattemptid is the attempt of the activity. The progress references the former.
 *
 * The progress may be gone for a finished attempt - retention removes it by configuration. For a
 * finished attempt the snapshot therefore carries what the CAT attempt row holds, and no progress
 * detail; final results belong to the persistent result structures, not here.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class attempt_snapshot {
    /**
     * Builds the snapshot of a CAT attempt.
     *
     * @param int $catattemptid Id in local_catquiz_attempts.
     * @return \stdClass|null Null if there is no such CAT attempt.
     */
    public static function for_cat_attempt(int $catattemptid): ?\stdClass {
        global $DB;

        $cat = $DB->get_record('local_catquiz_attempts', ['id' => $catattemptid]);
        if (!$cat) {
            return null;
        }

        $snapshot = (object) [
            'catattemptid' => (int) $cat->id,
            'adaptiveattemptid' => (int) $cat->attemptid,
            'userid' => (int) $cat->userid,
            'starttime' => (int) $cat->timecreated,
            'endtime' => empty($cat->endtime) ? null : (int) $cat->endtime,
            'running' => empty($cat->endtime),
            'hasprogress' => false,
            'progresstimemodified' => null,
            'numplayedquestions' => 0,
            'numansweredproductive' => 0,
            'playedquestionsbyscale' => [],
            'abilities' => [],
            'abilitytrace' => [],
            'lastquestion' => null,
            'lastquestionstartedat' => null,
            'responses' => [],
            'activescales' => [],
        ];

        $progress = progress::load_for_reading($catattemptid);
        if ($progress === null) {
            return $snapshot;
        }

        $snapshot->hasprogress = true;
        $snapshot->progresstimemodified = (int) $DB->get_field(
            'local_catquiz_progress',
            'timemodified',
            ['attemptid' => $catattemptid]
        ) ?: null;
        $snapshot->numplayedquestions = (int) $progress->get_num_playedquestions();
        $snapshot->numansweredproductive = $progress->get_num_answered_productive_questions();

        foreach ((array) $progress->get_playedquestions(true) as $scaleid => $questions) {
            $snapshot->playedquestionsbyscale[(int) $scaleid] = array_values(array_map(
                fn($q) => (int) (is_object($q) ? $q->id : $q),
                (array) $questions
            ));
        }
        foreach ((array) $progress->get_abilities() as $scaleid => $ability) {
            $snapshot->abilities[(int) $scaleid] = is_numeric($ability) ? (float) $ability : null;
        }
        $snapshot->abilitytrace = $progress->get_ability_trace();

        $last = $progress->get_last_question();
        if ($last) {
            $snapshot->lastquestion = (object) [
                'questionid' => (int) $last->id,
                'catscaleid' => isset($last->catscaleid) ? (int) $last->catscaleid : null,
                'ispilot' => !empty($last->is_pilot),
                'answered' => array_key_exists((int) $last->id, (array) $progress->get_responses()),
            ];
            // When the current item was handed out: the canonical start of the current step.
            $snapshot->lastquestionstartedat = isset($last->userlastattempttime) ? (int) $last->userlastattempttime : null;
        }
        foreach ((array) $progress->get_responses() as $questionid => $response) {
            $response = (array) $response;
            $snapshot->responses[(int) $questionid] = isset($response['fraction']) ? (float) $response['fraction'] : null;
        }
        $snapshot->activescales = array_map('intval', array_values((array) $progress->get_active_scales()));

        return $snapshot;
    }

    /**
     * Builds the snapshot for the attempt of an activity.
     *
     * @param int $adaptiveattemptid Id in adaptivequiz_attempt.
     * @return \stdClass|null Null if there is no CAT attempt for it.
     */
    public static function for_adaptive_attempt(int $adaptiveattemptid): ?\stdClass {
        $catattemptid = \local_catquiz\catquiz::get_cat_attempt_id($adaptiveattemptid, 'mod_adaptivequiz');

        return $catattemptid === null ? null : self::for_cat_attempt($catattemptid);
    }
}
