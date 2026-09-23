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
 * Quiz external functions and service definitions.
 *
 * @package local_catquiz
 * @category external
 * @copyright 2024 Wunderbyte GmbH (info@wunderbyte.at)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 4.0
 */

defined('MOODLE_INTERNAL') || die();

// Issue #67: the service "Catquiz external" and its three functions
// (local_catquiz_start_new_attempt, _submit_result, _get_next_question) were removed.
//
// They were never implemented: start_new_attempt returned attemptid 0 and did
// nothing, submit_result returned an empty array, and get_next_question answered with
// MAX(id) FROM {question} - any question of the installation, regardless of scale,
// context or attempt. At the same time they checked local/catquiz:canaccess, a
// CONTEXT_MODULE capability, in the system context and accepted a freely chosen user
// id. Repairing an interface that nobody calls and that never worked would have kept
// its attack surface for no benefit.
//
// A repository-wide search found no caller here or in the bundled dependencies. The
// comment that used to sit on this service warned against renaming it because of a
// "local_bookingapi" plugin; no such plugin exists in the Wunderbyte repositories or
// the Moodle plugin directory - it was carried over with copied code from the booking
// plugin and confirmed as such.

$services = [];

$functions = [
        'local_catquiz_delete_catscale' => [
                'classname' => 'local_catquiz\external\delete_catscale',
                'classpath' => '',
                'description' => 'Delete a catscale',
                'type' => 'write',
                'capabilities' => 'local/catquiz:manage_catscales',
                'ajax' => 1,
        ],
        'local_catquiz_create_catscale' => [
                'classname' => 'local_catquiz\external\manage_catscale',
                'classpath' => '',
                'description' => 'Manage or create a catscale',
                'type' => 'write',
                'capabilities' => 'local/catquiz:manage_catscales',
                'ajax' => 1,
        ],
        'local_catquiz_subscribe' => [
                'classname' => 'local_catquiz\external\subscribe',
                'classpath' => '',
                'description' => 'Subscribe to some listener.',
                'type' => 'write',
                'capabilities' => '',
                'ajax' => 1,
        ],
        'local_catquiz_update_parameters' => [
                'classname' => 'local_catquiz\external\update_parameters',
                'description' => 'Updates the item parameters',
                'type' => 'write',
                'ajax' => 1,
        ],
        'local_catquiz_execute_action' => [
                'classname' => 'local_catquiz\external\execute_action',
                'description' => 'Executes an action button',
                'type' => 'write',
                'capabilities' => '',
                'ajax' => true,
                'loginrequired' => true,
        ],
        'local_catquiz_reload_template' => [
                'classname' => 'local_catquiz\external\reload_template',
                'description' => 'Reloads a card',
                'type' => 'write',
                'capabilities' => '',
                'ajax' => true,
                'loginrequired' => true,
        ],
        'local_catquiz_feedback_tab_clicked' => [
                'classname' => 'local_catquiz\external\feedback_tab_clicked',
                'description' => 'Sends an event about a clicked feedback tab',
                'type' => 'write',
                'ajax' => 1,
        ],
        'local_catquiz_get_question_preview' => [
                'classname' => 'local_catquiz\\external\\get_question_preview',
                'description' => 'Returns the rendered text of one question for the lazy loaded preview',
                'type' => 'read',
                'ajax' => 1,
        ],
        'local_catquiz_render_question_with_response' => [
                'classname' => 'local_catquiz\external\render_question_with_response',
                'description' => 'Renders a question with a response',
                'type' => 'read',
                'ajax' => 1,
        ],
];
