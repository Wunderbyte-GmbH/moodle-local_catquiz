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

namespace local_catquiz\output\catscalemanager\quizattempts;

use local_catquiz\catquiz;
use local_catquiz\output\catscalemanager\scaleandcontexselector;
use local_catquiz\output\testenvironmentdashboard;
use local_catquiz\table\quizattempts_table;

/**
 * Renderable class for the catscalemanagers
 *
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH
 * @author     David Szkiba
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizattemptsdisplay {

    public function render_table() {
        $table = new quizattempts_table('testenvironmentstable');

        list($select, $from, $where, $filter, $params) = catquiz::return_sql_for_quizattempts();
        $table->set_filter_sql($select, $from, $where, $filter, $params);

        $table->define_columns([
            'name',
            'timecreated',
            'timemodified',
            'catscale',
            'catcontext',
            'course',
            'component',
            'instance',
            'teststrategy',
            'status',
            'total_number_of_testitems',
            'number_of_testitems_used',
            'personability_before_attempt',
            'personability_after_attempt',
            'starttime',
            'endtime',
            'action'
        ]);
        $table->define_headers([
            get_string('name', 'core'),
            get_string('timecreated', 'core'),
            get_string('timemodified', 'local_catquiz'),
            get_string('catscale', 'local_catquiz'),
            get_string('catcontext', 'local_catquiz'),
            get_string('course', 'core'),
            get_string('component', 'local_catquiz'),
            get_string('instance', 'local_catquiz'),
            get_string('teststrategy', 'local_catquiz'),
            get_string('status', 'core'),
            get_string('totalnumberoftestitems', 'local_catquiz'),
            get_string('numberoftestitemsused', 'local_catquiz'),
            get_string('personabilitybeforeattempt', 'local_catquiz'),
            get_string('personabilityafterattempt', 'local_catquiz'),
            get_string('starttime', 'local_catquiz'),
            get_string('endtime', 'local_catquiz'),
            get_string('action', 'core'),
        ]);

        //$table->define_filtercolumns(
        //    ['name' => [
        //        'localizedname' => get_string('name', 'core')
        //    ], 'component' => [
        //        'localizedname' => get_string('component', 'local_catquiz'),
        //    ], 'visible' => [
        //        'localizedname' => get_string('visible', 'core'),
        //        '1' => get_string('visible', 'core'),
        //        '0' => get_string('invisible', 'local_catquiz'),
        //    ], 'status' => [
        //        'localizedname' => get_string('status'),
        //        '2' => get_string('force', 'local_catquiz'),
        //        '1' => get_string('active', 'core'),
        //        '0' => get_string('inactive', 'core'),
        //    ], 'lang' => [
        //        'localizedname' => get_string('lang', 'local_catquiz'),
        //    ]
        //    ]);
        //$table->define_fulltextsearchcolumns(['name', 'component', 'description']);
        //$table->define_sortablecolumns([
        //    'name',
        //    'component',
        //    'visible',
        //    'availability',
        //    'lang',
        //    'status',
        //    'parentid',
        //    'timemodified',
        //    'timecreated',
        //    'action',
        //    'course',
        //]);

        $table->define_cache('local_catquiz', 'quizattempts');

        $table->pageable(true);

        $table->stickyheader = false;
        $table->showcountlabel = true;
        $table->showdownloadbutton = true;
        $table->showreloadbutton = true;
        $table->addcheckboxes = true;


        //$table->actionbuttons[] = [
        //    'label' => get_string('notifyteachersofselectedcourses', 'local_catquiz'), // Name of your action button.
        //    'methodname' => 'notifyteachersofselectedcourses', // The method needs to be added to your child of wunderbyte_table class.
        //    'class' => 'btn btn-primary',
        //    'href' => '#',
        //    'id' => -1, // This forces single call execution.
        //    'nomodal' => false,
        //    'selectionmandatory' => true,
        //    'data' => [ // Will be added eg as data-id = $values->id, so values can be transmitted to the method above.
        //        'id' => 'id',
        //        'name' => 'name'
        //    ]
        //    ];

        //$table->actionbuttons[] = [
        //    'label' => get_string('notifyallteachers', 'local_catquiz'), // Name of your action button.
        //    'methodname' => 'notifyallteachers', // The method needs to be added to your child of wunderbyte_table class.
        //    'class' => 'btn btn-primary',
        //    'href' => '#',
        //    'id' => -1, // This forces single call execution.
        //    'nomodal' => false,
        //    'data' => [ // Will be added eg as data-id = $values->id, so values can be transmitted to the method above.
        //        'id' => 'id',
        //        'name' => 'name'
        //    ]
        //    ];

        return $table->outhtml(10, true);
    }

    /**
     * Return the item tree of all catscales.
     * @return array
     */
    public function export_data_array(): array {

        $data = [
            'table' => $this->render_table(),

        ];

        return $data;
    }
}
