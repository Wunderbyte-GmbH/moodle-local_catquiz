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
 * The testiteminscale_updated event.
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
class testitemstatus_updated extends catquiz_event_base {

    /**
     * Init parameters.
     *
     * @return void
     *
     */
    protected function init() {
        $this->data['crud'] = 'u'; // Meaning: u = update.
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
        return get_string('testitemstatus_updated', 'local_catquiz');
    }

    /**
     * Get description first for function than for specific activity set.
     *
     * @return string
     *
     */
    public function get_description() {
        $data = $this->data;
        $otherarray = json_decode($data['other']);

        $testitemid = $data['objectid'];
        if (!empty($otherarray->catscaleid) &&
            !empty($otherarray->context) &&
            !empty($otherarray->component)
        ) {
            $linktotidetailview = catscale::get_link_to_testitem(
                $testitemid,
                $otherarray->catscaleid,
                $otherarray->context,
                $otherarray->component);
        } else {
            $linktotidetailview = get_string('testitem', 'local_catquiz', $testitemid);
        }
        $data['testitemlink'] = $linktotidetailview;

        // If we have information about the testitem and it's status, we display it.
        if (!empty($otherarray->status)) {
            $statusint = $otherarray->status;
            $string = 'itemstatus_'.$statusint;

            $statusstring = get_string($string, 'local_catquiz');
            $data['statusstring'] = $statusstring;

            $message = get_string('testitem_status_updated_description', 'local_catquiz', $data);
            return  $message;
        } else {
            return $this->get_name();
        }
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
