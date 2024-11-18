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

namespace local_catquiz\remote\response;

use local_catquiz\remote\hash\question_hasher;

/**
 * Handles storage and processing of remote responses.
 *
 * @package    local_catquiz
 * @copyright  2024 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class response_handler {
    /**
     * Store a response from a remote instance.
     *
     * @param string $questionhash The question hash
     * @param string $fraction The response fraction
     * @param int $remoteuserid The user ID from remote instance
     * @param string $sourceurl The source URL
     * @return bool Success status
     */
    public static function store_response($questionhash, $fraction, $remoteuserid, $sourceurl) {
        global $DB;

        $record = new \stdClass();
        $record->questionhash = $questionhash;
        $record->response = $fraction; // We store the fraction in the response field.
        $record->remoteuserid = $remoteuserid;
        $record->sourceurl = $sourceurl;
        $record->timecreated = time();

        try {
            $DB->insert_record('local_catquiz_rresponses', $record);
            return true;
        } catch (\Exception $e) {
            debugging('Error storing remote response: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Process stored responses.
     *
     * @param int $batchsize Number of responses to process in one batch
     * @return array Processing results
     */
    public static function process_responses($batchsize = 100) {
        global $DB;

        $responses = $DB->get_records('local_catquiz_rresponses',
            ['timeprocessed' => null],
            'timecreated ASC',
            '*',
            0,
            $batchsize);

        $results = ['processed' => 0, 'errors' => 0];

        foreach ($responses as $response) {
            $questionid = question_hasher::get_questionid_from_hash($response->questionhash);
            if (!$questionid) {
                self::mark_response_error($response, 'Question not found');
                $results['errors']++;
                continue;
            }

            try {
                // Process response using existing model_responses class.
                // This needs to be implemented based on your existing logic.
                self::mark_response_processed($response);
                $results['processed']++;
            } catch (\Exception $e) {
                self::mark_response_error($response, $e->getMessage());
                $results['errors']++;
            }
        }

        return $results;
    }

    /**
     * Marks a response as processed.
     *
     * @param \stdClass $response The response record
     */
    private static function mark_response_processed($response) {
        global $DB;
        $response->timeprocessed = time();
        $response->processinginfo = json_encode(['status' => 'success']);
        $DB->update_record('local_catquiz_rresponses', $response);
    }

    /**
     * Marks a response as failed with error information.
     *
     * @param \stdClass $response The response record
     * @param string $error The error message
     */
    private static function mark_response_error($response, $error) {
        global $DB;
        $response->timeprocessed = time();
        $response->processinginfo = json_encode(['status' => 'error', 'message' => $error]);
        $DB->update_record('local_catquiz_rresponses', $response);
    }
}
