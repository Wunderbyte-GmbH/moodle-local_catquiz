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

use local_catquiz\local\result\result_provenance;
use local_catquiz\local\result\scale_result;
use local_catquiz\teststrategy\feedback_helper;

/**
 * Learning progress as a cumulative "latest valid result per participant" series (issue #119).
 *
 * For every period t the cohort mean is the mean, over every person who has a valid result up to the
 * end of t, of that person's latest valid result up to the end of t. A person stays in the mean after
 * their last attempt; a new person enters with their first valid result; a new valid result replaces
 * only the value of the same person; an invalid result replaces nothing. A period without attempts
 * carries the previous state. Nothing is interpolated, and nothing is read from the current person
 * parameters: the values are the results stored with each attempt.
 *
 * The personal feedback (learningprogress) and the statistics shortcode (catquizstatistics) both
 * build their series here, so for the same attempts they show the same curve.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class learning_progress_series {
    /** @var int Attempts per query when the stored results are read. */
    private const CHUNK = 1000;

    /**
     * The cohort mean per period.
     *
     * @param array $items Results, each ['userid' => int, 'time' => int, 'order' => int, 'bucket' => string,
     *                     'value' => ?float, 'valid' => bool]. 'order' breaks ties of the same second.
     * @param string[] $bucketkeys The periods, in time order.
     * @return array Period => mean, or null before the first valid result.
     */
    public static function build_cumulative_latest_series(array $items, array $bucketkeys): array {
        usort($items, fn($a, $b) => [$a['time'], $a['order']] <=> [$b['time'], $b['order']]);
        $bybucket = [];
        foreach ($items as $item) {
            $bybucket[$item['bucket']][] = $item;
        }

        $latest = [];
        $series = [];
        foreach ($bucketkeys as $key) {
            foreach ($bybucket[$key] ?? [] as $item) {
                // An invalid result, or none for this scale, replaces nothing.
                if (empty($item['valid']) || $item['value'] === null) {
                    continue;
                }
                $latest[(int) $item['userid']] = (float) $item['value'];
            }
            $series[$key] = $latest ? array_sum($latest) / count($latest) : null;
        }
        return $series;
    }

    /**
     * The results of attempts for one scale, as items for the series.
     *
     * Source: the result stored for the scale when the attempt was finalised
     * (local_catquiz_personparams, resultsource "current", with its isvalid). An attempt finalised before
     * results were stored that way has none at all; for it the value in the attempt's own JSON is used and
     * counted as valid - it was never judged (documented fallback, issue #119). Which attempt that is, the time of
     * its finalisation decides (result_provenance): one finalised since then without a stored row has no result
     * (issue #150, re-audit 28.10.2026). An attempt that has stored results, but none for this scale, did not
     * measure the scale and gives no item.
     *
     * @param array $attempts local_catquiz_attempts records with id, userid, endtime and json.
     * @param int $scaleid
     * @param int $timerange One of the LOCAL_CATQUIZ_TIMERANGE_* constants.
     * @return array Items for build_cumulative_latest_series().
     */
    public static function items_for_attempts(array $attempts, int $scaleid, int $timerange): array {
        global $DB;

        $attempts = array_filter($attempts, fn($a) => !empty($a->endtime));
        $ids = array_values(array_unique(array_map(fn($a) => (int) $a->id, $attempts)));
        $stored = [];
        $withresults = [];
        foreach (array_chunk($ids, self::CHUNK) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED);
            $rows = $DB->get_recordset_select(
                'local_catquiz_personparams',
                "attemptid $insql AND resultsource = :source",
                $params + ['source' => 'current'],
                '',
                'id, attemptid, catscaleid, ability, isvalid, validationstatus'
            );
            foreach ($rows as $row) {
                $withresults[(int) $row->attemptid] = true;
                if ((int) $row->catscaleid === $scaleid) {
                    $stored[(int) $row->attemptid] = $row;
                }
            }
            $rows->close();
        }

        $items = [];
        foreach ($attempts as $attempt) {
            $id = (int) $attempt->id;
            if (isset($stored[$id])) {
                $value = $stored[$id]->ability === null ? null : (float) $stored[$id]->ability;
                // Issue #150 (re-audit 27.10.2026): a valid measurement of the scale, reported or not.
                $valid = scale_result::is_valid_measurement((int) $stored[$id]->isvalid, $stored[$id]->validationstatus);
            } else if (isset($withresults[$id])) {
                continue;
            } else if (result_provenance::without_stored_results((int) $attempt->endtime) !== result_provenance::LEGACY) {
                // Finalised since results are stored, none stored: no result (issue #150, re-audit 28.10.2026).
                continue;
            } else {
                $json = json_decode($attempt->json ?? '');
                $value = isset($json->personabilities->$scaleid) && $json->personabilities->$scaleid !== null
                    ? (float) $json->personabilities->$scaleid : null;
                $valid = true;
            }
            if ($value === null) {
                continue;
            }
            $items[] = [
                'userid' => (int) $attempt->userid,
                'time' => (int) $attempt->endtime,
                'order' => $id,
                'bucket' => feedback_helper::return_datestring_label($timerange, (int) $attempt->endtime),
                'value' => $value,
                'valid' => $valid,
            ];
        }
        // In the order of the attempts - end time, then id - whatever order the records came in: the database
        // gave get_records_list() no order, and the series came out in a different order on PostgreSQL.
        usort($items, fn($a, $b) => [$a['time'], $a['order']] <=> [$b['time'], $b['order']]);
        return $items;
    }

    /**
     * The two series of the progress chart: the viewer's own and the cohort of the others.
     *
     * Periods before the first value of either series are left out.
     *
     * @param array $attempts local_catquiz_attempts records of the scope (see items_for_attempts()).
     * @param int $scaleid
     * @param int $userid The viewer: their own series; the others form the cohort.
     * @param int $timerange One of the LOCAL_CATQUIZ_TIMERANGE_* constants.
     * @param int[] $range [start, end] timestamps of the periods.
     * @return array ['labels' => string[], 'user' => array, 'peers' => array], values float|null.
     */
    public static function comparison(array $attempts, int $scaleid, int $userid, int $timerange, array $range): array {
        $items = self::items_for_attempts($attempts, $scaleid, $timerange);
        $keys = array_values(feedback_helper::get_timerangekeys($timerange, $range));
        $user = self::build_cumulative_latest_series(array_filter($items, fn($i) => $i['userid'] === $userid), $keys);
        $peers = self::build_cumulative_latest_series(array_filter($items, fn($i) => $i['userid'] !== $userid), $keys);

        $labels = [];
        foreach ($keys as $key) {
            if (!$labels && $user[$key] === null && $peers[$key] === null) {
                continue;
            }
            $labels[] = $key;
        }
        return [
            'labels' => $labels,
            'user' => array_map(fn($k) => $user[$k], $labels),
            'peers' => array_map(fn($k) => $peers[$k], $labels),
        ];
    }

    /**
     * One person's valid results in time order, one value per attempt.
     *
     * @param array $attempts The person's local_catquiz_attempts records.
     * @param int $scaleid
     * @return float[]
     */
    public static function individual(array $attempts, int $scaleid): array {
        global $CFG;
        require_once($CFG->dirroot . '/local/catquiz/lib.php');
        $items = array_filter(
            self::items_for_attempts($attempts, $scaleid, LOCAL_CATQUIZ_TIMERANGE_DAY),
            fn($i) => $i['valid']
        );
        usort($items, fn($a, $b) => [$a['time'], $a['order']] <=> [$b['time'], $b['order']]);
        return array_map(fn($i) => $i['value'], array_values($items));
    }
}
