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

/**
 * Orchestration strategy for one calculation mode (issue #43).
 *
 * A strategy encapsulates the mode-specific orchestration (which start
 * parameters, how many estimation steps, persistence, context handling). It uses
 * the existing model/estimator/codec layer and never adds a workflow-specific
 * public API to catcalc or the model classes.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface calculation_strategy {
    /**
     * The mode this strategy implements.
     *
     * @return string One of calculation_mode::*.
     */
    public function get_mode(): string;

    /**
     * Runs the calculation for the given request.
     *
     * @param calculation_request $request
     * @return calculation_result
     */
    public function execute(calculation_request $request): calculation_result;
}
