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
 * The alise debug event.
 *
 * @package local_catquiz
 * @copyright 2024 Georg Maißer, <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\event;

use context_system;

/**
 * The alise_debug_event class.
 *
 * @property-read array $other { Extra information about event. Acesss an instance of the booking module }
 * @copyright 2024 Georg Maißer
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class alise_debug_event extends catquiz_event_base {

    /**
     * @var string
     */
    const NAME = 'alise debug event';

    /**
     * Init parameters.
     *
     * @return void
     *
     */
    protected function init() {
        $this->data['crud'] = 'c'; // Meaning: c = create.
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Get name.
     *
     * @return string
     *
     */
    public static function get_name() {
        return self::NAME;
    }

    /**
     * Get description.
     *
     * @return string
     *
     */
    public function get_description() {
        $other = $this->get_other_data();
        return sprintf('In %d: %s', $other->attemptid ?? 'unknown', $other->message);
    }

    /**
     * Get url.
     *
     * @return ?object
     */
    public function get_url() {
        null;
    }

    /**
     * Helper function to create and trigger an alise debug event
     *
     * @param int $attemptid
     * @param string $message
     * @return void
     */
    public static function log(int $attemptid, string $message) {
        $event = self::create(
            [
                'context' => context_system::instance(),
                'other' => [
                    'attemptid' => $attemptid,
                    'message' => $message,
                ],
            ]
        );
        $event->trigger();
    }
}
