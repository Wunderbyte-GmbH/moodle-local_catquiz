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
 * Entities Class to display list of entity records.
 *
 * @package local_catquiz
 * @author Daniel Pasterk
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

class preprocess_data{

    /***
     * @param $array: ouput of data\catquiz_base::get_question_results()
     * @return array:
     */
    static function get_fractions_by_question($array) {
        $result = array();
        foreach ($array as $obj) {
            $question_id = $obj->questionid;
            $fraction = $obj->fraction;
            if (!isset($result[$question_id])) {
                $result[$question_id] = array();
            }
            $result[$question_id][] = $fraction;
        }
        return $result;
    }





}
