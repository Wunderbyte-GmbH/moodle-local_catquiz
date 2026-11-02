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
 * Which completed attempts of a CAT context may have been played with the pool of issue #157. Read-only, aggregated.
 *
 * Before 2026102407/06 the pool of a context could take the parameterless row of an item of another context for a
 * question calibrated in its own context: the question was played as a pilot, or with parameters it did not have in
 * the context. Every completed attempt keeps, per question, whether it was played as a pilot and the difficulty used
 * (local_catquiz_attempts.json, graphicalsummary_data). This audit compares that with the items of the attempt's
 * context as they are now: a question played as a pilot that has active parameters in the context, and a difficulty
 * that differs from them. A recalibration since the attempt changes the parameters as well - the audit names
 * candidates, it does not judge. Nothing is changed, nothing re-scored. Counts only, no person.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class attempt_pool_audit {
    /**
     * The audit of a context.
     *
     * @param int $contextid
     * @param int|null $scaleid Only attempts of this root scale.
     * @return array
     */
    public static function report(int $contextid, ?int $scaleid = null): array {
        global $DB;
        // The active parameters of the questions in the context, now.
        $active = [];
        $rows = $DB->get_recordset_sql(
            "SELECT lci.componentid, lcip.difficulty
               FROM {local_catquiz_items} lci
               JOIN {local_catquiz_itemparams} lcip ON lcip.id = lci.activeparamid AND lcip.itemid = lci.id
                    AND lcip.contextid = lci.contextid AND (lcip.componentid = lci.componentid OR COALESCE(lcip.componentid, 0) = 0)
              WHERE lci.contextid = :ctx",
            ['ctx' => $contextid]
        );
        foreach ($rows as $row) {
            $active[(int) $row->componentid][] = (float) $row->difficulty;
        }
        $rows->close();

        $where = 'contextid = :ctx AND endtime IS NOT NULL AND endtime > 0';
        $params = ['ctx' => $contextid];
        if ($scaleid) {
            $where .= ' AND scaleid = :scale';
            $params['scale'] = $scaleid;
        }
        $report = [
            'completed attempts of the context' => 0,
            '... with the questions recorded (graphicalsummary_data)' => 0,
            'questions played' => 0,
            '... as pilot' => 0,
            '... as pilot, with active parameters in the context now' => 0,
            '... productive, difficulty differs from the active parameters now' => 0,
            'attempts with at least one such question (candidates)' => 0,
        ];
        $attempts = $DB->get_recordset_select('local_catquiz_attempts', $where, $params, 'id', 'id, json');
        foreach ($attempts as $attempt) {
            $report['completed attempts of the context']++;
            $json = json_decode((string) $attempt->json, true);
            $questions = $json['graphicalsummary_data'] ?? null;
            if (!is_array($questions)) {
                continue;
            }
            $report['... with the questions recorded (graphicalsummary_data)']++;
            $candidate = false;
            foreach ($questions as $question) {
                if (!is_array($question) || empty($question['id'])) {
                    continue;
                }
                $report['questions played']++;
                $calibrated = $active[(int) $question['id']] ?? [];
                if (!empty($question['pilot'])) {
                    $report['... as pilot']++;
                    if ($calibrated) {
                        $report['... as pilot, with active parameters in the context now']++;
                        $candidate = true;
                    }
                    continue;
                }
                if (
                    $calibrated && isset($question['difficulty']) && is_numeric($question['difficulty'])
                    && !array_filter($calibrated, fn($d) => abs($d - (float) $question['difficulty']) < 0.00005)
                ) {
                    $report['... productive, difficulty differs from the active parameters now']++;
                    $candidate = true;
                }
            }
            if ($candidate) {
                $report['attempts with at least one such question (candidates)']++;
            }
        }
        $attempts->close();
        return $report;
    }
}
