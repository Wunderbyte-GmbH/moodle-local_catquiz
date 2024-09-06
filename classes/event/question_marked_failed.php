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
 * The feedbacktab_clicked event.
 *
 * @package local_catquiz
 * @copyright 2024 Georg Maißer, <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\event;

/**
 * The feedbacktab_clicked event class.
 *
 * @copyright 2024 Georg Maißer
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_marked_failed extends catquiz_event_base {

    /**
     * Init parameters.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = parent::LEVEL_OTHER;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public static function get_name() {
        return 'question_mark_last_failed';
    }

    /**
     * Get description.
     *
     * @return string
     */
    public function get_description() {
        global $USER;
        if (!$other = $this->get_other_data()) {
            return 'No other data';
        }
        return sprintf('Question in usageid %d and slot %d marked as failed for user %d', $other->usageid, $other->slot, $USER->id);
    }

    /**
     * Get url.
     *
     * @return object
     */
    public function get_url() {
        return null;
    }
}

