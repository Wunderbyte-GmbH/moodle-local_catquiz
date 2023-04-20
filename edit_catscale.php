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
use local_catquiz\output\catscaledashboard;
use local_catquiz\subscription;
use local_catquiz\table\testitems_table;

require_once('../../config.php');

global $USER, $PAGE, $DB;

$context = \context_system::instance();
$PAGE->set_url('/local/catquiz/edit_catscale.php');

$PAGE->set_context($context);
require_login();

require_capability('local/catquiz:manage_catscales', $context);

$catscaleid = required_param('id', PARAM_INT);
$contextid = optional_param('contextid', 0, PARAM_INT);
$triggercalculation = optional_param('calculate', false, PARAM_BOOL);

if (empty($contextid)) {
    $contextid = $DB->get_field_sql("SELECT id FROM {local_catquiz_catcontext} WHERE " . $DB->sql_like(
        'json',
        "'default'"
    ));
}

$title = get_string('assigntestitemstocatscales', 'local_catquiz');
$PAGE->set_title($title);

$data = new catscaledashboard($catscaleid, $contextid, $triggercalculation);
$output = $PAGE->get_renderer('local_catquiz');
echo $output->render_catscaledashboard($data);

echo $OUTPUT->footer();
