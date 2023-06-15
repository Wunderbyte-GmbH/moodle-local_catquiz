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

use moodle_exception;

/**
 * Base class for test strategies.
 */
class teststrategy_fastest extends teststrategy {

    /**
     *
     * @var int $id // strategy id defined in lib.
     */
    public int $id = STRATEGY_FASTEST;

    public function __construct() {

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

        // Retrieve all questions for scale.
        $questions = array_values(parent::get_all_available_testitems($this->scaleid));

        if (empty($questions)) {
            throw new moodle_exception('nowquestionsincatscale', 'local_catquiz');
        }

        //


        $index = rand(0, count($questions));

        return $questions[$index];
    }

    /**
     * Return Description.
     *
     * @return void
     */
    public function get_description() {

        return parent::get_description();
    }

}