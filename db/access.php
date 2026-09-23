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

        // Guards the attempt web services (start_new_attempt, get_next_question,
        // submit_result). Those checked this capability while it was never declared
        // here, and an undeclared capability evaluates to false for everyone - the
        // admin included - so the endpoints denied every request.
        //
        // Module level, because taking a test happens in an activity, and allowed for
        // the roles that take or supervise one.
        'local/catquiz:canaccess' => [
            'captype' => 'read',
            'contextlevel' => CONTEXT_MODULE,
            'archetypes' => [
                'student' => CAP_ALLOW,
                'teacher' => CAP_ALLOW,
                'editingteacher' => CAP_ALLOW,
                'manager' => CAP_ALLOW,
            ],
        ],
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
        // Issue #18: this capability is checked in the context the attempt belongs
        // to (module, otherwise course). Declaring it at CONTEXT_MODULE lets it be
        // assigned and overridden per course and per quiz; system wide assignments
        // keep working, because Moodle inherits capabilities downwards.
        //
        // editingteacher is listed explicitly: role archetypes are independent
        // templates, an editingteacher role does NOT inherit the defaults granted to
        // the teacher archetype. Without this line an editing teacher - who already
        // holds view_users_feedback and may review other people's attempts - would
        // be the only teacher role unable to see the teacher feedback.
        'local/catquiz:view_teacher_feedback' => [
                'captype' => 'write',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => [
                        'manager' => CAP_ALLOW,
                        'editingteacher' => CAP_ALLOW,
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
        // Capability to trigger an incremental recalculation (issue #43).
        'local/catquiz:recalculate' => [
                'captype' => 'write',
                'contextlevel' => CONTEXT_SYSTEM,
                'archetypes' => [
                        'manager' => CAP_ALLOW,
                ],
        ],
        // Capability to trigger a disruptive recalculation (new context). This is
        // at least as strict as the incremental recalculation capability.
        'local/catquiz:disruptiverecalculate' => [
                'captype' => 'write',
                'contextlevel' => CONTEXT_SYSTEM,
                'riskbitmask' => RISK_DATALOSS,
                'archetypes' => [
                        'manager' => CAP_ALLOW,
                ],
        ],
];
