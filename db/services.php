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

$services = [
        'Catquiz external' => [ // Very important, don't rename or will break local_bookingapi plugin!!!
                'functions' => [
                        'local_catquiz_start_new_attempt',
                        'local_catquiz_submit_result',
                        'local_catquiz_get_next_question',
                ],
                'restrictedusers' => 0,
                'shortname' => 'local_catquiz_external',
                'downloadfiles' => 1, // Allow file downloads.
                'uploadfiles'  => 1, // Allow file uploads.
                'enabled' => 1,
        ],
        'CatQuiz Response Service' => [
                'functions' => [
                        'local_catquiz_submit_catquiz_responses',
                        'local_catquiz_fetch_item_parameters',
                        'local_catquiz_recalculate_remote',
                ],
                'restrictedusers' => 0, // Allow all users.
                'enabled' => 1,
                'shortname' => 'local_catquiz_response',
                'downloadfiles' => 0,
                'uploadfiles' => 0,
        ],
        'CatQuiz Parameter Service' => [
                'functions' => ['local_catquiz_client_fetch_parameters'],
                'restrictedusers' => 0,
                'enabled' => 1,
                'shortname' => 'local_catquiz_parameter',
                'downloadfiles' => 0,
                'uploadfiles' => 0,
        ],
];


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
        'local_catquiz_start_new_attempt' => [
                'classname' => 'local_catquiz\external\start_new_attempt',
                'classpath' => '',
                'description' => 'Starts a new attempt for given user.',
                'type' => 'write',
                'capabilities' => '',
                'ajax' => 1,
        ],
        'local_catquiz_submit_result' => [
                'classname' => 'local_catquiz\external\submit_result',
                'classpath' => '',
                'description' => 'Submits the score of an answered question',
                'type' => 'write',
                'capabilities' => '',
                'ajax' => 1,
        ],
        'local_catquiz_get_next_question' => [
                'classname' => 'local_catquiz\external\get_next_question',
                'classpath' => '',
                'description' => 'Receive a new question id within a started attempt.',
                'type' => 'write',
                'capabilities' => '',
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
        'local_catquiz_render_question_with_response' => [
                'classname' => 'local_catquiz\external\render_question_with_response',
                'description' => 'Renders a question with a response',
                'type' => 'read',
                'ajax' => 1,
        ],
        // Allows other instances to share their response data.
        'local_catquiz_submit_catquiz_responses' => [
            'classname' => 'local_catquiz\\external\\submit_responses',
            'methodname' => 'execute',
            'classpath' => 'local/catquiz/classes/external/submit_responses.php',
            'description' => 'Submit responses for CatQuiz.',
            'type' => 'write',
            // Should we require a capability? E.g. 'capabilities' => 'local/catquiz:submit_responses'?
            'ajax' => true,
        ],
        // Allows other instances to receive item parameters.
        'local_catquiz_fetch_item_parameters' => [
            'classname' => 'local_catquiz\\external\\fetch_item_parameters',
            'methodname' => 'execute',
            'classpath' => 'local/catquiz/classes/external/fetch_item_parameters.php',
            'description' => 'Fetch item parameters.',
            'type' => 'read',
            'ajax' => true,
        ],
        'local_catquiz_client_fetch_parameters' => [
                'classname' => 'local_catquiz\\external\\client_fetch_parameters',
                'methodname' => 'execute',
                'description' => 'Fetch item parameters from central instance',
                'type' => 'write',
                'capabilities' => 'moodle/site:config',
                'ajax' => true,
        ],
        'local_catquiz_recalculate_remote' => [
                'classname' => 'local_catquiz\\external\\recalculate_remote',
                'methodname' => 'execute',
                'description' => 'Enqueue an adhoc task to recalculate the parameters based on submitted responses',
                'type' => 'write',
                'capabilities' => 'moodle/site:config',
                'ajax' => true,
        ],
];
