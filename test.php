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

use local_catquiz\event\catscale_updated;
use local_catquiz\subscription;

require_once('../../config.php');

global $USER;

$context = \context_system::instance();

$PAGE->set_context($context);
require_login();

require_capability('local/catquiz:manage_catscales', $context);

$PAGE->set_url(new moodle_url('/local/catquiz/test.php', array()));

$title = "Test cases";
$PAGE->set_title($title);
$PAGE->set_heading($title);

echo $OUTPUT->header();

echo 'my test';

$userid = $USER->id;

$event = catscale_updated::create([
    'objectid' => 79,
    'context' => $context,
    'userid' => $userid, // The user who did cancel.
]);
$event->trigger();

$subscribed = subscription::return_subscription_state($userid, 'catscale', 79);

$data = [
    'id' => 79,
    'area' => 'catscale'];

if ($subscribed) {
    $data['subscribed'] = 'true';
}

echo $OUTPUT->render_from_template('local_catquiz/button_subscribe', $data);

echo $OUTPUT->footer();
