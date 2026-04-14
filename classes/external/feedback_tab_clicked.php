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
 * This class contains a webservice to trigger a tab-changed event.
 *
 * @package    local_catquiz
 * @copyright  2024 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_catquiz\external;

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use local_catquiz\event\feedbacktab_clicked;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * External Service for local catquiz.
 *
 * @package   local_catquiz
 * @copyright 2024 Wunderbyte GmbH {@link http://www.wunderbyte.at}
 * @author    David Szkiba
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedback_tab_clicked extends external_api {

    /**
     * Describes the parameters for update_parameters webservice.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid'  => new external_value(PARAM_INT, 'attemptid'),
            'feedback' => new external_value(PARAM_TEXT, 'feedback'),
            'feedbacktranslated' => new external_value(PARAM_TEXT, 'feedbacktranslated'),
            ]
        );
    }

    /**
     * Webservice for the local catquiz plugin to update context parameters
     *
     * @param int $attemptid
     * @param string $feedback
     * @param string $translatedfeedback
     *
     * @return array
     */
    public static function execute(int $attemptid, string $feedback, string $translatedfeedback): array {
        global $USER;
        self::validate_parameters(self::execute_parameters(), [
            'attemptid' => $attemptid,
            'feedback' => $feedback,
            'feedbacktranslated' => $translatedfeedback,
        ]);

        $ctx = \context_system::instance();
        $role = has_capability('local/catquiz:canmanage', $ctx)
            ? 'catmanager'
            : 'student';

        $event = feedbacktab_clicked::create([
            'context' => \context_system::instance(),
            'other' => [
                'attemptid' => $attemptid,
                'feedback' => $feedback,
                'feedback_translated' => $translatedfeedback,
                'userid' => $USER->id,
                'role' => $role,
            ],
        ]);
        $event->trigger();

        return [
            'success' => false,
            'message' => '',
        ];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Successful calculation', VALUE_REQUIRED),
            'message' => new external_value(PARAM_RAW, 'message if necessary', VALUE_OPTIONAL, ''),
            ]
        );
    }
}
