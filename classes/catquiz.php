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
        // Normalise first, decide afterwards: the method documents int|array, but read index 0
        // before it had established that an array was passed at all.
        $ids = is_array($catscaleids) ? array_values($catscaleids) : [$catscaleids];
        $ids = array_values(array_filter(array_map('intval', $ids), fn($id) => $id > 0));

        $where = '';
        $inparams = [];
        if (!empty($ids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($ids);
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

        /* One id asked for, one global scale returned. The test used to be is_int($catscaleids),
           which made the same call behave differently depending on whether the id arrived bare
           or wrapped in an array - the very ambiguity this method is supposed to absorb. */
        if (count($ids) === 1 && !$assocarray) {
            $sqlresult = $DB->get_record_sql($sql, $inparams);

            return $sqlresult ? [intval($sqlresult->globalid)] : [];
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
            'itemcontextid',
            'activeparamid',
            'invalidparamref',
            'questionstatus',
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
     * The usage statistics of the items in a CAT context, as a grouped statement.
     *
     * Issue #136: one number per question - the answers it got in attempts of the
     * given global scale(s) and context. "Answered" is read off
     * question_attempts.responsesummary, which is set as soon as a response exists;
     * the steps are not needed for it. The time of the last answer is
     * question_attempts.timemodified.
     *
     * The grouping column is the expression questionid + 0, not the bare column. Joined
     * on the bare column, MariaDB 10.11 evaluated this subquery once per outer row
     * ("lateral derived", split materialization) and read every attempt of that
     * question on the whole site each time: 12 s instead of 0.4 s on a production
     * instance. An expression gives it no index to split on, so the statistics are
     * computed once. PostgreSQL and MySQL accept the expression as well.
     *
     * @param string $scalesin The "IN (...)" / "= :x" part for the global scale ids
     * @param string $contextparam Name of the parameter holding the CAT context id
     * @return string
     */
    private static function pool_statistics_sql(string $scalesin, string $contextparam): string {
        return "SELECT lca.scaleid, qa.questionid + 0 AS questionid,
                       COUNT(qa.id) AS numberattempts, MAX(qa.timemodified) AS lastattempt
                  FROM {local_catquiz_attempts} lca
                  JOIN {adaptivequiz_attempt} aqa ON lca.attemptid = aqa.id
                  JOIN {question_attempts} qa ON qa.questionusageid = aqa.uniqueid
                 WHERE lca.contextid = :$contextparam AND lca.scaleid $scalesin
                   AND qa.responsesummary IS NOT NULL
              GROUP BY lca.scaleid, qa.questionid + 0";
    }

    /**
     * Number of answers per question in a CAT context (issue #136).
     *
     * Counts the answers in attempts with the same global scale and the same CAT
     * context - the same number the pool and the manager tables carry as attempts,
     * from the same statement ({@see self::pool_statistics_sql()}). The reference
     * computation for checks and repairs of the number (planned nightly cure task,
     * doc/issue-136-instanzcache.md).
     *
     * @param array $catscaleids Scale ids of the pool; their global scale is used
     * @param int $contextid CAT context
     * @return array<int, stdClass> keyed by question id: attempts, lastattempt
     */
    public static function get_pool_statistics(array $catscaleids, int $contextid): array {
        global $DB;
        $ids = array_values(array_filter(array_map('intval', $catscaleids), fn($id) => $id > 0));
        if (empty($ids)) {
            return [];
        }
        $globalscaleids = array_values(array_unique(self::get_global_scale($ids)));
        if (empty($globalscaleids)) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($globalscaleids, SQL_PARAMS_NAMED, 'poolstatscale');
        $params['poolstatcontextid'] = $contextid;
        $sql = "SELECT questionid, SUM(numberattempts) AS attempts, MAX(lastattempt) AS lastattempt
                  FROM (" . self::pool_statistics_sql($insql, 'poolstatcontextid') . ") stats
              GROUP BY questionid";
        $result = [];
        foreach ($DB->get_recordset_sql($sql, $params) as $row) {
            $result[(int) $row->questionid] = (object) [
                'attempts' => (int) $row->attempts,
                'lastattempt' => (int) $row->lastattempt,
            ];
        }
        return $result;
    }

    /**
     * When a person saw each question, and how often - anywhere on the site (issue #146).
     *
     * The same numbers the pool carried as userattempts and userlastattempttime, from the same steps:
     * step 0 is written when a question is presented, so this counts what the person saw, answered or not.
     *
     * @param int $userid
     * @return array question id => [times seen, last time]
     */
    public static function get_user_question_stats(int $userid): array {
        global $DB;
        if ($userid <= 0) {
            return [];
        }
        $sql = "SELECT qa.questionid + 0 AS questionid, COUNT(qa.id) AS numberattempts, MAX(qas.timecreated) AS lastattempt
                  FROM {question_attempt_steps} qas
                  JOIN {question_attempts} qa ON qa.id = qas.questionattemptid
                 WHERE qas.userid = :userid AND qas.sequencenumber = 0
              GROUP BY qa.questionid + 0";
        $stats = [];
        $rows = $DB->get_recordset_sql($sql, ['userid' => $userid]);
        foreach ($rows as $row) {
            $stats[(int) $row->questionid] = [(int) $row->numberattempts, (int) $row->lastattempt];
        }
        $rows->close();
        return $stats;
    }

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
     * @param bool $onlyavailable Only questions a test may use: the newest version of the question is ready in
     *                            the question bank and the item is active in its scale (issue #82). The manager
     *                            lists all, with questionstatus and testitemstatus to tell them apart.
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
        bool $leanselect = false,
        bool $onlyavailable = false
    ) {

        global $DB;
        if ($contextid === 0) {
            $contextid = self::get_default_context_id();
        }

        // Start the params array.
        $params = [
            'contextid' => $contextid,
            // Issue #157: the items of the CAT context, and only those.
            'itemcontextid' => $contextid,
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

        // Issue #82: two levels hide a question. The question bank hides a version (or keeps it a draft) for
        // every use; the CAT manager deactivates the item in one scale. Global wins, the scale narrows further.
        $itemfilter = '';
        $versionfilter = '';
        if ($onlyavailable) {
            require_once(__DIR__ . '/../lib.php');
            $itemfilter = ' AND lci.status <> :inactiveitemstatus ';
            $params['inactiveitemstatus'] = LOCAL_CATQUIZ_TESTITEM_STATUS_INACTIVE;
            // The state of the question is that of the newest version of its bank entry - what the question bank
            // shows. The item plays the version it refers to; its parameters belong to that one.
            $versionfilter = ' AND qvl.status = :readyversionstatus ';
            $params['readyversionstatus'] = \core_question\local\bank\question_version_status::QUESTION_STATUS_READY;
        }

        $insql = '';
        // Not $catscaleids[0]: callers hand this list on id-keyed as well, and index 0 would
        // then be undefined - the filter would fall away without anyone noticing.
        if (!empty($catscaleids) && (int) reset($catscaleids) > 0) {
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

        // Issue #136: the usage statistics, for the runtime pool and the manager tables
        // alike. See pool_statistics_sql() for why the grouping is on an expression.
        //
        // They stay in this statement on purpose. A first version fetched them in their
        // own queries and ran the pool join alone; on test data PostgreSQL had no table
        // statistics for yet, that join of seven tables got nested loops without index
        // conditions and a pool of 420 items did not come back within five minutes.
        // With the derived tables the planner behaves as before the change
        // (strategy_semantics_test: 180 s before, 188 s after).
        $statcolumns = ",
            -- Information about usage statistics
            COALESCE(astat.numberattempts,0) attempts,
            COALESCE(astat.lastattempt,0) as astatlastattempttime,
            ustat.userid, ustat.numberattempts userattempts,
            ustat.lastattempt as userlastattempttime";
        if (isset($parentscales1, $parentscales3)) {
            $statjoins = 'LEFT JOIN (' . self::pool_statistics_sql($parentscales3, 'statcontextid') . ') astat
              ON astat.questionid = q.id
                AND astat.scaleid ' . $parentscales1;
        } else {
            $statjoins = 'LEFT JOIN (SELECT NULL AS scaleid, NULL AS numberattempts, NULL AS lastattempt) astat ON 1=1';
        }

        $from = <<<SQL
        ( SELECT
            -- Issue #104: the first column keys the rows of get_records_sql() - in the manager tables,
            -- which select '*'. One row per item, that is per question and scale: a question in two
            -- sibling scales has two rows, and keyed by the question one of them vanished at random.
            lci.id as itemid,
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
            -- Issue #157: the item's own context and its reference to the active parameters - a reference that
            -- does not resolve to parameters of the item in its context is an error, not a pilot.
            lci.contextid AS itemcontextid,
            lci.activeparamid AS activeparamid,
            CASE WHEN COALESCE(lci.activeparamid, 0) <> 0 AND lcip.id IS NULL THEN 1 ELSE 0 END AS invalidparamref,
            -- Issue #82: ready, draft or hidden - the status of the newest version of the question in the bank.
            qvl.status AS questionstatus{$statcolumns}
          FROM {local_catquiz_catscales} lccs
          -- Get all corresponding items of those scales, skip if not existent
          -- (INNER JOIN). Issue #157: only the items of the CAT context asked for. An item belongs to one context;
          -- the items of the same question in other contexts are other calibrations (or none) and never stand in.
            JOIN {local_catquiz_items} lci ON lci.catscaleid=lccs.id AND lci.contextid = :itemcontextid $itemfilter

          -- Get the active item parameter, if there is one.
          --
          -- Issue #54: this used to be an INNER JOIN, so items without parameters -
          -- or without an *active* parameter - never appeared in the list at all.
          -- Those items are exactly the ones in piloting, and their statistics
          -- (attempt counts, last attempt) are of interest precisely while they are
          -- being piloted. A LEFT JOIN keeps them visible; the parameter columns are
          -- then NULL, which the validity column reports as "no parameters".
          -- Issue #157: the active parameters are those the item points to, of this very item, in its context,
          -- for its question.
            LEFT JOIN {local_catquiz_itemparams} lcip
              ON lcip.itemid = lci.id AND lci.activeparamid = lcip.id
                AND lcip.contextid = lci.contextid AND (lcip.componentid = lci.componentid OR COALESCE(lcip.componentid, 0) = 0)

          -- Get all information about the question from the questionbank itself
            JOIN {question} q ON q.id=lci.componentid $questionfilter
            JOIN {question_versions} qv ON qv.questionid=q.id
            JOIN {question_bank_entries} qbe ON qbe.id=qv.questionbankentryid
          -- Issue #82: the newest version of the entry, exactly one row - its status is the one the bank shows.
            JOIN {question_versions} qvl ON qvl.questionbankentryid = qbe.id
              AND qvl.version = (SELECT MAX(qvm.version) FROM {question_versions} qvm
                                  WHERE qvm.questionbankentryid = qbe.id) $versionfilter
            JOIN {question_categories} qc ON qc.id=qbe.questioncategoryid

            {$statjoins}
        SQL;

        if (!empty($userid)) {
            // Issue #136: when a person last saw a question, anywhere on the site. Step 0
            // is written when the question is presented, so it counts what the person
            // saw, answered or not, and needs neither catquiz nor adaptivequiz tables.
            $from .= <<<SQL
                LEFT JOIN (
                    SELECT qas.userid, qa.questionid + 0 AS questionid,
                           COUNT(qa.id) AS numberattempts, MAX(qas.timecreated) AS lastattempt
                      FROM {question_attempt_steps} qas
                      JOIN {question_attempts} qa ON qa.id = qas.questionattemptid
                     WHERE qas.userid = :statuserid AND qas.sequencenumber = 0
                  GROUP BY qas.userid, qa.questionid + 0
                ) ustat
                  ON ustat.questionid = q.id ) s
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
        // Issue #157: a pilot is an item without a reference to active parameters. An item whose reference does not
        // resolve (missing parameters, of another item, context or question) is no pilot: the runtime pool leaves it
        // out - local\integrity\item_param_integrity reports it -, the manager lists show it, marked
        // (invalidparamref), so that it can be repaired.
        if ($onlyavailable) {
            $where .= sprintf(' AND (lcipcontextid = %d OR COALESCE(activeparamid, 0) = 0)', (int) $contextid);
        } else {
            $where .= sprintf(' AND (lcipcontextid = %d OR lcipcontextid IS NULL)', (int) $contextid);
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
                -- Issue #58: deliberately NOT EXISTS, not a LEFT JOIN.
                --
                -- The rewrite to LEFT JOIN ... IS NULL was tried and measured. On a
                -- small pool it looked like the answer: MariaDB stopped scanning
                -- early and the statement went from 113 ms to 20 ms. At 250.000 items
                -- it reversed - warm p95 rose from 1.089 ms to 1.967 ms, because the
                -- changed join order makes the version lookups run per candidate row
                -- instead of per returned row, and that cost grows with the pool.
                --
                -- PostgreSQL preferred the outer join (206 -> 113 ms), so an
                -- engine-dependent form would help there. It is not worth two
                -- statements: MariaDB is the engine that misses the target, and this
                -- form is the better one for it.
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
            /* Issue #104: personparams keeps the history of every estimate. Joined as it is, each step came
               once per history row - all with the same key, so one arbitrary row survived, an invalid one
               too. Joined is the newest valid row per person, as in get_person_abilities(). */
            $joinabilitysql = <<<SQL
                JOIN {local_catquiz_personparams} lcp
                    ON lcp.userid = qas.userid
                    AND lcp.catscaleid = :scaleability
                    AND lcp.contextid = :scalecontext
                    AND lcp.isvalid = 1
                    AND lcp.id = (
                        SELECT MAX(lcp2.id)
                          FROM {local_catquiz_personparams} lcp2
                         WHERE lcp2.userid = lcp.userid
                           AND lcp2.catscaleid = lcp.catscaleid
                           AND lcp2.contextid = lcp.contextid
                           AND lcp2.isvalid = 1
                    )
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
        global $DB;
        $params = [];
        $filter = '';
        // Issue #104: a test row of the attempt's instance and component - the component is stored in
        // two spellings. local_catquiz_tests is not unique on (componentid, component), and joined on
        // the instance id alone every row of another component, or a second row, repeated the attempt;
        // the table keyed by the attempt showed one of the names at random. Of several rows, the newest.
        $prefixedattempt = $DB->sql_concat("'mod_'", 'lca.component');
        $prefixedtest = $DB->sql_concat("'mod_'", 'lct2.component');

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
                JOIN {local_catquiz_tests} lct ON lct.id = (
                    SELECT MAX(lct2.id)
                      FROM {local_catquiz_tests} lct2
                     WHERE lct2.componentid = lca.instanceid
                       AND (lct2.component = lca.component
                            OR lct2.component = $prefixedattempt
                            OR $prefixedtest = lca.component)
                )
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
     * @param int $adaptiveattemptid Id of the attempt of the component: adaptivequiz_attempt.id - not local_catquiz_attempts.id.
     * @param int $userid
     * @param array|null $userids Only attempts of these people (the group rule), null for everybody.
     *
     * @return mixed
     *
     */
    public static function return_data_from_attemptstable(
        int $numberofrecords = 1,
        int $instanceid = 0,
        int $courseid = 0,
        int $adaptiveattemptid = 0,
        int $userid = -1,
        ?array $userids = null
    ) {

        global $DB;

        $sqlarray = self::return_sql_for_attemptid_contextid_json(
            $numberofrecords,
            $instanceid,
            $courseid,
            $adaptiveattemptid,
            $userid,
            $userids
        );

        $recordsarray = $DB->get_records_sql($sqlarray[0], $sqlarray[1], 0, $sqlarray[2]);

        return $recordsarray;
    }

    /**
     * Summary of return_sql_for_attemptid_contextid_json
     * @param int $numberofrecords
     * @param int $instanceid
     * @param int $courseid
     * @param int $adaptiveattemptid Id of the attempt of the component: adaptivequiz_attempt.id - not local_catquiz_attempts.id.
     * @param int $userid
     * @param array|null $userids Only attempts of these people, null for everybody; empty selects nothing.
     * @return array [sql, params, the number of rows, 0 for all]
     */
    private static function return_sql_for_attemptid_contextid_json(
        int $numberofrecords = 1,
        int $instanceid = 0,
        int $courseid = 0,
        int $adaptiveattemptid = 0,
        int $userid = -1,
        ?array $userids = null
    ): array {
        global $DB;

        // Issue #104: keyed by the row's own id. attemptid is unique, but may be NULL - and rows with
        // NULL fell onto one key.
        $sql = "SELECT
        id, attemptid, contextid, userid, endtime, timemodified, json, debug_info,
        -- The strategy is a column of its own. Reading it only from the JSON payload
        -- fails for attempts whose payload predates that field, and the feedback then
        -- cannot be built at all.
        teststrategy
        FROM {local_catquiz_attempts} lca ";

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
        // The group rule (issue #85): in the limit, not after it, so that the number asked for is shown.
        if ($userids !== null) {
            if (!$userids) {
                $wherearray[] = ' 1 = 0 ';
            } else {
                [$insql, $inparams] = $DB->get_in_or_equal(array_map('intval', $userids), SQL_PARAMS_NAMED, 'fbuser');
                $wherearray[] = " userid $insql ";
                $params += $inparams;
            }
        }

        if ($adaptiveattemptid !== 0) {
            $wherearray[] = ' attemptid = :attemptid ';
            $params['attemptid'] = $adaptiveattemptid;
        }

        // Only completed attempts have a result to show (issues #101, #88). The status did not tell: a
        // running attempt is saved with status "OK" after every answer and was shown as a finished
        // result with the feedback of its last answer.
        $wherearray[] = ' ' . \local_catquiz\local\statistics\attempt_population::completed_sql('lca') . ' ';

        if (count($wherearray) > 0) {
            $sql .= " WHERE " . implode(' AND ', $wherearray);
        }

        // The newest first by the authoritative end time (issue #88) - timemodified changes whenever the
        // row is saved again. With the same time the later row first, so that the limit takes a fixed set.
        $sql .= " ORDER BY endtime DESC, id DESC";

        // We treat both INF as 0 as infinite value here, because intval(INF) is
        // converted to 0. The limit goes to the database layer, not as LIMIT into the SQL (issue #104).
        $limit = (!is_infinite($numberofrecords) && $numberofrecords > 0) ? $numberofrecords : 0;

        return [$sql, $params, $limit];
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
        // A recordset, not get_records_sql(): that method uses the first column as
        // the array key and silently drops every later row repeating it. The first
        // column here is rangeindex, which is deliberately not unique - there is one
        // row per range *and* bin. Only one bin per range survived, so a chart built
        // from 6/60/19/2 people showed 6/-/1/2.
        $recordset = $DB->get_recordset_sql($sql, $params, 0, self::CHART_MAX_DATA_POINTS);
        foreach ($recordset as $row) {
            $counts[(int) $row->rangeindex][(int) $row->bin] = (int) $row->frequency;
        }
        $recordset->close();

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
     * @param int|null $testid Restrict to one activity instance, or null for all.
     * @param int|null $starttime Only attempts ending at or after this time.
     * @param int|null $endtime Only attempts ending at or before this time.
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

        // Test and period reach the query (issue #88): they were accepted and dropped, so a shortcode
        // naming one test and one period counted the answers of every test at every time.
        [$inner, $params] = self::get_sql_for_questions_answered_per_person(
            $contextid,
            $scaleid,
            $courseid,
            $alloweduserids,
            $testid,
            $starttime,
            $endtime
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
     * @param int|null $testid Restrict to one activity instance, or null for all.
     * @param int|null $starttime Only attempts ending at or after this time.
     * @param int|null $endtime Only attempts ending at or before this time.
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
        // Test and period reach the query (issue #88): they were accepted and dropped, so a shortcode
        // naming one test and one period counted the answers of every test at every time.
        [$inner, $params] = self::get_sql_for_questions_answered_per_person(
            $contextid,
            $scaleid,
            $courseid,
            $alloweduserids,
            $testid,
            $starttime,
            $endtime
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
     * @param int|null $testid Restrict to one activity instance, or null for all.
     * @param int|null $starttime Only attempts ending at or after this time.
     * @param int|null $endtime Only attempts ending at or before this time.
     * @param string $selection attempt_population::ALL, FIRST or LAST (issue #88).
     * @return array<int, array<int, int>>
     */
    public static function get_attempts_per_person_histogram(
        int $contextid,
        int $scaleid,
        ?int $courseid,
        int $classwidth,
        array $ranges,
        ?array $alloweduserids = null,
        ?int $testid = null,
        ?int $starttime = null,
        ?int $endtime = null,
        string $selection = \local_catquiz\local\statistics\attempt_population::ALL
    ): array {
        [$inner, $params] = self::get_sql_for_attempts_per_person(
            $contextid,
            $scaleid,
            $courseid,
            $alloweduserids,
            $testid,
            $starttime,
            $endtime,
            $selection
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
     * @param int|null $testid Restrict to one activity instance, or null for all.
     * @param int|null $starttime Only attempts ending at or after this time.
     * @param int|null $endtime Only attempts ending at or before this time.
     * @param string $selection attempt_population::ALL, FIRST or LAST (issue #88).
     * @return int
     */
    public static function get_max_attempts_per_person(
        int $contextid,
        int $scaleid,
        ?int $courseid = null,
        ?array $alloweduserids = null,
        ?int $testid = null,
        ?int $starttime = null,
        ?int $endtime = null,
        string $selection = \local_catquiz\local\statistics\attempt_population::ALL
    ): int {
        [$inner, $params] = self::get_sql_for_attempts_per_person(
            $contextid,
            $scaleid,
            $courseid,
            $alloweduserids,
            $testid,
            $starttime,
            $endtime,
            $selection
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

        // Not $catscaleids[0]: callers hand this list on id-keyed as well, and index 0 would
        // then be undefined - the filter would fall away without anyone noticing.
        if (!empty($catscaleids) && (int) reset($catscaleids) > 0) {
            [$incatscales, $inparams] = $DB->get_in_or_equal($catscaleids, SQL_PARAMS_NAMED, 'countcatscales');
            $params = array_merge($params, $inparams);
            $wherecontains[] = "lccs.id $incatscales";
        }

        // Issue #157: the items of the context, with the parameters of the item in it - as the list.
        $params['itemcontextid'] = $contextid;
        $from = <<<SQL
        {local_catquiz_catscales} lccs
            JOIN {local_catquiz_items} lci ON lci.catscaleid = lccs.id AND lci.contextid = :itemcontextid
            LEFT JOIN {local_catquiz_itemparams} lcip
              ON lcip.itemid = lci.id AND lci.activeparamid = lcip.id
                AND lcip.contextid = lci.contextid AND (lcip.componentid = lci.componentid OR COALESCE(lcip.componentid, 0) = 0)
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
    /**
     * Returns every spelling under which a component is stored in local_catquiz_attempts.
     *
     * The column carries two spellings for the same component. save_attempt_to_db() writes the bare
     * module name it finds in the quiz settings - 'adaptivequiz' - while progress and the start of an
     * attempt use the frankenstyle name - 'mod_adaptivequiz'. context_resolver has long accepted
     * both. A lookup that compares with one of them only misses rows written with the other: the
     * CAT attempt of a running test disappeared the moment the result page had saved it.
     * Normalising what is stored belongs to issue #105; until then, lookups accept both.
     *
     * @param string $component The component as the caller names it.
     * @return string[] The frankenstyle name and the bare module name.
     */
    public static function component_names(string $component): array {
        if (strpos($component, 'mod_') === 0) {
            return [$component, substr($component, strlen('mod_'))];
        }

        return [$component, 'mod_' . $component];
    }

    /**
     * Returns the internal CAT attempt id for the attempt of a component.
     *
     * A component knows its own attempt id; local_catquiz_attempts holds that id next to its own.
     * Resolving in one place keeps the two namespaces apart - everything a caller hands in is
     * external, everything stored in the CATquiz tables is internal.
     *
     * @param int $adaptiveattemptid Id of the attempt of the component: adaptivequiz_attempt.id - not local_catquiz_attempts.id.
     * @param string $component The component the attempt belongs to.
     * @return int|null The id in local_catquiz_attempts, null when there is no CAT attempt yet.
     */
    public static function get_cat_attempt_id(int $adaptiveattemptid, string $component): ?int {
        global $DB;

        [$insql, $inparams] = $DB->get_in_or_equal(self::component_names($component), SQL_PARAMS_NAMED, 'comp');
        $ids = $DB->get_fieldset_select(
            'local_catquiz_attempts',
            'id',
            "attemptid = :attemptid AND component $insql",
            ['attemptid' => $adaptiveattemptid] + $inparams
        );

        if (empty($ids)) {
            return null;
        }

        // More than one would mean the component started several CAT attempts for one attempt of
        // its own. The newest is the running one.
        return (int) max($ids);
    }

    /**
     * Writes the current ability of a person for one scale.
     *
     * @param int $userid The person.
     * @param int $contextid The CAT context.
     * @param int $catscaleid The scale.
     * @param float $ability The estimated ability.
     * @param int|null $catattemptid The CAT attempt the value comes from - local_catquiz_attempts.id.
     *      Null when it does not come from a single attempt, as with a recalibration of the scale.
     * @param bool $isvalid Whether the estimate is valid; only valid rows become the current value.
     * @param string $resultsource Origin of the value: current, prior, legacy or recalibration.
     * @param float|null $standarderror Standard error of the estimate.
     * @param int|null $n Number of graded, non-pilot items behind the estimate.
     * @param float|null $fraction Fraction of correct answers.
     * @param bool $isprimary Whether this is the reported scale of the attempt.
     * @param string $validationstatus Comma-separated rejection reason codes.
     */
    public static function update_person_param(
        int $userid,
        int $contextid,
        int $catscaleid,
        float $ability,
        ?int $catattemptid = null,
        bool $isvalid = true,
        string $resultsource = 'current',
        ?float $standarderror = null,
        ?int $n = null,
        ?float $fraction = null,
        bool $isprimary = false,
        string $validationstatus = ''
    ) {
        global $DB;

        $now = time();

        /* Appended, not overwritten: local_catquiz_personparams keeps the history of what was
           estimated, one row per estimate. Reads take the newest valid row per user, context and
           scale - see get_current_person_param(). Overwriting would throw away exactly the
           information the per-attempt table used to hold. */
        $record = (object) [
            'userid' => $userid,
            'contextid' => $contextid,
            'catscaleid' => $catscaleid,
            'ability' => $ability,
            'standarderror' => $standarderror,
            'n' => $n,
            'fraction' => $fraction,
            'isprimary' => $isprimary ? 1 : 0,
            'isvalid' => $isvalid ? 1 : 0,
            'resultsource' => $resultsource,
            'validationstatus' => $validationstatus,
            'timecreated' => $now,
            'timemodified' => $now,
        ];

        /* Where the value comes from. Null is a legitimate answer, not a missing one: a
           recalibration of the whole scale estimates every person from the entire response set and
           belongs to no single attempt. Only a value that does come from one names it. */
        if ($catattemptid !== null) {
            $record->attemptid = $catattemptid;
        }

        $DB->insert_record('local_catquiz_personparams', $record);
    }

    /**
     * Returns the estimate a person is currently working from for one scale.
     *
     * The newest valid row wins. Rows without an attempt count too - a recalibration of the scale
     * produces the best available value and must not fall behind an older attempt result.
     *
     * @param int $userid The person.
     * @param int $contextid The CAT context.
     * @param int $catscaleid The scale.
     * @return \stdClass|null
     */
    public static function get_current_person_param(int $userid, int $contextid, int $catscaleid): ?\stdClass {
        global $DB;

        $records = $DB->get_records(
            'local_catquiz_personparams',
            ['userid' => $userid, 'contextid' => $contextid, 'catscaleid' => $catscaleid, 'isvalid' => 1],
            'id DESC',
            '*',
            0,
            1
        );

        return $records ? reset($records) : null;
    }

    /**
     * Return the attempt with the given attemptid
     *
     * @param int $adaptiveattemptid Id of the attempt of the component: adaptivequiz_attempt.id - not local_catquiz_attempts.id.
     * @return array<\stdClass>
     */
    public static function get_attempt_statistics(int $adaptiveattemptid) {
        global $DB;
        // Return exactly one row per question (per question attempt),
        // carrying the fraction of its LAST graded step. A question can have
        // several graded steps, so counting steps would overcount; here the inner
        // subquery reduces to the latest graded step per question attempt. A
        // question without any graded step (skipped/unanswered) yields a NULL
        // fraction via the LEFT JOIN, which the caller counts as "unanswered"
        // rather than "wrong". Pilot exclusion happens in the caller because the
        // pilot flag is context-computed, not a database column. In slot order: a
        // question administered twice has two rows, and callers count the first.
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
            WHERE aa.id = :attemptid
            ORDER BY qa.slot ASC",
            ['attemptid' => $adaptiveattemptid]
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
        $where = "pp.contextid = :contextid";
        $params = ['contextid' => $contextid];

        if ($catscaleids) {
            [$inscalesql, $inscaleparams] = $DB->get_in_or_equal(
                $catscaleids,
                SQL_PARAMS_NAMED,
                'incatscales'
            );
            $where .= " AND pp.catscaleid " . $inscalesql;
            $params = array_merge($params, $inscaleparams);
        }

        /* The newest valid row per person and scale, not every row: the table keeps the history of
           what was estimated. Rows without an attempt count too - a recalibration produces the best
           available value and must not fall behind an older attempt result. */
        $sql = "
            SELECT pp.*
            FROM {local_catquiz_personparams} pp
            WHERE $where
              AND pp.isvalid = 1
              AND pp.id = (
                  SELECT MAX(pp2.id)
                    FROM {local_catquiz_personparams} pp2
                   WHERE pp2.userid = pp.userid
                     AND pp2.contextid = pp.contextid
                     AND pp2.catscaleid = pp.catscaleid
                     AND pp2.isvalid = 1
              )";

        if ($userids) {
            [$inuseridssql, $inuseridsparams] = $DB->get_in_or_equal(
                $userids,
                SQL_PARAMS_NAMED,
                'inuserids'
            );
            $sql .= " AND pp.userid $inuseridssql";
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
     * person (the newest valid personparam per user, peer_population), excludes the compared user, and
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
        // Issue #88: "valid" is the stored verdict (isvalid), the same population as the histogram next to
        // the percentile (peer_population). Without it an invalid result inside the bound counted here
        // and nowhere else.
        [$peersql, $params] = \local_catquiz\local\statistics\peer_population::sql(
            $contextid,
            $catscaleid,
            $excludeuserid
        );
        $sql = "
            SELECT
                COUNT(1) AS n,
                AVG(peers.ability) AS meanvalue,
                SUM(CASE WHEN peers.ability < :scorelt THEN 1 ELSE 0 END) AS lowercount,
                SUM(CASE WHEN peers.ability = :scoreeq THEN 1 ELSE 0 END) AS equalcount
            FROM ($peersql) peers";
        $params += [
            'scorelt' => $score,
            'scoreeq' => $score,
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
     * @param int $adaptiveattemptid Id of the attempt of the component: adaptivequiz_attempt.id - not local_catquiz_attempts.id.
     *
     * @return object
     *
     */
    public static function get_testenvironment_by_attemptid(int $adaptiveattemptid) {
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
                'id' => $adaptiveattemptid,
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

        // Issue #157: the parameters of items of the context only - an item of another context with parameters in
        // this one is an integrity error (item_param_integrity), not a source of parameters.
        return $DB->get_records_sql(
            "SELECT lci.id as uniqueid, lcip.*
             FROM {local_catquiz_items} lci
             JOIN {local_catquiz_itemparams} lcip
                ON lci.id = lcip.itemid AND lci.contextid = lcip.contextid
                    AND (lcip.componentid = lci.componentid OR COALESCE(lcip.componentid, 0) = 0)
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

        // Issue #157 (T10): the CAT context the attempt plays in (kept by its progress), not the one its scale has now.
        $catcontext = isset($attemptdata['progress']) && $attemptdata['progress'] instanceof \local_catquiz\teststrategy\progress
            ? $attemptdata['progress']->get_contextid() : 0;
        $catcontext = $catcontext ?: catscale::get_context_id($attemptdata['catscaleid']);

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
            // The enrolment message written at finalisation (issue #129) belongs to the attempt, not to
            // the feedback data being saved; a save after finalisation must not drop it.
            $previous = json_decode((string) ($existingrecord->json ?? ''), true);
            if (is_array($previous) && isset($previous['enrolmentmessage']) && !isset($attemptdata['enrolmentmessage'])) {
                $attemptdata['enrolmentmessage'] = $previous['enrolmentmessage'];
                $data->json = json_encode($attemptdata);
            }
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
     * @param int $adaptiveattemptid Id of the attempt of the component: adaptivequiz_attempt.id - not local_catquiz_attempts.id.
     * @param string $status
     *
     * @return void
     */
    public static function set_final_attempt_status(int $adaptiveattemptid, string $status) {
        global $DB;
        $statusnumber = status::to_int($status);
        if (!$existingrecord = $DB->get_record('local_catquiz_attempts', ['attemptid' => $adaptiveattemptid])) {
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
     * @param int    $adaptiveattemptid Id of the attempt of the component: adaptivequiz_attempt.id - not local_catquiz_attempts.id.
     * @return array
     * @throws dml_exception
     */
    public static function return_course_and_instance_id(string $modulename, int $adaptiveattemptid) {
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
                'attemptid' => $adaptiveattemptid,
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
     * The returned attempts are completed (end time set, issue #88) and sorted by endtime in ascending
     * order. The scale is compared with local_catquiz_attempts.scaleid, which holds the root scale.
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
        // Completed attempts only (issue #88): the end time is stamped once, at finalisation. A running
        // attempt is saved with status "OK" after every answer, so the status said nothing - an open
        // attempt was listed as a result. The stop reasons share their numbers with the attempt states,
        // so the status is no criterion at all (issue #101 kept running attempts out the same way).
        $sql = "$with SELECT $fields FROM {local_catquiz_attempts} a $join WHERE "
            . \local_catquiz\local\statistics\attempt_population::completed_sql('a');

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
        // Ties of the same second in a fixed order: the first and the last attempt are well defined.
        $sql .= " ORDER BY a.endtime ASC, a.id ASC";
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
     * @param int|null $userid The person whose attempt it is; defaults to the current user.
     * @param int|null $courseid The course of the activity; defaults to $COURSE.
     *
     * @return string
     */
    public static function enrol_user(
        array $quizsettings,
        array $coursestoenrol,
        array $groupstoenrol,
        ?int $userid = null,
        ?int $courseid = null
    ): string {
        global $USER, $COURSE;

        // The person whose attempt it is, in the course of the activity (issue #129). At
        // finalisation the current user may be someone else - a teacher closing the attempt, or the
        // administrator running the time-limit task - and $COURSE the site.
        $userid = $userid ?? (int) $USER->id;
        $courseid = $courseid ?? (int) $COURSE->id;

        /* Issue #130: every routing scale exactly once. This used to loop over the scales and hand
           the whole map to a function that looped over it again; the inner result replaced the
           outer one on each pass. A scale that routes into groups only is a scale too. */
        $enrolmentarray = ['course' => [], 'group' => []];
        foreach (array_keys($coursestoenrol + $groupstoenrol) as $catscaleid) {
            $entries = self::enrol_and_create_message_array(
                $coursestoenrol[$catscaleid] ?? [],
                $groupstoenrol[$catscaleid] ?? [],
                (string) ($quizsettings['name'] ?? ''),
                (int) $catscaleid,
                $userid,
                $courseid
            );
            array_push($enrolmentarray['course'], ...($entries['course'] ?? []));
            array_push($enrolmentarray['group'], ...($entries['group'] ?? []));
        }
        $enrolmentarray = array_filter($enrolmentarray);

        $enrolementstrings = self::create_strings_for_enrolement_notification($enrolmentarray);
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
     * Enrols the user for one routing scale and returns what to tell them.
     *
     * One scale, its own courses and groups (issue #130). Courses are enrolled into, then the groups
     * of this scale in those courses and in the current course; existing memberships are left alone
     * and produce neither an event nor a message line. Events are triggered for every new
     * membership; the message lines are returned only if the range of this scale asks for a message.
     *
     * @param array $coursedata The routing data of this scale: course_ids, range, show_message.
     * @param array $groups Names of the groups of this scale.
     * @param string $testname
     * @param int $catscaleid The scale being processed - and the one named in events and messages.
     * @param int $userid
     * @param int|null $courseid The course of the activity; its groups are joined too. Defaults to $COURSE.
     * @return array Lines for the message: 'course' and 'group', each a list.
     */
    public static function enrol_and_create_message_array(
        array $coursedata,
        array $groups,
        string $testname,
        int $catscaleid,
        int $userid,
        ?int $courseid = null
    ): array {
        global $DB, $COURSE;

        $homecourseid = $courseid ?? (int) $COURSE->id;

        try {
            $catscale = catscale::return_catscale_object($catscaleid);
        } catch (\Exception $e) {
            $catscale = (object) ['name' => ''];
        }
        $scalename = $catscale->name ?? '';
        $rolestudent = $DB->get_record('role', ['shortname' => 'student']);
        $lines = ['course' => [], 'group' => []];

        $courseids = array_values(array_unique(array_merge(
            array_map('intval', $coursedata['course_ids'] ?? []),
            [$homecourseid]
        )));
        foreach ($courseids as $courseid) {
            $course = get_course($courseid);
            $context = \context_course::instance($courseid);
            $courseurl = (new moodle_url('/course/view.php', ['id' => $courseid]))->out();

            if ($courseid !== $homecourseid && !is_enrolled($context, $userid)) {
                if (enrol_try_internal_enrol($courseid, $userid, $rolestudent->id)) {
                    $line = [
                        'testname' => $testname,
                        'coursename' => $course->fullname ?? '',
                        'coursesummary' => $course->summary ?? '',
                        'courseurl' => $courseurl,
                        'catscalename' => $scalename,
                    ];
                    // This line used to go into $enrolementarray - a different variable from the one
                    // returned - so no course enrolment ever reached the message.
                    $lines['course'][] = $line;
                    self::course_enrolment_event($line, $userid);
                }
            }

            if (!$groups) {
                continue;
            }
            // Only groups that exist in the course are joined.
            foreach (groups_get_all_groups($courseid) as $existinggroup) {
                if (!in_array($existinggroup->name, $groups) || groups_is_member($existinggroup->id, $userid)) {
                    continue;
                }
                if (groups_add_member($existinggroup->id, $userid)) {
                    $line = [
                        'testname' => $testname,
                        'groupname' => $existinggroup->name,
                        'groupdescription' => $existinggroup->description ?? '',
                        'coursename' => $course->fullname ?? '',
                        'courseurl' => $courseurl,
                        'catscalename' => $scalename,
                    ];
                    $lines['group'][] = $line;
                    self::group_enrolment_event($line, $userid);
                }
            }
        }

        return empty($coursedata['show_message']) ? [] : array_filter($lines);
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
     * @param int|null $testid Only answers in this test (adaptivequiz.id), or null.
     * @param int|null $starttime Only answers in attempts ending at or after this time.
     * @param int|null $endtime Only answers in attempts ending at or before this time.
     *
     * @return array
     *
     */
    public static function get_sql_for_questions_answered_per_person(
        int $contextid,
        int $scaleid,
        ?int $courseid = null,
        ?array $alloweduserids = null,
        ?int $testid = null,
        ?int $starttime = null,
        ?int $endtime = null
    ) {
        global $DB;

        $catscaleids = [$scaleid, ...catscale::get_subscale_ids($scaleid)];

        // Get questions answered for the given context.
         [, $from, $where, $params] = self::get_sql_for_stat_base_request([], [$contextid]);
        [$insql, $inparams] = $DB->get_in_or_equal($catscaleids, SQL_PARAMS_NAMED, 'incatscales');
        $params = array_merge($params, $inparams);

        // Answers count from the attempts of the statistics' population (issue #88): completed, in
        // course, test and period. Test and period were accepted by the callers and dropped.
        $population = new \local_catquiz\local\statistics\attempt_population(
            $scaleid,
            $courseid ?: null,
            $testid ?: null,
            null,
            $starttime ?: null,
            $endtime ?: null
        );
        [$populationwhere, $populationparams] = $population->where('ca', 'qpa');
        // The ability: the latest valid result in the population, as in the attempts chart. The current
        // estimate in {local_catquiz_personparams} keeps one row per estimate and returned a person once
        // for each of them.
        [$scopewhere, $scopeparams] = $population->where('a2', 'qpb');
        [$value, $valueparams] = $population->value_sql('a2', 'qpv');
        [$valuewhere, $valuewhereparams] = $population->value_sql('a2', 'qpw');
        $abilitysql = "SELECT $value
                         FROM {local_catquiz_attempts} a2
                        WHERE a2.userid = ue.userid
                          AND $scopewhere
                          AND ($valuewhere) IS NOT NULL
                     ORDER BY a2.endtime DESC, a2.id DESC
                        LIMIT 1";
        $params = array_merge($params, $populationparams, $scopeparams, $valueparams, $valuewhereparams);
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

        $sql = "SELECT DISTINCT ue.userid, COALESCE(answercount, 0) total_answered, ($abilitysql) ability
                FROM {enrol} e
                JOIN {user_enrolments} ue ON e.id = ue.enrolid
                -- No join on {role}: enrol.roleid belongs to the enrolment instance,
                -- not to the person, and plugins such as the LTI enrolment leave it
                -- at 0. Requiring a matching role - let alone the shortname
                -- 'student' - silently removes everyone enrolled that way, which on
                -- an LTI course is nearly the entire population.
                LEFT JOIN (
                    SELECT s1.userid, COUNT(DISTINCT s1.id) as answercount
                    FROM (
                        SELECT qas.id, qas.userid, qa.questionid, qa.questionusageid
                        FROM $from
                        WHERE $where
                    ) s1
                    -- Make sure we only get responses from the quizzes in the given course.
                    JOIN {adaptivequiz_attempt} aa ON aa.uniqueid = s1.questionusageid
                    JOIN {adaptivequiz} a ON a.id = aa.instance
                    JOIN {local_catquiz_attempts} ca ON ca.attemptid = aa.id
                    -- Re-audit 02.11.2026 (#157): the items of the question in the CAT context of the attempt, with
                    -- their active params. The joins before compared an item id with a question id
                    -- (lci.id = lcip.componentid); an answer is counted once, whatever number of items it has.
                    JOIN {local_catquiz_items} lci
                      ON lci.componentname = 'question' AND s1.questionid = lci.componentid AND lci.contextid = ca.contextid
                    JOIN {local_catquiz_itemparams} lcip
                      ON lcip.id = lci.activeparamid AND lcip.itemid = lci.id AND lcip.contextid = lci.contextid
                    WHERE $where3 AND $populationwhere
                    GROUP BY s1.userid
                ) s2 ON ue.userid = s2.userid
                WHERE $where2 $userfilter";
        return [$sql, $params];
    }

    /**
     * Return the number of attempts per person
     *
     * The attempts come from the population of the statistics (issue #88, attempt_population): completed,
     * selected by the root scale, in course, test and period. The value that colours a person's bar is
     * their latest valid result for the requested scale in that population - the same rule as the ability
     * profile. The CAT context is no filter: it records the calibration an attempt was scored under, not
     * whether it took place (see below).
     *
     * @param int $contextid Not a filter; kept for the callers.
     * @param int $scaleid
     * @param ?int $courseid
     * @param array|null $alloweduserids Restriction from the group rules, or null.
     * @param int|null $testid Restrict to one activity instance, or null for all.
     * @param int|null $starttime Only attempts ending at or after this time.
     * @param int|null $endtime Only attempts ending at or before this time.
     * @param string $selection attempt_population::ALL, FIRST or LAST.
     * @return array [sql, params]
     */
    public static function get_sql_for_attempts_per_person(
        int $contextid,
        int $scaleid,
        ?int $courseid,
        ?array $alloweduserids = null,
        ?int $testid = null,
        ?int $starttime = null,
        ?int $endtime = null,
        string $selection = \local_catquiz\local\statistics\attempt_population::ALL
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
        //
        // Scope, completion and the selection of first or last attempts come from the one definition all
        // charts share (issue #88). A subscale selects its root scale's attempts: local_catquiz_attempts
        // holds the root scale, and asking for the subscale itself found nothing.
        $population = new \local_catquiz\local\statistics\attempt_population(
            $scaleid,
            $courseid ?: null,
            $testid ?: null,
            null,
            $starttime ?: null,
            $endtime ?: null,
            $selection
        );
        [$where, $params] = $population->where('a', 'pa', $alloweduserids);
        // The second copy of the scope, for the people without an attempt: its parameters need names of
        // their own, Moodle counts named parameters per occurrence.
        [$notexistswhere, $notexistsparams] = $population->where('ne', 'pne', $alloweduserids);

        // The ability that colours the bar: the person's latest valid result for the requested scale in
        // the same selection - the value they were shown, not the current estimate in
        // {local_catquiz_personparams}, which every recalibration overwrites. It used to take the latest
        // attempt on the scale without scope or completion, ordered by an end time that sorts the NULL of
        // a running attempt first on PostgreSQL.
        [$scopewhere, $scopeparams] = $population->where('a2', 'pab');
        [$value, $valueparams] = $population->value_sql('a2', 'pav');
        [$valuewhere, $valuewhereparams] = $population->value_sql('a2', 'paw');
        $abilitysql = "SELECT $value
                         FROM {local_catquiz_attempts} a2
                        WHERE a2.userid = a.userid
                          AND $scopewhere
                          AND ($valuewhere) IS NOT NULL
                     ORDER BY a2.endtime DESC, a2.id DESC
                        LIMIT 1";

        // The bar "no attempt" counts the people who could have taken a test of the scope - actively
        // enrolled, allowed to attempt it, the activity visible and available to them - not everyone
        // enrolled in the course, let alone on the platform (issue #132, eligible_population).
        $eligible = \local_catquiz\local\statistics\eligible_population::userids($scaleid, $courseid, $testid);

        // Null means no restriction; an empty array means nothing is visible and has
        // to yield no rows rather than all of them.
        if ($alloweduserids !== null) {
            $eligible = array_values(array_intersect($eligible, array_map('intval', $alloweduserids)));
        }
        $enrolparams = [];
        if ($eligible) {
            [$populationsql, $enrolparams] = $DB->get_in_or_equal($eligible, SQL_PARAMS_NAMED, 'eligible');
            $populationwhere = "u.id $populationsql";
        } else {
            $populationwhere = '1 = 0';
        }

        // The people of the population without an attempt in the selection form the
        // "no attempt" bucket. They are the one thing the attempts table cannot supply.
        //
        // The NOT EXISTS repeats the scope deliberately. It used to test the scale
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
                      GROUP BY a.userid

                         UNION ALL

                        SELECT u.id userid, CAST(NULL AS DECIMAL(10,5)) ability, 0 attempts
                          FROM {user} u
                         WHERE $populationwhere
                           AND NOT EXISTS (
                               SELECT 1
                                 FROM {local_catquiz_attempts} ne
                                WHERE ne.userid = u.id
                                  AND $notexistswhere
                           )
                  ) counted
              GROUP BY userid
              ORDER BY attempts";

        $params = array_merge($params, $scopeparams, $valueparams, $valuewhereparams, $enrolparams, $notexistsparams);

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
     * @param int|null $catscaleid The scale of the item.
     * @return stdClass|false The record object retrieved from the database.
     */
    public static function get_item(int $contextid, int $componentid, string $componentname, ?int $catscaleid = null) {
        global $DB;
        // Issue #104, H3: a question can be an item of several scales. With the scale, its item; without, the
        // item with the lowest id - not whichever row the database returns first.
        $conditions = [
            'contextid' => $contextid,
            'componentid' => $componentid,
            'componentname' => $componentname,
        ];
        if ($catscaleid) {
            $conditions['catscaleid'] = $catscaleid;
        }
        $items = $DB->get_records('local_catquiz_items', $conditions, 'id ASC', '*', 0, 1);
        return $items ? reset($items) : false;
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
     * Test seam (issue #148): called inside the transaction of create_items_in_new_context() before it is
     * committed; a test throws from here to prove that nothing of the change stays.
     *
     * @var callable|null
     */
    public static $beforecontextcommit = null;

    /**
     * Moves the items of a scale tree into a new CAT context, with their parameters (issue #148).
     *
     * The identity of an item is its question AND its scale: the same question can be an item of several
     * scales, each with parameters of its own. Before, items and parameters were mapped by question only - one
     * item per question survived, all parameters of the question were attached to it, the item of the other
     * scale lost its parameters, and with equal models the unique index (itemid, contextid, model) failed.
     *
     * For every item of the old or the new context (never of the contexts whose ids lie in between):
     * 1. Its parameters in the new context: those that point to it, else - fetched without an item, e.g. from
     *    a hub - those of its question, each item of the question with a copy of its own. Per model one: the
     *    highest status, then the latest change, then the lowest id.
     * 2. A model it has in the old context but not yet in the new one is copied over.
     * 3. If the old context is in use by a test, the item is copied into the new context, else moved; its
     *    parameters point to it, the active one is the parameter of the highest status.
     * Everything happens in one transaction: an error leaves nothing behind. Calling it again changes nothing.
     *
     * @param int $newcontextid The ID of the context to create items in
     * @param ?int $oldcontextid The ID of the old context, if any
     * @return void
     */
    public function create_items_in_new_context(int $newcontextid, ?int $oldcontextid): void {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        try {
            $oldcontextid = $oldcontextid && $oldcontextid !== $newcontextid ? $oldcontextid : null;
            $createnew = !$oldcontextid || $this->is_active_context($oldcontextid);

            [$insql, $inparams] = $DB->get_in_or_equal(
                array_filter([$newcontextid, $oldcontextid]),
                SQL_PARAMS_NAMED,
                'ctx'
            );
            $items = $DB->get_records_select('local_catquiz_items', "contextid $insql", $inparams, 'id ASC');

            // One item per question and scale - the one in the new context, if there is one.
            $itembykey = [];
            $keysbyquestion = [];
            $keyofitem = [];
            foreach ($items as $item) {
                $key = self::item_identity($item);
                $keyofitem[(int) $item->id] = $key;
                $keysbyquestion[(int) $item->componentid][$key] = $key;
                if (!isset($itembykey[$key]) || (int) $item->contextid === $newcontextid) {
                    $itembykey[$key] = $item;
                }
            }
            $keysof = function (stdClass $param) use ($keyofitem, $keysbyquestion): array {
                if (isset($keyofitem[(int) $param->itemid])) {
                    return [$keyofitem[(int) $param->itemid]];
                }
                return array_values($keysbyquestion[(int) $param->componentid] ?? []);
            };
            $better = fn(stdClass $a, stdClass $b): bool =>
                [(int) $a->status, (int) ($a->timemodified ?? 0), -(int) $a->id]
                > [(int) $b->status, (int) ($b->timemodified ?? 0), -(int) $b->id];

            // 1. The parameters of the new context, per item and model.
            $chosen = [];
            foreach ($DB->get_records('local_catquiz_itemparams', ['contextid' => $newcontextid], 'id ASC') as $param) {
                foreach ($keysof($param) as $key) {
                    $current = $chosen[$key][$param->model] ?? null;
                    if ($current === null || $better($param, $current)) {
                        $chosen[$key][$param->model] = $param;
                    }
                }
            }
            // A parameter can be chosen by one item only; the others get a copy.
            $taken = [];
            foreach ($chosen as $key => $models) {
                foreach ($models as $model => $param) {
                    if (isset($taken[(int) $param->id])) {
                        $copy = clone($param);
                        unset($copy->id);
                        $chosen[$key][$model] = $copy;
                    }
                    $taken[(int) ($param->id ?? 0)] = true;
                }
            }

            // 2. Models an item has only in the old context.
            if ($oldcontextid) {
                foreach ($DB->get_records('local_catquiz_itemparams', ['contextid' => $oldcontextid], 'id ASC') as $param) {
                    foreach ($keysof($param) as $key) {
                        $current = $chosen[$key][$param->model] ?? null;
                        if ($current !== null && !isset($current->oldparamid)) {
                            // The new context has this model for the item already.
                            continue;
                        }
                        if ($current === null || $better($param, $current)) {
                            $copy = clone($param);
                            unset($copy->id);
                            $copy->contextid = $newcontextid;
                            $copy->oldparamid = (int) $param->id;
                            $chosen[$key][$param->model] = $copy;
                        }
                    }
                }
            }

            // 3. The items, and their parameters pointing to them.
            foreach ($chosen as $key => $models) {
                $item = clone($itembykey[$key]);
                if ((int) $item->contextid !== $newcontextid) {
                    if ($createnew) {
                        unset($item->id);
                    }
                    $item->contextid = $newcontextid;
                }
                if (empty($item->id)) {
                    $item->activeparamid = null;
                    $item->id = (int) $DB->insert_record('local_catquiz_items', $item);
                }
                $active = null;
                foreach ($models as $param) {
                    $param->itemid = (int) $item->id;
                    $param->contextid = $newcontextid;
                    unset($param->oldparamid);
                    itemparam_validity::stamp($param);
                    if (empty($param->id)) {
                        $param->id = (int) $DB->insert_record('local_catquiz_itemparams', $param);
                    } else {
                        $DB->update_record('local_catquiz_itemparams', $param, true);
                    }
                    if ($active === null || $better($param, $active)) {
                        $active = $param;
                    }
                }
                $item->activeparamid = (int) $active->id;
                $DB->update_record('local_catquiz_items', $item);
            }

            if (self::$beforecontextcommit) {
                (self::$beforecontextcommit)();
            }
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }
        \cache_helper::purge_by_event('changesintestitems');
        \cache_helper::purge_by_event('changesinitemparams');
    }

    /**
     * The identity of an item across contexts: its component, its question and its scale (issue #148).
     *
     * @param stdClass $item A row of local_catquiz_items.
     * @return string
     */
    public static function item_identity(stdClass $item): string {
        return implode('|', [(string) ($item->componentname ?? 'question'), (int) $item->componentid, (int) $item->catscaleid]);
    }

    /**
     * Returns itemparams that are present in the oldcontext but not the new one
     *
     * Compares by question only. create_items_in_new_context() no longer uses it: it decides per item - question
     * and scale - and model (issue #148).
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
