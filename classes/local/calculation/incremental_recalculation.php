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

namespace local_catquiz\local\calculation;

use local_catquiz\catcontext;
use local_catquiz\catmodel_info;

/**
 * Incremental recalculation (issue #43).
 *
 * Uses the existing valid responses and the person parameters already stored in
 * the scale's context (treated as fixed). Runs a single item-parameter update
 * in place: no new context is created or activated, catscale.contextid is
 * unchanged, and person parameters are left untouched. This is the safe path the
 * scheduled task and the manual "recalculate" action both use.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class incremental_recalculation implements calculation_strategy {
    use identifiability_aware;

    /**
     * The mode this strategy implements.
     *
     * @return string
     */
    public function get_mode(): string {
        return calculation_mode::INCREMENTAL_RECALCULATION;
    }

    /**
     * Runs the incremental recalculation.
     *
     * @param calculation_request $request
     * @return calculation_result
     */
    public function execute(calculation_request $request): calculation_result {
        $result = new calculation_result(
            calculation_mode::INCREMENTAL_RECALCULATION,
            $request->get_scaleid(),
            $request->get_contextid()
        );

        $context = catcontext::load_from_db($request->get_contextid());
        $cmi = new catmodel_info();

        // Change detection before any mutation. No new responses => no-op.
        if (!$cmi->needs_update($context, $request->get_scaleid())) {
            $result->set('convergencereason', 'no new responses since last calculation');
            return $result->finish(calculation_result::STATUS_SKIPPED);
        }

        // Context-preserving in-place update: item params only, person params fixed.
        $summary = $cmi->update_params(
            $request->get_contextid(),
            $request->get_scaleid(),
            $request->get_requestedby(),
            true
        );

        // The incremental invariant: the active context is never switched.
        $result->set('targetcontextid', (int) ($summary['targetcontextid'] ?? $request->get_contextid()));
        $result->set('modelchanges', $summary['models']);
        $result->set('changeditems', array_sum($summary['models']));
        $this->apply_counts($result, $summary['counts'] ?? null);
        $this->apply_criteria($result, $summary);
        return $result->finish(calculation_result::STATUS_SUCCESS);
    }
}
