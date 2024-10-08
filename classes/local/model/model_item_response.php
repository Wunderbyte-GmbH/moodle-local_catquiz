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
 * @copyright 2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\local\model;

/**
 * Provides data required for parameter estimation
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class model_item_response {

    /**
     * @var string
     */
    private string $itemid;

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
     * @param string $itemid
     * @param float $response
     * @param model_person_param $personparams
     *
     */
    public function __construct(string $itemid, float $response, model_person_param &$personparams) {
        $this->itemid = $itemid;
        $this->response = $response;
        $this->personparams = $personparams;
    }

    /**
     * Returns the item ID.
     *
     * @return string
     */
    public function get_id(): string {
        return $this->itemid;
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
     * @return model_person_param
     */
    public function get_personparams(): ?model_person_param {
        return $this->personparams;
    }

    /**
     * Sets the personparam to the given one.
     *
     * @param \local_catquiz\local\model\model_person_param $pp
     * @return \local_catquiz\local\model\model_item_response
     */
    public function set_personparams(model_person_param $pp): self {
        $this->personparams = $pp;
        return $this;
    }
}
