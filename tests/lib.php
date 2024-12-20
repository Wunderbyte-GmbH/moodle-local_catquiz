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
 * Common functions for local_catquiz tests
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_catquiz\local\model\model_item_response;
use local_catquiz\local\model\model_person_param;
use local_catquiz\local\model\model_person_param_list;
use local_catquiz\local\model\model_responses;

/**
 * Loads the responses from the first person of a CSV file.
 *
 * @param string $filename The file to load the responses from.
 * @param int    $person   Optional. If given, the Nth person will be loaded.
 * @return array
 */
function loadresponsesforperson($filename, $person = 0): array {
    if (($handle = fopen($filename, "r")) === false) {
        throw new UnexpectedValueException("Can not open file: " . $filename);
    }

    $row = 0;
    $questionids = [];
    while (($data = fgetcsv($handle, 0, ";")) !== false) {
        $row++;
        if ($row == 1) {
            // The first row contains the question labels.
            // Prefix the label with SIM. E.g., A01-01 will become SIMA01-01.
            $questionids = preg_filter(
                '/^/',
                'SIM',
                array_slice($data, 1)
            );
            continue;
        }
        if ($row < $person) {
            continue;
        }
        // The responses for person 1.
        $responses = array_slice($data, 1);
        break;
    }

    fclose($handle);
    return array_combine($questionids, $responses);
}

/**
 * Parses a CSV and returns a model_response object for the given item.
 *
 * @param string $filename The file to load the responses from.
 * @param model_person_param_list $initialabilities
 * @return model_responses
 */
function loadresponsesforitem(string $filename, model_person_param_list $initialabilities): model_responses {
    global $CFG;
    if (($handle = fopen($filename, "r")) === false) {
        throw new UnexpectedValueException("Can not open file: " . $filename);
    }

    $row = 0;
    $labels = [];
    $mr = new model_responses();
    while (($data = fgetcsv($handle, 0, ";")) !== false) {
        $row++;
        if ($row == 1) {
            $labels = array_slice($data, 1);
            continue;
        }
        $personidstr = $data[0];
        $personid = (int) substr($personidstr, 1);
        foreach (array_slice($data, 1) as $index => $response) {
            $mr->set($personid, $labels[$index], $response, $initialabilities[$personid]);
        }
    }
    fclose($handle);
    return $mr;
}

/**
 * Returns personparams for each person and each scale in the given file
 *
 * This returns a two-dimensional array. The first key is the personid, the second one is the name of the scale. The value is a
 * model_person_param object.
 *
 * @param string $filename
 * @param string $scale
 * @return model_person_param_list
 * @throws UnexpectedValueException
 */
function loadpersonparams(string $filename, string $scale): model_person_param_list {
    if (($handle = fopen($filename, "r")) === false) {
        throw new UnexpectedValueException("Can not open file: " . $filename);
    }

    $row = 0;
    $personparams = new model_person_param_list();
    $labelindex = null;
    while (($data = fgetcsv($handle, 0, ";")) !== false) {
        $row++;
        if ($row === 1) {
            $labelindex = array_search($scale, $data);
            continue;
        }
        $personidstr = $data[0];
        $personid = (int) substr($personidstr, 1);
        $pp = (new model_person_param($personid, 1))->set_ability($data[$labelindex]);
        $personparams->add($pp);
    }
    fclose($handle);
    return $personparams;
}
