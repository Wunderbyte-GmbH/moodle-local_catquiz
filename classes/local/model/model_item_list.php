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
 * This class holds a list of item param objects
 */
class model_item_list {

    private array $items;

    /**
     * @var array<float>
     *
     * The array keys correspond to the item IDs.
     */
    private array $item_difficulties;
    public function __construct(array $items) {
        $this->items = $items;
        $this->item_difficulties = [];
    }

    /**
     * @return void
     */
    public function estimate_initial_item_difficulties(): void {

        $this->item_difficulties = [];
        $item_ids = array_keys($this->items);

        if (count($this->items) === 0) {
            return;
        }

        foreach($item_ids as $id) {

            $item_fractions = $this->items[$id];
            $num_passed = 0;
            $num_failed = 0;

            foreach ($item_fractions as $fraction){
                if ($fraction == 1){
                    $num_passed += 1;
                } else {
                    $num_failed += 1;
                }
            }

            $p = $num_passed / ($num_failed + $num_passed);
            //$item_difficulty = -log($num_passed / $num_failed);
            $item_difficulty = -log($p / (1-$p+0.00001)); //TODO: numerical stability check
            $item_difficulties[$id] = $item_difficulty;

        }

        $this->item_difficulties = $item_difficulties;
    }

    /**
     * @return array<float>
     */
    public function get_item_difficulties(): array {
        return $this->item_difficulties;
    }
};