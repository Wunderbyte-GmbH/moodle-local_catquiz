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

use context_system;
use html_writer;
use local_catquiz\catmodel_info;
use local_catquiz\catquiz;
use local_catquiz\importer\testitemimporter;
use local_catquiz\local\model\model_person_param_list;
use local_catquiz\output\catscalemanager\scaleandcontexselector;
use moodle_url;
use stdClass;
use templatable;
use renderable;

/**
 * Renderable class for the catscalemanagers
 *
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH
 * @author     Georg Maißer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catscaledashboard {

    /**
     * Sets the maximum number of values used for the chart.
     * @var int
     */
    public const CHART_MAX_NUM = 100;

    /** @var int of catscaleid */
    public int $catscaleid = 0;

    /** @var int of catcontextid */
    private int $catcontextid = 0;

    /**
     * If set to true, we execute the CAT parameter estimation algorithm.
     *
     * @var bool
     */
    private bool $triggercalculation;

    /** @var stdClass|bool */
    private $catscale;

    /**
     * Either returns one tree or treearray for every parentnode
     *
     * @param int $catscaleid
     * @param int $catcontextid
     * @param bool $triggercalculation
     *
     */
    public function __construct(int $catscaleid, int $catcontextid = 0, bool $triggercalculation = false) {
        global $DB;

        $this->catscaleid = $catscaleid;
        $this->catcontextid = $catcontextid;
        $this->triggercalculation = $triggercalculation;
        $this->catscale = $DB->get_record(
            'local_catquiz_catscales',
            ['id' => $catscaleid]
        );
    }


    /**
     * Renders item difficulties.
     *
     * @param array $itemlists
     *
     * @return array
     *
     */
    private function render_itemdifficulties(array $itemlists) {

        global $OUTPUT;

        $charts = [];
        foreach ($itemlists as $modelname => $itemlist) {
            $data = $itemlist->get_values(true);
            // Skip empty charts.
            if (empty($data)) {
                continue;
            }

            // To keep the time required to render the chart reasonable, do not
            // display more values than required.
            $data = $this->filter_values($data);
            $chart = new \core\chart_line();
            $series = new \core\chart_series('Series 1 (Line)', array_values($data));
            $chart->set_smooth(true); // Calling set_smooth() passing true as parameter, will display smooth lines.
            $chart->add_series($series);
            $chart->set_labels(array_keys($data));
            $charts[] = ['modelname' => $modelname, 'chart' => html_writer::tag('div', $OUTPUT->render($chart), ['dir' => 'ltr'])];
        }

        return $charts;
    }

    /**
     * Render person abilities.
     *
     * @param model_person_param_list $personparams
     *
     * @return string
     *
     */
    private function render_personabilities(model_person_param_list $personparams) {
        global $OUTPUT;

        $data = array_map(
            fn ($pp) => $pp['ability'],
            $personparams->get_values(true)
        );
        if (empty($data)) {
            return "";
        }

        // To keep the time required to render the chart reasonable, do not
        // display more values than required.
        $data = $this->filter_values($data);

        $chart = new \core\chart_line();
        $series = new \core\chart_series('Series 1 (Line)', array_values($data));
        $chart->set_smooth(true); // Calling set_smooth() passing true as parameter, will display smooth lines.
        $chart->add_series($series);
        $chart->set_labels(array_keys($data));
        return html_writer::tag('div', $OUTPUT->render($chart), ['dir' => 'ltr']);
    }

    /**
     * Render file picker
     *
     * @return string
     *
     */
    public static function render_testitem_importer() {

        $inputform = new \local_catquiz\form\csvimport(null, null, 'post', '', [], true, testitemimporter::return_ajaxformdata());

        // Set the form data with the same method that is called when loaded from JS.
        // It should correctly set the data for the supplied arguments.
        $inputform->set_data_for_dynamic_submission();

        // Render the form in a specific container, there should be nothing else in the same container.
        return html_writer::div($inputform->render(), '', ['id' => 'lcq_csv_import_form']);
    }

    /**
     * Render file picker
     *
     * @return string
     *
     */
    public static function render_testitem_demodata() {
        $title = get_string('importcolumnsinfos', 'local_catquiz');
        $columnsarray = testitemimporter::export_columns_for_template();
        $url = new moodle_url('/local/catquiz/classes/importer/demo.csv');
        return [
            'title' => $title,
            'columns' => $columnsarray,
            'demofileurl' => $url->out(),
        ];
    }

    /**
     * Renders model button
     *
     * @param mixed $contextid
     *
     * @return string
     *
     */
    private function render_modelbutton($contextid) {
        $buttontitle = get_string('calculate', 'local_catquiz');
        return sprintf('<button class="btn btn-primary" type="button" data-contextid="%s" id="model_button">%s</button>',
                        $contextid, $buttontitle);
    }

    /**
     * Renders sync button
     *
     * @return string
     */
    private function render_syncbutton() {
        // Only render the button for root scales that have no parent scale.
        if (!$this->is_root_scale()) {
            return '';
        }

        $buttontitle = get_string('syncbutton', 'local_catquiz');
        return sprintf('<button class="btn btn-primary" type="button" id="sync_button">%s</button>', $buttontitle);
    }

    /**
     * Renders button to trigger calculation via submitted responses
     *
     * @return string
     */
    private function render_remotecalc_button() {
        // Only render the button for root scales that have no parent scale.
        if (!$this->is_root_scale()) {
            return '';
        }

        $buttontitle = get_string('remotecalcbutton', 'local_catquiz');
        return sprintf('<button class="btn btn-primary" type="button" id="recalculate_remote">%s</button>', $buttontitle);
    }

    /**
     * Renders button to share responses with central instance
     *
     * @return string
     */
    private function render_submitresponses_button() {
        // Only render the button for root scales that have no parent scale.
        if (!$this->is_root_scale()) {
            return '';
        }

        $buttontitle = get_string('remotesubmitbutton', 'local_catquiz');
        return sprintf('<button class="btn btn-primary" type="button" id="submit_responses_remote">%s</button>', $buttontitle);
    }

    /**
     * Shows if the current scale is a root scale
     *
     * @return bool
     */
    private function is_root_scale() {
        return ($this->catscale->parentid ?? NULL)  === "0";
    }

    /**
     * Exports for template.
     *
     * @param \renderer_base $output
     *
     * @return array
     *
     */
    public function export_scaledetails(\renderer_base $output): array {

        $cm = new catmodel_info;
        [$itemdifficulties, $personabilities] = $cm->get_context_parameters(
            $this->catcontextid,
            $this->catscaleid,
            $this->triggercalculation
        );

        $backbutton = [
            'label' => get_string('backtotable', 'local_catquiz'),
            'type' => 'button',
            'class' => "btn-link",
        ];

        return [
            'contextselector' => scaleandcontexselector::render_contextselector($this->catcontextid),
            'backtoscaleslink' => $backbutton,
            'scaledetailviewheading' => get_string('scaledetailviewheading', 'local_catquiz', $this->catscale->name),
            'itemdifficulties' => $this->render_itemdifficulties($itemdifficulties),
            'personabilities' => $this->render_personabilities($personabilities),
            'modelbutton' => $this->render_modelbutton($this->catcontextid),
            'syncbutton' => $this->render_syncbutton(),
            'remotecalcbutton' => $this->render_remotecalc_button(),
            'submitresponsesbutton' => $this->render_submitresponses_button(),
            'is_root' => $this->is_root_scale(),
            'centralhost' => get_config('local_catquiz', 'central_host'),
        ];
    }

    /**
     * If the given array contains more values than allowed, values are removed.
     *
     * This is used to remove values from the arrays of the ability and item difficulty charts if they contain too many.
     *
     * @param array $values
     * @param int $max
     * @return array
     */
    private function filter_values(array $values, int $max = self::CHART_MAX_NUM): array {
        if (count($values) <= $max) {
            return $values;
        }

        // Show every Nth value.
        $showeveryn = round(count($values) / $max);
        $i = 0;
        foreach (array_keys($values) as $key) {
            if ($i % $showeveryn != 0) {
                unset($values[$key]);
            }
            $i++;
        }
        return $values;
    }
}
