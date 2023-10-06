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
 *  Code for validation of developing process.
 *
 * @package local_catquiz
 * @author Daniel Pasterk
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_catquiz\catcontext;
use local_catquiz\local\model\model_item_param;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_person_param;
use local_catquiz\local\model\model_person_param_list;
use local_catquiz\local\model\model_responses;
use local_catquiz\local\model\model_strategy;
use local_catquiz\matrix;

require_once(__DIR__ . '../../../../config.php');

require_login();

$PAGE->set_url(new moodle_url('/local/catquiz/workspace.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('frontpage');
$urlfront = new moodle_url('/workspace.php');
$urlplugin = new moodle_url('workspace.php');

echo $OUTPUT->header();

// PHP testbed for matrix inversion.

$testmatrix = [[2, 2, 3], [4, 5, 6], [7, 8, 9]];

$m = new matrix($testmatrix);

$minv = $m->inverse();

$tst = $minv[1][1];

echo "finished";

echo $OUTPUT->footer();
