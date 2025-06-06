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

use dml_exception;
use local_catquiz\data\dataapi;
use local_catquiz\event\usertocourse_enroled;
use local_catquiz\event\usertogroup_enroled;
use local_catquiz\local\status;
use local_catquiz\teststrategy\progress;
use moodle_exception;
use moodle_url;
use question_engine;
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
     * Give back the global (parent) scale id of a given catscale id or an array of catscale ids.
     *
     * @param int|array $catscaleids
     * @param bool $assocarray
     * @return array
     */
    private static function get_global_scale($catscaleids, bool $assocarray = false) {
        global $DB;
        $where = '';
        if (!empty($catscaleids) && $catscaleids[0] > 0) {
            [$insql, $inparams] = $DB->get_in_or_equal($catscaleids);
            $where = "WHERE scaleid $insql";
        } else {
            // NOTE: If no $catscaleids are given, then return ALL associations.
            $assocarray = true;
        }

        $sql = "WITH RECURSIVE globalscale (scaleid, globalid) AS (
            SELECT id, id
                FROM {local_catquiz_catscales}
                WHERE parentid = 0
            UNION ALL
            SELECT lcc.id, gs.globalid
                FROM {local_catquiz_catscales} lcc
                INNER JOIN globalscale gs ON lcc.parentid = gs.scaleid
        )
        SELECT scaleid, globalid
            FROM globalscale
            $where";

        if (is_int($catscaleids) && !$assocarray) {
            $sqlresult = $DB->get_record_sql($sql, $inparams);
            return [intval($sqlresult->globalid)];
        }

        if (!$assocarray) {
            $sqlresult = $DB->get_records_sql($sql, $inparams);
            $result = [];
            foreach ($sqlresult as $record) {
                $result[intval($record->scaleid)] = intval($record->globalid);
            }
            return $result;
        }

        $sqlresult = $DB->get_records_sql($sql, $inparams);
        $result = [];
        foreach ($sqlresult as $record) {
            $result[intval($record->scaleid)] = intval($record->globalid);
        }
        return $result;
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
     * @param string|null $orderby If given, order by the given field in ascending order
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

        ];
        $wherearray['lcipcontextid'] = $contextid;

        // If we fetch only for a given user, we need to add this to the sql.
        if (!empty($userid)) {
            $params['userid'] = $userid;
        }

        $insql = '';
        if (!empty($catscaleids) && $catscaleids[0] > 0) {

            $globalscaleids = self::get_global_scale($catscaleids);

            [$parentscales1, $inparams1] = $DB->get_in_or_equal($globalscaleids, SQL_PARAMS_NAMED, 'inparentscales1');
            [$parentscales2, $inparams2] = $DB->get_in_or_equal($globalscaleids, SQL_PARAMS_NAMED, 'inparentscales2');
            $params = array_merge($params, $inparams1, $inparams2);

            [$incatscales, $inparams] = $DB->get_in_or_equal($catscaleids, SQL_PARAMS_NAMED, 'incatscales');
            $params = array_merge($params, $inparams);
            $wherecontains['lccscatscaleid'] = $incatscales;
        }

        $select = "*";
        $from = <<<SQL
        ( SELECT
            -- Information about the question
            q.id,
            lci.componentid,
            qbe.idnumber as label,
            COALESCE (qbe.idnumber, CAST(qbe.id AS CHAR)) as idnumber,
            q.name as questionname,
            q.questiontext as questiontext,
            q.qtype as qtype,
            qc.name as categoryname,
            -- Information about CAT scales, parameters and contexts
            lci.catscaleid catscaleid,
            lci.status testitemstatus,
            lci.componentname component,
            lci.id as itemid,
            lccs.name as catscalename,
            lccs.id as lccscatscaleid,
            lcip.model as model,
            lcip.difficulty,
            lcip.discrimination,
            lcip.guessing,
            lcip.json,
            lcip.timecreated,
            lcip.timemodified,
            lcip.status,
            lcip.contextid AS lcipcontextid,
            -- Information about usage statisitcs
            COALESCE(astat.numberattempts,0) attempts,
            COALESCE(astat.lastattempt,0) as astatlastattempttime,
            ustat.userid, ustat.numberattempts userattempts,
            ustat.lastattempt as userlastattempttime
          FROM {local_catquiz_catscales} lccs
          -- Get all corresponding items of those scales, skip if not existent
          -- (INNER JOIN)
            JOIN {local_catquiz_items} lci ON lci.catscaleid=lccs.id

          -- Get all the item parameter for the question for the given context(s),
          -- skip if not existent
            JOIN {local_catquiz_itemparams} lcip ON lcip.itemid = lci.id AND lci.activeparamid = lcip.id

          -- Get all information about the question from the questionbank itself
            JOIN {question} q ON q.id=lci.componentid
            JOIN {question_versions} qv ON qv.questionid=q.id
            JOIN {question_bank_entries} qbe ON qbe.id=qv.questionbankentryid
            JOIN {question_categories} qc ON qc.id=qbe.questioncategoryid

          -- Get all information about the attempts in the scale(s)
          -- and context(s) in general and for specific user(s)
            LEFT JOIN (SELECT lca.scaleid, lca.contextid, qa.questionid, COUNT(qa.id) numberattempts,
              MAX(qas.timecreated) as lastattempt
              FROM {local_catquiz_attempts} lca
              JOIN {adaptivequiz_attempt} aqa ON lca.attemptid = aqa.id
              JOIN {question_attempts} qa ON qa.questionusageid = aqa.uniqueid
              JOIN {question_attempt_steps} qas
                ON qas.questionattemptid = qa.id AND qas.fraction IS NOT NULL
              GROUP BY lca.scaleid, lca.contextid, qa.questionid
            ) astat
              ON astat.contextid = lcip.contextid AND astat.questionid = q.id
                AND astat.scaleid $parentscales1
        SQL;

        if (!empty($userid)) {
            $from .= <<<SQL
                LEFT JOIN (
                    SELECT
                        lca.scaleid,
                        lca.contextid,
                        qa.questionid,
                        lca.userid,
                        COUNT(qa.id) numberattempts,
                        MAX(qas.timecreated) as lastattempt
                    FROM {local_catquiz_attempts} lca
                      JOIN {adaptivequiz_attempt} aqa ON lca.attemptid = aqa.id
                      JOIN {question_attempts} qa ON qa.questionusageid = aqa.uniqueid
                      JOIN {question_attempt_steps} qas
                        ON qas.questionattemptid = qa.id AND qas.fraction IS NOT NULL
                    GROUP BY lca.scaleid, lca.contextid, qa.questionid, lca.userid
                ) ustat
                  ON ustat.userid = :userid AND ustat.contextid = lcip.contextid AND ustat.questionid = q.id
                    AND ustat.scaleid $parentscales2 ) s
            SQL;
        } else {
            $from .= <<<SQL
              LEFT JOIN (SELECT NULL AS userid, NULL AS numberattempts, NULL AS lastattempt) as ustat
                ON 1=1 ) s
            SQL;
        }

        $where = '1=1';

        $filter = '';

        foreach ($wherearray as $key => $value) {
            $where .= ' AND ' . $DB->sql_equal($key, $value);
        }

        foreach ($wherecontains as $key => $value) {
            $where .= sprintf(' AND %s %s', $key, $value);
        }

        if ($orderby) {
            $where .= " ORDER BY $orderby";
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

        // TODO @DAVID: Re-Construct the SQL-Statemente as this contains all problematic patterns that has been fixed above as well.

        $contextfilter = $contextid === 0
            ? $DB->sql_like('ccc1.json', ':default')
            : "ccc1.id = :contextid";

        list(, $contextfrom, , $params) = self::get_sql_for_stat_base_request();
        $select = "id,
                idnumber,
                name,
                questiontext,
                qtype,
                categoryname,
                'question' as component,
                contextattempts as questioncontextattempts,
                catscaleids";
        $from = "( SELECT q.id, qbe.idnumber, q.name, q.questiontext, q.qtype, qc.name as categoryname, s2.contextattempts," .
             $DB->sql_group_concat($DB->sql_concat("'-'", 'lci.catscaleid', "'-'")) ." as catscaleids
            FROM {question} q
                JOIN (
                    SELECT *
                    FROM (
                        SELECT *, ROW_NUMBER() OVER (PARTITION BY questionbankentryid ORDER BY version DESC) n
                        FROM {question_versions}
                    ) s2
                    WHERE n = 1
                ) qv
                ON q.id=qv.questionid
                JOIN {question_bank_entries} qbe ON qv.questionbankentryid=qbe.id
                JOIN {question_categories} qc ON qc.id=qbe.questioncategoryid
                LEFT JOIN {local_catquiz_items} lci ON lci.componentid = q.id
                LEFT JOIN (
                    SELECT ccc1.id contextid, qa.questionid, COUNT(*) contextattempts
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
     * @param bool $joinitems Join items
     * @param int $joinability If given, join the ability of the scale with the given ID.
     *
     * @return array
     *
     */
    public static function get_sql_for_model_input(
        $contextid,
        array $catscaleids,
        ?int $testitemid,
        ?int $userid,
        bool $joinitems = false,
        int $joinability = 0
    ) {
        global $DB;
        $testitemids = $testitemid ? [$testitemid] : [];
        $userids = $userid ? [$userid] : [];
        [$insql, $inparams] = $DB->get_in_or_equal(
            $catscaleids,
            SQL_PARAMS_NAMED,
            'incatscales'
        );
        list (, $from, $where, $params) = self::get_sql_for_stat_base_request($testitemids, [$contextid], $userids);

        $joinitemssql = "";
        if ($joinitems) {
            $joinitemssql = <<<SQL
                JOIN {local_catquiz_items} lci
                    ON q.id = lci.componentid
                    AND lci.catscaleid $insql
            SQL;
        }

        $joinabilitysql = "";
        $abilityparams = [];
        $selectability = "";
        if ($joinability) {
            $selectability = ", lcp.ability ability";
            $abilityparams = ['scaleability' => $joinability, 'scalecontext' => $contextid];
            $joinabilitysql = <<<SQL
                JOIN {local_catquiz_personparams} lcp
                    ON lcp.userid = qas.userid
                    AND lcp.catscaleid = :scaleability
                    AND lcp.contextid = :scalecontext
            SQL;
        }

        $selectlci = $joinitems ? "lci.id" : "'-'";
        $select = $DB->sql_concat("qas.id", "'-'", "qas.userid", "'-'", "q.id", "'-'", $selectlci);
        $sql = "SELECT $select uniqueid,
            qas.id,
            qas.userid,
            qa.questionid,
            qas.state,
            qas.fraction,
            qa.minfraction,
            qa.maxfraction,
            q.qtype,
            qas.timecreated,
            qa.questionusageid attemptid
            $selectability
        FROM $from
        JOIN {question} q
            ON qa.questionid = q.id
        $joinitemssql
        $joinabilitysql
        WHERE $where
        ";

        return [$sql, array_merge($inparams, $params, $abilityparams)];
    }

    /**
     * Returns the last question that was answered in the current quiz attempt or false
     *
     * @param int $questionusageid
     * @return stdClass|bool
     */
    public static function get_last_response_for_attempt(int $questionusageid) {
        global $DB;

        [$unfinishedstatessql, $unfinishedstatesparams] = $DB->get_in_or_equal(
            self::get_unfinished_question_states(),
            SQL_PARAMS_NAMED,
            'unfinishedstates'
        );

        $sql = <<<SQL
        SELECT
            qs.id,
            questionattemptid,
            state,
            fraction originalfraction,
            ROUND(fraction, 3) fraction,
            timecreated,
            userid,
            questionusageid,
            questionid,
            questionsummary,
            rightanswer,
            responsesummary,
            timemodified
        FROM {question_attempt_steps} qs
        JOIN {question_attempts} qa ON qs.questionattemptid = qa.id
        AND qa.id = (
            SELECT max(questionattemptid) maxwithresponse
            FROM {question_attempt_steps} qs
                     JOIN (SELECT *
                           FROM {question_attempts}
                           WHERE questionusageid = :questionusageid
            ) sub1 ON qs.questionattemptid = sub1.id
            GROUP BY questionusageid
        ) AND state NOT $unfinishedstatessql
        SQL;

        $params = $unfinishedstatesparams;
        $params['questionusageid'] = $questionusageid;
        return $DB->get_record_sql(
            $sql,
            $params
        );
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
        global $DB;
        [$unfinishedstatessql, $unfinishedstatesparams] = $DB->get_in_or_equal(
            self::get_unfinished_question_states(),
            SQL_PARAMS_NAMED,
            'unfinishedstates'
        );

        // TODO: nochmal anschauen.
        $select = '*';
        $from = "{local_catquiz_catcontext} ccc1
                JOIN {question_attempt_steps} qas
                    ON ccc1.starttimestamp < qas.timecreated
                    AND ccc1.endtimestamp > qas.timecreated
                    AND qas.state NOT $unfinishedstatessql

                JOIN {question_attempts} qa
                    ON qas.questionattemptid = qa.id";
        ;
        $where = !empty($testitemids) ? 'qa.questionid IN (:testitemids)' : '1=1';
        $where .= !empty($contextids) ? ' AND ccc1.id IN (:contextids)' : '';
        $where .= !empty($studentids) ? ' AND userid IN (:studentids)' : '';

        $testitemidstring = sprintf("%s", implode(',', $testitemids));
        $contextidstring = sprintf("%s", implode(',', $contextids));
        $studentidstring = sprintf("%s", implode(',', $studentids));

        $params = self::set_optional_param([], 'testitemids', $testitemids, $testitemidstring);
        $params = self::set_optional_param($params, 'contextids', $contextids, $contextidstring);
        $params = self::set_optional_param($params, 'studentids', $studentids, $studentidstring);
        $params = array_merge($params, $unfinishedstatesparams);

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

        // TODO: SQL vereinfachen.
        // FRAGE @DAVID: Werden die ehemaligen Angaben noch gebraucht?

        // phpcs:disable
        /* Old code:
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
        LEFT JOIN (SELECT catscaleid as itemcatscale, COUNT(*) numberofitems
           FROM {local_catquiz_items}
           GROUP BY catscaleid
        ) s1 ON ct.catscaleid = s1.itemcatscale
        LEFT JOIN (
            SELECT c.id courseid, " .
                $DB->sql_group_concat($DB->sql_concat_join("' '", ['u.firstname', 'u.lastname']), ', ') . " teachers
            FROM {user} u
            JOIN {role_assignments} ra ON ra.userid = u.id
            JOIN {context} ct ON ct.id = ra.contextid
            JOIN {course} c ON c.id = ct.instanceid
            JOIN {role} r ON r.id = ra.roleid
            WHERE r.shortname IN ('teacher', 'editingteacher')
            GROUP BY c.id
            ) s2 ON s2.courseid = ct.courseid";
        */
        // phpcs:enable

        $select = " * ";

        $from = "
        ( SELECT
            ct.id,
            ct.name,
            component,
            componentid,
            status,
            parentid,
            fullname,
            c.timemodified,
            c.timecreated,
            ct.catscaleid,
            json,
            users,
            (CASE WHEN componentid <> 0 THEN 1 ELSE 0 END) istest
        FROM {local_catquiz_tests} ct
        LEFT JOIN {course} c ON c.id = ct.courseid
        LEFT JOIN {adaptivequiz} aq ON ct.componentid = aq.id
        LEFT JOIN (
            SELECT instance, COUNT(*) as users
            FROM (
                SELECT instance, userid
                FROM {adaptivequiz_attempt} at
                GROUP BY at.instance, at.userid
            ) s4
            GROUP BY s4.instance
        ) s2 ON s2.instance = ct.componentid
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
     * @param int $attemptid Optional attemptid.
     * @param int $userid
     *
     * @return mixed
     *
     */
    public static function return_data_from_attemptstable(
        int $numberofrecords = 1,
        int $instanceid = 0,
        int $courseid = 0,
        int $attemptid = 0,
        int $userid = -1) {

        global $DB;

        $sqlarray = self::return_sql_for_attemptid_contextid_json(
            $numberofrecords,
            $instanceid,
            $courseid,
            $attemptid,
            $userid
        );

        $recordsarray = $DB->get_records_sql($sqlarray[0], $sqlarray[1]);

        return $recordsarray;
    }

    /**
     * Summary of return_sql_for_attemptid_contextid_json
     * @param int $numberofrecords
     * @param int $instanceid
     * @param int $courseid
     * @param int $attemptid
     * @param int $userid
     * @return array
     */
    private static function return_sql_for_attemptid_contextid_json(
        int $numberofrecords = 1,
        int $instanceid = 0,
        int $courseid = 0,
        int $attemptid = 0,
        int $userid = -1): array {

        $sql = "SELECT
        attemptid, contextid, userid, endtime, timemodified, json, debug_info
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

        if ($attemptid !== 0) {
            $wherearray[] = ' attemptid = :attemptid ';
            $params['attemptid'] = $attemptid;
        }

        if (count($wherearray) > 0) {
            $sql .= " WHERE " . implode(' AND ', $wherearray);
        }

        $sql .= " ORDER BY timemodified DESC";

        // We treat both INF as 0 as infinite value here, because intval(INF) is
        // converted to 0.
        if (
            !is_infinite($numberofrecords)
            && $numberofrecords !== 0
        ) {
            $sql .= " LIMIT " . $numberofrecords;
        }

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
        $select = "*";
        $from = "(SELECT ccc.*, COUNT(lca.id) attempts
            FROM {local_catquiz_catcontext} ccc
            LEFT JOIN {local_catquiz_attempts} lca ON lca.contextid = ccc.id
            GROUP BY ccc.id
            ) s1";
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
              GROUP BY componentid, contextid";

        $params = [
            'itemid' => $testitemid,
            'contextid' => $contextid,
        ];

        if ($withmodel) {
            $sql = "
              SELECT ip.model, ip.status
                FROM {local_catquiz_itemparams} ip
                  INNER JOIN ( $sql ) s1 ON ip.status = s1.status
                WHERE ip.componentid = :itemid2
                  AND ip.contextid = :contextid2";

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
              AND status = :status";

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
     * Return the person abilities for the given parameters
     *
     * @param int $contextid
     * @param array $catscaleids
     * @param array|null $userids
     *
     * @return mixed
     *
     */
    public static function get_person_abilities(int $contextid, array $catscaleids, array $userids = []) {
        global $DB;
        $where = "contextid = :contextid";
        $params = ['contextid' => $contextid];

        if ($catscaleids) {
            [$inscalesql, $inscaleparams] = $DB->get_in_or_equal(
                $catscaleids,
                SQL_PARAMS_NAMED,
                'incatscales'
            );
            $where .= " AND catscaleid " . $inscalesql;
            $params = array_merge($params, $inscaleparams);
        }

        $sql = "
            SELECT *
            FROM {local_catquiz_personparams}
            WHERE $where";

        if ($userids) {
            [$inuseridssql, $inuseridsparams] = $DB->get_in_or_equal(
                $userids,
                SQL_PARAMS_NAMED,
                'inuserids'
            );
            $sql .= " AND userid $inuseridssql";
            $params = array_merge($params, $inuseridsparams);
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
     * Returns the main CAT scale for the given context if it exists.
     *
     * If it does not exist, returns null.
     *
     * @param int $contextid
     * @return ?stdClass
     */
    public static function get_main_scale(int $contextid): ?stdClass {
        global $DB;

        return $DB->get_record('local_catquiz_catscales', ['contextid' => $contextid]) ?: null;
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
                ) s1
            JOIN {local_catquiz_personparams} lcp ON s1.userid = lcp.userid
            WHERE lcp.contextid = :contextid
            AND lcp.catscaleid $insql
            ORDER BY abilityC";

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
     * @param ?string $model
     *
     * @return mixed
     *
     */
    public static function get_itemparams(int $contextid, array $catscaleids = [], ?string $model = null) {
        global $DB;
        $where = "lcip.contextid = :contextid  ";
        $params = ['contextid' => $contextid];
        if ($catscaleids) {
            [$insql, $inparams] = $DB->get_in_or_equal($catscaleids, SQL_PARAMS_NAMED, 'incatscales');
            $where .= "AND lci.catscaleid " . $insql . " ";
            $params = array_merge($params, $inparams);
        }

        if ($model) {
            $where .= "AND lcip.model = :model ";
            $params = array_merge($params, ['model' => $model]);
        } else {
            // If no model is given, link the itemparam via the activeparamid.
            $where .= "AND lci.activeparamid = lcip.id ";
        }

        return $DB->get_records_sql(
            "SELECT lci.id as uniqueid, lcip.*
             FROM {local_catquiz_items} lci
             JOIN {local_catquiz_itemparams} lcip
                ON lci.id = lcip.itemid
            WHERE $where
            ",
           $params
        );
    }

    /**
     * Summary of get_catscales
     * @param array $catscaleids
     * @return mixed
     */
    public static function get_catscales(array $catscaleids) {
        $all = dataapi::get_all_catscales();
        $filtered = array_filter(
            $all,
            fn ($scale) => in_array($scale->id, $catscaleids)
        );
        return $filtered;
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
            $attemptdata['quizsettings']->modulename,
            $attemptdata['attemptid']
        );

        $data = new stdClass;
        $data->userid = $attemptdata['userid'];
        $data->scaleid = $attemptdata['catscaleid'];
        $data->contextid = $catcontext;
        $data->courseid = $courseandinstance['courseid'];
        $data->attemptid = $attemptdata['attemptid'];
        $data->component = $attemptdata['quizsettings']->modulename;
        $data->instanceid = $courseandinstance['instanceid'];
        $data->teststrategy = $attemptdata['teststrategy'];
        $data->status = LOCAL_CATQUIZ_ATTEMPT_OK;
        $data->total_number_of_testitems = $attemptdata['total_number_of_testitems'];
        $data->number_of_testitems_used = $attemptdata['questionsattempted'];
        $data->personability_before_attempt = $attemptdata['ability_before_attempt'];
        $data->personability_after_attempt = $attemptdata['progress']->get_abilities()[$attemptdata['catscaleid']] ?? null;
        $data->starttime = $attemptdata['starttime'] ?? null;
        $data->endtime = $attemptdata['endtime'] ?: time();

        if (get_config('local_catquiz', 'store_debug_info')) {
            $data->debug_info = json_encode($attemptdata['debuginfo']);
            unset($attemptdata['debuginfo']);
        }

        // These values are not needed to render a feedback.
        $excluded = [
            'person_ability',
            'installed_models',
            'lastquestion',
            'lastresponse',
            'models',
            'prev_ability',
        ];
        foreach ($excluded as $key) {
            unset($attemptdata[$key]);
        }

        $now = time();
        $data->timemodified = $now;
        $data->timecreated = $now;

        $attemptdata['courseid'] = $courseandinstance['courseid'];

        self::replace_inf_with_minusone($attemptdata);
        // Do not save the quiz settings here - we get them from the progress class.
        unset($attemptdata['quizsettings']);
        // The progress data are saved in their own table - we do not need to save them here.
        unset($attemptdata['progress']);
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
     * Set the status in the attempts table.
     *
     * @param int $attemptid
     * @param string $status
     *
     * @return void
     */
    public static function set_final_attempt_status(int $attemptid, string $status) {
        global $DB;
        $statusnumber = status::to_int($status);
        if (!$existingrecord = $DB->get_record('local_catquiz_attempts', ['attemptid' => $attemptid])) {
            return;
        }
        $data = (object) [
            'id' => $existingrecord->id,
            'status' => $statusnumber,
        ];
        $DB->update_record('local_catquiz_attempts', $data);
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
     * The returned attempts are sorted by endtime in ascending order.
     *
     * @param ?int $userid
     * @param ?int $catscaleid
     * @param ?int $courseid
     * @param ?int $testid
     * @param ?int $contextid
     * @param ?int $starttime
     * @param ?int $endtime
     * @param bool $enrolled
     *
     * @return array
     */
    public static function get_attempts(
            ?int $userid = null,
            ?int $catscaleid = null,
            ?int $courseid = null,
            ?int $testid = null,
            ?int $contextid = null,
            ?int $starttime = null,
            ?int $endtime = null,
            bool $enrolled = true) {
        global $DB;

        // Select only attempts of courses, where the user of the attempt is
        // enrolled as student.
        $with = "";
        $join = "";
        if ($enrolled && $courseid) {
            $with = <<<SQL
                WITH EnrolledUsers AS (
                    SELECT DISTINCT ue.userid
                    FROM {user_enrolments} ue
                    JOIN {enrol} e ON ue.enrolid = e.id AND e.courseid = :courseid2
                )
            SQL;
            $join = <<<SQL
                JOIN EnrolledUsers s1 ON a.userid = s1.userid
            SQL;
        }

        $sql = "$with SELECT * FROM {local_catquiz_attempts} a $join WHERE 1=1";

        if (!is_null($userid)) {
            $sql .= " AND userid = :userid";
        }
        if (!is_null($catscaleid)) {
            $sql .= " AND scaleid = :catscaleid";
        }
        if (!is_null($courseid)) {
            $sql .= " AND a.courseid = :courseid";
        }
        if (!is_null($testid)) {
            $sql .= " AND instanceid = :instanceid";
        }
        if (!is_null($contextid)) {
            $sql .= " AND contextid = :contextid";
        }
        if (!is_null($starttime)) {
            $sql .= " AND a.timecreated >= :starttime";
        }
        if (!is_null($endtime)) {
            $sql .= " AND a.timecreated <= :endtime";
        }
        $sql .= " ORDER BY a.endtime";
        $params = [
            'userid' => $userid,
            'catscaleid' => $catscaleid,
            'courseid' => $courseid,
            'courseid2' => $courseid,
            'instanceid' => $testid,
            'contextid' => $contextid,
            'starttime' => $starttime,
            'endtime' => $endtime,
        ];

        return $DB->get_recordset_sql($sql, $params);
    }

    /**
     * Enrol user to courses or groups.
     *
     * @param array $quizsettings
     * @param array $coursestoenrol
     * @param array $groupstoenrol
     *
     * @return string
     */
    public static function enrol_user(
        array $quizsettings,
        array $coursestoenrol,
        array $groupstoenrol): string {
        global $USER;

        // Filter for scales that are selected for enrolement.

        $enrolementarray = [];

        foreach ($coursestoenrol as $catscaleid => $data) {
            $enrolementarray = self::enrol_and_create_message_array(
                $coursestoenrol,
                $groupstoenrol,
                $quizsettings['name'],
                $catscaleid,
                $USER->id
            );
        }

        $enrolementstrings = self::create_strings_for_enrolement_notification($enrolementarray);

        if (empty($enrolementstrings['messagetitle']) && empty($enrolementstrings['messagebody'])) {
            return "";
        }

        messages::send_html_message(
            $USER->id,
            $enrolementstrings['messagetitle'] ?? "",
            $enrolementstrings['messagebody'] ?? "",
            'enrolmentfeedback'
        );
        return $enrolementstrings['messageforfeedback'] ?? "";
    }

    /**
     * Creates array with courses and groups to enrole to.
     *
     * @param array $coursestoenrol
     * @param array $groupstoenrol
     * @param string $testname
     * @param int $catscaleid
     * @param int $userid
     *
     * @return array
     *
     */
    public static function enrol_and_create_message_array(
        array $coursestoenrol,
        array $groupstoenrol,
        string $testname,
        int $catscaleid,
        int $userid
        ): array {
        global $DB, $COURSE;

        try {
            $catscale = catscale::return_catscale_object($catscaleid);
        } catch (\Exception $e) {
            $catscale = (object) ['name' => '']; // Create a dummy object.
        }

        $rolestudent = $DB->get_record('role', ['shortname' => 'student']);
        $enrolmentarray = [];
        $message = false;
        foreach ($coursestoenrol as $catscaleid => $data) {
            $message = $data['show_message'] ?? false;
            $courseids = $data['course_ids'] ?? [];
            array_push($courseids, $COURSE->id);
            foreach ($courseids as $courseid) {
                $context = \context_course::instance($courseid);
                $course = get_course($courseid);
                $url = new moodle_url('/course/view.php', ['id' => $courseid]);

                $coursedata = [];
                $coursedata['testname'] = $testname;
                $coursedata['coursename'] = $course->fullname ?? "";
                $coursedata['coursesummary'] = $course->summary ?? "";
                $coursedata['courseurl'] = $url->out() ?? "";
                $coursedata['catscalename'] = $catscale->name ?? "";

                if (!is_enrolled($context, $userid) && !empty($course) && ($courseid != $COURSE->id)) {
                    if (enrol_try_internal_enrol($courseid, $userid, $rolestudent->id)) {
                        $enrolementarray['course'][] = $coursedata;
                        self::course_enrolment_event($coursedata, $userid);
                    }
                }
                if (empty($groupstoenrol[$catscaleid])) {
                    continue;
                }
                // Inscription only for existing groups.
                $groupsofcourse = groups_get_all_groups($courseid);
                foreach ($groupsofcourse as $existinggroup) {
                    foreach ($groupstoenrol[$catscaleid] as $newgroup) {
                        if ($existinggroup->name == $newgroup) {
                            if (groups_is_member($existinggroup->id, $userid)) {
                                continue;
                            }
                            $groupmember = groups_add_member($existinggroup->id, $userid);
                            if ($groupmember) {
                                $data = [];
                                $data['testname'] = $testname;
                                $data['groupname'] = $existinggroup->name;
                                $data['groupdescription'] = $existinggroup->description ?? "";
                                $data['coursename'] = $course->fullname ?? "";
                                $url = new moodle_url('/course/view.php', ['id' => $course->id]);
                                $data['courseurl'] = $url->out();
                                $data['catscalename'] = $catscale->name ?? "";
                                $enrolmentarray['group'][] = $data;
                                self::group_enrolment_event($data, $userid);
                            }
                        }
                    }
                }
            }
        }

        if (!$message) {
            return [];
        }

        return $enrolmentarray;
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

    /**
     * Get number of correctly answered questions by scale from quizattempt.
     *
     * @param array $catscaleids
     * @param stdClass $attemptrecord
     *
     * @return array
     */
    public static function get_percentage_of_right_answers_by_scale(array $catscaleids, stdClass $attemptrecord): array {
        $quizdata = json_decode($attemptrecord->json);
        $correctanswersperscale = [];
        $progress = progress::load($attemptrecord->attemptid, 'mod_adaptivequiz', $quizdata->contextid);
        foreach ($catscaleids as $catscaleid) {
            $questionsperscale = $progress->get_playedquestions(true, $catscaleid);
            if (!$questionsperscale) {
                continue;
            }
            $correct = 0;
            if (empty($questionsperscale)) {
                continue;
            }
            $nquestions = count($questionsperscale);
            $playedqids = [];
            foreach ($questionsperscale as $question) {
                $playedqids[] = $question->componentid;
            }
            $responses = $progress->get_responses();
            foreach ($responses as $componentid => $data) {
                if (!in_array($componentid, $playedqids)) {
                    continue;
                }
                if ($data['fraction'] == 1) {
                    $correct ++;
                }
            }
            $percentage = round(($correct / $nquestions) * 100);
            $correctanswersperscale[$catscaleid] = [
                'correct' => $correct,
                'total' => $nquestions,
                'percentage' => $percentage,
            ];
        }

        return $correctanswersperscale;
    }

    /**
     * Get number personability results per scale of quizattempt .
     *
     * @param stdClass $attemptrecord
     *
     * @return object
     */
    public static function get_personabilityresults_of_quizattempt(stdClass $attemptrecord): object {
        $quizdata = json_decode($attemptrecord->json);
        return $quizdata->personabilities;
    }

    /**
     * Returns all CAT tests for a given course ID.
     *
     * @param int $courseid
     * @return mixed
     */
    public static function get_tests_for_course(int $courseid) {
        global $DB;
        return $DB->get_records('local_catquiz_tests', ['courseid' => $courseid]);
    }

    /**
     * Returns all CAT tests for the given scale in the given course
     *
     * @param int $courseid
     * @param int $scaleid
     * @return mixed
     */
    public static function get_tests_for_scale(int $courseid, int $scaleid) {
        global $DB;
        return $DB->get_records('local_catquiz_tests', ['courseid' => $courseid, 'catscaleid' => $scaleid]);
    }

    /**
     * Returns a single CAT test record.
     *
     * @param int $testid
     * @return mixed
     */
    public static function get_test_by_component_id(int $testid) {
        global $DB;
        return $DB->get_record('local_catquiz_tests', ['componentid' => $testid], '*', MUST_EXIST);
    }

    /**
     * Return the sql for questions answered per person.
     *
     * For each user, this returns the number of questions answered in the
     * given scale (or any of its subscales).
     * When a courseid is given, all participants of the course are listed.
     * Otherwise, all users enrolled into any course are listed.
     *
     * Note that the courseid parameter just changes the list of selected
     * users. The number of answers will be the same, as this depends only on
     * the selected scale.
     *
     * @param int $contextid
     * @param int $scaleid
     * @param ?int $courseid
     *
     * @return array
     *
     */
    public static function get_sql_for_questions_answered_per_person(int $contextid, int $scaleid, ?int $courseid = null) {
        global $DB;

        $catscaleids = [$scaleid, ...catscale::get_subscale_ids($scaleid)];

        // Get questions answered for the given context.
        list (, $from, $where, $params) = self::get_sql_for_stat_base_request([], [$contextid]);
        [$insql, $inparams] = $DB->get_in_or_equal($catscaleids, SQL_PARAMS_NAMED, 'incatscales');
        $params = array_merge($params, $inparams, ['catscaleid' => $scaleid]);
        $where2 = '1=1';
        $where3 = "lci.catscaleid $insql";
        if ($courseid) {
            $where2 .= ' AND e.courseid = :courseid';
            $where3 .= ' AND a.course = :courseid2';
            $params = array_merge($params, ['courseid' => $courseid, 'courseid2' => $courseid]);
        }

        $sql = "SELECT DISTINCT ue.userid, COALESCE(answercount, 0) total_answered, lcp.ability
                FROM {enrol} e
                JOIN {user_enrolments} ue ON e.id = ue.enrolid
                JOIN {role} r ON e.roleid = r.id AND r.shortname = 'student'
                LEFT JOIN (
                    SELECT s1.userid, COUNT(*) as answercount
                    FROM (
                        SELECT qas.id, qas.userid, qa.questionid, qa.questionusageid
                        FROM $from
                        WHERE $where
                    ) s1
                    JOIN {local_catquiz_items} lci ON lci.componentname = 'question' AND s1.questionid = lci.componentid
                    -- Only select questions that have item params.
                    JOIN {local_catquiz_itemparams} lcip ON lci.id = lcip.componentid
                    -- Make sure we only get responses from the quizzes in the given course.
                    JOIN {adaptivequiz_attempt} aa ON aa.uniqueid = s1.questionusageid
                    JOIN {adaptivequiz} a ON a.id = aa.instance
                    WHERE $where3
                    GROUP BY s1.userid
                ) s2 ON ue.userid = s2.userid
                LEFT JOIN {local_catquiz_personparams} lcp ON ue.userid = lcp.userid AND lcp.catscaleid = :catscaleid
                WHERE $where2";
        return [$sql, $params];
    }

    /**
     * Return the number of attempts per person
     *
     * @param int $contextid
     * @param int $scaleid
     * @param ?int $courseid
     */
    public static function get_sql_for_attempts_per_person(int $contextid, int $scaleid, ?int $courseid) {
        $where = "1 = 1";
        $params = [
            'catscaleid' => $scaleid,
            'contextid' => $contextid,
        ];
        if ($courseid) {
            $where = "e.courseid = :courseid";
            $params = array_merge($params, ['courseid' => $courseid]);
        }

        // Subquery s3  summarizes the attempts for multiple courses. This is
        // used, if the shortcode is used outside a course and we want to know
        // all attempt across all courses.
        // Subquery s2 gets the number of attempts per course/context combination for all users
        // The outermost query takes the highest (MAX) ability in case we have
        // multiple abilities for the given user.
        $sql = "SELECT s3.userid, MAX(s3.ability) ability, SUM(attemptcount) attempts
                FROM (
                    SELECT s2.userid, s2.ability, SUM(attemptcount) attemptcount
                    FROM (
                        SELECT ue.userid, lcp.ability, s1.courseid, COALESCE(attemptcount, 0) attemptcount
                        FROM {enrol} e
                        JOIN {user_enrolments} ue ON e.id = ue.enrolid
                        JOIN {role} r ON e.roleid = r.id
                        LEFT JOIN (
                            SELECT a.userid, a.contextid, a.courseid, COUNT(*) as attemptcount
                            FROM {local_catquiz_attempts} a
                            WHERE a.contextid = :contextid
                            GROUP BY a.userid, a.contextid, a.courseid
                        ) s1 ON ue.userid = s1.userid AND e.courseid = s1.courseid
                        LEFT JOIN {local_catquiz_personparams} lcp ON
                            ue.userid = lcp.userid
                            AND lcp.catscaleid = :catscaleid
                            AND lcp.contextid = s1.contextid
                        WHERE $where
                    ) s2
                    GROUP BY s2.userid, s2.ability
                    ORDER BY attemptcount
                ) s3
                GROUP BY s3.userid
                ORDER BY attempts";

        return [$sql, $params];
    }

    /**
     * Returns the data for the CSV export of caquiz attempts.
     *
     * @param int $contextid
     * @param int $scaleid
     * @param ?int $courseid
     * @param ?int $testid
     * @param ?int $starttime
     * @param ?int $endtime
     * @param bool $enrolled
     *
     * @return array
     */
    public static function get_sql_for_csv_export(
        int $contextid,
        int $scaleid,
        ?int $courseid,
        ?int $testid,
        ?int $starttime,
        ?int $endtime,
        bool $enrolled = true
    ): array {
        $params = [
            'contextid' => $contextid,
            'scaleid' => $scaleid,
            'courseid' => $courseid,
            'testid' => $testid,
            'starttime' => $starttime,
            'endtime' => $endtime,
        ];
        $where = "a.contextid = :contextid AND a.scaleid = :scaleid";
        $join = "";
        if ($courseid) {
            $where .= " AND a.courseid = :courseid";
            if ($enrolled) {
                $join = <<<SQL
                    JOIN (SELECT DISTINCT ue.userid, e.courseid
                      FROM {user_enrolments} ue
                      JOIN {enrol} e ON ue.enrolid = e.id
                      -- JOIN {role} r ON e.roleid = r.id AND r.shortname = 'student'
                      ) userenroll ON a.userid = userenroll.userid
                        AND a.courseid = userenroll.courseid
                SQL;
            }
        }
        if ($testid) {
            $where .= " AND a.instanceid = :testid";
        }

        if ($starttime) {
            $where .= " AND a.starttime >= :starttime";
        }

        if ($endtime) {
            $where .= " AND a.starttime <= :endtime";
        }

        $sql = "SELECT a.attemptid,
            a.userid,
            u.username,
            u.firstname,
            u.lastname,
            u.email,
            a.starttime,
            a.endtime,
            a.teststrategy,
            a.status,
            a.number_of_testitems_used,
            a.personability_after_attempt,
            a.json
            FROM {local_catquiz_attempts} a
            JOIN {user} u ON a.userid = u.id
            $join
            WHERE $where
            ORDER BY attemptid DESC";
        return [$sql, $params];
    }

    /**
     * Set the activeparamid field of the given item.
     *
     * Selects one of the itemparams associated with the given item and sets it
     * in the activeparamsid DB column.
     *
     * @param int $itemid
     * @param ?int $activeparamid
     * @return void
     */
    public static function set_active_itemparam(int $itemid, ?int $activeparamid = null) {
        global $DB;
        $itemparams = $DB->get_records('local_catquiz_itemparams', ['itemid' => $itemid]);

        // Check if the given paramid is in the list of available itemparams for this item.
        if ($activeparamid && !in_array($activeparamid, array_map(fn ($ip) => $ip->id, $itemparams))) {
            // TODO: Log an error once the logger is merged.
            throw new InvalidArgumentException(
                sprintf(
                    'Given activeparamid %d does not belong to the given itemid %d'
                )
            );
        }

        // If no itemparamid is given, select the one with the highest status.
        if (!$activeparamid) {
            // Find the itemparam that should be selected.
            $sortfun = function ($a, $b) {
                return $a->status - $b->status;
            };
            usort($itemparams, $sortfun);
            $activeparamid = $itemparams[0]->id;
        }

        $dataobject = (object) ['id' => $itemid, 'activeparamid' => $activeparamid];
        $DB->update_record('local_catquiz_items', $dataobject);
    }

    /**
     * Return the item param with the given id
     *
     * @param int $id
     * @return ?stdClass
     */
    public static function get_item_param(int $id): ?stdClass {
        global $DB;
        if (!$record = $DB->get_record('local_catquiz_itemparams', ['id' => $id])) {
            return null;
        }
        return $record;
    }

    /**
     * Returns the itemparam for the given conditions
     *
     * @param array $conditions Use field as array key and required value as array value, e.g. ['contextid' => 1]
     * @return ?stdClass
     */
    public static function get_itemparams_for($conditions = []): ?stdClass {
        global $DB;
        $record = $DB->get_record('local_catquiz_itemparams', $conditions);
        return $record ?: null;
    }

    /**
     * Save an item param
     *
     * @param stdClass $record The record to save
     * @return int
     */
    public static function save_item_param(stdClass $record): int {
        global $DB;
        $record->timemodified = time();
        $id = $DB->insert_record('local_catquiz_itemparams', $record);
        return $id;
    }

    /**
     * Update an existing item param
     *
     * @param stdClass $record
     * @return int
     */
    public static function update_item_param(stdClass $record): int {
        global $DB;
        $record->timemodified = time();
        $DB->update_record('local_catquiz_itemparams', $record);
        return $record->id;
    }

    /**
     * Retrieve an item based on the context ID, component ID, and component name.
     *
     * @param int $contextid The context ID for the item.
     * @param int $componentid The ID of the component.
     * @param string $componentname The name of the component.
     * @return stdClass The record object retrieved from the database.
     */
    public static function get_item(int $contextid, int $componentid, string $componentname): stdClass {
        global $DB;
        return $DB->get_record(
            'local_catquiz_items',
            [
                'contextid' => $contextid,
                'componentid' => $componentid,
                'componentname' => $componentname,
            ]
        );
    }

    /**
     * Retrieve an item together with its parameters.
     *
     * @param int $componentid The ID of the component.
     * @param string $model The model of the item parameter
     * @param int $contextid The context of the item parameter.
     *
     * @return ?stdClassThe record object retrieved from the database or null if not found.
     */
    public function get_item_with_params(int $componentid, string $model, int $contextid): ?stdClass {
        global $DB;

        $sql = <<<SQL
                SELECT *
                FROM {local_catquiz_items} i
                JOIN {local_catquiz_itemparams} ip ON ip.itemid = i.id
                    AND ip.contextid = :contextid
                WHERE i.componentid = :componentid
                    AND ip.model = :model
SQL;
        return $DB->get_record_sql(
            $sql,
            [
                'componentid' => $componentid,
                'model' => $model,
                'contextid' => $contextid,
            ]
        ) ?: null;
    }

    /**
     * Update an item record in the 'local_catquiz_items' table.
     *
     * @param stdClass $item The item object containing the updated data.
     *
     * @return void
     */
    public static function update_item(stdClass $item): void {
        global $DB;
        $DB->update_record('local_catquiz_items', $item);
    }

    /**
     * Check if a context is actively used by any test.
     *
     * @param int $contextid The context ID to check
     *
     * @return bool True if the context is used by any test, false otherwise
     */
    public static function is_active_context(int $contextid): bool {
        global $DB;
        return $DB->record_exists('local_catquiz_tests', ['contextid' => $contextid]);
    }

    /**
     * Returns all scales for the active contexts
     *
     * @return array
     * @throws dml_exception
     */
    public static function get_all_scales_for_active_contexts(): array {
        global $DB;
        $now = time();
        // Get all contexts.
        $contexts = $DB->get_records_sql(
            <<<SQL
                SELECT DISTINCT s.*
                FROM {local_catquiz_catscales} s
                JOIN {local_catquiz_catcontext} cc ON s.contextid = cc.id
                WHERE s.contextid IS NOT NULL
                AND cc.starttimestamp <= :now1 AND cc.endtimestamp >= :now2
            ;
            SQL,
            [
                'now1' => $now,
                'now2' => $now,
            ]
        );
        return $contexts;
    }

    /**
     * Returns the state of questions that we will not consider as completed
     *
     * @return array
     */
    private static function get_unfinished_question_states() {
        return [
            'notstarted',
            'unprocessed',
            'todo',
            'invalid',
            'complete',
        ];
    }

        /**
         * Create items in a new context.
         *
         * For each item parameter in the new context:
         * 1. Get the corresponding item from either context
         * 2. If an item is in active use in the old context (used by a test), create a new item (copy).
         * 3. Otherwise update the existing item with new context and active parameter
         * 4. Update all parameters to point to the correct item
         *
         * @param int $newcontextid The ID of the context to create items in
         * @param ?int $oldcontextid The ID of the old context, if any
         * @return void
         */
    public function create_items_in_new_context(int $newcontextid, ?int $oldcontextid): void {
        global $DB;

        // Copy item parameters that are present in the old context but not in
        // the new one from old context to the new.
        // E.g., if we fetched 100 params from the central instance but locally we have 150, copy
        // the remaining 50 params.
        if ($oldcontextid) {
            $remainingparams = $this->get_params_from_old_context($oldcontextid, $newcontextid);
            $remainingparams = array_map(
                function ($param) use ($newcontextid) {
                    $param->contextid = $newcontextid;
                    $param->id = null;
                    return $param;
                },
                $remainingparams
            );
            $DB->insert_records('local_catquiz_itemparams', $remainingparams);
        }

        // Decide if we should just replace existing items with the new contextid or create new items.
        $createnew = false;
        if (!$oldcontextid || $this->is_active_context($oldcontextid)) {
            $createnew = true;
        }

        // Create a mapping of questionid -> item.
        // If it exists in both old and new context, the mapping maps to the new context.
        $qid2item = [];
        $contextids = $oldcontextid
            ? array_reverse(range($oldcontextid, $newcontextid))
            : [$newcontextid];
        [$insql, $inparams] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'contextid');
        foreach ($DB->get_records_select('local_catquiz_items', "contextid $insql", $inparams) as $i) {
            if (!isset($qid2item[$i->componentid]) || $i->contextid > $qid2item[$i->componentid]->contextid) {
                $qid2item[$i->componentid] = $i;
            }
        }

        $activeparam = [];
        $transaction = $DB->start_delegated_transaction();
        $newparams = $DB->get_records('local_catquiz_itemparams', ['contextid' => $newcontextid]);
        $qid2params = [];
        foreach ($newparams as $np) {
            $qid2params[$np->componentid][] = $np;
        }

        $tosave = [];

        foreach ($newparams as $ip) {
            $questionid = $ip->componentid;
            $item = $qid2item[$questionid];

            if ($createnew && $item->contextid == $oldcontextid) {
                unset($item->id);
            }

            // For each itemparam in the new context, get the one with the
            // highest `status` value. If there a multiple, pick the first one.
            if (
                !array_key_exists($questionid, $activeparam)
                || $ip->status > $activeparam[$questionid]->status
            ) {
                $activeparam[$questionid] = $ip;
                $item->activeparamid = $ip->id;
                $tosave[$questionid] = $item;
            }
        }

        foreach ($tosave as $questionid => $item) {
            if ($createnew && $item->contextid == $oldcontextid) {
                $item->contextid = $newcontextid;
                $itemid = $DB->insert_record('local_catquiz_items', $item, true);
            } else {
                // This item is already in the database because it was 1)
                // inserted when itemparams were fetched or 2) it existed
                // already and will be udpated.
                $item->contextid = $newcontextid;
                $DB->update_record('local_catquiz_items', $item);
                $itemid = $item->id;
            }
            foreach ($qid2params[$questionid] as $p) {
                $p->itemid = $itemid;
                $DB->update_record('local_catquiz_itemparams', $p, true);
            }
        }
        $DB->commit_delegated_transaction($transaction);
    }

    /**
     * Get scales by their labels.
     * @param array $labels Array of scale labels
     * @return array Array of scale objects
     */
    public function get_scales_by_labels(array $labels) {
        global $DB;

        if (empty($labels)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($labels);
        return $DB->get_records_select('local_catquiz_catscales', "label $insql", $params);
    }

    /**
     * Count unprocessed remote responses for a scale and its subscales.
     *
     * @param array $catscaleids Array of scale IDs (main scale and subscales)
     * @param int $contextid The context ID
     * @return int Number of unprocessed responses
     */
    public function count_unprocessed_remote_responses(array $catscaleids, int $contextid): int {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal($catscaleids, SQL_PARAMS_NAMED, 'incatscales');
        $params['contextid'] = $contextid;

        $sql = "SELECT COUNT(*)
            FROM {local_catquiz_rresponses} rr
            JOIN {local_catquiz_qhashmap} qh ON rr.questionhash = qh.questionhash
            JOIN {local_catquiz_items} lci ON lci.componentid = qh.questionid
            WHERE lci.catscaleid $insql
            AND lci.contextid = :contextid
            AND rr.timeprocessed IS NULL";

        return $DB->count_records_sql($sql, $params);
    }

    /**
     * Mark remote responses as processed for a scale and its subscales.
     *
     * @param array $catscaleids Array of scale IDs (main scale and subscales)
     * @param int $contextid The context ID where the responses were processed
     * @return void
     */
    public function mark_remote_responses_processed(array $catscaleids, int $contextid): void {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal($catscaleids, SQL_PARAMS_NAMED, 'incatscales');
        $params['contextid'] = $contextid;
        $params['now'] = time();
        $params['info'] = json_encode(['status' => 'success', 'contextid' => $contextid]);

        $DB->execute(
            "UPDATE {local_catquiz_rresponses}
            SET timeprocessed = :now, processinginfo = :info
            WHERE questionhash IN (
                SELECT qh.questionhash
                FROM {local_catquiz_qhashmap} qh
                JOIN {local_catquiz_items} lci ON lci.componentid = qh.questionid
                WHERE lci.catscaleid $insql
                AND lci.contextid = :contextid
        )
        AND timeprocessed IS NULL",
            $params
        );
    }

    /**
     * Get remote responses for a main scale and its subscales in a given context.
     *
     * @param int $mainscale The ID of the main scale
     * @param ?int $contextid Optional context ID. If not provided, will be determined from main scale
     * @return array Array of response records containing id, questionhash, attempthash and response
     */
    public function get_remote_responses(int $mainscale, ?int $contextid): array {
        global $DB;

        if (!$contextid) {
            $contextid = catscale::get_context_id($mainscale);
        }
        $subscales = catscale::get_subscale_ids($mainscale);
        $selectedscales = [$mainscale, ...$subscales];
        [$insql, $inparams] = $DB->get_in_or_equal($selectedscales, SQL_PARAMS_NAMED, 'selectedscales');

        $sql = "SELECT rr.id, rr.questionhash, attempthash, response
            FROM {local_catquiz_rresponses} rr
            JOIN {local_catquiz_qhashmap} qh ON rr.questionhash = qh.questionhash
            JOIN {local_catquiz_items} lci ON lci.componentid = qh.questionid AND lci.catscaleid $insql
            AND lci.contextid = :contextid";

        $params = array_merge(['contextid' => $contextid], $inparams);

        $records = $DB->get_records_sql($sql, $params);
        return $records;
    }

    /**
     * Get the last synced context ID for a given CAT scale.
     *
     * @param int $catscaleid The ID of the CAT scale to check
     * @return ?int The context ID of the last sync, or null if never synced
     */
    public function get_last_synced_context_id(int $catscaleid): ?int {
        global $DB;

        $sql = <<<SQL
            SELECT contextid
            FROM {local_catquiz_sync_event}
            WHERE catscaleid = :catscaleid
            ORDER BY id DESC
            LIMIT 1
        SQL;

        $params = ['catscaleid' => $catscaleid];
        if (!$record = $DB->get_record_sql($sql, $params)) {
            return null;
        }
        return $record->contextid;
    }

    /**
     * Save the given sync event to the database
     *
     * @param stdClass $data
     *
     * @return void
     */
    public function save_sync_event(stdClass $data): void {
        global $DB;

        $DB->insert_record('local_catquiz_sync_event', $data);
    }

    /**
     * Returns the context ids of that are in between the given start- and end-context
     * for the given scale.
     *
     * @param int $catscaleid
     * @param int $oldcontextid
     * @param int $newcontextid
     *
     * @return array
     */
    public static function get_intermediate_context_ids(int $catscaleid, int $oldcontextid, int $newcontextid): array {
        global $DB;

        $sql = <<<SQL
            SELECT contextid
            FROM {local_catquiz_sync_event}
            WHERE catscaleid = :catscaleid
            AND contextid >= :oldcontextid
            AND contextid <= :newcontextid
            ORDER BY contextid
        SQL;
        $params = [
            'oldcontextid' => $oldcontextid,
            'newcontextid' => $newcontextid,
            'catscaleid' => $catscaleid,
        ];
        $intermediatecontextids = $DB->get_fieldset_sql(
            $sql,
            $params
        );
        return $intermediatecontextids;
    }

    /**
     * Returns itemparams that are present in the oldcontext but not the new one
     *
     * @param int $oldcontextid
     * @param int $newcontextid
     *
     * @return array
     */
    public function get_params_from_old_context(int $oldcontextid, int $newcontextid): array {
        global $DB;
        $sql = <<<SQL
            SELECT *
            FROM {local_catquiz_itemparams} itemsouter
            WHERE contextid = :oldcontextid
            AND itemsouter.componentid NOT IN (
                SELECT componentid
                FROM {local_catquiz_itemparams}
                WHERE contextid = :newcontextid
            )
        SQL;
        $params = [
            'oldcontextid' => $oldcontextid,
            'newcontextid' => $newcontextid,
        ];
        return $DB->get_records_sql($sql, $params);
    }
}
