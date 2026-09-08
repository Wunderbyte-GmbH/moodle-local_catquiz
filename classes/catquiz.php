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
use local_catquiz\local\model\model_person_param;
use local_catquiz\event\usertocourse_enroled;
use local_catquiz\event\usertogroup_enroled;
use local_catquiz\local\status;
use local_catquiz\teststrategy\progress;
use moodle_exception;
use moodle_url;
use question_engine;
use stdClass;
use local_catquiz\local\itemparam_validity;

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
        $from = "( SELECT q.id, q.name, q.qtype, qc.name as categoryname
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
     * The column aliases the item pool query produces.
     *
     * Read off a live result rather than transcribed from the heredoc: the first
     * version of this list was written by hand from the SQL and had 'contextid' where
     * the alias is 'lcipcontextid', plus six columns missing entirely. The query
     * failed with "column s.contextid does not exist".
     *
     * If a column is added to the query and forgotten here, the lean select simply
     * does not carry it - which the equivalence test catches, because the runtime
     * pool would then differ from the full one.
     *
     * @return string[]
     */
    public static function pool_columns(): array {
        return [
            'id',
            'componentid',
            'label',
            'idnumber',
            'questionname',
            'qtype',
            'categoryname',
            'catscaleid',
            'testitemstatus',
            'component',
            'itemid',
            'catscalename',
            'lccscatscaleid',
            'model',
            'difficulty',
            'discrimination',
            'guessing',
            'json',
            'timecreated',
            'timemodified',
            'status',
            'usable',
            'itemparamvalidity',
            'lcipcontextid',
            'attempts',
            'astatlastattempttime',
            'userid',
            'userattempts',
            'userlastattempttime',
        ];
    }

    /**
     * Columns of the item pool that only the manager interface needs.
     *
     * The same query serves two consumers: the runtime selection, which fills the
     * item cache, and the CAT manager tables, which display and filter questions.
     * The interface needs these names; the selection never reads them - it works on
     * ids, scale ids and item parameters.
     *
     * Measured over 2.000 rows they are 36 % of the serialised payload, and that
     * payload is cached per scale and context: 206 MB at 250.000 items.
     *
     * @var string[]
     */
    const DISPLAYONLY_POOL_COLUMNS = [
        'questionname',
        'categoryname',
        'catscalename',
    ];

    /**
     * Returns the sql to get all the questions wanted.
     *
     * @param array $catscaleids
     * @param int $contextid
     * @param array $wherearray
     * @param int $userid
     * @param string|null $orderby If given, order by the given field in ascending order
     * @param int|null $questionid If given, restrict the query to this single question
     * @param bool $leanselect Omit the columns only the manager interface displays
     *
     * @return array
     *
     */
    public static function return_sql_for_catscalequestions(
        array $catscaleids,
        int $contextid,
        array $wherearray = [],
        int $userid = 0,
        ?string $orderby = null,
        ?int $questionid = null,
        bool $leanselect = false
    ) {

        global $DB;
        if ($contextid === 0) {
            $contextid = self::get_default_context_id();
        }

        // Start the params array.
        $params = [
            'contextid' => $contextid,

        ];
        // Items in piloting have no active parameter, so lcipcontextid is
        // NULL for them. Restricting on it alone would drop exactly those items again
        // after the join was widened - and their attempt numbers are the interesting
        // part while an item is being piloted.
        // The condition itself is appended below, parenthesised: an OR inside the
        // generic wherecontains loop would bind looser than the surrounding ANDs and
        // silently widen the whole WHERE clause.

        // If we fetch only for a given user, we need to add this to the sql.
        if (!empty($userid)) {
            $params['userid'] = $userid;
            $params['statuserid'] = $userid;
        }

        // The detail view needs exactly one question. Restricting the
        // innermost query keeps the expensive statistics joins from aggregating
        // over the whole scale first and discarding the rest afterwards - which is
        // what exhausted the memory limit on large, image heavy pools.
        $questionfilter = '';
        if (!empty($questionid)) {
            $questionfilter = ' AND q.id = :detailquestionid ';
            $params['detailquestionid'] = $questionid;
        }

        $insql = '';
        if (!empty($catscaleids) && $catscaleids[0] > 0) {
            $globalscaleids = self::get_global_scale($catscaleids);

            [$parentscales1, $inparams1] = $DB->get_in_or_equal($globalscaleids, SQL_PARAMS_NAMED, 'inparentscales1');
            [$parentscales2, $inparams2] = $DB->get_in_or_equal($globalscaleids, SQL_PARAMS_NAMED, 'inparentscales2');
            // The statistics subqueries restrict by scale themselves, so
            // they need their own placeholders - reusing the ones of the outer joins
            // would bind the same names twice for different clauses.
            [$parentscales3, $inparams3] = $DB->get_in_or_equal($globalscaleids, SQL_PARAMS_NAMED, 'inparentscales3');
            [$parentscales4, $inparams4] = $DB->get_in_or_equal($globalscaleids, SQL_PARAMS_NAMED, 'inparentscales4');
            $params = array_merge($params, $inparams1, $inparams2, $inparams3, $inparams4);
            $params['statcontextid'] = $contextid;
            $params['statcontextid2'] = $contextid;

            [$incatscales, $inparams] = $DB->get_in_or_equal($catscaleids, SQL_PARAMS_NAMED, 'incatscales');
            $params = array_merge($params, $inparams);
            $wherecontains['lccscatscaleid'] = $incatscales;
        }

        // The derived table below still computes every column; what changes is how
        // much of it is transferred and turned into PHP objects. That is where the
        // cost sits - the cache holds the hydrated rows, not the query.
        $select = "*";
        if ($leanselect) {
            $select = implode(', ', array_map(
                fn($column) => 's.' . $column,
                array_diff(self::pool_columns(), self::DISPLAYONLY_POOL_COLUMNS)
            ));
        }

        $from = <<<SQL
        ( SELECT
            -- Information about the question
            q.id,
            lci.componentid,
            qbe.idnumber as label,
            COALESCE (qbe.idnumber, CAST(qbe.id AS CHAR)) as idnumber,
            q.name as questionname,
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
            -- Issue #54: persisted so the backend can filter and sort on it; NULL for
            -- items in piloting, which have no active parameter row at all.
            lcip.usable,
            -- The visible column carries this name, and the table sorts by whatever
            -- the clicked header is called. Without the alias an ORDER BY on it would
            -- refer to a column that does not exist.
            lcip.usable AS itemparamvalidity,
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

          -- Get the active item parameter, if there is one.
          --
          -- Issue #54: this used to be an INNER JOIN, so items without parameters -
          -- or without an *active* parameter - never appeared in the list at all.
          -- Those items are exactly the ones in piloting, and their statistics
          -- (attempt counts, last attempt) are of interest precisely while they are
          -- being piloted. A LEFT JOIN keeps them visible; the parameter columns are
          -- then NULL, which the validity column reports as "no parameters".
            LEFT JOIN {local_catquiz_itemparams} lcip
              ON lcip.itemid = lci.id AND lci.activeparamid = lcip.id

          -- Get all information about the question from the questionbank itself
            JOIN {question} q ON q.id=lci.componentid $questionfilter
            JOIN {question_versions} qv ON qv.questionid=q.id
            JOIN {question_bank_entries} qbe ON qbe.id=qv.questionbankentryid
            JOIN {question_categories} qc ON qc.id=qbe.questioncategoryid

          -- Get all information about the attempts in the scale(s)
          -- and context(s) in general and for specific user(s)
            -- Issue #21: the restriction to context and scales lives inside the
            -- aggregation, not only in the outer join. Without it this subquery
            -- aggregated every CAT attempt of the whole site before a single row was
            -- discarded, and an outer LIMIT did nothing to shrink that work.
            --
            -- COUNT(DISTINCT qa.id): a question attempt has one step per interaction,
            -- and the join to question_attempt_steps multiplies the rows accordingly.
            -- A plain COUNT counted steps and reported them as attempts.
            LEFT JOIN (SELECT lca.scaleid, lca.contextid, qa.questionid,
                COUNT(DISTINCT qa.id) numberattempts,
              MAX(qas.timecreated) as lastattempt
              FROM {local_catquiz_attempts} lca
              JOIN {adaptivequiz_attempt} aqa ON lca.attemptid = aqa.id
              JOIN {question_attempts} qa ON qa.questionusageid = aqa.uniqueid
              JOIN {question_attempt_steps} qas
                ON qas.questionattemptid = qa.id AND qas.fraction IS NOT NULL
              WHERE lca.contextid = :statcontextid AND lca.scaleid $parentscales3
              GROUP BY lca.scaleid, lca.contextid, qa.questionid
            ) astat
              -- Issue #54: joined on lcip.contextid before, which is NULL for items
              -- without an active parameter - so pilot items lost their statistics,
              -- the very numbers that matter while an item is being piloted. The
              -- subquery already restricts the context itself (issue #21), so
              -- matching the context here again was redundant anyway.
              ON astat.questionid = q.id
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
                        COUNT(DISTINCT qa.id) numberattempts,
                        MAX(qas.timecreated) as lastattempt
                    FROM {local_catquiz_attempts} lca
                      JOIN {adaptivequiz_attempt} aqa ON lca.attemptid = aqa.id
                      JOIN {question_attempts} qa ON qa.questionusageid = aqa.uniqueid
                      JOIN {question_attempt_steps} qas
                        ON qas.questionattemptid = qa.id AND qas.fraction IS NOT NULL
                    WHERE lca.userid = :statuserid AND lca.contextid = :statcontextid2
                      AND lca.scaleid $parentscales4
                    GROUP BY lca.scaleid, lca.contextid, qa.questionid, lca.userid
                ) ustat
                  ON ustat.userid = :userid AND ustat.questionid = q.id
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

        // Items in piloting have no active parameter, so lcipcontextid is
        // NULL for them. Restricting on it alone would drop exactly those items after
        // the parameter join was widened - and their attempt numbers are the
        // interesting part while an item is being piloted.
        $where .= sprintf(' AND (lcipcontextid = %d OR lcipcontextid IS NULL)', (int) $contextid);

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

        [, $contextfrom, , $params] = self::get_sql_for_stat_base_request();
        $select = "id,
                idnumber,
                name,
                qtype,
                categoryname,
                'question' as component,
                -- Issue #58: the attempt count is no longer part of this query. It
                -- was produced by aggregating every question attempt of the context -
                -- 2.1 million steps in the measured instance, roughly eight seconds -
                -- and then thrown away for all but the ten rows on screen. The table
                -- fetches it for the visible page instead.
                0 as questioncontextattempts";
        // The list of scales a question belongs to used to be built with
        // GROUP_CONCAT into a string like '-3--7-' and then filtered with
        // LIKE '%-3-%'. That string was never displayed - it existed only to express
        // "not already assigned to this scale" - and a leading-wildcard LIKE cannot
        // use an index, so the filter forced a scan and the aggregation forced a
        // GROUP BY over the whole result. NOT EXISTS states the same condition
        // directly and is served by the (catscaleid, componentname, componentid)
        // index added in issue #25.
        $from = "( SELECT q.id, qbe.idnumber, q.name, q.qtype, qc.name as categoryname
            FROM {question} q
                -- Issue #22: the current version used to be found by numbering EVERY
                -- row of question_versions with a window function and then keeping
                -- n = 1. That materialises the whole version history of the site
                -- before a single row is discarded, and a window function cannot use
                -- an index for it.
                --
                -- The condition below says: no newer version of the same bank entry
                -- exists. That expresses the same
                -- thing as a correlated check, which is served by the index on
                -- questionbankentryid. It also stays portable: no window function,
                -- so PostgreSQL and MariaDB behave alike.
                JOIN {question_versions} qv
                ON qv.questionid = q.id
                   AND NOT EXISTS (
                       SELECT 1
                       FROM {question_versions} qvnewer
                       WHERE qvnewer.questionbankentryid = qv.questionbankentryid
                         AND qvnewer.version > qv.version
                   )
                JOIN {question_bank_entries} qbe ON qv.questionbankentryid=qbe.id
                JOIN {question_categories} qc ON qc.id=qbe.questioncategoryid
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM {local_catquiz_items} lci
                    WHERE lci.componentid = q.id
                      AND lci.componentname = 'question'
                      AND lci.catscaleid = :notassignedscaleid
                )
            ) as s1";

        $where = '1=1';
        $params['notassignedscaleid'] = $catscaleid;
        $params['contextid'] = $contextid;

        // Only bound when $contextfilter above actually references it: with
        // contextid 0 the default context is identified by its JSON flag. This was
        // briefly dropped while removing the GROUP_CONCAT filter for issue #22,
        // which would have broken exactly the "no context given" path.
        if ($contextid === 0) {
            $params['default'] = '%"default":true%';
        }
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
         [, $from, $where, $params] = self::get_sql_for_stat_base_request($testitemids, $contextids, $studentids);

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
         [, $from, $where, $params] = self::get_sql_for_stat_base_request($testitemids, $contextids);

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
        [, $from, $where, $params] = self::get_sql_for_stat_base_request($testitemids, $contextids, $studentids);

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
        [$select, $from, $where, $params] = self::get_sql_for_stat_base_request($testitemids, $contextids, $studentids);

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
         [, $from, $where, $params] = self::get_sql_for_stat_base_request($testitemids, $contextids);

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

         [, $from, $where, $params] = self::get_sql_for_stat_base_request($testitemids, $contextids);

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
         [, $from, $where, $params] = self::get_sql_for_stat_base_request($testitemids, [$contextid], $userids);

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

        // Audit (Expertise part C): the previous query keyed on
        // max(questionattemptid) - the question attempt with the highest id, i.e.
        // the last ADDED question. That is not the same as the last ANSWERED
        // question: if the most recently added item had not been answered yet,
        // the finished-state filter matched nothing and the whole lookup returned
        // null although earlier items were answered; it also assumed attempt-id
        // order equals administration order. Instead take the highest slot (the
        // administration order) that has a finished answer step, and within it the
        // final step, so questionattemptid, slot, questionid, fraction and
        // responsesummary always belong to one and the same answered question.
        $sql = <<<SQL
        SELECT
            qs.id,
            qs.questionattemptid,
            qa.slot,
            qs.state,
            qs.fraction originalfraction,
            ROUND(qs.fraction, 3) fraction,
            qs.timecreated,
            qs.userid,
            qa.questionusageid,
            qa.questionid,
            qa.questionsummary,
            qa.rightanswer,
            qa.responsesummary,
            qa.timemodified
        FROM {question_attempt_steps} qs
        JOIN {question_attempts} qa ON qs.questionattemptid = qa.id
        WHERE qa.questionusageid = :questionusageid
          AND qs.state NOT $unfinishedstatessql
        ORDER BY qa.slot DESC, qs.sequencenumber DESC, qs.id DESC
        SQL;

        $params = $unfinishedstatesparams;
        $params['questionusageid'] = $questionusageid;
        $records = $DB->get_records_sql($sql, $params, 0, 1);
        return $records ? reset($records) : false;
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
         [, $from, $where, $params] = self::get_sql_for_stat_base_request([], [$contextid]);

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
        [, $from, $where, $params] = self::get_sql_for_stat_base_request($testitemids, $contextids, $studentids);

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
        array $filterarray = []
    ) {
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
        int $userid = -1
    ) {

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
        int $userid = -1
    ): array {

        $sql = "SELECT
        attemptid, contextid, userid, endtime, timemodified, json, debug_info,
        -- The strategy is a column of its own. Reading it only from the JSON payload
        -- fails for attempts whose payload predates that field, and the feedback then
        -- cannot be built at all.
        teststrategy
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
        array $filterarray = []
    ) {

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
     * Upper bound for the number of data points a chart query returns.
     *
     * The classification already collapses a cohort into a handful of
     * counts, but a misconfigured class width could still produce a long tail of
     * near-empty classes. This caps what leaves the database; the charts themselves
     * never draw more than ATTEMPTS_PER_PERSON_CLASSES classes anyway.
     */
    const CHART_MAX_DATA_POINTS = 500;

    /**
     * Returns the largest value of a column within a subquery.
     *
     * Finding a maximum by loading every row and looping in PHP makes the
     * cost grow with the cohort. The database answers it with a single value.
     *
     * @param string $innersql A complete SELECT usable as a subquery.
     * @param array $params Its parameters.
     * @param string $column The column to take the maximum of.
     * @return int
     */
    public static function get_max_from_subquery(string $innersql, array $params, string $column): int {
        global $DB;

        return (int) $DB->get_field_sql("SELECT MAX($column) FROM ($innersql) sub", $params);
    }

    /**
     * Classifies one row per person into (range, class) counts inside the database.
     *
     * Both attempt charts only ever needed the number of people per range
     * and class - they counted rows they had loaded. This does the counting in SQL,
     * so only the finished numbers travel back.
     *
     * The class follows feedback_helper::get_histogram_bin(): value 0 forms class 0,
     * everything else is ceil(value / classwidth), which already leaves class 0 free.
     * The range follows feedback_helper::get_feedback_range_index(): half-open
     * intervals with the topmost one closed. The boundaries are bound parameters, and
     * a test compares both implementations directly so they cannot drift apart.
     *
     * @param string $innersql A complete SELECT yielding one row per person.
     * @param array $params Its parameters.
     * @param string $valuecolumn Column holding the count to classify.
     * @param int $classwidth Width of one class; at least 1.
     * @param array $ranges List of ['lower' => float, 'upper' => float], in order.
     * @param int $unmatchedrange Range for a value outside every configured range.
     * @return array<int, array<int, int>> Count keyed by range index, then class.
     */
    public static function aggregate_person_histogram(
        string $innersql,
        array $params,
        string $valuecolumn,
        int $classwidth,
        array $ranges,
        int $unmatchedrange = 0
    ): array {
        global $DB;

        $classwidth = max(1, $classwidth);
        $params['classwidth'] = $classwidth;

        $cases = [];
        foreach (array_values($ranges) as $index => $range) {
            $j = $index + 1;
            $params['rangelower' . $j] = (float) $range['lower'];
            $params['rangeupper' . $j] = (float) $range['upper'];
            // The topmost range includes its upper bound; all others are half-open.
            $comparison = ($j === count($ranges)) ? '<=' : '<';
            $cases[] = "WHEN ability >= :rangelower$j AND ability $comparison :rangeupper$j THEN $j";
        }
        $rangecase = 'CASE WHEN ability IS NULL THEN 0 '
            . implode(' ', $cases)
            . " ELSE $unmatchedrange END";

        // CEIL over a real division: integer division would truncate and push the
        // boundary value of every class into the class below it.
        $bincase = "CASE WHEN $valuecolumn = 0 THEN 0
                         ELSE CAST(CEIL($valuecolumn * 1.0 / :classwidth) AS INTEGER) END";

        $sql = "SELECT rangeindex, bin, COUNT(*) AS frequency
                  FROM (
                        SELECT $rangecase AS rangeindex, $bincase AS bin
                          FROM ($innersql) perperson
                       ) classified
                 WHERE rangeindex >= 0
              GROUP BY rangeindex, bin
              ORDER BY rangeindex, bin";

        $counts = [];
        foreach ($DB->get_records_sql($sql, $params, 0, self::CHART_MAX_DATA_POINTS) as $row) {
            $counts[(int) $row->rangeindex][(int) $row->bin] = (int) $row->frequency;
        }

        return $counts;
    }

    /**
     * Returns the highest number of questions a single person answered.
     *
     * The chart used to load one row per enrolled person only to find
     * this maximum in PHP. The database can answer it with a single value, and the
     * cost then no longer grows with the size of the cohort.
     *
     * @param int $contextid
     * @param int $scaleid
     * @param int|null $courseid
     * @param array|null $alloweduserids Restriction from the group rules, or null.
     * @return int
     */
    public static function get_max_questions_answered_per_person(
        int $contextid,
        int $scaleid,
        ?int $courseid = null,
        ?array $alloweduserids = null,
        ?int $testid = null,
        ?int $starttime = null,
        ?int $endtime = null
    ): int {
        global $DB;

        [$inner, $params] = self::get_sql_for_questions_answered_per_person(
            $contextid,
            $scaleid,
            $courseid,
            $alloweduserids
        );

        return self::get_max_from_subquery($inner, $params, 'total_answered');
    }

    /**
     * Returns the answers-per-person histogram as counts, aggregated in the database.
     *
     * The chart only ever needed the number of people per (range, class) -
     * it counted the rows it had loaded. Loading a row per person to count them is
     * what made memory and runtime grow with the cohort. The classification happens
     * in SQL instead, and only the finished counts travel back.
     *
     * The class of a person follows feedback_helper::get_histogram_bin(): zero
     * answers form class 0, everything else is ceil(answers / classwidth), shifted by
     * one so that class 0 stays reserved.
     *
     * The range follows feedback_helper::get_feedback_range_index(): half-open
     * intervals, with the topmost one closed so the maximum value is still covered.
     * The boundaries are passed as bound parameters, so the two implementations
     * cannot drift apart silently - a test compares them directly.
     *
     * @param int $contextid
     * @param int $scaleid
     * @param int|null $courseid
     * @param int $classwidth Width of one class; must be at least 1.
     * @param array $ranges List of ['lower' => float, 'upper' => float], 1-based order.
     * @param array|null $alloweduserids Restriction from the group rules, or null.
     * @return array<int, array<int, int>> Count keyed by range index, then class.
     */
    public static function get_answers_per_person_histogram(
        int $contextid,
        int $scaleid,
        ?int $courseid,
        int $classwidth,
        array $ranges,
        ?array $alloweduserids = null,
        ?int $testid = null,
        ?int $starttime = null,
        ?int $endtime = null
    ): array {
        [$inner, $params] = self::get_sql_for_questions_answered_per_person(
            $contextid,
            $scaleid,
            $courseid,
            $alloweduserids
        );

        // Unmatched abilities are dropped here, as the chart did before: a value
        // outside every configured range had no bar to go into.
        return self::aggregate_person_histogram($inner, $params, 'total_answered', $classwidth, $ranges, -1);
    }

    /**
     * Returns the attempts-per-person histogram as counts, aggregated in the database.
     *
     * The twin of get_answers_per_person_histogram() for the attempts
     * chart, which loaded one row per person for the same reason.
     *
     * @param int $contextid
     * @param int $scaleid
     * @param int|null $courseid
     * @param int $classwidth
     * @param array $ranges
     * @param array|null $alloweduserids Restriction from the group rules, or null.
     * @return array<int, array<int, int>>
     */
    public static function get_attempts_per_person_histogram(
        int $contextid,
        int $scaleid,
        ?int $courseid,
        int $classwidth,
        array $ranges,
        ?array $alloweduserids = null
    ): array {
        [$inner, $params] = self::get_sql_for_attempts_per_person(
            $contextid,
            $scaleid,
            $courseid,
            $alloweduserids,
            $testid,
            $starttime,
            $endtime
        );

        // Unlike the answers chart, this one puts an unmatched ability into range 0
        // rather than dropping it - that is what the PHP version did.
        return self::aggregate_person_histogram($inner, $params, 'attempts', $classwidth, $ranges, 0);
    }

    /**
     * Returns the highest number of attempts a single person made.
     *
     * @param int $contextid
     * @param int $scaleid
     * @param int|null $courseid
     * @param array|null $alloweduserids Restriction from the group rules, or null.
     * @return int
     */
    public static function get_max_attempts_per_person(
        int $contextid,
        int $scaleid,
        ?int $courseid = null,
        ?array $alloweduserids = null
    ): int {
        [$inner, $params] = self::get_sql_for_attempts_per_person(
            $contextid,
            $scaleid,
            $courseid,
            $alloweduserids,
            $testid,
            $starttime,
            $endtime
        );

        return self::get_max_from_subquery($inner, $params, 'attempts');
    }

    /**
     * Returns the number of attempts per question, for the given questions only.
     *
     * The add-questions dialog used to obtain this by aggregating every
     * question attempt of the context and joining the result onto all candidates. The
     * aggregate is driven by the number of attempt steps, not by the page size, so it
     * cost the same whether ten rows or none were shown.
     *
     * Restricting it to the questions actually on screen turns a full aggregation
     * into an indexed lookup of a handful of ids.
     *
     * @param array $questionids
     * @param int $contextid
     * @return array Attempt count keyed by question id.
     */
    public static function get_contextattempts_for_questions(array $questionids, int $contextid): array {
        global $DB;

        if (empty($questionids)) {
            return [];
        }

        [, $contextfrom, , $params] = self::get_sql_for_stat_base_request();
        $contextfilter = $contextid > 0
            ? 'ccc1.id = :contextid'
            : $DB->sql_like('ccc1.json', ':default', false, false);
        $params['contextid'] = $contextid;
        $params['default'] = '%"default":true%';

        [$insql, $inparams] = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED, 'visibleq');
        $params = array_merge($params, $inparams);

        $sql = "SELECT qa.questionid, COUNT(DISTINCT qa.id) contextattempts
                  FROM $contextfrom
                 WHERE $contextfilter
                   AND qa.questionid $insql
              GROUP BY qa.questionid";

        $counts = [];
        foreach ($DB->get_records_sql($sql, $params) as $row) {
            $counts[(int) $row->questionid] = (int) $row->contextattempts;
        }

        return $counts;
    }

    /**
     * Returns a light FROM/WHERE for counting the rows of the question list.
     *
     * Counting the list meant counting the rows of the full query - the
     * one that carries the per question and per user attempt statistics. Those
     * aggregates are computed only to be thrown away by COUNT(), which makes the
     * count as expensive as the list itself.
     *
     * The row set is defined entirely by the joins up to the question bank; the
     * statistics are LEFT JOINs and cannot add or remove a row. Leaving them out
     * therefore yields exactly the same number.
     *
     * The joins are kept in the same order and with the same conditions as in
     * return_sql_for_catscalequestions(); if that one changes, this has to follow.
     * A test compares both counts so a divergence shows up rather than silently
     * producing a wrong total.
     *
     * @param array $catscaleids
     * @param int $contextid
     * @return array [string $from, string $where, array $params]
     */
    public static function return_sql_for_catscalequestions_count(array $catscaleids, int $contextid): array {
        global $DB;

        $params = ['contextid' => $contextid];
        $wherecontains = [];

        if (!empty($catscaleids) && $catscaleids[0] > 0) {
            [$incatscales, $inparams] = $DB->get_in_or_equal($catscaleids, SQL_PARAMS_NAMED, 'countcatscales');
            $params = array_merge($params, $inparams);
            $wherecontains[] = "lccs.id $incatscales";
        }

        $from = <<<SQL
        {local_catquiz_catscales} lccs
            JOIN {local_catquiz_items} lci ON lci.catscaleid = lccs.id
            LEFT JOIN {local_catquiz_itemparams} lcip
              ON lcip.itemid = lci.id AND lci.activeparamid = lcip.id
            JOIN {question} q ON q.id = lci.componentid
            JOIN {question_versions} qv ON qv.questionid = q.id
            JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
            JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
        SQL;

        // Items in piloting have no active parameter, so their context is
        // NULL - the same allowance the list itself makes.
        $where = '(lcip.contextid = :contextid OR lcip.contextid IS NULL)';
        foreach ($wherecontains as $condition) {
            $where .= " AND $condition";
        }

        return [$from, $where, $params];
    }

    /**
     * Returns the number of items with unusable parameters, per scale.
     *
     * The per item column tells a maintainer what is wrong with one row,
     * but not whether a scale has a problem at all. This answers that for every
     * scale in one grouped query, so the overview costs the same whether there are
     * three scales or three hundred.
     *
     * Counts only items whose active parameter is stored as unusable. Items in
     * piloting have no active parameter and are a different, expected state.
     *
     * @param int|null $contextid Restrict to one context, or null for all.
     * @return array<int, int> Number of unusable items keyed by catscaleid.
     */
    public static function get_unusable_item_counts_per_scale(?int $contextid = null): array {
        global $DB;

        $params = [];
        $contextfilter = '';
        if ($contextid !== null) {
            $contextfilter = ' AND lci.contextid = :contextid ';
            $params['contextid'] = $contextid;
        }

        $sql = "SELECT lci.catscaleid, COUNT(*) AS unusablecount
                  FROM {local_catquiz_items} lci
                  JOIN {local_catquiz_itemparams} lcip
                    ON lcip.id = lci.activeparamid
                 WHERE lcip.usable = 0 $contextfilter
              GROUP BY lci.catscaleid";

        $counts = [];
        foreach ($DB->get_records_sql($sql, $params) as $row) {
            $counts[(int) $row->catscaleid] = (int) $row->unusablecount;
        }

        return $counts;
    }

    /**
     * Returns the number of questions per scale, for all scales in one query.
     *
     * The scale overview called get_sql_for_number_of_questions_in_scale()
     * once per scale, so the number of count queries grew with the number of scales.
     * One grouped query answers the same question for every scale at once.
     *
     * @return array<int, int> Question count keyed by catscaleid.
     */
    public static function get_number_of_questions_per_scale(): array {
        global $DB;

        $sql = "SELECT catscaleid, COUNT(*) AS numberofquestions
                  FROM {local_catquiz_items}
              GROUP BY catscaleid";

        $counts = [];
        foreach ($DB->get_records_sql($sql) as $row) {
            $counts[(int) $row->catscaleid] = (int) $row->numberofquestions;
        }

        return $counts;
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
        // Return exactly one row per question (per question attempt),
        // carrying the fraction of its LAST graded step. A question can have
        // several graded steps, so counting steps would overcount; here the inner
        // subquery reduces to the latest graded step per question attempt. A
        // question without any graded step (skipped/unanswered) yields a NULL
        // fraction via the LEFT JOIN, which the caller counts as "unanswered"
        // rather than "wrong". Pilot exclusion happens in the caller because the
        // pilot flag is context-computed, not a database column.
        return $DB->get_records_sql(
            "SELECT qa.id AS questionattemptid, qa.questionid, laststep.fraction
            FROM {adaptivequiz_attempt} aa
            JOIN {question_attempts} qa ON aa.uniqueid = qa.questionusageid
            LEFT JOIN (
                SELECT qas.questionattemptid, qas.fraction
                FROM {question_attempt_steps} qas
                JOIN (
                    SELECT questionattemptid, MAX(sequencenumber) AS maxseq
                    FROM {question_attempt_steps}
                    WHERE fraction IS NOT NULL
                    GROUP BY questionattemptid
                ) laststepseq
                    ON laststepseq.questionattemptid = qas.questionattemptid
                    AND laststepseq.maxseq = qas.sequencenumber
            ) laststep ON laststep.questionattemptid = qa.id
            WHERE aa.id = :attemptid",
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
     * Reduces attempt snapshots to one historical ability per person.
     *
     * Historical statistics must use the ability recorded at the time
     * of the attempt (personability_after_attempt), not the person's current
     * parameter. For a person-weighted analysis exactly one value per person is
     * used; the documented rule here is the latest attempt in the period (by
     * endtime). Attempts without a stored snapshot (legacy) are excluded.
     *
     * @param array $attempts Attempt records with userid, endtime and
     *                        personability_after_attempt.
     * @param string $rule    Selection rule for multiple attempts: 'last',
     *                        'first' or 'best'.
     *
     * @return array Map of userid => historical ability (float).
     */
    public static function get_snapshot_ability_per_person(array $attempts, string $rule = 'last'): array {
        // Build (userid, endtime, value) items from the attempt
        // snapshots and reduce to one value per person via the shared rule, so
        // charts, statistics and exports all apply the same selection.
        $items = [];
        foreach ($attempts as $attempt) {
            $items[] = [
                'userid' => (int) $attempt->userid,
                'endtime' => (int) ($attempt->endtime ?? 0),
                // A missing snapshot (legacy attempt) becomes null and is dropped.
                'value' => $attempt->personability_after_attempt ?? null,
            ];
        }
        return \local_catquiz\teststrategy\feedback_helper::reduce_to_one_value_per_person($items, $rule);
    }

    /**
     * Returns aggregated peer-comparison statistics for one context and scale.
     *
     * The reference group is context-true and statistically sound.
     * It comprises, within the given CAT context and scale, exactly one value per
     * person (the latest personparam per user), excludes the compared user, and
     * is aggregated in SQL rather than loading every row into PHP. The returned
     * counts allow a midrank percentile:
     *     100 * (lowercount + 0.5 * equalcount) / n
     * where n is the number of distinct peers.
     *
     * @param int $contextid     CAT context the comparison is scoped to.
     * @param int $catscaleid    Scale the comparison is scoped to.
     * @param float $score       The compared person's ability (rounded to 4 dp).
     * @param int $excludeuserid The user to exclude from the reference group.
     *
     * @return \stdClass Object with n, meanvalue, lowercount, equalcount.
     */
    public static function get_peer_comparison_stats(
        int $contextid,
        int $catscaleid,
        float $score,
        int $excludeuserid
    ): \stdClass {
        global $DB;
        // Compare at the stored precision (4 decimals) so ties are detected.
        $score = round($score, 4);
        // Issue #15 + #10: only valid results count as peers. Invalid results
        // (e.g. the "all correct" / "all wrong" case that #10 excludes from
        // feedback) diverge and are stored clamped to the saturation bound
        // ±MODEL_POS_INF; a valid ability is strictly inside that bound.
        $inf = model_person_param::MODEL_POS_INF;
        $sql = "
            SELECT
                COUNT(1) AS n,
                AVG(peers.ability) AS meanvalue,
                SUM(CASE WHEN peers.ability < :scorelt THEN 1 ELSE 0 END) AS lowercount,
                SUM(CASE WHEN peers.ability = :scoreeq THEN 1 ELSE 0 END) AS equalcount
            FROM (
                SELECT pp.userid, pp.ability
                FROM {local_catquiz_personparams} pp
                WHERE pp.contextid = :contextid
                  AND pp.catscaleid = :catscaleid
                  AND pp.userid <> :excludeuserid
                  AND pp.ability IS NOT NULL
                  AND ABS(pp.ability) < :inf
                  AND pp.id = (
                      SELECT MAX(pp2.id)
                      FROM {local_catquiz_personparams} pp2
                      WHERE pp2.contextid = pp.contextid
                        AND pp2.catscaleid = pp.catscaleid
                        AND pp2.userid = pp.userid
                        AND pp2.ability IS NOT NULL
                        AND ABS(pp2.ability) < :inf2
                  )
            ) peers";
        $params = [
            'contextid' => $contextid,
            'catscaleid' => $catscaleid,
            'excludeuserid' => $excludeuserid,
            'scorelt' => $score,
            'scoreeq' => $score,
            'inf' => $inf,
            'inf2' => $inf,
        ];
        $record = $DB->get_record_sql($sql, $params);
        return (object) [
            'n' => (int) ($record->n ?? 0),
            'meanvalue' => $record->meanvalue !== null ? (float) $record->meanvalue : 0.0,
            'lowercount' => (int) ($record->lowercount ?? 0),
            'equalcount' => (int) ($record->equalcount ?? 0),
        ];
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

        $data = new stdClass();
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
        // Never stamp an end time here. save_attempt_to_db() runs after
        // every response (i.e. while the attempt is still running); the end time
        // is set exactly once by attempt_finalizer at completion. On INSERT the
        // running attempt gets endtime = null; on UPDATE the field is left
        // untouched so a finalised end time is never clobbered.

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
            // Preserve the original creation time and never touch the
            // end time on update. The end time is owned by attempt_finalizer.
            unset($data->timecreated);
            unset($data->endtime);
            $DB->update_record('local_catquiz_attempts', $data);
            return $existingrecord->id;
        }

        // INSERT: fresh (running) attempt. No end time yet.
        $data->timecreated = $now;
        $data->endtime = null;

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
     * @param string $fields Columns to select; defaults to all.
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
        bool $enrolled = true,
        string $fields = '*'
    ) {
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

        // The caller decides which columns it needs. SELECT * always
        // carried debug_info along - a field that can hold the full trace of an
        // attempt and that none of the charts ever reads. On a large cohort that is
        // the bulk of the transferred bytes, thrown away right after loading.
        $sql = "$with SELECT $fields FROM {local_catquiz_attempts} a $join WHERE 1=1";

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
            // Filter historical periods by actual completion time.
            $sql .= " AND a.endtime >= :starttime";
        }
        if (!is_null($endtime)) {
            $sql .= " AND a.endtime <= :endtime";
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
        array $groupstoenrol
    ): string {
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
                    $groupstring .= "<div> - "  . get_string('groupenrolementstring', 'local_catquiz', $messageinfo) . "</div>";
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
                    $correct++;
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
     * @param array|null $alloweduserids Restriction from the group rules, or null.
     *
     * @return array
     *
     */
    public static function get_sql_for_questions_answered_per_person(
        int $contextid,
        int $scaleid,
        ?int $courseid = null,
        ?array $alloweduserids = null
    ) {
        global $DB;

        $catscaleids = [$scaleid, ...catscale::get_subscale_ids($scaleid)];

        // Get questions answered for the given context.
         [, $from, $where, $params] = self::get_sql_for_stat_base_request([], [$contextid]);
        [$insql, $inparams] = $DB->get_in_or_equal($catscaleids, SQL_PARAMS_NAMED, 'incatscales');
        $params = array_merge($params, $inparams, ['catscaleid' => $scaleid]);
        $where2 = '1=1';
        $where3 = "lci.catscaleid $insql";
        if ($courseid) {
            $where2 .= ' AND e.courseid = :courseid';
            $where3 .= ' AND a.course = :courseid2';
            $params = array_merge($params, ['courseid' => $courseid, 'courseid2' => $courseid]);
        }

        // Review finding on issue #18: the group restriction reached the CSV export
        // only, so the charts could still aggregate over members of other groups. It
        // belongs to the cohort itself - every consumer then inherits it.
        //
        // Null means no restriction applies; an empty array means nothing is visible
        // and must yield no rows rather than all of them.
        $userfilter = '';
        if ($alloweduserids !== null) {
            if (empty($alloweduserids)) {
                $userfilter = ' AND 1=0 ';
            } else {
                [$useridsql, $useridparams] = $DB->get_in_or_equal(
                    $alloweduserids,
                    SQL_PARAMS_NAMED,
                    'alloweduser'
                );
                $userfilter = " AND ue.userid $useridsql ";
                $params = array_merge($params, $useridparams);
            }
        }

        $sql = "SELECT DISTINCT ue.userid, COALESCE(answercount, 0) total_answered, lcp.ability
                FROM {enrol} e
                JOIN {user_enrolments} ue ON e.id = ue.enrolid
                -- No join on {role}: enrol.roleid belongs to the enrolment instance,
                -- not to the person, and plugins such as the LTI enrolment leave it
                -- at 0. Requiring a matching role - let alone the shortname
                -- 'student' - silently removes everyone enrolled that way, which on
                -- an LTI course is nearly the entire population.
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
                WHERE $where2 $userfilter";
        return [$sql, $params];
    }

    /**
     * Return the number of attempts per person
     *
     * @param int $contextid
     * @param int $scaleid
     * @param ?int $courseid
     * @param array|null $alloweduserids Restriction from the group rules, or null.
     */
    public static function get_sql_for_attempts_per_person(
        int $contextid,
        int $scaleid,
        ?int $courseid,
        ?array $alloweduserids = null,
        ?int $testid = null,
        ?int $starttime = null,
        ?int $endtime = null
    ): array {
        global $DB;

        // The attempts are the primary source, not the enrolments.
        //
        // The previous construction started from {enrol}, joined {user_enrolments}
        // and {role}, and attached the attempts with a LEFT JOIN. Every defect this
        // chart has had came from that direction: an inner join on {role} removed
        // everyone enrolled through LTI, because enrol.roleid is a property of the
        // enrolment instance and those plugins leave it at 0; a second enrolment in
        // the same course multiplied the count; people unenrolled since their test
        // disappeared, and people enrolled afterwards appeared retroactively as
        // "no attempt". None of it has anything to do with counting attempts.
        //
        // Counting from {local_catquiz_attempts} is immune to all of it: an attempt
        // that exists is counted once, whatever the person's enrolment looks like
        // today.
        //
        // The context is deliberately not a filter here. It records which calibration
        // an attempt was scored under, not whether it happened - and after a
        // recalibration the older attempts carry the previous context. Restricting on
        // it made the whole history vanish from the chart.
        $params = ['attemptscaleid' => $scaleid];
        $where = 'a.scaleid = :attemptscaleid';

        if ($courseid) {
            $where .= ' AND a.courseid = :courseid';
            $params['courseid'] = $courseid;
        }

        // The same scope the rest of the statistics uses. Without these the chart
        // answered a different question than the page around it: a shortcode naming
        // one test and one date range still counted attempts of other tests and from
        // outside the range.
        if ($testid) {
            $where .= ' AND a.instanceid = :testinstance';
            $params['testinstance'] = $testid;
        }

        // Bounded by endtime, like get_attempts(): an attempt belongs to the period
        // in which it was finished, not the one in which it was begun.
        if ($starttime) {
            $where .= ' AND a.endtime >= :starttime';
            $params['starttime'] = $starttime;
        }

        if ($endtime) {
            $where .= ' AND a.endtime <= :endtime';
            $params['endtime'] = $endtime;
        }

        // Null means no restriction; an empty array means nothing is visible and has
        // to yield no rows rather than all of them.
        $enrolwhere = $courseid ? 'e.courseid = :enrolcourseid' : '1 = 1';
        $enrolparams = $courseid ? ['enrolcourseid' => $courseid] : [];

        if ($alloweduserids !== null) {
            if (empty($alloweduserids)) {
                $where .= ' AND 1 = 0';
                $enrolwhere .= ' AND 1 = 0';
            } else {
                [$insql, $inparams] = $DB->get_in_or_equal(
                    $alloweduserids,
                    SQL_PARAMS_NAMED,
                    'alloweduser'
                );
                $where .= " AND a.userid $insql";
                $params = array_merge($params, $inparams);

                [$insql2, $inparams2] = $DB->get_in_or_equal(
                    $alloweduserids,
                    SQL_PARAMS_NAMED,
                    'allowedenrol'
                );
                $enrolwhere .= " AND ue.userid $insql2";
                $enrolparams = array_merge($enrolparams, $inparams2);
            }
        }

        // The ability that colours the bar comes from the person's last attempt in
        // the same selection - the value they were actually shown - rather than from
        // {local_catquiz_personparams}, which holds the current estimate and is
        // overwritten by every recalibration.
        $abilitysql = "SELECT a2.personability_after_attempt
                         FROM {local_catquiz_attempts} a2
                        WHERE a2.userid = a.userid
                          AND a2.scaleid = a.scaleid
                        ORDER BY a2.endtime DESC, a2.id DESC
                        LIMIT 1";

        // The clause $where appears twice in the statement below. Moodle counts named parameters
        // per occurrence, so the second copy needs its own names - otherwise the
        // query fails with "Incorrect number of query parameters" rather than
        // silently doing the wrong thing.
        $notexistswhere = $where;
        $notexistsparams = [];
        foreach ($params as $name => $value) {
            $notexistswhere = preg_replace(
                '/:' . preg_quote($name, '/') . '\b/',
                ':ne' . $name,
                $notexistswhere
            );
            $notexistsparams['ne' . $name] = $value;
        }

        // People enrolled today who have no attempt at all form the "no attempt"
        // bucket. They are the one thing the attempts table cannot supply, so the
        // enrolments are consulted for that alone - without any join on {role}.
        //
        // The NOT EXISTS repeats $where deliberately. It used to test the scale
        // alone, while the counting half above also filtered course, test and period.
        // Anyone who had attempted this scale somewhere else therefore fell out of
        // both halves at once: out of the counts because of those filters, and out of
        // "no attempt" because the bare scale check found their other attempt. They
        // vanished from the chart entirely rather than landing in a bucket.
        $sql = "SELECT userid, MAX(ability) ability, SUM(attempts) attempts
                  FROM (
                        SELECT a.userid, ($abilitysql) ability, COUNT(*) attempts
                          FROM {local_catquiz_attempts} a
                         WHERE $where
                      GROUP BY a.userid, a.scaleid

                         UNION ALL

                        SELECT DISTINCT ue.userid, CAST(NULL AS DECIMAL(10,5)) ability, 0 attempts
                          FROM {enrol} e
                          JOIN {user_enrolments} ue ON e.id = ue.enrolid
                         WHERE $enrolwhere
                           AND NOT EXISTS (
                               SELECT 1
                                 FROM {local_catquiz_attempts} a
                                WHERE a.userid = ue.userid
                                  AND $notexistswhere
                           )
                  ) counted
              GROUP BY userid
              ORDER BY attempts";

        $params = array_merge($params, $enrolparams, $notexistsparams);

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
            'scaleid' => $scaleid,
            'courseid' => $courseid,
            'testid' => $testid,
            'starttime' => $starttime,
            'endtime' => $endtime,
        ];
        // No restriction on the context. This is the raw data export: it has to
        // contain every attempt on the scale, and the context only records which
        // calibration an attempt was scored under, not whether it happened.
        //
        // Filtering on it returned the attempts of the currently active context
        // alone, so everything from before a recalibration was missing - silently,
        // because the file looked complete.
        //
        // The parameter is kept in the signature and written into the exported rows;
        // callers that want a single calibration can still filter on that column.
        $where = "a.scaleid = :scaleid";
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
            // Same completion-time period rule as the charts.
            $where .= " AND a.endtime >= :starttime";
        }

        if ($endtime) {
            $where .= " AND a.endtime <= :endtime";
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
            // Sort DESCENDING by status so that index 0 really is the highest status.
            // This used to sort ascending and then take element 0, which selected the
            // LEAST calibrated parameter - for an item carrying parameters for several
            // models that meant the stale/uncalibrated row (often an all-zero default)
            // became the active one and was played during the test.
            $sortfun = function ($a, $b) {
                return $b->status <=> $a->status;
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
        itemparam_validity::stamp($record);
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
        itemparam_validity::stamp($record);
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
            foreach ($remainingparams as $remainingparam) {
                itemparam_validity::stamp($remainingparam);
            }
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
                itemparam_validity::stamp($p);
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
