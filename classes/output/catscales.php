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

        // This used to walk the complete scale list once per level and ask
        // the database for the subscription state of every single scale. That is O(n)
        // queries and O(n^2) PHP work, both growing with the number of scales.
        //
        // Two preparations remove that: the subscriptions of this user are fetched in
        // one query, and the scales are grouped by their parent id once, so each level
        // only looks at its own children. The traversal order within a level is the
        // order of $elements, exactly as before.
        $subscribed = subscription::return_subscribed_itemids($USER->id, 'catscale');
        $childrenbyparent = self::group_by_parent($elements);

        return $this->build_branch($childrenbyparent, $subscribed, $parentid);
    }

    /**
     * Groups the scales by their parent id, preserving the original order.
     *
     * @param array $elements
     * @return array<int, array>
     */
    private static function group_by_parent(array $elements): array {
        $childrenbyparent = [];
        foreach ($elements as $catscaleitem) {
            $element = get_object_vars($catscaleitem);
            $childrenbyparent[(int) $element['parentid']][] = $element;
        }

        return $childrenbyparent;
    }

    /**
     * Builds one branch of the tree from the prepared maps.
     *
     * @param array $childrenbyparent Scales grouped by parent id.
     * @param array $subscribed Subscribed item ids as keys.
     * @param int $parentid
     * @return array
     */
    private function build_branch(array $childrenbyparent, array $subscribed, int $parentid): array {
        $branch = [];

        foreach ($childrenbyparent[$parentid] ?? [] as $element) {
            $element['subscribed'] = isset($subscribed[(int) $element['id']]);

            // Empty array rather than null: the Mustache template loops over children
            // and would otherwise recurse endlessly.
            $element['children'] = $this->build_branch($childrenbyparent, $subscribed, (int) $element['id']);

            $branch[] = $element;
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
        $out = $this->itemtree;

        // One grouped query instead of one count per scale.
        $counts = catquiz::get_number_of_questions_per_scale();
        // How many items of this scale carry unusable parameters, in the
        // context being looked at. Without the context the count mixed every context
        // of the installation into one number, which is not what the page shows.
        $unusable = catquiz::get_unusable_item_counts_per_scale($this->contextid ?: null);

        foreach ($out as &$item) {
            $item['image'] = $output->get_generated_image_for_id($item['id']);
            $item['numberofchildren'] = count($item['children']);
            $item['numberofquestions'] = $counts[(int) $item['id']] ?? 0;
            $item['numberofunusableitems'] = $unusable[(int) $item['id']] ?? 0;
            $item['hasunusableitems'] = !empty($unusable[(int) $item['id']]);
            // Deep link into the question list, pre-filtered to the unusable items:
            // a number without a way to act on it makes somebody hunt for the rows.
            $item['unusablelink'] = (new \moodle_url('/local/catquiz/manage_catscales.php', [
                // Without the tab the link lands on the default one, where the list
                // it is supposed to filter is not rendered at all.
                'tab' => 'questions',
                'scaleid' => (int) $item['id'],
                'contextid' => $this->contextid,
                'usable' => 0,
            ]))->out(false);
        }
        return $out;
    }

    /**
     * Return the item tree of all catscales as array.
     * @return array
     */
    public function return_as_array(): array {
        $out = $this->itemtree;

        // One grouped query instead of one count per scale.
        $counts = catquiz::get_number_of_questions_per_scale();
        // How many items of this scale carry unusable parameters, in the
        // context being looked at. Without the context the count mixed every context
        // of the installation into one number, which is not what the page shows.
        $unusable = catquiz::get_unusable_item_counts_per_scale($this->contextid ?: null);

        foreach ($out as &$item) {
            $item['numberofchildren'] = count($item['children']);
            $item['numberofquestions'] = $counts[(int) $item['id']] ?? 0;
            $item['numberofunusableitems'] = $unusable[(int) $item['id']] ?? 0;
            $item['hasunusableitems'] = !empty($unusable[(int) $item['id']]);
            // Deep link into the question list, pre-filtered to the unusable items:
            // a number without a way to act on it makes somebody hunt for the rows.
            $item['unusablelink'] = (new \moodle_url('/local/catquiz/manage_catscales.php', [
                'scaleid' => (int) $item['id'],
                'contextid' => $this->contextid,
                'usable' => 0,
            ]))->out(false);
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
