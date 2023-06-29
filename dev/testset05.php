<?php
use local_catquiz\catcontext;
use local_catquiz\local\model\model_item_param;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_person_param;
use local_catquiz\local\model\model_person_param_list;
use local_catquiz\local\model\model_responses;
use local_catquiz\local\model\model_strategy;

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

require_once('../../../config.php');
//use \local_catquiz;

$PAGE->set_url(new moodle_url('/local/catquiz/workspace.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('frontpage');
$url_front = new moodle_url('/workspace.php');
$url_plugin = new moodle_url('workspace.php');

echo $OUTPUT->header();



$response = new model_responses();



$synth_item_response = \local_catquiz\synthcat::get_item_response2(40,65,0.0);


# estimate item parameter with 1 PL

$start = [0.2];
$model_1pl = new \catmodel_raschbirnbauma\raschbirnbauma($response,"Rasch_1PL");
$params_model_1pl = \local_catquiz\catcalc::estimate_item_params($synth_item_response, $model_1pl, $start);

# estimate item parameter with 2 PL

$start = [1, 3];
$model_2pl = new \catmodel_raschbirnbaumb\raschbirnbaumb($response,"Rasch_2PL");
$params_model_2pl = \local_catquiz\catcalc::estimate_item_params($synth_item_response, $model_2pl, $start);

# estimate item parameter with 3 PL

$start = [1, 3, 0.2];
$model_3pl = new \catmodel_raschbirnbaumc\raschbirnbaumc($response,"Rasch_3PL");
$params_model_3pl = \local_catquiz\catcalc::estimate_item_params($synth_item_response, $model_3pl, $start);




echo "finished";

echo $OUTPUT->footer();
