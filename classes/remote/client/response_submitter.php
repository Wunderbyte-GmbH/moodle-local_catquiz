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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');

use context_system;
use local_catquiz\remote\hash\question_hasher;
use curl;
use local_catquiz\catquiz;
use local_catquiz\catscale;
use local_catquiz\event\responses_submitted;

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
     * Create a new response submitter with the given settings.
     *
     * @param string $centralhost The central host URL
     * @param string $token The web service token
     * @param string $scalelabel The label of the scale that will be synchronized
     * @param ?int $contextid The context to use. If left out, will be the context used by the scale.
     * @return self
     */
    public function __construct(string $centralhost, string $token, string $scalelabel, ?int $contextid = null) {
        global $DB;
        $this->centralhost = rtrim($centralhost, '/');
        $this->token = $token;
        $this->scaleid = $DB->get_record('local_catquiz_catscales', ['label' => $scalelabel], 'id')->id;
        $this->contextid = $contextid ?? catscale::return_catscale_object($this->scaleid)->contextid;
    }

    /**
     * Submit responses to central instance.
     *
     * @return \stdClass Result object with success status and details
     */
    public function submit_responses() {
        global $USER;

        // Get the response data.

        $responses = $this->get_response_data();
        if (empty($responses)) {
            return (object)[
                'success' => true,
                'message' => get_string('nonewresponses', 'local_catquiz'),
            ];
        }

        // Prepare the web service call.
        $serverurl = $this->centralhost . '/webservice/rest/server.php';
        $params = [
            'wstoken' => $this->token,
            'wsfunction' => 'local_catquiz_hub_collect_responses',
            'moodlewsrestformat' => 'json',
            'jsondata' => json_encode($responses),
        ];

        // Make the web service call.
        $curl = new curl();

        try {
            $response = $curl->post($serverurl, $params);
            $result = json_decode($response);

            if ($result === null) {
                debugging('Invalid JSON response from server: ' . $response, DEBUG_DEVELOPER);
                return (object)[
                    'success' => false,
                    'error' => sprintf('Invalid response from server: %s', $response),
                ];
            }

            if (!empty($result->exception)) {
                return (object)[
                    'success' => false,
                    'error' => $result->message,
                ];
            }

            // Trigger event.
            $event = responses_submitted::create([
                'context' => context_system::instance(),
                'userid' => $USER->id,
                'other' => [
                    'centralhost' => $this->centralhost,
                    'added' => $result->added,
                    'skipped' => $result->skipped,
                    'errors' => count($result->errors),
                ],
            ]);
            $event->trigger();

            return (object)[
                'success' => true,
                'processed' => count($responses),
                'added' => $result->added,
                'skipped' => $result->skipped,
                'errors' => $result->errors,
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
        global $CFG, $DB;
        $catscaleids = [$this->scaleid, ...catscale::get_subscale_ids($this->scaleid)];
        [$sql, $params] = catquiz::get_sql_for_model_input(
            $this->contextid,
            $catscaleids,
            null,
            null,
            false,
            $this->scaleid
        );
        $data = $DB->get_records_sql($sql, $params);
        $instancename = parse_url($CFG->wwwroot, PHP_URL_HOST);
        $counter = 0;
        foreach ($data as $uniqueid => $response) {
            $data[$counter++] = [
                'questionhash' => question_hasher::generate_hash($response->questionid),
                'attemptid' => crc32($instancename . $response->attemptid),
                'ability' => $response->ability,
                // TODO: get confirmation: Should 'gaveup' questions have a fraction of 0?
                'fraction' => $response->fraction ?? 0,
            ];
            unset($data[$uniqueid]);
        }
        return $data;
    }
}
