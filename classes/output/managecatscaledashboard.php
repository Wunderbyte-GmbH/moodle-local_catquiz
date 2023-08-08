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

use coding_exception;
use context_system;
use html_writer;
use local_catquiz\catmodel_info;
use local_catquiz\catquiz;
use local_catquiz\form\item_model_override_selector;
use local_catquiz\local\model\model_item_param;
use local_catquiz\local\model\model_raschmodel;
use local_catquiz\local\model\model_strategy;
use local_catquiz\output\catscalestats;
use moodle_url;
use templatable;
use renderable;
use stdClass;

/**
 * Renderable class for the catscalemanagers
 *
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH
 * @author     Georg Maißer, Magdalena Holczik
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class managecatscaledashboard implements renderable, templatable {

    /** @var integer of testitemid */
    public int $testitemid = 0;

    /**
     * @var integer
     */
    private int $contextid = 0;

    /**
     * @var integer
     */
    private int $catscaleid = 0;

    /**
     * @var integer
     */
    private int $usesubs = 1;

    /**
     * @var string
     */
    private string $componentname = 'question';

    /**
     *
     * @var array $catscales.
     */
    public ?array $catscalesarray = [];

    /**
     *
     * @var array $catscalemanagers.
     */
    public ?array $catscalemanagersarray = [];

    /**
     *
     * @var array $questionsdisplay.
     */
    public ?array $questionsdisplayarray = [];

    /**
     *
     * @var array $atscalestats.
     */
    public ?array $catscalestatsarray = [];

    /**
     *
     * @var array $testitemdashboard.
     */
    public ?array $testitemdashboardarray = [];

    /**
     * Either returns one tree or treearray for every parentnode
     *
     * @param int $fulltree
     * @param boolean $allowedit
     * @param int $contextid
     * @return array
     */
    public function __construct(int $testitemid, int $contextid, int $catscaleid, int $usesubs, string $componentname) {

        $this->testitemid = $testitemid;
        $this->contextid = $contextid;
        $this->catscaleid = $catscaleid;
        $this->usesubs = $usesubs;
        $this->componentname = $componentname;

        $catscales = new catscales();
        $this->catscalesarray = $catscales->return_as_array();

        $catscalemanagers = new catscalemanagers();
        $this->catscalemanagersarray = $catscalemanagers->return_as_array();

        $questionsdisplay = new questionsdisplay($this->testitemid, $this->contextid, $this->catscaleid, $this->usesubs, $this->componentname);
        $this->questionsdisplayarray = $questionsdisplay->export_data_array();

        $catscalestats = new catscalestats();
        $this->catscalestatsarray = $catscalestats->export_data_array();

        if (!empty($this->catscaleid)
            && !empty($this->testitemid)
            && !empty($this->contextid)) {
                $testitemdashboard = new testitemdashboard($this->testitemid, $this->contextid, $this->catscaleid);
                $this->testitemdashboardarray = $testitemdashboard->return_as_array();
        }

    }


    /**
     * Return the item tree of all catscales.
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {

        foreach ($this->catscalesarray as $key => $value) {
            $id = $this->catscalesarray[$key]['id'];
            $this->catscalesarray[$key]['image'] = $output->get_generated_image_for_id($id);
        }

        $data = [
            'itemtree' =>  $this->catscalesarray,
            'catscalemanagers' => $this->catscalemanagersarray,
            'questionsdisplay' => $this->questionsdisplayarray,
            'catscalestats' => $this->catscalestatsarray,
            'testitemdetails' => $this->testitemdashboardarray,
        ];
        return $data;
    }
}
