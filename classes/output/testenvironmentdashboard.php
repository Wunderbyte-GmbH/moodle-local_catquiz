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

use local_catquiz\catquiz;
use local_catquiz\table\testenvironments_table;
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
class testenvironmentdashboard implements renderable, templatable {

    /**
     * Either returns one tree or treearray for every parentnode
     *
     * @param int $fulltree
     * @param boolean $allowedit
     * @return array
     */
    public function __construct() {

    }

    /**
     * @param int? catscaleid If given, only return test environments that use the given cat scale
     */
    public function testenvironmenttable($catscaleid = null) {

        $table = new testenvironments_table('testenvironmentstable');

        list($select, $from, $where, $filter, $params) = $catscaleid
        ? catquiz::return_sql_for_testenvironments("catscaleid=$catscaleid")
        : catquiz::return_sql_for_testenvironments();

        $table->set_filter_sql($select, $from, $where, $filter, $params);

        $table->define_columns([
            'name',
            'component',
            'visible',
            'availability',
            'lang',
            'status',
            'parentid',
            'timemodified',
            'timecreated',
            'action',
            'catscaleid',
            'fullname',
            'numberofitems',
            'numberofusers',
            'teachers',
        ]);
        $table->define_headers([
            get_string('name', 'core'),
            get_string('component', 'local_catquiz'),
            get_string('visible', 'core'),
            get_string('availability', 'core'),
            get_string('lang', 'local_catquiz'),
            get_string('status'),
            get_string('parentid', 'local_catquiz'),
            get_string('timemodified', 'local_catquiz'),
            get_string('timecreated'),
            get_string('action', 'core'),
            get_string('catscaleid', 'local_catquiz'),
            get_string('course', 'core'),
            get_string('numberofquestions', 'local_catquiz'),
            get_string('numberofusers', 'local_catquiz'),
            get_string('teachers', 'core'),
        ]);

        $table->define_filtercolumns(
            ['name' => [
                'localizedname' => get_string('name', 'core')
            ], 'component' => [
                'localizedname' => get_string('component', 'local_catquiz'),
            ], 'visible' => [
                'localizedname' => get_string('visible', 'core'),
                '1' => get_string('visible', 'core'),
                '0' => get_string('invisible', 'local_catquiz'),
            ], 'status' => [
                'localizedname' => get_string('status'),
                '2' => get_string('force', 'local_catquiz'),
                '1' => get_string('active', 'core'),
                '0' => get_string('inactive', 'core'),
            ], 'lang' => [
                'localizedname' => get_string('lang', 'local_catquiz'),
            ]
        ]);
        $table->define_fulltextsearchcolumns(['name', 'component', 'description']);
        $table->define_sortablecolumns([
            'name',
            'component',
            'visible',
            'availability',
            'lang',
            'status',
            'parentid',
            'timemodified',
            'timecreated',
            'action',
            'course',
        ]);

        // $table->tabletemplate = 'local_wunderbyte_table/twtable_list';
        $table->define_cache('local_catquiz', 'testenvironments');

        // $table->addcheckboxes = true;

        $table->pageable(true);

        $table->stickyheader = false;
        $table->showcountlabel = true;
        $table->showdownloadbutton = true;
        $table->showreloadbutton = true;

        return $table->outhtml(10, true);
    }

    /**
     * We use this function to get only the array, without having to pass on the render base.
     *
     * @return array
     */
    public function return_array() {
        $url = new moodle_url('/local/catquiz/manage_catscales.php');

        return [
            'returnurl' => $url->out(),
            'table' => $this->testenvironmenttable(),
        ];
    }

    /**
     * Return the item tree of all catscales.
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {

        return $this->return_array();
    }
}
