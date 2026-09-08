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
}
