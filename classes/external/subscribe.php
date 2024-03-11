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
use local_catquiz\subscription;
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
class subscribe extends external_api {

    /**
     * Describes the parameters for get_next_question webservice.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid'  => new external_value(PARAM_INT, 'userid', VALUE_REQUIRED),
            'area'  => new external_value(PARAM_TEXT, 'area', VALUE_REQUIRED),
            'itemid'  => new external_value(PARAM_INT, 'itemid', VALUE_REQUIRED),
            ]
        );
    }

    /**
     * Webservice for the local catquiz plugin to get next question.
     *
     * @param int $userid
     * @param string $area
     * @param int $itemid
     *
     * @return array
     */
    public static function execute(int $userid, string $area, int $itemid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'userid' => $userid,
            'area' => $area,
            'itemid' => $itemid,
        ]);

        global $USER;

        require_login();

        $context = context_system::instance();
        if (!has_capability('local/catquiz:canmanage', $context)) {
            throw new moodle_exception('norighttoaccess', 'local_catquiz');
        }

        if (empty($params['userid'])) {
            $params['userid'] = (int)$USER->id;
        }

        // The transformation of the userid will be done in the start_new_attempt function.
        return subscription::toggle_subscription($params['userid'], $params['area'], $params['itemid'], );
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'subscribed' => new external_value(PARAM_INT, '1 is subscribed, 0 is not.', VALUE_REQUIRED),
            ]
        );
    }
}
