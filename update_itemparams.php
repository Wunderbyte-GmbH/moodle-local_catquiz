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
 * Just for testing
 * @package    local_catquiz
 * @copyright  2024 Wunderbyte GmbH
 * @author     David Bogner, et al.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_catquiz\catcontext;
use local_catquiz\catmodel_info;
use local_catquiz\catquiz;
use local_catquiz\catscale;

require_once('../../config.php');

$catcontextid = optional_param('contextid', 0, PARAM_INT);
$catscale = optional_param('scaleid', -1, PARAM_INT);
$context = \context_system::instance();
$PAGE->set_context($context);
require_login();

$title = 'test update itemparams';
$PAGE->set_title($title);
$PAGE->set_heading($title);

echo $OUTPUT->header();

$scale = catscale::return_catscale_object(1);
$cmi = new catmodel_info();
//$mainscales = catquiz::get_all_scales_for_active_contexts();
//foreach ($mainscales as $scale) {
    //$context = catcontext::load_from_db($scale->contextid);
    // if (!$cmi->needs_update($context, $scale->id)) {
    //     continue;
    // }
    $cmi->update_params($scale->contextid, $scale->id);
//}