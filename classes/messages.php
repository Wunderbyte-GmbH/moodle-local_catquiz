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

use core_user;

defined('MOODLE_INTERNAL') || die();

/**
 * Class catquiz
 *
 * @author Georg Maißer
 * @copyright 2022 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class messages {

    /**
     * Generic send message function for catquiz.
     *
     * @param integer $recepientid
     * @param string $messagesubject
     * @param string $message
     * @return void
     */
    public static function send_message(int $recepientid, string $messagesubject, string $messagetext) {

        $users = user_get_users_by_id([$recepientid]);

        $user = reset($users);

        $message = new \core\message\message();
        $message->component = 'local_catquiz'; // Your plugin's name.
        $message->name = 'updatecatscale'; // Your notification name from message.php.
        $message->userfrom = core_user::get_noreply_user(); // If the message is 'from' a specific user you can set them here.
        $message->userto = $user;
        $message->subject = $messagesubject;
        $message->fullmessage = $messagetext;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->notification = 1; // Because this is a notification generated from Moodle, not a user-to-user message.

        // Actually send the message
        $messageid = message_send($message);
    }

    /**
     * Notifiy all subscribed users of an update of a catscale.
     *
     * @param integer $catscaleid
     * @return void
     */
    public static function catscale_updated(int $catscaleid) {

        $userids = subscription::get_subscribed_user_ids($catscaleid, 'catscale');

        foreach ($userids as $userid) {
            self::send_message(
                $userid,
                get_string('catscaleupdatedtitle', 'local_catquiz'),
                get_string('catscaleupdatedbody', 'local_catquiz'));
        }
    }

}
