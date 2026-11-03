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
 * A stored progress did not belong to its CAT attempt and was refused (issue #96).
 *
 * Diagnosis for operations: which progress, which CAT attempt, which person and component were
 * expected and found. The test state itself is not part of the event.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class progress_integrity_failed extends \core\event\base {
    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_catquiz_progress';
    }

    /**
     * Get name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventprogressintegrityfailed', 'local_catquiz');
    }

    /**
     * Get description.
     *
     * @return string
     */
    public function get_description() {
        $o = $this->other;
        return sprintf(
            'Progress %d was refused (%s): expected CAT attempt %d of user %s (%s), found user %d (%s).',
            (int) ($o['progressid'] ?? 0),
            (string) ($o['reason'] ?? ''),
            (int) ($o['expectedcatattemptid'] ?? 0),
            (string) ($o['expecteduserid'] ?? '-'),
            (string) ($o['expectedcomponent'] ?? '-'),
            (int) ($o['founduserid'] ?? 0),
            (string) ($o['foundcomponent'] ?? '')
        );
    }
}
