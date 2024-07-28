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
 * The usertogroup_enroled event.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\event;

use moodle_url;

/**
 * The usertogroup_enroled event class.
 *
 * @property-read array $other
 * @copyright 2024 Wunderbyte
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class usertogroup_enroled extends catquiz_event_base {

    /**
     * Init parameters.
     *
     * @return void
     *
     */
    protected function init() {
        $this->data['crud'] = 'c'; // Meaning: c = create.
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'groups_members';
    }

    /**
     * Get name.
     *
     * @return string
     *
     */
    public static function get_name() {
        return get_string('usertogroup_enroled', 'local_catquiz');
    }

    /**
     * Get description.
     *
     * @return string
     *
     */
    public function get_description() {
        $other = $this->get_other_data();

        $stringdata['groupname'] = $other->groupname;
        $stringdata['coursename'] = $other->coursename;
        $stringdata['userid'] = $other->userid;
        $stringdata['courseurl'] = $other->courseurl;
        return get_string('usertogroup_enroled_description', 'local_catquiz', $stringdata);
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
