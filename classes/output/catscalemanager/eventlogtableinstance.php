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

namespace local_catquiz\output\catscalemanager;

use html_writer;
use local_catquiz\catquiz;
use local_catquiz\table\event_log_table;
use local_wunderbyte_table\filters\types\datepicker;
use local_wunderbyte_table\filters\types\standardfilter;
use moodle_url;

/**
 * Renderable class for the catscalemanagers
 *
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH
 * @author     Magdalena Holczik
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class eventlogtableinstance {


    /**
     * Constructor.
     *
     */
    public function __construct() {

    }

    /**
     * Render event log table.
     * @return ?string
     */
    public function render_event_log_table() {

        $table = new event_log_table('eventlogtable');

        list($select, $from, $where, $filter, $params) = catquiz::return_sql_for_event_logs();

        $table->set_filter_sql($select, $from, $where, $filter, $params);

        $columnsarray = [
            'username' => get_string('user', 'core'),
            'eventname' => get_string('eventname', 'core'),
            'description' => get_string('description', 'core'),
            'timecreated' => get_string('timecreated', 'core'),
        ];
        $table->define_columns(array_keys($columnsarray));
        $table->define_headers(array_values($columnsarray));

        $table->define_sortablecolumns(array_keys($columnsarray));
        $table->sort_default_column = 'timecreated';
        $table->sort_default_order = SORT_DESC;

        $standardfilter = new standardfilter('username', get_string('user', 'core'));
        $table->add_filter($standardfilter);

        $standardfilter = new standardfilter('eventname', get_string('eventname', 'local_catquiz'));
        $standardfilter->add_options([
            '\local_catquiz\event\attempt_completed' => get_string('attempt_completed', 'local_catquiz'),
            '\local_catquiz\event\calculation_executed' => get_string('calculation_executed', 'local_catquiz'),
            '\local_catquiz\event\catscale_created' => get_string('catscale_created', 'local_catquiz'),
            '\local_catquiz\event\catscale_updated' => get_string('catscale_updated', 'local_catquiz'),
            '\local_catquiz\event\context_created' => get_string('context_created', 'local_catquiz'),
            '\local_catquiz\event\context_updated' => get_string('context_updated', 'local_catquiz'),
            '\local_catquiz\event\testitemactivitystatus_updated' =>
                get_string('testitemactivitystatus_updated', 'local_catquiz'),
            '\local_catquiz\event\testiteminscale_added' => get_string('testiteminscale_added', 'local_catquiz'),
            '\local_catquiz\event\testiteminscale_updated' => get_string('testiteminscale_updated', 'local_catquiz'),
            '\local_catquiz\event\testitemstatus_updated' => get_string('testitemstatus_updated', 'local_catquiz'),
            '\local_catquiz\event\testitem_imported' => get_string('testitem_imported', 'local_catquiz'),

        ]);
        $table->add_filter($standardfilter);

        $datepicker = new datepicker('timecreated', get_string('logsafter', 'local_catquiz'));
        $datepicker->add_options(
            'standard',
            '>',
            get_string('apply_filter', 'local_wunderbyte_table'),
            'now',
        );
        $table->add_filter($datepicker);

        $datepicker = new datepicker('timecreated', get_string('logsbefore', 'local_catquiz'));
        $datepicker->add_options(
            'standard',
            '<',
            get_string('apply_filter', 'local_wunderbyte_table'),
            'now'
        );
        $table->add_filter($datepicker);

        $table->tabletemplate = 'local_wunderbyte_table/twtable_list';
        $table->define_cache('local_catquiz', 'eventlogtable');

        $table->pageable(true);

        $table->showcountlabel = true;
        $table->showdownloadbutton = false;
        $table->showreloadbutton = true;
        $table->showrowcountselect = true;

        $table->filteronloadinactive = true;

        $table->define_baseurl(new moodle_url('/local/catquiz/downloads/download.php'));

        return $table->outhtml(10, true);
    }
}
