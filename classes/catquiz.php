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
     * @param array $catscaleids
     * @param array $wherearray
     * @param array $filterarray
     * @param int $catcontextid
     * @param int $userid
     * @return array
     */
    public static function return_sql_for_catscalequestions(
        array $catscaleids,
        int $contextid,
        array $wherearray = [],
        array $filterarray = [],
        int $userid = 0) {

        global $DB;
        if ($contextid === 0) {
            $contextid = self::get_default_context_id();
        }

        // Start the params array.
        $params = [
            'contextid' => $contextid,
            'contextid2' => $contextid,
        ];

        $restrictforuser = "";
        // If we fetch only for a given user, we need to add this to the sql.
        if (!empty($userid)) {
            $restrictforuser = " AND qas.userid = :userid ";
            $params['userid'] = $userid;
        }

        $select = ' DISTINCT *';
        $from = "(SELECT
                    q.id,
                    qbe.idnumber,
                    q.name,
                    q.questiontext,
                    q.qtype,
                    qc.name as categoryname,
                    lci.catscaleid catscaleid,
                    lci.componentname component,
                    s2.attempts,
                    COALESCE(s2.lastattempttime,0) as lastattempttime,
                    s3.userattempts,
                    COALESCE(s3.userlastattempttime,0) as userlastattempttime,

                    maxlcip.difficulty,
                    maxlcip.discrimination,
                    maxlcip.model,

                    ( SELECT MAX(lcip.status)
                    	FROM {local_catquiz_itemparams} lcip
                    	WHERE lcip.componentname=lci.componentname AND lcip.componentid=lci.componentid) as maxstatus,
                    maxlcip.itemstatus


                FROM {question} q
                JOIN {question_versions} qv
                    ON q.id=qv.questionid
                JOIN {question_bank_entries} qbe
                    ON qv.questionbankentryid=qbe.id
                JOIN {question_categories} qc
                    ON qc.id=qbe.questioncategoryid
                LEFT JOIN {local_catquiz_items} lci
                    ON lci.componentid=q.id AND lci.componentname='question'

                LEFT JOIN (
                    SELECT lcip.difficulty, lcip.discrimination, lcip.componentid, lcip.componentname, lcip.model, lcip.status as itemstatus
                    FROM {local_catquiz_itemparams} lcip
                    GROUP BY lcip.difficulty, lcip.discrimination, lcip.componentid, lcip.componentname, lcip.model, lcip.status
                    ) as maxlcip
                ON maxlcip.componentid=q.id AND maxlcip.componentname=lci.componentname

                LEFT JOIN (
                    SELECT ccc1.id AS contextid, qa.questionid, COUNT(*) AS attempts, MAX(qas.timecreated) as lastattempttime
                    FROM {local_catquiz_catcontext} ccc1
                        JOIN {question_attempt_steps} qas
                            ON ccc1.starttimestamp < qas.timecreated AND ccc1.endtimestamp > qas.timecreated
                                AND qas.fraction IS NOT NULL
                        JOIN {question_attempts} qa
                            ON qas.questionattemptid = qa.id
                    WHERE ccc1.id = :contextid
                    GROUP BY ccc1.id, qa.questionid
                ) s2 ON q.id = s2.questionid
                LEFT JOIN (
                    SELECT ccc1.id AS contextid, qa.questionid, COUNT(*) AS userattempts, MAX(qas.timecreated) as userlastattempttime
                    FROM {local_catquiz_catcontext} ccc1
                             JOIN {question_attempt_steps} qas
                                  ON ccc1.starttimestamp < qas.timecreated AND ccc1.endtimestamp > qas.timecreated
                                      AND qas.fraction IS NOT NULL
                                      $restrictforuser
                             JOIN {question_attempts} qa
                                  ON qas.questionattemptid = qa.id
                    WHERE ccc1.id = :contextid2
                    GROUP BY ccc1.id, qa.questionid
                ) s3 ON q.id = s3.questionid
            ) as s1";
        [$insql, $inparams] = $DB->get_in_or_equal($catscaleids, SQL_PARAMS_NAMED);
        $where = ' catscaleid '. $insql .' AND COALESCE(itemstatus,0) = COALESCE(maxstatus, 0)';
        $params = array_merge($params, $inparams);
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
    public static function return_sql_for_addcatscalequestions(
        int $catscaleid,
        int $contextid,
        array $wherearray = [],
        array $filterarray = []
    ) {
        global $DB;
        $contextfilter = $contextid === 0
            ? $DB->sql_like('ccc1.json',':default')
            : "ccc1.id = :contextid";

        list(,$context_from, $context_where, $context_params) = self::get_sql_for_stat_base_request();
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
                JOIN {question_versions} qv ON q.id=qv.questionid
                JOIN {question_bank_entries} qbe ON qv.questionbankentryid=qbe.id
                JOIN {question_categories} qc ON qc.id=qbe.questioncategoryid
                LEFT JOIN {local_catquiz_items} lci ON lci.componentid=q.id AND lci.componentname='question'
                LEFT JOIN (
                    SELECT ccc1.id AS contextid, qa.questionid, COUNT(*) AS contextattempts
                    FROM $context_from
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
     * @param array<integer> $testitemids
     * @param array<integer> $contextids
     * @return array
     */
    public static function get_sql_for_questions_answered(
        array $testitemids = [],
        array $contextids = [],
        array $studentids = []
        ) {
        list ($select, $from, $where, $params) = self::get_sql_for_stat_base_request($testitemids, $contextids, $studentids);

        $sql = "SELECT COUNT(qas.id)
        FROM $from
        WHERE $where";

        return [$sql, $params];
    }

    /**
     * Return the sql for all questions answered.
     *
     * @param array<integer> $testitemids
     * @param integer $contextid
     * @return array
     */
    public static function get_sql_for_questions_average(array $testitemids = [], array $contextids = []) {
        list ($select, $from, $where, $params) = self::get_sql_for_stat_base_request($testitemids, $contextids);

        $sql = "SELECT AVG(qas.fraction)
        FROM $from
        WHERE $where";

        return [$sql, $params];
    }

    /**
     * Return the sql for all questions answered.
     *
     * @param array<integer> $testitemids
     * @return array
     */
    public static function get_sql_for_questions_answered_correct(
        array $testitemids = [],
        array $contextids = [],
        array $studentids = []
    ) {
        list($select, $from, $where, $params) = self::get_sql_for_stat_base_request($testitemids, $contextids, $studentids);

        $sql = "SELECT COUNT(qas.id)
        FROM $from
        WHERE $where
        AND qas.fraction = qa.maxfraction";

        return [$sql, $params];
    }

    /**
     * Return the sql for all questions answered.
     *
     * @param array<integer> $testitemids
     * @return array
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
     * @param array<integer> $testitemids
     * @return array
     */
    public static function get_sql_for_questions_answered_partlycorrect(array $testitemids = [], array $contextids = []) {
        list ($select, $from, $where, $params) = self::get_sql_for_stat_base_request($testitemids, $contextids);

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
     * @param array<integer> $testitemids
     * @return array
     */
    public static function get_sql_for_questions_answered_by_distinct_persons(array $testitemids = [], array $contextids = []) {

        $param = empty($testitemid) ? [] : [$testitemid];
        list ($select, $from, $where, $params) = self::get_sql_for_stat_base_request($testitemids, $contextids);

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
     * Returns the sql that can be used to get input data for the
     * helpercat::get_item_list($data) function
     */
    public static function get_sql_for_model_input($contextid) {

        list (, $from, $where, $params) = self::get_sql_for_stat_base_request([], [$contextid]);

        $sql = "
        SELECT qas.id, qas.userid, qa.questionid, qas.fraction, qa.minfraction, qa.maxfraction, q.qtype, qas.timecreated
        FROM $from
        JOIN {question} q
            ON qa.questionid = q.id
        WHERE $where
        ";

        return [$sql, $params];
    }

    /**
     * Return the sql for all questions answered.
     *
     * @param array<integer> $testitemids
     * @param array<integer> $contextids
     * @return array
     */
    public static function get_sql_for_questions_usages_in_tests(
        array $testitemids = [],
        array $contextids = [],
        array $studentids = []
    ) {
        list($select, $from, $where, $params) = self::get_sql_for_stat_base_request($testitemids, $contextids, $studentids);

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
    public static function return_sql_for_student_stats(int $contextid) {

        list ($select, $from, $where, $params) = self::get_sql_for_stat_base_request([], [$contextid]);

        $select = "*";
        $from .= " JOIN {user} u ON qas.userid = u.id";

        if ($where == "") {
            $where .= "1=1";
        }
        $where .= " GROUP BY u.id, u.firstname, u.lastname, ccc1.id";

        $from = " (SELECT u.id, u.firstname, u.lastname, ccc1.id AS contextid, COUNT(*) as studentattempts FROM $from WHERE $where) s1
                    JOIN (
                        SELECT userid, contextid, MAX(ability) as ability
                        FROM {local_catquiz_personparams} cpp
                        GROUP BY userid, contextid
                    ) s2 ON s1.id = s2.userid AND s1.contextid = s2.contextid
            ";

        return [$select, $from, "1=1", "", $params];
    }

    /**
     * Basefunction to fetch all questions in context.
     *
     * @param array $testitemids
     * @param array $contextid
     * @return array
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
     private static function set_optional_param($params, $name, $originalvalue, $sqlstringval) {
        if (!empty($originalvalue)) {
            $params[$name] = $sqlstringval;
        }
        return $params;
     }

    /**
     * Return sql to render all or a subset of testenvironments
     *
     * @return array
     */
    public static function return_sql_for_testenvironments(
        string $where = "1=1",
        array $filterarray = []) {
        global $DB;
        $params = [];
        $filter = '';

        $select = "
            c.id,
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
            teachers";

        $from = "
        {local_catquiz_tests} ct
        JOIN {course} c ON c.id = ct.courseid
        JOIN (SELECT catscaleid as itemcatscale, COUNT(*) AS numberofitems
           FROM {local_catquiz_items}
           GROUP BY catscaleid
        ) s1 ON ct.catscaleid = s1.itemcatscale
        JOIN (
            SELECT c.id AS courseid, " . $DB->sql_group_concat($DB->sql_concat_join("' '", ['u.firstname', 'u.lastname']), ', ') . " AS teachers
            FROM {user} u
            JOIN {role_assignments} ra ON ra.userid = u.id
            JOIN {context} ct ON ct.id = ra.contextid
            JOIN {course} c ON c.id = ct.instanceid
            JOIN {role} r ON r.id = ra.roleid
            WHERE r.shortname IN ('teacher', 'editingteacher')
            GROUP BY c.id
            ) s2 ON s2.courseid = ct.courseid"
        ;

        return [$select, $from, $where, $filter, $params];
    }

    /**
     * Return sql to render all or a subset of testenvironments
     *
     * @return array
     */
    public static function return_sql_for_catcontexts(
        array $wherearray = [],
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
     * @param integer $testitemids
     * @param integer $contextid
     * @return array
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
     * @return array
     */
    public static function get_sql_for_max_status_for_item(int $testitemid, int $contextid) {
        $sql = "
            SELECT max(status)
            FROM {local_catquiz_itemparams}
            WHERE componentid = :itemid
              AND contextid = :contextid
              GROUP BY componentid, contextid
        ";
        $params = [
            'itemid' => $testitemid,
            'contextid' => $contextid,
        ];

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
     * @param int $userid
     * @return array
     */
    public static function get_sql_for_last_calculation_time(int $userid) {
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
            'catscaleid' => $catscaleid
        ];
        return [$sql, $params];
    }
    /** Returns the number of test items in a CAT scale
     *
     * @param array $catscaleids
     * @return array
     *
     */

    public static function get_sql_for_scales_and_subscales(array $catscaleids) {
        global $DB;
        $select = "*";
        $from = "{local_catquiz_catscales}";

        $params = [];

        [$insql, $inparams] = $DB->get_in_or_equal($catscaleids, SQL_PARAMS_NAMED);

        $where = ' parentid '. $insql;
        $params = array_merge($params, $inparams);
        $filter = '';

        return [$select, $from, $where, $filter, $params];

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
     * Updates the person ability for the given user in the given context
     *
     * @param int $userid
     * @param int $contextid
     * @param float $ability
     * @return void
     */
    public static function update_person_param(
        int $userid,
        int $contextid,
        float $ability
    ) {
        global $DB;

        $existing_record = $DB->get_record(
            'local_catquiz_personparams',
            [
                'userid' => $userid,
                'contextid' => $contextid,
            ]
        );

        $record = (object)[
            'userid' => $userid,
            'contextid' => $contextid,
            'ability' => $ability,
            'timemodified' => time(),
        ];

        if (!$existing_record) {
            $DB->insert_record(
                'local_catquiz_personparams',
                $record
            );
            return;
        }

        $record->id = $existing_record->id;
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
            "SELECT state, COUNT(*)
            FROM {adaptivequiz_attempt} aa
            JOIN {question_attempts} qa ON aa.uniqueid = qa.questionusageid
            JOIN {question_attempt_steps} qas ON qa.id = qas.questionattemptid AND fraction IS NOT NULL
            WHERE aa.id = :attemptid
            GROUP BY state;",
            ['attemptid' => $attemptid]
        );
    }

    /**
     * Return the person ability for the given user in the given context
     *
     * @param int $userid,
     * @param int $contextid
     *
     * @return \stdClass
     */
    public static function get_person_ability(int $userid, int $contextid) {
        global $DB;
        return $DB->get_record_select(
            'local_catquiz_personparams',
           "userid = :userid AND contextid = :contextid",
          [
            'userid' => $userid,
            'contextid' => $contextid,
        ]);
    }

    public static function get_last_user_attemptid(int $userid) {
        global $DB;
        return $DB->get_record_sql(
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
}
