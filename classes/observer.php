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
        if (str_contains($classname, 'local_catquiz')) {
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
     * A change in the question bank that the runtime pool reads (issue #146).
     *
     * The pool takes a question whose newest version is ready (issue #82: the status of the newest version
     * of the bank entry decides; the item plays the version it refers to). A question hidden or set to draft,
     * a new version saved or a question moved changes it, without any CATquiz event - so far the pool took that in
     * only after its lifetime of ten minutes. When the question - in any of its versions - is a CAT item,
     * the pool is invalidated now; any other question of the site leaves it alone.
     *
     * @param base $event question_updated, question_created or question_moved.
     */
    public static function question_bank_changed(base $event) {
        global $DB;
        $questionid = (int) $event->objectid;
        if ($questionid <= 0) {
            return;
        }
        $isitem = $DB->record_exists_sql(
            "SELECT 1
               FROM {question_versions} qv
               JOIN {question_versions} qvall ON qvall.questionbankentryid = qv.questionbankentryid
               JOIN {local_catquiz_items} i ON i.componentid = qvall.questionid AND i.componentname = 'question'
              WHERE qv.questionid = :questionid",
            ['questionid' => $questionid]
        ) || $DB->record_exists('local_catquiz_items', ['componentid' => $questionid, 'componentname' => 'question']);
        if ($isitem) {
            cache_helper::purge_by_event('changesintestitems');
        }
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
