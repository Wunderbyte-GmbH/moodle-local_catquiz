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
     * @var integer
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

        $table = new testenvironments_table('testenvironmentstable' . $tablesuffix);

        list($select, $from, $where, $filter, $params) =
            catquiz::return_sql_for_testenvironments($catscaleid);

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
            // phpcs:ignore
            // 'numberofusers',
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
            [
                'id' => 'id',
                'name' => [
                    'localizedname' => get_string('name', 'core'),
                ],
                'component' => [
                    'localizedname' => get_string('component', 'local_catquiz'),
                ],
                'visible' => [
                    'localizedname' => get_string('visible', 'core'),
                    '1' => get_string('visible', 'core'),
                    '0' => get_string('invisible', 'local_catquiz'),
                ],
                'status' => [
                    'localizedname' => get_string('status'),
                    '2' => get_string('force', 'local_catquiz'),
                    '1' => get_string('active', 'core'),
                    '0' => get_string('inactive', 'core'),
                ],
                'lang' => [
                    'localizedname' => get_string('lang', 'local_catquiz'),
                ],
            ]);
        $table->define_fulltextsearchcolumns(['name', 'component', 'description']);
        $table->define_sortablecolumns(
            [
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

        $table->actionbuttons[] = [
            'label' => get_string('notifyteachersofselectedcourses', 'local_catquiz'), // Name of your action button.
            // The method needs to be added to your child of wunderbyte_table class.
            'methodname' => 'notifyteachersofselectedcourses',
            'class' => 'btn btn-primary',
            'href' => '#',
            'id' => -1, // This forces single call execution.
            'nomodal' => false,
            'selectionmandatory' => true,
            // Will be added eg as data-id = $values->id, so values can be transmitted to the method above.
            'data' => [
                'id' => 'id',
                'name' => 'name',
            ],
            ];

        $table->actionbuttons[] = [
            'label' => get_string('notifyallteachers', 'local_catquiz'), // Name of your action button.
            'methodname' => 'notifyallteachers', // The method needs to be added to your child of wunderbyte_table class.
            'class' => 'btn btn-primary',
            'href' => '#',
            'id' => -1, // This forces single call execution.
            'nomodal' => false,
            'data' => [ // Will be added eg as data-id = $values->id, so values can be transmitted to the method above.
                'id' => 'id',
                'name' => 'name',
            ],
            ];

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
