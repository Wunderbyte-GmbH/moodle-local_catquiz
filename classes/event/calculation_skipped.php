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
 * The calculation_skipped event.
 * @package local_catquiz
 * @copyright 2024 Wunderbyte <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\event;

use local_catquiz\catcontext;
use local_catquiz\catscale;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * The calculation_skipped event class.
 *
 * @property-read array $other { Extra information about event. Access an instance of the booking module }
 * @copyright 2024 Wunderbyte <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class calculation_skipped extends catquiz_event_base {
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
        return get_string('calculation_skipped', 'local_catquiz');
    }

    /**
     * Get description first for function than for specific activity set.
     *
     * @return string
     *
     */
    public function get_description() {
        $other = $this->get_other_data();
        $scaleid = $other->catscaleid;
        $catscale = catscale::return_catscale_object($scaleid);
        $context = catcontext::load_from_db($other->contextid);
        $data = (object) [
            'reason' => $other->reason,
            'scalename' => $catscale->name,
            'contextname' => $context->name,
        ];
        return get_string('noresponsestoestimatedesc', 'local_catquiz', $data);
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
