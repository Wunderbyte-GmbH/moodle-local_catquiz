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
                'downloadfiles' => 1,    // Allow file downloads.
                'uploadfiles'  => 1,      // Allow file uploads.
                'enabled' => 1,
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
];
