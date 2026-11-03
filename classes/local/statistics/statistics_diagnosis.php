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

namespace local_catquiz\local\statistics;

use local_catquiz\catquiz;
use local_catquiz\catscale;
use local_catquiz\local\model\model_item_param;
use local_catquiz\local\model\model_model;
use local_catquiz\local\model\model_strategy;
use local_catquiz\local\result\result_provenance;
use local_catquiz\local\result\scale_result;
use local_catquiz\output\catquizstatistics;
use local_catquiz\teststrategy\feedback_helper;

/**
 * Explains the ability profile of [catquizstatistics] for one scale (issue #150, re-audit 27.10.2026).
 *
 * Who is counted, why the others are not, and where the test information comes from. Anonymised: counts, ids of
 * scales, tests and CAT contexts, and the bars - no user, no name, no single ability value. The bars are computed
 * twice, from the raw rows and by the statistics (attempt_population); both must be the same. Read-only. Used by
 * cli/diagnose_statistics.php.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class statistics_diagnosis {
    /**
     * The report.
     *
     * @param int $scaleid The scale of the chart.
     * @param int|null $courseid The course of the page, as the shortcode takes it.
     * @param int|null $testid adaptivequiz.id (local_catquiz_tests.componentid).
     * @param int|null $starttime Lower bound of the end time, included.
     * @param int|null $endtime Upper bound of the end time, included; null for now.
     * @param string $selection attempt_population::ALL, FIRST or LAST.
     * @return array Sections: scope, funnel, attempts_by_value, reasons_of_invalid_measurements, bars,
     *               personparams_of_the_scale, testinfo.
     */
    public static function report(
        int $scaleid,
        ?int $courseid = null,
        ?int $testid = null,
        ?int $starttime = null,
        ?int $endtime = null,
        string $selection = attempt_population::ALL
    ): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/catquiz/lib.php');
        $endtime = $endtime ?? time();
        $report = [];

        // 1. Scope: scale, tree, tests, strategies, reporting.
        $scale = catscale::return_catscale_object($scaleid);
        $rootid = attempt_population::root_of($scaleid);
        $subscales = catscale::get_subscale_ids($scaleid);
        $testconditions = $testid ? ['componentid' => $testid] : array_filter(['catscaleid' => $rootid, 'courseid' => $courseid]);
        $tests = [];
        $strategynames = [
            LOCAL_CATQUIZ_STRATEGY_FASTEST => 'FASTEST', LOCAL_CATQUIZ_STRATEGY_BALANCED => 'BALANCED',
            LOCAL_CATQUIZ_STRATEGY_ALLSUBS => 'ALLSUBS (infer all subscales)',
            LOCAL_CATQUIZ_STRATEGY_LOWESTSUB => 'LOWESTSUB (infer lowest skill gap)',
            LOCAL_CATQUIZ_STRATEGY_HIGHESTSUB => 'HIGHESTSUB (infer greatest strength)', LOCAL_CATQUIZ_STRATEGY_PILOT => 'PILOT',
            LOCAL_CATQUIZ_STRATEGY_CLASSIC => 'CLASSIC', LOCAL_CATQUIZ_STRATEGY_RELSUBS => 'RELSUBS (relevant subscales)',
        ];
        foreach ($DB->get_records('local_catquiz_tests', $testconditions) as $test) {
            $json = json_decode((string) $test->json);
            $strategy = (int) ($json->catquiz_selectteststrategy ?? 0);
            $checkbox = "catquiz_scalereportcheckbox_{$scaleid}";
            $tests[] = [
                'testid' => (int) $test->componentid, 'courseid' => (int) $test->courseid, 'rootscale' => (int) $test->catscaleid,
                'catcontext' => (int) $test->contextid, 'catcontext_in_settings' => (int) ($json->catquiz_catcontext ?? 0),
                'strategy' => $strategy, 'strategyname' => $strategynames[$strategy] ?? 'unknown',
                'reportcheckbox_of_scale' => property_exists((object) $json, $checkbox) ? $json->$checkbox : 'not set',
            ];
        }
        $report['scope'] = [
            'scale' => $scaleid, 'scalename_length' => \core_text::strlen((string) $scale->name), 'root' => $rootid,
            'subscales' => count($subscales), 'catcontext_of_scale' => catscale::get_context_id($scaleid),
            'course' => $courseid, 'test' => $testid, 'starttime' => $starttime, 'endtime' => $endtime,
            'selection' => $selection, 'tests' => $tests,
        ];

        // 2. Funnel of the attempts: which filter takes how many.
        $funnel = [];
        $where = 'a.scaleid = :root';
        $params = ['root' => $rootid];
        $funnel['attempts of the root scale'] = $DB->count_records_sql(
            "SELECT COUNT(1) FROM {local_catquiz_attempts} a WHERE $where",
            $params
        );
        if ($courseid) {
            $where .= ' AND a.courseid = :course';
            $params['course'] = $courseid;
            $funnel['... in the course'] = $DB->count_records_sql(
                "SELECT COUNT(1) FROM {local_catquiz_attempts} a WHERE $where",
                $params
            );
        }
        if ($testid) {
            $where .= ' AND a.instanceid = :test';
            $params['test'] = $testid;
            $funnel['... of the test'] = $DB->count_records_sql(
                "SELECT COUNT(1) FROM {local_catquiz_attempts} a WHERE $where",
                $params
            );
        }
        $funnel['... not completed (no end time), by status'] = $DB->get_records_sql_menu(
            "SELECT a.status, COUNT(1) FROM {local_catquiz_attempts} a WHERE $where AND a.endtime IS NULL GROUP BY a.status",
            $params
        );
        $where .= ' AND ' . attempt_population::completed_sql('a');
        $funnel['... completed (end time set)'] = $DB->count_records_sql(
            "SELECT COUNT(1) FROM {local_catquiz_attempts} a WHERE $where",
            $params
        );
        $funnel['... completed, by status'] = $DB->get_records_sql_menu(
            "SELECT a.status, COUNT(1) FROM {local_catquiz_attempts} a WHERE $where GROUP BY a.status",
            $params
        );
        $funnel['... completed, by CAT context'] = $DB->get_records_sql_menu(
            "SELECT a.contextid, COUNT(1) FROM {local_catquiz_attempts} a WHERE $where GROUP BY a.contextid",
            $params
        );
        $population = new attempt_population($scaleid, $courseid, $testid, null, $starttime, $endtime, $selection);
        $attempts = $population->attempts();
        $funnel['... in the period and selection (the population)'] = count($attempts);
        $funnel['... persons'] = count(array_unique(array_map(fn($a) => (int) $a->userid, $attempts)));
        $report['funnel'] = $funnel;

        // 3. Since when results are stored (result_provenance): attempts before count with their own value.
        $since = result_provenance::since();
        $report['results_stored_since'] = [
            'time' => $since ?: null,
            'date' => $since ? userdate($since, '%Y-%m-%d %H:%M:%S') : 'unknown: no attempt counts as before',
            'source' => (string) get_config('local_catquiz', result_provenance::CONFIG_SOURCE),
            'from_the_records_now' => result_provenance::determine(),
        ];

        // 4. The value of each attempt for the scale, from the raw rows - independent of attempt_population::attempts().
        $classes = [];
        $reasons = [];
        $rawvalues = [];
        foreach ($attempts as $attempt) {
            $rows = $DB->get_records('local_catquiz_personparams', ['attemptid' => $attempt->id, 'resultsource' => 'current']);
            if (!$rows && (int) $attempt->endtime >= $since) {
                // Finalised since results are stored, none stored: no result (issue #150, re-audit 28.10.2026).
                $value = null;
                $carries = $scaleid === $rootid && $attempt->personability_after_attempt !== null;
                $class = $carries
                    ? 'after results were stored, none stored, carries a value (not counted since 28.10.2026)'
                    : 'after results were stored, none stored, no value';
            } else if (!$rows) {
                $value = null;
                if ($scaleid === $rootid && $attempt->personability_after_attempt !== null) {
                    $value = (float) $attempt->personability_after_attempt;
                } else {
                    $json = json_decode((string) $attempt->json);
                    if (isset($json->personabilities->$scaleid) && is_numeric($json->personabilities->$scaleid)) {
                        $value = (float) $json->personabilities->$scaleid;
                    }
                }
                $class = $value === null ? 'before stored results, no value' : 'before stored results, with value (counted)';
            } else {
                $row = null;
                foreach ($rows as $candidate) {
                    if ((int) $candidate->catscaleid === $scaleid) {
                        $row = $candidate;
                    }
                }
                $value = null;
                if (!$row) {
                    $class = 'stored results, scale not among them (not measured)';
                } else if ((int) $row->isvalid === 1) {
                    $class = 'stored: valid result (primary) (counted)';
                    $value = $row->ability !== null ? (float) $row->ability : null;
                } else if (scale_result::is_valid_measurement(0, $row->validationstatus)) {
                    $class = 'stored: valid measurement, not the reported scale (counted since 27.10.2026)';
                    $value = $row->ability !== null ? (float) $row->ability : null;
                } else {
                    $class = 'stored: not a valid measurement';
                    foreach (array_filter(explode(',', (string) $row->validationstatus)) as $reason) {
                        $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;
                    }
                    if ((string) $row->validationstatus === '') {
                        $reasons['(no reason stored)'] = ($reasons['(no reason stored)'] ?? 0) + 1;
                    }
                }
            }
            $classes[$class] = ($classes[$class] ?? 0) + 1;
            if ($value !== null) {
                // Latest (or first) per person in the order of the end time, as the chart.
                if ($selection !== 'first' || !array_key_exists((int) $attempt->userid, $rawvalues)) {
                    $rawvalues[(int) $attempt->userid] = $value;
                }
            }
        }
        ksort($classes);
        ksort($reasons);
        $report['attempts_by_value'] = $classes;
        $report['reasons_of_invalid_measurements'] = $reasons;

        // 5. Bars: from the raw rows, and as the statistics compute them.
        $range = (new feedback_helper())->get_ability_range($scaleid);
        $steps = [];
        for ($i = (float) $range['minscalevalue'] + 0.25; $i <= (float) $range['maxscalevalue'] - 0.25; $i += 0.5) {
            $steps[] = $i;
        }
        $rawbars = chart_output::counts_per_step($steps, array_values($rawvalues), 0.25);
        $codevalues = attempt_population::value_per_person($attempts, 'last');
        $codebars = chart_output::counts_per_step($steps, array_values($codevalues), 0.25);
        $report['bars'] = [
            'labels' => chart_output::step_labels($steps),
            'from_raw_rows' => $rawbars, 'from_the_statistics' => $codebars, 'identical' => $rawbars === $codebars,
            'n_persons' => count($codevalues), 'sum' => array_sum($codebars),
        ];

        // 6. Values of the scale that are not results of an attempt: never in the statistics.
        $report['personparams_of_the_scale'] = array_values(array_map(fn($r) => (array) $r, $DB->get_records_sql(
            "SELECT "
                . $DB->sql_concat('x.resultsource', "'/'", 'x.isvalid', "'/'", 'x.contextid', "'/'", 'x.withattempt')
                . " AS k,
                    x.resultsource, x.isvalid, x.contextid, x.withattempt,
                    COUNT(1) AS rowcount, COUNT(DISTINCT x.userid) AS persons
               FROM (SELECT COALESCE(pp.resultsource, '') AS resultsource, pp.isvalid, COALESCE(pp.contextid, 0) AS contextid,
                            CASE WHEN pp.attemptid IS NULL THEN 0 ELSE 1 END AS withattempt, pp.userid
                       FROM {local_catquiz_personparams} pp
                      WHERE pp.catscaleid = :scale) x
           GROUP BY x.resultsource, x.isvalid, x.contextid, x.withattempt",
            ['scale' => $scaleid]
        )));

        // 7. Test information, per CAT context in question.
        $helper = new feedback_helper();
        $contexts = array_unique(array_filter(array_merge(
            [(int) catscale::get_context_id($scaleid)],
            array_map(fn($t) => $t['catcontext'], $tests)
        )));
        $statistics = new catquizstatistics(
            $courseid,
            $testid,
            $scaleid,
            $endtime,
            $starttime,
            $selection
        );
        $report['testinfo'] = ['context_the_statistics_use' => $statistics->get_testinfo_contextid(), 'by_context' => []];
        foreach ($contexts as $contextid) {
            $info = $helper->testinfo_for_scale($scaleid, (int) $contextid, $steps);
            $items = $helper->get_testitems_for_catscale($scaleid, (int) $contextid, true);
            $direct = count(array_filter($items, fn($i) => (int) $i->catscaleid === $scaleid));
            $models = [];
            foreach ($items as $item) {
                $key = $item->model ?: '(none)';
                $models[$key] = ($models[$key] ?? 0) + 1;
            }
            $report['testinfo']['by_context'][$contextid] = [
                'items' => $info['items'], 'of_the_scale_itself' => $direct, 'of_subscales' => $info['items'] - $direct,
                'by_model' => $models, 'with_parameters_of_an_installed_model' => $info['withparameters'],
                'computed' => $info['computed'], 'cause' => $info['cause'] ?? 'shown',
                'stages' => self::testinfo_stages($scaleid, (int) $contextid, $steps),
            ];
        }

        return $report;
    }

    /**
     * The test information of a scale in a CAT context, stage by stage (issue #150, re-audit 28.10.2026, AP-04).
     *
     * From the items assigned in the context to the information at every ability step: how many are left after each
     * condition, and why the others dropped out. Read-only: the runtime pool is built here without its cache, and the
     * cache is only read, never written or purged. Counts and ids of contexts only - no person, no answer, no single
     * parameter.
     *
     * @param int $scaleid
     * @param int $contextid
     * @param float[] $steps The ability steps of the chart.
     * @return array
     */
    public static function testinfo_stages(int $scaleid, int $contextid, array $steps): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/catquiz/lib.php');
        $scaleids = array_values(array_unique(array_map('intval', [$scaleid, ...catscale::get_subscale_ids($scaleid)])));
        [$insql, $params] = $DB->get_in_or_equal($scaleids, SQL_PARAMS_NAMED, 'tis');
        $params += [
            'ctx' => $contextid, 'ctx2' => $contextid,
            'ready' => \core_question\local\bank\question_version_status::QUESTION_STATUS_READY,
            'inactive' => LOCAL_CATQUIZ_TESTITEM_STATUS_INACTIVE,
        ];
        // One row per item of the context, with what the conditions of the pool look at.
        $rows = $DB->get_records_sql(
            "SELECT lci.id, lci.componentid, lci.status AS itemstatus, lci.activeparamid,
                    lcip.id AS paramid, lcip.model, lcip.contextid AS paramcontextid,
                    q.id AS questionid, qvl.status AS questionstatus
               FROM {local_catquiz_items} lci
          LEFT JOIN {local_catquiz_itemparams} lcip ON lcip.id = lci.activeparamid AND lcip.itemid = lci.id
                    AND lcip.contextid = :ctx2
          LEFT JOIN {question} q ON q.id = lci.componentid
          LEFT JOIN {question_versions} qv ON qv.questionid = q.id
          LEFT JOIN {question_versions} qvl ON qvl.questionbankentryid = qv.questionbankentryid
                    AND qvl.version = (SELECT MAX(qvm.version) FROM {question_versions} qvm
                                        WHERE qvm.questionbankentryid = qv.questionbankentryid)
              WHERE lci.catscaleid $insql AND lci.contextid = :ctx",
            $params
        );
        $installed = model_strategy::get_installed_models();
        $stages = [
            'items of the scale and its subscales in the context' => count($rows),
            '... with an active parameter (activeparamid)' => 0,
            '... of the item and in this context' => 0,
            '... question exists' => 0,
            '... newest version of the question ready' => 0,
            '... item active in its scale' => 0,
            '... model installed' => 0,
        ];
        $bymodel = [];
        $calibratedquestions = [];
        foreach ($rows as $row) {
            if (empty($row->activeparamid)) {
                continue;
            }
            $stages['... with an active parameter (activeparamid)']++;
            if (empty($row->paramid)) {
                continue;
            }
            $stages['... of the item and in this context']++;
            if (empty($row->questionid)) {
                continue;
            }
            $stages['... question exists']++;
            if ($row->questionstatus !== \core_question\local\bank\question_version_status::QUESTION_STATUS_READY) {
                continue;
            }
            $stages['... newest version of the question ready']++;
            if ((int) $row->itemstatus === LOCAL_CATQUIZ_TESTITEM_STATUS_INACTIVE) {
                continue;
            }
            $stages['... item active in its scale']++;
            $model = (string) $row->model;
            $bymodel[$model] = $bymodel[$model] ?? ['items' => 0, 'installed' => isset($installed[$model])];
            $bymodel[$model]['items']++;
            if (!isset($installed[$model])) {
                continue;
            }
            $stages['... model installed']++;
            $calibratedquestions[(int) $row->questionid] = true;
        }
        ksort($bymodel);

        // The pool the statistics and the tests read: built here without the cache, compared with the cached one.
        [$select, $from, $where, , $poolparams] = catquiz::return_sql_for_catscalequestions(
            $scaleids,
            $contextid,
            [],
            0,
            null,
            null,
            true,
            true
        );
        $pool = model_strategy::rows_of_installed_models(
            catscale::one_row_per_question($DB->get_recordset_sql("SELECT $select FROM $from WHERE $where", $poolparams))
        );
        ksort($pool, SORT_NUMERIC);
        $signature = fn(array $items) => hash('sha256', json_encode(array_map(
            fn($i) => [(int) $i->itemid, (string) $i->model, (string) $i->difficulty, (string) $i->discrimination,
                (string) $i->guessing],
            $items
        )));
        $cached = \cache::make('local_catquiz', 'runtimepool')->get(catscale::pool_key($contextid, true, $scaleid, []));
        $itemcontexts = $pool ? $DB->get_records_list(
            'local_catquiz_items',
            'id',
            array_map(fn($i) => (int) $i->itemid, $pool),
            '',
            'id, contextid'
        ) : [];
        $othercontext = 0;
        $calibratedasother = 0;
        $withparams = 0;
        foreach ($pool as $questionid => $item) {
            $itemcontext = (int) ($itemcontexts[(int) $item->itemid]->contextid ?? 0);
            if ($itemcontext !== $contextid) {
                $othercontext++;
            }
            if (!empty($item->model)) {
                $withparams++;
            } else if (isset($calibratedquestions[(int) $questionid])) {
                // The question has parameters in this context, the pool has a row without: the row of an item of
                // another context won the choice of one row per question (catscale::one_row_per_question()).
                $calibratedasother++;
            }
        }

        // The information of the pool, item by item and step by step.
        $contract = 0;
        $finiteatall = 0;
        $finiteatstep = array_fill_keys(array_map('strval', $steps), 0);
        foreach ($pool as $item) {
            if (empty($item->model) || !isset($installed[$item->model])) {
                continue;
            }
            try {
                $params = model_item_param::from_record($item)->get_params_array();
                $model = model_model::get_instance($item->model);
            } catch (\Throwable $e) {
                continue;
            }
            $contract++;
            $all = true;
            foreach ($steps as $step) {
                try {
                    $info = $model->fisher_info(['ability' => (float) $step], $params);
                } catch (\Throwable $e) {
                    $info = NAN;
                }
                if (is_finite($info)) {
                    $finiteatstep[strval($step)]++;
                } else {
                    $all = false;
                }
            }
            if ($all) {
                $finiteatall++;
            }
        }

        // Issue #157: the pool as the rule before 28.10.2026 built it - items of every context, the parameters of this
        // one or none, one row per question with the lowest item id -, for the before/after comparison.
        [$insql3, $params3] = $DB->get_in_or_equal($scaleids, SQL_PARAMS_NAMED, 'tio');
        $params3 += [
            'ctx' => $contextid, 'ctx2' => $contextid,
            'ready' => \core_question\local\bank\question_version_status::QUESTION_STATUS_READY,
            'inactive' => LOCAL_CATQUIZ_TESTITEM_STATUS_INACTIVE,
        ];
        $oldrows = $DB->get_recordset_sql(
            "SELECT lci.id, lci.componentid, lci.contextid, lcip.model
               FROM {local_catquiz_items} lci
          LEFT JOIN {local_catquiz_itemparams} lcip ON lcip.itemid = lci.id AND lci.activeparamid = lcip.id
               JOIN {question} q ON q.id = lci.componentid
               JOIN {question_versions} qv ON qv.questionid = q.id
               JOIN {question_versions} qvl ON qvl.questionbankentryid = qv.questionbankentryid
                    AND qvl.version = (SELECT MAX(qvm.version) FROM {question_versions} qvm
                                        WHERE qvm.questionbankentryid = qv.questionbankentryid)
              WHERE lci.catscaleid $insql3 AND lci.status <> :inactive AND qvl.status = :ready
                AND (lcip.contextid = :ctx OR lcip.contextid IS NULL)",
            $params3
        );
        $oldpool = [];
        foreach ($oldrows as $row) {
            $qid = (int) $row->componentid;
            if (!isset($oldpool[$qid]) || (int) $row->id < (int) $oldpool[$qid]->id) {
                $oldpool[$qid] = $row;
            }
        }
        $oldrows->close();
        $oldpool = model_strategy::rows_of_installed_models($oldpool);
        $before = ['questions' => count($oldpool), 'with parameters' => 0, 'without parameters (as pilots)' => 0,
            'rows of an item of another CAT context' => 0, 'by context of the row' => []];
        foreach ($oldpool as $row) {
            $before[empty($row->model) ? 'without parameters (as pilots)' : 'with parameters']++;
            if ((int) $row->contextid !== $contextid) {
                $before['rows of an item of another CAT context']++;
            }
            $key = (int) $row->contextid;
            $before['by context of the row'][$key] = ($before['by context of the row'][$key] ?? 0) + 1;
        }
        ksort($before['by context of the row']);

        [$insql2, $params2] = $DB->get_in_or_equal($scaleids, SQL_PARAMS_NAMED, 'tin');
        $nocontext = $DB->count_records_select('local_catquiz_items', "catscaleid $insql2 AND contextid IS NULL", $params2);
        $othercontexts = $DB->get_records_sql_menu(
            "SELECT contextid, COUNT(1) FROM {local_catquiz_items}
              WHERE catscaleid $insql2 AND contextid IS NOT NULL AND contextid <> :ctx GROUP BY contextid",
            $params2 + ['ctx' => $contextid]
        );
        return [
            'items of the scales in other CAT contexts, by context' => $othercontexts,
            'items of the scales without a CAT context' => $nocontext,
            'stages' => $stages,
            'by_model_after_the_conditions' => $bymodel,
            'references that do not resolve (issue #157, left out of the pool)' =>
                \local_catquiz\local\integrity\item_param_integrity::check($contextid, $scaleids),
            'pool before 28.10.2026 (items of every context, lowest item id)' => $before,
            'pool' => [
                'questions in the pool (one row per question, cold: without the cache)' => count($pool),
                '... rows with parameters' => $withparams,
                '... rows of an item of another CAT context' => $othercontext,
                '... questions with parameters in this context, but a row without' => $calibratedasother,
                'cache' => $cached === false ? 'no entry' : [
                    'questions' => count($cached),
                    'same as cold' => $signature($cached) === $signature($pool),
                ],
            ],
            'information' => [
                'items whose parameters can be read (model_item_param)' => $contract,
                'items with a finite information at every step' => $finiteatall,
                'items with a finite information, by step' => $finiteatstep,
            ],
        ];
    }
}
