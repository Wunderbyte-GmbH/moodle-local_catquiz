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

namespace local_catquiz\local\integrity;

/**
 * Whether the items of a CAT context point to parameters they may use (issue #157).
 *
 * An item belongs to one CAT context (local_catquiz_items.contextid). Its active parameters
 * (local_catquiz_items.activeparamid) have to be a row of local_catquiz_itemparams of this very item, in its context,
 * for its question. An item without a reference is a pilot. An item whose reference does not resolve is neither: the
 * runtime pool leaves it out (catquiz::return_sql_for_catscalequestions()), it never becomes a pilot and never takes
 * the parameters of another item or context. This class names such items, by kind, for the diagnosis, the CLI and the
 * event item_param_integrity_failed. Read-only.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class item_param_integrity {
    /** @var string The active parameters do not exist. */
    public const MISSING = 'missing';

    /** @var string The active parameters are those of another item. */
    public const OTHER_ITEM = 'otheritem';

    /** @var string The active parameters lie in another CAT context than the item. */
    public const OTHER_CONTEXT = 'othercontext';

    /** @var string The active parameters belong to another question. */
    public const OTHER_QUESTION = 'otherquestion';

    /** @var int Item ids listed per kind at most. */
    private const SAMPLE = 20;

    /**
     * The items of a CAT context with an active reference that does not resolve.
     *
     * @param int $contextid
     * @param int[] $scaleids Only items of these scales; empty for every scale.
     * @return array ['count' => int, 'bykind' => [kind => int], 'sample' => [kind => int[] item ids],
     *               'withoutcontext' => int (items of the scales without a CAT context, never in a pool)]
     */
    public static function check(int $contextid, array $scaleids = []): array {
        global $DB;
        $scalesql = '';
        $params = ['ctx' => $contextid];
        if ($scaleids) {
            [$insql, $inparams] = $DB->get_in_or_equal(array_map('intval', $scaleids), SQL_PARAMS_NAMED, 'ipi');
            $scalesql = " AND lci.catscaleid $insql";
            $params += $inparams;
        }
        $rows = $DB->get_recordset_sql(
            "SELECT lci.id, lci.componentid, lci.contextid, lci.activeparamid,
                    lcip.id AS paramid, lcip.itemid AS paramitemid, lcip.contextid AS paramcontextid,
                    lcip.componentid AS paramcomponentid
               FROM {local_catquiz_items} lci
          LEFT JOIN {local_catquiz_itemparams} lcip ON lcip.id = lci.activeparamid
              WHERE lci.contextid = :ctx AND COALESCE(lci.activeparamid, 0) <> 0 $scalesql",
            $params
        );
        $bykind = [self::MISSING => 0, self::OTHER_ITEM => 0, self::OTHER_CONTEXT => 0, self::OTHER_QUESTION => 0];
        $sample = array_fill_keys(array_keys($bykind), []);
        foreach ($rows as $row) {
            $kind = self::kind($row);
            if ($kind === null) {
                continue;
            }
            $bykind[$kind]++;
            if (count($sample[$kind]) < self::SAMPLE) {
                $sample[$kind][] = (int) $row->id;
            }
        }
        $rows->close();
        $withoutcontext = 0;
        if ($scaleids) {
            [$insql, $inparams] = $DB->get_in_or_equal(array_map('intval', $scaleids), SQL_PARAMS_NAMED, 'ipn');
            $withoutcontext = $DB->count_records_select(
                'local_catquiz_items',
                "catscaleid $insql AND contextid IS NULL",
                $inparams
            );
        }
        return ['count' => array_sum($bykind), 'bykind' => $bykind, 'sample' => $sample, 'withoutcontext' => $withoutcontext];
    }

    /**
     * What is wrong with the reference of an item; null when it resolves.
     *
     * @param \stdClass $row Item with paramid, paramitemid, paramcontextid, paramcomponentid.
     * @return string|null
     */
    public static function kind(\stdClass $row): ?string {
        if (empty($row->activeparamid)) {
            return null;
        }
        if (empty($row->paramid)) {
            return self::MISSING;
        }
        if ((int) $row->paramitemid !== (int) $row->id) {
            return self::OTHER_ITEM;
        }
        if ((int) $row->paramcontextid !== (int) $row->contextid) {
            return self::OTHER_CONTEXT;
        }
        // A parameter row without a question (componentid 0 or null, as older rows and the seed tools write them) is
        // bound to its item by itemid alone; only a row that names another question is an error.
        if ((int) $row->paramcomponentid !== 0 && (int) $row->paramcomponentid !== (int) $row->componentid) {
            return self::OTHER_QUESTION;
        }
        return null;
    }

    /**
     * Reports the items of a pool that were left out for a reference that does not resolve: an event for the CAT
     * manager (logs). Called when a runtime pool is built, so once per pool and cache.
     *
     * @param int $contextid
     * @param int[] $scaleids
     * @return array The result of check().
     */
    public static function report(int $contextid, array $scaleids): array {
        $result = self::check($contextid, $scaleids);
        if ($result['count'] > 0) {
            \local_catquiz\event\item_param_integrity_failed::create([
                'context' => \context_system::instance(),
                'other' => [
                    'catcontextid' => $contextid,
                    'count' => $result['count'],
                    'bykind' => $result['bykind'],
                    'sample' => $result['sample'],
                ],
            ])->trigger();
        }
        return $result;
    }
}
