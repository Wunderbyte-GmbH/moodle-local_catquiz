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
 * Class helpercat.
 *
 * @package local_catquiz
 * @author Daniel Pasterk
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

/**
 * Class for helper function in data preprocessing
 *
 * @package local_catquiz
 * @author Daniel Pasterk
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helpercat {

    /**
     * Returns person response.
     *
     * @param mixed $response
     * @param mixed $user_id
     * 
     * @return mixed
     * 
     */
    static function get_person_response($response, $user_id) {

        $components = array_keys($response[$user_id]);
        $items = $response[$user_id][$components[0]]; // TODO: fix for multiple components
        return $items;

    }

    /**
     * Returns user ability.
     *
     * @param int $user_id
     * 
     * @return mixed
     * 
     */
    static function get_user_ability(int $user_id) {
        return 0.5; //dummy data
    }

    /**
     * REturns item params.
     *
     * @param int $item_id
     * 
     * @return mixed
     * 
     */
    static function get_item_params(int $item_id) { //and context_id ?
        return 0.5; //dummy data
    }

}
