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

namespace local_catquiz\local\result;

/**
 * Where the result of a completed attempt comes from (issue #150, re-audit 28.10.2026).
 *
 * Since version 2026082104 (issue #9) the finalisation judges every completed attempt and stores what it measured
 * (local_catquiz_personparams, resultsource "current", attemptid = local_catquiz_attempts.id). An attempt finalised
 * before was never judged: its value counts as valid (legacy). An attempt finalised after it without a stored row
 * - aborted, nothing measured, or ended by the error of issue #106 - has no result. Before, the missing row alone
 * made an attempt "legacy", and the value it carried counted as a valid result: the single bar of #150.
 *
 * The boundary is a fact of the site, read from its own records once, at the upgrade (record()), and kept in the
 * plugin setting "resultsstoredsince":
 * - the first entry of the upgrade log of local_catquiz at a version from 2026082104 on - the moment the code that
 *   stores results was installed;
 * - the first stored result of an attempt (resultsource "current", attemptid set), in case the log was cleaned up.
 * Both lie at or after the true boundary; the earlier of the two is taken. Without either (no attempt has been
 * stored and the log is gone), the time of the upgrade: every attempt so far counts as before, as until now.
 * A new installation stores results from the start: the time of the installation.
 *
 * Without the setting the boundary is unknown, and no attempt counts as legacy: a missing row is no proof of an old
 * attempt. The site administrator can set it (admin/cli/cfg.php --component=local_catquiz --name=resultsstoredsince
 * --set=<unix time>); cli/diagnose_statistics.php shows it, with its source.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class result_provenance {
    /** @var string Plugin setting: the time from which results of attempts are stored. */
    public const CONFIG = 'resultsstoredsince';

    /** @var string Plugin setting: where the time came from. */
    public const CONFIG_SOURCE = 'resultsstoredsince_source';

    /** @var int The first version that stores the results of attempts (upgrade step of issue #9). */
    public const FIRST_STORING_VERSION = 2026082104;

    /** @var string The attempt has stored results. */
    public const STORED = 'stored';

    /** @var string Finalised before results were stored: its own value counts, never judged. */
    public const LEGACY = 'legacy';

    /** @var string Finalised since results are stored, without a stored row: no result. */
    public const NORESULT = 'noresult';

    /**
     * The time from which results are stored; 0 when unknown.
     *
     * @return int
     */
    public static function since(): int {
        $value = get_config('local_catquiz', self::CONFIG);
        return is_numeric($value) ? max(0, (int) $value) : 0;
    }

    /**
     * Where an attempt without stored results stands.
     *
     * @param int|null $endtime local_catquiz_attempts.endtime
     * @return string LEGACY or NORESULT.
     */
    public static function without_stored_results(?int $endtime): string {
        return !empty($endtime) && $endtime < self::since() ? self::LEGACY : self::NORESULT;
    }

    /**
     * The same rule as SQL: an attempt finalised before results were stored.
     *
     * @param string $alias Alias of local_catquiz_attempts.
     * @param string $prefix Prefix of the parameter name.
     * @return array [sql, params]
     */
    public static function legacy_sql(string $alias, string $prefix): array {
        return ["$alias.endtime < :{$prefix}since", [$prefix . 'since' => self::since()]];
    }

    /**
     * The boundary as the records of the site give it.
     *
     * @return array ['since' => int|null, 'source' => string, 'upgradelog' => int|null, 'firstresult' => int|null]
     */
    public static function determine(): array {
        global $DB;

        $upgradelog = null;
        $dbman = $DB->get_manager();
        if ($dbman->table_exists('upgrade_log')) {
            $rows = $DB->get_recordset('upgrade_log', ['plugin' => 'local_catquiz'], '', 'id, version, timemodified');
            foreach ($rows as $row) {
                if (is_numeric($row->version) && (int) $row->version >= self::FIRST_STORING_VERSION) {
                    $upgradelog = $upgradelog === null ? (int) $row->timemodified : min($upgradelog, (int) $row->timemodified);
                }
            }
            $rows->close();
        }
        $first = $DB->get_field_sql(
            "SELECT MIN(timecreated)
               FROM {local_catquiz_personparams}
              WHERE resultsource = :source AND attemptid IS NOT NULL AND timecreated > 0",
            ['source' => 'current']
        );
        $firstresult = $first ? (int) $first : null;
        $candidates = array_filter([$upgradelog, $firstresult], fn($v) => $v !== null && $v > 0);
        if (!$candidates) {
            return ['since' => null, 'source' => 'none', 'upgradelog' => null, 'firstresult' => null];
        }
        $since = min($candidates);
        return [
            'since' => $since,
            'source' => $since === $upgradelog ? 'upgradelog' : 'firstresult',
            'upgradelog' => $upgradelog,
            'firstresult' => $firstresult,
        ];
    }

    /**
     * Records the boundary in the plugin settings, unless it is set already.
     *
     * @param int|null $fallback The time to take when the records give none (the upgrade, the installation).
     * @return array The result of determine(), with the time recorded as 'since'.
     */
    public static function record(?int $fallback = null): array {
        $existing = get_config('local_catquiz', self::CONFIG);
        if (is_numeric($existing) && (int) $existing > 0) {
            return [
                'since' => (int) $existing,
                'source' => (string) get_config('local_catquiz', self::CONFIG_SOURCE),
                'upgradelog' => null,
                'firstresult' => null,
            ];
        }
        $found = self::determine();
        if ($found['since'] === null) {
            $found['since'] = $fallback ?? time();
            $found['source'] = 'upgrade';
        }
        set_config(self::CONFIG, $found['since'], 'local_catquiz');
        set_config(self::CONFIG_SOURCE, $found['source'], 'local_catquiz');
        return $found;
    }
}
