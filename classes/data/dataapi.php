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
use cache_helper;
use coding_exception;
use context_system;
use core_text;
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
    /** @var int Length of local_catquiz_catcontext.name and local_catquiz_catscales.name (issue #81). */
    public const CONTEXT_NAME_MAXLENGTH = 255;

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
     * Test seam (issue #81): when set, called with the context just stored for a scale, before
     * anything else is written. A test lets it throw to prove that a failed creation leaves neither
     * scale nor context behind. Only honoured in unit tests.
     *
     * @var callable|null
     */
    public static $aftercontextstoredhook = null;

    /**
     * The name of an automatically created context, cut to the length of the column (issue #81).
     *
     * The scale name is shortened, not the time: two contexts of the same long scale name stay
     * distinguishable by when they were made.
     *
     * @param string $identifier Language string with the placeholders scalename and usertime.
     * @param string $scalename
     * @return string
     */
    public static function auto_context_name(string $identifier, string $scalename): string {
        $timestring = userdate(time(), get_string('strftimedatetimeshort', 'core_langconfig'));
        $usertime = str_replace(' ', '', $timestring);
        $build = fn(string $name) => get_string($identifier, 'local_catquiz', [
            'scalename' => $name,
            'usertime' => $usertime,
        ]);
        $name = $build($scalename);
        $overflow = core_text::strlen($name) - self::CONTEXT_NAME_MAXLENGTH;
        if ($overflow > 0) {
            $keep = max(0, core_text::strlen($scalename) - $overflow);
            $name = $build(core_text::substr($scalename, 0, $keep));
        }
        return core_text::substr($name, 0, self::CONTEXT_NAME_MAXLENGTH);
    }

    /**
     * Stores a new automatic context for a scale, without touching the scale (issue #81).
     *
     * @param string $scalename
     * @param string $source Optional: provide details about the source
     * @return catcontext
     */
    private static function store_auto_context(string $scalename, string $source = ''): catcontext {
        $defaultcontext = catquiz::get_default_context_object();

        $data = new stdClass();
        $data->name = self::auto_context_name('uploadcontext', $scalename);
        $data->starttimestamp = $defaultcontext->starttimestamp;
        $data->endtimestamp = $defaultcontext->endtimestamp;
        $data->description = get_string('autocontextdescription', 'local_catquiz', $scalename);
        if ($source) {
            $data->description .= sprintf(' [%s]', $source);
        }

        $context = new catcontext($data);
        $context->save_or_update($data);

        if (defined('PHPUNIT_TEST') && PHPUNIT_TEST && is_callable(self::$aftercontextstoredhook)) {
            call_user_func(self::$aftercontextstoredhook, $context);
        }
        return $context;
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

        $context = self::store_auto_context($scalename, (string) $source);
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

        $data = new stdClass();
        $data->name = self::auto_context_name('updatedparamscontext', (string) $catscale->name);
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
     * Re-keys a scale array by catscale id, which is what the method below works on.
     *
     * @param array $catscales
     * @return array
     */
    private static function key_scales_by_id(array $catscales): array {
        $keyed = [];
        $rekeyed = false;

        foreach ($catscales as $key => $scale) {
            $id = is_object($scale) ? ($scale->id ?? null) : ($scale['id'] ?? null);

            if ($id === null) {
                // Nothing to key by - hand the array on as it came and let the caller's own
                // expectations apply.
                return $catscales;
            }

            $keyed[(int) $id] = $scale;

            $rekeyed = $rekeyed || (int) $key !== (int) $id;
        }

        // Reported once per call, not once per row: an array handed in list-keyed produces one
        // finding, not one per scale.
        if ($rekeyed) {
            debugging(
                'get_catscale_and_children() expects scales keyed by catscale id; '
                    . 'the array was re-keyed.',
                DEBUG_DEVELOPER
            );
        }

        return $keyed;
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
        $catcontext = null
    ) {

        $catscales = empty($catscales) ? self::get_all_catscales() : self::key_scales_by_id($catscales);
        $returnarray = [];

        $parentscales = array_filter($catscales, fn($a) => $a->id == $parentid);
        if (empty($parentscales)) {
            $parentscale = new stdClass();
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

        $isroot = intval($catscale->parentid) === 0;
        $contextmissing = !empty($catscale->contextid)
            && !$DB->record_exists('local_catquiz_catcontext', ['id' => $catscale->contextid]);
        if ($isroot && $contextmissing) {
            throw new moodle_exception('invalidcatcontext', 'local_catquiz');
        }
        if (!$isroot && empty($catscale->contextid)) {
            // Issue #81: a subscale has no context of its own - it uses the one of its root. The form sends 0.
            $catscale->contextid = null;
        }

        // Issue #81: the scale and its context are created together or not at all. The context is stored
        // first, so the scale is never written with context 0 - not even inside the transaction - and a
        // failure on either side rolls both back.
        $transaction = $DB->start_delegated_transaction();
        try {
            if ($isroot && empty($catscale->contextid)) {
                $catcontext = self::store_auto_context((string) $catscale->name);
                $catscale->contextid = (int) $catcontext->id;
            }
            $id = $DB->insert_record('local_catquiz_catscales', $catscale, true);
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            if ($isroot && isset($catcontext)) {
                $catscale->contextid = null;
            }
            // Rethrows.
            $transaction->rollback($e);
        }

        if (isset($catcontext)) {
            catcontext::store_context_as_singleton($catcontext, $id);
        }

        // Trigger catscale created event - after the commit, so no observer sees a scale that is rolled back.
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

        self::invalidate_catscale_caches();
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
        // Issue #52: the children are asked of the database, not of a cache that may not know them yet.
        if ($DB->record_exists('local_catquiz_catscales', ['parentid' => $catscaleid])) {
            return [
                'success' => false,
                'message' => get_string('cannotdeletescalewithchildren', 'local_catquiz'),
            ];
        }
        $result = $DB->delete_records('local_catquiz_catscales', ['id' => $catscaleid]);
        self::invalidate_catscale_caches();

        return $result ? ['success' => true] : [
            'success' => false,
            'message' => get_string('cannotdeletescale', 'local_catquiz'),
        ];
    }

    /**
     * Invalidates every cache that holds scales or what depends on them (issue #52).
     *
     * One place for create, update, delete and moving a scale: the scales themselves, the active scales
     * and item numbers of the tests, the item parameters, the item tables, and the item pools - since
     * issue #146 in a cache of their own, invalidated by the same events. The state of the running
     * attempts in the sessions is left alone: it holds no pool any more.
     *
     * @return void
     */
    public static function invalidate_catscale_caches(): void {
        cache_helper::purge_by_event('changesincatscales');
        cache_helper::purge_by_event('changesintestitems');
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

        $oldrecord = $DB->get_record('local_catquiz_catscales', ['id' => $catscale->id]);
        // Issue #52: a scale cannot become a child of itself or of one of its descendants - the tree would
        // become a cycle and every walk through it endless.
        if (isset($catscale->parentid) && (int) $catscale->parentid !== 0) {
            $descendants = catscale::get_subscale_ids((int) $catscale->id);
            $newparent = (int) $catscale->parentid;
            if ($newparent === (int) $catscale->id || in_array($newparent, array_map('intval', $descendants), true)) {
                throw new moodle_exception('cannotmovescaleintoitself', 'local_catquiz');
            }
        }

        // Issue #81: context 0 is no context. The form offers it as "New default context for scale": a root
        // scale gets a new context of its own, as on creation, instead of pointing to nothing (and falling
        // back to the global default context). A subscale stores none - it uses the context of its root.
        // A null context (a webservice update that does not carry one) is left as it is.
        $parentgiven = isset($catscale->parentid) && (int) $catscale->parentid >= 0;
        $isroot = $parentgiven ? (int) $catscale->parentid === 0 : (int) ($oldrecord->parentid ?? 0) === 0;
        $contextgiven = property_exists($catscale, 'contextid') && $catscale->contextid !== null;
        $newcontext = $contextgiven && empty($catscale->contextid) && $isroot;
        $nocontext = $contextgiven && empty($catscale->contextid) && !$isroot;
        if ($nocontext) {
            $catscale->contextid = null;
        }
        if (!empty($catscale->contextid) && !$DB->record_exists('local_catquiz_catcontext', ['id' => $catscale->contextid])) {
            throw new moodle_exception('invalidcatcontext', 'local_catquiz');
        }

        $transaction = $DB->start_delegated_transaction();
        try {
            if ($newcontext) {
                $name = (string) ($catscale->name ?? $oldrecord->name ?? '');
                $catscale->contextid = (int) self::create_new_context_for_scale($catscale->id, $name, '', true, false)->id;
            }
            // If the context of the scale was changed, we have to update the active item params.
            // Only migrate when a (non-null) new context was actually supplied: a webservice
            // update that does not carry a contextid must not trigger a context migration.
            if (!empty($catscale->contextid) && $oldrecord->contextid != $catscale->contextid) {
                $repo = new catquiz();
                $repo->create_items_in_new_context($catscale->contextid, $oldrecord->contextid);
            }

            $record = (object) get_object_vars($catscale);
            if (!$nocontext && property_exists($record, 'contextid') && $record->contextid === null) {
                // Not carried: the stored context stays.
                unset($record->contextid);
            }
            $result = $DB->update_record('local_catquiz_catscales', $record);
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            // Rethrows.
            $transaction->rollback($e);
        }

        $context = context_system::instance();

        // After the commit (issue #81): no observer sees a change that is rolled back.
        $event = catscale_updated::create([
            'objectid' => $catscale->id,
            'context' => $context,
            'userid' => $USER->id, // The user who did cancel.
            'other' => [
                'catscaleid' => $catscale->id,
            ],
        ]);
        $event->trigger();

        self::invalidate_catscale_caches();

        // Issue #86: the feedback ranges of the tests of a global scale end at its limits.
        $limitsgiven = property_exists($catscale, 'minscalevalue') || property_exists($catscale, 'maxscalevalue');
        if ($isroot && $limitsgiven && $oldrecord) {
            $new = $DB->get_record('local_catquiz_catscales', ['id' => $catscale->id], 'minscalevalue, maxscalevalue');
            if (
                (float) $new->minscalevalue !== (float) $oldrecord->minscalevalue
                || (float) $new->maxscalevalue !== (float) $oldrecord->maxscalevalue
            ) {
                \local_catquiz\local\feedback_limits::align_tests_of_scale((int) $catscale->id);
            }
        }
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
     * Check if label of catscale already exsists - must be unique
     *
     * @param string $label catscale label
     *
     * @return bool true if label already exists, false if not
     */
    public static function label_exists(string $label): bool {
        global $DB;
        return $DB->record_exists('local_catquiz_catscales', ['label' => $label]);
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
                        $params['tag' . $indexparam] = $tag->id;
                        $where .= "t.tagid";
                        $where .= $operator == 'OR' ? ' = ' : ' != ';
                        $where .= ":tag" . $indexparam;
                        if ($index + 1 < $tagscount) {
                            $where .= ' ' . $operator . ' ';
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
        $sql = "SELECT " . join(',', $fields) .
                " FROM {course} c
                JOIN {context} ctx ON c.id = ctx.instanceid
                AND ctx.contextlevel = :contextcourse
                WHERE " .
                $whereclause . "ORDER BY c.sortorder";
        $list = $DB->get_records_sql(
            $sql,
            ['contextcourse' => CONTEXT_COURSE] + $params
        );
        return $list;
    }
}
