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
 * Single source of truth for "may this user see other users' results?".
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\local\access;

use context;
use context_system;

/**
 * Decides whether the current user may see results of users other than themselves.
 *
 * Statistics views, exports and AJAX endpoints each carried their own
 * variant of this check, some of them against the system context only. That made
 * it possible for the exported CSV and the rendered page to disagree about who is
 * allowed to see what. All of them now call into this class, so the rule exists
 * exactly once.
 *
 * The rule: a CAT manager may see everything site wide; anybody else needs
 * local/catquiz:view_users_feedback in the context the data belongs to.
 */
class feedback_access {
    /**
     * Whether the current user may see other users' results in this context.
     *
     * @param context $context The context the viewed data belongs to.
     * @return bool
     */
    public static function can_view_other_users(context $context): bool {
        if (has_capability('local/catquiz:canmanage', context_system::instance())) {
            return true;
        }

        return has_capability('local/catquiz:view_users_feedback', $context);
    }

    /**
     * Whether the current user may see the results of one particular user.
     *
     * Participants always see their own attempts, never those of others.
     *
     * @param context $context The context the viewed data belongs to.
     * @param int $userid The owner of the data.
     * @return bool
     */
    public static function can_view_user(context $context, int $userid): bool {
        global $USER;

        if ((int) $userid === (int) $USER->id) {
            return true;
        }

        return self::can_view_other_users($context);
    }

    /**
     * Returns the ids of the users whose results may be shown in this context.
     *
     * Honours the Moodle group mode: in separate groups mode a teacher without
     * moodle/site:accessallgroups only sees members of their own groups. Returns
     * null when no group restriction applies, so callers can skip filtering
     * entirely instead of building a needlessly large IN() clause.
     *
     * Both levels are covered. With a course module the activity group mode decides;
     * for a course-wide view the course group mode does. The latter used to be
     * skipped altogether, which released every participant of the course exactly
     * where the widest set of people is shown.
     *
     * @param context $context The context the viewed data belongs to.
     * @return int[]|null Allowed user ids, or null if unrestricted.
     */
    public static function get_allowed_userids(context $context): ?array {
        global $USER;

        $cm = self::get_coursemodule($context);

        if ($cm) {
            // Moodle returns the group mode as a string ('1'), so a strict comparison
            // against the int constant would never match. Cast explicitly rather than
            // relying on loose comparison.
            $groupmode = (int) groups_get_activity_groupmode($cm);
            $groups = $groupmode === SEPARATEGROUPS ? groups_get_activity_allowed_groups($cm) : [];
        } else {
            // Course-wide views have no course module, and simply returning null here
            // released every participant of the course: the group restriction was
            // skipped precisely where the widest set of people is shown. The course
            // group mode applies instead - it is what a course-wide statistic is
            // governed by.
            $coursecontext = $context->get_course_context(false);
            if (!$coursecontext) {
                // System context: no course, so no group semantics to apply.
                return null;
            }

            $course = get_course($coursecontext->instanceid);
            $groupmode = (int) $course->groupmode;
            $groups = $groupmode === SEPARATEGROUPS
                ? groups_get_all_groups($course->id, $USER->id)
                : [];
        }

        if ($groupmode !== SEPARATEGROUPS) {
            return null;
        }

        if (has_capability('moodle/site:accessallgroups', $context)) {
            return null;
        }

        $allowed = [];
        foreach ($groups as $group) {
            foreach (groups_get_members($group->id, 'u.id') as $member) {
                $allowed[(int) $member->id] = (int) $member->id;
            }
        }

        // Users may always see themselves, even outside any group.
        $allowed[(int) $USER->id] = (int) $USER->id;

        return array_values($allowed);
    }

    /**
     * Returns the course module of a context, if it has one.
     *
     * @param context $context
     * @return \cm_info|\stdClass|null
     */
    private static function get_coursemodule(context $context) {
        if ($context->contextlevel !== CONTEXT_MODULE) {
            return null;
        }

        return get_coursemodule_from_id('', $context->instanceid, 0, false, IGNORE_MISSING) ?: null;
    }
}
