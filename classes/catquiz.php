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
 * Entities Class to display list of entity records.
 *
 * @package local_catquiz
 * @author Thomas Winkler
 * @copyright 2021 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

/**
 * Class catquiz
 *
 * @author Georg Maißer
 * @copyright 2022 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catquiz {

    /**
     * entities constructor.
     */
    public function __construct() {

    }

    /**
     * Start a new attempt for a user.
     *
     * @param integer $userid
     * @param integer $categoryid
     * @return array
     */
    public static function start_new_attempt(int $userid, int $categoryid) {

        return [
            'attemptid' => 0
        ];
    }

    /**
     * Deal with result from the answered question.
     *
     * @param integer $attemptid
     * @param integer $questionid
     * @param integer $score
     * @return array
     */
    public static function submit_result(int $attemptid, int $questionid, int $score) {

        return [];
    }

    /**
     * Deliver next questionid for attempt.
     *
     * @param integer $attemptid
     * @param integer $quizid
     * @param string $component
     * @return array
     */
    public static function get_next_question(int $attemptid, int $quizid, string $component) {

        global $DB;

        $sql = "SELECT max(id)
                FROM {question}";

        $questionid = $DB->get_field_sql($sql);

        return [
            'questionid' => $questionid
        ];
    }

    /**
     * Returns the sql to get all the questions wanted.
     * @param array $where
     * @param array $filter
     * @return void
     */
    public static function return_sql_for_questions(array $where = [], array $filter = []) {
        $select = '*';
        $from = "( SELECT q.id, q.name, q.questiontext, q.qtype, qc.name as categoryname
            FROM {question} q
                JOIN {question_versions} qv ON q.id=qv.questionid
                JOIN {question_bank_entries} qbe ON qv.questionbankentryid=qbe.id
                JOIN {question_categories} qc ON qc.id=qbe.questioncategoryid
            ) as s1";
        $where = '1=1';
        $filter = '';

        return [$select, $from, $where, $filter];
    }
}
