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
        'CatQuiz Hub Service' => [
                'functions' => [
                        // Endpoint on hub if node submits responses.
                        'local_catquiz_hub_collect_responses',
                        // Endpoint on hub if node wants to fetch.
                        'local_catquiz_hub_distribute_parameters',
                        // Endpoint on hub to enqueue param calculation.
                        'local_catquiz_hub_enqueue_parameter_recalculation',
                        // Endpoint on node to fetch parameters from hub.
                        'local_catquiz_node_fetch_parameters',
                        // Endpoint on node to submit parameters to hub.
                        'local_catquiz_node_submit_responses',
                ],
                'restrictedusers' => 0, // Allow all users.
                'enabled' => 1,
                'shortname' => 'local_catquiz_hub_service',
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
        'local_catquiz_hub_collect_responses' => [
            'classname' => 'local_catquiz\\external\\hub\\collect_responses',
            'methodname' => 'execute',
            'description' => 'Collects new responses from a node',
            'type' => 'write',
            'capabilities' => 'moodle/site:config',
            'ajax' => true,
        ],
        'local_catquiz_hub_distribute_parameters' => [
            'classname' => 'local_catquiz\\external\\hub\\distribute_parameters',
            'methodname' => 'execute',
            'description' => 'Allows nodes to fetch item parameters',
            'type' => 'write',
            'capabilities' => 'moodle/site:config',
            'ajax' => true,
        ],
        'local_catquiz_hub_enqueue_parameter_recalculation' => [
                'classname' => 'local_catquiz\\external\\hub\\enqueue_parameter_recalculation',
                'methodname' => 'execute',
                'description' => 'Enqueue an adhoc task to recalculate the parameters based on submitted responses',
                'type' => 'write',
                'capabilities' => 'moodle/site:config',
                'ajax' => true,
        ],
        'local_catquiz_node_submit_responses' => [
            'classname' => 'local_catquiz\\external\\node\\submit_responses',
            'methodname' => 'execute',
            'description' => 'Submit responses for a given scale ID from a node.',
            'type' => 'write',
            'capabilities' => 'moodle/site:config',
            'ajax' => true,
        ],
        'local_catquiz_node_fetch_parameters' => [
                'classname' => 'local_catquiz\\external\\node\\fetch_parameters',
                'methodname' => 'execute',
                'description' => 'Fetch item parameters from central instance',
                'type' => 'write',
                'capabilities' => 'moodle/site:config',
                'ajax' => true,
        ],
];
