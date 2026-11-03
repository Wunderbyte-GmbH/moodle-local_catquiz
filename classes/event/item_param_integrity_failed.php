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

namespace local_catquiz\event;

/**
 * Items of a CAT context point to active parameters they may not use and were left out of the pool (issue #157).
 *
 * For the CAT manager: the context, the number per kind and some item ids. No person, no parameter values.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class item_param_integrity_failed extends \core\event\base {
    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventitemparamintegrityfailed', 'local_catquiz');
    }

    /**
     * Get description.
     *
     * @return string
     */
    public function get_description() {
        $o = $this->other;
        return sprintf(
            '%d item(s) of CAT context %d point to active parameters they may not use (%s) and were left out of the '
                . 'item pool. Items, for example: %s.',
            (int) ($o['count'] ?? 0),
            (int) ($o['catcontextid'] ?? 0),
            json_encode($o['bykind'] ?? []),
            json_encode($o['sample'] ?? [])
        );
    }
}
