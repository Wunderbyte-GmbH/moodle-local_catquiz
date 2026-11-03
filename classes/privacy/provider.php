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

namespace local_catquiz\privacy;

use cache_helper;
use context;
use context_module;
use context_system;
use context_user;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use stdClass;

/**
 * Privacy provider of local_catquiz (issue #17).
 *
 * Where the data lives:
 * - The attempts of a test, their scale results (personparams with an attempt), their working state
 *   (progress) and their trace belong to the activity the test was taken in: the module context. The
 *   activity is found through the attempt of the activity (adaptivequiz_attempt), else through the
 *   instance the CAT attempt names - a running attempt has no course yet.
 * - Ability estimates without an attempt (stored while an attempt runs, or by a recalculation),
 *   subscriptions, and attempts whose activity is gone belong to the person: the user context.
 * - Who last changed a CAT context or a subscription, and who fetched item parameters from the central
 *   hub, is recorded in the system context. These records are not the person's own data; deleting
 *   anonymises the field and keeps the record.
 *
 * Deleting removes the rows - setting the user to 0 would merge every deleted person into one in the
 * person weighted statistics, and the attempt would still lead back to the person.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider
{
    /** @var string[] local_catquiz_attempts.component is written in both spellings. */
    private const COMPONENTS = ['mod_adaptivequiz', 'adaptivequiz'];

    /**
     * The personal data local_catquiz stores and sends.
     *
     * @param collection $items
     * @return collection
     */
    public static function get_metadata(collection $items): collection {
        $tables = [
            'local_catquiz_attempts' => ['userid', 'scaleid', 'contextid', 'courseid', 'attemptid', 'component', 'instanceid',
                'teststrategy', 'status', 'total_number_of_testitems', 'number_of_testitems_used',
                'personability_before_attempt', 'personability_after_attempt', 'starttime', 'endtime', 'json', 'debug_info',
                'timecreated', 'timemodified'],
            'local_catquiz_personparams' => ['userid', 'catscaleid', 'contextid', 'attemptid', 'ability', 'standarderror',
                'status', 'n', 'fraction', 'isprimary', 'isvalid', 'resultsource', 'validationstatus', 'rangelower', 'rangeupper',
                'rangebackfilled', 'timecreated', 'timemodified'],
            'local_catquiz_progress' => ['userid', 'component', 'attemptid', 'json', 'quizsettings', 'timemodified'],
            'local_catquiz_trace' => ['adaptiveattemptid', 'userid', 'kind', 'questionnumber', 'requestms', 'dbqueries', 'spans',
                'timecreated'],
            'local_catquiz_subscriptions' => ['userid', 'itemid', 'area', 'status', 'usermodified', 'timecreated',
                'timemodified'],
            'local_catquiz_catcontext' => ['usermodified', 'timemodified'],
            'local_catquiz_sync_event' => ['userid', 'contextid', 'catscaleid', 'num_fetched_params'],
            'local_catquiz_rresponses' => ['attempthash', 'response', 'sourceurl', 'timecreated'],
        ];
        foreach ($tables as $table => $fields) {
            $described = [];
            foreach ($fields as $field) {
                $described[$field] = 'privacy:metadata:' . $table . ':' . $field;
            }
            $items->add_database_table($table, $described, 'privacy:metadata:' . $table);
        }
        // The central hub client sends answers of attempts, pseudonymised, to another site.
        $items->add_external_location_link('catquizcentralhub', [
            'attempthash' => 'privacy:metadata:catquizcentralhub:attempthash',
            'questionhash' => 'privacy:metadata:catquizcentralhub:questionhash',
            'ability' => 'privacy:metadata:catquizcentralhub:ability',
            'fraction' => 'privacy:metadata:catquizcentralhub:fraction',
        ], 'privacy:metadata:catquizcentralhub');
        return $items;
    }

    /**
     * The SQL of the CAT attempts and the activity context they belong to.
     *
     * Two ways lead to the activity: the attempt of the activity, and the instance the CAT attempt names.
     *
     * @return array [sql selecting catattemptid, userid, contextid; params]
     */
    private static function attempt_context_sql(): array {
        global $DB;
        [$comp1, $p1] = $DB->get_in_or_equal(self::COMPONENTS, SQL_PARAMS_NAMED, 'pcompa');
        [$comp2, $p2] = $DB->get_in_or_equal(self::COMPONENTS, SQL_PARAMS_NAMED, 'pcompb');
        $sql = "SELECT ca.id AS catattemptid, ca.userid, ctx.id AS contextid
                  FROM {local_catquiz_attempts} ca
                  JOIN {adaptivequiz_attempt} aa ON aa.id = ca.attemptid
                  JOIN {course_modules} cm ON cm.instance = aa.instance
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'adaptivequiz'
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :pmodlevela
                 WHERE ca.component $comp1
                 UNION
                SELECT ca.id AS catattemptid, ca.userid, ctx.id AS contextid
                  FROM {local_catquiz_attempts} ca
                  JOIN {course_modules} cm ON cm.instance = ca.instanceid
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'adaptivequiz'
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :pmodlevelb
                 WHERE ca.component $comp2
                   AND NOT EXISTS (SELECT 1 FROM {adaptivequiz_attempt} aa2 WHERE aa2.id = ca.attemptid)";
        return [$sql, $p1 + $p2 + ['pmodlevela' => CONTEXT_MODULE, 'pmodlevelb' => CONTEXT_MODULE]];
    }

    /**
     * The SQL of the trace rows and the activity context they belong to.
     *
     * @return array [sql selecting traceid, userid, contextid; params]
     */
    private static function trace_context_sql(): array {
        $sql = "SELECT tr.id AS traceid, tr.userid, ctx.id AS contextid
                  FROM {local_catquiz_trace} tr
                  JOIN {adaptivequiz_attempt} aa ON aa.id = tr.adaptiveattemptid
                  JOIN {course_modules} cm ON cm.instance = aa.instance
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'adaptivequiz'
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :ptracemodlevel";
        return [$sql, ['ptracemodlevel' => CONTEXT_MODULE]];
    }

    /**
     * The CAT attempts of a person without an activity left: the activity and its attempt are gone.
     *
     * @param int $userid
     * @return int[]
     */
    private static function orphan_attempt_ids(int $userid): array {
        global $DB;
        [$sql, $params] = self::attempt_context_sql();
        return array_map('intval', $DB->get_fieldset_sql(
            "SELECT ca.id
               FROM {local_catquiz_attempts} ca
              WHERE ca.userid = :ouserid
                AND ca.id NOT IN (SELECT ac.catattemptid FROM ($sql) ac)",
            $params + ['ouserid' => $userid]
        ));
    }

    /**
     * Whether the person has data of their own outside any activity.
     *
     * @param int $userid
     * @return bool
     */
    private static function has_user_context_data(int $userid): bool {
        global $DB;
        $params = ['userid' => $userid];
        return $DB->record_exists_select('local_catquiz_personparams', 'userid = :userid AND attemptid IS NULL', $params)
            || $DB->record_exists('local_catquiz_subscriptions', ['userid' => $userid])
            || !empty(self::orphan_attempt_ids($userid));
    }

    /**
     * Whether the person is named as the one who changed or fetched something.
     *
     * @param int $userid
     * @return bool
     */
    private static function has_actor_data(int $userid): bool {
        global $DB;
        return $DB->record_exists('local_catquiz_catcontext', ['usermodified' => $userid])
            || $DB->record_exists('local_catquiz_subscriptions', ['usermodified' => $userid])
            || $DB->record_exists('local_catquiz_sync_event', ['userid' => $userid]);
    }

    /**
     * The contexts with data of a person.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        [$sql, $params] = self::attempt_context_sql();
        $params['cuserid'] = $userid;
        $contextlist->add_from_sql("SELECT ac.contextid FROM ($sql) ac WHERE ac.userid = :cuserid", $params);
        [$sql, $params] = self::trace_context_sql();
        $params['tuserid'] = $userid;
        $contextlist->add_from_sql("SELECT tc.contextid FROM ($sql) tc WHERE tc.userid = :tuserid", $params);
        if (self::has_user_context_data($userid)) {
            $contextlist->add_user_context($userid);
        }
        if (self::has_actor_data($userid)) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * The people with data in a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context instanceof context_module) {
            [$sql, $params] = self::attempt_context_sql();
            $userlist->add_from_sql(
                'userid',
                "SELECT ac.userid FROM ($sql) ac WHERE ac.contextid = :uctx",
                $params + ['uctx' => $context->id]
            );
            [$sql, $params] = self::trace_context_sql();
            $userlist->add_from_sql(
                'userid',
                "SELECT tc.userid FROM ($sql) tc WHERE tc.contextid = :tctx",
                $params + ['tctx' => $context->id]
            );
        } else if ($context instanceof context_user) {
            if (self::has_user_context_data((int) $context->instanceid)) {
                $userlist->add_user((int) $context->instanceid);
            }
        } else if ($context instanceof context_system) {
            foreach (['local_catquiz_catcontext', 'local_catquiz_subscriptions'] as $table) {
                $userlist->add_from_sql('usermodified', "SELECT usermodified FROM {{$table}} WHERE usermodified > 0", []);
            }
            $userlist->add_from_sql('userid', 'SELECT userid FROM {local_catquiz_sync_event} WHERE userid > 0', []);
        }
    }

    /**
     * The CAT attempts in an activity context, optionally of some people only.
     *
     * @param context_module $context
     * @param int[]|null $userids
     * @return int[]
     */
    private static function attempts_in_context(context_module $context, ?array $userids = null): array {
        global $DB;
        [$sql, $params] = self::attempt_context_sql();
        $where = 'ac.contextid = :actx';
        $params['actx'] = $context->id;
        if ($userids !== null) {
            if (!$userids) {
                return [];
            }
            [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'auser');
            $where .= " AND ac.userid $insql";
            $params += $inparams;
        }
        return array_map('intval', $DB->get_fieldset_sql("SELECT ac.catattemptid FROM ($sql) ac WHERE $where", $params));
    }

    /**
     * The trace rows in an activity context, optionally of some people only.
     *
     * @param context_module $context
     * @param int[]|null $userids
     * @return int[]
     */
    private static function traces_in_context(context_module $context, ?array $userids = null): array {
        global $DB;
        [$sql, $params] = self::trace_context_sql();
        $where = 'tc.contextid = :tctx';
        $params['tctx'] = $context->id;
        if ($userids !== null) {
            if (!$userids) {
                return [];
            }
            [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'tuser');
            $where .= " AND tc.userid $insql";
            $params += $inparams;
        }
        return array_map('intval', $DB->get_fieldset_sql("SELECT tc.traceid FROM ($sql) tc WHERE $where", $params));
    }

    /**
     * Exports the data of a person in the approved contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = (int) $contextlist->get_user()->id;
        $plugin = get_string('pluginname', 'local_catquiz');

        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_module) {
                $attemptids = self::attempts_in_context($context, [$userid]);
                foreach ($attemptids as $catattemptid) {
                    $attempt = $DB->get_record('local_catquiz_attempts', ['id' => $catattemptid]);
                    writer::with_context($context)->export_data(
                        [$plugin, get_string('privacy:attempt', 'local_catquiz', $catattemptid)],
                        self::attempt_export($attempt)
                    );
                }
                $traceids = self::traces_in_context($context, [$userid]);
                if ($traceids) {
                    [$insql, $inparams] = $DB->get_in_or_equal($traceids, SQL_PARAMS_NAMED);
                    $rows = $DB->get_records_select(
                        'local_catquiz_trace',
                        "id $insql",
                        $inparams,
                        'id',
                        'id, adaptiveattemptid, kind, questionnumber, requestms, dbqueries, timecreated'
                    );
                    writer::with_context($context)->export_data(
                        [$plugin, get_string('privacy:trace', 'local_catquiz')],
                        (object) ['requests' => array_values(array_map(
                            fn($r) => (object) [
                            'attempt' => $r->adaptiveattemptid, 'kind' => $r->kind, 'questionnumber' => $r->questionnumber,
                            'milliseconds' => $r->requestms, 'queries' => $r->dbqueries,
                            'time' => transform::datetime($r->timecreated),
                            ],
                            $rows
                        ))]
                    );
                }
            } else if ($context instanceof context_user && (int) $context->instanceid === $userid) {
                self::export_user_context($context, $userid, $plugin);
            } else if ($context instanceof context_system) {
                self::export_actor_data($context, $userid, $plugin);
            }
        }
    }

    /**
     * What an attempt holds: the attempt, its scale results, its working state.
     *
     * @param stdClass $attempt local_catquiz_attempts record
     * @return stdClass
     */
    private static function attempt_export(stdClass $attempt): stdClass {
        global $DB;
        $scales = $DB->get_records('local_catquiz_personparams', ['attemptid' => $attempt->id], 'id');
        $progress = $DB->get_record('local_catquiz_progress', ['attemptid' => $attempt->id]);
        return (object) [
            'attemptofactivity' => $attempt->attemptid,
            'teststrategy' => $attempt->teststrategy,
            'status' => $attempt->status,
            'questionsavailable' => $attempt->total_number_of_testitems,
            'questionsused' => $attempt->number_of_testitems_used,
            'abilitybefore' => $attempt->personability_before_attempt,
            'abilityafter' => $attempt->personability_after_attempt,
            'starttime' => $attempt->starttime ? transform::datetime($attempt->starttime) : null,
            'endtime' => $attempt->endtime ? transform::datetime($attempt->endtime) : null,
            'result' => json_decode((string) $attempt->json),
            'debuginfo' => json_decode((string) $attempt->debug_info),
            'timecreated' => transform::datetime($attempt->timecreated),
            'scales' => array_values(array_map(fn($s) => self::ability_export($s), $scales)),
            'workingstate' => $progress ? json_decode((string) $progress->json) : null,
        ];
    }

    /**
     * One ability estimate.
     *
     * @param stdClass $row local_catquiz_personparams record
     * @return stdClass
     */
    private static function ability_export(stdClass $row): stdClass {
        return (object) [
            'catscaleid' => $row->catscaleid,
            'contextid' => $row->contextid,
            'ability' => $row->ability,
            'standarderror' => $row->standarderror,
            'questions' => $row->n,
            'fraction' => $row->fraction,
            'isprimary' => transform::yesno((bool) $row->isprimary),
            'isvalid' => transform::yesno((bool) $row->isvalid),
            'timecreated' => transform::datetime($row->timecreated),
        ];
    }

    /**
     * The person's data outside any activity.
     *
     * @param context_user $context
     * @param int $userid
     * @param string $plugin
     */
    private static function export_user_context(context_user $context, int $userid, string $plugin): void {
        global $DB;
        $abilities = $DB->get_records_select(
            'local_catquiz_personparams',
            'userid = :userid AND attemptid IS NULL',
            ['userid' => $userid],
            'id'
        );
        if ($abilities) {
            writer::with_context($context)->export_data(
                [$plugin, get_string('privacy:abilities', 'local_catquiz')],
                (object) ['abilities' => array_values(array_map(fn($s) => self::ability_export($s), $abilities))]
            );
        }
        $subscriptions = $DB->get_records('local_catquiz_subscriptions', ['userid' => $userid], 'id');
        if ($subscriptions) {
            writer::with_context($context)->export_data(
                [$plugin, get_string('privacy:subscriptions', 'local_catquiz')],
                (object) ['subscriptions' => array_values(array_map(
                    fn($s) => (object) [
                    'area' => $s->area, 'itemid' => $s->itemid, 'status' => $s->status,
                    'timecreated' => transform::datetime($s->timecreated),
                    ],
                    $subscriptions
                ))]
            );
        }
        foreach (self::orphan_attempt_ids($userid) as $catattemptid) {
            $attempt = $DB->get_record('local_catquiz_attempts', ['id' => $catattemptid]);
            writer::with_context($context)->export_data(
                [$plugin, get_string('privacy:attempt', 'local_catquiz', $catattemptid)],
                self::attempt_export($attempt)
            );
        }
    }

    /**
     * What the person changed or fetched.
     *
     * @param context_system $context
     * @param int $userid
     * @param string $plugin
     */
    private static function export_actor_data(context_system $context, int $userid, string $plugin): void {
        global $DB;
        $data = (object) [
            'catcontexts' => array_values(array_map(fn($c) => (object) [
                'name' => $c->name, 'timemodified' => transform::datetime($c->timemodified),
            ], $DB->get_records('local_catquiz_catcontext', ['usermodified' => $userid], 'id', 'id, name, timemodified'))),
            'subscriptionschanged' => $DB->count_records('local_catquiz_subscriptions', ['usermodified' => $userid]),
            'parameterfetches' => array_values(array_map(fn($e) => (object) [
                'catscaleid' => $e->catscaleid, 'contextid' => $e->contextid, 'parameters' => $e->num_fetched_params,
            ], $DB->get_records('local_catquiz_sync_event', ['userid' => $userid], 'id'))),
        ];
        if ($data->catcontexts || $data->subscriptionschanged || $data->parameterfetches) {
            writer::with_context($context)->export_data([$plugin, get_string('privacy:changes', 'local_catquiz')], $data);
        }
    }

    /**
     * Deletes the data of everybody in a context.
     *
     * @param context $context
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;
        if ($context instanceof context_module) {
            self::delete_attempts(self::attempts_in_context($context));
            self::delete_traces(self::traces_in_context($context));
        } else if ($context instanceof context_user) {
            self::delete_user_context_data((int) $context->instanceid);
        } else if ($context instanceof context_system) {
            $DB->set_field_select('local_catquiz_catcontext', 'usermodified', 0, 'usermodified > 0');
            $DB->set_field_select('local_catquiz_subscriptions', 'usermodified', 0, 'usermodified > 0');
            $DB->set_field_select('local_catquiz_sync_event', 'userid', 0, 'userid > 0');
        }
        self::purge_caches();
    }

    /**
     * Deletes the data of a person in the approved contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            self::delete_for_users_in($context, [$userid]);
        }
        self::purge_caches();
    }

    /**
     * Deletes the data of some people in a context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        self::delete_for_users_in($userlist->get_context(), array_map('intval', $userlist->get_userids()));
        self::purge_caches();
    }

    /**
     * Deletes the data of some people in one context.
     *
     * @param context $context
     * @param int[] $userids
     */
    private static function delete_for_users_in(context $context, array $userids): void {
        global $DB;
        if (!$userids) {
            return;
        }
        if ($context instanceof context_module) {
            self::delete_attempts(self::attempts_in_context($context, $userids));
            self::delete_traces(self::traces_in_context($context, $userids));
        } else if ($context instanceof context_user) {
            if (in_array((int) $context->instanceid, $userids, true)) {
                self::delete_user_context_data((int) $context->instanceid);
            }
        } else if ($context instanceof context_system) {
            [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
            $DB->set_field_select('local_catquiz_catcontext', 'usermodified', 0, "usermodified $insql", $inparams);
            $DB->set_field_select('local_catquiz_subscriptions', 'usermodified', 0, "usermodified $insql", $inparams);
            $DB->set_field_select('local_catquiz_sync_event', 'userid', 0, "userid $insql", $inparams);
        }
    }

    /**
     * Deletes the person's data outside any activity.
     *
     * @param int $userid
     */
    private static function delete_user_context_data(int $userid): void {
        global $DB;
        self::delete_attempts(self::orphan_attempt_ids($userid));
        $DB->delete_records_select('local_catquiz_personparams', 'userid = :userid AND attemptid IS NULL', ['userid' => $userid]);
        $DB->delete_records('local_catquiz_subscriptions', ['userid' => $userid]);
    }

    /**
     * Deletes CAT attempts with their scale results and working state.
     *
     * @param int[] $catattemptids
     */
    private static function delete_attempts(array $catattemptids): void {
        global $DB;
        foreach (array_chunk($catattemptids, 500) as $chunk) {
            [$insql, $inparams] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED);
            $DB->delete_records_select('local_catquiz_personparams', "attemptid $insql", $inparams);
            $DB->delete_records_select('local_catquiz_progress', "attemptid $insql", $inparams);
            $DB->delete_records_select('local_catquiz_attempts', "id $insql", $inparams);
        }
    }

    /**
     * Deletes trace rows.
     *
     * @param int[] $traceids
     */
    private static function delete_traces(array $traceids): void {
        global $DB;
        foreach (array_chunk($traceids, 500) as $chunk) {
            [$insql, $inparams] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED);
            $DB->delete_records_select('local_catquiz_trace', "id $insql", $inparams);
        }
    }

    /**
     * Statistics and attempt lists cached from the deleted rows go as well.
     */
    private static function purge_caches(): void {
        cache_helper::purge_by_event('changesinquizattempts');
        cache_helper::purge_by_event('changesintestitems');
    }
}
