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

namespace local_catquiz\local\result;

use local_catquiz\teststrategy\feedbacksettings;
use local_catquiz\teststrategy\progress;

/**
 * The single place that decides the validity of a CAT attempt result (Issue #7).
 *
 * All consumers (feedback assembly, completion, persistence, statistics) obtain
 * an {@see attempt_result} from here and MUST NOT re-derive validity. The raw
 * per-scale inputs (ability, SE, N, fraction, exclusion flags) are still
 * produced by the strategy and feedbacksettings pipeline; this class is the
 * single interpreter that turns them into an authoritative, machine-readable
 * verdict.
 *
 * Reporting and statistical validity are kept separate (decision 8.1). The
 * historical "reportable scales" set (toreport, not excluded, not hidden) is
 * reproduced exactly by {@see attempt_result::get_reportable_scale_ids()}, so
 * routing the existing feedback gating through this validator is
 * behaviour-preserving.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class attempt_result_validator {
    /**
     * Builds an attempt result from an already-computed per-scale abilities
     * structure (the array feedback assembly works with). Pure and
     * persistence-independent, so it is fully unit-testable.
     *
     * @param array $personabilities Per-scale array with keys such as
     *        'value', 'error' (se|nminscale|fraction|rootonly|checkbox),
     *        'excluded', 'hidden', 'toreport'.
     * @param array $sebyscale Optional map scaleid => standard error.
     * @param array $nbyscale Optional map scaleid => number of graded, non-pilot
     *        items in this attempt. When given, a scale counts as measured in the
     *        current attempt only when its N is greater than zero.
     * @param array $fractionbyscale Optional map scaleid => mean fraction of the productive items
     *        answered in this attempt. For a scale measured in this attempt a fraction of 0 or 1
     *        makes the result statistically invalid (issue #140); a missing fraction applies no
     *        fraction rule.
     * @param int|null $primaryscaleid When given, only this scale is primary;
     *        otherwise every 'toreport' scale is primary.
     * @param int|null $answeredtotal Productive items answered in the whole attempt; null when unknown.
     * @param int $minquestions The test's minimum number of questions; 0 for none (issue #141).
     * @param int|null $administered Items administered in the attempt (question slots); null when unknown.
     * @param int $maxquestions The test's maximum number of questions; 0 for none.
     * @return attempt_result
     */
    public static function from_personabilities(
        array $personabilities,
        array $sebyscale = [],
        array $nbyscale = [],
        array $fractionbyscale = [],
        ?int $primaryscaleid = null,
        ?int $answeredtotal = null,
        int $minquestions = 0,
        ?int $administered = null,
        int $maxquestions = 0
    ): attempt_result {
        /* The minimum number of questions (issue #141), for the attempt as a whole. A test that
           ended before its minimum - after 3 of at least 12 items, say - has a running estimate,
           but no final result: every scale is invalid, whatever its own numbers look like. The
           estimate stays for diagnostics and the export. */
        $belowminimum = $minquestions > 0 && $answeredtotal !== null && $answeredtotal < $minquestions;
        /* The maximum (adaptivequizcatmodel_catquiz #8). Reaching it is a regular end; administering
           more is not - it happened before the host refused a slot beyond the maximum (33 slots at a
           maximum of 25: repetitions after pilot items). Such an attempt has no valid result. */
        $abovemaximum = $maxquestions > 0 && $administered !== null && $administered > $maxquestions;

        $results = [];

        foreach ($personabilities as $scaleid => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $scaleid = (int) $scaleid;
            $error = $entry['error'] ?? [];
            $reasons = [];

            // First: an attempt below the minimum has no result at all, and its reason is the one
            // to tell - not, say, "all answers correct" after three items.
            if ($belowminimum) {
                $reasons[] = scale_result::REASON_MIN_QUESTIONS;
            }
            if ($abovemaximum) {
                $reasons[] = scale_result::REASON_MAX_QUESTIONS_EXCEEDED;
            }

            // Measurement-quality (statistical) reasons.
            if (isset($error['se'])) {
                $reasons[] = isset($error['se']['semindefined'])
                    ? scale_result::REASON_SE_MIN
                    : scale_result::REASON_SE_MAX;
            }
            if (isset($error['nminscale'])) {
                $reasons[] = scale_result::REASON_N_MIN;
            }
            if (isset($error['fraction'])) {
                $reasons[] = scale_result::REASON_FRACTION;
            }
            if (isset($error['rootonly'])) {
                $reasons[] = scale_result::REASON_ROOTONLY;
            }

            // Measured in the current attempt (vs. carry-over only).
            $n = array_key_exists($scaleid, $nbyscale) ? (int) $nbyscale[$scaleid] : null;
            // Measured only when this attempt's N is known and positive (issue #128). An unknown N
            // used to count as measured, so a caller that passed no counts made every scale -
            // a value carried over from an earlier attempt included - look measured.
            $measured = $n !== null && $n > 0;
            $fraction = array_key_exists($scaleid, $fractionbyscale) && $fractionbyscale[$scaleid] !== null
                ? (float) $fractionbyscale[$scaleid]
                : null;

            /* The fraction rule (issue #140), here and nowhere else. With every productive item
               of a scale answered wrongly or every one answered correctly, the ability estimate
               sits at the edge of the range and says nothing reliable: the scale keeps its score,
               SE, N and fraction for the report but is statistically invalid. The strategies used
               to apply this rule on the fraction of the whole test, each in its own way, so a
               subscale answered entirely correctly inside a mixed test passed. The rule applies
               to measured scales only - with N = 0 the scale is "not measured" instead, and with
               no fraction at all no fraction rule applies. */
            if ($measured && $fraction !== null) {
                if ($fraction <= 0.0) {
                    $reasons[] = scale_result::REASON_FRACTION_ALL_INCORRECT;
                } else if ($fraction >= 1.0) {
                    $reasons[] = scale_result::REASON_FRACTION_ALL_CORRECT;
                }
            }

            /* 'excluded' now means exactly one thing: the measurement is unusable.
               The display decision "reporting switched off" arrives as its own flag
               (feedbacksettings::FIELD_NOTREPORTED), so the statistical check no
               longer has to compensate for a conflated flag by inspecting the error
               array. The checkbox error entry is still read for backwards
               compatibility with data written before the split. */
            $notreported = !empty($entry[feedbacksettings::FIELD_NOTREPORTED]) || isset($error['checkbox']);
            $excluded = !empty($entry['excluded']);
            $hidden = !empty($entry['hidden']);
            $toreport = !empty($entry['toreport']);

            /* Statistical validity: no measurement-quality reason and not excluded.
               Reporting being switched off never makes a result invalid. Data
               written BEFORE the flag split marks the reporting case as 'excluded'
               too, so an excluded scale that is only not-reported still counts as
               statistically valid - otherwise stored results would retroactively
               become invalid. */
            $statisticallyvalid = ($reasons === []) && !($excluded && !$notreported);

            // Reporting / display gate.
            $reportable = $toreport && !$hidden && !$notreported;
            if ($notreported) {
                $reasons[] = scale_result::REASON_REPORTING_DISABLED;
            }
            if ($hidden) {
                $reasons[] = scale_result::REASON_HIDDEN;
            }

            // Primary selection.
            $primary = $primaryscaleid !== null ? ($scaleid === $primaryscaleid) : $toreport;
            if (!$primary) {
                $reasons[] = scale_result::REASON_NOT_PRIMARY;
            }

            if (!$measured) {
                $reasons[] = scale_result::REASON_NOT_MEASURED;
            }

            // Result validity for completion (decision 8.1: reporting excluded).
            $valid = $primary && $statisticallyvalid && $measured;

            $score = isset($entry['value']) ? (float) $entry['value'] : null;
            $se = array_key_exists($scaleid, $sebyscale) ? (float) $sebyscale[$scaleid] : null;

            $results[$scaleid] = new scale_result(
                $scaleid,
                $score,
                $se,
                $n,
                $fraction,
                $measured,
                $statisticallyvalid,
                $reportable,
                $primary,
                $valid,
                array_values(array_unique($reasons))
            );
        }

        return new attempt_result($results);
    }

    /**
     * Validates the stored result of a completed attempt.
     *
     * Loads the per-scale abilities persisted with the attempt and the
     * per-scale item counts from the progress, then delegates to
     * {@see self::from_personabilities()}. Intended for completion (Issue #8)
     * and persistence (Issue #9); returns an empty result when no data is found.
     *
     * @param int $adaptiveattemptid The mod_adaptivequiz attempt id.
     * @return attempt_result
     */
    public static function validate(int $adaptiveattemptid): attempt_result {
        global $DB;

        $catattempt = $DB->get_record('local_catquiz_attempts', ['attemptid' => $adaptiveattemptid]);
        if (!$catattempt || empty($catattempt->json)) {
            return new attempt_result([]);
        }

        $data = json_decode($catattempt->json, true);
        if (!is_array($data)) {
            return new attempt_result([]);
        }

        /* The scales of the attempt: those the strategy reports, else every scale the feedback knows. An empty list of
           reported scales is no reason to persist nothing - all right or all wrong leaves every scale with an error,
           and the strategy then reports none (issue #106, E2E 27.10.2026). Before, `??` took the empty list, the
           attempt got no result row at all, and the estimates written during the attempt stayed the latest valid
           value of the person. */
        $personabilities = [];
        foreach (['personabilities_abilities', 'customscalefeedback_abilities'] as $key) {
            if (!empty($data[$key]) && is_array($data[$key])) {
                $personabilities = $data[$key];
                break;
            }
        }
        if ($personabilities === []) {
            return new attempt_result([]);
        }

        $sebyscale = (isset($data['se']) && is_array($data['se'])) ? $data['se'] : [];

        /* Per-scale N must be the number of ANSWERED, non-pilot items of that scale.
           This used to call get_playedquestions(), which counts DISPLAYED items - so
           N could include a still unanswered pending item as well as pilot items,
           and an attempt could be declared valid on a too optimistic N. The comment
           here claimed the value was pilot-filtered while the code was not; the
           authoritative counter on progress now enforces both filters (Issue #7). */
        $nbyscale = [];
        $fractionbyscale = [];
        // Read without cache, ownership check or a context id (issue #128): finalisation may run in a
        // scheduled task, and a CAT attempt filed at the start has no context id yet. Without the
        // progress, N stays unknown and no scale counts as measured.
        $progress = progress::load_for_reading((int) $catattempt->id);
        if ($progress !== null) {
            try {
                foreach (array_keys($personabilities) as $scaleid) {
                    $nbyscale[(int) $scaleid] = $progress->get_num_answered_productive_questions((int) $scaleid);

                    // The share of points on the same population as N. It was never
                    // filled - validate() passed an empty array - so the fraction
                    // column of the attempt scale results stayed null on every row
                    // while the value was available all along.
                    $fraction = $progress->get_fraction_for_scale((int) $scaleid);
                    if ($fraction !== null) {
                        $fractionbyscale[(int) $scaleid] = $fraction;
                    }
                }
            } catch (\Throwable $e) {
                $nbyscale = [];
            }
        }

        // The strategy records its designated primary scale in the
        // stored feedback data. Delegate its id so only that scale drives the
        // completion verdict (from_personabilities marks every other reported
        // scale REASON_NOT_PRIMARY). Absent - e.g. attempts finalised before this
        // was persisted - keeps the historic $toreport fallback.
        $primaryscaleid = isset($data['primaryscale']['id'])
            ? (int) $data['primaryscale']['id']
            : null;

        return self::from_personabilities(
            $personabilities,
            $sebyscale,
            $nbyscale,
            $fractionbyscale,
            $primaryscaleid,
            $progress !== null ? $progress->get_num_answered_productive_questions() : null,
            $progress !== null ? self::min_questions($progress) : 0,
            self::administered_items($adaptiveattemptid),
            self::max_questions($progress, $adaptiveattemptid)
        );
    }

    /**
     * The items administered in an attempt: the question slots of its question usage.
     *
     * @param int $adaptiveattemptid
     * @return int|null null when the attempt has no question usage.
     */
    public static function administered_items(int $adaptiveattemptid): ?int {
        global $DB;
        $uniqueid = $DB->get_field('adaptivequiz_attempt', 'uniqueid', ['id' => $adaptiveattemptid]);
        if (empty($uniqueid)) {
            return null;
        }
        return $DB->count_records('question_attempts', ['questionusageid' => $uniqueid]);
    }

    /**
     * The test's maximum number of questions: the CAT maximum the progress carries, else the activity's.
     *
     * @param progress|null $progress
     * @param int $adaptiveattemptid
     * @return int 0 when neither sets one.
     */
    public static function max_questions(?progress $progress, int $adaptiveattemptid): int {
        global $DB;
        if ($progress !== null) {
            $group = $progress->get_quiz_settings()->maxquestionsgroup ?? null;
            $max = is_array($group) ? ($group['catquiz_maxquestions'] ?? 0) : ($group->catquiz_maxquestions ?? 0);
            if ((int) $max > 0) {
                return (int) $max;
            }
        }
        $max = $DB->get_field_sql(
            'SELECT q.maximumquestions
               FROM {adaptivequiz_attempt} a
               JOIN {adaptivequiz} q ON q.id = a.instance
              WHERE a.id = :id',
            ['id' => $adaptiveattemptid]
        );
        return max(0, (int) $max);
    }

    /**
     * The test's minimum number of questions, as the progress of the attempt carries it.
     *
     * @param progress $progress
     * @return int 0 when the test sets none.
     */
    public static function min_questions(progress $progress): int {
        $group = $progress->get_quiz_settings()->maxquestionsgroup ?? null;
        $min = is_array($group) ? ($group['catquiz_minquestions'] ?? 0) : ($group->catquiz_minquestions ?? 0);
        return max(0, (int) $min);
    }
}
