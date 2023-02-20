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

namespace local_catquiz\data;

/**
 * Get and store data from db.
 */
class catquiz_base {

    /**
     * Returns all or parts of answered questions in the Moodle instance.
     *
     * @param integer $testid
     * @param integer $userid
     * @param integer $questionid
     * @return array
     */
    public static function get_question_results(int $testid = 0, int $userid = 0, int $questionid = 0):array {

        global $DB;

        $params = [];
        $sql = "SELECT qas.id, qa.questionid, qas.userid, qas.fraction, q.qtype, qas.state, qa.maxmark, qa.maxfraction, qa.minfraction
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
            $sql .= " AND qa.userid = :userid ";
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
}
