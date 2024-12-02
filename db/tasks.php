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

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => recalculate_cat_model_params::class,
        'blocking' => 0,
        'minute' => 'R',
        'hour' => '0',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*',
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
        'classname' => 'local_catquiz\task\recalculate_remote_item_parameters',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '3',
        'day' => '1',
        'month' => '*',
        'dayofweek' => '*',
        'disabled' => 0,
    ],
];
