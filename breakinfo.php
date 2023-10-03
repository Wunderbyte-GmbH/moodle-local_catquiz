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
 * Breakinfo view page
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH
 * @author     David Szkiba
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_catquiz\output\breakinfo;

require_once('../../config.php');

$context = \context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/local/catquiz/breakinfo.php');
require_login();
$PAGE->set_url(new moodle_url('/local/catquiz/breakinfo.php', []));

$title = 'TODO translate breakinfo';
$PAGE->set_title($title);
$PAGE->set_heading($title);
$cmid = required_param('cmid', PARAM_INT);
$breakend = required_param('breakend', PARAM_INT);

echo $OUTPUT->header();
$breakinfo = new breakinfo($cmid, $breakend);
$data = $breakinfo->export_for_template($OUTPUT);
echo $OUTPUT->render_from_template('local_catquiz/breakinfo', $data);
echo $OUTPUT->footer();