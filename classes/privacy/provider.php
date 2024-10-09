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
 * Privacy subsystem implementation for local_shopping_cart
 *
 * @package    local_catquiz
 * @copyright  Wunderbyte <info@wunderbyte.at>
 * @author     Magdalena Holczik
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_catquiz\privacy;

use context_module;
use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\user_preference_provider;
use core_privacy\local\request\userlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\writer;
use Exception;

/**
 * Privacy subsystem implementation for local_shopping_cart
 *
 * @package    local_catquiz
 * @copyright  Wunderbyte <info@wunderbyte.at>
 * @author     Magdalena Holczik
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements     \core_privacy\local\metadata\provider,
                            \core_privacy\local\request\subsystem\provider,
                            \core_privacy\local\request\core_userlist_provider {

    /**
     * Get the metadata associated with this plugin.
     *
     * @param collection $items A collection to add items to.
     * @return collection The updated collection of metadata items.
     */
    public static function get_metadata(collection $items): collection {
        // Adding information about the tables and data stored related to users.
        $items->add_database_table(
            'local_catquiz_subscriptions',
            [
                'userid' => 'privacy:metadata:local_catquiz_subscriptions:userid',
                'itemid' => 'privacy:metadata:local_catquiz_subscriptions:itemid',
                'area' => 'privacy:metadata:local_catquiz_subscriptions:area',
                'status' => 'privacy:metadata:local_catquiz_subscriptions:status',
                'timecreated' => 'privacy:metadata:local_catquiz_subscriptions:timecreated',
                'timemodified' => 'privacy:metadata:local_catquiz_subscriptions:timemodified',
            ],
            'privacy:metadata:local_catquiz_subscriptions'
        );

        $items->add_database_table(
            'local_catquiz_personparams',
            [
                'userid' => 'privacy:metadata:local_catquiz_personparams:userid',
                'catscaleid' => 'privacy:metadata:local_catquiz_personparams:catscaleid',
                'contextid' => 'privacy:metadata:local_catquiz_personparams:contextid',
                'attemptid' => 'privacy:metadata:local_catquiz_personparams:attemptid',
                'ability' => 'privacy:metadata:local_catquiz_personparams:ability',
                'standarderror' => 'privacy:metadata:local_catquiz_personparams:standarderror',
                'timecreated' => 'privacy:metadata:local_catquiz_personparams:timecreated',
                'timemodified' => 'privacy:metadata:local_catquiz_personparams:timemodified',
            ],
            'privacy:metadata:local_catquiz_personparams'
        );

        $items->add_database_table(
            'local_catquiz_attempts',
            [
                'userid' => 'privacy:metadata:local_catquiz_attempts:userid',
                'scaleid' => 'privacy:metadata:local_catquiz_attempts:scaleid',
                'contextid' => 'privacy:metadata:local_catquiz_attempts:contextid',
                'courseid' => 'privacy:metadata:local_catquiz_attempts:courseid',
                'attemptid' => 'privacy:metadata:local_catquiz_attempts:attemptid',
                'component' => 'privacy:metadata:local_catquiz_attempts:component',
                'instanceid' => 'privacy:metadata:local_catquiz_attempts:instanceid',
                'status' => 'privacy:metadata:local_catquiz_attempts:status',
                'timecreated' => 'privacy:metadata:local_catquiz_attempts:timecreated',
                'timemodified' => 'privacy:metadata:local_catquiz_attempts:timemodified',
            ],
            'privacy:metadata:local_catquiz_attempts'
        );

        $items->add_database_table(
            'local_catquiz_progress',
            [
                'id' => 'privacy:metadata:local_catquiz_progress:id',
                'userid' => 'privacy:metadata:local_catquiz_progress:userid',
                'component' => 'privacy:metadata:local_catquiz_progress:component',
                'attemptid' => 'privacy:metadata:local_catquiz_progress:attemptid',
                'json' => 'privacy:metadata:local_catquiz_progress:json',
                'quizsettings' => 'privacy:metadata:local_catquiz_progress:quizsettings',
            ],
            'privacy:metadata:local_catquiz_progress'
        );

        // Returning the updated metadata collection.
        return $items;
    }

    /**
     * Get the list of contexts that contain user information for a given user.
     *
     * @param int $userid The user ID to search for.
     * @return contextlist The list of contexts containing user information.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        // SQL query to find all contexts of courses where attempts where executed.
        $sql = "
        SELECT DISTINCT
            ctx.id AS contextid
        FROM
            {local_catquiz_attempts} ca
        JOIN
            {context} ctx ON (ctx.instanceid = ca.courseid AND ctx.contextlevel = 50)
        WHERE
            ca.userid = :userid
        ";

        $params = ['userid' => $userid];
        $contextlist->add_from_sql($sql, $params);
        return $contextlist;
    }

    /**
     * Export all user data for the given approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contextlist.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $user = $contextlist->get_user();
        $userid = $user->id;
        // All these contexts will be level course.
        $contexts = $contextlist->get_contexts();
        $modulecontextslist = [];
        // Iterate over each context to export user data.
        foreach ($contexts as $context) {
            $contextdata = helper::get_context_data($context, $user);
            // Fetch attemptdata for the right courses.
            $contextlevel = CONTEXT_COURSE;
            $sql = "
                SELECT
                    ca.*
                FROM
                    {local_catquiz_attempts} ca
                JOIN
                    {context} ctx ON (ca.courseid = ctx.instanceid AND ctx.contextlevel = :contextlevel)
                WHERE
                    ca.userid = :userid
                    AND ctx.id = :contextid
                ";
            $params = [
                'userid' => $userid,
                'contextid' => $context->id,
                'contextlevel' => $contextlevel,
            ];
            $attempts = $DB->get_records_sql($sql, $params);
            foreach ($attempts as $attempt) {
                // Progressdata is specific to attempt.
                $progresses = $DB->get_records('local_catquiz_progress', [
                    'userid' => $userid,
                    'attemptid' => $attempt->id,
                ]);
                $progressdata = [];
                foreach ($progresses as $progress) {
                    $progressdata[] = $progress->json;
                }
                $progress = (object) $progressdata;
                $data = [
                        'timecreated' => $attempt->timecreated,
                        'timemodified' => $attempt->timemodified,
                        'data' => $attempt->json,
                        'progress' => $progress,
                ];
                $data = (object) $data;
                // There might be more than one attempts in the same module (adaptivequizinstance).
                $modulecontextslist[$attempt->instanceid][] = $data;

            }
        }
              // Might move this out of context loop!
        foreach ($modulecontextslist as $instanceid => $attemptsinmodule) {
            $data = (object) $attemptsinmodule;
            $cm = get_coursemodule_from_instance('adaptivequiz', $instanceid);
            $modulecontext = context_module::instance($cm->id);
            writer::with_context($modulecontext)->export_data([], $data);
        }

        // Personparams.
        $personparams = $DB->get_records(
            'local_catquiz_personparams',
            [
                'userid' => $userid
            ]);
        // Personparams we apply to context system.
        $data = (object) $personparams;
        $systemcontext = context_system::instance();
        writer::with_context($systemcontext)->export_related_data([], 'catquiz_personparams', $data);
    }

    /**
     * Delete all user data for the given context.
     *
     * @param approved_contextlist $context The context to delete data from.
     * @param int $userid The user ID whose data to delete.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;

        $DB->delete_records('local_catquiz_subscriptions', ['userid' => $userid]);
        // We keep records from local_catquiz_personparams and local_catquiz_attempts because they are needed for statistics...
        // and do not contain personal identifiable information.
        // We rely on moodle core User Account Anonymization in users table.
    }

    /**
     * Delete data for users.
     *
     * @param \core_privacy\local\request\approved_userlist $userlist The list of users to delete data for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $userids = $userlist->get_userids();

        $DB->delete_records_list('local_catquiz_subscriptions', 'userid', $userids);

        // We keep records from local_catquiz_personparams and local_catquiz_attempts because they are needed for statistics...
        // and do not contain personal identifiable information.
        // We rely on moodle core User Account Anonymization in users table.
    }

    /**
     * Delete data for all users in given context.
     *
     * @param \context $context
     *
     * @return void
     *
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        // Only delete data of subscriptions table.
        switch($context->contextlevel) {
            case CONTEXT_SYSTEM:
                // System context, delete all data.
                $DB->delete_records('local_catquiz_subscriptions');
                break;
            case CONTEXT_COURSE:
                // Course context, delete data for all users in the course.
                $users = get_enrolled_users($context);
                $userids = array_map(function($u) {
                    return $u->id;
                }, $users);
                $DB->delete_records_list('local_catquiz_subscriptions', 'userid', $userids);
                break;
            case CONTEXT_MODULE:
                // Module context, delete data for all users in the module.
                $users = get_enrolled_users($context);
                break;
            default:
                try {
                    $users = get_enrolled_users($context);
                    $userids = array_map(function($u) {
                        return $u->id;
                    }, $users);
                    $DB->delete_records_list('local_catquiz_subscriptions', 'userid', $userids);
                } catch (Exception $e) {
                    // Do nothing.
                    $error = $e;
                }
                break;
        }

    }

    /**
     * Get the list of users who have data within a specific context.
     *
     * @param userlist $userlist The userlist object to add user IDs to.
     */
    public static function get_users_in_context(userlist $userlist) {
        global $DB;

        // Get the context from the userlist.
        $context = $userlist->get_context();

        // Ensure we only process course contexts for this example.
        if ($context instanceof \context_course ||
            $context instanceof context_module) {

            $users = get_enrolled_users($context);
            $params = ['courseid' => $context->instanceid];

            $sql = "SELECT userid
                    FROM {local_catquiz_attempts}";
            $usersfromattempts = $DB->get_records_sql($sql, $params);

            foreach ($users as $user) {
                if (in_array($user->id, array_keys($usersfromattempts))) {
                    $userlist->add_user($user->userid);
                }
            }
        }
    }
}
