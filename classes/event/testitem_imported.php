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
 * The testiteminscale_added event.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\event;
use moodle_url;

/**
 * The catscale_updated event class.
 *
 * @property-read array $other { Extra information about event. Acesss an instance of the booking module }
 * @copyright 2024 Wunderbyte <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class testitem_imported extends catquiz_event_base {

    /**
     * Init parameters.
     *
     * @return void
     *
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Get name.
     *
     * @return string
     *
     */
    public static function get_name() {
        return get_string('testitem_imported', 'local_catquiz');
    }

    /**
     * Get description.
     *
     * @return string
     *
     */
    public function get_description() {
        // Data is not used: $data = $this->data.
        $other = $this->get_other_data();
        $itemcount = $other->itemcount ?? 0;

        return get_string('imported_testitem_description', 'local_catquiz', $itemcount);
    }

    /**
     * Get url.
     *
     * @return object
     *
     */
    public function get_url() {
        return new moodle_url('');
    }
}
