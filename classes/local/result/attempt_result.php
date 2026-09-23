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
 * Immutable attempt-level result of a CAT attempt (Issue #7).
 *
 * Aggregates the per-scale {@see scale_result} objects and answers the two
 * questions all consumers need: which scales are reportable (display), and
 * whether the attempt has a valid result (completion). Produced solely by
 * {@see attempt_result_validator}.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class attempt_result {
    /**
     * Constructor.
     *
     * @param scale_result[] $scaleresults Per-scale results, indexed by scale id.
     */
    public function __construct(
        /** @var scale_result[] Per-scale results, indexed by scale id. */
        private readonly array $scaleresults
    ) {
    }

    /**
     * All per-scale results, indexed by scale id.
     *
     * @return scale_result[]
     */
    public function get_scale_results(): array {
        return $this->scaleresults;
    }

    /**
     * The result for a single scale, or null if the scale is not present.
     *
     * @param int $scaleid
     * @return scale_result|null
     */
    public function get_scale_result(int $scaleid): ?scale_result {
        return $this->scaleresults[$scaleid] ?? null;
    }

    /**
     * Whether the attempt has at least one valid primary scale.
     *
     * This is the single definition of attempt-level validity consumed by
     * completion (Issue #8). It does not depend on reporting being enabled
     * (decision 8.1).
     *
     * @return bool
     */
    public function is_valid(): bool {
        foreach ($this->scaleresults as $scaleresult) {
            if ($scaleresult->valid) {
                return true;
            }
        }
        return false;
    }

    /**
     * The primary valid scale result, or null when there is none.
     *
     * @return scale_result|null
     */
    public function get_primary_scale(): ?scale_result {
        foreach ($this->scaleresults as $scaleresult) {
            if ($scaleresult->valid) {
                return $scaleresult;
            }
        }
        return null;
    }

    /**
     * The scale ids that may be shown to the participant (reportable and
     * statistically valid). This reproduces the historical "reportable scales"
     * set and is the single source feedback assembly consumes (Issue #10).
     *
     * @return int[]
     */
    public function get_reportable_scale_ids(): array {
        $ids = [];
        foreach ($this->scaleresults as $scaleid => $scaleresult) {
            if ($scaleresult->reportable && $scaleresult->statisticallyvalid) {
                $ids[] = (int) $scaleid;
            }
        }
        return $ids;
    }

    /**
     * Whether at least one scale is reportable (display gate for feedback).
     *
     * @return bool
     */
    public function has_reportable_result(): bool {
        return $this->get_reportable_scale_ids() !== [];
    }
}
