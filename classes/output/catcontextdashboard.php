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
use local_catquiz\table\catcontext_table;
use moodle_url;
use templatable;
use renderable;

/**
 * Renderable class for the catcontext
 *
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH
 * @author     Georg Maißer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catcontextdashboard implements renderable, templatable {

    /**
     * Construct
     *
     * @return void
     */
    public function __construct() {

    }

    public function catcontexttable() {

        $table = new catcontext_table('catcontexttable');

        list($select, $from, $where, $filter, $params) = catquiz::return_sql_for_catcontexts();

        $table->set_filter_sql($select, $from, $where, $filter, $params);

        $columns = [
            'name' => get_string('name'),
            'starttimestamp' => get_string('starttimestamp', 'local_catquiz'),
            'endtimestamp' => get_string('endtimestamp', 'local_catquiz'),
            'timecreated' => get_string('timecreated'),
            'timemodified' => get_string('timemodified', 'local_catquiz'),
            'attempts' => get_string('attempts', 'local_catquiz'),
            'action' => get_string('action', 'local_catquiz'),
        ];

        $table->define_columns(array_keys($columns));
        $table->define_headers(array_values($columns));

        $table->define_fulltextsearchcolumns(['name']);
        $table->define_sortablecolumns(array_keys($columns));

        // $table->tabletemplate = 'local_wunderbyte_table/twtable_list';
        $table->define_cache('local_catquiz', 'catcontexts');

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
            'table' => $this->catcontexttable(),
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
