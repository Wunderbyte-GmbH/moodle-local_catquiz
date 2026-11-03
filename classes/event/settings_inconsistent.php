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
 * An attempt started with test settings that cannot be met (issue #60).
 *
 * Saving such settings is refused by the form; a template, an import or settings from before the check can
 * still hold them. The attempt goes on - the test ends as the runtime ends it - and this names why.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class settings_inconsistent extends \core\event\base {
    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventsettingsinconsistent', 'local_catquiz');
    }

    /**
     * Get description.
     *
     * @return string
     */
    public function get_description() {
        return sprintf(
            'Attempt %d of test %d started with settings that cannot be met: %s.',
            (int) ($this->other['attemptid'] ?? 0),
            (int) ($this->other['testid'] ?? 0),
            implode(', ', (array) ($this->other['codes'] ?? []))
        );
    }
}
