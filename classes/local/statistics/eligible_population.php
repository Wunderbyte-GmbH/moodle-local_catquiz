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

namespace local_catquiz\local\statistics;

use context_module;
use local_catquiz\catscale;

/**
 * Who could have taken a test: the population behind "no attempt" (issue #132).
 *
 * A person belongs to the population of a test instance when, today,
 * - they are actively enrolled in its course (not suspended, enrolment not expired),
 * - they may attempt it (mod/adaptivequiz:attempt in the activity),
 * - the activity is visible to them (hidden only for those who see hidden activities), and
 * - its availability conditions that select people let them in - group, grouping, profile fields,
 *   completion and the like (core_availability\info_module::filter_user_list()). A time window says
 *   when, not who: once it has closed, the people who could have taken the test stay the population.
 *
 * Several instances of a scale form the union of their populations. Without a course filter the
 * instances of the scale in every course count - never everyone enrolled anywhere. With a test filter,
 * only that test counts. The statistic stays an aggregate: who is looking does not change it.
 *
 * Current access counts, not who had access earlier; people with attempts are counted by their
 * attempts whatever their access is today (catquiz::get_sql_for_attempts_per_person()).
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class eligible_population {
    /** @var array Populations computed in this request, by scope. */
    private static array $cache = [];

    /**
     * The people who could take a test of the scale in the scope.
     *
     * @param int $scaleid The scale; a subscale stands for its root scale.
     * @param int|null $courseid Only tests in this course, or null for all courses.
     * @param int|null $testid Only this test (adaptivequiz.id), or null for all tests of the scale.
     * @return int[] User ids, sorted.
     */
    public static function userids(int $scaleid, ?int $courseid, ?int $testid): array {
        $key = implode('/', [$scaleid, (int) $courseid, (int) $testid]);
        if (!array_key_exists($key, self::$cache)) {
            $users = [];
            foreach (self::instances($scaleid, $courseid, $testid) as $instanceid) {
                $users += array_flip(self::for_instance($instanceid));
            }
            $users = array_keys($users);
            sort($users);
            self::$cache[$key] = $users;
        }
        return self::$cache[$key];
    }

    /**
     * Forgets the populations of this request (tests, and after changes to enrolments or access).
     */
    public static function reset(): void {
        self::$cache = [];
    }

    /**
     * The test instances of the scale in the scope.
     *
     * @param int $scaleid
     * @param int|null $courseid
     * @param int|null $testid
     * @return int[] adaptivequiz ids
     */
    private static function instances(int $scaleid, ?int $courseid, ?int $testid): array {
        global $DB;

        $parentid = $DB->get_field('local_catquiz_catscales', 'parentid', ['id' => $scaleid]);
        $rootid = empty($parentid) ? $scaleid : (int) catscale::get_ancestors($scaleid, 3)['mainscale'];

        $where = "t.component = :component AND t.componentid > 0 AND t.catscaleid = :rootid";
        $params = ['component' => 'mod_adaptivequiz', 'rootid' => $rootid];
        if ($courseid) {
            $where .= ' AND q.course = :courseid';
            $params['courseid'] = $courseid;
        }
        if ($testid) {
            $where .= ' AND t.componentid = :testid';
            $params['testid'] = $testid;
        }
        // Joined with the activity: a test whose activity was deleted has nobody who could take it.
        return array_map('intval', $DB->get_fieldset_sql(
            "SELECT DISTINCT t.componentid
               FROM {local_catquiz_tests} t
               JOIN {adaptivequiz} q ON q.id = t.componentid
              WHERE $where",
            $params
        ));
    }

    /**
     * The population of one test instance.
     *
     * @param int $instanceid adaptivequiz.id
     * @return int[] User ids.
     */
    private static function for_instance(int $instanceid): array {
        $cm = get_coursemodule_from_instance('adaptivequiz', $instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return [];
        }
        $context = context_module::instance($cm->id);
        // Active enrolments only, with the right to attempt the test in this activity.
        $users = get_enrolled_users($context, 'mod/adaptivequiz:attempt', 0, 'u.id', null, 0, 0, true);
        if (!$users) {
            return [];
        }
        $cminfo = get_fast_modinfo($cm->course)->get_cm($cm->id);
        if (!$cminfo->visible) {
            $users = array_filter(
                $users,
                fn($u) => has_capability('moodle/course:viewhiddenactivities', $context, $u->id)
            );
        }
        // Group, grouping, profile and the other conditions that select people; dates select no one.
        $users = (new \core_availability\info_module($cminfo))->filter_user_list($users);
        return array_map('intval', array_keys($users));
    }
}
