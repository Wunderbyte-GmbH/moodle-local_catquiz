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
 * Plugin tasks are defined here.
 *
 * @package     local_catquiz
 * @copyright   2023 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_catquiz\task\cancel_expired_attempts;
use local_catquiz\task\recalculate_cat_model_params;
use local_catquiz\task\cleanup_attempt_progress;

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => recalculate_cat_model_params::class,
        'blocking' => 0,
        // Issue #44: safe defaults. The incremental recalculation must be a deliberate
        // admin decision, so it ships disabled, and its default cadence is
        // quarterly (1st of every third month) instead of daily. Admin changes to
        // the schedule are preserved on upgrade (Moodle keeps customised tasks).
        'disabled' => 1,
        'minute' => 'R',
        'hour' => '0',
        'day' => '1',
        'dayofweek' => '*',
        'month' => '*/3',
    ],
    [
        'classname' => cancel_expired_attempts::class,
        'blocking' => 0,
        'minute' => '*/5', // Runs every 5 minutes.
        'hour' => '*',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*',
    ],
    [
        // Issue #56: applies the configured retention period to attempt progress.
        'classname' => cleanup_attempt_progress::class,
        'blocking' => 0,
        'minute' => '30',
        'hour' => '3',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*',
    ],
];
