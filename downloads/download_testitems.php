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

/**
 * Baseurl of wunderbyte_table will always point to this file for download.
 * @package local_catquiz
 * @copyright 2023 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_wunderbyte_table\wunderbyte_table;

require_once("../../../config.php");

global $CFG, $PAGE;

require_login();

require_once($CFG->dirroot . '/local/wunderbyte_table/classes/wunderbyte_table.php');

$download = optional_param('download', '', PARAM_ALPHA);
$encodedtable = optional_param('encodedtable', '', PARAM_RAW);

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/downloads/download.php');

$table = wunderbyte_table::instantiate_from_tablecache_hash($encodedtable);

// We always want to make sure for download that...
// We have an id column...
// And we don't have the action column and checkbox column.

$newcolumns = [
    'componentid' => 'componentid',
];

$columnstoexclude = ['action', 'wbcheckbox'];
$columnstoinclude = [
    'label' => get_string('label', 'local_catquiz'),
    'guessing' => get_string('guessing', 'local_catquiz'),
    'timecreated' => get_string('timecreated'),
    'timemodified' => get_string('timemodified', 'local_catquiz'),
    'status' => get_string('status'),
    'catscaleid' => get_string('catscaleid', 'local_catquiz'),
    'catscalename' => 'catscalename',
    'parentscalenames' => 'parentscalenames',
];

foreach ($table->columns as $key => $value) {

    if (!in_array($key, $columnstoexclude, true)) {
        $newcolumns[$key] = $table->headers[$value];
    }
}
$newcolumns = array_merge($newcolumns, $columnstoinclude);
$table->columns = [];
$table->headers = [];

$table->define_columns(array_keys($newcolumns));
$table->define_headers(array_keys($newcolumns));

$table->is_downloading($download, 'download', 'download');

$table->printtable(20, true);
