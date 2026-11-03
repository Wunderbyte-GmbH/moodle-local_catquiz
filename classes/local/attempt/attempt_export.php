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
use local_catquiz\local\access\context_resolver;
use local_catquiz\local\access\feedback_access;
use local_catquiz\local\result\personparam_repository;
use local_catquiz\teststrategy\progress;

/**
 * The export of one attempt: its state and the stored result of every scale (issue #120).
 *
 * Works for an invalid result as for a valid one and says which it is. Scale values come from the
 * result rows written at finalisation - ability, SE, N and fraction as they were, with the reasons
 * a scale was not valid. Nothing is computed afresh and nothing is filled in: an attempt that was
 * not finalised, or a scale without a value, leaves the cells empty.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class attempt_export {
    /** @var string The attempt is still running. */
    public const LIFECYCLE_RUNNING = 'running';

    /** @var string Completed with a valid result. */
    public const LIFECYCLE_COMPLETED_VALID = 'completed_valid';

    /** @var string Completed, but without a valid result. */
    public const LIFECYCLE_COMPLETED_INVALID = 'completed_invalid';

    /** @var string Ended before a single answer was processed. */
    public const LIFECYCLE_ABORTED = 'aborted';

    /** @var string Completed, but CATquiz has not judged the result (yet). */
    public const LIFECYCLE_NOT_FINALISED = 'completed_not_finalised';

    /** @var string[] Column names, in order. */
    public const COLUMNS = [
        'attemptid', 'userid', 'testid', 'timecreated', 'timefinished', 'attemptstate', 'lifecycle', 'stopreason',
        'resultstatus', 'resultvalid', 'resultreason', 'questionsattempted',
        'questionslots', 'duplicateslots', 'answeredquestions', 'answeredproductive',
        'scaleid', 'scalename', 'isprimary', 'ability', 'standarderror', 'n', 'fraction', 'isvalid', 'validationstatus',
    ];

    /**
     * Whether the current user may export this attempt: the rule of the export tab.
     *
     * The tab is teacher feedback, so the teacher capability is needed; on top of that the user must
     * be allowed to see this person's results (own attempt, or other users' results in the context).
     *
     * @param int $adaptiveattemptid adaptivequiz_attempt.id
     * @return bool
     */
    public static function can_export(int $adaptiveattemptid): bool {
        global $DB;

        $owner = $DB->get_field('adaptivequiz_attempt', 'userid', ['id' => $adaptiveattemptid]);
        if ($owner === false) {
            return false;
        }
        $context = context_resolver::for_attempt($adaptiveattemptid);
        return feedback_access::can_view_user($context, (int) $owner)
            && has_capability('local/catquiz:view_teacher_feedback', $context);
    }

    /**
     * The rows of the export: one per stored scale result, or one without scale values when there is none.
     *
     * @param int $adaptiveattemptid adaptivequiz_attempt.id
     * @return array[] Rows keyed by COLUMNS.
     */
    public static function rows(int $adaptiveattemptid): array {
        global $DB;

        $attempt = $DB->get_record('adaptivequiz_attempt', ['id' => $adaptiveattemptid], '*', MUST_EXIST);
        // Validated means: the finaliser wrote a status. Until then resultvalid only holds its default 0,
        // which would read as "invalid" for an attempt nobody has judged yet.
        $status = property_exists($attempt, 'resultstatus') ? $attempt->resultstatus : null;
        $validated = $status !== null && $status !== '';
        $base = [
            'attemptid' => (int) $attempt->id,
            'userid' => (int) $attempt->userid,
            'testid' => (int) $attempt->instance,
            'timecreated' => (int) $attempt->timecreated,
            'timefinished' => empty($attempt->timefinished) ? '' : (int) $attempt->timefinished,
            'attemptstate' => (string) $attempt->attemptstate,
            'lifecycle' => self::lifecycle($attempt, $validated),
            'stopreason' => (string) $attempt->attemptstopcriteria,
            'resultstatus' => $validated ? (string) $status : '',
            'resultvalid' => $validated ? (int) $attempt->resultvalid : '',
            'resultreason' => property_exists($attempt, 'resultreason') ? (string) $attempt->resultreason : '',
            'questionsattempted' => (int) $attempt->questionsattempted,
        ];
        $catattemptid = catquiz::get_cat_attempt_id($adaptiveattemptid, 'mod_adaptivequiz');
        $base += self::question_counts((int) $attempt->uniqueid, $catattemptid);
        $empty = array_fill_keys(array_slice(self::COLUMNS, count($base)), '');

        $results = $catattemptid === null ? [] : personparam_repository::get_for_attempt($catattemptid);
        if (!$results) {
            return [$base + $empty];
        }

        $names = $DB->get_records_list('local_catquiz_catscales', 'id', array_column($results, 'catscaleid'), '', 'id, name');
        $rows = [];
        foreach ($results as $result) {
            $rows[] = $base + [
                'scaleid' => (int) $result->catscaleid,
                'scalename' => isset($names[$result->catscaleid]) ? (string) $names[$result->catscaleid]->name : '',
                'isprimary' => (int) $result->isprimary,
                'ability' => self::number($result->ability),
                'standarderror' => self::number($result->standarderror),
                'n' => $result->n === null ? '' : (int) $result->n,
                'fraction' => self::number($result->fraction),
                'isvalid' => (int) $result->isvalid,
                'validationstatus' => (string) $result->validationstatus,
            ];
        }
        usort($rows, fn($a, $b) => [$b['isprimary'], $a['scaleid']] <=> [$a['isprimary'], $b['scaleid']]);
        return $rows;
    }

    /**
     * Where the attempt stands, in one word (issue #141).
     *
     * Running, completed with a valid result, completed without one, ended before a single answer,
     * or completed but not judged. A completed attempt is only called valid or invalid once
     * CATquiz has judged it; until then "invalid" would be a guess.
     *
     * @param \stdClass $attempt adaptivequiz_attempt record.
     * @param bool $validated Whether the finaliser wrote a result status.
     * @return string One of the LIFECYCLE_* constants.
     */
    public static function lifecycle(\stdClass $attempt, bool $validated): string {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/catquiz/lib.php');

        if ($attempt->attemptstate !== 'complete') {
            return self::LIFECYCLE_RUNNING;
        }
        // The status column also carries the stop codes of local\status, and 1 is "no remaining
        // questions" there as well: aborted is only an attempt without a single answer.
        $catstatus = $DB->get_field('local_catquiz_attempts', 'status', ['attemptid' => $attempt->id]);
        if ($catstatus !== false && (int) $catstatus === LOCAL_CATQUIZ_ATTEMPT_ABORTED && empty($attempt->questionsattempted)) {
            return self::LIFECYCLE_ABORTED;
        }
        if (!$validated) {
            return self::LIFECYCLE_NOT_FINALISED;
        }
        return empty($attempt->resultvalid) ? self::LIFECYCLE_COMPLETED_INVALID : self::LIFECYCLE_COMPLETED_VALID;
    }

    /**
     * What was asked and what counts (issues #141, #125/#126).
     *
     * The slots are every question the activity administered, a repeated one included; the answered
     * questions are those the CAT model counts - each once, pilot items included, which is what the
     * maximum limits; the productive ones are what the minimum and the result count. Empty, not zero,
     * where there is no usage or no progress to count from.
     *
     * @param int $usageid adaptivequiz_attempt.uniqueid
     * @param int|null $catattemptid local_catquiz_attempts.id
     * @return array
     */
    private static function question_counts(int $usageid, ?int $catattemptid): array {
        global $DB;

        $counts = ['questionslots' => '', 'duplicateslots' => '', 'answeredquestions' => '', 'answeredproductive' => ''];
        if ($usageid > 0) {
            $slots = $DB->count_records('question_attempts', ['questionusageid' => $usageid]);
            $distinct = (int) $DB->count_records_sql(
                'SELECT COUNT(DISTINCT questionid) FROM {question_attempts} WHERE questionusageid = :usageid',
                ['usageid' => $usageid]
            );
            $counts['questionslots'] = $slots;
            $counts['duplicateslots'] = $slots - $distinct;
        }
        $progress = $catattemptid === null ? null : progress::load_for_reading($catattemptid);
        if ($progress !== null) {
            $counts['answeredquestions'] = $progress->get_num_answered_questions();
            $counts['answeredproductive'] = $progress->get_num_answered_productive_questions();
        }
        return $counts;
    }

    /**
     * A stored number as it is, or empty when none was stored.
     *
     * @param mixed $value
     * @return float|string
     */
    private static function number($value) {
        return $value === null || $value === '' ? '' : (float) $value;
    }
}
