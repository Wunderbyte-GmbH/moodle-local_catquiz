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
 * Returns a monolog logger for catquiz
 *
 * In case that monolog is not enabled, it returns a dummy logger that does nothing.
 * @package    local_catquiz
 * @copyright  2024 Wunderbyte GmbH
 * @author     David Bogner, et al.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Psr\Log\LoggerInterface;
use local_catquiz\dummy_logger;

/**
 * Class logger
 *
 * @package    local_catquiz
 * @copyright  2024 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @author     David Szkiba
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class logger {
    /**
     * Static property that holds the logger.
     *
     * @var ?LoggerInterface $logger
     */
    protected static ?LoggerInterface $logger = null;

    /**
     * This is a singleton - make the constructor private.
     */
    private function __construct() {
    }

    /**
     * Returns a logger or null
     * @return MonologLogger
     */
    public static function get() {
        global $CFG;
        if (!$CFG->monolog) {
            return new dummy_logger();
        }
        require_once('/var/www/html/local/catquiz/vendor/autoload.php');
        if (!self::$logger) {
            self::$logger = new MonologLogger('catquiz');
            self::$logger->pushHandler(new StreamHandler($CFG->dirroot . '/local/catquiz/catquiz.log', MonologLogger::DEBUG));
        }
        return self::$logger;
    }
}
