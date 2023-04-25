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
 * This class holds a single item param object
 *  
 * This is one of the return values from a model param estimation.
 */
class model_item_param {

    // For some items, the model returns -INF or INF as difficulty.
    // However, we expect it to be numeric, so we encode those
    // values as -1000 and 1000
    const MODEL_NEG_INF = -1000;
    const MODEL_POS_INF = 1000;

    /**
     * @var float
     */
    private float $difficulty = 0;

    /**
     * @var integer $id The item id, e.g. question id
     */
    private int $id;

    public function __construct(int $id) {
        $this->id = $id;
    }

    /**
     * Returns the item id (e.g. question id)
     * 
     * @return int
     */
    public function get_id(): int {
        return $this->id;
    }
    public function get_difficulty(): float {
        return $this->difficulty;
    }

    public function set_difficulty(float $difficulty): self {
        $this->difficulty = $difficulty;
        return $this;
    }
};