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
 * @copyright  2023 Wunderbyte GmbH
 * @author     David Szkiba
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


declare(strict_types=1);

namespace local_catquiz\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use core\context\system as context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use invalid_parameter_exception;

/**
 * External service for submitting CatQuiz responses.
 *
 * @package    local_catquiz
 * @copyright  2024 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submit_responses extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'responses' => new external_multiple_structure(
                new external_single_structure([
                    'questionid' => new external_value(PARAM_INT, 'The ID of the question'),
                    'response' => new external_value(PARAM_RAW, 'The response data'),
                    'timestamp' => new external_value(PARAM_INT, 'Unix timestamp of the response'),
                ])
            ),
        ]);
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'Status of the submission'),
            'message' => new external_value(PARAM_TEXT, 'Status message'),
            'responses' => new external_multiple_structure(
                new external_single_structure([
                    'questionid' => new external_value(PARAM_INT, 'The ID of the question'),
                    'status' => new external_value(PARAM_BOOL, 'Individual response status'),
                    'message' => new external_value(PARAM_TEXT, 'Individual response message'),
                ])
            ),
        ]);
    }

    /**
     * Submit responses for CatQuiz.
     *
     * @param array $responses The array of response data
     * @return array The status and processed responses
     */
    public static function execute($responses) {
        // The $USER is the local user for whom the token was created.
        global $USER, $DB;

        // Parameter validation.
        $params = self::validate_parameters(self::execute_parameters(), ['responses' => $responses]);

        // Context validation.
        $context = context_system::instance();
        self::validate_context($context);

        // TODO: should we do an additional capability check?
        // Something like: require_capability('local/catquiz:submit_responses', $context); ?

        $results = [];
        $overallstatus = true;

        foreach ($params['responses'] as $response) {
            try {
                // Validate that the question exists.
                if (!$DB->record_exists('question', ['id' => $response['questionid']])) {
                    throw new invalid_parameter_exception('Invalid question ID: ' . $response['questionid']);
                }

                // Here you would process and store the response.
                // This is a placeholder for your actual response processing logic.
                $status = self::process_response($response);

                $results[] = [
                    'questionid' => $response['questionid'],
                    'status' => $status,
                    'message' => $status ? 'Success' : 'Failed to process response',
                ];

                if (!$status) {
                    $overallstatus = false;
                }
            } catch (\Exception $e) {
                $results[] = [
                    'questionid' => $response['questionid'],
                    'status' => false,
                    'message' => $e->getMessage(),
                ];
                $overallstatus = false;
            }
        }

        return [
            'status' => $overallstatus,
            'message' => $overallstatus ? 'All responses processed successfully' : 'Some responses failed',
            'responses' => $results,
        ];
    }

    /**
     * Process a single response.
     *
     * @param array $response The response data
     * @return bool Success status
     */
    private static function process_response($response) {
        global $DB, $USER;

        try {
            // Add your response processing logic here.
            // This is where you would store the response in your plugin's tables.
            // For example:
            $record = new \stdClass();
            $record->questionid = $response['questionid'];
            $record->response = $response['response'];
            $record->timestamp = $response['timestamp'];
            $record->userid = $USER->id;

            // Insert into your responses table.
            // $DB->insert_record('local_catquiz_responses', $record);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
