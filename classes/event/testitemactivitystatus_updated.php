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
 * The testitemactivitystatus_updated event.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\event;

use local_catquiz\catscale;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/catquiz/lib.php');


/**
 * The catscale_updated event class.
 *
 * @property-read array $other { Extra information about event. Acesss an instance of the booking module }
 * @copyright 2024 Wunderbyte <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class testitemactivitystatus_updated extends catquiz_event_base {

    /**
     * Init parameters.
     *
     * @return void
     *
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_catquiz_items';
    }

    /**
     * Get name.
     *
     * @return string
     *
     */
    public static function get_name() {
        return get_string('testitemactivitystatus_updated', 'local_catquiz');
    }

    /**
     * Get description first for function than for specific activity set.
     *
     * @return string
     *
     */
    public function get_description() {
        $data = $this->data;
        $other = $this->get_other_data();

        if (!empty($other->catscaleid) &&
            !empty($data['objectid']) &&
            !empty($other->context) &&
            !empty($other->component)
        ) {
            $linktotidetailview = catscale::get_link_to_testitem(
                $data['objectid'],
                $other->catscaleid,
                $other->context,
                $other->component);
        } else {
            $linktotidetailview = get_string('testitem', 'local_catquiz', $data['objectid']);
        }

        $data['testitemlink'] = $linktotidetailview;

        $testitemstring = get_string('update_testitem_activity_status', 'local_catquiz', $data);

        $activitystring = "";

        $activitystatus = $other->activitystatus;
        if (intval($activitystatus) == LOCAL_CATQUIZ_TESTITEM_STATUS_INACTIVE) {
            $activitystring = get_string('activitystatussetinactive', 'local_catquiz');
        } else if (intval($activitystatus) == LOCAL_CATQUIZ_TESTITEM_STATUS_ACTIVE) {
            $activitystring = get_string('activitystatussetactive', 'local_catquiz');
        }

        return $testitemstring . " " . $activitystring;
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
