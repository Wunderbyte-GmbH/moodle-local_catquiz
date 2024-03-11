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
 * Event observers.
 *
 * @package local_catquiz
 * @copyright 2024 Georg Maißer <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\event\base;
use local_catquiz\catscale;
use local_catquiz\event\catscale_updated;
use local_catquiz\event\testitem_imported;
use local_catquiz\event\testiteminscale_added;
use local_catquiz\messages;
use mod_adaptivequiz\event\attempt_completed;
use core\event\question_deleted;
use local_catquiz\feedback\feedbackclass;

/**
 * Event observer for local_catquiz.
 */
class local_catquiz_observer {

    /**
     * Observer for the update_catscale event
     *
     * @param base $event
     */
    public static function purge_event_cache(base $event) {

        $classname = get_class($event);
        if (strpos($classname, 'local_catquiz') >= 0) {
            cache_helper::purge_by_event('changesineventlog');
        };
    }

    /**
     * Observer for the update_catscale event
     *
     * @param catscale_updated $event
     */
    public static function catscale_updated(catscale_updated $event) {

        $catscaleid = $event->objectid;
        $userid = $event->userid;

        // See which users need to actually be notified.
        $catscale = catscale::return_catscale_object($catscaleid);

        messages::catscale_updated($catscale, $userid);
        cache_helper::purge_by_event('changesincatscales');
    }

    /**
     * Observer for the question_deleted event
     *
     * @param question_deleted $event
     */
    public static function question_deleted(question_deleted $event) {
        global $DB;

        // Questions used (for example in a quiz) are not deleted, just hidden
        // ...therefore the deletion from the table should never really apply.
        $questionid = $event->objectid;
        $data = [
            'componentid' => $questionid,
        ];
        $DB->delete_records('local_catquiz_items', $data);

        cache_helper::purge_by_event('changesintestitems');
    }

    /**
     * Observer for the testitem_imported event.
     *
     * @param testitem_imported $event
     */
    public static function testitem_imported(testitem_imported $event) {

        cache_helper::purge_by_event('changesintestitems');
        cache_helper::purge_by_event('changesineventlog');
    }


}
