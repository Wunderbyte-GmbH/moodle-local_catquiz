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
 * This class creates a catscale for local_catquiz.
 *
 * @package    local_catquiz
 * @category   external
 * @copyright  2023 Wunderbyte GmbH <info@wunderbyte.at>
 * @author     David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_catquiz\external;

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use local_catquiz\data\dataapi;
use local_catquiz\data\catscale_structure;

/**
 * External Service for local catquiz.
 *
 * @package   local_catquiz
 * @copyright 2024 Wunderbyte GmbH {@link http://www.wunderbyte.at}
 * @author    Georg Maißer
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manage_catscale extends external_api {
    /**
     * Describes the parameters for manage_catscale webservice.
     *
     * @return external_function_parameters
     * @see external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
                [
                        'name' => new external_value(PARAM_TEXT, 'The name of the catscale', VALUE_REQUIRED),
                        'description' => new external_value(PARAM_RAW, 'The description of the catscale', VALUE_REQUIRED),
                        'action' => new external_value(PARAM_ALPHA, 'update or create', VALUE_REQUIRED),
                        'minscalevalue' => new external_value(PARAM_FLOAT, 'Min scale value', VALUE_OPTIONAL),
                        'maxscalevalue' => new external_value(PARAM_FLOAT, 'Max scale value', VALUE_OPTIONAL),
                        'parentid' => new external_value(PARAM_INT, 'The parent ID of the catscale', VALUE_DEFAULT, 0),
                        'id' => new external_value(PARAM_INT, 'The id of the catscale', VALUE_DEFAULT, 0),
                ]
        );
    }

    /**
     * Create the catscale.
     *
     * @param string $name
     * @param string $description
     * @param string $action
     * @param ?float $minscalevalue
     * @param ?float $maxscalevalue
     * @param ?int $parentid
     * @param ?int $id
     *
     * @return array
     */
    public static function execute(
                                string $name,
                                string $description,
                                string $action,
                                ?float $minscalevalue = null,
                                ?float $maxscalevalue = null,
                                ?int $parentid = null,
                                ?int $id = null
                                ): array {

        $params = self::validate_parameters(self::execute_parameters(), [
                'name' => $name,
                'description' => $description,
                'minscalevalue' => $minscalevalue,
                'maxscalevalue' => $maxscalevalue,
                'action' => $action,
                'parentid' => $parentid,
                'id' => $id,
        ]);
        $params['timecreated'] = time();
        $params['timemodified'] = time();
        // Create a new record in the local_catquiz_catscales table.
        $record = new catscale_structure($params);

        // Insert the record into the database.
        if ($action === 'create') {
            $id = dataapi::create_catscale($record);
        } else if ($action === 'update') {
            $id = dataapi::update_catscale($record);
        }

        // Return the ID of the newly created record.
        return ['id' => $id];
    }

    /**
     * Return value definintion
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure(
                [
                        'id' => new external_value(PARAM_INT, 'The ID of the newly created catscale'),
                ]
        );
    }
}
