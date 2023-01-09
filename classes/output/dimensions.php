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

    /** @var array of objects including all items */
    public array $items;

    /** @var array $fullitemtree associative array item tree */
    public array $itemtree;

    public array $branchitems;

    /**
     * Either returns one tree or treearray for every parentnode
     *
     * @param int $fulltree
     * @param boolean $allowedit
     * @return array
     */
    public function __construct(bool $allowedit = true) {
        $this->items = dataapi::get_all_dimensions();
        $this->build_tree($this->items);
        $this->itemtree = $this->branchitems[0];
    }

    /**
     * Build full item tree. All children are marked as 'children' in the parent item.
     *
     * @param array $items
     * @param int $parentid
     * @return
     *
     */
    public function build_tree(array $elements, int $parentid = 0) {
        $branch = array();

        foreach ($elements as $element) {
            if ($element['parentid'] == $parentid) {
                $children = $this->build_tree($elements, $element['id']);
                if ($children) {
                    $element['children'] = $children;
                } else {
                    $element['children'] = [];
                }
                $branch[] = $element;
            }
        }
        $this->branchitems[$parentid] = $branch;
        return $branch;
    }

    /**
     * Return the item tree of all dimensions.
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        return $this->itemtree;
    }
}
