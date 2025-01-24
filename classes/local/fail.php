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
 * Сlass fail.
 *
 * @package    local_catquiz
 * @copyright  2025 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\local;

use Exception;

/**
 * Overrides abstract methods from result
 *
 * @package    local_catquiz
 * @copyright  2025 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fail extends result {
    /**
     * Just returns the current result
     *
     * @param callable $op
     *
     * @return result
     */
    public function and_then(callable $op): result {
        return $this;
    }

    /**
     * Calls the given callable
     *
     * @param callable $op
     *
     * @return result
     */
    public function or_else(callable $op): result {
        return $op($this);
    }

    /**
     * Throws an exception
     *
     * @return result
     *
     * @throws Exception
     */
    public function expect(): result {
        throw new Exception($this->geterrormessage());
    }
}
