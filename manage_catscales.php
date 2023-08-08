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
 * @author     David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_catquiz\output\managecatscaledashboard;

require_once('../../config.php');

$catcontextid = optional_param('contextid', 0, PARAM_INT);
$catscale = optional_param('scaleid', -1, PARAM_INT);
$usesubs = optional_param('usesubs', 1, PARAM_INT);
$testitemid = optional_param('id', 0, PARAM_INT); // ID of record to be displayed in detail instead of table.
$componentname = optional_param('component', 'question', PARAM_TEXT); // ID of record to be displayed in detail instead of table.


global $USER;
$context = \context_system::instance();
$PAGE->set_context($context);
require_login();
require_capability('local/catquiz:manage_catscales', $context);

$PAGE->set_url(new moodle_url('/local/catquiz/manage_catscales.php', array()));

$title = sprintf(
    '%s: %s',
    get_string('catmanager', 'local_catquiz'),
    get_string('summary', 'local_catquiz')
);
$PAGE->set_title($title);
$PAGE->set_heading($title);

echo $OUTPUT->header();

$managecatscaledashboard = new managecatscaledashboard($testitemid, $catcontextid, $catscale, $usesubs, $componentname);
$data = $managecatscaledashboard->export_for_template($OUTPUT);
echo $OUTPUT->render_from_template('local_catquiz/managecatscaledashboard', $data);

echo $OUTPUT->footer();