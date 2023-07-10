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
use local_catquiz\table\testitems_table;
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
    private int $usesubs = 0; // If subscales should be integrated in question display, value is 1.

    /**
     *
     * @return void
     */
    public function __construct() {
        $this->catcontextid = optional_param('contextid', 0, PARAM_INT);
        $this->scale = optional_param('scale', -1, PARAM_INT);
        $this->usesubs = optional_param('usesubs', 0, PARAM_INT);

        $this->tablescale = $this->scale; // TODO check if subscales are selected an write into tablescale.
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
            "hidelabel" => true,
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
        if ($this->usesubs !== 1) {
            return "";
        }
        //$ajaxformdata = empty($this->catcontextid) ? [] : ['contextid' => $this->catcontextid];
        $scaleid = empty($this->scale) ? -1 : $this->scale;

        $customdata = [
            'type' => 'subscale',
            'parentscaleid' => ["$scaleid"],
        ];

        $form = new \local_catquiz\form\scaleselector(null, $customdata, 'post', '', [], true, []);
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
        $checked = "";
        if ($this->usesubs == 1) {
            $checked = "checked";
        }
        $checkboxarray = [
            'label' => get_string('integratequestions', 'local_catquiz'),
            'checked' => $checked,
        ];

        return $checkboxarray;
    }


    /**
     *
     */
    public function renderquestionstable() {
        $this->tablescale = 1;

        $table = new testitems_table('questionstable', $this->tablescale, $this->catcontextid);

        list($select, $from, $where, $filter, $params) = catquiz::return_sql_for_catscalequestions([$this->tablescale], $this->catcontextid, [], []);

        $table->set_filter_sql($select, $from, $where, $filter, $params);

        $columnsarray = [
            'idnumber' => get_string('label', 'local_catquiz'),
            'questiontext' => get_string('questiontext', 'local_catquiz'),
            'qtype' => get_string('questiontype', 'local_catquiz'),
            'categoryname' => get_string('questioncategories', 'local_catquiz'),
            'model' => get_string('model', 'local_catquiz'),
            'difficulty' => get_string('difficulty', 'local_catquiz'),
            'lastattempttime' => get_string('lastattempttime', 'local_catquiz'),
            'attempts' => get_string('questioncontextattempts', 'local_catquiz'),
            'action' => get_string('action', 'local_catquiz'),
        ];

        $table->define_columns(array_keys($columnsarray));
        $table->define_headers(array_values($columnsarray));

        /*
        $table->define_filtercolumns(['categoryname' => [
            'localizedname' => get_string('questioncategories', 'local_catquiz'),
        ], 'qtype' => [
            'localizedname' => get_string('questiontype', 'local_catquiz'),
            'truefalse' => get_string('pluginname', 'qtype_truefalse'),
            'ddimageortext' => get_string('pluginname', 'qtype_ddimageortext'),
            'essay' => get_string('pluginname', 'qtype_essay'),
            'gapselect' => get_string('pluginname', 'qtype_gapselect'),
            'multianswer' => get_string('pluginname', 'qtype_multianswer'),
            'multichoice' => get_string('pluginname', 'qtype_multichoice'),
            'numerical' => get_string('pluginname', 'qtype_numerical'),
            'shortanswer' => get_string('pluginname', 'qtype_shortanswer'),
        ]]);
        $table->define_fulltextsearchcolumns(['idnumber', 'name', 'questiontext', 'qtype', 'model']);
        $table->define_sortablecolumns(array_keys($columnsarray));
        */

        $table->tabletemplate = 'local_wunderbyte_table/twtable_list';
        $table->define_cache('local_catquiz', 'testitemstable');

        $table->pageable(true);

        $table->stickyheader = false;
        $table->showcountlabel = true;
        $table->showdownloadbutton = true;
        $table->showreloadbutton = true;
        $table->showrowcountselect = true;

        $table->filteronloadinactive = true;

        return $table->outhtml(10, true);
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
            'table' => empty($this->renderquestionstable()) ? "" : $this->renderquestionstable(),
        ];

        return $data;
    }
}
