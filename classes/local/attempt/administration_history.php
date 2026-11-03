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

use stdClass;

/**
 * Every administration of an item in an attempt, one entry per question usage slot (issue #125).
 *
 * The progress keeps played questions and responses keyed by question id. That is right for the
 * measurement - a question counts once - but it cannot record that the same question was
 * administered twice: the second administration collapses into the first and vanishes from any
 * history built on it. The question engine, however, keeps both. This reads them from there.
 *
 * Three views are kept strictly apart:
 *  - the audit history: every administered slot, a repeated question marked as a technical
 *    duplicate of the slot it first appeared in;
 *  - the measurement: each question once, from its first administration;
 *  - result validity: untouched. A technical duplicate is the system's mistake, not the test
 *    taker's, and is never a reason to reject an attempt.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class administration_history {
    /**
     * Returns every administration in an attempt of mod_adaptivequiz, in slot order.
     *
     * Each entry carries questionattemptid, slot, questionid, administeredat (time of the first
     * step), state (of the last step), fraction (of the last graded step, null if never graded),
     * ispilot, technicalduplicate and duplicateofslot. A reload of the active slot is the same
     * slot and therefore never a duplicate; only a further slot with the same question is.
     *
     * @param int $adaptiveattemptid Id of the attempt in adaptivequiz_attempt.
     * @param int[] $pilotquestionids Ids of questions played as pilot items in this attempt.
     * @return stdClass[] The administrations, keyed by slot.
     */
    public static function for_attempt(int $adaptiveattemptid, array $pilotquestionids = []): array {
        global $DB;

        $usageid = $DB->get_field('adaptivequiz_attempt', 'uniqueid', ['id' => $adaptiveattemptid]);
        if (!$usageid) {
            return [];
        }

        return self::for_usage((int) $usageid, $pilotquestionids);
    }

    /**
     * Returns every administration in a question usage, in slot order.
     *
     * @param int $usageid Id of the question usage.
     * @param int[] $pilotquestionids Ids of questions played as pilot items.
     * @return stdClass[] The administrations, keyed by slot.
     */
    public static function for_usage(int $usageid, array $pilotquestionids = []): array {
        global $DB;

        $attempts = $DB->get_records(
            'question_attempts',
            ['questionusageid' => $usageid],
            'slot ASC',
            'id, slot, questionid'
        );
        if (!$attempts) {
            return [];
        }

        // The steps in one query; first, last and last graded step are picked in PHP, which keeps
        // the SQL the same on every database engine.
        [$insql, $params] = $DB->get_in_or_equal(array_keys($attempts), SQL_PARAMS_NAMED, 'qa');
        $steps = $DB->get_recordset_select(
            'question_attempt_steps',
            "questionattemptid $insql",
            $params,
            'questionattemptid ASC, sequencenumber ASC',
            'id, questionattemptid, sequencenumber, state, fraction, timecreated'
        );
        $first = [];
        $last = [];
        $graded = [];
        foreach ($steps as $step) {
            $qaid = (int) $step->questionattemptid;
            $first[$qaid] = $first[$qaid] ?? $step;
            $last[$qaid] = $step;
            if ($step->fraction !== null) {
                $graded[$qaid] = $step;
            }
        }
        $steps->close();

        $pilots = array_flip(array_map('intval', $pilotquestionids));
        $firstslotofquestion = [];
        $history = [];
        foreach ($attempts as $qa) {
            $qaid = (int) $qa->id;
            $slot = (int) $qa->slot;
            $questionid = (int) $qa->questionid;
            $original = $firstslotofquestion[$questionid] ?? null;
            if ($original === null) {
                $firstslotofquestion[$questionid] = $slot;
            }

            $history[$slot] = (object) [
                'questionattemptid' => $qaid,
                'slot' => $slot,
                'questionid' => $questionid,
                'administeredat' => isset($first[$qaid]) ? (int) $first[$qaid]->timecreated : null,
                'state' => isset($last[$qaid]) ? (string) $last[$qaid]->state : null,
                'fraction' => isset($graded[$qaid]) ? (float) $graded[$qaid]->fraction : null,
                'ispilot' => isset($pilots[$questionid]),
                'technicalduplicate' => $original !== null,
                'duplicateofslot' => $original,
            ];
        }

        return $history;
    }

    /**
     * Returns the administrations that count for the measurement: each question once.
     *
     * The first administration of a question is the one that counts. That is also what the
     * progress already does - it keeps the first response of a question and ignores later ones -
     * so the measurement and this view agree.
     *
     * @param stdClass[] $history As returned by for_attempt() or for_usage().
     * @return stdClass[] The first administration of each question, keyed by slot.
     */
    public static function counted_administrations(array $history): array {
        return array_filter($history, fn(stdClass $entry) => !$entry->technicalduplicate);
    }

    /**
     * Returns the technical duplicates in a history.
     *
     * @param stdClass[] $history As returned by for_attempt() or for_usage().
     * @return stdClass[] The repeated administrations, keyed by slot.
     */
    public static function technical_duplicates(array $history): array {
        return array_filter($history, fn(stdClass $entry) => $entry->technicalduplicate);
    }

    /**
     * Finds attempts in which a question was administered more than once.
     *
     * For diagnosing past attempts. Two steps rather than one query with a string aggregate: the
     * pairs are found with GROUP BY/HAVING, which every engine supports alike, and their slots
     * and question attempts are read afterwards.
     *
     * @param int|null $instanceid Restrict to one activity instance; null for all.
     * @return stdClass[] One entry per attempt and question: attemptid, userid, instance, usageid,
     *      questionid, slots, questionattemptids and states.
     */
    public static function find_duplicate_administrations(?int $instanceid = null): array {
        global $DB;

        $where = '';
        $params = [];
        if ($instanceid !== null) {
            $where = 'WHERE aa.instance = :instance';
            $params['instance'] = $instanceid;
        }

        $pairs = $DB->get_recordset_sql(
            "SELECT qa.questionusageid, qa.questionid, COUNT(qa.id) AS administrations
               FROM {question_attempts} qa
               JOIN {adaptivequiz_attempt} aa ON aa.uniqueid = qa.questionusageid
               $where
           GROUP BY qa.questionusageid, qa.questionid
             HAVING COUNT(qa.id) > 1",
            $params
        );

        $found = [];
        foreach ($pairs as $pair) {
            $attempt = $DB->get_record(
                'adaptivequiz_attempt',
                ['uniqueid' => $pair->questionusageid],
                'id, userid, instance'
            );
            $history = self::for_usage((int) $pair->questionusageid);
            $entries = array_values(array_filter(
                $history,
                fn(stdClass $entry) => $entry->questionid === (int) $pair->questionid
            ));
            $found[] = (object) [
                'attemptid' => (int) $attempt->id,
                'userid' => (int) $attempt->userid,
                'instance' => (int) $attempt->instance,
                'usageid' => (int) $pair->questionusageid,
                'questionid' => (int) $pair->questionid,
                'slots' => array_map(fn($e) => $e->slot, $entries),
                'questionattemptids' => array_map(fn($e) => $e->questionattemptid, $entries),
                'states' => array_map(fn($e) => $e->state, $entries),
            ];
        }
        $pairs->close();

        return $found;
    }
}
