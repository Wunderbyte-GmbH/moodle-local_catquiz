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
     * @param array $wherearray
     * @param array $filterarray
     * @return void
     */
    public static function return_sql_for_addquestions(array $wherearray = [], array $filterarray = []) {

        global $DB;

        $select = '*';
        $from = "( SELECT q.id, q.name, q.questiontext, q.qtype, qc.name as categoryname
            FROM {question} q
                JOIN {question_versions} qv ON q.id=qv.questionid
                JOIN {question_bank_entries} qbe ON qv.questionbankentryid=qbe.id
                JOIN {question_categories} qc ON qc.id=qbe.questioncategoryid
            ) as s1";

        $where = '1=1';
        $filter = '';

        foreach ($wherearray as $key => $value) {
            $where .= ' AND ' . $DB->sql_equal($key, $value, false, false);
        }

        return [$select, $from, $where, $filter];
    }

    /**
     * Returns the sql to get all the questions wanted.
     * @param int $catscaleid
     * @param array $wherearray
     * @param array $filterarray
     * @return void
     */
    public static function return_sql_for_catscalequestions(int $catscaleid, array $wherearray = [], array $filterarray = []) {

        global $DB;

        $params = [];
        $select = ' DISTINCT *';
        $from = "( SELECT q.id, qbe.idnumber, q.name, q.questiontext, q.qtype, qc.name as categoryname, lci.catscaleid catscaleid, lci.componentname component
            FROM {question} q
                JOIN {question_versions} qv ON q.id=qv.questionid
                JOIN {question_bank_entries} qbe ON qv.questionbankentryid=qbe.id
                JOIN {question_categories} qc ON qc.id=qbe.questioncategoryid
                LEFT JOIN {local_catquiz_items} lci ON lci.componentid=q.id AND lci.componentname='question'
            ) as s1";

        $where = ' catscaleid = :catscaleid ';
        $params['catscaleid'] = $catscaleid;
        $filter = '';

        foreach ($wherearray as $key => $value) {
            $where .= ' AND ' . $DB->sql_equal($key, $value, false, false);
        }

        return [$select, $from, $where, $filter, $params];
    }

    /**
     * Returns the sql to get all the questions wanted.
     * @param int $catscaleid
     * @param array $wherearray
     * @param array $filterarray
     * @return void
     */
    public static function return_sql_for_addcatscalequestions(int $catscaleid, array $wherearray = [], array $filterarray = []) {

        global $DB;

        $params = [];
        $select = 'DISTINCT id, idnumber, name, questiontext, qtype, categoryname, \'question\' as component';
        $from = "( SELECT q.id, qbe.idnumber, q.name, q.questiontext, q.qtype, qc.name as categoryname, " .
             $DB->sql_group_concat($DB->sql_concat("'-'", 'lci.catscaleid', "'-'")) ." as catscaleids
            FROM {question} q
                JOIN {question_versions} qv ON q.id=qv.questionid
                JOIN {question_bank_entries} qbe ON qv.questionbankentryid=qbe.id
                JOIN {question_categories} qc ON qc.id=qbe.questioncategoryid
                LEFT JOIN {local_catquiz_items} lci ON lci.componentid=q.id AND lci.componentname='question'
                GROUP BY q.id, qbe.idnumber, q.name, q.questiontext, q.qtype, qc.name
            ) as s1";

        $where = " ( " . $DB->sql_like('catscaleids', ':catscaleid', false, false, true) . ' OR catscaleids IS NULL ) ';
        $params['catscaleid'] = "%-$catscaleid-%";
        $filter = '';

        foreach ($wherearray as $key => $value) {
            $where .= ' AND ' . $DB->sql_equal($key, $value, false, false);
        }

        return [$select, $from, $where, $filter, $params];
    }

    /**
     * Return the sql for all questions answered.
     *
     * @param integer $testitemid
     * @return void
     */
    public static function get_sql_for_questions_answered(int $testitemid = 0) {

        $and = "";
        $params = [];

        if (!empty($testitemid)) {
            $and = " AND qa.questionid=:questionid ";
            $params = ['questionid' => $testitemid];
        }

        $sql = "SELECT COUNT(qas.id)
        FROM {question_attempt_steps} qas
        JOIN {question_attempts} qa ON qas.questionattemptid=qa.id
        WHERE qas.fraction IS NOT NULL"
        . $and;
        $params = ['questionid' => $testitemid];

        return [$sql, $params];
    }

    /**
     * Return the sql for all questions answered.
     *
     * @param integer $testitemid
     * @return void
     */
    public static function get_sql_for_questions_average(int $testitemid = 0) {

        $and = "";
        $params = [];

        if (!empty($testitemid)) {
            $and = " AND qa.questionid=:questionid ";
            $params = ['questionid' => $testitemid];
        }

        $sql = "SELECT AVG(qas.fraction)
        FROM {question_attempt_steps} qas
        JOIN {question_attempts} qa ON qas.questionattemptid=qa.id"
        . $and;
        $params = ['questionid' => $testitemid];

        return [$sql, $params];
    }

    /**
     * Return the sql for all questions answered.
     *
     * @param integer $testitemid
     * @return void
     */
    public static function get_sql_for_questions_answered_correct(int $testitemid = 0) {

        $and = "";
        $params = [];

        if (!empty($testitemid)) {
            $and = " AND qa.questionid=:questionid ";
            $params = ['questionid' => $testitemid];
        }

        $sql = "SELECT COUNT(qas.id)
        FROM {question_attempt_steps} qas
        JOIN {question_attempts} qa ON qas.questionattemptid=qa.id
        WHERE qas.fraction = qa.maxfraction"
        . $and;
        $params = ['questionid' => $testitemid];

        return [$sql, $params];
    }

    /**
     * Return the sql for all questions answered.
     *
     * @param integer $testitemid
     * @return void
     */
    public static function get_sql_for_questions_answered_incorrect(int $testitemid = 0) {

        $and = "";
        $params = [];

        if (!empty($testitemid)) {
            $and = " AND qa.questionid=:questionid ";
            $params = ['questionid' => $testitemid];
        }

        $sql = "SELECT COUNT(qas.id)
        FROM {question_attempt_steps} qas
        JOIN {question_attempts} qa ON qas.questionattemptid=qa.id
        WHERE qas.fraction = qa.minfraction"
        . $and;
        $params = ['questionid' => $testitemid];

        return [$sql, $params];
    }

    /**
     * Return the sql for all questions answered.
     *
     * @param integer $testitemid
     * @return void
     */
    public static function get_sql_for_questions_answered_partlycorrect(int $testitemid = 0) {

        $and = "";
        $params = [];

        if (!empty($testitemid)) {
            $and = " AND qa.questionid=:questionid ";
            $params = ['questionid' => $testitemid];
        }

        $sql = "SELECT COUNT(qas.id)
        FROM {question_attempt_steps} qas
        JOIN {question_attempts} qa ON qas.questionattemptid=qa.id
        WHERE qas.fraction <> qa.minfraction
        AND qas.fraction <> qa.maxfraction "
        . $and;
        $params = ['questionid' => $testitemid];

        return [$sql, $params];
    }

    /**
     * Return the sql for all questions answered.
     *
     * @param integer $testitemid
     * @return void
     */
    public static function get_sql_for_questions_answered_by_distinct_persons(int $testitemid = 0) {

        $and = "";
        $params = [];

        if (!empty($testitemid)) {
            $and = " AND qa.questionid=:questionid ";
            $params = ['questionid' => $testitemid];
        }

        $sql = "SELECT COUNT(s1.questionid)
        FROM (
            SELECT qas.userid, qa.questionid
            FROM {question_attempt_steps} qas
            JOIN {question_attempts} qa ON qas.questionattemptid=qa.id
            WHERE qas.fraction IS NOT NULL"
            . $and .
            "GROUP BY qa.questionid, qas.userid)
        as s1";

        return [$sql, $params];
    }

    /**
     * Return the sql for all questions answered.
     *
     * @param integer $testitemid
     * @return void
     */
    public static function get_sql_for_questions_usages_in_tests(int $testitemid = 0) {

        $and = "";
        $params = [];

        if (!empty($testitemid)) {
            $and = " AND qa.questionid=:questionid ";
            $params = ['questionid' => $testitemid];
        }

        $sql = "SELECT COUNT(s1.questionid)
        FROM (
            SELECT qa.questionid, qu.contextid
            FROM {question_attempt_steps} qas
            JOIN {question_attempts} qa ON qas.questionattemptid=qa.id
            JOIN {question_usages} qu ON qa.questionusageid=qu.id
            WHERE qas.fraction IS NOT NULL"
            . $and .
            "GROUP BY qa.questionid, qu.contextid)
        as s1";

        return [$sql, $params];
    }

    /**
     * Return sql to render all or a subset of testenvironments
     *
     * @return array
     */
    public static function return_sql_for_testenvironments(
        array $wherearray = [],
        array $filterarray = []) {

        $params = [];
        $where = [];
        $filter = '';

        $select = "*";
        $from = "{local_catquiz_tests}";
        $where = "1=1";

        return [$select, $from, $where, $filter, $params];
    }
}
