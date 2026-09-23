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
 * Resolves the Moodle context that a CAT quiz attempt actually belongs to.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\local\access;

use context;
use context_course;
use context_module;
use context_system;

/**
 * Resolves the real course or module context of an attempt.
 *
 * Teacher feedback and statistics permissions used to be checked
 * against context_system::instance() or against the global $COURSE. Neither is
 * correct: an attempt belongs to a concrete adaptive quiz instance in a concrete
 * course, and during shortcode rendering, AJAX calls or central overviews the
 * global $COURSE is not necessarily that course.
 *
 * Every permission check that concerns a single attempt must therefore resolve
 * the context through this class, so that page requests, AJAX endpoints and
 * exports all judge access by exactly the same rule.
 *
 * The resolver degrades gracefully: if the module context cannot be determined
 * (deleted course module, unknown component) it falls back to the course context
 * and finally to the system context, so that a missing context never silently
 * grants access it should not.
 */
class context_resolver {
    /**
     * Request level cache of already resolved attempt contexts.
     *
     * Keyed by "component|attemptid". Avoids an N+1 pattern when a feedback page
     * renders many generators for the same attempt.
     *
     * @var array<string, context>
     */
    private static array $attemptcontextcache = [];

    /**
     * Returns the context an attempt belongs to.
     *
     * Resolution order: module context of the quiz instance, then the course
     * context, then the system context as last resort.
     *
     * @param int $attemptid The attempt id as used by the component (e.g. adaptivequiz attempt id).
     * @param string $component The component the attempt belongs to.
     * @return context
     */
    public static function for_attempt(int $attemptid, string $component = 'mod_adaptivequiz'): context {
        global $DB;

        if ($attemptid <= 0) {
            return context_system::instance();
        }

        $cachekey = $component . '|' . $attemptid;
        if (isset(self::$attemptcontextcache[$cachekey])) {
            return self::$attemptcontextcache[$cachekey];
        }

        // The identity of an external attempt is (component, attemptid), not the
        // attempt id alone: id 123 of one component is not id 123 of another. The
        // component was already part of the cache key but was not used for the
        // lookup, so a colliding id from another component could have decided the
        // context.
        //
        // IGNORE_MISSING rather than IGNORE_MULTIPLE: an ambiguous or missing record
        // must not silently pick one row. Without a record resolve_from_record()
        // falls back to the system context, where the capability check still applies
        // - fail closed rather than authorise against a foreign course.
        // The component is stored inconsistently: the runtime writes the plain
        // module name ('adaptivequiz', see return_course_and_instance_id()), while
        // callers of this method pass the frankenstyle form ('mod_adaptivequiz').
        // Comparing either spelling against the other never matches, so both are
        // accepted here. Normalising the stored data would need a migration and
        // would not make old rows match in the meantime.
        [$insql, $inparams] = $DB->get_in_or_equal(
            self::component_spellings($component),
            SQL_PARAMS_NAMED,
            'component'
        );
        $inparams['attemptid'] = $attemptid;

        $record = $DB->get_record_select(
            'local_catquiz_attempts',
            "attemptid = :attemptid AND component $insql",
            $inparams,
            'courseid, instanceid, component',
            IGNORE_MULTIPLE
        );

        $context = self::resolve_from_record($record);
        self::$attemptcontextcache[$cachekey] = $context;

        return $context;
    }

    /**
     * Builds the most specific context available for an attempt record.
     *
     * @param \stdClass|false $record Attempt record with courseid, instanceid and component.
     * @return context
     */
    /**
     * Returns the spellings a component may be stored under.
     *
     * Both the plain module name and the frankenstyle form are accepted, because the
     * two are used inconsistently across the plugin and existing rows carry the
     * former.
     *
     * @param string $component
     * @return string[]
     */
    private static function component_spellings(string $component): array {
        $plain = preg_replace('/^mod_/', '', $component);

        return array_values(array_unique([$component, $plain, 'mod_' . $plain]));
    }

    /**
     * Turns an attempt record into the most specific context available.
     *
     * Without a record the system context is returned: the capability check still
     * applies there, so an unresolvable attempt fails closed rather than being
     * authorised against a foreign course.
     *
     * @param \stdClass|false|null $record
     * @return context
     */
    private static function resolve_from_record($record): context {
        if (empty($record)) {
            return context_system::instance();
        }

        // Prefer the module context: it is the most specific one and the level at
        // which teachers are normally assigned.
        $instanceid = (int) ($record->instanceid ?? 0);
        $component = (string) ($record->component ?? '');
        $modulename = self::modulename_from_component($component);

        if ($instanceid > 0 && $modulename !== null) {
            $cm = get_coursemodule_from_instance($modulename, $instanceid, 0, false, IGNORE_MISSING);
            if ($cm) {
                $modulecontext = context_module::instance($cm->id, IGNORE_MISSING);
                if ($modulecontext) {
                    return $modulecontext;
                }
            }
        }

        return self::for_courseid((int) ($record->courseid ?? 0));
    }

    /**
     * Returns the course context, or the system context if the course is unknown.
     *
     * The site course (id 1) is deliberately treated as "no course": a check
     * against it would be equivalent to a system wide check and would reintroduce
     * exactly the over-broad access this class exists to prevent.
     *
     * @param int $courseid
     * @return context
     */
    public static function for_courseid(int $courseid): context {
        if ($courseid <= SITEID) {
            return context_system::instance();
        }

        $context = context_course::instance($courseid, IGNORE_MISSING);

        return $context ?: context_system::instance();
    }

    /**
     * Maps a component name to the module name used by the course module API.
     *
     * @param string $component E.g. "mod_adaptivequiz" or "adaptivequiz".
     * @return string|null Null if the component is not a module.
     */
    private static function modulename_from_component(string $component): ?string {
        if ($component === '') {
            return null;
        }

        if (strpos($component, 'mod_') === 0) {
            return substr($component, strlen('mod_'));
        }

        // Some callers store the bare module name.
        return $component;
    }

    /**
     * Returns the context of a quiz instance (the component id of a CATquiz test).
     *
     * @param int $instanceid The instance id, e.g. an adaptivequiz id.
     * @param string $component The component the instance belongs to.
     * @return context
     */
    public static function for_instance(int $instanceid, string $component = 'mod_adaptivequiz'): context {
        if ($instanceid <= 0) {
            return context_system::instance();
        }

        $modulename = self::modulename_from_component($component);
        if ($modulename === null) {
            return context_system::instance();
        }

        $cm = get_coursemodule_from_instance($modulename, $instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return context_system::instance();
        }

        return context_module::instance($cm->id, IGNORE_MISSING) ?: context_system::instance();
    }

    /**
     * Returns the context a statistics view refers to.
     *
     * Statistics can be scoped to a single test, to a course, or to neither (a
     * site wide overview). The most specific available scope wins, so that a
     * teacher looking at one quiz is judged in that quiz's context.
     *
     * @param int|null $courseid
     * @param int|null $testid The component id of the test, if the view is scoped to one.
     * @return context
     */
    public static function for_statistics(?int $courseid, ?int $testid): context {
        if (!empty($testid)) {
            $context = self::for_instance((int) $testid);
            if ($context->contextlevel === CONTEXT_MODULE) {
                return $context;
            }
        }

        return self::for_courseid((int) ($courseid ?? 0));
    }

    /**
     * Clears the request level cache.
     *
     * Only needed in tests, where several attempts are created within one request.
     *
     * @return void
     */
    public static function reset_cache(): void {
        self::$attemptcontextcache = [];
    }
}
