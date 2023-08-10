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
 *  Code for validation of developing process;
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

require_once(__DIR__ . '../../../../config.php');

$PAGE->set_url(new moodle_url('/local/catquiz/workspace.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('frontpage');
$url_front = new moodle_url('/workspace.php');
$url_plugin = new moodle_url('workspace.php');

echo $OUTPUT->header();

print "hehe";

$sum = 0;
$mycallable = function($x) {return 0;};

$my_x = 3;

$history = [];

$callables = [];
for ($i=1; $i<=100;$i++){

    $rnd = rand(0,10);
    array_push($history,$rnd);

    $funpart = function ($x) use ($rnd) {return $rnd*$x;};
    //$funpart = function ($x) use ($rnd) {return rand(0,100)*$x;};

    $sum = $sum + $funpart($my_x);

    $mycallable = fn($x) => $mycallable($x) + $funpart($x);
    array_push($callables,$mycallable);
}

echo "finished";

echo $OUTPUT->footer();
