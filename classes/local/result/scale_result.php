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

/**
 * Immutable per-scale result of a CAT attempt (Issue #7).
 *
 * Produced solely by {@see attempt_result_validator}. Feedback, persistence and
 * completion consume this object and MUST NOT re-derive validity themselves.
 *
 * Reporting and statistical validity are modelled separately (decision 8.1):
 * `reportable` is a display/configuration decision, `statisticallyvalid` is a
 * measurement-quality decision. The result validity used by completion is
 * `valid = primary && statisticallyvalid && measuredincurrentattempt` and does
 * NOT depend on reporting being enabled.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class scale_result {
    /** @var string Rejection: standard error above the configured maximum. */
    public const REASON_SE_MAX = 'se_max';

    /** @var string Rejection: standard error below the configured minimum. */
    public const REASON_SE_MIN = 'se_min';

    /** @var string Rejection: fewer graded, non-pilot items than required. */
    public const REASON_N_MIN = 'n_min';

    /** @var string Rejection: fraction rule not met (e.g. all-correct/all-wrong). */
    public const REASON_FRACTION = 'fraction';

    /** @var string Rejection: every productive item of the scale answered wrongly (fraction 0, issue #140). */
    public const REASON_FRACTION_ALL_INCORRECT = 'fraction_all_incorrect';

    /** @var string Rejection: every productive item of the scale answered correctly (fraction 1, issue #140). */
    public const REASON_FRACTION_ALL_CORRECT = 'fraction_all_correct';

    /** @var string Rejection: only the root scale, no reportable subscale. */
    public const REASON_ROOTONLY = 'rootonly';

    /** @var string Rejection: reporting disabled for the scale (display only). */
    public const REASON_REPORTING_DISABLED = 'reporting_disabled';

    /** @var string Rejection: the scale is hidden. */
    public const REASON_HIDDEN = 'hidden';

    /** @var string Rejection: the scale is not the primary/reported scale. */
    public const REASON_NOT_PRIMARY = 'not_primary';

    /** @var string Rejection: the value was not measured in the current attempt (carry-over only). */
    public const REASON_NOT_MEASURED = 'not_measured_in_current_attempt';

    /** @var string Rejection: the attempt ended with fewer answered productive items than the test's minimum (#141). */
    public const REASON_MIN_QUESTIONS = 'min_questions_not_reached';

    /**
     * @var string Rejection: the attempt administered more items than the test's maximum - the
     *      production case of 33 slots at a maximum of 25 (adaptivequizcatmodel_catquiz #8).
     */
    public const REASON_MAX_QUESTIONS_EXCEEDED = 'max_questions_exceeded';

    /**
     * Constructor.
     *
     * @param int $scaleid
     * @param float|null $score The ability estimate for the scale.
     * @param float|null $standarderror
     * @param int|null $n Number of graded, non-pilot items in this attempt for the scale.
     * @param float|null $fraction
     * @param bool $measuredincurrentattempt Whether the value was measured in this attempt (not carry-over only).
     * @param bool $statisticallyvalid Whether the measurement meets N/SE/fraction/structure rules.
     * @param bool $reportable Whether the scale may be shown to the participant (display/config).
     * @param bool $primary Whether the scale is the primary/reported scale.
     * @param bool $valid primary && statisticallyvalid && measuredincurrentattempt.
     * @param string[] $rejectionreasons Machine-readable reason codes (see REASON_* constants).
     */
    public function __construct(
        /** @var int Scale id. */
        public readonly int $scaleid,
        /** @var float|null Ability estimate. */
        public readonly ?float $score,
        /** @var float|null Standard error. */
        public readonly ?float $standarderror,
        /** @var int|null Number of graded, non-pilot items in this attempt. */
        public readonly ?int $n,
        /** @var float|null Fraction. */
        public readonly ?float $fraction,
        /** @var bool Whether the value was measured in the current attempt. */
        public readonly bool $measuredincurrentattempt,
        /** @var bool Whether the measurement is statistically valid. */
        public readonly bool $statisticallyvalid,
        /** @var bool Whether the scale may be shown to the participant. */
        public readonly bool $reportable,
        /** @var bool Whether the scale is the primary/reported scale. */
        public readonly bool $primary,
        /** @var bool Whether the scale result is valid for completion. */
        public readonly bool $valid,
        /** @var string[] Machine-readable rejection reason codes. */
        public readonly array $rejectionreasons
    ) {
    }

    /**
     * The reasons that say nothing about the measurement, only about how it is used: not the reported scale of the
     * attempt, reporting switched off, hidden. In the order the validator appends them (attempt_result_validator).
     *
     * @return string[]
     */
    public static function display_only_reasons(): array {
        return [self::REASON_REPORTING_DISABLED, self::REASON_HIDDEN, self::REASON_NOT_PRIMARY];
    }

    /**
     * Whether a stored row is a valid measurement of its scale (issue #150, re-audit 27.10.2026).
     *
     * isvalid is the verdict on the result of the attempt: primary, statistically valid and measured in it. A scale
     * that is measured validly but is not the reported one - the root scale under "lowest skill gap", every subscale
     * but one - is stored with isvalid = 0 and the reason not_primary. For the ability of the persons in a scale
     * (histograms, the peer comparison, the learning progress) such a value counts: the measurement is valid. A
     * statistical reason (SE, N, fraction, minimum or maximum of questions, root only) or a scale not measured in the
     * attempt keeps it out. A row without reasons that is not valid was written before the reasons were stored and
     * stays out.
     *
     * @param int $isvalid
     * @param string|null $validationstatus Comma-separated reasons (local_catquiz_personparams.validationstatus).
     * @return bool
     */
    public static function is_valid_measurement(int $isvalid, ?string $validationstatus): bool {
        if ($isvalid === 1) {
            return true;
        }
        $reasons = array_filter(array_map('trim', explode(',', (string) $validationstatus)), fn($r) => $r !== '');
        if (!$reasons) {
            return false;
        }
        return !array_diff($reasons, self::display_only_reasons());
    }

    /**
     * The same rule as is_valid_measurement(), as an SQL condition on a row of local_catquiz_personparams.
     *
     * The validator writes the reasons in a fixed order, so the stored strings of display reasons only are the
     * non-empty subsequences of display_only_reasons().
     *
     * @param string $alias Alias of local_catquiz_personparams.
     * @param string $prefix Prefix of the parameter names.
     * @return array [sql, params]
     */
    public static function valid_measurement_sql(string $alias, string $prefix): array {
        global $DB;
        $reasons = self::display_only_reasons();
        $strings = [];
        $count = count($reasons);
        for ($mask = 1; $mask < (1 << $count); $mask++) {
            $subset = [];
            foreach ($reasons as $i => $reason) {
                if ($mask & (1 << $i)) {
                    $subset[] = $reason;
                }
            }
            $strings[] = implode(',', $subset);
        }
        [$insql, $params] = $DB->get_in_or_equal($strings, SQL_PARAMS_NAMED, $prefix . 'vm');
        return ["($alias.isvalid = 1 OR $alias.validationstatus $insql)", $params];
    }
}
