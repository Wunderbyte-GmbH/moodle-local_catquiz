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
 * Test file for catquiz
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH
 * @author     Georg Maißer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_catquiz\catquiz;
use local_catquiz\event\catscale_updated;
use local_catquiz\subscription;
use local_catquiz\table\testitems_table;

require_once('../../config.php');

global $USER;

$context = \context_system::instance();

$PAGE->set_context($context);
require_login();

require_capability('local/catquiz:manage_catscales', $context);

$PAGE->set_url(new moodle_url('/local/catquiz/test.php', array()));

$title = get_string('assigntestitemstocatscales', 'local_catquiz');
$PAGE->set_title($title);
$PAGE->set_heading($title);

echo $OUTPUT->header();


$data = [
    'catscaleid' => 1,
    'containerselector' => 'wunderbyte_table_container_catscaleedit',
    'inputclass' => 'testitem-checkbox',
];
echo $OUTPUT->render_from_template('local_catquiz/button_assign', $data);

$table = new testitems_table('catscaleedit');

list($select, $from, $where, $filter) = catquiz::return_sql_for_questions();

$table->set_filter_sql($select, $from, $where, $filter);

$table->define_columns(['action', 'id', 'name', 'questiontext', 'qtype', 'categoryname']);

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

$table->pageable(true);

$table->stickyheader = false;
$table->showcountlabel = true;
$table->showdownloadbutton = true;
$table->showreloadbutton = true;

$table->out(20, true);

echo $OUTPUT->footer();
