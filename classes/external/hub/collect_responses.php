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

namespace local_catquiz\external\hub;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use core\context\system as context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use invalid_parameter_exception;
use local_catquiz\event\responses_added;
use local_catquiz\remote\response\response_handler;
use UnexpectedValueException;
use webservice;

/**
 * External service for collecting responses.
 *
 * @package    local_catquiz
 * @copyright  2024 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class collect_responses extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'jsondata' => new external_value(PARAM_RAW, 'JSON encoded array of response data'),
            'sourceurl' => new external_value(PARAM_TEXT, 'The source URL as provided by the client'),
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
            'added' => new external_value(PARAM_INT, 'Responses that were newly added'),
            'skipped' => new external_value(PARAM_INT, 'Responses that were skipped because they were already present'),
            'errors' => new external_multiple_structure(
                new external_single_structure([
                    'questionhash' => new external_value(PARAM_TEXT, 'Hash of the question'),
                    'attempthash' => new external_value(PARAM_INT, 'The attempt hash'),
                    'message' => new external_value(PARAM_TEXT, 'Message that describes the error'),
                ])
            ),
        ]);
    }

    /**
     * Submit responses for CatQuiz.
     *
     * @param string $jsondata The response data as json-encoded string
     * @param string $sourceurl The source url
     * @return array The status and processed responses
     */
    public static function execute($jsondata, $sourceurl) {
        global $USER;

        // Parameter validation.
        // Decode the JSON string into an array.
        $decodeddata = json_decode($jsondata, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new invalid_parameter_exception('Invalid JSON data provided: ' . json_last_error_msg());
        }

        // Validate each response has required fields.
        foreach ($decodeddata as $response) {
            if (!isset($response['questionhash'], $response['attemptid'], $response['fraction'], $response['ability'])) {
                throw new invalid_parameter_exception('Each response must contain questionhash, attemptid, fraction and ability');
            }
        }

        $responsedata = $decodeddata;

        // Context validation.
        $context = context_system::instance();
        self::validate_context($context);

        // TODO: should we do an additional capability check?
        // Something like: require_capability('local/catquiz:submit_responses', $context); ?

        $errors = [];
        $overallstatus = true;
        $stored = 0;
        $skipped = 0;

        foreach ($responsedata as $response) {
            try {
                // Basic validation of the fraction value.
                if (!is_numeric($response['fraction'])) {
                    throw new invalid_parameter_exception('Invalid fraction value for question ' . $response['questionhash']);
                }

                // Basic validation of the attemptid.
                if (!is_numeric($response['attemptid'])) {
                    throw new invalid_parameter_exception('Invalid attemptid for question ' . $response['questionhash']);
                }

                // Store the response using the response handler.
                $res = \local_catquiz\remote\response\response_handler::store_response(
                    $response['questionhash'],
                    $response['fraction'],
                    $response['attemptid'],
                    $sourceurl
                );
                $success = $res != response_handler::RESPONSE_ERROR;

                switch ($res) {
                    case response_handler::RESPONSE_ERROR:
                        $errors[] = [
                            'questionhash' => $response['questionhash'],
                            'attempthash' => $response['attemptid'],
                            'message' => 'Response error',
                        ];
                        break;
                    case response_handler::RESPONSE_STORED:
                        $stored++;
                        break;
                    case response_handler::RESPONSE_EXISTS:
                        $skipped++;
                        break;
                    default:
                        throw new UnexpectedValueException(sprintf('Unexpected return code %s from store_response', $res));
                }
                if (!$success) {
                    $overallstatus = false;
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'questionhash' => $response['questionhash'],
                    'attempthash' => $response['attemptid'],
                    'message' => $e->getMessage(),
                ];
                $overallstatus = false;
            }
        }

            // Trigger event.
        $event = responses_added::create([
            'context' => context_system::instance(),
            'userid' => $USER->id,
            'other' => [
                'sourceurl' => $sourceurl,
                'added' => $stored,
                'skipped' => $skipped,
                'errors' => count($errors),
            ],
        ]);
        $event->trigger();

        return [
            'status' => $overallstatus,
            'message' => $overallstatus ? 'All responses processed successfully' : 'Some responses failed',
            'added' => $stored,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }
}
