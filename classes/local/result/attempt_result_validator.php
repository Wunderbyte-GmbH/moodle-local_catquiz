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
     * @param array $fractionbyscale Optional map scaleid => fraction.
     * @param int|null $primaryscaleid When given, only this scale is primary;
     *        otherwise every 'toreport' scale is primary.
     * @return attempt_result
     */
    public static function from_personabilities(
        array $personabilities,
        array $sebyscale = [],
        array $nbyscale = [],
        array $fractionbyscale = [],
        ?int $primaryscaleid = null
    ): attempt_result {
        $results = [];

        foreach ($personabilities as $scaleid => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $scaleid = (int) $scaleid;
            $error = $entry['error'] ?? [];
            $reasons = [];

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

            // Measured in the current attempt (vs. carry-over only).
            $n = array_key_exists($scaleid, $nbyscale) ? (int) $nbyscale[$scaleid] : null;
            $measured = $n === null ? true : ($n > 0);
            if (!$measured) {
                $reasons[] = scale_result::REASON_NOT_MEASURED;
            }

            // Result validity for completion (decision 8.1: reporting excluded).
            $valid = $primary && $statisticallyvalid && $measured;

            $score = isset($entry['value']) ? (float) $entry['value'] : null;
            $se = array_key_exists($scaleid, $sebyscale) ? (float) $sebyscale[$scaleid] : null;
            $fraction = array_key_exists($scaleid, $fractionbyscale) ? (float) $fractionbyscale[$scaleid] : null;

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

        $personabilities = $data['personabilities_abilities']
            ?? $data['customscalefeedback_abilities']
            ?? [];
        if (!is_array($personabilities) || $personabilities === []) {
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
        if (!empty($catattempt->contextid)) {
            try {
                $progress = progress::load($adaptiveattemptid, 'mod_adaptivequiz', (int) $catattempt->contextid);
                foreach (array_keys($personabilities) as $scaleid) {
                    $nbyscale[(int) $scaleid] = $progress->get_num_answered_productive_questions((int) $scaleid);

                    // The share of points on the same population as N. It was never
                    // filled - validate() passed an empty array - so the fraction
                    // column of local_catquiz_attemptscale stayed null on every row
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
            $primaryscaleid
        );
    }
}
