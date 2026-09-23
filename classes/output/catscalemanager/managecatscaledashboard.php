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
    /**
     * Tabs of the manager, in the order the template renders them.
     *
     * The constructor used to build every one of these regardless of which
     * was on screen - eight displays, each with its own queries, for one visible tab.
     * The list is kept here so the tab parameter can be validated against it rather
     * than trusted.
     *
     * @var string[]
     */
    const TABS = [
        'summary',
        'catscales',
        'questions',
        'testsandtemplates',
        'quizattempts',
        'importer',
        'calculations',
        'versioning',
    ];

    /**
     * The tab currently being rendered.
     * @var string
     */
    private string $activetab = 'summary';
    /** @var int of testitemid */
    public int $testitemid = 0;

    /**
     * @var int
     */
    private int $contextid = 0;

    /**
     * @var int
     */
    private int $catscaleid = 0;

    /**
     * @var array
     */
    private array $catscalesdetailview = [];

    /**
     * @var int
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
     * @param string $activetab
     *
     */
    public function __construct(
        int $testitemid,
        int $contextid,
        int $catscaleid,
        int $scaledetailview,
        int $usesubs,
        string $componentname,
        string $activetab = ''
    ) {

        // An unknown or absent value falls back to the first tab rather
        // than building everything. Validating against the list keeps a crafted URL
        // parameter from selecting something that does not exist.
        $this->activetab = in_array($activetab, self::TABS, true) ? $activetab : self::TABS[0];

        $this->testitemid = $testitemid;
        $this->contextid = $contextid;
        $this->catscaleid = $catscaleid;
        $this->usesubs = $usesubs;
        $this->componentname = $componentname;

        if ($this->activetab === 'catscales') {
            $catscales = new catscales($this->catscaleid, $scaledetailview, $this->contextid);
            $this->catscalesarray = $catscales->return_as_array();
            $this->catscalesdetailview = $catscales->return_detailview();
        }

        if ($this->activetab === 'catscales') {
            $catscalemanagers = new catscalemanagers();
            $this->catscalemanagersarray = $catscalemanagers->return_as_array();
        }

        if ($this->activetab === 'questions') {
            $questionsdisplay = new questionsdisplay(
                $this->testitemid,
                $this->contextid,
                $this->catscaleid,
                $this->usesubs,
                $this->componentname
            );
            $this->questionsdisplayarray = $questionsdisplay->export_data_array();
        }

        if ($this->activetab === 'testsandtemplates') {
            $testenvironmentdashboard = new testsandtemplatesdisplay($this->catscaleid, $this->usesubs, $this->componentname);
            $this->testsandtemplatesdisplay = $testenvironmentdashboard->export_data_array();
        }

        if ($this->activetab === 'quizattempts') {
            $quizattempts = new quizattemptsdisplay();
            $this->quizattemptsdisplay = $quizattempts->export_data_array();
        }

        if ($this->activetab === 'summary') {
            $catscalestats = new catscalestats();
            $this->catscalestatsarray = $catscalestats->export_data_array();
        }

        // The item detail belongs to the questions tab: it is reached by selecting a
        // question there. The existing conditions stay - they guard against building
        // it without a selection - and the tab is added to them.
        if (
            $this->activetab === 'questions'
            && !empty($this->catscaleid)
            && !empty($this->testitemid)
            && !empty($this->contextid)
        ) {
                $testitemdashboard = new testitemdashboard(
                    $this->testitemid,
                    $this->contextid,
                    $this->catscaleid,
                    $this->componentname
                );
                $this->testitemdashboardarray = $testitemdashboard->return_as_array();
        }

        if ($this->activetab === 'summary') {
            $eventlogtable = new eventlogtableinstance();
            $this->eventlogtable = $eventlogtable->render_event_log_table();
        }

        if ($this->activetab === 'calculations') {
            $calculationsdisplay = new calculationsdisplay();
            $this->calculationsdisplay = $calculationsdisplay->export_data_array();
        }

        if ($this->activetab === 'versioning') {
            $versioningdisplay = new catcontextdashboard();
            $this->versioningdisplay = $versioningdisplay->return_array();
        }
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
            // Mustache cannot build names dynamically, so each tab gets its own flag
            // and URL. The flag decides which pane is rendered at all - the others
            // have no data behind them, because they were never built.
            'issummary' => $this->activetab === 'summary',
            'iscatscales' => $this->activetab === 'catscales',
            'isquestions' => $this->activetab === 'questions',
            'istestsandtemplates' => $this->activetab === 'testsandtemplates',
            'isquizattempts' => $this->activetab === 'quizattempts',
            'isimporter' => $this->activetab === 'importer',
            'iscalculations' => $this->activetab === 'calculations',
            'isversioning' => $this->activetab === 'versioning',
            'summaryurl' => $this->tab_url('summary'),
            'catscalesurl' => $this->tab_url('catscales'),
            'questionsurl' => $this->tab_url('questions'),
            'testsandtemplatesurl' => $this->tab_url('testsandtemplates'),
            'quizattemptsurl' => $this->tab_url('quizattempts'),
            'importerurl' => $this->tab_url('importer'),
            'calculationsurl' => $this->tab_url('calculations'),
            'versioningurl' => $this->tab_url('versioning'),
            'itemtree' => $this->catscalesarray,
            'catscaledetailview' => $this->catscalesdetailview,
            'catscalemanagers' => $this->catscalemanagersarray,
            'questionsdisplay' => $this->questionsdisplayarray,
            'testsandtemplatesdisplay' => $this->testsandtemplatesdisplay,
            'quizattemptsdisplay' => $this->quizattemptsdisplay,
            'catscalestats' => $this->catscalestatsarray,
            'testitemdashboard' => $this->testitemdashboardarray,
            'eventlogtable' => $this->eventlogtable,
            // Building this form registers Moodle's form validation JS,
            // which looks up the form by id on load. With only the active tab
            // rendered the element is absent, the lookup returns null and the script
            // throws - leaving the page permanently "not ready". That is what made
            // seven Behat scenarios fail at a step that has nothing to do with the
            // importer.
            'testitemsimporter' => $this->activetab === 'importer'
                ? catscaledashboard::render_testitem_importer()
                : '',
            'testitemsimporterdemodata' => catscaledashboard::render_testitem_demodata(),
            'calculationsdisplay' => $this->calculationsdisplay,
            'catcontextdisplay' => $this->versioningdisplay,
        ];
        return $data;
    }
    /**
     * Returns the URL of a tab, carrying the state the manager is looking at.
     *
     * Without context, scale and usesubs in the link, switching tabs would silently
     * reset what the user had selected.
     *
     * @param string $tab
     * @return string
     */
    private function tab_url(string $tab): string {
        return (new \moodle_url('/local/catquiz/manage_catscales.php', [
            'tab' => $tab,
            'contextid' => $this->contextid,
            'scaleid' => $this->catscaleid,
            'usesubs' => $this->usesubs,
        ]))->out(false);
    }
}
