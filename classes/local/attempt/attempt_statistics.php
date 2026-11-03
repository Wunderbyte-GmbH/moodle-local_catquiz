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
use stdClass;

/**
 * Key figures of an attempt for teachers: the tab "Statistics & export" (decision of 2026-10-14).
 *
 * The answers counted the way the former tab "Summary" counted them - each question once, from its
 * first administration (issue #125), pilot items left out and counted on their own - plus the time
 * the attempt took and the time per item. All read from the attempt itself, so the figures exist for
 * an invalid result as well.
 *
 * Time per item: from the moment the question was put into the attempt (its first step) to its last
 * step, for answered productive items only. An item without an answer has no time of its own.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class attempt_statistics {
    /**
     * The key figures of an attempt.
     *
     * @param int $adaptiveattemptid adaptivequiz_attempt.id
     * @param int[] $pilotids Ids of the questions played as pilot items.
     * @return stdClass right, wrong, partial, unanswered, pilots, pilot (the same four counts for the pilot
     *      items), duration (seconds or null), itemcount, itemmean, itemsd (seconds or null).
     */
    public static function for_attempt(int $adaptiveattemptid, array $pilotids): stdClass {
        global $DB;

        $pilotids = array_map('intval', $pilotids);
        $rows = catquiz::get_attempt_statistics($adaptiveattemptid) ?: [];
        $counts = self::count_answers($rows, $pilotids);

        $attempt = $DB->get_record('adaptivequiz_attempt', ['id' => $adaptiveattemptid], 'id, uniqueid, timecreated, timefinished');
        $duration = null;
        if ($attempt && !empty($attempt->timefinished) && !empty($attempt->timecreated)) {
            $duration = max(0, (int) $attempt->timefinished - (int) $attempt->timecreated);
        }

        $times = $attempt ? self::item_times((int) $attempt->uniqueid, $pilotids) : [];
        [$mean, $sd] = self::mean_and_sd($times);

        return (object) [
            'right' => $counts['right'],
            'wrong' => $counts['wrong'],
            'partial' => $counts['partial'],
            'unanswered' => $counts['unanswered'],
            'pilots' => $counts['pilots'],
            'pilot' => $counts['pilot'],
            'duration' => $duration,
            'itemcount' => count($times),
            'itemmean' => $mean,
            'itemsd' => $sd,
        ];
    }

    /**
     * Counts right, wrong, partially right and unanswered questions, and the pilot items apart.
     *
     * Each question once, from its first administration (issue #125): a question administered twice
     * by mistake has two rows, and counting both entered the same answer twice.
     *
     * @param stdClass[] $rows Question attempts with questionid and fraction, in slot order.
     * @param int[] $pilotids
     * @return array right, wrong, partial, unanswered, pilots, pilot (right, wrong, partial, unanswered of the
     *      pilot items).
     */
    public static function count_answers(array $rows, array $pilotids): array {
        $empty = ['right' => 0, 'wrong' => 0, 'partial' => 0, 'unanswered' => 0];
        $counts = $empty + ['pilots' => 0, 'pilot' => $empty];
        $seen = [];
        foreach ($rows as $row) {
            $questionid = (int) $row->questionid;
            if (isset($seen[$questionid])) {
                continue;
            }
            $seen[$questionid] = true;
            $category = self::category($row->fraction);
            if (in_array($questionid, $pilotids, true)) {
                $counts['pilots']++;
                $counts['pilot'][$category]++;
                continue;
            }
            $counts[$category]++;
        }
        return $counts;
    }

    /**
     * The category of an answer by its fraction.
     *
     * @param mixed $fraction Null when the question has no graded step.
     * @return string right, wrong, partial or unanswered
     */
    private static function category($fraction): string {
        if ($fraction === null) {
            return 'unanswered';
        }
        if ((float) $fraction >= 1.0) {
            return 'right';
        }
        return (float) $fraction <= 0.0 ? 'wrong' : 'partial';
    }

    /**
     * Seconds per answered productive item, each question once.
     *
     * @param int $usageid question_usages.id
     * @param int[] $pilotids
     * @return int[]
     */
    private static function item_times(int $usageid, array $pilotids): array {
        global $DB;

        $records = $DB->get_records_sql(
            "SELECT qa.id, qa.questionid, qa.slot,
                    MIN(qas.timecreated) AS firststep,
                    MAX(qas.timecreated) AS laststep,
                    MAX(CASE WHEN qas.fraction IS NOT NULL THEN 1 ELSE 0 END) AS graded
               FROM {question_attempts} qa
               JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
              WHERE qa.questionusageid = :usageid
           GROUP BY qa.id, qa.questionid, qa.slot
           ORDER BY qa.slot",
            ['usageid' => $usageid]
        );
        $times = [];
        $seen = [];
        foreach ($records as $record) {
            $questionid = (int) $record->questionid;
            if (isset($seen[$questionid])) {
                continue;
            }
            $seen[$questionid] = true;
            if (in_array($questionid, $pilotids, true) || empty($record->graded)) {
                continue;
            }
            $times[] = max(0, (int) $record->laststep - (int) $record->firststep);
        }
        return $times;
    }

    /**
     * Mean and sample standard deviation.
     *
     * @param int[] $values
     * @return array [mean or null, sd or null] - no standard deviation for fewer than two values.
     */
    public static function mean_and_sd(array $values): array {
        $n = count($values);
        if ($n === 0) {
            return [null, null];
        }
        $mean = array_sum($values) / $n;
        if ($n < 2) {
            return [$mean, null];
        }
        $sum = 0.0;
        foreach ($values as $value) {
            $sum += ($value - $mean) ** 2;
        }
        return [$mean, sqrt($sum / ($n - 1))];
    }

    /**
     * Seconds as minutes and seconds, e.g. "2:05 min".
     *
     * @param float|null $seconds
     * @return string "–" when unknown.
     */
    public static function format_seconds(?float $seconds): string {
        if ($seconds === null) {
            return '–';
        }
        $seconds = (int) round($seconds);
        return sprintf('%d:%02d min', intdiv($seconds, 60), $seconds % 60);
    }
}
