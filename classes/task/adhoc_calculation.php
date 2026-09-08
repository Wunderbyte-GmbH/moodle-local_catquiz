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

namespace local_catquiz\task;

use local_catquiz\local\calculation\calculation_request;
use local_catquiz\local\calculation\calculation_service;

/**
 * Ad-hoc task that runs a calculation request through the central service.
 *
 * Used by manual "recalculate" (incremental) and "recalculate anew" (disruptive)
 * actions from the CAT management area. The web request only queues this task;
 * the heavy calculation runs out of the web request (issue #43).
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class adhoc_calculation extends \core\task\adhoc_task {
    /**
     * Runs the requested calculation via the central service.
     *
     * @return void
     */
    public function execute() {
        $data = (array) $this->get_custom_data();
        $request = calculation_request::from_array($data);
        $service = new calculation_service();
        $service->execute($request);
    }
}
