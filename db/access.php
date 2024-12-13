<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin capabilities are defined here.
 *
 * @package     local_catquiz
 * @category    access
 * @copyright   2022 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

        'local/catquiz:canmanage' => [
            'captype' => 'write',
            'contextlevel' => CONTEXT_SYSTEM,
        ],
        'local/catquiz:subscribecatscales' => [
            'captype' => 'read',
            'contextlevel' => CONTEXT_SYSTEM,
            'archetypes' => [
                'manager' => CAP_ALLOW,
            ],
        ],
        'local/catquiz:manage_catscales' => [
                'captype' => 'write',
                'contextlevel' => CONTEXT_SYSTEM,
                'archetypes' => [
                        'manager' => CAP_ALLOW,
                ],
        ],
        'local/catquiz:manage_testenvironments' => [
                'captype' => 'write',
                'contextlevel' => CONTEXT_SYSTEM,
                'archetypes' => [
                        'manager' => CAP_ALLOW,
                ],
        ],
        'local/catquiz:manage_catcontexts' => [
                'captype' => 'write',
                'contextlevel' => CONTEXT_SYSTEM,
                'archetypes' => [
                        'manager' => CAP_ALLOW,
                ],
        ],
        'local/catquiz:view_teacher_feedback' => [
                'captype' => 'write',
                'contextlevel' => CONTEXT_SYSTEM,
                'archetypes' => [
                        'manager' => CAP_ALLOW,
                        'teacher' => CAP_ALLOW,
                ],
        ],
        // Capability to feedback of users other than current.
        'local/catquiz:view_users_feedback' => [
                'captype' => 'read',
                'contextlevel' => CONTEXT_COURSE,
                'archetypes' => [
                        'editingteacher' => CAP_ALLOW,
                        'teacher' => CAP_ALLOW,
                ],
        ],
];
