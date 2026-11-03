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

namespace local_catquiz\teststrategy;

/**
 * A reader asked for the progress of an attempt that has none - neither in the cache nor in the database (issue #149).
 *
 * A reader brings no test settings and must not create anything: before, progress::load() went on to create a
 * new progress with settings null, and the result page ended in a TypeError. Now it says what is missing.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class progress_missing_exception extends \moodle_exception {
    /**
     * Constructor.
     *
     * @param int $adaptiveattemptid Id of the attempt of the component.
     * @param string $component
     */
    public function __construct(int $adaptiveattemptid, string $component) {
        parent::__construct('progressmissing', 'local_catquiz', '', null, "$component attempt $adaptiveattemptid");
    }
}
