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

namespace local_catquiz\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->libdir . '/filelib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use external_multiple_structure;
use local_catquiz\catcontext;
use local_catquiz\catscale;
use local_catquiz\data\dataapi;
use local_catquiz\local\model\model_item_param;
use local_catquiz\remote\hash\question_hasher;

/**
 * External service for fetching parameters from central instance.
 *
 * @package    local_catquiz
 * @copyright  2024 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class client_fetch_parameters extends external_api {

    /**
     * Returns description of method parameters.
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'scaleid' => new external_value(PARAM_INT, 'ID of the scale to sync')
        ]);
    }

    /**
     * Returns description of method result value.
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'Status of the sync'),
            'message' => new external_value(PARAM_TEXT, 'Status message'),
            'duration' => new external_value(PARAM_FLOAT, 'Duration in seconds'),
            'synced' => new external_value(PARAM_INT, 'Number of parameters synced'),
            'errors' => new external_value(PARAM_INT, 'Number of errors encountered'),
            'warnings' => new external_multiple_structure(
                new external_single_structure([
                    'item' => new external_value(PARAM_TEXT, 'Item identifier'),
                    'warning' => new external_value(PARAM_TEXT, 'Warning message'),
                ])
            )
        ]);
    }

    /**
     * Fetch and store item parameters from central instance.
     * @param int $scalelabel Label of the scale to sync
     * @return array Status and result information
     */
    public static function execute(string $scaleid) {
        global $DB, $CFG;

        // Parameter validation.
        $params = self::validate_parameters(self::execute_parameters(), [
            'scaleid' => $scaleid,
        ]);

        $centralurl = get_config('local_catquiz', 'central_host');
        $wstoken = get_config('local_catquiz', 'central_token');

        $starttime = microtime(true);
        $warnings = [];
        $stored = 0;
        $errors = 0;

        $scale = $DB->get_record('local_catquiz_catscales', ['id' => $params['scaleid']], '*', MUST_EXIST);
        if (!$scale) {
            throw new \moodle_exception('scalenotfound', 'local_catquiz', '', $params['scaleid']);
        }
        if (!$scale->label) {
            throw new \moodle_exception('scalehasnolabel', 'local_catquiz', '', $params['scaleid']);
        }

        // Create a mapping of catscale labels to IDs.
        $scalemapping = [];
        $catscaleids = [$scale->id, ...catscale::get_subscale_ids($scale->id)];
        [$inscalesql, $inscaleparams] = $DB->get_in_or_equal($catscaleids, SQL_PARAMS_NAMED, 'scaleid');

        $sql = "SELECT * FROM {local_catquiz_catscales} lcs WHERE lcs.id $inscalesql";
        $scalerecords = $DB->get_records_sql($sql, $inscaleparams);
        foreach ($scalerecords as $s) {
            $scalemapping[$s->label] = $s->id;
        }

        // Get all questions assigned to this scale or its subscales.
        $sql = "SELECT DISTINCT q.id
                FROM {local_catquiz_items} lci
                JOIN {question} q ON q.id = lci.componentid
                WHERE lci.catscaleid $inscalesql";

        $questions = $DB->get_records_sql($sql, $inscaleparams);
        $hashmap = [];

        // Generate and store hashes for all questions.
        foreach ($questions as $question) {
            try {
                $hash = question_hasher::generate_hash($question->id);
                $hashmap[$hash] = $question->id;
            } catch (\Exception $e) {
                debugging('Error generating hash for question ' . $question->id . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        // Prepare the web service call.
        $serverurl = rtrim($centralurl, '/') . '/webservice/rest/server.php';
        $wsparams = [
            'wstoken' => $wstoken,
            'wsfunction' => 'local_catquiz_fetch_item_parameters',
            'moodlewsrestformat' => 'json',
            'scalelabel' => $scale->label,
        ];

        // Make the web service call.
        $CFG->curlsecurityblockedhosts = '';
        $curl = new \curl();
        $curl->setopt([
            'CURLOPT_SSL_VERIFYPEER' => false,
            'CURLOPT_SSL_VERIFYHOST' => false,
        ]);
        $response = $curl->post($serverurl, $wsparams);
        unset($CFG->curlsecurityblockedhosts);
        $result = json_decode($response);

        if (!$result) {
            return [
                'status' => false,
                'message' => 'Invalid response from server: ' . $response,
                'duration' => 0,
                'synced' => 0,
                'errors' => 1,
                'warnings' => [],
            ];
        }

        if (!empty($result->exception)) {
            return [
                'status' => false,
                'message' => 'Web service error: ' . $result->message,
                'duration' => 0,
                'synced' => 0,
                'errors' => 1,
                'warnings' => [],
            ];
        }

        if (!$result->status) {
            return [
                'status' => false,
                'message' => 'Error: ' . $result->message,
                'duration' => 0,
                'synced' => 0,
                'errors' => 1,
                'warnings' => [],
            ];
        }

        $source = "Fetch from " . parse_url($centralurl, PHP_URL_HOST);
        $newcontext = dataapi::create_new_context_for_scale(
            $scale->id,
            $scale->name,
            $source,
            false,
            false
        );

        // Store the received parameters.
        foreach ($result->parameters as $param) {
            $questionid = $hashmap[$param->questionhash] ?? null;
            if (!$questionid) {
                $warnings[] = [
                    'item' => $param->questionhash,
                    'warning' => get_string('noquestionhashmatch', 'local_catquiz'),
                ];
                $errors++;
                continue;
            }

            if (!$param->scalelabel) {
                $warnings[] = [
                    'item' => $param->questionhash,
                    'warning' => 'Missing scale label',
                ];
                $errors++;
                continue;
            }

            if (!array_key_exists($param->scalelabel, $scalemapping)) {
                $warnings[] = [
                    'item' => $param->scalelabel,
                    'warning' => get_string(
                        'nolocalmappingforscale',
                        'local_catquiz',
                        (object) ['remotelabel' => $param->scalelabel]
                    ),
                ];
                $errors++;
                continue;
            }

            try {
                // If there is no entry yet for the given scale and component: insert with new contextid.
                // Otherwise: update with new contextid.
                $existing = $DB->get_record(
                    'local_catquiz_items',
                    ['componentid' => $questionid, 'catscaleid' => $scalemapping[$param->scalelabel]]
                );

                if ($existing) {
                    $itemrecord = $existing;
                    $itemrecord->contextid = $newcontext->id;
                    $itemid = $existing->id;
                    $DB->update_record('local_catquiz_items', $itemrecord);
                } else {
                    $itemrecord = new \stdClass();
                    $itemrecord->componentid = $questionid;
                    $itemrecord->componentname = 'question';
                    $itemrecord->catscaleid = $scalemapping[$param->scalelabel];
                    $itemrecord->contextid = $newcontext->id;
                    $itemrecord->status = LOCAL_CATQUIZ_TESTITEM_STATUS_ACTIVE;
                    $itemid = $DB->insert_record('local_catquiz_items', $itemrecord);
                    // TODO: How to set the active model?
                }

                // Create and save the item parameter.
                $record = (object)[
                    'itemid' => $itemid,
                    'componentid' => $questionid,
                    'componentname' => 'question',
                    'model' => $param->model,
                    'contextid' => $newcontext->id,
                    'difficulty' => $param->difficulty,
                    'discrimination' => $param->discrimination,
                    'status' => $param->status,
                    'json' => $param->json,
                ];

                $itemparam = model_item_param::from_record($record);
                $itemparam->save();
                $stored++;
            } catch (\Exception $e) {
                debugging('Error storing parameter: ' . $e->getMessage(), DEBUG_DEVELOPER);
                $warnings[] = [
                    'item' => $param->scalelabel,
                    'warning' => $e->getMessage(),
                ];
                $errors++;
            }
        }

        $duration = microtime(true) - $starttime;

        return [
            'status' => $stored > 0,
            'message' => $stored > 0
                ? get_string('fetchsuccess', 'local_catquiz', (object) ['num' => $stored, 'contextname' => $newcontext->name])
                : get_string('fetchempty', 'local_catquiz'),
            'duration' => round($duration, 2),
            'synced' => $stored,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }
}
