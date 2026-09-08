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
 * @author     Georg Maißer, Magdalena Holczik
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace local_catquiz\external;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use local_catquiz\execute_method_from_webservice;
use moodle_exception;


/**
 * External Service for local wunderbyte_table to (re)load data.
 *
 * @package   local_catquiz
 * @copyright 2024 Wunderbyte GmbH {@link http://www.wunderbyte.at}
 * @author    Georg Maißer
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reload_template extends external_api {
    /**
     * Renderers this endpoint may build, by symbolic name.
     *
     * The key is what the client sends. A PHP class name is an implementation detail
     * and has no business in a public API contract: it tells a caller which classes
     * exist, it breaks when the class is renamed, and it invites the endpoint to
     * construct whatever it is given. A symbolic name does none of that - it can only
     * ever select something this list already permits.
     *
     * @var array
     */
    const RENDERERS = [
        'questiondatacard' => \local_catquiz\output\catscalemanager\questions\cards\datacard::class,
    ];
    /**
     * Describes the parameters this webservice.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            // Typed fields instead of one JSON blob with a comma-separated
            // parameter string. validate_parameters() can check these; it could never
            // check what was inside the blob, so PARAM_RAW was the only honest
            // declaration and no declaration at all in practice.
            'renderer' => new external_value(PARAM_ALPHANUMEXT, 'Symbolic renderer name', VALUE_REQUIRED),
            'action' => new external_value(PARAM_ALPHANUMEXT, 'Action to run first', VALUE_DEFAULT, ''),
            'actionparams' => new external_value(PARAM_TEXT, 'Parameters for the action', VALUE_DEFAULT, ''),
            'testitemid' => new external_value(PARAM_INT, 'Test item id', VALUE_DEFAULT, 0),
            'contextid' => new external_value(PARAM_INT, 'CAT context id', VALUE_DEFAULT, 0),
            'catscaleid' => new external_value(PARAM_INT, 'CAT scale id', VALUE_DEFAULT, 0),
            'component' => new external_value(PARAM_COMPONENT, 'Component name', VALUE_DEFAULT, 'question'),
        ]);
    }

    /**
     * Execute this webservice.
     *
     * @param string $renderer Symbolic renderer name, resolved through self::RENDERERS
     * @param string $action Action to run before rendering, empty for none
     * @param string $actionparams Parameters for the action
     * @param int $testitemid
     * @param int $contextid
     * @param int $catscaleid
     * @param string $component
     *
     * @return boolean external_function_parameters
     *
     */
    public static function execute(
        string $renderer,
        string $action = '',
        string $actionparams = '',
        int $testitemid = 0,
        int $contextid = 0,
        int $catscaleid = 0,
        string $component = 'question'
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'renderer' => $renderer,
            'action' => $action,
            'actionparams' => $actionparams,
            'testitemid' => $testitemid,
            'contextid' => $contextid,
            'catscaleid' => $catscaleid,
            'component' => $component,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/catquiz:manage_catscales', $context);

        if (!array_key_exists($params['renderer'], self::RENDERERS)) {
            throw new moodle_exception('invalidrenderclass', 'local_catquiz', '', $params['renderer']);
        }

        // The action still travels as a name plus a parameter string, but it is
        // dispatched through a switch in execute_method_from_webservice rather than
        // being used to construct anything - unlike the renderer, it cannot name a
        // class.
        $success = 1;
        if ($params['action'] !== '') {
            $success = execute_method_from_webservice::execute_method(
                $params['action'],
                $params['actionparams']
            );
        }

        $classname = self::RENDERERS[$params['renderer']];

        try {
            $renderclass = new $classname(
                $params['testitemid'],
                $params['contextid'],
                $params['catscaleid'],
                $params['component']
            );
        } catch (\Throwable $e) {
            return [
                'success' => 0,
                'message' => get_string('invalidrenderparams', 'local_catquiz'),
                'data' => '',
            ];
        }

        global $PAGE;
        $output = $PAGE->get_renderer('local_catquiz');

        return [
            'success' => $success,
            'message' => '',
            'data' => json_encode($renderclass->export_for_template($output)),
        ];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_INT, '1 is success, 0 isn\'t'),
            'message' => new external_value(PARAM_RAW, 'Message to be displayed', VALUE_OPTIONAL, ''),
            'data' => new external_value(PARAM_RAW, 'Data for the template to be rendered', VALUE_OPTIONAL, null),
            ]);
    }
}
