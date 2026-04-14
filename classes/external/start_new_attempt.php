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
 * This class contains a list of webservice functions related to the catquiz Module by Wunderbyte.
 *
 * @package    local_catquiz
 * @copyright  2022 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_catquiz\external;

use context_system;
use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use local_catquiz\catquiz;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * External Service for local catquiz.
 *
 * @package   local_catquiz
 * @copyright 2024 Wunderbyte GmbH {@link http://www.wunderbyte.at}
 * @author    Georg Maißer
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class start_new_attempt extends external_api {

    /**
     * Describes the parameters for start_new_attempt webservice.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid'  => new external_value(PARAM_INT, 'userid', VALUE_REQUIRED),
            'categoryid'  => new external_value(PARAM_INT, 'categorid', VALUE_REQUIRED),
            ]
        );
    }

    /**
     * Webservice for the local catquiz plugin to start new attempt.
     *
     * @param int $userid
     * @param int $categoryid
     *
     * @return array
     */
    public static function execute(int $userid, int $categoryid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'userid' => $userid,
            'categoryid' => $categoryid,
        ]);

        require_login();

        $context = context_system::instance();
        if (!has_capability('local/catquiz:canaccess', $context)) {
            throw new moodle_exception('norighttoaccess', 'local_catquiz');
        }

        // The transformation of the userid will be done in the start_new_attempt function.
        return catquiz::start_new_attempt($params['userid'], $params['categorid']);
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'attemptid' => new external_value(PARAM_INT, 'Attempt id', VALUE_REQUIRED),
            ]
        );
    }
}
