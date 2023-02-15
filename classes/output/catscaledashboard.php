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
 * @author     Georg Maißer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catscaledashboard implements renderable, templatable {

    /** @var integer of catscaleid */
    public int $catscaleid = 0;

    /**
     * Either returns one tree or treearray for every parentnode
     *
     * @param int $fulltree
     * @param boolean $allowedit
     * @return array
     */
    public function __construct(int $catscaleid) {

        $this->catscaleid = $catscaleid;
    }

    private function render_addtestitems_table(int $catscaleid) {

        $table = new testitems_table('addtestitems');

        list($select, $from, $where, $filter, $params) = catquiz::return_sql_for_addcatscalequestions($catscaleid);

        $table->set_filter_sql($select, $from, $where, $filter, $params);

        $table->define_columns(['idnumber', 'questiontext', 'qtype', 'categoryname']);
        $table->define_headers([
            get_string('label', 'local_catquiz'),
            get_string('questiontext', 'local_catquiz'),
            get_string('questiontype', 'local_catquiz'),
            get_string('questioncategories', 'local_catquiz')
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
        $table->define_sortablecolumns(['idnunber', 'name', 'questiontext', 'qtype']);

        $table->tabletemplate = 'local_wunderbyte_table/twtable_list';
        $table->define_cache('local_catquiz', 'testitemstable');

        $table->addcheckboxes = true;

        $table->actionbuttons[] = [
            'label' => get_string('addtestitem', 'local_catquiz'), // Name of your action button.
            'class' => 'btn btn-success',
            'methodname' => 'addtestitem', // The method needs to be added to your child of wunderbyte_table class.
            'id' => -1, // This makes one Ajax call for all selected item, not one for each.
            'data' => [ // Will be added eg as data-id = $values->id, so values can be transmitted to the method above.
                'titlestring' => 'addtestitemtitle',
                'bodystring' => 'addtestitembody',
                'submitbuttonstring' => 'addtestitemsubmit',
                'component' => 'local_catquiz',
                'labelcolumn' => 'name',
                'catscaleid' => $catscaleid,
            ]
        ];

        $table->pageable(true);

        $table->stickyheader = false;
        $table->showcountlabel = true;
        $table->showdownloadbutton = true;
        $table->showreloadbutton = true;

        return $table->outhtml(10, true);
    }

    /**
     * Function to render the testitems attributed to a given catscale.
     *
     * @param integer $catscaleid
     * @return string
     */
    private function render_testitems_table(int $catscaleid) {

        $table = new testitems_table('testitems');

        list($select, $from, $where, $filter, $params) = catquiz::return_sql_for_catscalequestions($catscaleid);

        $table->set_filter_sql($select, $from, $where, $filter, $params);

        $table->define_columns(['idnumber', 'questiontext', 'qtype', 'categoryname']);
        $table->define_headers([
            get_string('label', 'local_catquiz'),
            get_string('questiontext', 'local_catquiz'),
            get_string('questiontype', 'local_catquiz'),
            get_string('questioncategories', 'local_catquiz')
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
        $table->define_sortablecolumns(['idnumber', 'name', 'questiontext', 'qtype']);

        $table->tabletemplate = 'local_wunderbyte_table/twtable_list';
        $table->define_cache('local_catquiz', 'testitemstable');

        $table->addcheckboxes = true;

        $table->actionbuttons[] = [
            'label' => get_string('removetestitem', 'local_catquiz'), // Name of your action button.
            'class' => 'btn btn-danger',
            'methodname' => 'removetestitem', // The method needs to be added to your child of wunderbyte_table class.
            'id' => -1, // This makes one Ajax call for all selected item, not one for each.
            'data' => [ // Will be added eg as data-id = $values->id, so values can be transmitted to the method above.
                'titlestring' => 'removetestitemtitle',
                'bodystring' => 'removetestitembody',
                'submitbuttonstring' => 'removetestitemsubmit',
                'component' => 'local_catquiz',
                'labelcolumn' => 'name',
                'catscaleid' => $catscaleid,
            ]
        ];

        $table->pageable(true);

        $table->stickyheader = false;
        $table->showcountlabel = true;
        $table->showdownloadbutton = true;
        $table->showreloadbutton = true;

        return $table->outhtml(10, true);
    }

    private function render_differentialitem() {

        global $OUTPUT;

        $chart = new \core\chart_line();
        $series1 = new \core\chart_series('Series 1 (Line)', [0.2, 0.3, 0.1, 0.4, 0.5, 0.2, 0.1, 0.3, 0.1, 0.4]);
        $series2 = new \core\chart_series('Series 2 (Line)', [0.22, 0.35, 0.09, 0.38, 0.4, 0.24, 0.18, 0.31, 0.09, 0.4]);
        $chart->set_smooth(true); // Calling set_smooth() passing true as parameter, will display smooth lines.
        $chart->add_series($series1);
        $chart->add_series($series2);
        $chart->set_labels(['1', '2', '3', '4', '5', '6', '7', '8', '9', '10']);

        return html_writer::tag('div', $OUTPUT->render($chart), ['dir' => 'ltr']);
    }

    private function render_statindependence() {

        global $OUTPUT;

        $chart = new \core\chart_line(); // Create a bar chart instance.
        $series1 = new \core\chart_series('Series 1 (Line)', [1.26, -0.87, 0.39, 2.31, 1.47, -0.53, 0.02, -1.14, 1.29, -0.04]);
        $series2 = new \core\chart_series('Series 2 (Line)', [0.63, -0.04, -0.42, 1.98, -1.23, 0.53, 0.87, -0.35, -0.64, 0.18]);
        $series2->set_type(\core\chart_series::TYPE_LINE); // Set the series type to line chart.
        $chart->add_series($series2);
        $chart->add_series($series1);
        $chart->set_labels(['1', '2', '3', '4', '5', '6', '7', '8', '9', '10']);

        return html_writer::tag('div', $OUTPUT->render($chart), ['dir' => 'ltr']);
    }

    private function render_loglikelihood() {

        global $OUTPUT;

        $chart = new \core\chart_line();
        $series = new \core\chart_series('Series 1 (Line)', [-1.53, 0.34, 1.21, 2.64, -0.35, -0.02, -0.56, 1.28, 1.26, 0.09, -0.5]);
        $chart->set_smooth(true); // Calling set_smooth() passing true as parameter, will display smooth lines.
        $chart->add_series($series);
        $chart->set_labels(["-5", "-4", "-3", "-2", "-1", "0", "1", "2", "3", "4", "5"]);

        return html_writer::tag('div', $OUTPUT->render($chart), ['dir' => 'ltr']);
    }

    /**
     * Return the item tree of all catscales.
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {

        $url = new moodle_url('/local/catquiz/manage_catscales.php');

        return [
            'returnurl' => $url->out(),
            'testitemstable' => $this->render_testitems_table($this->catscaleid),
            'addtestitemstable' => $this->render_addtestitems_table($this->catscaleid),
            'statindependence' => $this->render_statindependence(),
            'loglikelihood' => $this->render_loglikelihood(),
            'differentialitem' => $this->render_differentialitem(),
        ];
    }
}
