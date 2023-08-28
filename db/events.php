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
 * Plugin event listeners are defined here.
 *
 * @package     local_catquiz
 * @category    event
 * @copyright   2023 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 defined('MOODLE_INTERNAL') || die();

 $observers = array(
     array(
         'eventname' => '\local_catquiz\event\catscale_updated',
         'callback' => 'local_catquiz_observer::catscale_updated'
     ),
     array(
         'eventname' => '\mod_adaptivequiz\event\attempt_completed',
         'callback' => 'local_catquiz_observer::attempt_completed'
     ),
     array(
        'eventname' => '\mod_adaptivequiz\event\testiteminscale_added',
        'callback' => 'local_catquiz_observer::testiteminscale_added'
    ),
 );
