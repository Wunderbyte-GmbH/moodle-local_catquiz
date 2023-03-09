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
 * catquiz catscales view page
 * @package    local_catquiz catscales
 * @copyright  2023 Wunderbyte GmbH
 * @author     Georg Maißer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_catquiz;
use local_catquiz\testenvironment;

require_once('../../config.php');

$context = \context_system::instance();
$PAGE->set_context($context);
require_login();
require_capability('local/catquiz:manage_testenvironments', $context);

$PAGE->set_url(new moodle_url('/local/catquiz/manage_testenvironments.php', array()));

$title = get_string('pluginname', 'local_catquiz');
$PAGE->set_title($title);
$PAGE->set_heading($title);

echo $OUTPUT->header();

// Get an array to display all the test environments.
$data = testenvironment::get_all_environments_as_array();

echo $OUTPUT->render_from_template('local_catquiz/catscalesdashboard', $data);

echo $OUTPUT->footer();
