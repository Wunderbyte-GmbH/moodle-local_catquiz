<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin upgrade helper functions are defined here.
 *
 * @package     local_catquiz
 * @category    upgrade
 * @copyright   2022 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Helper function used by the upgrade.php file.
 */
function local_catquiz_helper_function() {
    global $DB;

    // Please note: you can only use raw low level database access here.
    // Avoid Moodle API calls in upgrade steps.
    //
    // For more information please read {@link https://docs.moodle.org/dev/Upgrade_API}.
}

/**
 * Re-keys local_catquiz_progress.attemptid from the attempt of the component to the CAT attempt.
 *
 * Issue #95: the column held adaptivequiz_attempt.id although the schema declared a foreign key to
 * local_catquiz_attempts.id. This moves every row to the internal id.
 *
 * Kept out of upgrade.php so it can be tested against existing data - PHPUnit installs the schema
 * fresh from install.xml, so an upgrade step written inline never meets a real row. That is how
 * the first version of this step went out broken: it only ever ran on an empty table.
 *
 * Portable on purpose. MySQL and MariaDB refuse a DELETE whose subquery reads the same table, and a
 * unique key on attemptid makes every intermediate duplicate fatal. So the key is dropped first,
 * the new values are worked out in PHP, rows that cannot be assigned get NULL - which a unique key
 * tolerates any number of times, unlike 0 - and duplicates are removed by id.
 *
 * @return array{rekeyed: int, started: int, orphans: int, duplicates: int} What was done, for the upgrade log.
 */
function local_catquiz_rekey_progress_attempts(): array {
    global $DB;

    $dbman = $DB->get_manager();
    $table = new xmldb_table('local_catquiz_progress');

    // Any unique constraint on attemptid would reject the intermediate states below.
    foreach (
        [
        new xmldb_key('attemptid', XMLDB_KEY_FOREIGN_UNIQUE, ['attemptid'], 'local_catquiz_attempts', ['id']),
        new xmldb_key('attemptid', XMLDB_KEY_UNIQUE, ['attemptid']),
        ] as $key
    ) {
        try {
            $dbman->drop_key($table, $key);
        } catch (\ddl_exception $e) {
            // The key was not there in this shape; nothing to drop.
            unset($e);
        }
    }
    foreach (
        [
        new xmldb_index('componentattempt', XMLDB_INDEX_UNIQUE, ['component', 'attemptid']),
        new xmldb_index('attemptid', XMLDB_INDEX_UNIQUE, ['attemptid']),
        ] as $index
    ) {
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }
    }

    // Work out the internal id for every row: the pair (component, attemptid) is what the runtime
    // used, and local_catquiz_attempts holds exactly that pair next to its own id.
    $rows = $DB->get_records('local_catquiz_progress', null, 'id ASC', 'id, attemptid, component, userid');
    $catids = [];
    // Issue #95: a CAT attempt of another person under the same pair is a conflict, not a match.
    $mismatchedrows = [];
    foreach ($rows as $row) {
        /* local_catquiz_attempts stores the component in two spellings - 'adaptivequiz' from the
           result page, 'mod_adaptivequiz' elsewhere - while progress always says 'mod_adaptivequiz'.
           Comparing with one spelling found no CAT attempt at all on a real installation; every
           running test would then have been given a second one under the same number and the
           unique index on attemptid would have stopped the upgrade. Both spellings count. */
        $component = (string) $row->component;
        $names = strpos($component, 'mod_') === 0
            ? [$component, substr($component, strlen('mod_'))]
            : [$component, 'mod_' . $component];
        [$insql, $inparams] = $DB->get_in_or_equal($names, SQL_PARAMS_NAMED, 'comp');
        $catattempt = $DB->get_record_sql(
            "SELECT id, userid FROM {local_catquiz_attempts}
              WHERE id = (SELECT MAX(id) FROM {local_catquiz_attempts} WHERE attemptid = :attemptid AND component $insql)",
            ['attemptid' => (int) $row->attemptid] + $inparams
        );
        if ($catattempt && (int) $catattempt->userid !== (int) $row->userid) {
            $mismatchedrows[(int) $row->id] = true;
            $catattempt = false;
        }
        $catids[(int) $row->id] = $catattempt ? (int) $catattempt->id : null;
    }

    // Duplicates: two rows that end up on the same CAT attempt. The newest row wins - a second
    // row can only have come from the ambiguity this step removes.
    $keep = [];
    $duplicates = [];
    foreach ($catids as $rowid => $catid) {
        if ($catid === null) {
            continue;
        }
        if (isset($keep[$catid])) {
            $duplicates[] = min($keep[$catid], $rowid);
            $keep[$catid] = max($keep[$catid], $rowid);
        } else {
            $keep[$catid] = $rowid;
        }
    }
    if ($duplicates) {
        $DB->delete_records_list('local_catquiz_progress', 'id', $duplicates);
    }

    $rekeyed = 0;
    $orphans = 0;
    $started = 0;
    $mismatched = 0;
    foreach ($catids as $rowid => $catid) {
        if (in_array($rowid, $duplicates, true)) {
            continue;
        }
        if ($catid === null && isset($mismatchedrows[$rowid])) {
            // Not this person's CAT attempt: left unassigned, nothing deleted, counted for the log.
            $DB->set_field('local_catquiz_progress', 'attemptid', null, ['id' => $rowid]);
            $mismatched++;
            continue;
        }
        if ($catid === null) {
            /* A progress row without a CAT attempt is, at upgrade time, a test that is running: the
               CAT attempt used to be written only when the result page was shown. Since issue #101
               it exists from the start, so the running test gets one now - provided its attempt
               still exists. Only a row whose attempt is gone stays unassigned; NULL never matches
               at runtime, and nothing is deleted. */
            $row = $rows[$rowid];
            $attemptexists = $row->component === 'mod_adaptivequiz'
                && $dbman->table_exists(new xmldb_table('adaptivequiz_attempt'))
                && $DB->record_exists('adaptivequiz_attempt', ['id' => (int) $row->attemptid]);
            // Issue #95: the attempt must be the same person's; otherwise the row is a conflict.
            $sameperson = ['id' => (int) $row->attemptid, 'userid' => (int) $row->userid];
            if ($attemptexists && !$DB->record_exists('adaptivequiz_attempt', $sameperson)) {
                $DB->set_field('local_catquiz_progress', 'attemptid', null, ['id' => $rowid]);
                $mismatched++;
                continue;
            }
            // The number may be taken by a CAT attempt of a truly different component (issue #5
            // makes attemptid unique on its own); then no second one can be filed.
            $attemptexists = $attemptexists
                && !$DB->record_exists('local_catquiz_attempts', ['attemptid' => (int) $row->attemptid]);
            if ($attemptexists) {
                $now = time();
                $catid = (int) $DB->insert_record('local_catquiz_attempts', (object) [
                    'userid' => (int) $DB->get_field('local_catquiz_progress', 'userid', ['id' => $rowid]),
                    'attemptid' => (int) $row->attemptid,
                    'component' => $row->component,
                    'status' => 2, // LOCAL_CATQUIZ_ATTEMPT_RUNNING; lib.php is not loaded during upgrade.
                    'json' => '{}',
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]);
                $DB->set_field('local_catquiz_progress', 'attemptid', $catid, ['id' => $rowid]);
                $started++;
                continue;
            }
            $DB->set_field('local_catquiz_progress', 'attemptid', null, ['id' => $rowid]);
            $orphans++;
        } else {
            $DB->set_field('local_catquiz_progress', 'attemptid', $catid, ['id' => $rowid]);
            $rekeyed++;
        }
    }

    $dbman->add_key(
        $table,
        new xmldb_key('attemptid', XMLDB_KEY_FOREIGN_UNIQUE, ['attemptid'], 'local_catquiz_attempts', ['id'])
    );

    return [
        'rekeyed' => $rekeyed,
        'started' => $started,
        'orphans' => $orphans,
        'duplicates' => count($duplicates),
        'mismatched' => $mismatched,
    ];
}

/**
 * Merges local_catquiz_attemptscale into local_catquiz_personparams and removes the scale table.
 *
 * The person table becomes append-only: one row per estimate, the newest valid one being current.
 * Existing person rows count as valid, marked resultsource 'legacy' so an assumed validity stays
 * distinguishable from a checked one.
 *
 * Kept out of upgrade.php so it can be tested against existing data, and portable across database
 * engines. The reference to the CAT attempt is called catattemptid on the 1.2 line and attemptid
 * where it was renamed first; whichever exists is read.
 *
 * @return array{legacy: int, carried: int} Rows marked as legacy, rows carried over from the scale table.
 */
function local_catquiz_merge_attemptscale_into_personparams(): array {
    global $DB;

    $dbman = $DB->get_manager();
    $table = new xmldb_table('local_catquiz_personparams');

    foreach (
        [
        new xmldb_field('n', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'status'),
        new xmldb_field('fraction', XMLDB_TYPE_NUMBER, '10, 4', null, null, null, null, 'n'),
        new xmldb_field('isprimary', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'fraction'),
        new xmldb_field('isvalid', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'isprimary'),
        new xmldb_field('resultsource', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'isvalid'),
        new xmldb_field('validationstatus', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'resultsource'),
        ] as $field
    ) {
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
    }

    $legacy = $DB->count_records_select('local_catquiz_personparams', 'resultsource IS NULL');
    $DB->execute(
        "UPDATE {local_catquiz_personparams} SET isvalid = 1, resultsource = 'legacy' WHERE resultsource IS NULL"
    );

    // The one-row-per-person rule of issue #25 gives way to the append-only history.
    $oldindex = new xmldb_index('userid_contextid_catscaleid', XMLDB_INDEX_UNIQUE, ['userid', 'contextid', 'catscaleid']);
    if ($dbman->index_exists($table, $oldindex)) {
        $dbman->drop_index($table, $oldindex);
    }
    foreach (
        [
        new xmldb_index('userid_contextid_catscaleid', XMLDB_INDEX_NOTUNIQUE, ['userid', 'contextid', 'catscaleid']),
        new xmldb_index('isvalid', XMLDB_INDEX_NOTUNIQUE, ['isvalid']),
        new xmldb_index('attemptid_catscaleid', XMLDB_INDEX_NOTUNIQUE, ['attemptid', 'catscaleid']),
        ] as $index
    ) {
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
    }

    $carried = 0;
    $scaletable = new xmldb_table('local_catquiz_attemptscale');
    if ($dbman->table_exists($scaletable)) {
        $reference = $dbman->field_exists($scaletable, new xmldb_field('catattemptid')) ? 'catattemptid' : 'attemptid';

        // Row by row rather than INSERT ... SELECT: the insert goes through Moodle's DML layer and
        // behaves the same on every engine, and the scale table is small.
        $rows = $DB->get_recordset('local_catquiz_attemptscale', null, 'id ASC');
        foreach ($rows as $row) {
            $DB->insert_record('local_catquiz_personparams', (object) [
                'userid' => $row->userid,
                'catscaleid' => $row->catscaleid,
                'contextid' => $row->contextid,
                'attemptid' => $row->{$reference},
                'ability' => $row->score,
                'standarderror' => $row->standarderror,
                'n' => $row->n,
                'fraction' => $row->fraction,
                'isprimary' => (int) $row->isprimary,
                'isvalid' => (int) $row->isvalid,
                'resultsource' => $row->resultsource ?? 'current',
                'validationstatus' => $row->validationstatus,
                'timecreated' => $row->timecreated,
                'timemodified' => $row->timecreated,
            ]);
            $carried++;
        }
        $rows->close();

        $dbman->drop_table($scaletable);
    }

    return ['legacy' => $legacy, 'carried' => $carried];
}

/**
 * Gives every item parameter without an item the item of its question (upgrade step 2024080200).
 *
 * Before 2024080200 a parameter belonged to a question, not to an item. The step that introduced the
 * itemid read the pairs with get_records_sql(), keyed by the parameter: a question in several scales
 * delivered one row per item, all under the same key, and whichever the database returned last won
 * (issue #104, K6). Now the rule is named: the question's lowest item - the same item the parameter
 * list reads for a question (H2). Step 2024081300 then moves parameters to the items of their context.
 *
 * Only rows still without an item (itemid 0) are touched, so the function can run again.
 *
 * @return int Number of parameters that got an item.
 */
function local_catquiz_upgrade_assign_itemparam_items(): int {
    global $DB;

    $sql = "SELECT lcip.id AS itemparamid, MIN(lci.id) AS itemid
              FROM {local_catquiz_itemparams} lcip
              JOIN {local_catquiz_items} lci ON lci.componentid = lcip.componentid
                   AND lci.componentname = lcip.componentname
             WHERE lcip.itemid = 0
          GROUP BY lcip.id";
    $updated = 0;
    // One row per parameter (GROUP BY), so the key is unique.
    foreach ($DB->get_records_sql($sql) as $row) {
        $DB->set_field('local_catquiz_itemparams', 'itemid', (int) $row->itemid, ['id' => (int) $row->itemparamid]);
        $updated++;
    }
    return $updated;
}
