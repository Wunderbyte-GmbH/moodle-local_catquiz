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
 * @copyright 2023 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\local\model;

/**
 * Provides data required for parameter estimation
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class model_item_response {

    /**
     * @var float response
     */
    private float $response;

    /**
     * @var model_person_param personparams
     */
    private model_person_param $personparams;

    /**
     * Set parameters for class instance.
     *
     * @param float $response
     * @param model_person_param $personparams
     *
     */
    public function __construct(float $response, model_person_param $personparams) {
        $this->response = $response;
        $this->personparams = $personparams;
    }

    /**
     * Return response.
     *
     * @return float
     *
     */
    public function get_response(): float {
        return $this->response;
    }

    /**
     * Return ability.
     *
     * @return float
     *
     */
    public function get_personparams(): model_person_param {
        return $this->personparams;
    }
}
