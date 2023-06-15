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

namespace local_catquiz\teststrategy;

use local_catquiz\catscale;
use moodle_exception;

/**
 * Base class for test strategies.
 */
class teststrategy {


    /**
     *
     * @var int $id // strategy id defined in lib.
     */
    public int $id = 0; // Administrativ.


    /**
     *
     * @var int $id scaleid.
     */
    public int $scaleid;


    public function __construct() {

    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function get_description() {

        $classname = get_class($this);

        $parts = explode('\\', $classname);
        $classname = array_pop($parts);
        return get_string($classname, 'local_catquiz');
    }

    /**
     * Strategy specific way of returning the next testitem.
     *
     * @return object
     */
    public function return_next_testitem() {

        if (empty($this->scaleid)) {
            throw new moodle_exception('noscaleid', 'local_catquiz');
        }

        // Todo: Not hardcode context.
        $questions = array_values($this->get_all_available_testitems($this->scaleid));

        if (empty($questions)) {
            throw new moodle_exception('nowquestionsincatscale', 'local_catquiz');
        }

        $index = rand(0, count($questions));

        return $questions[$index];
    }

    /**
     * Retrieves all the available testitems from the current scale.
     *
     * @return array
     */
    public function get_all_available_testitems(int $catscaleid):array {

        $catscale = new catscale($catscaleid);

        // Todo: Not hardcode context.
        return $catscale->get_testitems(1);

    }

    /**
     * Set catscale id.
     * @param int $scaleid
     * @return void
     */
    public function set_scale(int $scaleid) {
        $this->scaleid = $scaleid;
    }
}