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

namespace local_catquiz\external\node;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->libdir . '/filelib.php');

use dml_write_exception;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use external_multiple_structure;
use local_catquiz\catquiz;
use local_catquiz\catscale;
use local_catquiz\data\dataapi;
use local_catquiz\local\model\model_item_param;
use local_catquiz\local\sync_event;
use local_catquiz\remote\hash\question_hasher;

/**
 * External service for fetching parameters from central instance.
 *
 * @package    local_catquiz
 * @copyright  2024 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fetch_parameters extends external_api {

    /**
     * Internal helper to check if an item exists already in the new context.
     * @var array
     */
    private static array $existingitems = [];

    /**
     * Stores context IDs between the fetched and older contexts.
     *
     * @var ?array
     */
    private static ?array $intermediatecontexts = null;

    /**
     * Returns description of method parameters.
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'scaleid' => new external_value(PARAM_INT, 'ID of the scale to sync'),
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
            ),
        ]);
    }

    /**
     * Fetch and store item parameters from central instance.
     * @param int $scaleid ID of the scale to sync
     *
     * @return array Status and result information
     */
    public static function execute(int $scaleid) {
        global $DB, $CFG;
        $repo = new catquiz();

        // Parameter validation.
        $params = self::validate_parameters(self::execute_parameters(), [
            'scaleid' => $scaleid,
        ]);

        $centralurl = get_config('local_catquiz', 'central_host');
        $wstoken = get_config('local_catquiz', 'central_token');

        $starttime = microtime(true);
        $warnings = [];
        $errors = 0;
        $changedparams = [];

        $scale = $DB->get_record('local_catquiz_catscales', ['id' => $params['scaleid']], '*', MUST_EXIST);
        if (!$scale) {
            throw new \moodle_exception('scalenotfound', 'local_catquiz', '', $params['scaleid']);
        }
        if (!$scale->label) {
            throw new \moodle_exception('scalehasnolabel', 'local_catquiz', '', $params['scaleid']);
        }
        $targetcontext = $repo->get_last_synced_context_id($scale->id);
        $activecontext = catscale::get_context_id($scale->id);
        if (!$targetcontext) {
            $targetcontext = $activecontext;
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
            } catch (dml_write_exception $e) {
                $error = 'Error generating hash for question ' . $question->id . ': ' . $e->error;
                debugging($error, DEBUG_DEVELOPER);
                return [
                    'status' => false,
                    'message' => $e->error,
                    'duration' => 0,
                    'synced' => 0,
                    'errors' => 1,
                    'warnings' => [],
                ];
            } catch (\Exception $e) {
                $error = 'Error generating hash for question ' . $question->id . ': ' . $e->getMessage();
                debugging($error, DEBUG_DEVELOPER);
                return [
                    'status' => false,
                    'message' => $error,
                    'duration' => 0,
                    'synced' => 0,
                    'errors' => 1,
                    'warnings' => [],
                ];
            }
        }

        // Prepare the web service call.
        $serverurl = rtrim($centralurl, '/') . '/webservice/rest/server.php';
        $wsparams = [
            'wstoken' => $wstoken,
            'wsfunction' => 'local_catquiz_hub_distribute_parameters',
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
        $transaction = $DB->start_delegated_transaction();
        $newcontext = dataapi::create_new_context_for_scale(
            $scale->id,
            $scale->name,
            $source,
            false,
            false
        );

        $stored = 0;

        // Store the received parameters.
        foreach ($result->parameters as $param) {
            $questionid = $hashmap[$param->questionhash] ?? null;
            if (!$questionid) {
                $warnings[] = [
                    'item' => $param->questionhash,
                    'warning' => get_string('noquestionhashmatch', 'local_catquiz'),
                ];
                continue;
            }

            if (!$param->scalelabel) {
                $warnings[] = [
                    'item' => $param->questionhash,
                    'warning' => 'Missing scale label',
                ];
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
                continue;
            }

            try {
                // If there is no entry yet for the given scale and component:
                // insert with new contextid. Otherwise: update with new
                // contextid.
                $localparam = $repo->get_item_with_params(
                    $questionid,
                    $param->model,
                    $targetcontext
                );

                // If not found, check intermediate contexts.
                if (!$localparam) {
                    // Traverse from newest to oldest and see if the item exists in an intermediate context.
                    $intermediatecontexts = self::get_intermediate_context_ids(
                        min($activecontext, [$targetcontext]),
                        $newcontext->id
                    );
                    foreach ($intermediatecontexts as $context) {
                        $localparam = $repo->get_itemparams_for(
                            [
                                'componentid' => $questionid,
                                'model' => $param->model,
                                'contextid' => $context,
                            ]
                        );
                        if ($localparam) {
                            break;
                        }
                    }
                }

                if (!$localparam) {
                    // Insert only once per questionid.
                    if (!self::item_exists($questionid)) {
                        $itemrecord = new \stdClass();
                        $itemrecord->componentid = $questionid;
                        $itemrecord->componentname = 'question';
                        $itemrecord->catscaleid = $scalemapping[$param->scalelabel];
                        $itemrecord->contextid = $newcontext->id;
                        $itemrecord->status = LOCAL_CATQUIZ_STATUS_UPDATED_MANUALLY;
                        $itemid = $DB->insert_record(
                            'local_catquiz_items',
                            $itemrecord
                        );
                        self::$existingitems[$questionid] = true;
                    }
                }

                // If it did not change, we can skip it.
                $changed = !$localparam // This is a new model param we do not have locally.
                    || !self::check_float_equal($param->difficulty, $localparam->difficulty)
                    || !self::check_float_equal($param->discrimination, $localparam->discrimination)
                    || !self::check_float_equal($param->guessing, $localparam->guessing)
                    || $param->json != $localparam->json
                    || $param->status != $localparam->status;

                if ($changed) {
                    $changedparams[] = ['old' => $localparam ?? null, 'new' => $param];
                }

                // Create and save the item parameter.
                $record = (object)[
                    'itemid' => $itemid ?? null,
                    'componentid' => $questionid,
                    'componentname' => 'question',
                    'model' => $param->model,
                    'contextid' => $newcontext->id,
                    'difficulty' => $param->difficulty,
                    'discrimination' => $param->discrimination,
                    'guessing' => $param->guessing ?? 0,
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

        // Only if we synced at least one parameter, commit the transaction to
        // create a new context and update the item parameters.
        $countchanged = count($changedparams);
        if (count($changedparams) > 0) {
            $DB->commit_delegated_transaction($transaction);

            $syncevent = new sync_event(
                $newcontext->id,
                $scale->id,
                $countchanged
            );
            $syncevent->save();
        }

        $duration = microtime(true) - $starttime;

        return [
            'status' => $errors == 0,
            'message' => $countchanged > 0
            ? get_string(
                'fetchsuccess',
                'local_catquiz',
                (object) ['num' => $stored, 'contextname' => $newcontext->name]
            )
            : get_string('fetchempty', 'local_catquiz'),
            'duration' => round($duration, 2),
            'synced' => $countchanged,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Internal helper to check if there is an item for that question
     *
     * @param int $questionid The questionid
     *
     * @return bool
     */
    private static function item_exists(int $questionid) {
        global $DB;
        if (array_key_exists($questionid, self::$existingitems)) {
            return true;
        }
        // Insert only once per questionid.
        $existsforquestion = $DB->record_exists(
            'local_catquiz_items',
            [
                'componentid' => $questionid,
            ]
        );

        self::$existingitems[$questionid] = $existsforquestion;
        return $existsforquestion;
    }

    /**
     * Checks if two float numbers are equal within a given precision.
     *
     * @param float $num1 First number to compare
     * @param float $num2 Second number to compare
     * @param int $precision Number of decimal places to check (default: 4)
     *
     * @return bool True if numbers are equal within precision, false otherwise
     */
    private static function check_float_equal(float $num1, float $num2, int $precision = 4) {
        return abs($num1 - $num2) < pow(10, -1 * $precision);
    }

    /**
     * Just returns a simple range of context IDs between start- and end-context
     *
     * @param int $startcontextid
     * @param int $endcontextid
     *
     * @return array
     */
    private static function get_intermediate_context_ids(int $startcontextid, int $endcontextid) {
        if (is_null(self::$intermediatecontexts)) {
            self::$intermediatecontexts = array_reverse(
                range(
                    $startcontextid,
                    $endcontextid
                )
            );
        }
        return self::$intermediatecontexts;
    }
}
