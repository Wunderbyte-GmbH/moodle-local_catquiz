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

namespace local_catquiz\remote\client;

use local_catquiz\remote\hash\question_hasher;
use curl;
use local_catquiz\catcontext;
use local_catquiz\catscale;
use local_catquiz\local\model\model_responses;

/**
 * Handles submission of responses to central instance.
 *
 * @package    local_catquiz
 * @copyright  2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class response_submitter {
    /** @var string The central host URL */
    private $centralhost;

    /** @var string The web service token */
    private $token;

    /** @var int The scale to sync */
    private $scaleid;

    /** @var int The context to use */
    private int $contextid;

    /**
     * Constructor.
     *
     * @param string $centralhost The central host URL
     * @param string $token The web service token
     * @param int $scaleid The scale that will be synchronized
     * @param ?int $contextid The context to use. If left out, will be the context used by the scale.
     * @return self
     */
    public function __construct(string $centralhost, string $token, int $scaleid, ?int $contextid = null) {
        $this->centralhost = rtrim($centralhost, '/');
        $this->token = $token;
        $this->scaleid = $scaleid;
        $this->contextid = $contextid ?? catscale::return_catscale_object($scaleid)->contextid;
    }

    /**
     * Submit responses to central instance.
     *
     * @return \stdClass Result object with success status and details
     */
    public function submit_responses() {
        global $CFG;

        // Get the response data.

        $responsedata = $this->get_response_data();
        if (empty($responsedata)) {
            return (object)[
                'success' => false,
                'error' => 'No responses to submit',
            ];
        }

        // Prepare the data for submission.
        $responses = [];
        foreach ($responsedata as $attemptid => $components) {
            foreach ($components as $component => $userresponses) {
                foreach ($userresponses as $qid => $response) {
                    // Generate hash for the question.
                    try {
                        $hash = question_hasher::generate_hash($qid);
                    } catch (\Exception $e) {
                        debugging(
                            'Error generating hash for question ' . $response->questionid . ': ' . $e->getMessage(),
                            DEBUG_DEVELOPER
                        );
                        continue;
                    }

                    $responses[] = [
                        'questionhash' => $hash,
                        'fraction' => $response['fraction'],
                        'remoteuserid' => $userid,
                        'timestamp' => $response['timestamp'],
                    ];
                }
            }
        }

        if (empty($responses)) {
            return (object)[
                'success' => false,
                'error' => 'No valid responses to submit',
            ];
        }

        // Prepare the web service call.
        $serverurl = $this->centralhost . '/webservice/rest/server.php';
        $params = [
            'wstoken' => $this->token,
            'wsfunction' => 'local_catquiz_submit_catquiz_responses',
            'moodlewsrestformat' => 'json',
            'responses' => $responses,
        ];

        // Make the web service call.
        $curl = new curl();
        $curl->setopt(['CURLOPT_SSL_VERIFYPEER' => false, 'CURLOPT_SSL_VERIFYHOST' => false]);

        try {
            $response = $curl->post($serverurl, $params);
            $result = json_decode($response);

            if ($result === null) {
                debugging('Invalid JSON response from server: ' . $response, DEBUG_DEVELOPER);
                return (object)[
                    'success' => false,
                    'error' => 'Invalid response from server',
                ];
            }

            if (!empty($result->exception)) {
                return (object)[
                    'success' => false,
                    'error' => $result->message,
                ];
            }

            return (object)[
                'success' => true,
                'processed' => count($responses),
            ];
        } catch (\Exception $e) {
            debugging('Error submitting responses: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return (object)[
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get response data to submit.
     * This method should be replaced with your implementation.
     *
     * @return array Array of response objects
     */
    private function get_response_data() {
        global $DB;
        $catscaleids = [$this->scaleid, ...catscale::get_subscale_ids($this->scaleid)];
        $contextid = $DB->get_field('local_catquiz_catscales', 'contextid', ['id' => $this->scaleid]);
        $responsedata = model_responses::create_for_context($contextid);
        // This is a placeholder - you'll provide the actual implementation.
        // The expected return format should be an array of objects with:
        // - questionid
        // - response (the actual response data)
        // - userid
        // - timecreated
        return $responsedata;
    }
}
