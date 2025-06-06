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
 * The context_updated event.
 *
 * @package local_catquiz
 * @copyright 2024 Georg Maißer, <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\event;

use html_writer;
use moodle_url;

/**
 * The context_updated event class.
 *
 * @property-read array $other { Extra information about event. Acesss an instance of the booking module }
 * @copyright 2024 Georg Maißer
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class context_updated extends catquiz_event_base {

    /**
     * Init parameters.
     *
     * @return void
     *
     */
    protected function init() {
        $this->data['crud'] = 'u'; // Meaning: u = update.
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_catquiz_catcontext';
    }

    /**
     * Get name.
     *
     * @return string
     *
     */
    public static function get_name() {
        return get_string('context_updated', 'local_catquiz');
    }

    /**
     * Get description.
     *
     * @return string
     *
     */
    public function get_description() {
        $data = $this->data;
        $other = $this->get_other_data();
        $contextnamelink = html_writer::link('local/catquiz/manage_catcontexts.php', $other->contextname);
        return get_string('update_context_description', 'local_catquiz', $contextnamelink);
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
