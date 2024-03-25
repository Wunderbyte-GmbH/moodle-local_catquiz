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
function loadresponsesforperson($filename, $person = 0) {
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
 * @param string $label  The label of the item.
 * @return model_responses
 */
function loadresponsesforitem($filename, $label, $scale = 'Gesamt'): model_responses {
    global $CFG;
    if (($handle = fopen($filename, "r")) === false) {
        throw new UnexpectedValueException("Can not open file: " . $filename);
    }

    $row = 0;
    $responses = [];
    $labelindex = null;
    $personparams = loadpersonparams($CFG->dirroot . '/local/catquiz/tests/fixtures/persons.csv', $scale);
    $arr = [];
    while (($data = fgetcsv($handle, 0, ";")) !== false) {
        $row++;
        if ($row == 1) {
            $labelindex = array_search($label, $data);
            continue;
        }
        $personid = $row - 2;
        $pp = $personparams[$personid];
        $response = $data[$labelindex];
        $responses[] = new model_item_response($response, $pp);
        $catscaleid = 1; // TODO: At the moment they do not have a scale id.
        $arr[$personid] = ['question' => [$catscaleid => ['fraction' => $response]]];
    }
    fclose($handle);
    $mr = model_responses::create_from_array($arr);
    return $mr;
}

/**
 * Returns personparams for each person and each scale in the given file
 *
 * This returns a two-dimensional array. The first key is the personid, the second one is the name of the scale. The value is a
 * model_person_param object.
 *
 * @param string $filename
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
        $pp = (new model_person_param($row - 2))->set_ability($data[$labelindex]); // Use $row as personid.
        $personparams->add($pp);
    }
    fclose($handle);
    return $personparams;
}
