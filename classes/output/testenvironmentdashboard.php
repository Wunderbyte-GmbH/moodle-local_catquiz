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

use local_catquiz\catquiz;
use local_catquiz\catscale;
use local_catquiz\table\testenvironments_table;
use local_wunderbyte_table\filters\types\standardfilter;
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
     * @var int
     */
    private ?int $catscaleid = null;


    /**
     * Constructor.
     * @param ?int $catscaleid
     */
    public function __construct($catscaleid = null) {
        $this->catscaleid = $catscaleid;
    }

    /**
     * REnders testenvironment table.
     *
     * @param ?int $catscaleid If given, only return test environments that use the given cat scale.
     * @return string
     */
    public function testenvironmenttable($catscaleid = 0) {

        $tablesuffix = $catscaleid < 1 ? "" : $catscaleid;
        $catscaleancestors = catscale::get_ancestors($catscaleid);
        $catscaleid = end($catscaleancestors) ?: $catscaleid;

        $table = new testenvironments_table('testenvironmentstable' . $tablesuffix);

        list($select, $from, $where, $filter, $params) =
            catquiz::return_sql_for_testenvironments($catscaleid);

        $table->set_filter_sql($select, $from, $where, $filter, $params);

        $table->define_columns([
            'name',
            'component',
            'istest',
            'status',
            'parentid',
            'timemodified',
            'timecreated',
            'action',
            'catscaleid',
            'fullname',
            'numberofitems',
            // phpcs:ignore
            // 'numberofusers',
            'users',
        ]);
        $table->define_headers([
            get_string('name', 'core'),
            get_string('component', 'local_catquiz'),
            get_string('type', 'local_catquiz'),
            get_string('status'),
            get_string('parentid', 'local_catquiz'),
            get_string('timemodified', 'local_catquiz'),
            get_string('timecreated'),
            get_string('action', 'core'),
            get_string('catscaleid', 'local_catquiz'),
            get_string('course', 'core'),
            get_string('numberofquestions', 'local_catquiz'),
            get_string('numberofusers', 'local_catquiz'),
            get_string('users', 'core'),
        ]);

        $standardfilter = new standardfilter('name', get_string('name', 'core'));
        $table->add_filter($standardfilter);

        $standardfilter = new standardfilter('component', get_string('component', 'local_catquiz'));
        $table->add_filter($standardfilter);

        $standardfilter = new standardfilter('status', get_string('status', 'core'));
        $standardfilter->add_options([
            '2' => get_string('force', 'local_catquiz'),
            '1' => get_string('active', 'core'),
            '0' => get_string('inactive', 'core'),
        ]);
        $table->add_filter($standardfilter);

        $standardfilter = new standardfilter('istest', get_string('type', 'local_catquiz'));
        $standardfilter->add_options([
                '1' => get_string('testtype', 'local_catquiz'),
                '0' => get_string('templatetype', 'local_catquiz'),
            ]);
        $table->add_filter($standardfilter);

        $table->define_fulltextsearchcolumns(['name', 'component', 'description']);
        $table->define_sortablecolumns(
            [
                'name',
                'component',
                'status',
                'parentid',
                'timemodified',
                'timecreated',
                'action',
                'course',
            ]
        );

        $table->sort_default_column = 'timemodified';
        $table->sort_default_order = SORT_DESC;

        $table->define_cache('local_catquiz', 'testenvironments');

        $table->pageable(true);

        $table->stickyheader = false;
        $table->showcountlabel = true;
        $table->showdownloadbutton = true;
        $table->showreloadbutton = true;
        $table->addcheckboxes = true;

        list($idstring, $encodedtable, $html) = $table->lazyouthtml(10, true);
        return $html;
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
            'table' => $this->testenvironmenttable($this->catscaleid),
        ];
    }

    /**
     * Exports for template.
     *
     * @param \renderer_base $output
     *
     * @return array
     *
     */
    public function export_for_template(\renderer_base $output): array {

        return $this->return_array();
    }
}
