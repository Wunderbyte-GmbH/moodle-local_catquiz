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

use local_catquiz\catmodel_info;
use local_catquiz\data\dataapi;
use Throwable;

/**
 * Disruptive recalculation (issue #43).
 *
 * Recomputes person and item parameters iteratively (via the existing
 * model_strategy estimation loop), persists the result into a new context, and
 * activates that context only after a successful, fully persisted run. A failed
 * or aborted run leaves the previously active context untouched.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class disruptive_recalculation implements calculation_strategy {
    use identifiability_aware;

    /**
     * The mode this strategy implements.
     *
     * @return string
     */
    public function get_mode(): string {
        return calculation_mode::DISRUPTIVE_RECALCULATION;
    }

    /**
     * Runs the disruptive recalculation.
     *
     * @param calculation_request $request
     * @return calculation_result
     */
    public function execute(calculation_request $request): calculation_result {
        $result = new calculation_result(
            calculation_mode::DISRUPTIVE_RECALCULATION,
            $request->get_scaleid(),
            $request->get_contextid()
        );

        $cmi = new catmodel_info();
        try {
            // Iterative PP/IP estimation into a fresh (not yet active) context.
            $summary = $cmi->update_params(
                $request->get_contextid(),
                $request->get_scaleid(),
                $request->get_requestedby(),
                false
            );
        } catch (Throwable $e) {
            // A failed run must not replace the active context.
            $result->add_error($e->getMessage());
            $result->set('convergencereason', 'aborted with error; active context unchanged');
            return $result->finish(calculation_result::STATUS_ERROR);
        }

        $targetcontextid = (int) ($summary['targetcontextid'] ?? 0);
        if ($targetcontextid <= 0 || $targetcontextid === $request->get_contextid()) {
            // No new context was produced (e.g. no responses); nothing to activate.
            $result->set('convergencereason', 'no new context produced');
            return $result->finish(calculation_result::STATUS_SKIPPED);
        }

        // Activate the new context only now, after a successful, persisted run.
        $catscale = (object) [
            'id' => $request->get_scaleid(),
            'contextid' => $targetcontextid,
            'timemodified' => time(),
        ];
        dataapi::update_catscale($catscale);

        $result->set('targetcontextid', $targetcontextid);
        $result->set('modelchanges', $summary['models']);
        $result->set('changeditems', array_sum($summary['models']));
        $this->apply_counts($result, $summary['counts'] ?? null);
        $this->apply_criteria($result, $summary);
        return $result->finish(calculation_result::STATUS_SUCCESS);
    }
}
