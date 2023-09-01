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
use local_catquiz\event\testitemactivitystatus_updated;
use local_catquiz\event\testiteminscale_added;
use local_catquiz\event\testiteminscale_updated;
use local_catquiz\local\result;
use local_catquiz\local\status;
use moodle_exception;
use moodle_url;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * Class catquiz
 *
 * @author Georg Maißer
 * @copyright 2022 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catscale {

    /**
     *
     * @var stdClass $catscale.
     */
    public ?stdClass $catscale = null;

    /**
     * Catscale constructor.
     *
     * @param int $catscaleid
     */
    public function __construct(int $catscaleid) {

        $this->catscale = self::return_catscale_object($catscaleid);
    }

    /**
     * Static function to return catscale object.
     *
     * @param int $catscaleid
     * @return stdClass|null
     */
    public static function return_catscale_object(int $catscaleid) {
        global $DB;
        return $DB->get_record('local_catquiz_catscales', ['id' => $catscaleid]) ?? null;
    }

    /**
     * Adds or updates attribution of question to scale.
     *
     * @param int $catscaleid
     * @param int $testitemid
     * @param int $status
     * @param string $component
     * @return result
     */
    public static function add_or_update_testitem_to_scale(
            int $catscaleid,
            int $testitemid,
            int $status = 2, // Just to check if status is changed, we make a nonsense default.
            string $component = 'question') {

        global $DB;
        $context = \context_system::instance();

        $searchparams = [
            'componentid' => $testitemid,
            'componentname' => $component,
            'catscaleid' => $catscaleid,
        ];

        // Check if status is changed
        $statuschanged = false;
        if ($status == 2) {
            $status = TESTITEM_STATUS_ACTIVE;
        } else {
            $statuschanged = true;
        }

        $data = $searchparams;
        $data['status'] = $status;

        // We need the default context for the events that will be triggered
        $catcontext = catquiz::get_default_context_id();

        if ($record = $DB->get_record('local_catquiz_items', $searchparams)) {
            // Right now, there is nothing to do, as we don't have more data.
            $id = $record->id;
            $data['id'] = $id;
            $DB->update_record('local_catquiz_items', (object)$data);

            if ($statuschanged) {
                // Trigger status changed event
                $event = testitemactivitystatus_updated::create([
                    'objectid' => $testitemid,
                    'context' => $context,
                    'other' => [
                        'activitystatus' => $status,
                        'testitemid' => $testitemid,
                        'catscaleid' => $catscaleid,
                        'context' => $catcontext,
                        'component' => $component,
                    ]
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
                ]
                ]);
            $event->trigger();

        } else {
            // We won't allow an item to be assigned to both a scale and its sub- or parent-scale.
            if (self::is_assigned_to_parent_scale($catscaleid, $testitemid)
                || self::is_assigned_to_subscale($catscaleid, $testitemid)) {
                    return result::err(status::ERROR_TESTITEM_ALREADY_IN_RELATED_SCALE, $testitemid);
            }

            $now = time();
            $data['timemodified'] = $now;
            $data['timecreated'] = $now;
            $id = $DB->insert_record('local_catquiz_items', (object)$data);

            // Trigger event
            $event = testiteminscale_added::create([
                'objectid' => $testitemid,
                'context' => $context,
                'other' => [
                    'testitemid' => $testitemid,
                    'catscaleid' => $catscaleid,
                    'context' => $catcontext,
                    'component' => $component,
                ]
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
            array_merge($inparams, ['testitemid' => $testitemid]));
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
            array_merge($inparams, ['testitemid' => $testitemid]));
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
        cache_helper::purge_by_event('changesintestitems');
    }

    /**
     * Returns the testitems for this scale and all the subscales.
     *
     * @param int $contextid
     * @param bool $includesubscales
     * @param string|null $orderby If given, sort items by that field
     *
     * @return array
     *
     */
    public function get_testitems(int $contextid, bool $includesubscales = false, ?string $orderby = null):array {

        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        $cachekey = sprintf('testitems_%s_%s', $contextid, $includesubscales);
        if ($testitems = $cache->get($cachekey)) {
            return $testitems;
        }

        global $DB, $USER;
        $scaleids = [$this->catscale->id];
        if ($includesubscales) {
            $subscaleids = self::get_subscale_ids($this->catscale->id);
            $scaleids = array_merge($scaleids, $subscaleids);
        }

        list($select, $from, $where, $filter, $params) = catquiz::return_sql_for_catscalequestions(
            $scaleids,
            $contextid,
            [],
            [],
            $USER->id,
            $orderby
        );

        $sql = "SELECT $select FROM $from WHERE $where";

        $testitems = $DB->get_records_sql($sql, $params) ?? [];
        $cache->set($cachekey, $testitems);
        return $testitems;
    }

    /**
     * Update testitem.
     *
     * @param int $contextid
     * @param mixed $question
     * @param bool $includesubscales
     *
     * @return void
     *
     */
    public static function update_testitem(int $contextid, $question, $includesubscales = false) {
        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        $cachekey = sprintf('testitems_%s_%s', $contextid, $includesubscales);
        // This should never happen...
        if (!$testitems = $cache->get($cachekey)) {
            throw new moodle_exception(
                sprintf(
                    "Can not update question in questions cache: cache with key %s is empty",
                    $cachekey
                )
            );
        }
        $testitems[$question->id] = $question;
        $cache->set($cachekey, $testitems);
    }

    /**
     * Get all subscale IDs
     *
     * @param int|null $catscaleid
     *
     * @return array
     *
     */
    public static function get_subscale_ids(int $catscaleid = null): array {
        global $DB;

        $all = $DB->get_records("local_catquiz_catscales", null, "", "id, parentid");

        return self::add_subscales($catscaleid, $all);
    }

    /**
     * Add subscales.
     *
     * @param int $parentid
     * @param mixed $all
     *
     * @return array|null
     *
     */
    private static function add_subscales(int $parentid, $all): ?array {
        $result = [];
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

        $where = ' parentid '. $insql;
        $params = array_merge($params, $inparams);
        $filter = '';
        $sql = "SELECT $select FROM $from WHERE $where";
        $subscaleids = $DB->get_records_sql($sql, $params);

        return $subscaleids;

    }

    /**
     * Get IDs of parentscales.
     * @param int $catscaleid
     * @return null|array
     * @throws dml_exception
     */
    public static function get_ancestors(int $catscaleid) {
        global $DB;
        $all = $DB->get_records("local_catquiz_catscales", null, "", "id, parentid");
        return self::add_parentscales($catscaleid, $all);
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
     * @param int $catscaleid
     * @return string
     */
    public static function get_link_to_catscale(int $catscaleid, $url = '/local/catquiz/edit_catscale.php') {

        $catscale = self::return_catscale_object($catscaleid);
        $catscalename = $catscale->name;

        $url = new moodle_url($url);
        $url->param('id', $catscaleid);
        $linktoscale = html_writer::link($url, $catscalename);

        return $linktoscale;
    }

    /**
     * Get HTML link to testitem detail view.
     * @param int $catscaleid
     * @return string
     */
    public static function get_link_to_testitem(
        int $testitemid,
        int $catscaleid,
        int $context,
        string $component,
        string $linktext = "",
        $url = '/local/catquiz/manage_catscales.php') {

        if (empty($linktext)) {
            $linktext = get_string('testitem', 'local_catquiz', $testitemid);
        }
        $url = new moodle_url($url);
        $url->param('id', $testitemid);
        $url->param('contextid', $context);
        $url->param('scaleid', $catscaleid);
        $url->param('component', $component);
        $url->set_anchor("questions");

        $linktoscale = html_writer::link($url, $linktext);

        return $linktoscale;
    }
}
