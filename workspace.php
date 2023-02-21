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
 * Entities Class to display list of entity records.
 *
 * @package local_catquiz
 * @author Daniel Pasterk
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

use local_catquiz\catengine;
use local_catquiz\data;


$PAGE->set_url(new moodle_url('/local/catquiz/workspace.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('frontpage');
$url_front = new moodle_url('/workspace.php');
$url_plugin = new moodle_url('workspace.php');




echo $OUTPUT->header();

$quizdata_all = data\catquiz_base::get_question_results(0,0,0);


$map = local_catquiz\preprocess_data::get_fractions_by_question($quizdata_all);
$item_difficulty = local_catquiz\calc_item_parameter::get_all_item_difficulty($map);

var_dump($item_difficulty);

echo $OUTPUT->footer();