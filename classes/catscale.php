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
use local_catquiz\local\result;
use local_catquiz\local\status;
use moodle_exception;
use stdClass;

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
     * @param integer $catscaleid
     */
    public function __construct(int $catscaleid) {

        $this->catscale = self::return_catscale_object($catscaleid);
    }


    /**
     * Static function to return catscale object.
     *
     * @param integer $catscaleid
     * @return stdClass|null
     */
    public static function return_catscale_object(int $catscaleid) {
        global $DB;
        return $DB->get_record('local_catquiz_catscales', ['id' => $catscaleid]) ?? null;
    }

    /**
     * Adds or updates attribution of question to scale.
     *
     * @param integer $catscaleid
     * @param integer $testitemid
     * @param string $component
     * @return result
     */
    public static function add_or_update_testitem_to_scale(int $catscaleid, int $testitemid, string $component = 'question') {

        global $DB;

        $data = [
            'componentid' => $testitemid,
            'componentname' => 'question',
            'catscaleid' => $catscaleid,
        ];

        if ($record = $DB->get_record('local_catquiz_items', $data)) {
            // Right now, there is nothing to do, as we don't have more data.
            // $data['id'] = $record->id;
            // $DB->update_record('local_catquiz_items', (object)$data);
            $id = $record->id;
        } else {
            // We won't allow an item to be assigned to both a scale and its sub- or parent-scale
            if (self::is_assigned_to_parent_scale($catscaleid, $testitemid)
                || self::is_assigned_to_subscale($catscaleid, $testitemid)) {
                    return result::err(status::ERROR_TESTITEM_ALREADY_IN_RELATED_SCALE, $testitemid);
                }

            $now = time();
            $data['timemodified'] = $now;
            $data['timecreated'] = $now;
            $id = $DB->insert_record('local_catquiz_items', (object)$data);
        }
        cache_helper::purge_by_event('changesintestitems');
        return result::ok($id);
    }

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
     * @param integer $catscaleid
     * @param integer $testidemid
     * @param string $component
     * @return void
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
     * @return array
     */
    public function get_testitems(int $contextid, bool $includesubscales = false):array {

        $cache = cache::make('local_catquiz', 'attemptquestions');
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
            [],
            [],
            $contextid,
            $USER->id
        );

        $sql = "SELECT $select FROM $from WHERE $where";

        $testitems = $DB->get_records_sql($sql, $params) ?? [];
        $cache->set($cachekey, $testitems);
        return $testitems;
    }

    public static function update_testitem(int $contextid, $question, $includesubscales = false) {
        $cache = cache::make('local_catquiz', 'attemptquestions');
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
     * @return array 
     */
    private static function get_subscale_ids(int $catscaleid = null): array {
        global $DB;

        $all = $DB->get_records("local_catquiz_catscales", null, "", "id, parentid");

        return self::add_subscales($catscaleid, $all);
    }

    private static function add_subscales(int $parentid, $all): ?array {
        foreach ($all as $scale) {
            if (intval($scale->parentid) === $parentid) {
                return [$scale->id, ...self::add_subscales(intval($scale->id), $all)];
            }
        }
        return [];
    }

    private static function get_ancestors(int $catscaleid) {
        global $DB;
        $all = $DB->get_records("local_catquiz_catscales", null, "", "id, parentid");
        return self::add_parentscales($catscaleid, $all);
    }

    private static function add_parentscales(int $scaleid, array $all): ?array {
        foreach ($all as $scale) {
            if (intval($scale->id) === $scaleid && intval($scale->parentid) !== 0) {
                return [$scale->parentid, ...self::add_parentscales(intval($scale->parentid), $all)];
            }
        }
        return [];
    }
}
