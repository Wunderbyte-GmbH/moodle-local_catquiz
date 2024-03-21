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
use moodle_url;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/user/lib.php');

/**
 * Class catquiz
 *
 * @author Georg Maißer
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class messages {

    /**
     * Generic send message function for catquiz.
     *
     * @param int $recepientid
     * @param string $messagesubject
     * @param string $messagetext
     * @param string $messagename
     *
     * @return void
     *
     */
    public static function send_message(int $recepientid, string $messagesubject, string $messagetext, string $messagename) {

        global $CFG;
        require_once($CFG->dirroot . '/user/lib.php');

        $users = user_get_users_by_id([$recepientid]);

        $user = reset($users);

        $message = new \core\message\message();
        $message->component = 'local_catquiz'; // Your plugin's name.
        $message->name = $messagename; // Your notification name from message.php.
        $message->userfrom = core_user::get_noreply_user(); // If the message is 'from' a specific user you can set them here.
        $message->userto = $user;
        $message->subject = $messagesubject;
        $message->fullmessage = $messagetext;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->notification = 1; // Because this is a notification generated from Moodle, not a user-to-user message.

        // Actually send the message.
        $messageid = message_send($message);
    }

    /**
     * Generic send message function for catquiz.
     *
     * @param int $recepientid
     * @param string $messagesubject
     * @param string $messagetext
     * @param string $messagename
     *
     * @return void
     *
     */
    public static function send_html_message(int $recepientid, string $messagesubject, string $messagetext, string $messagename) {

        global $CFG;
        require_once($CFG->dirroot . '/user/lib.php');

        $users = user_get_users_by_id([$recepientid]);

        $user = reset($users);

        $message = new \core\message\message();
        $message->component = 'local_catquiz'; // Your plugin's name.
        $message->name = $messagename; // Your notification name from message.php.
        $message->userfrom = core_user::get_noreply_user(); // If the message is 'from' a specific user you can set them here.
        $message->userto = $user;
        $message->subject = $messagesubject;
        $message->fullmessage = $messagetext;
        $message->fullmessagehtml = $messagetext;
        $message->fullmessageformat = FORMAT_HTML;
        $message->notification = 1; // Because this is a notification generated from Moodle, not a user-to-user message.

        // Actually send the message.
        $messageid = message_send($message);
    }

    /**
     * Notifiy all subscribed users of an update of a catscale.
     *
     * @param stdClass $catscale
     * @param int $usermodified
     *
     * @return void
     *
     */
    public static function catscale_updated(stdClass $catscale, int $usermodified) {

        global $DB, $CFG;

        require_once($CFG->dirroot . '/user/lib.php');

        $userids = subscription::get_subscribed_user_ids($catscale->id, 'catscale');

        $urltoscale = new moodle_url('/local/catquiz/edit_catscale.php',
            ['id' => $catscale->id]);
        $urltoscale = $urltoscale->out();

        $usermodified = $DB->get_record('user', ['id' => $usermodified]);

        $users = user_get_users_by_id($userids);

        foreach ($users as $user) {

            $data = (object)[
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'editorname' => $usermodified->firstname . ' ' . $usermodified->lastname,
                'instancename' => '<a href="' . $CFG->wwwroot . '">' . $CFG->wwwroot . '</a>',
                'catscalename' => $catscale->name,
                'linkonscale' => '<a href="' . $urltoscale . '">' . $urltoscale . '</a>',
                'changedescription' => '',
            ];

            self::send_message(
                $user->id,
                get_string('catscaleupdatedtitle', 'local_catquiz'),
                get_string('notificationcatscalechange', 'local_catquiz', $data),
                'updatecatscale');
        }
    }

}
