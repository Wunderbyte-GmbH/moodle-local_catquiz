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

use stdClass;

/**
 * Whether the result of the global scale is valid as a grade (decision of 2026-10-14).
 *
 * Graded is the global scale alone. Its result is valid when it meets every criterion the test sets,
 * whatever the strategy reports first:
 *
 * - it was measured in the attempt (N > 0);
 * - N reaches the minimum number of questions per scale, applied to the global scale;
 * - N reaches the minimum number of questions of the test (every item of the test counts for the
 *   global scale, so its N is the number of productive answers);
 * - the standard error does not exceed the maximum standard error;
 * - the general fraction rule (issue #140): not every answer right, not every answer wrong. This rule
 *   belongs to the validity check in general and is to be negotiated again there.
 *
 * Not reported first, reporting switched off, hidden, the minimum standard error (a stopping criterion)
 * and reaching the maximum number of questions play no part. A criterion the test does not set does not
 * apply. Exceeding the maximum does (adaptivequizcatmodel_catquiz #8): it needs the question slots of the
 * attempt, which the stored row does not hold, so the reason the validation recorded with the row is kept.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class global_scale_validity {
    /**
     * Why the result of the global scale is not valid; empty when it is.
     *
     * @param stdClass $row local_catquiz_personparams of the global scale (n, standarderror, fraction), or the
     *      same fields from a prediction.
     * @param stdClass|null $quizsettings The settings of the test at the attempt, as its progress carries them;
     *      null applies no configured criterion.
     * @return string[] Reasons (scale_result::REASON_*).
     */
    public static function reasons(stdClass $row, ?stdClass $quizsettings): array {
        [$minperscale, $mintest, $semax] = self::criteria($quizsettings);
        $n = isset($row->n) && is_numeric($row->n) ? (int) $row->n : null;
        $se = isset($row->standarderror) && is_numeric($row->standarderror) ? (float) $row->standarderror : null;
        $fraction = isset($row->fraction) && is_numeric($row->fraction) ? (float) $row->fraction : null;

        $reasons = [];
        if ($n === null || $n <= 0) {
            $reasons[] = scale_result::REASON_NOT_MEASURED;
        }
        if ($minperscale > 0 && ($n ?? 0) < $minperscale) {
            $reasons[] = scale_result::REASON_N_MIN;
        }
        if ($mintest > 0 && ($n ?? 0) < $mintest) {
            $reasons[] = scale_result::REASON_MIN_QUESTIONS;
        }
        // Without a recorded standard error the maximum cannot be shown to be kept.
        if ($semax !== null && ($se === null || $se > $semax)) {
            $reasons[] = scale_result::REASON_SE_MAX;
        }
        $recorded = array_map('trim', explode(',', (string) ($row->validationstatus ?? '')));
        if (in_array(scale_result::REASON_MAX_QUESTIONS_EXCEEDED, $recorded, true)) {
            $reasons[] = scale_result::REASON_MAX_QUESTIONS_EXCEEDED;
        }
        if ($fraction !== null && ($n ?? 0) > 0) {
            if ($fraction <= 0.0) {
                $reasons[] = scale_result::REASON_FRACTION_ALL_INCORRECT;
            } else if ($fraction >= 1.0) {
                $reasons[] = scale_result::REASON_FRACTION_ALL_CORRECT;
            }
        }
        return $reasons;
    }

    /**
     * The criteria a test sets: minimum per scale, minimum of the test, maximum standard error.
     *
     * @param stdClass|null $quizsettings
     * @return array [int minimum per scale (0: none), int minimum of the test (0: none), float|null maximum SE]
     */
    public static function criteria(?stdClass $quizsettings): array {
        if ($quizsettings === null) {
            return [0, 0, null];
        }
        $perscale = (array) ($quizsettings->maxquestionsscalegroup ?? []);
        $test = (array) ($quizsettings->maxquestionsgroup ?? []);
        $se = (array) ($quizsettings->catquiz_standarderrorgroup ?? []);
        $semax = $se['catquiz_standarderror_max'] ?? null;
        return [
            max(0, (int) ($perscale['catquiz_minquestionspersubscale'] ?? 0)),
            max(0, (int) ($test['catquiz_minquestions'] ?? 0)),
            is_numeric($semax) && (float) $semax > 0 ? (float) $semax : null,
        ];
    }
}
