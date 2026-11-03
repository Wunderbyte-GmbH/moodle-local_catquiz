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

namespace local_catquiz\local\attempt;

use local_catquiz\catquiz;
use local_catquiz\event\progress_integrity_failed;
use moodle_exception;
use stdClass;

/**
 * Checks that a stored progress belongs to the CAT attempt it is attached to (issue #96).
 *
 * A progress row holds the running state of a test - played questions, answers, ability estimates,
 * settings. Taken over into someone else's attempt it changes their questions and estimates and
 * leaks another person's test. A row is accepted only if its CAT attempt exists and belongs to the
 * same person and component. Otherwise the runtime fails closed: nothing is taken over, repaired or
 * overwritten, and the mismatch is logged as an event for operations, without session data.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class progress_integrity {
    /** @var string The CAT attempt of the progress row does not exist. */
    public const NO_CAT_ATTEMPT = 'nocatattempt';

    /** @var string The progress row belongs to another person than its CAT attempt. */
    public const USER_MISMATCH = 'usermismatch';

    /** @var string The progress row belongs to another component than its CAT attempt. */
    public const COMPONENT_MISMATCH = 'componentmismatch';

    /**
     * Returns what is wrong with a progress row, or null if it belongs to its CAT attempt.
     *
     * @param stdClass $progress The row of local_catquiz_progress.
     * @param stdClass|null $catattempt Its row of local_catquiz_attempts, null if there is none.
     * @return string|null One of the constants, or null.
     */
    public static function check(stdClass $progress, ?stdClass $catattempt): ?string {
        if ($catattempt === null) {
            return self::NO_CAT_ATTEMPT;
        }
        if ((int) $progress->userid !== (int) $catattempt->userid) {
            return self::USER_MISMATCH;
        }
        // The component is stored in two spellings, adaptivequiz and mod_adaptivequiz.
        if (!in_array((string) $progress->component, catquiz::component_names((string) $catattempt->component), true)) {
            return self::COMPONENT_MISMATCH;
        }
        return null;
    }

    /**
     * Logs a mismatch for operations.
     *
     * @param stdClass $progress The row of local_catquiz_progress.
     * @param stdClass|null $catattempt Its row of local_catquiz_attempts, null if there is none.
     * @param string $reason One of the constants.
     * @param int $contextid The CAT context the progress was requested for.
     */
    public static function report(stdClass $progress, ?stdClass $catattempt, string $reason, int $contextid): void {
        progress_integrity_failed::create([
            'objectid' => (int) $progress->id,
            'context' => \context_system::instance(),
            'relateduserid' => $catattempt ? (int) $catattempt->userid : null,
            'other' => [
                'reason' => $reason,
                'progressid' => (int) $progress->id,
                'expectedcatattemptid' => (int) $progress->attemptid,
                'expecteduserid' => $catattempt ? (int) $catattempt->userid : null,
                'founduserid' => (int) $progress->userid,
                'expectedcomponent' => $catattempt ? (string) $catattempt->component : null,
                'foundcomponent' => (string) $progress->component,
                'contextid' => $contextid,
            ],
        ])->trigger();
    }

    /**
     * Logs a mismatch and stops: the test cannot go on with this state.
     *
     * @param stdClass $progress The row of local_catquiz_progress.
     * @param stdClass|null $catattempt Its row of local_catquiz_attempts, null if there is none.
     * @param string $reason One of the constants.
     * @param int $contextid The CAT context the progress was requested for.
     * @throws moodle_exception Always, with a generic message for the person taking the test.
     */
    public static function fail(stdClass $progress, ?stdClass $catattempt, string $reason, int $contextid): void {
        self::report($progress, $catattempt, $reason, $contextid);
        throw new moodle_exception(
            'progressintegrityerror',
            'local_catquiz',
            '',
            null,
            sprintf(
                'Progress %d does not belong to CAT attempt %d (%s): progress user %d, component %s.',
                (int) $progress->id,
                (int) $progress->attemptid,
                $reason,
                (int) $progress->userid,
                (string) $progress->component
            )
        );
    }

    /**
     * Finds progress rows that do not belong to their CAT attempt, for a health check.
     *
     * Read-only: nothing is changed, merged or deleted here.
     *
     * @return array Progress ids by reason - the three constants as keys.
     */
    public static function find_inconsistencies(): array {
        global $DB;

        $found = [self::NO_CAT_ATTEMPT => [], self::USER_MISMATCH => [], self::COMPONENT_MISMATCH => []];
        $rows = $DB->get_recordset_sql(
            'SELECT p.id, p.attemptid, p.userid, p.component,
                    a.id AS catid, a.userid AS catuserid, a.component AS catcomponent
               FROM {local_catquiz_progress} p
          LEFT JOIN {local_catquiz_attempts} a ON a.id = p.attemptid
           ORDER BY p.id'
        );
        foreach ($rows as $row) {
            $catattempt = $row->catid === null ? null
                : (object) ['userid' => $row->catuserid, 'component' => $row->catcomponent];
            $reason = self::check($row, $catattempt);
            if ($reason !== null) {
                $found[$reason][] = (int) $row->id;
            }
        }
        $rows->close();

        return $found;
    }
}
