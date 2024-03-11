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
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

/**
 * Class for helper function in data preprocessing
 *
 * @package local_catquiz
 * @author Daniel Pasterk
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helpercat {

    /**
     * Returns person response.
     *
     * @param mixed $response
     * @param mixed $userid
     *
     * @return mixed
     *
     */
    public static function get_person_response($response, $userid) {

        $components = array_keys($response[$userid]);
        $items = $response[$userid][$components[0]]; // TODO: fix for multiple components.
        return $items;

    }

    /**
     * Returns user ability.
     *
     * @param int $userid
     *
     * @return mixed
     *
     */
    public static function get_user_ability(int $userid) {
        return 0.5; // Dummy data.
    }

    /**
     * REturns item params.
     *
     * @param int $itemid
     *
     * @return mixed
     *
     */
    public static function get_item_params(int $itemid) {
        // And context_id ?.
        return 0.5; // Dummy data.
    }

}
