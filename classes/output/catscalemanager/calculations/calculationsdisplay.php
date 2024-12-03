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

use core_form\dynamic_form;
use html_writer;
use local_catquiz\catquiz;
use local_catquiz\form\remote_settings_form;
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
class calculationsdisplay {

    /**
     * Constructor.
     *
     */
    public function __construct() {

    }

    /**
     * Renders the remote calculation configuration form.
     *
     * @return void
     */
    public function render_remote_calculation_config() {
        /** @var dynamic_form */
        $form = new remote_settings_form();
        return html_writer::div($form->render(), '', ['id' => 'remote_settings_form']);
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

        $standardfilter = new standardfilter('username', get_string('user', 'core'));
        $table->add_filter($standardfilter);

        $datepicker = new datepicker('timecreated', get_string('logsafter', 'local_catquiz'));
        $datepicker->add_options(
            'standard',
            '>',
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

        list(, , $html) = $table->lazyouthtml(10, true);
        return $html;
    }

    /**
     * Return all data to be rendered and displayed.
     * @return array
     */
    public function export_data_array(): array {

        $data = [
            'remoteconfig' => $this->render_remote_calculation_config(),
            'table' => $this->render_calculations_log_table(),
        ];
        return $data;
    }
}
