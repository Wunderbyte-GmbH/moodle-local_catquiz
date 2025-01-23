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
 * External service for fetching calculated item parameters.
 *
 * @package    local_catquiz
 * @copyright  2024 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\external\hub;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use core\context\system as context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_catquiz\catscale;
use local_catquiz\local\model\model_item_param;
use local_catquiz\remote\hash\question_hasher;
use moodle_exception;

/**
 * External service implementation for fetching item parameters.
 *
 * @package    local_catquiz
 * @copyright  2024 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class distribute_parameters extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters(
            [
                'scalelabel' => new external_value(
                    PARAM_TEXT,
                    'Label of the CAT scale to fetch parameters for'
                ),
                'questionhashes' => new external_multiple_structure(
                    new external_value(PARAM_TEXT, 'Hash of the question'),
                    'Optional array of question hashes to filter by',
                    VALUE_DEFAULT,
                    []
                ),
                'models' => new external_multiple_structure(
                    new external_value(PARAM_TEXT, 'Name of the model'),
                    'Optional array of model names to filter by',
                    VALUE_DEFAULT,
                    []
                ),
            ]
        );
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure(
            [
                'status' => new external_value(PARAM_BOOL, 'Status of the request'),
                'message' => new external_value(PARAM_TEXT, 'Status message'),
                'contextid' => new external_value(PARAM_INT, 'Context ID the parameters were fetched from'),
                'parameters' => new external_multiple_structure(
                    new external_single_structure([
                        'questionhash' => new external_value(PARAM_TEXT, 'Hash of the question'),
                        'model' => new external_value(PARAM_TEXT, 'Name of the model'),
                        'difficulty' => new external_value(PARAM_FLOAT, 'Item difficulty parameter'),
                        'discrimination' => new external_value(PARAM_FLOAT, 'Item discrimination parameter', VALUE_DEFAULT, 0.0),
                        'guessing' => new external_value(PARAM_FLOAT, 'Item guessing parameter', VALUE_DEFAULT, 0.0),
                        'status' => new external_value(PARAM_INT, 'Status of the parameter'),
                        'json' => new external_value(PARAM_RAW, 'Additional parameters as JSON', VALUE_DEFAULT, null),
                        'scalelabel' => new external_value(PARAM_RAW, 'The label of the assigned catscale', VALUE_DEFAULT, null),
                    ])
                ),
            ]
        );
    }

    /**
     * Fetch item parameters for a given CAT scale.
     *
     * @param string $scalelabel The label of the CAT scale
     * @param array $questionhashes Optional array of question hashes to filter by
     * @param array $models Optional array of model names to filter by
     * @return array The status and retrieved parameters
     */
    public static function execute($scalelabel, $questionhashes = [], $models = []) {
        global $DB;

        // Parameter validation.
        $params = self::validate_parameters(self::execute_parameters(), [
            'scalelabel' => $scalelabel,
            'questionhashes' => $questionhashes,
            'models' => $models,
        ]);

        // Context validation.
        $systemcontext = context_system::instance();
        self::validate_context($systemcontext);

        try {
            // Get the scale from its label.
            $scale = $DB->get_record('local_catquiz_catscales', ['label' => $scalelabel], '*', MUST_EXIST);
            // Get all relevant scale IDs (parent scale and subscales).
            $catscaleids = [$scale->id, ...catscale::get_subscale_ids($scale->id)];
            // Get the latest context for this scale.
            $contextid = catscale::get_context_id($scale->id);
            // Get all questions assigned to this scale.
            [$inscalesql, $inscaleparams] = $DB->get_in_or_equal($catscaleids, SQL_PARAMS_NAMED, 'scaleid');

            $sql = "SELECT lci.*, lcip.*, lcs.label scalelabel
            FROM {local_catquiz_items} lci
            JOIN {local_catquiz_itemparams} lcip ON lci.id = lcip.itemid
            JOIN {local_catquiz_catscales} lcs ON lci.catscaleid = lcs.id
            WHERE lci.catscaleid $inscalesql
            AND lcip.contextid = :contextid";

            $params = array_merge($inscaleparams, ['contextid' => $contextid]);
            $records = $DB->get_records_sql($sql, $params);

            $results = [];
            foreach ($records as $record) {
                // Generate hash for this question.
                try {
                    $hash = question_hasher::generate_hash($record->componentid);
                } catch (\Exception $e) {
                    debugging(
                        'Error generating hash for question ' . $record->componentid . ': ' . $e->getMessage(),
                        DEBUG_DEVELOPER
                    );
                    continue;
                }

                // Skip if we're filtering by hashes and this isn't one we want.
                if (!empty($questionhashes) && !in_array($hash, $questionhashes)) {
                    continue;
                }

                // Skip if we're filtering by model and this isn't one we want.
                if (!empty($models) && !in_array($record->model, $models)) {
                    continue;
                }

                // Create item param object to handle parameter extraction.
                $itemparam = model_item_param::from_record($record);
                $paramarray = $itemparam->get_params_array();

                $results[] = [
                    'questionhash' => $hash,
                    'model' => $itemparam->get_model_name(),
                    'difficulty' => $paramarray['difficulty'] ?? 0.0,
                    'discrimination' => $paramarray['discrimination'] ?? 0.0,
                    'guessing' => $paramarray['guessing'] ?? 0.0,
                    'status' => $itemparam->get_status(),
                    'json' => $itemparam->to_record()->json,
                    'scalelabel' => $record->scalelabel,
                ];
            }
            return [
                'status' => true,
                'message' => 'Parameters retrieved successfully',
                'contextid' => $contextid,
                'parameters' => $results,
            ];
        } catch (\Exception $e) {
            return [
                'status' => false,
                'message' => $e->getMessage(),
                'contextid' => 0,
                'parameters' => [],
            ];
        }
    }
}
