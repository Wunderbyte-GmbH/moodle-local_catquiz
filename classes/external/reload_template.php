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
use Exception;
use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use local_catquiz\execute_method_from_webservice;
use local_catquiz\output\catscalemanager\questions\cards\datacard;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

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
     * Describes the parameters this webservice.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'data'  => new external_value(PARAM_RAW, 'Data package as json.', VALUE_REQUIRED),
            ]
        );
    }

    /**
     * Execute this webservice.
     *
     * @param string $data
     *
     * @return boolean external_function_parameters
     *
     */
    public static function execute(
        string $data) {

        global $PAGE;

        $context = context_system::instance();
        $PAGE->set_context($context);
        $dataobject = json_decode($data);

        // Make sure, the element triggering the reload includes all necessary data.
        $admethodname = $dataobject->admethodname;
        $adparams = $dataobject->adparams;
        $resultsuccess = execute_method_from_webservice::execute_method($admethodname, $adparams);

        // Get data for template.
        $tdparamsstring = $dataobject->tdparams;
        $paramsarray = explode(",", $tdparamsstring);
        $renderclass = new $dataobject->classlocation(...$paramsarray);
        // To be able to render the data from the class, make sure the class implements the renderable interface.
        $datafortemplate = $renderclass->export_for_template();
        $templatedatajson = json_encode($datafortemplate);

        if ($resultsuccess) {
            $result = [
                'success' => 1,
                'message' => get_string($admethodname."_message", 'local_catquiz'),
                'data' => $templatedatajson,
            ];
        } else {
            $result = [
                'success' => 0,
                'message' => get_string('functiondoesntexist', 'local_wunderbyte_table'),
            ];
        }
        return $result;
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
            ]
        );
    }
}
