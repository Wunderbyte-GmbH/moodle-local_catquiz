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

use local_catquiz\logger_interface;
use Monolog\Logger as MonologLogger;
use Psr\Log\LoggerInterface;
use local_catquiz\dummy_logger;
use Monolog\Handler\RotatingFileHandler;

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
     * If no log level is given, this is used.
     *
     * @var string
     */
    const DEFAULT_LEVEL = MonologLogger::ERROR;

    /**
     * Path to the logfile relative to the CFG->dirroot.
     *
     * @var string
     */
    const LOGFILE = '/local/catquiz/logs/catquiz.log';

    /**
     * Maximum number of log files
     *
     * @var int
     */
    const MAX_FILES = 20;

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
     * Returns a logger implementing logger_interface
     *
     * If monolog is not enabled, it returns a dummy logger that does nothing.
     * @return logger_interface
     */
    public static function get() {
        global $CFG;

        // If monolog is not set in the config.php, return a dummy logger.
        if (!property_exists($CFG, 'monolog') || !$CFG->monolog) {
            return new dummy_logger();
        }

        // Monolog is configured, so we can expect that it is installed.
        require_once($CFG->dirroot . '/local/catquiz/vendor/autoload.php');

        if (self::$logger) {
            return self::$logger;
        }

        // Allow overriding the default log level.
        $level = self::DEFAULT_LEVEL;
        if (property_exists($CFG, 'monolog_level')
            && in_array(strtoupper($CFG->monolog_level), array_keys(MonologLogger::getLevels()))) {
            $level = strtoupper($CFG->monolog_level);
        }

        $maxfiles = self::MAX_FILES;
        if (property_exists($CFG, 'monolog_max_files')
            && is_int($CFG->monolog_max_files)) {
            $maxfiles = intval($CFG->monolog_max_files);
        }

        if (!self::$logger) {
            self::$logger = new MonologLogger('catquiz');
            $filename = $CFG->dirroot . self::LOGFILE;
            self::$logger->pushHandler(new RotatingFileHandler($filename, $maxfiles, $level));
        }
        return self::$logger;
    }
}
