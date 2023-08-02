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

use html_writer;
use local_catquiz\catquiz;
use local_catquiz\table\catscalequestions_table;
use moodle_url;
use templatable;
use renderable;


/**
 * Renderable class for the catscalemanagers
 *
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH
 * @author     Magdalena Holczik
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class questionsdisplay implements renderable, templatable {
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
    private int $tablescale = 0; // Scale used for tabledisplay. Can be scale or subscale.

    /**
     * @var integer
     */
    private int $subscale = -1; // If subscales should be integrated in question display, value is 1.

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
    private ?int $detailid = null; // Records found in table query.

    /**
     *
     * @return void
     */
    public function __construct() {
        $this->catcontextid = optional_param('contextid', 0, PARAM_INT);
        $this->scale = optional_param('scale', -1, PARAM_INT);
        $this->subscale = optional_param('subscale', 0, PARAM_INT);
        $this->usesubs = optional_param('usesubs', 1, PARAM_INT);
        $this->detailid = optional_param('detail', null, PARAM_INT); // ID of record to be displayed in detail instead of table.

        // If a subscale is selected, we assign it for further use (i.e. to fetch the records for the table).
        // Otherwise we are using the parentscale variable.
        if ($this->subscale > 0) {
            $this->tablescale = $this->subscale;
        } else {
            $this->tablescale = $this->scale;
        }
    }
    /**
     * Renders the context selector.
     * @return string
     */
    private function render_contextselector()
    {
        $ajaxformdata = empty($this->catcontextid) ? [] : ['contextid' => $this->catcontextid];

        $customdata = [
            "hideheader" => true,
            "hidelabel" => false,
            "labeltext" => get_string('versionchosen', 'local_catquiz'),
        ];

        $form = new \local_catquiz\form\contextselector(null, $customdata, 'post', '', [], true, $ajaxformdata);
        // Set the form data with the same method that is called when loaded from JS. It should correctly set the data for the supplied arguments.
        $form->set_data_for_dynamic_submission();
        // Render the form in a specific container, there should be nothing else in the same container.
        return html_writer::div($form->render(), '', ['id' => 'select_context_form']);
    }

    /**
     * Renders the scale selector.
     * @return string
     */
    private function render_scaleselector()
    {
        $scaleid = empty($this->scale) ? -1 : ['scale' => $this->scale];

        $customdata = [
            'type' => 'scale',
            'label' => get_string('selectcatscale', 'local_catquiz'),
        ];

        $form = new \local_catquiz\form\scaleselector(null, $customdata, 'post', '', [], true, $scaleid);
        // Set the form data with the same method that is called when loaded from JS. It should correctly set the data for the supplied arguments.
        $form->set_data_for_dynamic_submission();
        // Render the form in a specific container, there should be nothing else in the same container.
        return html_writer::div($form->render(), '', ['id' => 'select_scale_form']);
    }

    /**
     * Renders the subscale selector.
     * @return string
     */
    private function render_subscaleselector()
    {
        // If we have no scale selected or the selected scale doesn't have any subscales, subscale select isn't displayed.
        if ($this->scale < 0) {
            return "";
        }
        $subscaleresult = catquiz::get_subscale_ids_from_parent([$this->scale]);
        if (empty($subscaleresult)) {
            return "";
        }
        $scaleid = empty($this->scale) ? -1 : $this->scale;

        $customdata = [
            'type' => 'subscale',
            'parentscaleid' => ["$scaleid"],
        ];

        $subscaleid = empty($this->subscale) ? [-1] : ['subscale' => $this->subscale];

        $form = new \local_catquiz\form\scaleselector(null, $customdata, 'post', '', [], true, $subscaleid);
        // Set the form data with the same method that is called when loaded from JS. It should correctly set the data for the supplied arguments.
        $form->set_data_for_dynamic_submission();
        // Render the form in a specific container, there should be nothing else in the same container.
        return html_writer::div($form->render(), '', ['id' => 'select_subscale_form']);
    }

    /**
     * Renders the subscale checkbox.
     * @return array
     */
    private function render_subscale_checkbox()
    {
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
     * Render table.
     */
    public function renderquestionstable() {
        global $DB;
        if ($this->tablescale === -1) {
            return $this->get_no_table_string();
        }
        // If no context is set, get default context from DB.
        $catcontext = empty($this->catcontextid) ? catquiz::get_default_context_id() : $this->catcontextid;

        $table = new catscalequestions_table('catscale_' . $this->tablescale . ' questionstable', $this->tablescale, $catcontext);

        // If we integrate questions from subscales, we add different ids.
        if ($this->usesubs > 0) {
            $subscaleids = catquiz::get_subscale_ids_from_parent(
                [$this->tablescale]
            );
            $idsforquery = array_keys($subscaleids);
            array_push($idsforquery, $this->tablescale);
        } else {
            $idsforquery = [$this->tablescale];
        }

        list($select, $from, $where, $filter, $params) = catquiz::return_sql_for_catscalequestions($idsforquery, $catcontext, [], []);

        $table->set_filter_sql($select, $from, $where, $filter, $params);

        $columnsarray = [
            // TODO get Label
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
            'label' => get_string('addquestion', 'local_catquiz'), // 'NoModal, MultipleCall, NoSelection'-> Name of your action button.
            'class' => 'btn btn-primary', // Example colors bootstrap 4 classes.
            'href' => '#',
            'methodname' => 'addquestion', // The method needs to be added to your child of wunderbyte_table class.
            'nomodal' => true, // If set to true, there is no modal and the method will be called directly.
            'selectionmandatory' => false, // When set to true, action will only be triggered, if elements are selected.
            'data' => [ // Will be added eg as data-id = $values->id, so values can be transmitted to the method above.
                'id' => 'id',
                'component' => 'local_wunderbyte_table', // Localization of strings
            ]
        ];
        $table->actionbuttons[] = [
            'label' => get_string('addtest', 'local_catquiz'), // 'NoModal, MultipleCall, NoSelection'-> Name of your action button.
            'class' => 'btn btn-primary', // Example colors bootstrap 4 classes.
            'href' => '#',
            'methodname' => 'addtest', // The method needs to be added to your child of wunderbyte_table class.
            'nomodal' => true, // If set to true, there is no modal and the method will be called directly.
            'selectionmandatory' => false, // When set to true, action will only be triggered, if elements are selected.
            'data' => [ // Will be added eg as data-id = $values->id, so values can be transmitted to the method above.
                'id' => 'id',
                'component' => 'local_wunderbyte_table', // Localization of strings
            ]
        ];
        $table->actionbuttons[] = [
            'label' => get_string('checklinking', 'local_catquiz'), // 'NoModal, MultipleCall, NoSelection'-> Name of your action button.
            'class' => 'btn btn-primary', // Example colors bootstrap 4 classes.
            'href' => '#',
            'methodname' => 'addquestion', // The method needs to be added to your child of wunderbyte_table class.
            'nomodal' => true, // If set to true, there is no modal and the method will be called directly.
            'selectionmandatory' => false, // When set to true, action will only be triggered, if elements are selected.
            'data' => [ // Will be added eg as data-id = $values->id, so values can be transmitted to the method above.
                'id' => 'id',
                'component' => 'local_wunderbyte_table', // Localization of strings
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

        $output = $table->outhtml(10, true);
        $this->numberofrecords = $table->return_records_count()[0];
        if ($this->numberofrecords > 0) { //Only if the table contains records, we will return it.
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
     */
    private function check_tabledisplay() {
        $output = "";
        if (empty($this->detailid)) {
            $output = empty($this->renderquestionstable()) ? $this->get_no_table_string() : $this->renderquestionstable();
        }
        return $output;
    }

    /**
     * Check if we display a table or a detailview of a specific item.
     */
    private function render_detailview() {
        global $DB;
        if (empty($this->detailid)) {
            return;
        }
        $catcontext = empty($this->catcontextid) ? catquiz::get_default_context_id() : $this->catcontextid; // If no context is set, get default context from DB.

        // Get the record for the specific userid (fetched from optional param).
        list($select, $from, $where, $filter, $params) = catquiz::return_sql_for_catscalequestions([$this->tablescale], $catcontext, [], [], $this->detailid);
        $idcheck = "id=:userid";
        $sql = "SELECT $select FROM $from WHERE $where AND $idcheck";
        $recordinarray = $DB->get_records_sql($sql, $params, IGNORE_MISSING);
        $record = $recordinarray[$this->detailid];

        $title = get_string('general', 'core');

        $body['id'] = $record->id;
        $body['type'] = $record->qtype;
        $body['status'] = $record->status;
        $body['model'] = $record->model;
        $body['attempts'] = $record->attempts;

        return [
            'title' => $title,
            'body' => $body,
        ];
    }


    /**
     * Return the item tree of all catscales.
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {

        $data = [
            'contextselector' => $this->render_contextselector(),
            'scaleselector' => empty($this->render_scaleselector()) ? "" : $this->render_scaleselector(),
            'subscaleselector' => empty($this->render_subscaleselector()) ? "" : $this->render_subscaleselector(),
            'checkbox' => $this->render_subscale_checkbox(),
            'table' => $this->check_tabledisplay(),
            'detailview' => $this->render_detailview(),
        ];

        return $data;
    }
}
