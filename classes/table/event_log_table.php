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

/**
 * Class event_log_table.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\table;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/../../lib.php');
require_once($CFG->libdir . '/tablelib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');

use local_catquiz\catquiz;
use local_wunderbyte_table\wunderbyte_table;

/**
 * Event log table class.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class event_log_table extends wunderbyte_table {
    /**
     * Overrides the output for this column.
     * @param object $values
     * @return string
     */
    public function col_eventname($values) {
        unset($values->id);
        unset($values->origin);
        unset($values->ip);
        unset($values->realuserid);
        unset($values->user);
        unset($values->uniqueid);
        unset($values->username);

        // Issue #79: a log row outlives its event class - moved to a subplugin or removed.
        $event = self::restore_event($values);
        if ($event) {
            try {
                return $event->get_name();
            } catch (\Throwable $e) {
                debugging('Event name failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
        return get_string('historicevent', 'local_catquiz', s((string) ($values->eventname ?? '')));
    }

    /**
     * Overrides the output for this column.
     * @param object $values
     * @return string
     */
    public function col_description($values) {
        unset($values->id);
        unset($values->origin);
        unset($values->ip);
        unset($values->realuserid);
        unset($values->user);
        unset($values->uniqueid);
        unset($values->username);

        $event = self::restore_event($values);
        if ($event) {
            try {
                return $event->get_description();
            } catch (\Throwable $e) {
                debugging('Event description failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
        return get_string('historiceventdescription', 'local_catquiz', (object) [
            'component' => s((string) ($values->component ?? '')),
            'action' => s((string) ($values->action ?? '')),
            'target' => s((string) ($values->target ?? '')),
        ]);
    }

    /**
     * The event of a log row, or null when its class no longer exists or cannot rebuild it (issue #79).
     *
     * @param object $values The row.
     * @return \core\event\base|null
     */
    private static function restore_event($values): ?\core\event\base {
        $class = $values->eventname ?? '';
        if (!is_string($class) || !class_exists($class) || !is_subclass_of($class, \core\event\base::class)) {
            return null;
        }
        try {
            return $class::restore((array) $values, []) ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * The context information column.
     *
     * @param object $values The row data.
     * @return string
     */
    public function col_timecreated($values) {
        return userdate($values->timecreated);
    }
}
