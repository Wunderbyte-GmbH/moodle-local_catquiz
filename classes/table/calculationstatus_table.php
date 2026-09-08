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

namespace local_catquiz\table;

use local_catquiz\local\calculation\calculation_mode;
use local_catquiz\local\calculation\calculation_service;
use local_wunderbyte_table\wunderbyte_table;
use moodle_url;
use stdClass;

/**
 * Per-scale CAT recalculation status shown as a wunderbyte_table (issue #43).
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class calculationstatus_table extends wunderbyte_table {
    /**
     * Format the scale name.
     *
     * @param stdClass $values
     * @return string
     */
    public function col_name(stdClass $values): string {
        return format_string($values->name);
    }

    /**
     * Show the last persisted run summary for the scale, if any.
     *
     * @param stdClass $values
     * @return string
     */
    public function col_lastcalculation(stdClass $values): string {
        $summary = calculation_service::get_last_summary((int) $values->id);
        return $summary ? $summary->to_console_line() : get_string('none');
    }

    /**
     * Show whether a calculation is currently running or pending for the scale.
     *
     * @param stdClass $values
     * @return string
     */
    public function col_status(stdClass $values): string {
        $status = calculation_service::get_pending_status((int) $values->id);
        if ($status === 'running') {
            return get_string('calculationrunning', 'local_catquiz');
        }
        if ($status === 'pending') {
            return get_string('calculationpending', 'local_catquiz');
        }
        return get_string('none');
    }

    /**
     * Render the trigger buttons (incremental always; disruptive if allowed).
     *
     * @param stdClass $values
     * @return string
     */
    public function col_action(stdClass $values): string {
        global $OUTPUT, $PAGE;

        $incrementalurl = new moodle_url('/local/catquiz/manage_calculation.php', [
            'scaleid' => $values->id,
            'mode' => calculation_mode::INCREMENTAL_RECALCULATION,
            'sesskey' => sesskey(),
        ]);
        $actions = $OUTPUT->single_button(
            $incrementalurl,
            get_string('startrecalculation', 'local_catquiz'),
            'get'
        );

        if (has_capability('local/catquiz:disruptiverecalculate', $PAGE->context)) {
            $disruptiveurl = new moodle_url('/local/catquiz/manage_calculation.php', [
                'scaleid' => $values->id,
                'mode' => calculation_mode::DISRUPTIVE_RECALCULATION,
                'sesskey' => sesskey(),
            ]);
            $actions .= $OUTPUT->single_button(
                $disruptiveurl,
                get_string('startdisruptive', 'local_catquiz'),
                'get'
            );
        }

        return $actions;
    }
}
