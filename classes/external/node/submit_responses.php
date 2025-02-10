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
 * External API for submitting responses.
 *
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\external\node;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use moodle_exception;
use Throwable;

/**
 * External API class for submitting responses.
 *
 * @copyright  2024 Wunderbyte GmbH <info@wunderbyte.at>
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
            'scaleid' => new external_value(PARAM_INT, 'ID of the scale for which responses are submitted', VALUE_REQUIRED),
        ]);
    }

    /**
     * Executes the submission of responses for a given scale.
     *
     * @param int $scaleid The ID of the scale for which responses are submitted
     * @return array Status message indicating the result of the submission
     */
    public static function execute($scaleid) {
        try {
            $config = get_config('local_catquiz');
            if (empty($config->central_host) || empty($config->central_token)) {
                throw new moodle_exception('nocentralconfig', 'local_catquiz');
            }

            global $DB;

            // Validate parameters passed to the function.
            $params = self::validate_parameters(self::execute_parameters(), ['scaleid' => $scaleid]);
            // Fetch scale label from the database.
            if (!$label = $DB->get_field('local_catquiz_catscales', 'label', ['id' => $scaleid], MUST_EXIST)) {
                return [
                    'message' => get_string(
                        'submission_error',
                        'local_catquiz',
                        get_string('missing_scale_label', 'local_catquiz')
                    ),
                    'success' => false,
                ];
            }

            $submission = new \local_catquiz\remote\client\response_submitter(
                $config->central_host,
                $config->central_token,
                $label
            );
            $result = $submission->submit_responses();

            if ($result->success) {
                return [
                    'message' => $result->message ?? get_string(
                        'submission_success',
                        'local_catquiz',
                        (object)[
                            'total' => $result->processed,
                            'added' => $result->added,
                            'skipped' => $result->skipped,
                        ]
                    ),
                    'success' => true,
                ];
            }

            return [
                'message' => get_string('submission_error', 'local_catquiz', $result->error),
                'success' => false,
            ];
        } catch (Throwable $t) {
            return [
                'message' => sprintf('Could not submit responses: "%s" in %s:%d', $t->getMessage(), $t->getFile(), $t->getLine()),
                'success' => false,
            ];
        }
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'message' => new external_value(PARAM_TEXT, 'Message'),
            'success' => new external_value(PARAM_BOOL, 'Request was successful'),
        ]);
    }
}
