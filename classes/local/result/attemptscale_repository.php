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

use stdClass;

/**
 * Persistence for per-attempt, per-scale CAT results (Issue #9).
 *
 * Historical truth: one row per finalised attempt and successfully tested
 * scale, written exclusively by the finaliser after validation. Prior values
 * from earlier attempts serve only as start values; N, fraction and SE are
 * never accumulated across attempts.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class attemptscale_repository {
    /** @var string The table name. */
    private const TABLE = 'local_catquiz_attemptscale';

    /**
     * Persist the validated per-scale results of a finalised attempt.
     *
     * Writes exactly one row per scale that was measured in this attempt
     * (unique on catattemptid + catscaleid, so re-finalisation is idempotent).
     * Scales that only carry a prior value (not measured in this attempt) are
     * not written, so N/fraction/SE are never carried over.
     *
     * @param int $catattemptid local_catquiz_attempts.id
     * @param int $userid
     * @param int|null $contextid
     * @param attempt_result $result
     * @return void
     */
    public static function save_attempt_result(
        int $catattemptid,
        int $userid,
        ?int $contextid,
        attempt_result $result
    ): void {
        global $DB;

        $now = time();
        foreach ($result->get_scale_results() as $scaleresult) {
            // Only successfully tested (measured in this attempt) scales are
            // historised. Carry-over-only scales are not persisted here.
            if (!$scaleresult->measuredincurrentattempt) {
                continue;
            }

            $record = (object) [
                'catattemptid' => $catattemptid,
                'userid' => $userid,
                'contextid' => $contextid,
                'catscaleid' => $scaleresult->scaleid,
                'score' => $scaleresult->score,
                'standarderror' => $scaleresult->standarderror,
                'n' => $scaleresult->n,
                'fraction' => $scaleresult->fraction,
                'isprimary' => $scaleresult->primary ? 1 : 0,
                'isvalid' => $scaleresult->valid ? 1 : 0,
                'resultsource' => 'current',
                'validationstatus' => implode(',', $scaleresult->rejectionreasons),
                'timecreated' => $now,
            ];

            $existing = $DB->get_record(
                self::TABLE,
                ['catattemptid' => $catattemptid, 'catscaleid' => $scaleresult->scaleid],
                'id'
            );
            if ($existing) {
                $record->id = $existing->id;
                $DB->update_record(self::TABLE, $record);
            } else {
                $DB->insert_record(self::TABLE, $record);
            }
        }
    }

    /**
     * All result rows for a CATquiz attempt, indexed by scale id.
     *
     * @param int $catattemptid local_catquiz_attempts.id
     * @return stdClass[]
     */
    public static function get_for_attempt(int $catattemptid): array {
        global $DB;
        return $DB->get_records(self::TABLE, ['catattemptid' => $catattemptid], '', '*');
    }

    /**
     * The most recent valid result for a user/context/scale, used as the
     * carry-over start value for a following attempt.
     *
     * @param int $userid
     * @param int $contextid
     * @param int $catscaleid
     * @return stdClass|null
     */
    public static function get_latest_valid(int $userid, int $contextid, int $catscaleid): ?stdClass {
        global $DB;
        $records = $DB->get_records(
            self::TABLE,
            ['userid' => $userid, 'contextid' => $contextid, 'catscaleid' => $catscaleid, 'isvalid' => 1],
            'timecreated DESC, id DESC',
            '*',
            0,
            1
        );
        return $records ? reset($records) : null;
    }

    /**
     * The most recent valid primary scale for a user/context, used to
     * prioritise re-testing the last primary scale in a following attempt.
     *
     * @param int $userid
     * @param int $contextid
     * @return stdClass|null
     */
    public static function get_last_primary(int $userid, int $contextid): ?stdClass {
        global $DB;
        $records = $DB->get_records(
            self::TABLE,
            ['userid' => $userid, 'contextid' => $contextid, 'isprimary' => 1, 'isvalid' => 1],
            'timecreated DESC, id DESC',
            '*',
            0,
            1
        );
        return $records ? reset($records) : null;
    }
}
