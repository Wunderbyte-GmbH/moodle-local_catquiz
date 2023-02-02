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
use local_catquiz\catquiz;
use local_catquiz\table\testitems_table;
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

        $table->define_columns(['id', 'name', 'questiontext', 'qtype', 'categoryname']);

        $table->define_filtercolumns(['id', 'categoryname' => [
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
        $table->define_fulltextsearchcolumns(['id', 'name', 'questiontext', 'qtype']);
        $table->define_sortablecolumns(['id', 'name', 'questiontext', 'qtype']);

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

        $table->define_columns(['id', 'name', 'questiontext', 'qtype', 'categoryname']);

        $table->define_filtercolumns(['id', 'categoryname' => [
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
        $table->define_fulltextsearchcolumns(['id', 'name', 'questiontext', 'qtype']);
        $table->define_sortablecolumns(['id', 'name', 'questiontext', 'qtype']);

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

    /**
     * Return the item tree of all catscales.
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        return [
            'testitemstable' => $this->render_testitems_table($this->catscaleid),
            'addtestitemstable' => $this->render_addtestitems_table($this->catscaleid),
        ];
    }
}
