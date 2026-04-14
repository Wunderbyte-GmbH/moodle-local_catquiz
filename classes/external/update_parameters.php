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
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_catquiz\external;

use Exception;
use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use local_catquiz\catmodel_info;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * External Service for local catquiz.
 *
 * @package   local_catquiz
 * @copyright 2024 Wunderbyte GmbH {@link http://www.wunderbyte.at}
 * @author    David Szkiba
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_parameters extends external_api {

    /**
     * Describes the parameters for update_parameters webservice.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid'  => new external_value(PARAM_INT, 'context ID'),
            'catscaleid' => new external_value(PARAM_INT, 'CAT scale ID'),
            ]
        );
    }

    /**
     * Webservice for the local catquiz plugin to update context parameters
     *
     * @param int $contextid
     * @param int $catscaleid
     *
     * @return array
     */
    public static function execute(int $contextid, int $catscaleid): array {
        self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'catscaleid' => $catscaleid,
        ]);

        $cm = new catmodel_info();
        try {
            $cm->trigger_parameter_calculation($contextid, $catscaleid);
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => '',
            ];
        }
        return [
            'success' => true,
            'message' => '',
        ];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Successful calculation', VALUE_REQUIRED),
            'message' => new external_value(PARAM_RAW, 'message if necessary', VALUE_OPTIONAL, ''),
            ]
        );
    }
}
