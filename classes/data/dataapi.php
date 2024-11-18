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
 * The dataapi class.
 *
 * @package local_catquiz
 * @copyright 2024 Georg Maißer, <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\data;

use cache;
use coding_exception;
use context_system;
use InvalidArgumentException;
use dml_exception;
use ddl_exception;
use local_catquiz\catcontext;
use local_catquiz\catquiz;
use local_catquiz\catscale;
use local_catquiz\event\catscale_created;
use local_catquiz\event\catscale_updated;
use moodle_exception;
use stdClass;

/**
 * Get and store data from db.
 *
 * @package local_catquiz
 * @copyright 2024 Georg Maißer, <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dataapi {

    /**
     * Get all catscales either from cache or db
     *
     * @return array
     */
    public static function get_all_catscales(): array {
        global $DB;
        $cache = cache::make('local_catquiz', 'catscales');
        $allcatscales = $cache->get('allcatscales');
        if (!$allcatscales) {
            $allcatscales = [];
            $records = $DB->get_records('local_catquiz_catscales');
            if (!empty($records)) {
                foreach ($records as $record) {
                    $allcatscales[$record->id] = new catscale_structure((array) $record);
                }
            } else {
                $allcatscales = [];
            }
            $cache->set('allcatscales', $allcatscales);
        }
        return $allcatscales;
    }

    /**
     * Returns all catcontexts
     *
     * @return array
     *
     */
    public static function get_all_catcontexts(): array {
        global $DB;
        $cache = cache::make('local_catquiz', 'catcontexts');
        $allcatcontexts = $cache->get('allcatcontexts');
        if ($allcatcontexts) {
            return $allcatcontexts;
        } else {
            $allcatcontexts = [];
        }

        $records = $DB->get_records('local_catquiz_catcontext');
        if (!empty($records)) {
            foreach ($records as $record) {
                $allcatcontexts[$record->id] = new catcontext($record);
            }
        } else {
            $allcatcontexts = [];
        }
        $cache->set('allcatcontexts', $allcatcontexts);
        return $allcatcontexts;
    }

    /**
     * Creates a new context for a new scale.
     *
     * @param int $scaleid
     * @param string $scalename
     * @param string $source Optional: provide details about the source
     * @param bool $duplicate Optional: Duplicate an existing old context if it exists
     * @param bool $setdefault Optional: Set the new context as the default for the given scale
     *
     * @return catcontext
     */
    public static function create_new_context_for_scale(
        int $scaleid,
        string $scalename = "",
        $source = "",
        $duplicate = true,
        $setdefault = true
    ) {
        global $DB;

        $defaultcontext = catquiz::get_default_context_object();
        $timestring = userdate(time(), get_string('strftimedatetimeshort', 'core_langconfig'));
        $usertime = str_replace(' ', '', $timestring);

        $data = new stdClass;
        $data->name = get_string('uploadcontext', 'local_catquiz', [
            'scalename' => $scalename,
            'usertime' => $usertime,
            ]);
        $data->starttimestamp = $defaultcontext->starttimestamp;
        $data->endtimestamp = $defaultcontext->endtimestamp;
        $data->description = get_string('autocontextdescription', 'local_catquiz', $scalename);
        if ($source) {
            $data->description .= sprintf(' [%s]', $source);
        }

        $context = new catcontext($data);
        $context->save_or_update($data);
        catcontext::store_context_as_singleton($context, $scaleid);

        // If there is already an old context, we duplicate all the items.
        if ($duplicate && $oldcontextid = $DB->get_field('local_catquiz_catscales', 'contextid', ['id' => $scaleid])) {
            catscale::duplicate_testitemparams_for_scale_with_new_contextid($scaleid, $oldcontextid, $context->id);
        }

        // If we should not modify the scale, we can return.
        if (!$setdefault) {
            return $context;
        }

        // We set the new context as a default context in the catscale.
        $catscale = new stdClass();
        $catscale->id = $scaleid;
        $catscale->contextid = $context->id;
        $catscale->timemodified = time();
        self::update_catscale($catscale);

        return $context;
    }

    /**
     * Creates a new "updatedparams" context for the given scale
     *
     * @param stdClass $catscale
     * @return catcontext
     * @throws InvalidArgumentException
     * @throws dml_exception
     * @throws coding_exception
     * @throws ddl_exception
     */
    public static function create_new_context_for_updated_parameters(stdClass $catscale): catcontext {
        global $DB;
        $defaultcontext = catquiz::get_default_context_object();
        $timestring = userdate(time(), get_string('strftimedatetimeshort', 'core_langconfig'));
        $usertime = str_replace(' ', '', $timestring);

        $data = new stdClass();
        $data->name = get_string('updatedparamscontext', 'local_catquiz', [
            'scalename' => $catscale->name,
            'usertime' => $usertime,
            ]);
        $data->starttimestamp = $defaultcontext->starttimestamp;
        $data->endtimestamp = $defaultcontext->endtimestamp;
        $data->description = get_string(
            'updatedparamscontextdesc',
            'local_catquiz',
            $catscale->name
        );

        $context = new catcontext($data);
        $context->save_or_update($data);
        catcontext::store_context_as_singleton($context, $catscale->id);

        // Duplicate all the items from the previous context, so that we do not lose items that
        // can not be calculated due to missing responses.
        $oldcontextid = $DB->get_field('local_catquiz_catscales', 'contextid', ['id' => $catscale->id]);
        catscale::duplicate_testitemparams_for_scale_with_new_contextid($catscale->id, $oldcontextid, $context->id);

        return $context;
    }

    /**
     * We'll get an array of catscales where every catscale is followed by its children.
     *
     * @param integer $parentid
     * @param bool $getsubchildren
     * @param array $catscales
     * @param bool $returnasarray
     * @param ?int $catcontext
     * @return array
     */
    public static function get_catscale_and_children(
        $parentid = 0,
        bool $getsubchildren = false,
        $catscales = [],
        $returnasarray = false,
        $catcontext = null) {

        $catscales = empty($catscales) ? self::get_all_catscales() : $catscales;
        $returnarray = [];

        $parentscales = array_filter($catscales, fn($a) => $a->id == $parentid);
        if (empty($parentscales)) {
            $parentscale = new stdClass;
            $parentcontextid = $catcontext ?? catquiz::get_default_context_id();
            $parentscale->depth = 0;
        } else {
            $parentscale = reset($parentscales);

            if ($parentscale->parentid == 0) {
                $parentscale->depth = 0;
                $parentcontextid = $parentscale->contextid;
            }

            $returnarray[$parentscale->id] = $parentscale;
        }

        foreach ($catscales as $catscale) {

            $catscales[$catscale->id]->contextid = $parentcontextid ?? $catscales[$parentid]->contextid;
            // First check is, if the scale is already in our return array.
            // This can happen when we return children, run the function again and return ourselves.
            if (isset($returnarray[$catscale->id])) {
                continue;
            }

            if ($catscale->parentid == $parentid) {
                $catscale->depth = $parentscale->depth + 1;

                $returnarray[$catscale->id] = $catscale;

                if ($getsubchildren) {
                    // Now get all children.
                    $children = self::get_catscale_and_children($catscale->id, $getsubchildren, $catscales);

                    foreach ($children as $child) {
                        if (isset($returnarray[$child->id])) {
                            continue;
                        }
                        if (!empty($parentcontextid)) {
                            $child->contextid = $parentcontextid;
                        }
                        $returnarray[$child->id] = $child;
                    }
                }
            }
        }

        if (!$returnasarray) {
            return $returnarray;
        }

        $returndata = [];
        foreach ($returnarray as $catscalestructure) {
            $catscale = get_object_vars($catscalestructure);
            $returndata[] = $catscale;
        }
        return $returndata;
    }

    /**
     * Save a new catscale and invalidate cache. Checks if name is unique.
     *
     * @param catscale_structure $catscale
     * @return int 0 if name already exists
     */
    public static function create_catscale(catscale_structure $catscale): int {
        global $DB;

        $id = $DB->insert_record('local_catquiz_catscales', $catscale, true);

        // For a new parent catscale, create new auto-context.
        if (intval($catscale->parentid) === 0
            && $catscale->contextid == 0) {
            $catcontext = self::create_new_context_for_scale($id, $catscale->name);
        }

        // Trigger catscale created event.
        $event = catscale_created::create([
            'objectid' => $id,
            'context' => context_system::instance(),
            'other' => [
                'scalename' => $catscale->name,
                'catscaleid' => $id,
                'catscale' => $catscale,
                'catcontext' => $catcontext ?? null,
            ],
            ]);
        $event->trigger();

        // Invalidate cache. TODO: Instead of invalidating cache, add the item to the cache.
        $cache = cache::make('local_catquiz', 'catscales');
        $cache->delete('allcatscales');
        return $id;
    }

    /**
     * Delete a catscale and invalidate cache.
     *
     * @param int $catscaleid
     *
     * @return array
     *
     */
    public static function delete_catscale(int $catscaleid): array {
        global $DB;
        $allcatscales = self::get_all_catscales();
        $catscaleids = [];
        foreach ($allcatscales as $catscale) {
            $catscaleids[] = $catscale->parentid;
        }
        if (!in_array($catscaleid, $catscaleids)) {
            $result = $DB->delete_records('local_catquiz_catscales', ['id' => $catscaleid]);
        } else {
            // Throw new moodle_exception('', 'local_catquiz').
            // Cannot delete catscale which has children.
            $result = false;
            $message = get_string('cannotdeletescalewithchildren', 'local_catquiz');
        }

        // Invalidate cache. TODO: Instead of invalidating cache, delete the item from the cache.
        $cache = cache::make('local_catquiz', 'catscales');
        $cache->delete('allcatscales');
        $cache->delete($catscaleid);

        if ($result) {
            return [
                'success' => true,
            ];
        } else {
            return [
                'success' => false,
                'message' => $message,
            ];
        }
    }

    /**
     * Update a catscale and invalidate cache.
     *
     * @param catscale_structure|stdClass $catscale
     * @return bool
     */
    public static function update_catscale($catscale): bool {
        global $DB, $USER;
        if (!isset($catscale->id)) {
            throw new moodle_exception('noidset', 'local_catquiz');
        }

        $result = $DB->update_record('local_catquiz_catscales', $catscale);

        $context = context_system::instance();

        $event = catscale_updated::create([
            'objectid' => $catscale->id,
            'context' => $context,
            'userid' => $USER->id, // The user who did cancel.
            'other' => [
                'catscaleid' => $catscale->id,
            ],
        ]);
        $event->trigger();

        // Invalidate cache. TODO: Instead of invalidating cache, delete and add the item from the cache.
        $cache = cache::make('local_catquiz', 'catscales');
        $cache->delete('allcatscales');
        $cache->delete($catscale->id);
        return $result;
    }

    /**
     * Check if name of catscale already exsists - must be unique
     * @param string $name catscale name
     * @return bool true if name already exists, false if not
     */
    public static function name_exists(string $name): bool {
        global $DB;
        if ($DB->record_exists('local_catquiz_catscales', ['name' => $name])) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get catscale by ID
     * @param int $id catscale id
     * @return ?object
     */
    public static function get_catscale_by_id(int $id): ?object {
        global $DB;
        if ($DB->record_exists('local_catquiz_catscales', ['id' => $id])) {
            return $DB->get_record('local_catquiz_catscales', ['id' => $id], '*', MUST_EXIST);
        } else {
            return null;
        }
    }

    /**
     * Get catscales with specific parent scale.
     *
     * @param int $parentscaleid
     * @return array
     */
    public static function get_catscales_by_parent(int $parentscaleid): array {

        $allcatscales = self::get_all_catscales();

        $filteredscales = array_filter($allcatscales, fn($a) => ($a->parentid == $parentscaleid));
        return $filteredscales;
    }


    /**
     * Start a new attempt for a user.
     *
     * @return array
     */
    public static function get_courses_from_settings_tags() {
        $list = self::buildsqlquery();
        return $list;
    }

    /**
     * Build sql query with config filters.
     *
     * @return array
     */
    public static function buildsqlquery() {
        global $DB;
        $where = "c.id IN (SELECT t.itemid FROM {tag_instance} t";
        $configs = get_config('local_catquiz');

        if (empty($configs->cattags)) {
            return [];
        }
        // Search courses that are tagged with the specified tag.
        $configtags['OR'] = explode(',', str_replace(' ', '', $configs->cattags));

        $params = [];

        // Filter according to the tags.
        if ($configtags['OR'][0] != null) {
            $where .= " WHERE (";

            $indexparam = 0;
            foreach ($configtags as $operator => $tags) {
                if (!empty($tags[0])) {
                    $tagscount = count($tags);
                    foreach ($tags as $index => $tag) {
                        $tag = $DB->get_record('tag', ['id' => $tag], 'id, name');
                        if (!$tag) {
                            throw new moodle_exception('tagnotfoundindb', 'local_catquiz');
                        }
                        $params['tag'. $indexparam] = $tag->id;
                        $where .= "t.tagid";
                        $where .= $operator == 'OR' ? ' = ' : ' != ';
                        $where .= ":tag" . $indexparam;
                        if ($index + 1 < $tagscount) {
                            $where .= ' ' . $operator .' ';
                        } else {
                            $where .= ")";
                        };
                        $indexparam += 1;
                    }
                }
            }
            $where .= ")";
        }

        return self::get_course_records($where, $params);

    }

    /**
     * Build sql query with config filters.
     * @param str $whereclause
     * @param array $params
     * @return object
     */
    protected static function get_course_records($whereclause, $params) {
        global $DB;
        $fields = ['c.id', 'c.fullname', 'c.shortname'];
        $sql = "SELECT ". join(',', $fields).
                " FROM {course} c
                JOIN {context} ctx ON c.id = ctx.instanceid
                AND ctx.contextlevel = :contextcourse
                WHERE " .
                $whereclause."ORDER BY c.sortorder";
        $list = $DB->get_records_sql($sql,
            ['contextcourse' => CONTEXT_COURSE] + $params);
        return $list;
    }

}
