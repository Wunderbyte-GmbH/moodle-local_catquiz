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
use local_catquiz\local\result\attempt_result_validator;
use local_catquiz\local\result\personparam_repository;
use local_catquiz\teststrategy\progress;

/**
 * Authoritative, idempotent finaliser for a CATquiz attempt (Issue #5).
 *
 * This is the single place where a CATquiz attempt is turned from "running"
 * into "finished". It is invoked from the authoritative status change in
 * mod_adaptivequiz (adaptivequiz_complete_attempt() via the
 * post_complete_attempt_callback catmodel hook), so finalisation no longer
 * depends on the attempt-finished page being reached. It is safe to call more
 * than once: a second call on an already-finalised attempt is a no-op.
 *
 * The end time is taken from the adaptive quiz attempt's immutable
 * timefinished (passed in as $finishedat), never from a session cache and
 * never from time() during a running attempt.
 *
 * Issues #7 (central result validation) and #9 (per-attempt scale results and
 * carry-over) hook into the marked extension point below; Issue #8 will set
 * resultstatus/resultvalid on the adaptivequiz_attempt from here.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class attempt_finalizer {
    /**
     * Finalise the CATquiz attempt that belongs to the given adaptive quiz attempt.
     *
     * @param int $adaptiveattemptid The mod_adaptivequiz attempt id (adaptivequiz_attempt.id).
     * @param int $finishedat Authoritative completion timestamp (adaptivequiz_attempt.timefinished).
     * @param string $stopreason The reason the attempt was stopped (for later result status).
     * @return bool True if this call performed the finalisation, false if it was a no-op.
     */
    public static function finalize(int $adaptiveattemptid, int $finishedat, string $stopreason = ''): bool {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/local/catquiz/lib.php');

        // Locate the local CAT attempt row created during the attempt. If none
        // exists (for example an attempt that never produced a CAT row), there
        // is nothing to finalise.
        $catattempt = $DB->get_record('local_catquiz_attempts', ['attemptid' => $adaptiveattemptid]);
        if (!$catattempt) {
            return false;
        }

        /* A CAT attempt still marked running (issue #101) is one whose test ended before a single
           answer was processed - by the time limit, after too long a break, or by leaving right
           after the start. It is finalised like any other: a test that has ended is announced as
           ended, whether or not it produced a result. Finalisation needs the authoritative end
           time of the activity's attempt, which exists only once that attempt is complete, so a
           test that is still running never gets this far. */
        $endedwithoutanswer = (int) $catattempt->status === LOCAL_CATQUIZ_ATTEMPT_RUNNING;

        // Idempotency guard: a finalised attempt carries a non-empty endtime.
        // Re-running finalisation must not change the stored result. Removing
        // this guard is what the teeth test detects.
        if (!empty($catattempt->endtime)) {
            return false;
        }

        /* The end time is AUTHORITATIVE. In the normal flow $finishedat
           is the immutable timefinished stamped by adaptivequiz_complete_attempt().
           A missing or non-positive value means the caller reached finalisation
           without a completed attempt - previously this silently invented time()
           and persisted a fabricated end time, which is exactly what an
           authoritative timestamp must never be. Refuse to finalise instead and
           make the condition visible to developers. */
        if ($finishedat <= 0) {
            debugging(
                sprintf(
                    'local_catquiz: refusing to finalise attempt %d without an authoritative end time.',
                    $adaptiveattemptid
                ),
                DEBUG_DEVELOPER
            );
            return false;
        }

        $transaction = $DB->start_delegated_transaction();

        // The authoritative end time is stamped exactly once here; timecreated
        // is left untouched (it is only ever set on INSERT).
        $catattempt->endtime = $finishedat;
        // Take the final number of used test items from the adaptive quiz
        // attempt, which is authoritative at completion.
        $questionsattempted = $DB->get_field(
            'adaptivequiz_attempt',
            'questionsattempted',
            ['id' => $adaptiveattemptid]
        );
        if ($questionsattempted !== false) {
            $catattempt->number_of_testitems_used = (int) $questionsattempted;
        }
        if ($endedwithoutanswer) {
            // Ended without any processed answer: there is no result, and the row says so.
            $catattempt->status = LOCAL_CATQUIZ_ATTEMPT_ABORTED;
        }
        $catattempt->timemodified = time();
        $DB->update_record('local_catquiz_attempts', $catattempt);

        // Issue #7 + #9: validate the attempt result centrally and persist one
        // history row per successfully tested scale. This runs exactly once (the
        // idempotency guard above prevents re-finalisation) and inside the same
        // transaction as the end-time stamp, so the attempt becomes
        // Completed -> Validated -> Persisted atomically.
        $result = attempt_result_validator::validate($adaptiveattemptid);
        $contextid = ($catattempt->contextid !== null) ? (int) $catattempt->contextid : null;
        personparam_repository::save_attempt_result(
            (int) $catattempt->id,
            (int) $catattempt->userid,
            $contextid,
            $result
        );

        // Expose the validity verdict on the adaptivequiz attempt so
        // the completionvalidresult rule can query it. Guarded by a column check
        // so local_catquiz keeps working against an older mod_adaptivequiz that
        // does not yet have these fields.
        if ($DB->get_manager()->field_exists('adaptivequiz_attempt', 'resultvalid')) {
            $isvalid = $result->is_valid() ? 1 : 0;
            $DB->set_field('adaptivequiz_attempt', 'resultvalid', $isvalid, ['id' => $adaptiveattemptid]);
            $status = $isvalid ? 'valid' : 'invalid';
            $DB->set_field('adaptivequiz_attempt', 'resultstatus', $status, ['id' => $adaptiveattemptid]);
        }

        // Refresh the personparams "latest known state" snapshot only
        // now, and only for valid scales measured in this attempt - never from a
        // mere carry-over or an intermediate estimate.
        if ($contextid !== null) {
            $userid = (int) $catattempt->userid;

            // Phase 2: the pre-attempt person abilities, captured at the first
            // question before any during-attempt estimate was written. Lets us
            // restore a non-validly-measured scale to its exact prior state.
            $preattempt = [];
            $catattemptid = catquiz::get_cat_attempt_id($adaptiveattemptid, 'mod_adaptivequiz');
            if (
                $catattemptid !== null
                    && $DB->record_exists('local_catquiz_progress', ['attemptid' => $catattemptid])
            ) {
                try {
                    $preattempt = progress::load($adaptiveattemptid, 'mod_adaptivequiz', $contextid)
                        ->get_preattempt_abilities();
                } catch (\Throwable $e) {
                    $preattempt = [];
                }

                // The progress row is NOT removed here. Finalisation is
                // not the end of the request - the feedback path loads the progress
                // again afterwards, and a row deleted at this point made load() fall
                // through to create_new() without quiz settings, which fails with a
                // type error.
                //
                // In the data-sparing mode the scheduled task sweeps it instead,
                // which is a matter of minutes rather than of a single request.
            }

            foreach ($result->get_scale_results() as $scaleresult) {
                $scaleid = $scaleresult->scaleid;
                if ($scaleresult->valid && $scaleresult->score !== null) {
                    /* Nothing to write here: personparam_repository::save_attempt_result() above
                       has already filed this scale, with the standard error, the item count and
                       the validation status that only it knows. A second write would append a
                       poorer duplicate of the same estimate. */
                    continue;
                }

                // Reconcile a scale that was NOT validly measured in this attempt
                // so an intermediate/invalid estimate does not survive as the
                // cross-attempt "latest known state" (the during-attempt preselect
                // tasks may have written one). Prefer the exact pre-attempt value
                // (Phase 2); fall back to the last valid history value (Phase 1);
                // otherwise leave it untouched.
                if (array_key_exists($scaleid, $preattempt)) {
                    // Carried over, not measured in this attempt - hence 'prior'.
                    catquiz::update_person_param(
                        $userid,
                        $contextid,
                        $scaleid,
                        (float) $preattempt[$scaleid],
                        (int) $catattempt->id,
                        true,
                        'prior'
                    );
                    continue;
                }

                $lastvalid = personparam_repository::get_latest_valid($userid, $contextid, $scaleid);
                if ($lastvalid !== null && $lastvalid->ability !== null) {
                    catquiz::update_person_param(
                        $userid,
                        $contextid,
                        $scaleid,
                        (float) $lastvalid->ability,
                        (int) $catattempt->id,
                        true,
                        'prior'
                    );
                }
            }
        }

        // The fields resultstatus and resultvalid are set on the adaptivequiz_attempt
        // above, which the completionvalidresult rule then consumes.
        unset($stopreason);

        $transaction->allow_commit();

        // Issue #122: the CAT attempt is finalised - announce it, and only now. After the commit,
        // so no listener sees a half-written result; here, and not on the result page, so it
        // happens whether or not anyone ever opens that page. The endtime guard above makes a
        // second finalize() return early, so an attempt is announced once.
        self::announce_completion($catattempt, $adaptiveattemptid, $finishedat, $result->is_valid());

        // Issue #129: enrolment on every way a test ends, once - not when the result page is built,
        // which may never happen. Only scales that route (valid, measured, selected) enrol anyone;
        // an invalid result enrols nobody.
        try {
            attempt_enrolment::enrol($adaptiveattemptid);
        } catch (\Throwable $e) {
            debugging('Enrolment after the attempt failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return true;
    }

    /**
     * Triggers the attempt_completed event for a finalised CAT attempt.
     *
     * @param \stdClass $catattempt The finalised row of local_catquiz_attempts.
     * @param int $adaptiveattemptid Id of the attempt in adaptivequiz_attempt.
     * @param int $finishedat When the attempt was finalised.
     * @param bool $isvalid Whether the result is valid.
     */
    private static function announce_completion(
        \stdClass $catattempt,
        int $adaptiveattemptid,
        int $finishedat,
        bool $isvalid
    ): void {
        global $DB;

        /* The module context of the activity, taken from the activity's attempt itself - not from
           local_catquiz_attempts through the context resolver. A CAT attempt is filed when the test
           starts, before course and instance are known; the resolver falls back to the system
           context for it and keeps that for the rest of the request. */
        $instanceid = (int) $DB->get_field('adaptivequiz_attempt', 'instance', ['id' => $adaptiveattemptid]);
        $cm = $instanceid ? get_coursemodule_from_instance('adaptivequiz', $instanceid, 0, false, IGNORE_MISSING) : false;
        $context = $cm ? \context_module::instance($cm->id) : \context_system::instance();

        try {
            \local_catquiz\event\attempt_completed::create([
                'objectid' => (int) $catattempt->id,
                'context' => $context,
                'userid' => (int) $catattempt->userid,
                'relateduserid' => (int) $catattempt->userid,
                'other' => [
                    'catattemptid' => (int) $catattempt->id,
                    'adaptiveattemptid' => $adaptiveattemptid,
                    'instanceid' => $instanceid,
                    'courseid' => $cm ? (int) $cm->course : (int) ($catattempt->courseid ?? 0),
                    'catscaleid' => (int) ($catattempt->scaleid ?? 0),
                    'teststrategy' => (int) ($catattempt->teststrategy ?? 0),
                    'resultvalid' => $isvalid ? 1 : 0,
                    'resultstatus' => $isvalid ? 'valid' : 'invalid',
                    'finishedat' => $finishedat,
                ],
            ])->trigger();
        } catch (\Throwable $e) {
            // The attempt is finalised and committed; a failing announcement must not turn that
            // into an error for the person who just finished the test.
            debugging('attempt_completed could not be triggered: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
