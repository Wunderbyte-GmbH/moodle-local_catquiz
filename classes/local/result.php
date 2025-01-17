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
 * Сlass result.
 *
 * @package    local_catquiz
 * @copyright  2025 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\local;

use Exception;
use local_catquiz\local\status;

/**
 * Provides methods to obtain results.
 *
 * @package    local_catquiz
 * @copyright  2025 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class result {
    /**
     * @var string status
     */
    protected string $status;
    /**
     * @var mixed value
     */
    protected $value;

    /**
     * Result-specific instantiation can go here.
     *
     * @param mixed|null $value
     * @param string $status
     *
     */
    public function __construct($value = null, string $status = status::OK) {
        $this->value  = $value;
        $this->status = $status;
    }

    /**
     * Returns error.
     *
     * @param string $status
     * @param mixed|null $value
     *
     * @return fail
     */
    public static function err(string $status = status::ERROR_GENERAL, $value = null): fail {
        return new fail($value, $status);
    }

    /**
     * Returns OK result.
     *
     * @param mixed|null $value
     *
     * @return success
     */
    public static function ok($value = null): success {
        return new success($value, status::OK);
    }

    /**
     * Calls the given callable if successful
     *
     * @return result
     */
    abstract public function and_then(callable $op): result;

    /**
     * Calls the given callable if not successful
     *
     * @return result
     */
    abstract public function or_else(callable $op): result;

    /**
     * Throws an exception if not successful
     *
     * @throws Exception
     */
    abstract public function expect(): result;

    /**
     * Returns status.
     *
     * @return string
     *
     */
    public function get_status() {
        return $this->status;
    }

    /**
     * Returns umwrapped value.
     *
     * @return mixed
     *
     */
    public function unwrap() {
        return $this->value;
    }

    /**
     * Returns is result OK.
     *
     * @return bool
     *
     */
    public function isok() {
        return $this->status === Status::OK;
    }

    /**
     * Returns is result error.
     *
     * @return bool
     *
     */
    public function iserr() {
        return $this->status !== Status::OK;
    }

    /**
     * Returns error message.
     *
     * @return string
     *
     */
    public function geterrormessage() {
        if ($this->isOk()) {
            throw new \moodle_exception("Trying to get the error message for a result that has no error.");
        }

        return get_string($this->get_status(), 'local_catquiz');
    }
}
