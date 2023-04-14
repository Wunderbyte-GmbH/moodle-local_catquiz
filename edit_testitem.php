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

use local_catquiz\catmodel_info;
use local_catquiz\output\testitemdashboard;

require_once('../../config.php');

global $USER;

$context = \context_system::instance();

$PAGE->set_context($context);
require_login();

require_capability('local/catquiz:manage_catscales', $context);

$PAGE->set_url('/local/catquiz/edit_testitem.php');

$testitemid = required_param('id', PARAM_INT);
$catscaleid = required_param('catscaleid', PARAM_INT);
$contextid = optional_param('contextid', 0, PARAM_INT);

$title = get_string('testitemdashboard', 'local_catquiz');
$PAGE->set_title($title);
$PAGE->set_heading($title);

echo $OUTPUT->header();

// $response = catmodel_info::get_item_parameters(0, 126);

$data = new testitemdashboard($testitemid, $contextid);
$output = $PAGE->get_renderer('local_catquiz');
echo $output->render_testitemdashboard($data);

echo $OUTPUT->footer();
