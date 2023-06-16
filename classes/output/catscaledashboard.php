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

use context_system;
use html_writer;
use local_catquiz\catmodel_info;
use local_catquiz\catquiz;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_person_param_list;
use local_catquiz\synthcat;
use local_catquiz\table\testitems_table;
use local_catquiz\table\student_stats_table;
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
class catscaledashboard implements renderable, templatable
{

    /** @var integer of catscaleid */
    public int $catscaleid = 0;

    /** @var integer of catcontextid */
    private int $catcontextid = 0;

    /**
     * If set to true, we execute the CAT parameter estimation algorithm.
     *
     * @var boolean
     */
    private bool $triggercalculation;

    /** @var stdClass|bool */
    private $catscale;

    /**
     * Either returns one tree or treearray for every parentnode
     *
     * @param int $fulltree
     * @param boolean $allowedit
     * @return array
     */
    public function __construct(int $catscaleid, int $catcontextid = 0, bool $triggercalculation = false)
    {
        global $DB;

        $this->catscaleid = $catscaleid;
        $this->catcontextid = $catcontextid;
        $this->triggercalculation = $triggercalculation;
        $this->catscale = $DB->get_record(
            'local_catquiz_catscales',
            ['id' => $catscaleid]
        );
    }

    private function render_title()
    {
        global $OUTPUT;
        global $PAGE;

        $PAGE->set_heading($this->catscale->name);
        echo $OUTPUT->header();
    }
    private function render_addtestitems_table(int $catscaleid)
    {

        $table = new testitems_table('addtestitems', $catscaleid, $this->catcontextid);

        list($select, $from, $where, $filter, $params) = catquiz::return_sql_for_addcatscalequestions($catscaleid, $this->catcontextid);

        $table->set_filter_sql($select, $from, $where, $filter, $params);

        $table->define_columns([
            'idnumber',
            'questiontext',
            'qtype',
            'categoryname',
            'questioncontextattempts',
            'action'
        ]);
        $table->define_headers([
            get_string('label', 'local_catquiz'),
            get_string('questiontext', 'local_catquiz'),
            get_string('questiontype', 'local_catquiz'),
            get_string('questioncategories', 'local_catquiz'),
            get_string('questioncontextattempts', 'local_catquiz'),
            get_string('action', 'local_catquiz'),
        ]);

        $table->define_filtercolumns(['categoryname' => [
            'localizedname' => get_string('questioncategories', 'local_catquiz')
        ], 'qtype' => [
            'localizedname' => get_string('questiontype', 'local_catquiz'),
            'ddimageortext' => get_string('pluginname', 'qtype_ddimageortext'),
            'essay' => get_string('pluginname', 'qtype_essay'),
            'gapselect' => get_string('pluginname', 'qtype_gapselect'),
            'multianswer' => get_string('pluginname', 'qtype_multianswer'),
            'multichoice' => get_string('pluginname', 'qtype_multichoice'),
            'numerical' => get_string('pluginname', 'qtype_numerical'),
            'shortanswer' => get_string('pluginname', 'qtype_shortanswer'),
            'truefalse' => get_string('pluginname', 'qtype_truefalse'),
        ]]);
        $table->define_fulltextsearchcolumns(['idnumber', 'name', 'questiontext', 'qtype']);
        $table->define_sortablecolumns([
            'idnunber',
            'name',
            'questiontext',
            'qtype',
            'questioncontextattempts',
        ]);

        $table->tabletemplate = 'local_wunderbyte_table/twtable_list';
        $table->define_cache('local_catquiz', 'testitemstable');

        $table->addcheckboxes = true;

        $table->actionbuttons[] = [
            'label' => get_string('addtestitem', 'local_catquiz'), // Name of your action button.
            'class' => 'btn btn-success',
            'href' => '#',
            'methodname' => 'addtestitem', // The method needs to be added to your child of wunderbyte_table class.
            'id' => -1, // This makes one Ajax call for all selected item, not one for each.
            'data' => [ // Will be added eg as data-id = $values->id, so values can be transmitted to the method above.
                'titlestring' => 'addtestitemtitle',
                'bodystring' => 'addtestitembody',
                'submitbuttonstring' => 'addtestitemsubmit',
                'component' => 'local_catquiz',
                'labelcolumn' => 'idnumber',
                'catscaleid' => $catscaleid,
            ]
        ];

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
     * Function to render the testitems attributed to a given catscale.
     *
     * @param integer $catscaleid
     * @return string
     */
    private function render_testitems_table(int $catscaleid) {



        $table = new testitems_table('testitems', $this->catscaleid, $this->catcontextid);

        list($select, $from, $where, $filter, $params) = catquiz::return_sql_for_catscalequestions([$catscaleid], [], [], $this->catcontextid);

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

        $table->tabletemplate = 'local_wunderbyte_table/twtable_list';
        $table->define_cache('local_catquiz', 'testitemstable');

        $table->addcheckboxes = true;

        $table->actionbuttons[] = [
            'label' => get_string('removetestitem', 'local_catquiz'), // Name of your action button.
            'class' => 'btn btn-danger',
            'href' => '#',
            'methodname' => 'removetestitem', // The method needs to be added to your child of wunderbyte_table class.
            'id' => -1, // This makes one Ajax call for all selected item, not one for each.
            'data' => [ // Will be added eg as data-id = $values->id, so values can be transmitted to the method above.
                'titlestring' => 'removetestitemtitle',
                'bodystring' => 'removetestitembody',
                'submitbuttonstring' => 'removetestitemsubmit',
                'component' => 'local_catquiz',
                'labelcolumn' => 'idnumber',
                'catscaleid' => $catscaleid,
            ]
        ];

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
     * @param array<model_item_param_list> $item_lists
     */
    private function render_itemdifficulties(array $item_lists)
    {

        global $OUTPUT;

        $charts = [];
        foreach ($item_lists as $model_name => $item_list) {
            $data = $item_list->get_values(true);
            // Skip empty charts
            if (empty($data)) {
                continue;
            }

            $chart = new \core\chart_line();
            $series = new \core\chart_series('Series 1 (Line)', array_values($data));
            $chart->set_smooth(true); // Calling set_smooth() passing true as parameter, will display smooth lines.
            $chart->add_series($series);
            $chart->set_labels(array_keys($data));
            $charts[] = ['modelname' => $model_name, 'chart' => html_writer::tag('div', $OUTPUT->render($chart), ['dir' => 'ltr'])];
        }

        return $charts;
    }

    /**
     * @param model_person_param_list $person_params
     */
    private function render_personabilities(model_person_param_list $person_params)
    {
        global $OUTPUT;

        $data = $person_params->get_values(true);
        $chart = new \core\chart_line();
        $series = new \core\chart_series('Series 1 (Line)', array_values($data));
        $chart->set_smooth(true); // Calling set_smooth() passing true as parameter, will display smooth lines.
        $chart->add_series($series);
        $chart->set_labels(array_keys($data));
        return html_writer::tag('div', $OUTPUT->render($chart), ['dir' => 'ltr']);
    }

    private function render_contextselector()
    {
        $ajaxformdata = empty($this->catcontextid) ? [] : ['contextid' => $this->catcontextid];
        $form = new \local_catquiz\form\contextselector(null, null, 'post', '', [], true, $ajaxformdata);
        // Set the form data with the same method that is called when loaded from JS. It should correctly set the data for the supplied arguments.
        $form->set_data_for_dynamic_submission();
        // Render the form in a specific container, there should be nothing else in the same container.
        return html_writer::div($form->render(), '', ['id' => 'select_context_form']);
    }

    private function render_student_stats_table(int $catscaleid, int $catcontextid)
    {
        $table = new student_stats_table('students', $this->catscaleid, $this->catcontextid);

        list($select, $from, $where, $filter, $params) = catquiz::return_sql_for_student_stats($catcontextid);

        $table->set_filter_sql($select, $from, $where, $filter, $params);

        $table->define_columns(['firstname', 'lastname', 'studentattempts', 'ability', 'action']);
        $table->define_headers([
            get_string('firstname', 'core'),
            get_string('lastname', 'core'),
            get_string('questioncontextattempts', 'local_catquiz'),
            get_string('personabilities', 'local_catquiz'),
            get_string('action', 'local_catquiz'),
        ]);

        $table->define_fulltextsearchcolumns(['firstname', 'lastname']);
        $table->define_sortablecolumns(['firstname', 'lastname', 'studentattempts', 'ability']);

        $table->tabletemplate = 'local_wunderbyte_table/twtable_list';
        $table->define_cache('local_catquiz', 'studentstatstable');

        $table->pageable(true);

        $table->stickyheader = false;
        $table->showcountlabel = true;
        $table->showdownloadbutton = true;
        $table->showreloadbutton = true;
        $table->showrowcountselect = true;

        return $table->outhtml(10, true);
    }
    private function render_modelbutton($contextid)
    {
        return sprintf('<button class="btn btn-primary" type="button" data-contextid="%s" id="model_button">Calculate</button>', $contextid);
    }

    public function export_for_template(\renderer_base $output): array
    {

        $url = new moodle_url('/local/catquiz/manage_catscales.php');
        $testenvironmentdashboard = new testenvironmentdashboard();
        $cm = new catmodel_info;
        list($item_difficulties, $person_abilities) = $cm->get_context_parameters(
            $this->catcontextid,
            $this->triggercalculation
        );

        return [
            'title' => $this->render_title(),
            'returnurl' => $url->out(),
            'testitemstable' => $this->render_testitems_table($this->catscaleid),
            'addtestitemstable' => $this->render_addtestitems_table($this->catscaleid),
            'itemdifficulties' => $this->render_itemdifficulties($item_difficulties),
            'personabilities' => $this->render_personabilities($person_abilities),
            'contextselector' => $this->render_contextselector(),
            'table' => $testenvironmentdashboard->testenvironmenttable($this->catscaleid),
            'studentstable' => $this->render_student_stats_table($this->catscaleid, $this->catcontextid),
            'modelbutton' => $this->render_modelbutton($this->catcontextid),
        ];
    }
}
