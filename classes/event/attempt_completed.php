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
 * The attempt_completed event.
 *
 * @package local_catquiz
 * @copyright 2024 Georg Maißer, <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\event;

use local_catquiz\catscale;
use moodle_url;

/**
 * The attempt_completed event class.
 *
 * @property-read array $other { Extra information about event. Acesss an instance of the booking module }
 * @copyright 2024 Georg Maißer
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attempt_completed extends catquiz_event_base {
    /**
     * Init method.
     *
     * The event describes an attempt, so its object is the CAT attempt - not a CAT scale as it
     * used to be (issue #122).
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'local_catquiz_attempts';
    }

    /**
     * Get name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('attempt_completed', 'local_catquiz');
    }

    /**
     * Get description.
     *
     * @return string
     */
    public function get_description() {
        $other = $this->get_other_data();
        if (!$other) {
            return '';
        }

        $data = [
            'attemptid' => $other->adaptiveattemptid ?? $this->objectid,
            'userid' => $this->relateduserid ?? $this->userid,
            'catscalelink' => !empty($other->catscaleid) ? catscale::get_link_to_catscale((int) $other->catscaleid) : '',
        ];

        return get_string('complete_attempt_description', 'local_catquiz', $data);
    }

    /**
     * Returns the review page of the attempt.
     *
     * The page checks the reviewer's capability itself; the link grants nothing.
     *
     * @return moodle_url|null
     */
    public function get_url() {
        $other = $this->get_other_data();
        if (!$other || empty($other->adaptiveattemptid)) {
            return null;
        }

        return new moodle_url('/local/catquiz/show_attemptfeedback.php', ['attemptid' => $other->adaptiveattemptid]);
    }
}
