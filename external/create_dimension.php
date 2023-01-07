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
 * This class creates a dimension for local_catquiz.
 *
 * @package    local_catquiz
 * @category   external
 * @copyright  2023 Wunderbyte GmbH <info@wunderbyte.at>
 * @author     David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_catquiz\external;

defined('MOODLE_INTERNAL') || die();

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use local_catquiz\data\dataapi;
use local_catquiz\data\dimension_structure;

class create_dimension extends external_api {
    /**
     * @return external_function_parameters
     * @see external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
                array(
                        'name' => new external_value(PARAM_TEXT, 'The name of the dimension', VALUE_REQUIRED),
                        'description' => new external_value(PARAM_RAW, 'The description of the dimension', VALUE_REQUIRED),
                        'parentid' => new external_value(PARAM_INT, 'The parent ID of the dimension', VALUE_OPTIONAL, null)
                )
        );
    }

    /**
     * Create the dimension
     *
     * @param $name
     * @param $description
     * @param $parentid
     * @return array
     */
    public static function execute($name, $description, $parentid = null): array {

        $params = self::validate_parameters(self::execute_parameters(), [
                'name' => $name,
                'description' => $description,
                'parentid' => $parentid,
        ]);
        $params['timecreated'] = time();
        $params['timemodified'] = time();
        // Create a new record in the local_catquiz_dimensions table.
        $record = new dimension_structure($params);

        // Insert the record into the database.
        $id = dataapi::create_dimension($record);

        // Return the ID of the newly created record.
        return array('id' => $id);
    }

    /**
     * Return value definintion
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure(
                array(
                        'id' => new external_value(PARAM_INT, 'The ID of the newly created dimension')
                )
        );
    }
}