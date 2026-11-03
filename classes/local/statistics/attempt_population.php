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

use local_catquiz\catscale;
use local_catquiz\local\result\result_provenance;
use local_catquiz\local\result\scale_result;

/**
 * The attempts a statistic is built from: one definition for every chart (issue #88).
 *
 * An attempt belongs to the population when
 * - it was taken on the root scale of the requested scale (local_catquiz_attempts.scaleid holds the root
 *   scale; a subscale is selected through its root and reported with its own values),
 * - it is completed: its end time is set. The finalisation stamps it exactly once; a running attempt has
 *   none. The status column is no criterion - a running attempt is saved with status "OK" after every
 *   answer, and the stop reasons share their numbers with the attempt states,
 * - it lies in the scope: course, test (adaptivequiz.id), CAT context, and a period of end times with
 *   both bounds included (starttime <= endtime <= endtime of the period),
 * - optionally, it is the first or the last completed attempt of its person in the scope. First and
 *   last are decided over the whole history of the scope, the period filters afterwards: a person whose
 *   first attempt lies before the period has no first attempt in it.
 *
 * A completed attempt without an answer (aborted) belongs to the population: it took place. It has no
 * result. The result of an attempt for a scale is the value stored at finalisation
 * (local_catquiz_personparams, resultsource "current", attemptid = local_catquiz_attempts.id) when it is a
 * valid measurement of the scale, otherwise none. A valid measurement is the valid result of the attempt, or
 * a scale measured validly that is not the reported one - the root scale under "lowest skill gap", say
 * (scale_result::is_valid_measurement(); issue #150, re-audit 27.10.2026). Before, only the reported scale of
 * an attempt had a value: the histogram of a root scale under such a strategy showed almost nobody.
 * Whether the attempt as a whole has a valid result (primaryvalid) is unchanged: the reported scale, valid.
 * Values of local_catquiz_personparams without an attempt (resultsource legacy or recalibration) are not
 * results of an attempt: they have no course, test or end time and are not in the population. An attempt
 * finalised before results were stored that way (legacy) counts with the value it carries, as valid - it was
 * never judged (the rule of learning_progress_series). Which attempt that is, the time of finalisation decides,
 * not a missing row (result_provenance; issue #150, re-audit 28.10.2026): an attempt finalised since results are
 * stored and without a stored row has no result - aborted, nothing measured, or the error of issue #106. Before,
 * such an attempt counted with the value it carried, as valid: the single bar of the root scale in #150.
 *
 * Deliberate differences, named here and tested (doc/statistics-population.md):
 * - In SQL the legacy value exists for the root scale only (personability_after_attempt); the value of a
 *   subscale lives in the JSON of a legacy attempt and is read in PHP only.
 * - "No attempt" counts the people who could take the test (eligible_population), not attempts.
 * - The peer comparison of the feedback excludes the person compared.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class attempt_population {
    /** @var string Every completed attempt of the scope. */
    public const ALL = 'all';

    /** @var string The first completed attempt of each person in the scope. */
    public const FIRST = 'first';

    /** @var string The last completed attempt of each person in the scope. */
    public const LAST = 'last';

    /** @var int Attempts per query when stored results are read. */
    private const CHUNK = 1000;

    /** @var int The requested scale: its values are reported. */
    private int $scaleid;

    /** @var int The root scale of the requested one: attempts are selected by it. */
    private int $rootscaleid;

    /** @var int|null */
    private ?int $courseid;

    /** @var int|null adaptivequiz.id */
    private ?int $testid;

    /** @var int|null */
    private ?int $contextid;

    /** @var int|null Lower bound of the end time, included. */
    private ?int $starttime;

    /** @var int|null Upper bound of the end time, included. */
    private ?int $endtime;

    /** @var string One of ALL, FIRST, LAST. */
    private string $selection;

    /** @var array Attempts loaded by attempts(), by field list. */
    private array $loaded = [];

    /**
     * Constructor.
     *
     * @param int $scaleid The requested scale; a subscale selects through its root scale.
     * @param int|null $courseid
     * @param int|null $testid adaptivequiz.id
     * @param int|null $contextid CAT context, or null for every context of the scale.
     * @param int|null $starttime Lower bound of the end time (included), or null.
     * @param int|null $endtime Upper bound of the end time (included), or null.
     * @param string $selection ALL, FIRST or LAST.
     */
    public function __construct(
        int $scaleid,
        ?int $courseid = null,
        ?int $testid = null,
        ?int $contextid = null,
        ?int $starttime = null,
        ?int $endtime = null,
        string $selection = self::ALL
    ) {
        if (!in_array($selection, [self::ALL, self::FIRST, self::LAST], true)) {
            throw new \coding_exception('Unknown attempt selection: ' . $selection);
        }
        $this->scaleid = $scaleid;
        $this->rootscaleid = self::root_of($scaleid);
        $this->courseid = $courseid ?: null;
        $this->testid = $testid ?: null;
        $this->contextid = $contextid ?: null;
        $this->starttime = $starttime;
        $this->endtime = $endtime;
        $this->selection = $selection;
    }

    /**
     * The root scale of a scale.
     *
     * @param int $scaleid
     * @return int
     */
    public static function root_of(int $scaleid): int {
        global $DB;
        $parentid = $DB->get_field('local_catquiz_catscales', 'parentid', ['id' => $scaleid]);
        if (empty($parentid)) {
            return $scaleid;
        }
        return (int) catscale::get_ancestors($scaleid, 3)['mainscale'];
    }

    /**
     * The SQL condition of a completed attempt.
     *
     * @param string $alias Alias of local_catquiz_attempts; empty for the unqualified column.
     * @return string
     */
    public static function completed_sql(string $alias = 'a'): string {
        $column = $alias === '' ? 'endtime' : "$alias.endtime";
        return "$column IS NOT NULL AND $column > 0";
    }

    /**
     * The requested scale.
     *
     * @return int
     */
    public function get_scaleid(): int {
        return $this->scaleid;
    }

    /**
     * The root scale the attempts are selected by.
     *
     * @return int
     */
    public function get_rootscaleid(): int {
        return $this->rootscaleid;
    }

    /**
     * The selection: ALL, FIRST or LAST.
     *
     * @return string
     */
    public function get_selection(): string {
        return $this->selection;
    }

    /**
     * The condition selecting the attempts of the population.
     *
     * @param string $alias Alias of local_catquiz_attempts.
     * @param string $prefix Prefix of the parameter names; distinct for every occurrence in one statement.
     * @param array|null $userids Only these people, or null for everybody. An empty list selects nothing.
     * @return array [sql, params]
     */
    public function where(string $alias = 'a', string $prefix = 'ap', ?array $userids = null): array {
        global $DB;

        [$sql, $params] = $this->scope_where($alias, $prefix, true);
        if ($this->selection !== self::ALL) {
            // First or last over the whole history of the scope: the period filters afterwards.
            $other = $alias . 'x';
            [$scope, $scopeparams] = $this->scope_where($other, $prefix . 'x', false);
            $before = $this->selection === self::FIRST ? '<' : '>';
            $sql .= " AND NOT EXISTS (
                        SELECT 1
                          FROM {local_catquiz_attempts} $other
                         WHERE $other.userid = $alias.userid
                           AND $scope
                           AND ($other.endtime $before $alias.endtime
                                OR ($other.endtime = $alias.endtime AND $other.id $before $alias.id))
                      )";
            $params += $scopeparams;
        }
        if ($userids !== null) {
            if (!$userids) {
                $sql .= ' AND 1 = 0';
            } else {
                [$insql, $inparams] = $DB->get_in_or_equal(array_map('intval', $userids), SQL_PARAMS_NAMED, $prefix . 'u');
                $sql .= " AND $alias.userid $insql";
                $params += $inparams;
            }
        }
        return [$sql, $params];
    }

    /**
     * The scope without the selection of first or last.
     *
     * @param string $alias
     * @param string $prefix
     * @param bool $withperiod
     * @return array [sql, params]
     */
    private function scope_where(string $alias, string $prefix, bool $withperiod): array {
        $sql = "$alias.scaleid = :{$prefix}root AND " . self::completed_sql($alias);
        $params = [$prefix . 'root' => $this->rootscaleid];
        if ($this->courseid) {
            $sql .= " AND $alias.courseid = :{$prefix}course";
            $params[$prefix . 'course'] = $this->courseid;
        }
        if ($this->testid) {
            $sql .= " AND $alias.instanceid = :{$prefix}test";
            $params[$prefix . 'test'] = $this->testid;
        }
        if ($this->contextid) {
            $sql .= " AND $alias.contextid = :{$prefix}context";
            $params[$prefix . 'context'] = $this->contextid;
        }
        if ($withperiod && $this->starttime !== null) {
            $sql .= " AND $alias.endtime >= :{$prefix}start";
            $params[$prefix . 'start'] = $this->starttime;
        }
        if ($withperiod && $this->endtime !== null) {
            $sql .= " AND $alias.endtime <= :{$prefix}end";
            $params[$prefix . 'end'] = $this->endtime;
        }
        return [$sql, $params];
    }

    /**
     * The valid result of an attempt for the requested scale, as an SQL expression; NULL when there is none.
     *
     * Stored result of the finalisation when the attempt has stored results; for a legacy attempt (finalised before
     * results were stored, result_provenance) the value of the attempt on its root scale; otherwise none. A legacy
     * subscale value has no SQL expression (see the class). The same rule as attempts().
     *
     * @param string $alias Alias of local_catquiz_attempts.
     * @param string $prefix Prefix of the parameter names.
     * @return array [sql, params]
     */
    public function value_sql(string $alias = 'a', string $prefix = 'apv'): array {
        [$measured, $measuredparams] = scale_result::valid_measurement_sql("{$prefix}p", $prefix);
        $stored = "(SELECT MAX({$prefix}p.ability)
                      FROM {local_catquiz_personparams} {$prefix}p
                     WHERE {$prefix}p.attemptid = $alias.id
                       AND {$prefix}p.resultsource = :{$prefix}src
                       AND {$prefix}p.catscaleid = :{$prefix}scale
                       AND $measured)";
        $legacy = $this->scaleid === $this->rootscaleid ? "$alias.personability_after_attempt" : 'NULL';
        [$islegacy, $legacyparams] = result_provenance::legacy_sql($alias, $prefix);
        $sql = "CASE WHEN EXISTS (SELECT 1
                                    FROM {local_catquiz_personparams} {$prefix}x
                                   WHERE {$prefix}x.attemptid = $alias.id
                                     AND {$prefix}x.resultsource = :{$prefix}src2)
                     THEN $stored
                     WHEN $islegacy THEN $legacy
                     ELSE NULL
                 END";
        return [$sql, [
            $prefix . 'src' => 'current',
            $prefix . 'src2' => 'current',
            $prefix . 'scale' => $this->scaleid,
        ] + $measuredparams + $legacyparams];
    }

    /**
     * The attempts of the population, in the order of their end time.
     *
     * Every record carries `value` (the valid result for the requested scale or null), `primaryvalid`
     * (whether the reported scale of the attempt has a valid result), `provenance` (result_provenance::STORED,
     * LEGACY or NORESULT), `legacy` (provenance LEGACY) and `valuesource`: 'reported' (the valid result of the
     * attempt), 'measured' (a valid measurement of a scale the attempt did not report), 'legacy', or null;
     * `primaryscaleid` and `primaryvalue`: the valid reported primary result of the attempt, whatever scale it is
     * on (issue #160) - for LOWESTSUB often a subscale while the requested root scale has no valid measurement.
     *
     * @param string $fields Columns of local_catquiz_attempts, alias a; id, userid, scaleid, endtime and json
     *                       are always read.
     * @param array|null $userids See where().
     * @return array
     */
    public function attempts(string $fields = '', ?array $userids = null): array {
        global $DB;

        $columns = 'a.id, a.userid, a.scaleid, a.contextid, a.courseid, a.instanceid, a.attemptid, '
            . 'a.starttime, a.endtime, a.json, a.personability_after_attempt, a.status, a.timemodified';
        if ($fields !== '') {
            $columns .= ', ' . $fields;
        }
        $key = $columns . '|' . ($userids === null ? '*' : implode(',', $userids));
        if (isset($this->loaded[$key])) {
            return $this->loaded[$key];
        }
        [$where, $params] = $this->where('a', 'ap', $userids);
        $records = [];
        $decoded = [];
        // Never debug_info: it can hold the full trace of an attempt and no chart reads it. A recordset
        // holds a database resource until it is closed.
        $recordset = $DB->get_recordset_sql(
            "SELECT $columns FROM {local_catquiz_attempts} a WHERE $where ORDER BY a.endtime ASC, a.id ASC",
            $params
        );
        foreach ($recordset as $record) {
            // Only what the charts read stays in memory.
            $json = json_decode((string) ($record->json ?? ''));
            $decoded[(int) $record->id] = (object) [
                'personabilities_abilities' => $json->personabilities_abilities ?? null,
                'personabilities' => $json->personabilities ?? null,
            ];
            $record->json = json_encode($decoded[(int) $record->id]);
            $records[(int) $record->id] = $record;
        }
        $recordset->close();

        $stored = $this->stored_results(array_keys($records));
        foreach ($records as $id => $record) {
            $json = $decoded[$id];
            $record->valuesource = null;
            if (isset($stored[$id])) {
                $row = $stored[$id]['scales'][$this->scaleid] ?? null;
                $record->value = ($row && $row->ability !== null
                    && scale_result::is_valid_measurement((int) $row->isvalid, $row->validationstatus))
                    ? (float) $row->ability : null;
                if ($record->value !== null) {
                    $record->valuesource = (int) $row->isvalid === 1 ? 'reported' : 'measured';
                }
                $record->primaryvalid = $stored[$id]['primaryvalid'];
                $primary = $stored[$id]['primary'];
                $record->primaryscaleid = $primary && $primary->ability !== null ? (int) $primary->catscaleid : null;
                $record->primaryvalue = $record->primaryscaleid !== null ? (float) $primary->ability : null;
                $record->provenance = result_provenance::STORED;
            } else if (result_provenance::without_stored_results((int) $record->endtime) === result_provenance::LEGACY) {
                $record->value = self::legacy_value($record, $json, $this->scaleid, $this->rootscaleid);
                $record->valuesource = $record->value !== null ? 'legacy' : null;
                // Never judged: valid, like its value. Its reported result is the one of the root scale.
                $record->primaryvalid = true;
                $rootvalue = self::legacy_value($record, $json, $this->rootscaleid, $this->rootscaleid);
                $record->primaryscaleid = $rootvalue !== null ? $this->rootscaleid : null;
                $record->primaryvalue = $rootvalue;
                $record->provenance = result_provenance::LEGACY;
            } else {
                // Judged at finalisation, nothing stored: no result, whatever the attempt carries (issue #150).
                $record->value = null;
                $record->primaryvalid = false;
                $record->primaryscaleid = null;
                $record->primaryvalue = null;
                $record->provenance = result_provenance::NORESULT;
            }
            $record->legacy = $record->provenance === result_provenance::LEGACY;
        }
        $this->loaded[$key] = array_values($records);
        return $this->loaded[$key];
    }

    /**
     * The value of a legacy attempt (no stored results) for a scale.
     *
     * @param \stdClass $record
     * @param mixed $json Decoded JSON of the attempt.
     * @param int $scaleid
     * @param int $rootscaleid
     * @return float|null
     */
    private static function legacy_value(\stdClass $record, $json, int $scaleid, int $rootscaleid): ?float {
        if ($scaleid === $rootscaleid && isset($record->personability_after_attempt)) {
            return (float) $record->personability_after_attempt;
        }
        if (isset($json->personabilities->$scaleid) && is_numeric($json->personabilities->$scaleid)) {
            return (float) $json->personabilities->$scaleid;
        }
        return null;
    }

    /**
     * The results stored at finalisation, by attempt.
     *
     * @param int[] $ids local_catquiz_attempts.id
     * @return array attemptid => ['scales' => [scaleid => row], 'primaryvalid' => bool]
     */
    private function stored_results(array $ids): array {
        global $DB;

        $stored = [];
        foreach (array_chunk($ids, self::CHUNK) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'apsr');
            $rows = $DB->get_recordset_select(
                'local_catquiz_personparams',
                "attemptid $insql AND resultsource = :source",
                $params + ['source' => 'current'],
                'id',
                'id, attemptid, catscaleid, ability, isvalid, isprimary, validationstatus'
            );
            foreach ($rows as $row) {
                $id = (int) $row->attemptid;
                $stored[$id] = $stored[$id] ?? ['scales' => [], 'primaryvalid' => false, 'primary' => null];
                $stored[$id]['scales'][(int) $row->catscaleid] = $row;
                if ((int) $row->isprimary === 1 && (int) $row->isvalid === 1) {
                    $stored[$id]['primaryvalid'] = true;
                    // Issue #160: the reported primary result itself - its scale may be a subscale.
                    $stored[$id]['primary'] = $row;
                }
            }
            $rows->close();
        }
        return $stored;
    }

    /**
     * One valid value per person: the latest (or first) valid result among the attempts of the population.
     *
     * @param array $attempts Records of attempts().
     * @param string $rule 'last' or 'first'.
     * @return array userid => value
     */
    public static function value_per_person(array $attempts, string $rule = 'last'): array {
        $values = [];
        foreach ($attempts as $attempt) {
            if ($attempt->value === null) {
                continue;
            }
            $userid = (int) $attempt->userid;
            if ($rule === 'first' && array_key_exists($userid, $values)) {
                continue;
            }
            $values[$userid] = (float) $attempt->value;
        }
        return $values;
    }
}
