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

/**
 * Loads the responses from the first person of a CSV file.
 *
 * @param string $filename The file to load the responses from.
 * @param int    $person   Optional. If given, the Nth person will be loaded.
 * @return array
 */
function loadresponsesdata($filename, $person = 0): array {
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
