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

use cache;
use cache_helper;
use dml_exception;
use html_writer;
use local_catquiz\event\testitem_deleted;
use local_catquiz\event\testitemactivitystatus_updated;
use local_catquiz\event\testiteminscale_added;
use local_catquiz\event\testiteminscale_updated;
use local_catquiz\local\result;
use local_catquiz\local\status;
use local_catquiz\local\attempt_cache;
use context_system;
use Exception;
use local_catquiz\data\dataapi;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_model;
use local_catquiz\local\model\model_strategy;
use local_catquiz\local\monitoring\timeline;
use local_catquiz\output\catscales;
use moodle_exception;
use moodle_url;
use stdClass;
use local_catquiz\local\itemparam_validity;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * Class catquiz
 *
 * @author Georg Maißer
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catscale {
    /** @var string Session key of the generation of the question statistics of a person (issue #146). */
    private const STATS_GENERATION_KEY = 'userquestionstatsgeneration';

    /**
     *
     * @var stdClass $catscale.
     */
    public ?stdClass $catscale = null;

    /**
     *
     * @var ?int $catscaleid.
     */
    public ?int $catscaleid = null;

    /**
     * Catscale constructor.
     *
     * @param int $catscaleid
     */
    public function __construct(int $catscaleid) {

        $catscale = self::return_catscale_object($catscaleid);
        $this->catscale = $catscale;
        $this->catscaleid = $catscaleid;
    }

    /**
     * Static function to return catscale object.
     *
     * @param int $catscaleid
     * @return stdClass|null
     */
    public static function return_catscale_object(int $catscaleid) {
        global $DB;
        $cache = cache::make('local_catquiz', 'catscales');
        if ($catscale = $cache->get($catscaleid)) {
            return $catscale;
        }
        $catscale = $DB->get_record('local_catquiz_catscales', ['id' => $catscaleid]);
        if (! $catscale) {
            throw new \Exception(sprintf('Could not find a scale with ID %s', $catscaleid));
        }
        $cache->set($catscaleid, $catscale);
        return $catscale;
    }

    /**
     * Static function to return catscale object.
     *
     * @param string $catscalename
     * @return stdClass|null
     */
    public static function return_catscale_by_name(string $catscalename) {
        global $DB;
        $catscale = $DB->get_record('local_catquiz_catscales', ['name' => $catscalename]) ?: null;
        return $catscale;
    }

    /**
     * Static function to return array with key catscaleid and value link to catscale.
     *
     * @param int $componentid
     * @param string $componentname
     * @param bool $returnlink
     * @return array
     */
    public static function return_catscaleids_and_links_for_testitemitem(
        int $componentid,
        string $componentname = "question",
        bool $returnlink = false
    ) {
        global $DB;

        $sql = "SELECT catscaleid
        FROM {local_catquiz_items}
        WHERE componentid = :componentid
        AND componentname = :componentname";
        $catscaleids = $DB->get_fieldset_sql($sql, [
            'componentid' => $componentid,
            'componentname' => $componentname,
        ]);
        if ($returnlink) {
            $returndata = [];
            foreach ($catscaleids as $catscaleid) {
                $returndata[$catscaleid] = self::get_link_to_catscale($catscaleid);
            }
            return $returndata;
        } else {
            return $catscaleids;
        }
    }

    /**
     * Returns the contextid associated with a catscale.
     *
     * If a catscale does not have a contextid, it returns the contextid of the
     * ancestor scale that has one.
     *
     * @param int $catscaleid
     * @return int|false
     */
    public static function get_context_id(int $catscaleid): int {
        try {
            $catscale = self::return_catscale_object($catscaleid);
            if ($catscale->contextid) {
                return $catscale->contextid;
            }
            if ($catscale->parentid === 0) {
                return catquiz::get_default_context_id();
            }
            return self::get_context_id($catscale->parentid);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Whether a stored bound is set: a number, 0 included.
     *
     * @param mixed $bound
     * @return bool
     */
    private static function is_set($bound): bool {
        return is_numeric($bound);
    }

    /**
     * Returns the minscalevalue and maxscalevalue of ability associated with a catscale.
     *
     * This returns the values of the main ancestor scale.
     *
     * @param int $catscaleid
     * @return array
     */
    public function get_ability_range(int $catscaleid = 0): array {
        if (empty($catscaleid)) {
            $catscaleid = $this->catscaleid;
        }
        $catscale = self::return_catscale_object($catscaleid);
        // A bound of 0 is a bound (mod_adaptivequiz issue #14): the check used to be a truthiness
        // test, which sent a numeric 0 to the default range. Only bounds that are not set fall
        // back. Bounds that are set but form no range (upper <= lower) are returned as they are:
        // replacing them with the default would hide a configuration error that has to stop
        // grading, not be graded on a range nobody chose.
        if ($catscale->parentid == 0 && self::is_set($catscale->minscalevalue) && self::is_set($catscale->maxscalevalue)) {
            return [
                'minscalevalue' => $catscale->minscalevalue,
                'maxscalevalue' => $catscale->maxscalevalue,
            ];
        }
        if ($catscale->parentid == 0) {
            return [
                'minscalevalue' => LOCAL_CATQUIZ_PERSONABILITY_LOWER_LIMIT,
                'maxscalevalue' => LOCAL_CATQUIZ_PERSONABILITY_UPPER_LIMIT,
            ];
        }
        return $this->get_ability_range($catscale->parentid);
    }

    /**
     * Adds or updates attribution of question to scale.
     *
     * @param int $catscaleid
     * @param int $testitemid
     * @param int $status // Just to check if status is changed, we make a nonsense default.
     * @param string $component
     * @param bool $overridecatscale // When true, an item already assigned to a catscale of the same tree will be updated.
     *
     * @return mixed
     *
     */
    public static function add_or_update_testitem_to_scale(
        int $catscaleid,
        int $testitemid,
        int $status = LOCAL_CATQUIZ_TESTITEM_STATUS_UNDEFINED,
        string $component = 'question',
        bool $overridecatscale = false
    ) {

        global $DB;
        $context = context_system::instance();

        $searchparams = [
            'componentid' => $testitemid,
            'componentname' => $component,
            'catscaleid' => $catscaleid,
        ];
        if ($overridecatscale) {
            unset($searchparams['catscaleid']);
        }

        // Check if status is changed.
        $statuschanged = false;
        if ($status == LOCAL_CATQUIZ_TESTITEM_STATUS_UNDEFINED) {
            $status = LOCAL_CATQUIZ_TESTITEM_STATUS_ACTIVE;
        } else {
            $statuschanged = true;
        }

        $data = $searchparams;
        $data['status'] = $status;

        // We need the default context for the events that will be triggered.
        $catcontext = catquiz::get_default_context_id();

        // The item belongs to the context of its scale. Without, the item of the scale was not found in it: the
        // page of the item failed, and a context change of the scale passed over it (issues #82, #148).
        $scalecontextid = (int) self::get_context_id($catscaleid);

        if ($record = $DB->get_record('local_catquiz_items', $searchparams)) {
            // Right now, there is nothing to do, as we don't have more data.
            $id = $record->id;
            $data['id'] = $id;
            if ($overridecatscale) {
                $data['catscaleid'] = $catscaleid;
            }
            if (empty($record->contextid) && $scalecontextid) {
                $data['contextid'] = $scalecontextid;
            }
            $DB->update_record('local_catquiz_items', (object)$data);

            if ($statuschanged) {
                // Trigger status changed event.
                $event = testitemactivitystatus_updated::create([
                    'objectid' => $testitemid,
                    'context' => $context,
                    'other' => [
                        'activitystatus' => $status,
                        'testitemid' => $testitemid,
                        'catscaleid' => $catscaleid,
                        'context' => $catcontext,
                        'component' => $component,
                    ],
                    ]);
                $event->trigger();
            }

            // Trigger general testitem updated event.
            $event = testiteminscale_updated::create([
                'objectid' => $testitemid,
                'context' => $context,
                'other' => [
                    'catscaleid' => $catscaleid,
                    'testitemid' => $testitemid,
                    'context' => $catcontext,
                    'component' => $component,
                ],
                ]);
            $event->trigger();
        } else {
            // We won't allow an item to be assigned to both a scale and its sub- or parent-scale.
            if (
                self::is_assigned_to_parent_scale($catscaleid, $testitemid)
                || self::is_assigned_to_subscale($catscaleid, $testitemid)
            ) {
                    return result::err(status::ERROR_TESTITEM_ALREADY_IN_RELATED_SCALE, $testitemid);
            }

            $now = time();
            $data['timemodified'] = $now;
            $data['timecreated'] = $now;
            if ($scalecontextid) {
                $data['contextid'] = $scalecontextid;
            }
            $id = $DB->insert_record('local_catquiz_items', (object)$data);

            // Trigger event.
            $event = testiteminscale_added::create([
                'objectid' => $testitemid,
                'context' => $context,
                'other' => [
                    'testitemid' => $testitemid,
                    'catscaleid' => $catscaleid,
                    'context' => $catcontext,
                    'component' => $component,
                ],
                ]);
            $event->trigger();
        }
        cache_helper::purge_by_event('changesintestitems');
        return result::ok($id);
    }

    /**
     * Check is assigned to parent scale.
     *
     * @param mixed $catscaleid
     * @param int $testitemid
     *
     * @return bool
     *
     */
    public static function is_assigned_to_parent_scale($catscaleid, int $testitemid): bool {
        $ancestorids = self::get_ancestors($catscaleid);
        if (empty($ancestorids)) {
            return false;
        }

        global $DB;
        [$insql, $inparams] = $DB->get_in_or_equal($ancestorids, SQL_PARAMS_NAMED, 'ctx');
        $records = $DB->get_records_sql(
            <<<SQL
                SELECT *
                FROM {local_catquiz_items}
                WHERE componentid = :testitemid AND catscaleid $insql
            SQL,
            array_merge($inparams, ['testitemid' => $testitemid])
        );
        return !empty($records);
    }

    /**
     * Check is assigned to subscale.
     *
     * @param mixed $catscaleid
     * @param int $testitemid
     *
     * @return bool
     *
     */
    public static function is_assigned_to_subscale($catscaleid, int $testitemid): bool {
        $childids = self::get_subscale_ids($catscaleid);
        if (empty($childids)) {
            return false;
        }

        global $DB;
        [$insql, $inparams] = $DB->get_in_or_equal($childids, SQL_PARAMS_NAMED, 'ctx');
        $records = $DB->get_records_sql(
            <<<SQL
                SELECT *
                FROM {local_catquiz_items}
                WHERE componentid = :testitemid AND catscaleid $insql
            SQL,
            array_merge($inparams, ['testitemid' => $testitemid])
        );
        return !empty($records);
    }

    /**
     * Removes attribution of question to scale.
     *
     * @param int $catscaleid
     * @param int $testidemid
     * @param string $component
     *
     * @return void
     *
     */
    public static function remove_testitem_from_scale(int $catscaleid, int $testidemid, string $component = 'question') {

        global $DB;

        $data = [
            'componentid' => $testidemid,
            'componentname' => $component,
            'catscaleid' => $catscaleid,
        ];

        $DB->delete_records('local_catquiz_items', $data);
        unset($data['catscaleid']);
        $DB->delete_records('local_catquiz_itemparams', $data);

        // Trigger testitem deleted event.
        $event = testitem_deleted::create([
            'objectid' => $testidemid,
            'context' => context_system::instance(),
            'other' => [
                'componentid' => $testidemid,
                'catscaleid' => $catscaleid,
                'componentname' => $component,
            ],
        ]);
        $event->trigger();

        cache_helper::purge_by_event('changesintestitems');
    }

    /**
     * Returns the testitems for this scale and all the subscales.
     *
     * @param int $contextid
     * @param bool $includesubscales
     * @param string|null $orderby If given, sort items by that field
     * @param array $selectedsubscales Ids of subscales to be treated
     *
     * @return array
     *
     */
    public function get_testitems(
        int $contextid,
        bool $includesubscales = false,
        ?string $orderby = null,
        array $selectedsubscales = []
    ): array {

        if (empty($this->catscale)) {
            return [];
        }

        /* Issue #146: the pool is the same for everybody - items, parameters, usage in the CAT context - and
           is kept in an application cache of its own, invalidated by the events of items, parameters and
           scales. What depends on the person - when they last saw a question, how often - is added from a
           small table per person in the session ({@see self::user_question_stats()}). Before, the hydrated
           pool sat in the session cache, was written back whole for every question, and every attempt
           start purged it - with the progress and feedback of any other open attempt of the session. */
        $poolkey = self::pool_key($contextid, $includesubscales, (int) $this->catscale->id, $selectedsubscales);
        $poolcache = cache::make('local_catquiz', 'runtimepool');
        // Issue #136: whether the pool came from the cache, and what a miss costs.
        timeline::start('pool:cache_lookup');
        $testitems = $poolcache->get($poolkey);
        timeline::stop('pool:cache_lookup', [
            'pool_cache' => $testitems !== false ? 'hit' : 'miss',
            'pool_items' => $testitems !== false ? count($testitems) : 0,
        ]);

        if ($testitems === false) {
            // Issue #147: the cached pool is canonical - by question id - whatever order the first caller asked
            // for; the order is applied below, on every return. Before, the key held no order, and the order of
            // whoever filled the cache was the order of everybody after.
            $testitems = $this->load_pool($contextid, $includesubscales, null, $selectedsubscales);
            ksort($testitems, SORT_NUMERIC);
            timeline::span('pool:cache_write', fn() => $poolcache->set($poolkey, $testitems), ['pool_items' => count($testitems)]);
        }
        $testitems = self::order_pool($testitems, $orderby);

        global $USER;
        $stats = self::user_question_stats((int) $USER->id);
        foreach ($testitems as $id => $item) {
            $seen = $stats[$id] ?? null;
            $item->userid = $seen ? (int) $USER->id : null;
            $item->userattempts = $seen ? $seen[0] : null;
            $item->userlastattempttime = $seen ? $seen[1] : null;
        }
        return $testitems;
    }

    /**
     * A pool in the order asked for (issue #147), the same with a cold and a warm cache.
     *
     * Without an order: by question id. With one: by that field ascending - numbers as numbers -, rows without
     * a value (a pilot without difficulty) last, equal values by question id. The SQL order it replaces put
     * NULL last on PostgreSQL and first on MariaDB, and left ties to the database.
     *
     * @param array $testitems Keyed by question id.
     * @param string|null $orderby A field of the rows, e.g. difficulty.
     * @return array Keyed by question id.
     */
    public static function order_pool(array $testitems, ?string $orderby): array {
        if ($orderby === null || $orderby === '') {
            ksort($testitems, SORT_NUMERIC);
            return $testitems;
        }
        $field = trim(preg_replace('/\s+(ASC|DESC)$/i', '', $orderby));
        uksort($testitems, function ($a, $b) use ($testitems, $field): int {
            $va = $testitems[$a]->$field ?? null;
            $vb = $testitems[$b]->$field ?? null;
            $nulla = $va === null || $va === '';
            $nullb = $vb === null || $vb === '';
            if ($nulla !== $nullb) {
                return $nulla ? 1 : -1;
            }
            if (!$nulla) {
                $cmp = is_numeric($va) && is_numeric($vb) ? ((float) $va <=> (float) $vb) : strcmp((string) $va, (string) $vb);
                if ($cmp !== 0) {
                    return $cmp;
                }
            }
            return (int) $a <=> (int) $b;
        });
        return $testitems;
    }

    /**
     * The key of a runtime pool (issue #146): CAT context, scale, whether with subscales, which subscales.
     *
     * Nothing of a person or a session is part of the pool - it is the same for everybody who plays it.
     *
     * @param int $contextid
     * @param bool $includesubscales
     * @param int $catscaleid
     * @param array $selectedsubscales
     * @return string
     */
    public static function pool_key(int $contextid, bool $includesubscales, int $catscaleid, array $selectedsubscales): string {
        $selected = array_map('intval', $selectedsubscales);
        sort($selected);
        return sprintf(
            'pool_%d_%d_%d_%s',
            $contextid,
            $includesubscales ? 1 : 0,
            $catscaleid,
            $selected ? hash('crc32b', implode('_', $selected)) : 'all'
        );
    }

    /**
     * Reads a runtime pool from the database, without the statistics of a person.
     *
     * @param int $contextid
     * @param bool $includesubscales
     * @param string|null $orderby
     * @param array $selectedsubscales
     * @return array Rows keyed by question id.
     */
    private function load_pool(int $contextid, bool $includesubscales, ?string $orderby, array $selectedsubscales): array {
        global $DB;
        $scaleids = [$this->catscale->id];
        if ($includesubscales) {
            // Subscales ids for checked subscale boxes.
            $subscaleids = !empty($selectedsubscales) ? $selectedsubscales : self::get_subscale_ids($this->catscale->id);
            $scaleids = array_merge($scaleids, $subscaleids);
        }

        // Issue #26: the runtime pool is cached per scale and context, and the cache
        // holds the hydrated rows. The manager interface needs the question, category
        // and scale names; the selection does not - it works on ids, scale ids and
        // item parameters. Leaving them out of this path takes 36 % off a payload
        // that measured 206 MB at 250.000 items.
        // Issue #146: without a person (user id 0) - their statistics are added per request.
        [$select, $from, $where, , $params] = catquiz::return_sql_for_catscalequestions(
            $scaleids,
            $contextid,
            [],
            0,
            $orderby,
            null,
            true,
            // Issue #82: hidden and draft questions and items deactivated in their scale are not played.
            true
        );

        $sql = "SELECT $select FROM $from WHERE $where";

        // Issue #104: one entry per question, by a fixed rule. A question can be in two sibling scales;
        // the query then has a row per scale, both with the question id first, and get_records_sql()
        // kept one of them at random (and reported the duplicate). The selection plays a question once,
        // so it gets one row - the one of the scale the question was assigned to first, the lowest item id.
        $pool = timeline::span(
            'pool:query',
            fn() => self::one_row_per_question($DB->get_recordset_sql($sql, $params)),
            ['pool_scales' => count($scaleids)]
        );
        // Issue #157: items of the context whose reference to active parameters does not resolve were left out by the
        // query - they are neither pilots nor calibrated. Say so, once per pool built.
        \local_catquiz\local\integrity\item_param_integrity::report($contextid, $scaleids);
        // Issue #153: an item whose model is not installed cannot be selected or scored; the manager says why
        // (item_availability()).
        return model_strategy::rows_of_installed_models($pool);
    }

    /**
     * When a person last saw each question, and how often (issue #146).
     *
     * Kept in the session per person, in one small table for all pools: question id => [times seen, last
     * time]. Read from the database once per generation - a new attempt starts a new one - and kept up to
     * date with every question handed out ({@see self::update_testitem()}).
     *
     * @param int $userid
     * @return array
     */
    public static function user_question_stats(int $userid): array {
        $cache = attempt_cache::cache();
        $key = self::user_stats_key($userid);
        $stats = $cache->get($key);
        if ($stats === false) {
            $stats = timeline::span('pool:user_stats', fn() => catquiz::get_user_question_stats($userid));
            $cache->set($key, $stats);
        }
        return $stats;
    }

    /**
     * The session key of the question statistics of a person, in the current generation.
     *
     * @param int $userid
     * @return string
     */
    private static function user_stats_key(int $userid): string {
        return sprintf('userquestionstats_%d_%d', $userid, (int) attempt_cache::cache()->get(self::STATS_GENERATION_KEY));
    }

    /**
     * A new generation of the question statistics of a person: read anew from the database at their next use.
     *
     * At the start of an attempt - what other attempts showed since, also on other devices, is taken in.
     * The other open attempts of the session lose nothing: their state is not in these statistics.
     *
     * @param int $userid
     */
    public static function renew_user_question_stats(int $userid): void {
        $cache = attempt_cache::cache();
        $cache->delete(self::user_stats_key($userid));
        $cache->set(self::STATS_GENERATION_KEY, (int) $cache->get(self::STATS_GENERATION_KEY) + 1);
    }

    /** @var string Issue #82: the question is played in this scale. */
    public const AVAILABILITY_ACTIVE = 'active';

    /** @var string Issue #82: deactivated in this scale only; other scales may play it. */
    public const AVAILABILITY_SCALEINACTIVE = 'scaleinactive';

    /** @var string Issue #82: hidden in the question bank - no test plays it, whatever the scale says. */
    public const AVAILABILITY_GLOBALHIDDEN = 'globalhidden';

    /** @var string Issue #82: a draft in the question bank - no test plays it until it is ready. */
    public const AVAILABILITY_GLOBALDRAFT = 'globaldraft';

    /** @var string Issue #153: the model of its parameters is not installed - no test can select or score it. */
    public const AVAILABILITY_MODELNOTINSTALLED = 'modelnotinstalled';

    /**
     * Whether a test may play a question in a scale, from both levels that hide it (issue #82).
     *
     * The question bank comes first: a hidden or draft newest version keeps the question out of every test,
     * and no scale can bring it back. The scale can only narrow further.
     *
     * Issue #153: an item whose parameters name a model that is not installed is left out of the runtime pool; the
     * manager reads why here.
     *
     * @param string|null $questionstatus Status of the newest version in the question bank.
     * @param int|string|null $testitemstatus local_catquiz_items.status in the scale.
     * @param string|null $model The model of the active parameters of the item, if it has any.
     * @return string One of the AVAILABILITY_* constants.
     */
    public static function item_availability(?string $questionstatus, $testitemstatus, ?string $model = null): string {
        if ($questionstatus === \core_question\local\bank\question_version_status::QUESTION_STATUS_DRAFT) {
            return self::AVAILABILITY_GLOBALDRAFT;
        }
        if ($questionstatus !== \core_question\local\bank\question_version_status::QUESTION_STATUS_READY) {
            return self::AVAILABILITY_GLOBALHIDDEN;
        }
        if ($model !== null && $model !== '' && !array_key_exists($model, model_strategy::get_installed_models())) {
            return self::AVAILABILITY_MODELNOTINSTALLED;
        }
        if ((int) $testitemstatus === LOCAL_CATQUIZ_TESTITEM_STATUS_INACTIVE) {
            return self::AVAILABILITY_SCALEINACTIVE;
        }
        return self::AVAILABILITY_ACTIVE;
    }

    /**
     * The questions of a list that no test may hand out now (issue #82).
     *
     * A question is out when the newest version of its bank entry is not ready - hidden or a draft - or when
     * it is gone. The runtime pool is cached, and the question bank can hide a question without any event:
     * question_delete_question() on a question in use only sets the status of its versions. So the selection
     * asks this for the question it is about to hand out - one query - and drops what it names.
     *
     * @param int[] $questionids
     * @return int[] The ids that are not available.
     */
    public static function unavailable_question_ids(array $questionids): array {
        global $DB;
        $ids = array_values(array_unique(array_filter(array_map('intval', $questionids))));
        $unavailable = [];
        foreach (array_chunk($ids, 1000) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'availq');
            $params['availready'] = \core_question\local\bank\question_version_status::QUESTION_STATUS_READY;
            $ready = $DB->get_fieldset_sql(
                "SELECT qv.questionid
                   FROM {question_versions} qv
                   JOIN {question_versions} qvl ON qvl.questionbankentryid = qv.questionbankentryid
                    AND qvl.version = (SELECT MAX(qvm.version) FROM {question_versions} qvm
                                        WHERE qvm.questionbankentryid = qv.questionbankentryid)
                  WHERE qv.questionid $insql AND qvl.status = :availready",
                $params
            );
            $unavailable = array_merge($unavailable, array_diff($chunk, array_map('intval', $ready)));
        }
        return array_values($unavailable);
    }

    /**
     * The rows of the runtime pool, one per question: the row with the active parameters of the CAT context
     * (local_catquiz_items.activeparamid); without such a row, the row without parameters.
     *
     * The order of the rows is kept - the position of a question is that of its first row.
     *
     * Issue #150 (re-audit 28.10.2026): the pool of a context also holds the rows of items of other contexts that
     * have no parameters (piloting, catquiz::return_sql_for_catscalequestions()). An item of an earlier context,
     * created first, has the lower id: its row without parameters won over the row with the parameters of this
     * context, and the question counted as a pilot - in the selection and in the test information. A row with
     * parameters always has them in this context (lcipcontextid; the query admits no other), so it is taken. Only
     * between rows alike - a question in two sibling scales, both calibrated or both without parameters - the lowest
     * item id decides, so that the choice is fixed (issue #104). The selection excludes a question played once by
     * its question id (removeplayedquestions), so with every item it has.
     *
     * @param \moodle_recordset $rows Rows with the question id first and the item id as itemid.
     * @return array Rows keyed by question id.
     */
    public static function one_row_per_question(\moodle_recordset $rows): array {
        $items = [];
        $calibrated = fn($row): bool => isset($row->lcipcontextid) && $row->lcipcontextid !== '';
        foreach ($rows as $row) {
            $id = $row->id;
            if (
                !isset($items[$id])
                || ($calibrated($row) && !$calibrated($items[$id]))
                || ($calibrated($row) === $calibrated($items[$id]) && (int) $row->itemid < (int) $items[$id]->itemid)
            ) {
                $items[$id] = $row;
            }
        }
        $rows->close();
        return $items;
    }

    /**
     * Records that a question was handed out to a person: in their question statistics (issue #146).
     *
     * The pool itself is the same for everybody and is not changed. The parameters of the pool are no
     * longer needed and kept for the callers.
     *
     * @param int $contextid
     * @param mixed $question The question, with the time it was handed out as userlastattempttime.
     * @param mixed $catscaleid
     * @param bool $includesubscales
     * @param array $selectedsubscales
     *
     * @return void
     *
     */
    public static function update_testitem(
        int $contextid,
        $question,
        $catscaleid,
        $includesubscales = false,
        array $selectedsubscales = []
    ) {
        global $USER;
        /* Issue #146: only what changes - the person has seen the question now. Before, the whole hydrated
           pool was read and written back to the session for every question handed out. */
        $userid = (int) ($question->userid ?? 0) ?: (int) $USER->id;
        $stats = self::user_question_stats($userid);
        $seen = $stats[(int) $question->id] ?? [0, 0];
        $stats[(int) $question->id] = [
            (int) $seen[0] + 1,
            (int) ($question->userlastattempttime ?? time()),
        ];
        attempt_cache::cache()->set(self::user_stats_key($userid), $stats);
    }

    /**
     * Get all subscale IDs
     *
     * @param int|null $catscaleid
     *
     * @return array
     *
     */
    public static function get_subscale_ids(?int $catscaleid = null): array {
        $all = dataapi::get_all_catscales();
        return self::add_subscales($catscaleid, $all);
    }

    /**
     * Add subscales.
     *
     * @param ?int $parentid
     * @param mixed $all
     *
     * @return array|null
     *
     */
    private static function add_subscales(?int $parentid, $all): ?array {
        $result = [];
        if ($parentid === null) {
            return $result;
        }

        foreach ($all as $scale) {
            if (intval($scale->parentid) === $parentid) {
                $result = [...$result, $scale->id, ...self::add_subscales(intval($scale->id), $all)];
            }
        }
        return $result;
    }

    /** Returns an array with the ids of only the next subscale of the given scale.
     *
     * @param array $catscaleids
     * @return array
     *
     */
    public static function get_next_level_subscales_ids_from_parent(array $catscaleids) {
        global $DB;
        $select = "*";
        $from = "{local_catquiz_catscales}";

        $params = [];

        [$insql, $inparams] = $DB->get_in_or_equal($catscaleids, SQL_PARAMS_NAMED);

        $where = ' parentid ' . $insql;
        $params = array_merge($params, $inparams);
        $sql = "SELECT $select FROM $from WHERE $where";
        $subscaleids = $DB->get_records_sql($sql, $params);

        return $subscaleids;
    }

    /**
     * Get IDs of parentscales.
     * The highest parent being the last value in array.
     * With return names, returnvalue is either only the scaleids (1),
     * scalenames (2) or both (3).
     *
     * @param int $catscaleid
     * @param int $returnnames
     *
     * @return array
     *
     */
    public static function get_ancestors(int $catscaleid, int $returnnames = 1) {
        $all = dataapi::get_all_catscales();
        $ancestorsintarray = self::add_parentscales($catscaleid, $all);
        switch ($returnnames) {
            case 1:
                return $ancestorsintarray;
            case 2:
                return array_map(fn($a) => $all[$a]->name, $ancestorsintarray);
            case 3:
                $parentscales = array_filter($ancestorsintarray, fn($a) => $all[$a]->parentid == 0);
                return [
                    'catscalenames' => array_map(fn($a) => $all[$a]->name, $ancestorsintarray),
                    'catscaleids' => $ancestorsintarray,
                    'mainscale' => reset($parentscales),
                ];
            default:
                return $ancestorsintarray;
        }
    }

    /**
     * Add parentscales.
     *
     * @param int $scaleid
     * @param array $all
     *
     * @return array|null
     *
     */
    private static function add_parentscales(int $scaleid, array $all): ?array {
        foreach ($all as $scale) {
            if (intval($scale->id) === $scaleid && intval($scale->parentid) !== 0) {
                return [$scale->parentid, ...self::add_parentscales(intval($scale->parentid), $all)];
            }
        }
        return [];
    }

    /**
     * Get HTML link to scale detail view.
     *
     * @param int $catscaleid
     * @param string $url
     *
     * @return string
     */
    public static function get_link_to_catscale(int $catscaleid, $url = '/local/catquiz/manage_catscales.php') {

        try {
            $catscale = self::return_catscale_object($catscaleid);
            if (!empty($catscale->name)) {
                $catscalename = $catscale->name;

                // Tab as a parameter, not as a fragment - see above.
                $url = new moodle_url($url, [
                    'tab' => 'catscales',
                    'scaleid' => $catscaleid,
                ]);
                $linktoscale = html_writer::link($url, $catscalename);

                return $linktoscale;
            }
        } catch (\Exception $e) {
            return get_string("deletedcatscale", "local_catquiz");
        }
    }

    /**
     * Get HTML link to testitem detail view.
     *
     * @param int $testitemid
     * @param int $catscaleid
     * @param int $context
     * @param string $component
     * @param string $linktext
     * @param string $url
     *
     * @return string
     */
    public static function get_link_to_testitem(
        int $testitemid,
        int $catscaleid,
        int $context,
        string $component,
        string $linktext = "",
        $url = '/local/catquiz/manage_catscales.php'
    ) {

        if (empty($linktext)) {
            $linktext = get_string('testitem', 'local_catquiz', $testitemid);
        }
        $url = new moodle_url($url);
        $url->param('id', $testitemid);
        $url->param('contextid', $context);
        $url->param('scaleid', $catscaleid);
        $url->param('component', $component);
        // Issue #32: the tab is a request parameter since #29; the anchor landed on the default tab.
        $url->param('tab', 'questions');

        $linktoscale = html_writer::link($url, $linktext);

        return $linktoscale;
    }

    /**
     * This function duplicates all the records from the old context for the new context.
     *
     * Following steps are performed for each existing local_catquiz_itemparams item parameter:
     * 1. Duplicate the item parameter
     * 2. Duplicate the local_catquiz_items item that pointed to the previous
     *    item parameter and make it point to the new item parameter via its
     *    `activeparamid` field.
     * 3. Update the duplicated item parameter so that its `itemid` field points to the duplicated item.
     *
     * @param mixed $scaleid
     * @param mixed $oldcontextid
     * @param mixed $contextid
     * @return void
     * @throws dml_exception
     */
    public static function duplicate_testitemparams_for_scale_with_new_contextid($scaleid, $oldcontextid, $contextid) {

        global $DB;

        // Make sure we don't do unnecessary work.
        if ($oldcontextid == $contextid) {
            return;
        }

        $scaleids = self::get_subscale_ids($scaleid);
        $scaleids[] = $scaleid;
        [$inorequal, $params] = $DB->get_in_or_equal($scaleids, SQL_PARAMS_NAMED);

        $sql = "SELECT lcip.*
                FROM {local_catquiz_items} lci
                JOIN {local_catquiz_itemparams} lcip
                ON (lci.id = lcip.itemid AND lci.contextid = lcip.contextid)
                WHERE lci.catscaleid $inorequal
                AND lcip.contextid=:contextid";
        $params['catscaleid'] = $scaleid;
        $params['contextid'] = $oldcontextid;

        $itemparams = $DB->get_records_sql($sql, $params);

        // This holds a mapping of old itemparam to new itemparam, where the key is the old ID and the value the new ID.
        $mapping = [];
        $saved = [];
        foreach ($itemparams as $record) {
            $record->contextid = $contextid;
            $oldid = $record->id;
            unset($record->id);
            // Keep the persisted usability flag in step with the rule.
            itemparam_validity::stamp($record);
            $newid = $DB->insert_record('local_catquiz_itemparams', $record);
            $record->id = $newid;
            $saved[$newid] = $record;
            $mapping[$oldid] = $newid;
        }

        if (!$mapping) {
            return;
        }

        // For each new record that was pointed to as active param by a local_catquiz_items item, we duplicate that item
        // for the new context and update the active param to point to the duplicated item param.
        [$insql, $inparams] = $DB->get_in_or_equal(
            array_keys($mapping),
            SQL_PARAMS_NAMED,
            'inoldparams'
        );
        $sql = <<<SQL
            SELECT *
            FROM {local_catquiz_items} lci
            WHERE lci.contextid = :contextid
            AND lci.activeparamid $insql
        SQL;

        $newitems = [];
        $originalitems = $DB->get_records_sql(
            $sql,
            array_merge(['contextid' => $oldcontextid], $inparams)
        );
        foreach ($originalitems as $i) {
            $i->contextid = $contextid;
            $i->activeparamid = $mapping[$i->activeparamid];
            unset($i->id);
            $newitemid = $DB->insert_record('local_catquiz_items', $i);
            $newitems[$i->activeparamid] = $newitemid;
        }

        // Now update the item params to point to the duplicated items.
        foreach ($newitems as $ipid => $itemid) {
            $itemparam = $saved[$ipid];
            $itemparam->itemid = $itemid;
            itemparam_validity::stamp($itemparam);
            $DB->update_record('local_catquiz_itemparams', $itemparam, true);
        }
    }

    /**
     * This function duplicates all the records from the old context for the new context.
     *
     * @param int $scaleid
     * @param int $oldcontextid
     * @param int $contextid
     * @return void
     * @throws dml_exception
     */
    public static function duplicate_items_for_scale_with_new_contextid(int $scaleid, int $oldcontextid, int $contextid) {
        global $DB;

        // Make sure we don't do unnecessary work.
        if ($oldcontextid == $contextid) {
            return;
        }

        $sql = "SELECT lci.*
                FROM {local_catquiz_items} lci
                WHERE lci.catscaleid = :scaleid
                AND lci.contextid = :contextid";
        $params['contextid'] = $oldcontextid;
        $params['scaleid'] = $scaleid;

        $records = $DB->get_records_sql($sql, $params);

        foreach ($records as $record) {
            $record->contextid = $contextid;
            unset($record->id);
            $DB->insert_record('local_catquiz_items', $record);
        }
    }

    /**
     * Returns the standard error for the given ability and items
     *
     * @param float $ability
     * @param model_item_param_list $items
     * @param float $default
     * @return float
     */
    public static function get_standarderror(
        float $ability,
        model_item_param_list $items,
        float $default = 1.0
    ): float {
        if (count($items) === 0) {
            return $default;
        }

        $fisherinfo = 0.0;
        foreach ($items as $item) {
            $fisherinfo += model_model::get_instance($item->get_model_name())
                ->fisher_info(['ability' => $ability], $item->get_params_array());
        }

        $fisherinfo = max(10 ** -6, $fisherinfo);
        return (1 / sqrt($fisherinfo));
    }

    /**
     * Calculates the test potential for the items of a scale.
     *
     * This returns the sum of the fisher information of the X items with the
     * greatest fisher information, where X is the number of remaining
     * questions that can be drawn from the scale.
     *
     * @param float $ability The person ability in the given scale.
     * @param model_item_param_list $items The items in the scale.
     * @param int $remaining The number of items that can be drawn.
     * @return float
     */
    public static function get_testpotential(float $ability, model_item_param_list $items, int $remaining): float {
        if ($remaining < 1) {
            return 0.0;
        }

        $fi = [];
        foreach ($items as $item) {
            $fi[] = model_model::get_instance($item->get_model_name())
                ->fisher_info(['ability' => $ability], $item->get_params_array());
        }
        rsort($fi, SORT_NUMERIC);
        $mostinformative = array_slice($fi, 0, $remaining);
        return array_sum($mostinformative);
    }

    /**
     * Calculates the test information of the given items.
     *
     * This returns the sum of the fisher information of the given items.     *
     *
     * @param float $ability The person ability in the given scale.
     * @param model_item_param_list $items The items in the scale.
     * @return float
     */
    public static function get_testinformation(float $ability, model_item_param_list $items): float {
        $fi = [];
        foreach ($items as $item) {
            $fi[] = model_model::get_instance($item->get_model_name())->fisher_info(
                ['ability' => $ability],
                $item->get_params_array()
            );
        }
        return array_sum($fi);
    }
}
