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

 namespace local_catquiz;

 defined('MOODLE_INTERNAL') || die();

 require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * This class executes methods from webservice.
 *
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH
 * @author     Georg Maißer, Magdalena Holczik
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class execute_method_from_webservice {

    /**
     * Execute method using params: methodname, data.
     *
     * @param array $methodname
     * @param string $paramsarrayasstring
     *
     * @return boolean
     */
    public static function execute_method($methodname, $paramsarrayasstring = "") {
        // Params need to be set in the template.
        if (!empty($paramsarrayasstring)) {
            $params = explode(",", $paramsarrayasstring);
        }

        switch ($methodname) {

            case 'local_catquiz_toggle_testitemstatus':

                if ($params[2] == "-slash") {
                    $status = LOCAL_CATQUIZ_TESTITEM_STATUS_ACTIVE;
                } else {
                    $status = LOCAL_CATQUIZ_TESTITEM_STATUS_INACTIVE;
                };

                $catscaleid = $params[0];
                $id = $params[1];
                $component = $params[3];

                catscale::add_or_update_testitem_to_scale((int)$catscaleid, $id, $status, $component);

                return true;
            default:
                return false;
        }

    }

};
