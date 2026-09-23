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
 * Removes retained attempt progress once the retention period has passed.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\task;

use core\task\scheduled_task;
use local_catquiz\local\progress_retention;
use local_catquiz\teststrategy\progress;

/**
 * Applies the configured retention period to local_catquiz_progress.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_attempt_progress extends scheduled_task {
    /** @var int Rows removed per run; keeps a large instance from hitting a timeout. */
    const BATCH = 500;

    /**
     * Returns the name shown in the scheduled task settings.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('cleanupprogress', 'local_catquiz');
    }

    /**
     * Removes rows older than the retention period.
     *
     * Idempotent: a second run finds nothing left to do. Rows of attempts that are
     * still running are never touched - only progress belonging to an attempt that
     * has ended may be removed, otherwise a learner would lose a running test.
     *
     * @return void
     */
    public function execute(): void {
        global $DB;

        $days = progress_retention::retention_days();
        $minimal = progress_retention::should_delete();

        if ($days <= 0 && !$minimal) {
            // Unlimited retention and nothing to sweep: saying so is cheaper than
            // scanning the table.
            return;
        }

        // In the data-sparing mode every finished attempt is swept, regardless of a
        // retention period - that is what "minimal" means. The deletion cannot happen
        // during finalisation itself: the feedback path loads the progress again
        // right afterwards, so a row removed there breaks the attempt.
        $cutoff = $minimal ? time() : time() - ($days * DAYSECS);

        $sql = "SELECT p.attemptid
                  FROM {local_catquiz_progress} p
                  JOIN {local_catquiz_attempts} a ON a.attemptid = p.attemptid
                 WHERE a.endtime IS NOT NULL
                   AND a.endtime < :cutoff";

        $removed = 0;
        while (true) {
            $attemptids = $DB->get_fieldset_sql($sql, ['cutoff' => $cutoff]);
            if (empty($attemptids)) {
                break;
            }

            foreach (array_slice($attemptids, 0, self::BATCH) as $attemptid) {
                // Through progress::delete() so the cache is cleared as well; a row
                // removed behind the cache's back would come back on the next read.
                progress::delete((int) $attemptid);
                $removed++;
            }

            if (count($attemptids) <= self::BATCH) {
                break;
            }
        }

        if ($removed > 0) {
            mtrace("local_catquiz: removed $removed expired attempt progress row(s).");
        }
    }
}
