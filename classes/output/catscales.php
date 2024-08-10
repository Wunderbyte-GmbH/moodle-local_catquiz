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

namespace local_catquiz\output;

use html_writer;
use local_catquiz\catquiz;
use local_catquiz\data\dataapi;
use local_catquiz\output\catscaledashboard;
use local_catquiz\subscription;
use templatable;
use renderable;

/**
 * Renderable class for the catscales page
 *
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH
 * @author     David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catscales implements renderable, templatable {

    /** @var array of objects including all items */
    public array $items;

    /** @var array $fullitemtree associative array item tree */
    public array $itemtree;

    /** @var array $branchitems */
    public array $branchitems;
    /**
     * @var int
     */
    private int $catscaleid = 0;

    /** If set to 1, detailview of selected scale will be rendered.
     * @var int
     */
    private int $scaledetailview = 0;

    /**
     * @var int
     */
    private int $contextid = 0;

    /**
     * Constructor.
     *
     * @param mixed $catscaleid
     * @param mixed $scaledetailview
     * @param mixed $contextid
     * @param bool $allowedit
     *
     */
    public function __construct(&$catscaleid, &$scaledetailview, &$contextid, bool $allowedit = true) {

        $this->catscaleid = $catscaleid;
        $this->scaledetailview = $scaledetailview;
        $this->contextid = $contextid;

        $this->items = dataapi::get_all_catscales();
        $this->build_tree($this->items);
        $this->itemtree = $this->branchitems[0];
    }

    /**
     * Build full item tree. All children are marked as 'children' in the parent item.
     *
     * @param array $elements
     * @param int $parentid
     *
     * @return array
     *
     */
    public function build_tree(array $elements, int $parentid = 0): array {

        global $USER;

        $branch = [];

        foreach ($elements as $catscaleitem) {
            // Transform object catscale_structur into array, which is needed here.
            $element = get_object_vars($catscaleitem);

            // Walk only elements on the current node, meaning with the given parentid
            if ($element['parentid'] !== $parentid) { continue; }

            if ($subscribed = subscription::return_subscription_state($USER->id, 'catscale', $element['id'])) {
                $element['subscribed'] = true;
            } else {
                $element['subscribed'] = false;
            }

            if ($element['parentid'] == $parentid) {
                $children = $this->build_tree($elements, $element['id']);
                if ($children) {
                    $element['children'] = $children;
                } else {
                    // Add empty array. That is needed for mustache templated in order to avoid infinit loop.
                    $element['children'] = [];
                }
                $branch[] = $element;
            }
        }
        $this->branchitems[$parentid] = $branch;
        return $branch;
    }

    /**
     * Return the item tree of all catscales for template.
     *
     * @param \renderer_base $output
     *
     * @return array
     *
     */
    public function export_for_template(\renderer_base $output): array {
        global $DB;
        $out = $this->itemtree;
        foreach ($out as &$item) {
            $item['image'] = $output->get_generated_image_for_id($item['id']);
            $item['numberofchildren'] = count($item['children']);
            list($sql, $params) = catquiz::get_sql_for_number_of_questions_in_scale($item['id']);
            $item['numberofquestions'] = $DB->count_records_sql($sql, $params);
        }
        return $out;
    }

    /**
     * Return the item tree of all catscales as array.
     * @return array
     */
    public function return_as_array(): array {
        global $DB;
        $out = $this->itemtree;
        foreach ($out as &$item) {
            $item['numberofchildren'] = count($item['children']);
            list($sql, $params) = catquiz::get_sql_for_number_of_questions_in_scale($item['id']);
            $item['numberofquestions'] = $DB->count_records_sql($sql, $params);
        }

        return $out;
    }
    /**
     * Return the item tree of all catscales as array.
     * @return array
     */
    public function return_detailview(): array {
        global $OUTPUT;

        $out = [];
        // Check if we have a detailview and if so, show data.
        if ($this->catscaleid != -1 && $this->scaledetailview == 1) {
            $catscaledashboard = new catscaledashboard($this->catscaleid, $this->contextid);
            $out['scaledetailview'] = $catscaledashboard->export_scaledetails($OUTPUT);
        } else {
            $out['scaledetailview'] = "";
        }
        return $out;
    }

}
