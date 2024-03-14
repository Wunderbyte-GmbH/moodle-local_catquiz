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

namespace local_catquiz\output\catscalemanager;

use local_catquiz\output\catcontextdashboard;
use local_catquiz\output\catscaledashboard;
use local_catquiz\output\catscalemanager\calculations\calculationsdisplay;
use local_catquiz\output\catscalemanagers;
use local_catquiz\output\catscales;
use local_catquiz\output\testitemdashboard;
use local_catquiz\output\catscalestats;
use local_catquiz\output\catscalemanager\questions\questionsdisplay;
use local_catquiz\output\catscalemanager\quizattempts\quizattemptsdisplay;
use local_catquiz\output\catscalemanager\testsandtemplates\testsandtemplatesdisplay;
use templatable;
use renderable;

/**
 * Renderable class for the catscalemanagers
 *
 * @package    local_catquiz
 * @copyright  2024 Wunderbyte GmbH
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
     * @var array
     */
    private array $catscalesdetailview = [];

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
     * @var array $testsandtemplatesdisplay.
     */
    public ?array $testsandtemplatesdisplay = [];

    /**
     *
     * @var array $quizattemptsdisplay
     */
    public array $quizattemptsdisplay = [];

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
     *
     * @var string $eventlogtable.
     */
    public ?string $eventlogtable = "";

    /**
     *
     * @var array $calculationsdisplay.
     */
    public ?array $calculationsdisplay = [];

    /**
     *
     * @var array $versioningdisplay
     */
    public ?array $versioningdisplay = [];

    /**
     * Constructor.
     *
     * @param int $testitemid
     * @param int $contextid
     * @param int $catscaleid
     * @param int $scaledetailview
     * @param int $usesubs
     * @param string $componentname
     *
     */
    public function __construct(
        int $testitemid,
        int $contextid,
        int $catscaleid,
        int $scaledetailview,
        int $usesubs,
        string $componentname) {

        $this->testitemid = $testitemid;
        $this->contextid = $contextid;
        $this->catscaleid = $catscaleid;
        $this->usesubs = $usesubs;
        $this->componentname = $componentname;

        $catscales = new catscales($this->catscaleid, $scaledetailview, $this->contextid);
        $this->catscalesarray = $catscales->return_as_array();
        $this->catscalesdetailview = $catscales->return_detailview();

        $catscalemanagers = new catscalemanagers();
        $this->catscalemanagersarray = $catscalemanagers->return_as_array();

        $questionsdisplay = new questionsdisplay(
            $this->testitemid,
            $this->contextid,
            $this->catscaleid,
            $this->usesubs,
            $this->componentname);
        $this->questionsdisplayarray = $questionsdisplay->export_data_array();

        $testenvironmentdashboard = new testsandtemplatesdisplay($this->catscaleid, $this->usesubs, $this->componentname);
        $this->testsandtemplatesdisplay = $testenvironmentdashboard->export_data_array();

        $quizattempts = new quizattemptsdisplay();
        $this->quizattemptsdisplay = $quizattempts->export_data_array();

        $catscalestats = new catscalestats();
        $this->catscalestatsarray = $catscalestats->export_data_array();

        if (!empty($this->catscaleid)
            && !empty($this->testitemid)
            && !empty($this->contextid)) {
                $testitemdashboard = new testitemdashboard(
                    $this->testitemid,
                    $this->contextid,
                    $this->catscaleid,
                    $this->componentname);
                $this->testitemdashboardarray = $testitemdashboard->return_as_array();
        }

        $eventlogtable = new eventlogtableinstance();
        $this->eventlogtable = $eventlogtable->render_event_log_table();

        $calculationsdisplay = new calculationsdisplay();
        $this->calculationsdisplay = $calculationsdisplay->export_data_array();

        $versioningdisplay = new catcontextdashboard();
        $this->versioningdisplay = $versioningdisplay->return_array();
    }

    /**
     * Export for template.
     *
     * @param \renderer_base $output
     *
     * @return array
     *
     */
    public function export_for_template(\renderer_base $output): array {

        foreach ($this->catscalesarray as $key => $value) {
            $id = $this->catscalesarray[$key]['id'];
            $this->catscalesarray[$key]['image'] = $output->get_generated_image_for_id($id);
        }

        $data = [
            'itemtree' => $this->catscalesarray,
            'catscaledetailview' => $this->catscalesdetailview,
            'catscalemanagers' => $this->catscalemanagersarray,
            'questionsdisplay' => $this->questionsdisplayarray,
            'testsandtemplatesdisplay' => $this->testsandtemplatesdisplay,
            'quizattemptsdisplay' => $this->quizattemptsdisplay,
            'catscalestats' => $this->catscalestatsarray,
            'testitemdashboard' => $this->testitemdashboardarray,
            'eventlogtable' => $this->eventlogtable,
            'testitemsimporter' => catscaledashboard::render_testitem_importer(),
            'testitemsimporterdemodata' => catscaledashboard::render_testitem_demodata(),
            'calculationsdisplay' => $this->calculationsdisplay,
            'catcontextdisplay' => $this->versioningdisplay,
        ];
        return $data;
    }
}
