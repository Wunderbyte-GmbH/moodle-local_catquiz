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

use local_catquiz\local\result\attempt_result_validator;
use local_catquiz\local\result\personparam_repository;
use local_catquiz\teststrategy\progress;
use stdClass;

/**
 * Judges a finalised attempt again by today's rules - deliberately and only on request (issue #141).
 *
 * Finalisation runs once and is never repeated by itself. An attempt finalised before a rule existed
 * - three of at least twelve questions answered, say - keeps the verdict of its time. This applies
 * the current validation to such an attempt, from what the attempt itself recorded: its estimates,
 * its answers and the test settings of its time, which the progress carries.
 *
 * Without the progress the answers are unknown; such an attempt is reported as not reconstructable
 * and left as it is - an unknown N would make every scale invalid, which is not a judgement but an
 * artefact. The cross-attempt "latest known state" of the person parameters is not rewound: later
 * attempts may have built on it.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class attempt_revalidator {
    /** @var string The attempt has no finalised CAT attempt. */
    public const NOT_FINALISED = 'not_finalised';

    /** @var string The answers of the attempt are no longer stored. */
    public const NO_PROGRESS = 'no_progress';

    /**
     * Revalidates one attempt.
     *
     * @param int $adaptiveattemptid adaptivequiz_attempt.id
     * @param bool $execute False reports only what would change.
     * @return stdClass attemptid, reconstructable, reason (when not), before, after (validity, null if unknown),
     *      changed, reasons (of the new verdict, comma separated).
     */
    public static function revalidate(int $adaptiveattemptid, bool $execute): stdClass {
        global $DB;

        $report = (object) [
            'attemptid' => $adaptiveattemptid,
            'reconstructable' => false,
            'reason' => '',
            'before' => null,
            'after' => null,
            'changed' => false,
            'reasons' => '',
        ];

        $catattempt = $DB->get_record('local_catquiz_attempts', ['attemptid' => $adaptiveattemptid]);
        if (!$catattempt || empty($catattempt->endtime)) {
            $report->reason = self::NOT_FINALISED;
            return $report;
        }
        if (progress::load_for_reading((int) $catattempt->id) === null) {
            $report->reason = self::NO_PROGRESS;
            return $report;
        }

        $resultvalid = $DB->get_field('adaptivequiz_attempt', 'resultvalid', ['id' => $adaptiveattemptid]);
        $report->before = $resultvalid === false || $resultvalid === null ? null : (bool) $resultvalid;

        $result = attempt_result_validator::validate($adaptiveattemptid);
        $report->reconstructable = true;
        $report->after = $result->is_valid();
        $report->changed = $report->before !== $report->after;
        $reasons = [];
        foreach ($result->get_scale_results() as $scaleresult) {
            array_push($reasons, ...$scaleresult->rejectionreasons);
        }
        $report->reasons = implode(',', array_unique($reasons));

        if (!$execute) {
            return $report;
        }

        $transaction = $DB->start_delegated_transaction();
        try {
            personparam_repository::save_attempt_result(
                (int) $catattempt->id,
                (int) $catattempt->userid,
                $catattempt->contextid !== null ? (int) $catattempt->contextid : null,
                $result,
                true
            );
            // As at finalisation; the activity reads its own result from here.
            if ($DB->get_manager()->field_exists('adaptivequiz_attempt', 'resultvalid')) {
                $DB->update_record('adaptivequiz_attempt', (object) [
                    'id' => $adaptiveattemptid,
                    'resultvalid' => $report->after ? 1 : 0,
                    'resultstatus' => $report->after ? 'valid' : 'invalid',
                ]);
            }
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            // Nothing of a failed attempt is kept, and the caller - a batch over many attempts, say -
            // is not left inside an open transaction that would take the rest down with it.
            $transaction->rollback($e);
        }

        return $report;
    }
}
