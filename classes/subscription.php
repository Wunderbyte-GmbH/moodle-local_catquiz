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
 * Entities Class to display list of entity records.
 *
 * @package local_catquiz
 * @author Thomas Winkler
 * @copyright 2021 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../lib.php');

/**
 * Class handles subscriptions for catquiz.
 *
 * @author Georg Maißer
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class subscription {

    /**
     * Toggles the subscription and returns the state.
     *
     * @param int $userid
     * @param string $area
     * @param int $itemid
     *
     * @return array
     *
     */
    public static function toggle_subscription(int $userid, string $area, int $itemid) {

        global $DB, $USER;

        $now = time();

        if (!$record = $DB->get_record('local_catquiz_subscriptions', [
            'userid' => $userid,
            'area' => $area,
            'itemid' => $itemid,
            ])) {

            $record = (object)[
                'userid' => $userid,
                'itemid' => $itemid,
                'area' => $area,
                'status' => LOCAL_CATQUIZ_STATUS_SUBSCRIPTION_BOOKED,
                'usermodified' => $USER->id,
                'timemodified' => $now,
                'timecreated' => $now,
            ];

            $DB->insert_record('local_catquiz_subscriptions', $record);
        } else {
            switch ($record->status) {
                case LOCAL_CATQUIZ_STATUS_SUBSCRIPTION_BOOKED:
                    $record->status = LOCAL_CATQUIZ_STATUS_SUBSCRIPTION_DELETED;
                    $record->timemodified = $now;
                    $record->usermodified = $USER->id;
                    break;
                case LOCAL_CATQUIZ_STATUS_SUBSCRIPTION_DELETED:
                    $record->status = LOCAL_CATQUIZ_STATUS_SUBSCRIPTION_BOOKED;
                    $record->timemodified = $now;
                    $record->usermodified = $USER->id;
                    break;
            }
            $DB->update_record('local_catquiz_subscriptions', $record);
        }

        return ['subscribed' => $record->status];
    }

    /**
     * Toggles the subscription and returns the state.
     *
     * @param int $userid
     * @param string $area
     * @param int $itemid
     * @return integer
     */
    public static function return_subscription_state(int $userid, string $area, int $itemid) {

        global $DB;

        $status = 0;
        $booked = LOCAL_CATQUIZ_STATUS_SUBSCRIPTION_BOOKED;

        if ($DB->record_exists('local_catquiz_subscriptions', [
            'userid' => $userid,
            'itemid' => $itemid,
            'area' => $area,
            'status' => LOCAL_CATQUIZ_STATUS_SUBSCRIPTION_BOOKED,
            ])) {
            $status = 1;
        }

        return $status;
    }

    /**
     * Return subscribed usersids as array of one subscribeable item.
     *
     * @param int $itemid
     * @param string $area
     *
     * @return array
     */
    public static function get_subscribed_user_ids(int $itemid, string $area) {

        global $DB;

        $params = [
            'itemid' => $itemid,
            'area' => $area,
        ];

        $sql = 'itemid = :itemid AND area = :area';

        $userids = $DB->get_fieldset_select('local_catquiz_subscriptions', 'userid', $sql, $params);

        return $userids;
    }
}
