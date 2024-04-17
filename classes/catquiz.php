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
 * Catquiz class.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use core\check\result;
use dml_exception;
use local_catquiz\event\usertocourse_enroled;
use local_catquiz\event\usertogroup_enroled;
use moodle_exception;
use moodle_url;
use question_attempt;
use question_attempt_pending_step;
use question_bank;
use question_engine;
use question_finder;
use question_state_gradedwrong;
use stdClass;

/**
 * Class catquiz
 *
 * @author Georg Maißer
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catquiz {

    /**
     * Entities constructor.
     */
    public function __construct() {

    }

    /**
     * Start a new attempt for a user.
     *
     * @param int $userid
     * @param int $categoryid
     * @return array
     */
    public static function start_new_attempt(int $userid, int $categoryid) {

        return [
            'attemptid' => 0,
        ];
    }

    /**
     * Deal with result from the answered question.
     *
     * @return array
     */
    public static function submit_result() {

        return [];
    }

    /**
     * Deliver next questionid for attempt.
     *
     * @return array
     */
    public static function get_next_question() {

        global $DB;

        $sql = "SELECT max(id)
                FROM {question}";

        $questionid = $DB->get_field_sql($sql);

        return [
            'questionid' => $questionid,
        ];
    }

    /**
     * Returns the sql to get all the questions wanted.
     * @param array $wherearray
     * @return array
     */
    public static function return_sql_for_addquestions(array $wherearray = []) {

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
     *
     * @param array $catscaleids
     * @param int $contextid
     * @param array $wherearray
     * @param int $userid
     * @param ?string $orderby If given, order by the given field in ascending order
     *
     * @return array
     *
     */
    public static function return_sql_for_catscalequestions(
        array $catscaleids,
        int $contextid,
        array $wherearray = [],
        int $userid = 0,
        ?string $orderby = null
    ) {

        global $DB;
        if ($contextid === 0) {
            $contextid = self::get_default_context_id();
        }

        // Start the params array.
        $params = [
            'contextid' => $contextid,
            'contextid2' => $contextid,
        ];

        // If we fetch only for a given user, we need to add this to the sql.
        if (!empty($userid)) {
            $restrictforuser = " AND qas.userid = :userid ";
            $params['userid'] = $userid;
        }

        $insql = '';
        if (!empty($catscaleids) && $catscaleids[0] > 0) {
            [$insql, $inparams] = $DB->get_in_or_equal($catscaleids, SQL_PARAMS_NAMED, 'incatscales');
            $params = array_merge($params, $inparams);
            $insql = " WHERE catscaleid $insql ";
        }

        $select = 'DISTINCT *';
        $from = "( SELECT s1.*, s5.model, s5.difficulty, s5.discrimination, s5.guessing,
                    s5.timecreated, s5.timemodified, s5.status
            FROM (
            SELECT
                q.id,
                lci.componentid,
                qbe.idnumber as label,
                qbe.idnumber,
                q.name,
                q.questiontext,
                q.qtype,
                qc.name as categoryname,
                lci.catscaleid catscaleid,
                lci.status testitemstatus,
                lci.componentname component,
                lci.id as itemid,
                lccs.name as catscalename,
                s2.attempts,
                COALESCE(s2.lastattempttime,0) as lastattempttime,
                s3.userattempts,
                COALESCE(s3.userlastattempttime,0) as userlastattempttime
            FROM {question} q

            JOIN {question_versions} qv
            ON q.id=qv.questionid

            LEFT JOIN {question_bank_entries} qbe
            ON qv.questionbankentryid=qbe.id

            LEFT JOIN {question_categories} qc
            ON qc.id=qbe.questioncategoryid

            RIGHT JOIN {local_catquiz_items} lci
            ON lci.componentid=q.id AND lci.componentname='question'

            LEFT JOIN {local_catquiz_catscales} lccs
            ON lci.catscaleid = lccs.id

            LEFT JOIN (
                SELECT ccc1.id AS contextid, qa.questionid, COUNT(*) AS attempts, MAX(qas.timecreated) as lastattempttime
                FROM {local_catquiz_catcontext} ccc1

                JOIN {question_attempt_steps} qas
                ON ccc1.starttimestamp < qas.timecreated
                AND ccc1.endtimestamp > qas.timecreated
                AND qas.fraction IS NOT NULL

                JOIN {question_attempts} qa
                ON qas.questionattemptid = qa.id

                WHERE ccc1.id = :contextid
                GROUP BY ccc1.id, qa.questionid
            ) s2
            ON q.id = s2.questionid

            LEFT JOIN (
                SELECT
                    qa.questionid,
                    ccc1.id AS contextid
                    COUNT(*) AS userattempts,
                    MAX(qas.timecreated) as userlastattempttime
                FROM {local_catquiz_catcontext} ccc1
                        JOIN {question_attempt_steps} qas
                            ON ccc1.starttimestamp < qas.timecreated AND ccc1.endtimestamp > qas.timecreated
                                AND qas.fraction IS NOT NULL

                        JOIN {question_attempts} qa
                            ON qas.questionattemptid = qa.id
                WHERE ccc1.id = :contextid2
                GROUP BY ccc1.id, qa.questionid
            ) s3
            ON q.id = s3.questionid

            ) as s1
            LEFT JOIN (
                SELECT
                    maxlcip.componentid,
                    maxlcip.componentname,
                    maxlcip.model,
                    maxlcip.difficulty,
                    maxlcip.discrimination,
                    maxlcip.guessing,
                    s4.timecreated,
                    maxlcip.timemodified,
                    s4.status
                FROM (
                    SELECT lcip.*,
                        ROW_NUMBER() OVER (PARTITION BY componentid,
                        componentname ORDER BY lcip.status DESC,
                        lcip.timecreated DESC) AS n
                    FROM {local_catquiz_itemparams} lcip
                ) AS s4
                JOIN {local_catquiz_itemparams} maxlcip
                ON s4.id = maxlcip.id
                WHERE n = 1
            ) AS s5
            ON s5.componentid = s1.id
            AND s5.componentname = s1.component
            $insql
        ) AS s6";

        $where = '1=1';

        $filter = '';

        foreach ($wherearray as $key => $value) {
            $where .= ' AND ' . $DB->sql_equal($key, $value, false, false);
        }

        if ($orderby) {
            $where .= " ORDER BY $orderby ASC";
        }

        return [$select, $from, $where, $filter, $params];
    }

    /**
     * Returns the sql to get all the questions wanted.
     *
     * @param int $catscaleid
     * @param int $contextid
     * @param array $wherearray
     *
     * @return array
     *
     */
    public static function return_sql_for_addcatscalequestions(
        int $catscaleid,
        int $contextid,
        array $wherearray = []
    ) {
        global $DB;
        $contextfilter = $contextid === 0
            ? $DB->sql_like('ccc1.json', ':default')
            : "ccc1.id = :contextid";

        list(, $contextfrom, , ) = self::get_sql_for_stat_base_request();
        $params = [];
        $select = '
            DISTINCT
                id,
                idnumber,
                name,
                questiontext,
                qtype,
                categoryname,
                \'question\' as component,
                contextattempts as questioncontextattempts,
                catscaleids
            ';
        $from = "( SELECT q.id, qbe.idnumber, q.name, q.questiontext, q.qtype, qc.name as categoryname, s2.contextattempts," .
             $DB->sql_group_concat($DB->sql_concat("'-'", 'lci.catscaleid', "'-'")) ." as catscaleids
            FROM {question} q
                JOIN (
                    SELECT *
                    FROM (
                        SELECT *, ROW_NUMBER() OVER (PARTITION BY questionbankentryid ORDER BY version DESC) AS n
                        FROM {question_versions}
                    ) s2
                    WHERE n = 1
                ) qv
                ON q.id=qv.questionid
                JOIN {question_bank_entries} qbe ON qv.questionbankentryid=qbe.id
                JOIN {question_categories} qc ON qc.id=qbe.questioncategoryid
                LEFT JOIN {local_catquiz_items} lci ON lci.componentid=q.id AND lci.componentname='question'
                LEFT JOIN (
                    SELECT ccc1.id AS contextid, qa.questionid, COUNT(*) AS contextattempts
                    FROM $contextfrom
                    WHERE $contextfilter
                    GROUP BY ccc1.id, qa.questionid
                ) s2 ON q.id = s2.questionid
                GROUP BY q.id, qbe.idnumber, q.name, q.questiontext, q.qtype, qc.name, s2.contextattempts
            ) as s1";

        $where = " ( " . $DB->sql_like('catscaleids', ':catscaleid', false, false, true) . ' OR catscaleids IS NULL ) ';
        $params['catscaleid'] = "%-$catscaleid-%";
        $params['contextid'] = $contextid;
        $params['default'] = '%"default":true%';
        $filter = '';

        foreach ($wherearray as $key => $value) {
            $where .= ' AND ' . $DB->sql_equal($key, $value, false, false);
        }

        return [$select, $from, $where, $filter, $params];
    }

    /**
     * Return the sql for all questions answered.
     *
     * @param array $testitemids
     * @param array $contextids
     * @param array $studentids
     *
     * @return array
     *
     */
    public static function get_sql_for_questions_answered(
        array $testitemids = [],
        array $contextids = [],
        array $studentids = []
        ) {
        list (, $from, $where, $params) = self::get_sql_for_stat_base_request($testitemids, $contextids, $studentids);

        $sql = "SELECT COUNT(qas.id)
        FROM $from
        WHERE $where";

        return [$sql, $params];
    }

    /**
     * Return the sql for all questions answered.
     *
     * @param array $testitemids
     * @param array $contextids
     *
     * @return array
     *
     */
    public static function get_sql_for_questions_average(array $testitemids = [], array $contextids = []) {
        list (, $from, $where, $params) = self::get_sql_for_stat_base_request($testitemids, $contextids);

        $sql = "SELECT AVG(qas.fraction)
        FROM $from
        WHERE $where";

        return [$sql, $params];
    }

    /**
     * Return the sql for all questions answered.
     *
     * @param array $testitemids
     * @param array $contextids
     * @param array $studentids
     *
     * @return array
     *
     */
    public static function get_sql_for_questions_answered_correct(
        array $testitemids = [],
        array $contextids = [],
        array $studentids = []
    ) {
        list(, $from, $where, $params) = self::get_sql_for_stat_base_request($testitemids, $contextids, $studentids);

        $sql = "SELECT COUNT(qas.id)
        FROM $from
        WHERE $where
        AND qas.fraction = qa.maxfraction";

        return [$sql, $params];
    }

    /**
     * Return the sql for all questions answered.
     *
     * @param array $testitemids
     * @param array $contextids
     * @param array $studentids
     *
     * @return array
     *
     */
    public static function get_sql_for_questions_answered_incorrect(
        array $testitemids = [],
        array $contextids = [],
        array $studentids = []
    ) {
        list($select, $from, $where, $params) = self::get_sql_for_stat_base_request($testitemids, $contextids, $studentids);

        $sql = "SELECT COUNT(qas.id)
        FROM $from
        WHERE $where
        AND qas.fraction = qa.minfraction";

        return [$sql, $params];
    }

    /**
     * Return the sql for all questions answered.
     *
     * @param array $testitemids
     * @param array $contextids
     *
     * @return array
     *
     */
    public static function get_sql_for_questions_answered_partlycorrect(array $testitemids = [], array $contextids = []) {
        list (, $from, $where, $params) = self::get_sql_for_stat_base_request($testitemids, $contextids);

        $sql = "SELECT COUNT(qas.id)
        FROM $from
        WHERE $where
        AND qas.fraction <> qa.minfraction
        AND qas.fraction <> qa.maxfraction";

        return [$sql, $params];
    }

    /**
     * Return the sql for all questions answered.
     *
     * @param array $testitemids
     * @param array $contextids
     *
     * @return array
     *
     */
    public static function get_sql_for_questions_answered_by_distinct_persons(array $testitemids = [], array $contextids = []) {

        list (, $from, $where, $params) = self::get_sql_for_stat_base_request($testitemids, $contextids);

        $sql = "SELECT COUNT(s1.questionid)
        FROM (
            SELECT qas.userid, qa.questionid
            FROM $from
            WHERE $where
            GROUP BY qa.questionid, qas.userid)
        as s1";

        return [$sql, $params];
    }


    /**
     * Returns the sql that can be used to get input data to get item list.
     *
     * @param mixed $contextid
     * @param array $catscaleids
     * @param ?int $testitemid
     * @param ?int $userid
     *
     * @return array
     *
     */
    public static function get_sql_for_model_input($contextid, array $catscaleids, ?int $testitemid, ?int $userid) {
        global $DB;
        $testitemids = $testitemid ? [$testitemid] : [];
        $userids = $userid ? [$userid] : [];
        [$insql, $inparams] = $DB->get_in_or_equal(
            $catscaleids,
            SQL_PARAMS_NAMED,
            'incatscales'
        );
        list (, $from, $where, $params) = self::get_sql_for_stat_base_request($testitemids, [$contextid], $userids);

        $sql = "
        SELECT " . $DB->sql_concat("qas.id", "'-'", "qas.userid", "'-'", "q.id", "'-'", "lci.id") .
        " AS uniqueid, qas.id, qas.userid, qa.questionid, qas.fraction, qa.minfraction, qa.maxfraction, q.qtype, qas.timecreated
        FROM $from
        JOIN {question} q
            ON qa.questionid = q.id
        JOIN {local_catquiz_items} lci
            ON q.id = lci.componentid
            AND lci.catscaleid $insql
        WHERE $where
        ";

        return [$sql, array_merge($inparams, $params)];
    }

    /**
     * Returns the last question that was answered in the current quiz attempt or false
     *
     * @param int $questionusageid
     * @return stdClass|bool
     */
    public static function get_last_response_for_attempt(int $questionusageid) {
        global $DB;
        $sql = <<<SQL
        SELECT * FROM {question_attempt_steps} qs
        JOIN {question_attempts} qa ON qs.questionattemptid = qa.id
        AND qa.id = (
            SELECT max(questionattemptid) maxwithresponse
            FROM {question_attempt_steps} qs
                     JOIN (SELECT *
                           FROM {question_attempts}
                           WHERE questionusageid = :questionusageid
            ) sub1 ON qs.questionattemptid = sub1.id
            WHERE fraction IS NOT NULL
            GROUP BY questionusageid
        ) AND fraction IS NOT NULL
        SQL;
        return $DB->get_record_sql($sql, ['questionusageid' => $questionusageid]);
    }

    /**
     * Shows if a user gave up a question
     *
     * @param int $questionusageid
     * @param int $questionid
     * @return bool
     */
    public static function user_gave_up_question(int $questionusageid, int $questionid): bool {
        global $DB;
        $sql = <<<SQL
        SELECT * FROM {question_attempts} qa
        JOIN {question_attempt_steps} qas ON qa.id = qas.questionattemptid
        WHERE qa.questionusageid = :questionusageid
        AND qa.questionid = :questionid
        AND qas.state = 'gaveup'
        SQL;
        return $DB->record_exists_sql(
            $sql,
            [
                'questionusageid' => $questionusageid,
                'questionid' => $questionid,
            ]
        );
    }

    /**
     * Returns the SQL to retrieve the number of new responses.
     *
     * Returns the number of new responses since $lastcalclation for a CAT
     * context and a list of CAT scales.
     *
     * @param int   $contextid
     * @param array $catscaleids
     * @param int   $lastcalculation
     *
     * @return array
     */
    public static function get_sql_for_new_responses(int $contextid, array $catscaleids, int $lastcalculation) {
        global $DB;
        [$insql, $inparams] = $DB->get_in_or_equal(
            $catscaleids,
            SQL_PARAMS_NAMED,
            'incatscales'
        );
        list (, $from, $where, $params) = self::get_sql_for_stat_base_request([], [$contextid]);

        $sql = "
        SELECT COUNT(*)
        FROM $from
        JOIN {question} q
            ON qa.questionid = q.id
        JOIN {local_catquiz_items} lci
            ON q.id = lci.componentid
            AND lci.catscaleid $insql
        WHERE $where
        AND qas.timecreated >= :lastcalculation
        ";

        return [
            $sql,
            array_merge(
                $inparams,
                $params,
                ['lastcalculation' => $lastcalculation]
            ),
        ];
    }

    /**
     * Return the sql for all questions answered.
     *
     * @param array $testitemids
     * @param array $contextids
     * @param array $studentids
     *
     * @return array
     *
     */
    public static function get_sql_for_questions_usages_in_tests(
        array $testitemids = [],
        array $contextids = [],
        array $studentids = []
    ) {
        list(, $from, $where, $params) = self::get_sql_for_stat_base_request($testitemids, $contextids, $studentids);

        $sql = "SELECT COUNT(s1.questionid)
        FROM (
            SELECT qa.questionid, qu.contextid
            FROM $from
            JOIN {question_usages} qu ON qa.questionusageid=qu.id
            WHERE $where
            AND qas.fraction IS NOT NULL
            GROUP BY qa.questionid, qu.contextid)
        as s1";

        return [$sql, $params];
    }

    /**
     * Basefunction to fetch all questions in context.
     *
     * @param array $testitemids
     * @param array $contextids
     * @param array $studentids
     *
     * @return array
     *
     */
    private static function get_sql_for_stat_base_request(
        array $testitemids = [],
        array $contextids = [],
        array $studentids = []
    ): array {
        $select = '*';
        $from = '{local_catquiz_catcontext} ccc1
                JOIN {question_attempt_steps} qas
                    ON ccc1.starttimestamp < qas.timecreated
                    AND ccc1.endtimestamp > qas.timecreated
                    AND qas.fraction IS NOT NULL
                JOIN {question_attempts} qa
                    ON qas.questionattemptid = qa.id';
        $where = !empty($testitemids) ? 'qa.questionid IN (:testitemids)' : '1=1';
        $where .= !empty($contextids) ? ' AND ccc1.id IN (:contextids)' : '';
        $where .= !empty($studentids) ? ' AND userid IN (:studentids)' : '';

        $testitemidstring = sprintf("%s", implode(',', $testitemids));
        $contextidstring = sprintf("%s", implode(',', $contextids));
        $studentidstring = sprintf("%s", implode(',', $studentids));

        $params = self::set_optional_param([], 'testitemids', $testitemids, $testitemidstring);
        $params = self::set_optional_param($params, 'contextids', $contextids, $contextidstring);
        $params = self::set_optional_param($params, 'studentids', $studentids, $studentidstring);

        return [$select, $from, $where, $params];
    }

    /**
     * Set optional param.
     *
     * @param array $params
     * @param string $name
     * @param array $originalvalue
     * @param string $sqlstringval
     *
     * @return array
     *
     */
    private static function set_optional_param($params, $name, $originalvalue, $sqlstringval) {
        if (!empty($originalvalue)) {
            $params[$name] = $sqlstringval;
        }
        return $params;
    }

    /**
     * Return sql to render all or a subset of testenvironments
     *
     * @param int $catscaleid
     * @param array $filterarray
     *
     * @return array
     *
     */
    public static function return_sql_for_testenvironments(
        int $catscaleid = 0,
        array $filterarray = []) {
        global $DB;
        $params = [];
        $filter = '';

        $select = " * ";

        $from = "
        ( SELECT
            ct.id,
            name,
            component,
            c.visible,
            availability,
            c.lang,
            status,
            parentid,
            fullname,
            c.timemodified,
            c.timecreated,
            ct.catscaleid,
            numberofitems,
            teachers
        FROM {local_catquiz_tests} ct
        JOIN {course} c ON c.id = ct.courseid
        LEFT JOIN (SELECT catscaleid as itemcatscale, COUNT(*) AS numberofitems
           FROM {local_catquiz_items}
           GROUP BY catscaleid
        ) s1 ON ct.catscaleid = s1.itemcatscale
        LEFT JOIN (
            SELECT c.id AS courseid, " .
                $DB->sql_group_concat($DB->sql_concat_join("' '", ['u.firstname', 'u.lastname']), ', ') . " AS teachers
            FROM {user} u
            JOIN {role_assignments} ra ON ra.userid = u.id
            JOIN {context} ct ON ct.id = ra.contextid
            JOIN {course} c ON c.id = ct.instanceid
            JOIN {role} r ON r.id = ra.roleid
            WHERE r.shortname IN ('teacher', 'editingteacher')
            GROUP BY c.id
            ) s2 ON s2.courseid = ct.courseid
            ) s3";

        $where = "1=1";
        $filter = '';

        if (!empty($catscaleid)) {
            $where .= ' AND catscaleid =:catscaleid';
            $params['catscaleid'] = $catscaleid;
        }

        return [$select, $from, $where, $filter, $params];
    }

    /**
     * Return sql to render quiz attempts.
     *
     *
     * @return array
     *
     */
    public static function return_sql_for_quizattempts() {
        $params = [];
        $filter = '';

        $select = "
            *
        ";

        $from = "(
            SELECT
                lca.id AS id,
                lca.attemptid as attemptid,
                lca.timecreated AS timecreated,
                lca.timemodified AS timemodified,
                u.username AS username,
                lcc.name AS catscale,
                lccc.name AS catcontext,
                c.fullname AS course,
                lca.component AS component,
                lct.name AS instance,
                lca.teststrategy,
                lca.status,
                lca.total_number_of_testitems,
                lca.number_of_testitems_used,
                lca.personability_before_attempt,
                lca.personability_after_attempt,
                lca.starttime,
                lca.endtime
                FROM {local_catquiz_attempts} lca
                JOIN {user} u ON lca.userid = u.id
                JOIN {local_catquiz_catscales} lcc ON lca.scaleid = lcc.id
                JOIN {local_catquiz_catcontext} lccc ON lca.contextid = lccc.id
                JOIN {course} c ON lca.courseid = c.id
                JOIN {local_catquiz_tests} lct ON lca.instanceid = lct.componentid
            ) as s1
        ";

        return [$select, $from, "1=1", $filter, $params];
    }

    /**
     * Return sql to render quiz attempts.
     *
     * @param int $numberofrecords
     * @param int $instanceid
     * @param int $courseid
     * @param int $userid
     *
     * @return mixed
     *
     */
    public static function return_data_from_attemptstable(
        int $numberofrecords = 1,
        int $instanceid = 0,
        int $courseid = 0,
        int $userid = -1) {

        global $DB;

        $sqlarray = self::return_sql_for_attemptid_contextid_json($numberofrecords, $instanceid, $courseid, $userid);

        $recordsarray = $DB->get_records_sql($sqlarray[0], $sqlarray[1]);

        return $recordsarray;
    }

    /**
     * Summary of return_sql_for_attemptid_contextid_json
     * @param int $numberofrecords
     * @param int $instanceid
     * @param int $courseid
     * @param int $userid
     * @return array
     */
    private static function return_sql_for_attemptid_contextid_json(
        int $numberofrecords = 1,
        int $instanceid = 0,
        int $courseid = 0,
        int $userid = -1): array {

        $sql = "SELECT
        attemptid, contextid, json
        FROM {local_catquiz_attempts} ";

        $wherearray = [];
        $params = [];

        if (!empty($instanceid)) {
            $wherearray[] = ' instanceid = :instanceid ';
            $params['instanceid'] = $instanceid;
        }

        if (!empty($courseid)) {
            $wherearray[] = ' courseid = :courseid ';
            $params['courseid'] = $courseid;
        }
        if ($userid != -1) {
            $wherearray[] = ' userid = :userid ';
            $params['userid'] = $userid;
        }

        if (count($wherearray) > 0) {
            $sql .= " WHERE " . implode(' AND ', $wherearray);
        }

        $sql .= " ORDER BY timemodified DESC
        LIMIT " . $numberofrecords;

        return [$sql, $params];
    }

    /**
     * Return sql to render all or a subset of testenvironments
     *
     * @param array $filterarray
     *
     * @return array
     *
     */
    public static function return_sql_for_catcontexts(
        array $filterarray = []) {

        $params = [];
        $where = [];
        $filter = '';
        $select = "ccc.*, s1.attempts";
        $from = "{local_catquiz_catcontext} ccc
                 LEFT JOIN (
                        SELECT ccc1.id, COUNT(*) AS attempts
                          FROM {local_catquiz_catcontext} ccc1
                          JOIN {question_attempt_steps} qas
                            ON ccc1.starttimestamp < qas.timecreated AND ccc1.endtimestamp > qas.timecreated
                           AND qas.fraction IS NOT NULL
                      GROUP BY ccc1.id
                 ) s1
                 ON s1.id = ccc.id";
        $where = "1=1";

        return [$select, $from, $where, $filter, $params];
    }

    /**
     * Return the sql for all item params for an item in a given context
     *
     * @param int $testitemid
     * @param int $contextid
     *
     * @return array
     *
     */
    public static function get_sql_for_item_params(int $testitemid, int $contextid) {
        $sql = "SELECT *
        FROM {local_catquiz_itemparams}
        WHERE componentid = :itemid
          AND contextid = :contextid";

        $params = [
            'itemid' => $testitemid,
            'contextid' => $contextid,
        ];

        return [$sql, $params];
    }

    /**
     * Returns the highest status for the given item in the given context
     * @param int $testitemid
     * @param int $contextid
     * @param bool $withmodel If true, also the model name is returned
     * @return array
     */
    public static function get_sql_for_max_status_for_item(int $testitemid, int $contextid, bool $withmodel = false) {
        $sql = "
            SELECT max(status) as status
            FROM {local_catquiz_itemparams}
            WHERE componentid = :itemid
              AND contextid = :contextid
              GROUP BY componentid, contextid
        ";
        $params = [
            'itemid' => $testitemid,
            'contextid' => $contextid,
        ];

        if ($withmodel) {
            $sql = "
            SELECT ip.model, ip.status
            FROM {local_catquiz_itemparams} ip
            INNER JOIN ( $sql )
            s1 ON ip.status = s1.status
            WHERE ip.componentid = :itemid2
                AND ip.contextid = :contextid2
            ";
            $params['itemid2'] = $testitemid;
            $params['contextid2'] = $contextid;
        }

        return [$sql, $params];
    }

    /**
     * For a CAT-Scale manager, returns the number of assigned CAT-Scales.
     *
     * @param int $userid
     * @return array
     */
    public static function get_sql_for_number_of_assigned_catscales(int $userid) {
        $sql = "
            SELECT COUNT(*)
            FROM {local_catquiz_subscriptions}
            WHERE userid = :userid
                AND area = :area
                AND status = :status
        ";
        $params = [
            'userid' => $userid,
            'area' => 'catscale',
            'status' => 1,
        ];

        return [$sql, $params];
    }

    /**
     * For a CAT-Scale manager, returns the number of tests that are connected
     * to the managed CAT scales.
     *
     * @param int $userid
     * @return array
     */
    public static function get_sql_for_number_of_assigned_tests(int $userid) {
        $sql = "
            SELECT COUNT(*)
            FROM {local_catquiz_subscriptions} lcs
                JOIN {local_catquiz_tests} lct
                    ON lcs.itemid=lct.catscaleid
            WHERE userid = :userid
                AND area = :area
                AND lcs.status = :status
        ";
        $params = [
            'userid' => $userid,
            'area' => 'catscale',
            'status' => 1,
        ];

        return [$sql, $params];
    }

    /**
     * For a CAT-Scale manager, returns the number of questions that are
     * assigned to the managed scales
     *
     * @param int $userid
     * @return array
     */
    public static function get_sql_for_number_of_assigned_questions(int $userid) {
        $sql = "
            SELECT COUNT(*)
            FROM {local_catquiz_subscriptions} lcs
                    JOIN {local_catquiz_items} lci ON lcs.itemid=lci.catscaleid
            WHERE userid = :userid
            AND area = :area
            AND lcs.status = :status
        ";
        $params = [
            'userid' => $userid,
            'area' => 'catscale',
            'status' => 1,
        ];

        return [$sql, $params];
    }

    /**
     * Returns the timestamp of the most recent calculation across all contexts
     *
     * @return array
     */
    public static function get_sql_for_last_calculation_time() {
        $sql = "
            SELECT max(timecalculated)
            FROM {local_catquiz_catcontext}
        ";
        $params = [];

        return [$sql, $params];
    }

    /**
     * Returns the number of test items in a CAT scale
     *
     * @param int $catscaleid
     * @return array
     */
    public static function get_sql_for_number_of_questions_in_scale(int $catscaleid) {
        $sql = "
            SELECT COUNT(*)
            FROM {local_catquiz_items}
            WHERE catscaleid = :catscaleid
        ";
        $params = [
            'catscaleid' => $catscaleid,
        ];
        return [$sql, $params];
    }

    /**
     * Returns the default context id from DB.
     *
     * @return int
     */
    public static function get_default_context_id() {
        global $DB;
        $contextid = $DB->get_field_sql(
           "SELECT id FROM {local_catquiz_catcontext} WHERE " . $DB->sql_like(
               'json',
               ":default"
           ),
           [
               'default' => '%"default":true%',
           ],
           MUST_EXIST
        );

        return intval($contextid);
    }

    /**
     * Returns the default context object from DB.
     *
     * @return object
     */
    public static function get_default_context_object() {
        global $DB;

        $context = $DB->get_record_sql(
           "SELECT * FROM {local_catquiz_catcontext} WHERE " . $DB->sql_like(
               'json',
               ":default"
           ),
           [
               'default' => '%"default":true%',
           ],
           MUST_EXIST
        );

        return $context;
    }

    /**
     * Updates the person ability for the given user in the given context
     *
     * @param int $userid
     * @param int $contextid
     * @param int $catscaleid
     * @param float $ability
     *
     * @return void
     *
     */
    public static function update_person_param(
        int $userid,
        int $contextid,
        int $catscaleid,
        float $ability
    ) {
        global $DB;

        $existingrecord = $DB->get_record(
            'local_catquiz_personparams',
            [
                'userid' => $userid,
                'contextid' => $contextid,
                'catscaleid' => $catscaleid,
            ]
        );

        $record = (object)[
            'userid' => $userid,
            'contextid' => $contextid,
            'catscaleid' => $catscaleid,
            'ability' => $ability,
            'timemodified' => time(),
        ];

        if (!$existingrecord) {
            $DB->insert_record(
                'local_catquiz_personparams',
                $record
            );
            return;
        }

        $record->id = $existingrecord->id;
        $DB->update_record('local_catquiz_personparams', $record);
    }

    /**
     * Return the attempt with the given attemptid
     *
     * @param int $attemptid
     * @return array<\stdClass>
     */
    public static function get_attempt_statistics(int $attemptid) {
        global $DB;
        return $DB->get_records_sql(
            "SELECT state, COUNT(*) as count
            FROM {adaptivequiz_attempt} aa
            LEFT JOIN {question_attempts} qa ON aa.uniqueid = qa.questionusageid
            LEFT JOIN {question_attempt_steps} qas ON qa.id = qas.questionattemptid AND fraction IS NOT NULL
            WHERE aa.id = :attemptid
            GROUP BY state;",
            ['attemptid' => $attemptid]
        );
    }

    /**
     * Return the person ability for the given user in the given context
     *
     * @param int $contextid
     * @param array $catscaleids
     * @param int|null $userid
     *
     * @return mixed
     *
     */
    public static function get_person_abilities(int $contextid, array $catscaleids, ?int $userid = null) {
        global $DB;
        [$insql, $inparams] = $DB->get_in_or_equal(
            $catscaleids,
            SQL_PARAMS_NAMED,
            'incatscales'
        );

        $sql = "
            SELECT *
            FROM {local_catquiz_personparams}
            WHERE contextid = :contextid
                AND catscaleid $insql
        ";
        $params = array_merge([
            'contextid' => $contextid,
        ], $inparams);

        if ($userid) {
            $sql .= " AND userid = :userid";
            $params['userid'] = $userid;
        }

        return $DB->get_records_sql(
            $sql,
            $params
          );
    }

    /**
     * Get last user attemptid.
     *
     * @param int $userid
     *
     * @return int
     *
     */
    public static function get_last_user_attemptid(int $userid) {
        global $DB;
        $record = $DB->get_record_sql(
            "SELECT id FROM {adaptivequiz_attempt}
            WHERE userid = :userid
                AND attemptstate = :attemptstate
            ORDER BY id DESC
            LIMIT 1",
            [
                'userid' => $userid,
                'attemptstate' => 'complete',
            ]
        );
        return $record->id;
    }

    /**
     * Get testenvironment by attemptid.
     *
     * @param int $attemptid
     *
     * @return object
     *
     */
    public static function get_testenvironment_by_attemptid(int $attemptid) {
        global $DB;

        return $DB->get_record_sql(
            "SELECT lct.*
             FROM {adaptivequiz_attempt} aa
             JOIN {local_catquiz_tests} lct
                ON aa.instance = lct.componentid
                AND component = :component
             WHERE aa.id = :id
            ",
            [
                'component' => 'mod_adaptivequiz',
                'id' => $attemptid,
            ]
            );
    }

    /**
     * Get the id of the parentscale with id of subscale.
     * @param int $subscaleid
     * @return mixed
     */
    public static function get_parent_scale(int $subscaleid) {
        global $DB;
        $record = $DB->get_record_sql(
            "SELECT parentid FROM {local_catquiz_catscales}
            WHERE id = :subscaleid
            LIMIT 1",
            [
                'subscaleid' => $subscaleid,
                'attemptstate' => 'complete',
            ]
        );
        return $record->parentid;
    }

    /**
     * Get all parent catscales.
     *
     * @return array
     *
     */
    public static function get_all_parent_catscales() {
        global $DB;
        return $DB->get_records(
            'local_catquiz_catscales',
            ['parentid' => 0],
            '',
          'id, name'
        );
    }

    /**
     * Get all catscales.
     *
     * @return array
     *
     */
    public static function get_all_catscales() {
        global $DB;

        return $DB->get_records('local_catquiz_catscales');
    }

    /**
     * Returns all person params for the given testid
     * @param int $componentid The id of the adaptivequiz component
     * @return array
     */
    public static function get_personparams_for_adaptivequiz_test(int $componentid) {
        global $DB;

        $test = $DB->get_record_sql(
            "
                SELECT *
                FROM {local_catquiz_tests}
                WHERE componentid = :componentid
                    AND component = :component
            ",
            [
                'componentid' => $componentid,
                'component' => 'mod_adaptivequiz',
            ],
            MUST_EXIST
        );
        if (!$testsettings = json_decode($test->json)) {
            throw new moodle_exception("Can not read test settings");
        }

        $contextid = catscale::get_context_id($testsettings->catquiz_catscales);
        $catscaleids = explode(",", $testsettings->catquiz_catscales);
        [$insql, $inparams] = $DB->get_in_or_equal($catscaleids, SQL_PARAMS_NAMED, 'incatscales');

        $sql = "
            SELECT lcp.*
            FROM
                (
                    SELECT DISTINCT userid
                    FROM {adaptivequiz_attempt}
                    WHERE instance = :componentid
                ) AS s1
            JOIN {local_catquiz_personparams} lcp ON s1.userid = lcp.userid
            WHERE lcp.contextid = :contextid
            AND lcp.catscaleid $insql
            ORDER BY ability ASC";

        $params = [
            'componentid' => $componentid,
            'contextid' => $contextid,
        ];

        $params = array_merge($params, $inparams);
        $records = $DB->get_records_sql($sql, $params);
        return $records;
    }

    /**
     * Returns all item parameters in the given context that are assigned to the
     * given catscaleid and were calculated with the given model
     *
     * @param int $contextid
     * @param array $catscaleids
     * @param string $model
     *
     * @return mixed
     *
     */
    public static function get_itemparams(int $contextid, array $catscaleids, string $model) {
        global $DB;
        [$insql, $inparams] = $DB->get_in_or_equal($catscaleids, SQL_PARAMS_NAMED, 'incatscales');

        return $DB->get_records_sql(
            "SELECT lci.id as uniqueid, lcip.*
             FROM {local_catquiz_items} lci
             JOIN {local_catquiz_itemparams} lcip
                ON lci.componentname = lcip.componentname
                    AND lci.componentid = lcip.componentid
                    AND lcip.contextid = :contextid
                    AND lcip.model = :model
            WHERE lci.catscaleid $insql
            ",
            array_merge(
                [
                    'contextid' => $contextid,
                    'model' => $model,
                ],
                $inparams
            )
        );
    }

    /**
     * Summary of get_catscales
     * @param array $catscaleids
     * @return mixed
     */
    public static function get_catscales(array $catscaleids) {
        global $DB;
        return $DB->get_records_list(
            "local_catquiz_catscales",
            'id',
            $catscaleids
        );
    }

    /**
     * Return the sql for the event logs of catquiz component.
     *
     * @param string $component
     *
     * @return array
     *
     */
    public static function return_sql_for_event_logs($component = 'local_catquiz') {
        global $DB;

        $select = "*";

        $from = "(
                    SELECT lsl.id as uniqueid, " .
                    $DB->sql_concat("u.firstname", "' '", "u.lastname") . " as username,
                    lsl.*
                    FROM {logstore_standard_log} lsl
                    LEFT JOIN {user} u
                    ON u.id = lsl.userid
                ) as s1";

        $where = 'component = :component ';

        $filter = '';

        $params = [
            'component' => $component,
        ];

        return [$select, $from, $where, $filter, $params];
    }

    /**
     * Return the record of a user.
     *
     * @param int $userid
     *
     * @return object
     *
     */
    public static function get_user_by_id($userid) {
        global $DB;

        $sql = "SELECT *
                FROM {user}
                WHERE id = :userid
                ";

        $record = $DB->get_record_sql($sql, ['userid' => $userid]);

        return $record;
    }

    /**
     * Adds or updates an attempt to db
     *
     * @param array $attemptdata
     * @return int The Id of the attemptdata entry, 0 for error
     */
    public static function save_attempt_to_db(array $attemptdata) {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/local/catquiz/lib.php');

        if (empty($attemptdata)) {
            return 0;
        }

        $catcontext = catscale::get_context_id($attemptdata['catscaleid']);

        // To query the db only once we fetch courseid und instanceid here.
        $courseandinstance = self::return_course_and_instance_id(
            $attemptdata['quizsettings']['modulename'],
            $attemptdata['attemptid']
        );

        $data = new stdClass;
        $data->userid = $attemptdata['userid'];
        $data->scaleid = $attemptdata['catscaleid'];
        $data->contextid = $catcontext;
        $data->courseid = $courseandinstance['courseid'];
        $data->attemptid = $attemptdata['attemptid'];
        $data->component = $attemptdata['quizsettings']['modulename'];
        $data->instanceid = $courseandinstance['instanceid'];
        $data->teststrategy = $attemptdata['teststrategy'];
        $data->status = LOCAL_CATQUIZ_ATTEMPT_OK;
        $data->total_number_of_testitems = $attemptdata['total_number_of_testitems'];
        $data->number_of_testitems_used = $attemptdata['number_of_testitems_used'];
        $data->personability_before_attempt = $attemptdata['ability_before_attempt'];
        $data->personability_after_attempt = $attemptdata['personabilities'][$attemptdata['catscaleid']]['value'] ?? null;
        $data->starttime = $attemptdata['starttime'] ?? null;
        $data->endtime = $attemptdata['endtime'] ?? time();

        $now = time();
        $data->timemodified = $now;
        $data->timecreated = $now;

        $attemptdata['courseid'] = $courseandinstance['courseid'];

        self::replace_inf_with_minusone($attemptdata);
        $data->json = json_encode($attemptdata);

        // Ensure there is only one row per attempt.
        $existingrecord = $DB->get_record('local_catquiz_attempts', ['attemptid' => $attemptdata['attemptid']]);
        if ($existingrecord) {
            $data->id = $existingrecord->id;
            $DB->update_record('local_catquiz_attempts', $data);
            return $existingrecord->id;
        }

        $id = $DB->insert_record('local_catquiz_attempts', (object) $data);

        return $id;
    }

    /**
     * Replace INF values in array with -1.
     * @param mixed $array
     *
     * @return void
     */
    public static function replace_inf_with_minusone(&$array) {
        foreach ($array as &$element) {
            if (empty($element)) {
                continue;
            } else if (is_array($element)) {
                self::replace_inf_with_minusone($element); // Recursively call the function for nested arrays.
            } else {
                if ($element === INF) {
                    $element = -1;
                }
            }
        }
    }

    /**
     * Fetch courseid and and instanceid from DB for attempt.
     *
     * @param string $modulename
     * @param int    $attemptid
     * @return array
     * @throws dml_exception
     */
    public static function return_course_and_instance_id(string $modulename, int $attemptid) {
        global $DB;
        $courseid = 0;
        $instanceid = 0;
        if ($modulename == 'adaptivequiz') {
            $sql = "SELECT aq.id, aq.course
                    FROM {adaptivequiz_attempt} aqa
                    JOIN {adaptivequiz} aq
                    ON aq.id = aqa.instance
                    WHERE aqa.id = :attemptid";

            $params = [
                'attemptid' => $attemptid,
            ];
            $record = $DB->get_record_sql($sql, $params);
            $courseid = $record->course;
            $instanceid = $record->id;
        }

        return [
            'courseid' => $courseid,
            'instanceid' => $instanceid,
        ];
    }

    /**
     * Takes an array of ids and returns an array of the questions with these ids.
     *
     * @param array $questionids
     * @return array
     */
    public static function get_questions_by_ids(array $questionids) {
        global $DB;

        $questions = $DB->get_records_list('question', 'id', $questionids);

        return $questions;
    }

    /**
     * Get all quizattempts corresponding to given params.
     *
     * @param int $userid
     * @param int $catscaleid
     * @param int $courseid
     * @param int $contextid
     * @param int $starttime
     * @param int $endtime
     *
     * @return array
     */
    public static function get_attempts(
            int $userid = null,
            int $catscaleid = null,
            int $courseid = null,
            int $contextid = null,
            int $starttime = null,
            int $endtime = null) {
        global $DB;

        $sql = "SELECT * FROM {local_catquiz_attempts} WHERE 1=1";

        if (!is_null($userid)) {
            $sql .= " AND userid = :userid";
        }
        if (!is_null($catscaleid)) {
            $sql .= " AND scaleid = :catscaleid";
        }
        if (!is_null($courseid)) {
            $sql .= " AND courseid = :courseid";
        }
        if (!is_null($contextid)) {
            $sql .= " AND contextid = :contextid";
        }
        if (!is_null($starttime)) {
            $sql .= " AND timecreated >= :starttime";
        }
        if (!is_null($endtime)) {
            $sql .= " AND timecreated <= :endtime";
        }
        $sql .= " ORDER BY endtime ASC";
        $params = [
            'userid' => $userid,
            'catscaleid' => $catscaleid,
            'courseid' => $courseid,
            'contextid' => $contextid,
            'starttime' => $starttime,
            'endtime' => $endtime,
        ];

        $records = $DB->get_records_sql($sql, $params);
        return $records;
    }

    /**
     * Enrol user to courses or groups.
     *
     * @param int $userid
     * @param array $quizsettings
     * @param array $personabilities
     *
     * @return string
     */
    public static function enrol_user(
        int $userid,
        array $quizsettings,
        array $personabilities): string {

        // Filter for scales that are selected for enrolement.

        $enrolementarray = [];

        foreach ($personabilities as $catscaleid => $personability) {
            $enrolementarray = self::enrol_and_create_message_array(
                $enrolementarray,
                $quizsettings,
                $catscaleid,
                $personability,
                $userid
            );
        }

        $enrolementstrings = self::create_strings_for_enrolement_notification($enrolementarray);

        if (empty($enrolementstrings['messagetitle']) && empty($enrolementstrings['messagebody'])) {
            return "";
        }

        messages::send_html_message(
            $userid,
            $enrolementstrings['messagetitle'] ?? "",
            $enrolementstrings['messagebody'] ?? "",
            'enrolmentfeedback'
        );
        return $enrolementstrings['messageforfeedback'] ?? "";
    }

    /**
     * Creates array with courses and groups to enrole to.
     *
     * @param array $enrolementarray
     * @param array $quizsettings
     * @param int $catscaleid
     * @param float $personability
     * @param int $userid
     *
     * @return array
     *
     */
    public static function enrol_and_create_message_array(
        array $enrolementarray,
        array $quizsettings,
        int $catscaleid,
        float $personability,
        int $userid
        ): array {
        global $DB;

        $rolestudent = $DB->get_record('role', ['shortname' => 'student']);
        try {
            $catscale = catscale::return_catscale_object($catscaleid);
        } catch (\Exception $e) {
            $catscale = (object) ['name' => '']; // Create a dummy object.
        }
        $i = 1;
        while (isset($quizsettings['feedback_scaleid_limit_lower_' . $catscaleid . '_'. $i])) {

            $lowerlimit = $quizsettings['feedback_scaleid_limit_lower_' . $catscaleid . '_'. $i];
            $upperlimit = $quizsettings['feedback_scaleid_limit_upper_' . $catscaleid . '_'. $i];

            if ($personability >= (float) $lowerlimit && $personability <= (float) $upperlimit) {
                $message = !empty($quizsettings["enrolment_message_checkbox_" . $catscaleid . "_" . $i]);
                $groupstoenrol = $quizsettings['catquiz_group_' . $catscaleid . '_' . $i] ?? "";
                if (!empty($groupstoenrol)) {
                    $groupsarray = explode(",", $groupstoenrol);
                } else {
                    $groupsarray = [];
                }
                $coursestoenrol = $quizsettings['catquiz_courses_' . $catscaleid . '_' . $i] ?? [];
                if (empty($coursestoenrol) && empty($groupsarray)) {
                    // No courses and groups to enrol.
                    $i++;
                    continue;
                }
                // The first element at array key 0 is a dummy value to
                // display some message like "please select course" in the
                // form and has a course ID of 0.
                $coursestoenrol = array_filter($coursestoenrol, fn ($v) => $v != 0);
                foreach ($coursestoenrol as $courseid) {
                    $context = \context_course::instance($courseid);
                    $course = get_course($courseid);
                    $url = new moodle_url('/course/view.php', ['id' => $courseid]);

                    $coursedata = [];
                    $coursedata['testname'] = $quizsettings['name'];
                    $coursedata['coursename'] = $course->fullname ?? "";
                    $coursedata['coursesummary'] = $course->summary ?? "";
                    $coursedata['courseurl'] = $url->out() ?? "";
                    $coursedata['catscalename'] = $catscale->name ?? "";

                    if (!is_enrolled($context, $userid) && !empty($course)) {
                        if (enrol_try_internal_enrol($courseid, $userid, $rolestudent->id)) {
                            $enrolementarray['course'][] = $coursedata;
                            self::course_enrolment_event($coursedata, $userid);
                        }
                    }
                    if (empty($groupsarray)) {
                        continue;
                    }
                    // Inscription only for existing groups.
                    $groupsofcourse = groups_get_all_groups($courseid);
                    foreach ($groupsofcourse as $existinggroup) {
                        foreach ($groupsarray as $newgroup) {
                            if ($existinggroup->name == $newgroup) {
                                $groupmember = groups_add_member($existinggroup->id, $userid);
                                if ($groupmember) {
                                    $data = [];
                                    $data['testname'] = $quizsettings['name'];
                                    $data['groupname'] = $newgroup;
                                    $data['groupdescription'] = $existinggroup->description ?? "";
                                    $data['coursename'] = $course->fullname ?? "";
                                    $url = new moodle_url('/course/view.php', ['id' => $course->id]);
                                    $data['courseurl'] = $url->out();
                                    $data['catscalename'] = $catscale->name ?? "";
                                    $enrolementarray['group'][] = $data;
                                    self::group_enrolment_event($data, $userid);
                                }
                            }
                        }
                    }
                }
            }
            $i++;
        }

        if (!$message) {
            return [];
        }

        return $enrolementarray;

    }

    /**
     * Send event for user enrolement to course.
     *
     * @param array $coursedata
     * @param int $userid
     *
     * @return void
     *
     */
    public static function course_enrolment_event(array $coursedata, int $userid) {

        // Trigger user_enroled event.
        $event = usertocourse_enroled::create([
            'objectid' => $userid,
            'context' => \context_system::instance(),
            'other' => [
                'coursename' => $coursedata['coursename'],
                'courseurl' => $coursedata['courseurl'],
                'userid' => $userid,
                'testname' => $coursedata['testname'],
                'catscalename' => $coursedata['catscalename'],
            ],
        ]);
        $event->trigger();

    }

    /**
     * Send event for user enrolement to group.
     *
     * @param array $data
     * @param int $userid
     *
     * @return void
     *
     */
    public static function group_enrolment_event(array $data, int $userid) {

        // Trigger user_enroled event.
        $event = usertogroup_enroled::create([
            'objectid' => $userid,
            'context' => \context_system::instance(),
            'other' => [
                'groupname' => $data['groupname'],
                'coursename' => $data['coursename'],
                'courseurl' => $data['courseurl'],
                'userid' => $userid,
                'testname' => $data['testname'],
                'catscalename' => $data['catscalename'],
            ],
        ]);
        $event->trigger();
    }

    /**
     * Create strings for enrolement notifications.
     *
     * @param array $enrolementarray
     *
     * @return array
     *
     */
    public static function create_strings_for_enrolement_notification(array $enrolementarray): array {
        $messagetitle = get_string('enrolmentmessagetitle', 'local_catquiz');
        $messagebody = "";

        if (empty($enrolementarray)) {
            return [
                'messagetitle' => "",
                'messagebody' => "",
            ];
        }

        $messagebody = "";
        // If there is only one element, message is different. So we count.
        $sum = 0;
        foreach ($enrolementarray as $subarray) {
            $sum += count($subarray);
        }

        if ($sum == 1) {
            $type = array_keys($enrolementarray)[0];
            if ($type === 'course') {
                $message = get_string('onecourseenroled', 'local_catquiz', $enrolementarray['course'][0]);
            } else if ($type === 'group') {
                $message = get_string('onegroupenroled', 'local_catquiz', $enrolementarray['group'][0]);
            }
            return [
                'messagetitle' => $messagetitle,
                'messagebody' => $message ?? "",
                'messageforfeedback' => $message ?? "",

            ];
        }

        $coursestring = "<br>" . get_string('followingcourses', 'local_catquiz');
        $originalcs = $coursestring;
        $groupstring = get_string('followinggroups', 'local_catquiz');
        $originalgs = $groupstring;
        foreach ($enrolementarray as $type => $dataarray) {
            foreach ($dataarray as $messageinfo) {
                if ($type === "course") {
                    $coursestring .= "<div> - <a href=" . $messageinfo['courseurl'] . ">" . $messageinfo['coursename'] . "</a>
                    </div>";
                };
                if ($type === "group") {
                    $groupstring .= "<div> - "  . get_string('groupenrolementstring', 'local_catquiz', $messageinfo) ."</div>";
                }
            }
        }
        // Check if something was appended to the string.
        if ($coursestring === $originalcs) {
            $coursestring = "";
        }
        if ($groupstring === $originalgs) {
            $groupstring = "";
        }
        $startstring = get_string('enrolementstringstart', 'local_catquiz', $dataarray[0]);
        $startstringforfeedback = get_string('enrolementstringstartforfeedback', 'local_catquiz', $dataarray[0]);
        $endstring = get_string('enrolementstringend', 'local_catquiz', $dataarray[0]);

        $messagebody =
            $startstring .
            $coursestring .
            "<br>" .
            $groupstring .
            $endstring;
        $messageforfeedback =
            $startstringforfeedback .
            $coursestring .
            "<br>" .
            $groupstring .
            $endstring;
        return [
            'messagetitle' => $messagetitle,
            'messagebody' => $messagebody,
            'messageforfeedback' => $messageforfeedback,

        ];
    }

    /**
     * Marks the last question as failed
     *
     * @param int $usageid
     */
    public static function mark_last_question_failed(int $usageid) {
        global $DB;
        $quba = question_engine::load_questions_usage_by_activity($usageid);
        $slot = max($quba->get_slots());

        // Choose another valid but incorrect response.
        $correctresponse = $quba->get_correct_response($slot)['answer'];
        if ($correctresponse >= 1) {
            $response = $correctresponse - 1;
        } else {
            $response = $correctresponse + 1;
        }

        $qa = $quba->get_question_attempt($slot);
        $qa->process_action(['answer' => $response]);
        $qa->finish();
        $quba->finish_question($slot);
        question_engine::save_questions_usage_by_activity($quba);

        // Increment questions attempted.
        $adqattempt = $DB->get_record('adaptivequiz_attempt', ['uniqueid' => $usageid]);
        $now = time();
        $adqattempt->timemodified = $now;
        $adqattempt->questionsattempted++;
        $DB->update_record('adaptivequiz_attempt', $adqattempt);
    }
}
