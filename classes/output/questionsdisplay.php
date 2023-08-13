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
use local_catquiz\catscale;
use local_catquiz\table\catscalequestions_table;
use moodle_url;

/**
 * Renderable class for the catscalemanagers
 *
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH
 * @author     Magdalena Holczik
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class questionsdisplay {
    /**
     * @var integer
     */
    private int $catcontextid = 0; // Selected context.

    /**
     * @var integer
     */
    private int $scale = 0; // The selected scale.

    /**
     * @var integer
     */
    private int $detailsscale = 0; // The most detailed child of scale.

    /**
     * @var integer
     */
    private int $usesubs = 1; // If subscales should be integrated in question display, value is 1.

    /**
     * @var integer
     */
    private int $numberofrecords = 0; // Records found in table query.

    /**
     * @var integer
     */
    private ?int $testitemid = null; // ID of testitem.

    /**
     * @var string
     */
    private string $componentname = 'question'; // Componentname of the testitem.

    /**
     * Constructor.
     *
     * @param int $testitemid
     * @param int $contextid
     * @param int $catscaleid
     * @param int $usesubs
     * @param string $componentname
     *
     */
    public function __construct(int $testitemid, int $contextid, int $catscaleid = 0, int $usesubs = 1, string $componentname = 'question') {
        $this->catcontextid = $contextid;
        $this->scale = $catscaleid;
        $this->usesubs = $usesubs;
        $this->testitemid = $testitemid; // ID of record to be displayed in detail instead of table.
        $this->componentname = $componentname; // ID of record to be displayed in detail instead of table.

    }

    /**
     * Renders the context selector.
     * @return string
     */
    private function render_contextselector() {
        $ajaxformdata = empty($this->catcontextid) ? [] : ['contextid' => $this->catcontextid];

        $customdata = [
            "hideheader" => true,
            "hidelabel" => false,
            "labeltext" => get_string('versionchosen', 'local_catquiz'),
        ];

        $form = new \local_catquiz\form\contextselector(null, $customdata, 'post', '', [], true, $ajaxformdata);
        // Set the form data with the same method that is called when loaded from JS.
        // It should correctly set the data for the supplied arguments.
        $form->set_data_for_dynamic_submission();
        // Render the form in a specific container, there should be nothing else in the same container.
        return html_writer::div($form->render(), '', ['id' => 'select_context_form']);
    }

    /**
     * Renders the scale selector.
     * @return string
     */
    private function render_scaleselectors() {
        $selectors = $this->render_selector($this->scale);
        $ancestorids = catscale::get_ancestors($this->scale);
        if (count($ancestorids) > 0) {
            foreach ($ancestorids as $ancestorid) {
                $selector = $this->render_selector($ancestorid);
                $selectors = "$selector <br> $selectors";
            }
        }
        $childids = catscale::get_subscale_ids($this->scale);
        if (count($childids) > 0) {
            // If the selected scale has subscales, we render a selector to choose them with no default selection.
            $subscaleselector = $this->render_selector($childids[0], true);
            $selectors .= "<br> $subscaleselector";
        }
        return $selectors;
    }

    /**
     * Renders the scale selector.
     *
     * @param mixed $scaleid
     * @param bool $noselection
     * @param string $label
     *
     * @return string
     *
     */
    private function render_selector($scaleid, $noselection = false, $label = 'selectcatscale') {
        $selected = $noselection ? 0 : $scaleid;
        $ajaxformdata = [
                        'scaleid' => $scaleid,
                        'selected' => $selected,
                        ];
        $customdata = [
            'type' => 'scale',
            'label' => $label, // String localized in 'local_catquiz'.
        ];

        $form = new \local_catquiz\form\scaleselector(null, $customdata, 'post', '', [], true, $ajaxformdata);
        // Set the form data with the same method that is called when loaded from JS.
        // It should correctly set the data for the supplied arguments.
        $form->set_data_for_dynamic_submission();
        // Render the form in a specific container, there should be nothing else in the same container.
        return html_writer::div($form->render(), '', ['id' => 'select_scale_form_scaleid_' . $scaleid]);
    }

    /**
     * If checked subscales are integrated in the table query.
     * @return array
     */
    private function render_subscale_checkbox() {
        $checked = "checked";
        if ($this->usesubs < 1) {
            $checked = "";
        }
        $checkboxarray = [
            'label' => get_string('integratequestions', 'local_catquiz'),
            'checked' => $checked,
        ];

        return $checkboxarray;
    }

    /**
     * Render questions table.
     * @return ?string
     */
    public function renderquestionstable() {
        global $DB;
        if ($this->scale === -1) {
            return $this->get_no_table_string();
        }
        // If no context is set, get default context from DB.
        $catcontext = empty($this->catcontextid) ? catquiz::get_default_context_id() : $this->catcontextid;

        $table = new catscalequestions_table('catscale_' . $this->scale . ' questionstable', $this->scale, $catcontext);

        // If we integrate questions from subscales, we add different ids.
        if ($this->usesubs > 0) {
            $subscaleids = catquiz::get_subscale_ids_from_parent(
                [$this->scale]
            );
            $idsforquery = array_keys($subscaleids);
            array_push($idsforquery, $this->scale);
        } else {
            $idsforquery = [$this->scale];
        }

        list($select, $from, $where, $filter, $params) = catquiz::return_sql_for_catscalequestions($idsforquery, $catcontext, [], []);

        $table->set_filter_sql($select, $from, $where, $filter, $params);

        $columnsarray = [
            'status' => get_string('status', 'core'),
            'qtype' => get_string('type', 'local_catquiz'),
            'name' => get_string('name', 'core'),
            'model' => get_string('model', 'local_catquiz'),
            'attempts' => get_string('attempts', 'local_catquiz'),
            'lastattempttime' => get_string('lastattempttime', 'local_catquiz'),
            'difficulty' => get_string('difficulty', 'local_catquiz'),
            'action' => get_string('action', 'local_catquiz'),
        ];
        $table->define_columns(array_keys($columnsarray));
        $table->define_headers(array_values($columnsarray));

        $sortcolumns = $columnsarray;
        unset($sortcolumns['action']);
        $table->define_sortablecolumns(array_keys($sortcolumns));

        $table->addcheckboxes = true;

        $table->actionbuttons[] = [
            // The 'NoModal, MultipleCall, NoSelection'-> Name of your action button.
            'label' => get_string('addquestion', 'local_catquiz'),
            'class' => 'btn btn-primary', // Example colors bootstrap 4 classes.
            'href' => '#',
            'methodname' => 'addquestion', // The method needs to be added to your child of wunderbyte_table class.
            'nomodal' => true, // If set to true, there is no modal and the method will be called directly.
            'selectionmandatory' => false, // When set to true, action will only be triggered, if elements are selected.
            'data' => [ // Will be added eg as data-id = $values->id, so values can be transmitted to the method above.
                'id' => 'id',
                'component' => 'local_wunderbyte_table', // Localization of strings.
            ]
        ];
        $table->actionbuttons[] = [
            // The 'NoModal, MultipleCall, NoSelection'-> Name of your action button.
            'label' => get_string('addtest', 'local_catquiz'),
            'class' => 'btn btn-primary', // Example colors bootstrap 4 classes.
            'href' => '#',
            'methodname' => 'addtest', // The method needs to be added to your child of wunderbyte_table class.
            'nomodal' => true, // If set to true, there is no modal and the method will be called directly.
            'selectionmandatory' => false, // When set to true, action will only be triggered, if elements are selected.
            'data' => [ // Will be added eg as data-id = $values->id, so values can be transmitted to the method above.
                'id' => 'id',
                'component' => 'local_wunderbyte_table', // Localization of strings.
            ]
        ];
        $table->actionbuttons[] = [
            // The 'NoModal, MultipleCall, NoSelection'-> Name of your action button.
            'label' => get_string('checklinking', 'local_catquiz'),
            'class' => 'btn btn-primary', // Example colors bootstrap 4 classes.
            'href' => '#',
            'methodname' => 'addquestion', // The method needs to be added to your child of wunderbyte_table class.
            'nomodal' => true, // If set to true, there is no modal and the method will be called directly.
            'selectionmandatory' => false, // When set to true, action will only be triggered, if elements are selected.
            'data' => [ // Will be added eg as data-id = $values->id, so values can be transmitted to the method above.
                'id' => 'id',
                'component' => 'local_wunderbyte_table', // Localization of strings.
            ]
        ];
        $table->placebuttonandpageelementsontop = true;

        $table->tabletemplate = 'local_wunderbyte_table/twtable_list';
        $table->define_cache('local_catquiz', 'testitemstable');

        $table->pageable(true);

        $table->showcountlabel = true;
        $table->showdownloadbutton = false;
        $table->showreloadbutton = false;
        $table->showrowcountselect = true;

        $table->filteronloadinactive = true;

        $table->showdownloadbutton = true;
        $table->define_baseurl(new moodle_url('/local/catquiz/download.php'));

        $output = $table->outhtml(10, true);
        $this->numberofrecords = $table->return_records_count()[0];
        if ($this->numberofrecords > 0) { // Only if the table contains records, we will return it.
            return $output;
        } else {
            return null;
        }
    }
    /**
     * When there is no table to display, return the right message.
     * @return string
     */
    private function get_no_table_string() {
        if ($this->scale == 0) {
            return get_string('noscaleselected', 'local_catquiz');
        } else if ($this->numberofrecords == 0) {
            return get_string('norecordsfound', 'local_catquiz');
        } else {
            return "";
        }
    }
    /**
     * Check if we display a table or a detailview of a specific item.
     * @return string
     */
    private function check_tabledisplay() {
        $output = "";
        if (empty($this->testitemid)) {
            $output = empty($this->renderquestionstable()) ? $this->get_no_table_string() : $this->renderquestionstable();
        }
        return $output;
    }

    /**
     * Return the item tree of all catscales.
     * @return array
     */
    public function export_data_array(): array {

        $data = [
            'contextselector' => $this->render_contextselector(),
            'scaleselectors' => empty($this->render_scaleselectors()) ? "" : $this->render_scaleselectors(),
            'checkbox' => $this->render_subscale_checkbox(),
            'table' => $this->check_tabledisplay(),
        ];

        return $data;
    }
}
