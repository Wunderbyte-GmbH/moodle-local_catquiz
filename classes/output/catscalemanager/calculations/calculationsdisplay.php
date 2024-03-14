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

namespace local_catquiz\output\catscalemanager\calculations;

use html_writer;
use local_catquiz\catquiz;
use local_catquiz\table\event_log_table;
use moodle_url;

/**
 * Renderable class for the catscalemanagers
 *
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH
 * @author     Magdalena Holczik
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class calculationsdisplay {

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
    public function render_calculations_log_table() {

        $table = new event_log_table('eventlogtable_calculations');

        list($select, $from, $where, $filter, $params) = catquiz::return_sql_for_event_logs();

        $where .= " AND eventname LIKE :eventname ";
        $params['eventname'] = '%calculation_executed';

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

        $filtercolumns = [
            'username' => [
                'localizedname' => get_string('user', 'core'),
                    ],
            'timecreated' => [ // Columns containing Unix timestamps can be filtered.
                'localizedname' => get_string('eventtime', 'local_catquiz'),
                'datepicker' => [
                    get_string('logsafter', 'local_catquiz') => [ // Can be localized and like "Courses starting after:".
                        'operator' => '>', // Must be defined, can be any SQL comparison operator.
                        'defaultvalue' => 'now', // Can also be Unix timestamp or string "now".
                        // Can be localized and will be displayed next to the filter checkbox (ie 'apply filter').
                        'checkboxlabel' => get_string('apply_filter', 'local_wunderbyte_table'),
                    ],
                    get_string('logsbefore', 'local_catquiz') => [ // Can be localized and like "Courses starting after:".
                        'operator' => '<',
                        'defaultvalue' => 'now', // Can also be Unix timestamp or string "now".
                        // Can be localized and will be displayed next to the filter checkbox (ie 'apply filter').
                        'checkboxlabel' => get_string('apply_filter', 'local_wunderbyte_table'),
                    ],
                ],
                    ],
        ];
        $table->define_filtercolumns($filtercolumns);

        $table->tabletemplate = 'local_wunderbyte_table/twtable_list';
        $table->define_cache('local_catquiz', 'eventlogtable');

        $table->pageable(true);

        $table->showcountlabel = true;
        $table->showdownloadbutton = false;
        $table->showreloadbutton = true;
        $table->showrowcountselect = true;

        $table->filteronloadinactive = true;

        $table->define_baseurl(new moodle_url('/local/catquiz/downloads/download.php'));

        list(, , $html) = $table->lazyouthtml(10, true);
        return $html;
    }

    /**
     * Return all data to be rendered and displayed.
     * @return array
     */
    public function export_data_array(): array {

        $data = [
            'table' => $this->render_calculations_log_table(),
        ];
        return $data;
    }
}
