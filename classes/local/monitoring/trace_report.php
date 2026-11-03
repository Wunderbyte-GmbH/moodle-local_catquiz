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

namespace local_catquiz\local\monitoring;

/**
 * Reads the attempt traces back: one attempt in full, or percentiles over many (issue #136).
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class trace_report {
    /**
     * Every trace of one attempt, oldest first, with the spans decoded.
     *
     * @param int $adaptiveattemptid adaptivequiz_attempt.id
     * @return array
     */
    public static function for_attempt(int $adaptiveattemptid): array {
        global $DB;

        $rows = $DB->get_records(timeline::TABLE, ['adaptiveattemptid' => $adaptiveattemptid], 'timecreated ASC, id ASC');
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int) $row->id,
                'adaptiveattemptid' => (int) $row->adaptiveattemptid,
                'kind' => $row->kind,
                'questionnumber' => $row->questionnumber === null ? null : (int) $row->questionnumber,
                'requestms' => $row->requestms === null ? null : (float) $row->requestms,
                'dbqueries' => $row->dbqueries === null ? null : (int) $row->dbqueries,
                'timecreated' => (int) $row->timecreated,
                'spans' => json_decode((string) $row->spans, true) ?: [],
            ];
        }
        return $out;
    }

    /**
     * Percentiles of the request time and of every span, per kind of request.
     *
     * @param int $since Only traces written at or after this time; 0 for all.
     * @return array kind => ['count', 'requestms' => [p50, p95, p99, max], 'spans' => name => [...]]
     */
    public static function summary(int $since = 0): array {
        global $DB;

        $rs = $DB->get_recordset_select(timeline::TABLE, 'timecreated >= :since', ['since' => $since], 'id ASC');
        $request = [];
        $spans = [];
        foreach ($rs as $row) {
            if ($row->requestms !== null) {
                $request[$row->kind][] = (float) $row->requestms;
            }
            foreach (json_decode((string) $row->spans, true) ?: [] as $span) {
                if (isset($span['ms'])) {
                    $spans[$row->kind][$span['name']][] = (float) $span['ms'];
                }
            }
        }
        $rs->close();

        $out = [];
        foreach (array_unique(array_merge(array_keys($request), array_keys($spans))) as $kind) {
            $out[$kind] = [
                'count' => count($request[$kind] ?? []),
                'requestms' => self::percentiles($request[$kind] ?? []),
                'spans' => array_map([self::class, 'percentiles'], $spans[$kind] ?? []),
            ];
            ksort($out[$kind]['spans']);
        }
        ksort($out);
        return $out;
    }

    /**
     * Nearest-rank percentiles p50, p95 and p99, and the maximum.
     *
     * @param float[] $values
     * @return array
     */
    public static function percentiles(array $values): array {
        if (!$values) {
            return ['n' => 0, 'p50' => null, 'p95' => null, 'p99' => null, 'max' => null];
        }
        sort($values);
        $n = count($values);
        $rank = fn(float $p) => $values[max(0, (int) ceil($p * $n) - 1)];
        return ['n' => $n, 'p50' => $rank(0.50), 'p95' => $rank(0.95), 'p99' => $rank(0.99), 'max' => $values[$n - 1]];
    }
}
