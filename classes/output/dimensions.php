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

namespace local_catquiz\output;

use local_catquiz\data\dataapi;
use templatable;
use renderable;

/**
 * Renderable class for the dimensions page
 *
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH
 * @author     David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dimensions implements renderable, templatable {

    /** @var array all items */
    public array $items;

    /** @var array $fullitemtree hierarchical item tree */
    public array $itemtree;

    /**
     * Either returns one tree or treearray for every parentnode
     *
     * @param int $fulltree
     * @param boolean $allowedit
     * @return array
     */
    public function __construct(bool $allowedit = true) {
        $this->items = dataapi::get_all_dimensions();
        $children = [];
        foreach ($this->items as $item) {
            $item->parentid = $item->parentid ? : 0;
            $item->allowedit = $allowedit;
            $children[$item->parentid][] = $item;
        }
        foreach ($this->items as $item) {
            if (isset($children[$item->id])) {
                $item->childs = $children[$item->id];
            } else {
                $item->children = false;
                $item->leaf = true;
            }
        }
        $this->itemtree = $children;
    }

    /**
     * Return the item tree of all dimensions.
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        return $this->itemtree;
    }
}
