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
 * The catquiz_base class.
 *
 * @package local_catquiz
 * @copyright 2024 Georg Maißer, <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\data;

/**
 * Get and store data from db.
 *
 * @package local_catquiz
 * @copyright 2024 Georg Maißer, <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catquiz_base {

    /**
     * Returns all or parts of answered questions in the Moodle instance.
     *
     * @param int $testid
     * @param int $userid
     * @param int $questionid
     * @return array
     */
    public static function get_question_results(int $testid = 0, int $userid = 0, int $questionid = 0): array {

        global $DB;

        $params = [];
        $sql = "SELECT qas.id,
                    qa.questionid,
                    qas.userid,
                    qas.fraction,
                    q.qtype,
                    qas.state,
                    qa.maxmark,
                    qa.maxfraction,
                    qa.minfraction,
                    qas.state,
                    qas.timecreated
                FROM {question_attempts} qa
                JOIN {question_attempt_steps} qas
                ON qas.questionattemptid = qa.id
                JOIN {question} q
                ON q.id=qa.questionid
                WHERE qas.fraction IS NOT NULL ";

        // Testid is for the moment used questionusageid. Will be cmid or test context id.
        if ($testid > 0) {
            $sql .= " AND qa.questionusageid = :testid ";
            $params['testid'] = $testid;
        }

        // Only provide data for a given user.
        if ($userid > 0) {
            $sql .= " AND qas.userid = :userid ";
            $params['userid'] = $userid;
        }

        // Only provide data for a given question.
        if ($questionid > 0) {
            $sql .= " AND q.id = :questionid ";
            $params['questionid'] = $questionid;
        }

        $records = $DB->get_records_sql($sql, $params);

        return $records;
    }

    /**
     * This Function returns the answered questions in an array...
     * ... where the userid is the key and every user has an array where the key is the questionid.
     *
     * @param int $testid
     * @param int $userid
     * @param int $questionid
     * @return array
     */
    public static function get_question_results_by_person(int $testid = 0, int $userid = 0, int $questionid = 0): array {

        $records = self::get_question_results($testid, $userid, $questionid);

        $returnarray = [];
        foreach ($records as $record) {

            if (!isset($returnarray[$record->userid])) {
                $returnarray[$record->userid] = [];
            }
            if (!isset($returnarray[$record->userid]['question'])) { // This is the component.
                $returnarray[$record->userid]['question'] = [];
            }
            if (!isset($returnarray[$record->userid]['question'][$record->questionid])) { // This is the component.
                $returnarray[$record->userid]['question'][$record->questionid] = [];
            }

            $response = [
                'fraction' => $record->fraction,
                'maxfraction' => $record->maxfraction,
                'minfraction' => $record->minfraction,
                'qtype' => $record->qtype,
                'timestamp' => $record->timecreated,
            ];

            $returnarray[$record->userid]['question'][$record->questionid][] = $response;
        }

        return $returnarray;
    }
}
