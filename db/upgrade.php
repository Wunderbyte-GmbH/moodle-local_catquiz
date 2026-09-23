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
 * Plugin upgrade steps are defined here.
 *
 * @package     local_catquiz
 * @category    upgrade
 * @copyright   2022 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/upgradelib.php');

/**
 * Execute local_catquiz upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_catquiz_upgrade($oldversion) {
    global $DB, $CFG;

    $dbman = $DB->get_manager();

    // For further information please read {@link https://docs.moodle.org/dev/Upgrade_API}.
    //
    // You will also have to create the db/install.xml file by using the XMLDB Editor.
    // Documentation for the XMLDB Editor can be found at {@link https://docs.moodle.org/dev/XMLDB_editor}.

    if ($oldversion < 2023012504) {
        // Define field min / max scalevalue to be added to local_catquiz_catscales.
        $table = new xmldb_table('local_catquiz_catscales');

        $field = new xmldb_field('minscalevalue', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, 0, null);
        // Conditionally launch add fields min scale value.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('maxscalevalue', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, 0, null);
        // Conditionally launch add fields max scale value.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Catquiz savepoint reached.
        upgrade_plugin_savepoint(true, 2023012504, 'local', 'catquiz');
    }

    if ($oldversion < 2023030700) {
        // Define table local_catquiz_tests to be created.
        $table = new xmldb_table('local_catquiz_tests');

        // Adding fields to table local_catquiz_tests.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('componentid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('component', XMLDB_TYPE_CHAR, '255', null, null, null, '');
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, null, null, '0');
        $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('descriptionformat', XMLDB_TYPE_INTEGER, '2', null, null, null, '1');
        $table->add_field('json', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('visible', XMLDB_TYPE_INTEGER, '2', null, null, null, '1');
        $table->add_field('availability', XMLDB_TYPE_CHAR, '255', null, null, null, '');
        $table->add_field('lang', XMLDB_TYPE_CHAR, '30', null, null, null, '');
        $table->add_field('status', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('parentid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table local_catquiz_tests.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for local_catquiz_tests.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Catquiz savepoint reached.
        upgrade_plugin_savepoint(true, 2023030700, 'local', 'catquiz');
    }

    if ($oldversion < 2023040100) {
        // Define table local_catquiz_personparams to be created.
        $table = new xmldb_table('local_catquiz_personparams');

        // Adding fields to table local_catquiz_personparams.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('contextid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('ability', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('status', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table local_catquiz_personparams.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for local_catquiz_personparams.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Catquiz savepoint reached.
        upgrade_plugin_savepoint(true, 2023040100, 'local', 'catquiz');
    }

    if ($oldversion < 2023040102) {
        // Define table local_catquiz_catcontext to be created.
        $table = new xmldb_table('local_catquiz_catcontext');

        // Adding fields to table local_catquiz_catcontext.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('descriptionformat', XMLDB_TYPE_INTEGER, '2', null, null, null, null);
        $table->add_field('starttimestamp', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('endtimestamp', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('json', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table local_catquiz_catcontext.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);

        // Conditionally launch create table for local_catquiz_catcontext.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        // Catquiz savepoint reached.
        upgrade_plugin_savepoint(true, 2023040102, 'local', 'catquiz');
    }

    if ($oldversion < 2023040701) {
        // Define field catscaleid to be added to local_catquiz_tests.
        $table = new xmldb_table('local_catquiz_tests');
        $field = new xmldb_field('catscaleid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'component');

        // Conditionally launch add field catscaleid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Catquiz savepoint reached.
        upgrade_plugin_savepoint(true, 2023040701, 'local', 'catquiz');
    }

    if ($oldversion < 2023041400) {
        // Define field model to be added to local_catquiz_personparams.
        $table = new xmldb_table('local_catquiz_personparams');
        $field = new xmldb_field('model', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'contextid');

        // Conditionally launch add field model.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Catquiz savepoint reached.
        upgrade_plugin_savepoint(true, 2023041400, 'local', 'catquiz');
    }

        // Add the itemparams table.
        $savepointadditemparams = 2023041703;
    if ($oldversion < $savepointadditemparams) {
        // Define table local_catquiz_itemparams to be created.
        $table = new xmldb_table('local_catquiz_itemparams');

        // Adding fields to table local_catquiz_itemparams.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('componentid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('componentname', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('contextid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('model', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('difficulty', XMLDB_TYPE_NUMBER, '10, 4', null, null, null, '0');
        $table->add_field('discrimination', XMLDB_TYPE_NUMBER, '10, 4', null, null, null, '0');

        // Adding keys to table local_catquiz_itemparams.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for local_catquiz_itemparams.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Catquiz savepoint reached.
        upgrade_plugin_savepoint(true, $savepointadditemparams, 'local', 'catquiz');
    }

        $savepointaddtimefields = 2023042001;
    if ($oldversion < $savepointaddtimefields) {
        // Add timecreated field
        // Define field timecreated to be added to local_catquiz_itemparams.
        $table = new xmldb_table('local_catquiz_itemparams');
        $field = new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'discrimination');

        // Conditionally launch add field timecreated.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add timemodified field
        // Define field timemodified to be added to local_catquiz_itemparams.
        $table = new xmldb_table('local_catquiz_itemparams');
        $field = new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timecreated');

        // Conditionally launch add field timemodified.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Catquiz savepoint reached.
        upgrade_plugin_savepoint(true, $savepointaddtimefields, 'local', 'catquiz');
    }

        $savepointupdateabilityprecision = 2023042101;
    if ($oldversion < $savepointupdateabilityprecision) {
        // Changing precision of field ability on table local_catquiz_personparams to (10, 4).
        $table = new xmldb_table('local_catquiz_personparams');
        $field = new xmldb_field('ability', XMLDB_TYPE_NUMBER, '10, 4', null, null, null, null, 'model');

        // Launch change of precision for field ability.
        $dbman->change_field_precision($table, $field);

        // Catquiz savepoint reached.
        upgrade_plugin_savepoint(true, $savepointupdateabilityprecision, 'local', 'catquiz');
    }

        $savepointaddcourseidfield = 2023050203;
    if ($oldversion < $savepointaddcourseidfield) {
        // Define field courseid to be added to local_catquiz_tests.
        $table = new xmldb_table('local_catquiz_tests');
        $field = new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'catscaleid');

        // Conditionally launch add field courseid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Catquiz savepoint reached.
        upgrade_plugin_savepoint(true, $savepointaddcourseidfield, 'local', 'catquiz');
    }

        $savepointremovepersonparamsmodel = 2023050501;
    if ($oldversion < $savepointremovepersonparamsmodel) {
        // Define field model to be dropped from local_catquiz_personparams.
        $table = new xmldb_table('local_catquiz_personparams');
        $field = new xmldb_field('model');

        // Conditionally launch drop field model.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Catquiz savepoint reached.
        upgrade_plugin_savepoint(true, $savepointremovepersonparamsmodel, 'local', 'catquiz');
    }

        $savepointadditemparamsstatusfield = 2023050503;
    if ($oldversion < $savepointadditemparamsstatusfield) {
        // Define field status to be added to local_catquiz_itemparams.
        $table = new xmldb_table('local_catquiz_itemparams');
        $field = new xmldb_field('status', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, 0, 'difficulty');

        // Conditionally launch add field status.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Catquiz savepoint reached.
        upgrade_plugin_savepoint(true, $savepointadditemparamsstatusfield, 'local', 'catquiz');
    }

        $savepointaddtimecalculatedfield = 2023060201;
    if ($oldversion < $savepointaddtimecalculatedfield) {
        // Define field timecalculated to be added to local_catquiz_catcontext.
        $table = new xmldb_table('local_catquiz_catcontext');
        $field = new xmldb_field('timecalculated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timemodified');

        // Conditionally launch add field timecalculated.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Catquiz savepoint reached.
        upgrade_plugin_savepoint(true, $savepointaddtimecalculatedfield, 'local', 'catquiz');
    }

    if ($oldversion < 2023072400) {
        // Define field catscaleid to be added to local_catquiz_personparams.
        $table = new xmldb_table('local_catquiz_personparams');
        $field = new xmldb_field('catscaleid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'status');

        // Conditionally launch add field catscaleid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Catquiz savepoint reached.
        upgrade_plugin_savepoint(true, 2023072400, 'local', 'catquiz');
    }

    if ($oldversion < 2023080300) {
        // Define field status to be added to local_catquiz_items.
        $table = new xmldb_table('local_catquiz_items');
        $field = new xmldb_field('status', XMLDB_TYPE_INTEGER, '2', null, null, null, '0', 'lastupdated');

        // Conditionally launch add field status.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Catquiz savepoint reached.
        upgrade_plugin_savepoint(true, 2023080300, 'local', 'catquiz');
    }

    if ($oldversion < 2023091900) {
        // Define table local_catquiz_attempts to be created.
        $table = new xmldb_table('local_catquiz_attempts');

        // Adding fields to table local_catquiz_attempts.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('scaleid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('contextid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('attemptid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('component', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('instanceid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('teststrategy', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('status', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('total_number_of_testitems', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('number_of_testitems_used', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('personability_before_attempt', XMLDB_TYPE_NUMBER, '10, 4', null, null, null, null);
        $table->add_field('personability_after_attempt', XMLDB_TYPE_NUMBER, '10, 4', null, null, null, null);
        $table->add_field('starttime', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('endtime', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('json', XMLDB_TYPE_TEXT, null, null, null, null, null);

        // Adding keys to table local_catquiz_attempts.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for local_catquiz_attempts.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Catquiz savepoint reached.
        upgrade_plugin_savepoint(true, 2023091900, 'local', 'catquiz');
    }

    if ($oldversion < 2023110600) {
        // Define field contextid to be added to local_catquiz_catscales.
        $table = new xmldb_table('local_catquiz_catscales');
        $field = new xmldb_field('contextid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'parentid');

        // Conditionally launch add field contextid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Catquiz savepoint reached.
        upgrade_plugin_savepoint(true, 2023110600, 'local', 'catquiz');
    }

    if ($oldversion < 2024021200) {
        // Define table local_catquiz_progress to be created.
        $table = new xmldb_table('local_catquiz_progress');

        // Adding fields to table local_catquiz_progress.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('component', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('attemptid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('json', XMLDB_TYPE_TEXT, null, null, null, null, null);

        // Adding keys to table local_catquiz_progress.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for local_catquiz_progress.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Catquiz savepoint reached.
        upgrade_plugin_savepoint(true, 2024021200, 'local', 'catquiz');
    }

    if ($oldversion < 2024021500) {
        // Changing the default of field minscalevalue on table local_catquiz_catscales to drop it.
        $table = new xmldb_table('local_catquiz_catscales');
        $field = new xmldb_field('minscalevalue', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null, 'description');
        $field = new xmldb_field('maxscalevalue', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null, 'description');

        // Launch change of default for field minscalevalue.
        $dbman->change_field_default($table, $field);

        // Catquiz savepoint reached.
        upgrade_plugin_savepoint(true, 2024021500, 'local', 'catquiz');
    }

    if ($oldversion < 2024030800) {
        // The subplugin names changed, so we need to change the value in the itemparams table.
        $updatednames = [
            'raschbirnbauma' => 'rasch',
            'raschbirnbaumb' => 'raschbirnbaum',
            'raschbirnbaumc' => 'mixedraschbirnbaum',
            'web_raschbirnbauam' => 'web_rasch',
        ];
        foreach ($updatednames as $oldmodel => $newmodel) {
            $itemparams = $DB->get_records('local_catquiz_itemparams', ['model' => $oldmodel]);
            foreach ($itemparams as $ip) {
                $ip->model = $newmodel;
                $DB->update_record('local_catquiz_itemparams', $ip, true);
            }
        }

        // Catquiz savepoint reached.
        upgrade_plugin_savepoint(true, 2024030800, 'local', 'catquiz');
    }

    if ($oldversion < 2024031200) {
        // Changing the default of field minscalevalue on table local_catquiz_catscales to drop it.
        $table = new xmldb_table('local_catquiz_catscales');
        $field = new xmldb_field('minscalevalue', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null, 'description');

        // Launch change of default for field minscalevalue.
        $dbman->change_field_default($table, $field);

        // Catquiz savepoint reached.
        upgrade_plugin_savepoint(true, 2024031200, 'local', 'catquiz');
    }

    if ($oldversion < 2024041500) {
        // Define field model to be dropped from local_catquiz_tests.
        $table = new xmldb_table('local_catquiz_tests');
        $fields = [];
        $fields[] = new xmldb_field('visible');
        $fields[] = new xmldb_field('availability');
        $fields[] = new xmldb_field('lang');

        foreach ($fields as $field) {
            // Conditionally launch drop field model.
            if ($dbman->field_exists($table, $field)) {
                $dbman->drop_field($table, $field);
            }
        }

        // Catquiz savepoint reached.
        upgrade_plugin_savepoint(true, 2024041500, 'local', 'catquiz');
    }

    if ($oldversion < 2024050200) {
        // Define field quizsettings to be added to local_catquiz_progress.
        $table = new xmldb_table('local_catquiz_progress');
        $field = new xmldb_field('quizsettings', XMLDB_TYPE_TEXT, null, null, null, null, null, 'json');

        // Conditionally launch add field quizsettings.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Catquiz savepoint reached.
        upgrade_plugin_savepoint(true, 2024050200, 'local', 'catquiz');
    }

    if ($oldversion < 2024051401) {
        // Define field debug_info to be added to local_catquiz_attempts.
        $table = new xmldb_table('local_catquiz_attempts');
        $field = new xmldb_field('debug_info', XMLDB_TYPE_TEXT, null, null, null, null, null, 'json');

        // Conditionally launch add field debug_info.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Catquiz savepoint reached.
        upgrade_plugin_savepoint(true, 2024051401, 'local', 'catquiz');
    }

    if ($oldversion < 2024080200) {
        // Define field itemid to be added to local_catquiz_itemparams.
        $table = new xmldb_table('local_catquiz_itemparams');

        $field = new xmldb_field('itemid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0, 0);

        // Conditionally launch add fields itemid value.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Make sure the database has updated the itemid in the catquiz_itemparams table.
        $sql = <<<SQL
            SELECT lcip.id itemparamid, lci.id itemid
              FROM {local_catquiz_itemparams} lcip
              JOIN {local_catquiz_items} lci ON lci.componentid = lcip.componentid
                  AND lci.componentname = lcip.componentname
        SQL;

        $sqlresult = $DB->get_records_sql($sql);

        foreach ($sqlresult as $row) {
            $updaterecord = new stdclass();
            $updaterecord->id = $row->itemparamid;
            $updaterecord->itemid = $row->itemid;
            $DB->update_record('local_catquiz_itemparams', $updaterecord);
        }

        // Catquiz savepoint reached.
        upgrade_plugin_savepoint(true, 2024080200, 'local', 'catquiz');
    }

    if ($oldversion < 2024080800) {
        $table = new xmldb_table('local_catquiz_items');
        $fields = [];
        $keys = [];
        $indexes = [];
        $fields[] = new xmldb_field('activeparamid', XMLDB_TYPE_INTEGER, '10');
        $fields[] = new xmldb_field('contextid', XMLDB_TYPE_INTEGER, '10');
        $keys[] = new xmldb_key('catscaleid', XMLDB_KEY_FOREIGN, ['catscaleid'], 'local_catquiz_catscales', explode(',', 'id'));
        $keys[] = new xmldb_key('contextid', XMLDB_KEY_FOREIGN, ['contextid'], 'local_catquiz_catcontext', explode(',', 'id'));
        $keys[] = new xmldb_key(
            'activeparamid',
            XMLDB_KEY_FOREIGN,
            ['activeparamid'],
            'local_catquiz_itemparams',
            explode(',', 'id')
        );
        $indexes[] = new xmldb_index('componentid', XMLDB_INDEX_NOTUNIQUE, explode(',', 'componentid'));
        $indexes[] = new xmldb_index('catscaleid', XMLDB_INDEX_NOTUNIQUE, explode(',', 'catscaleid'));
        $indexes[] = new xmldb_index('contextid', XMLDB_INDEX_NOTUNIQUE, explode(',', 'contextid'));
        $indexes[] = new xmldb_index('activeparamid', XMLDB_INDEX_NOTUNIQUE, explode(',', 'activeparamid'));
        $indexes[] = new xmldb_index('catscaleid', XMLDB_INDEX_NOTUNIQUE, ['catscaleid']);

        // Conditionally launch add fields, keys and indexes.
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        foreach ($keys as $key) {
            $dbman->add_key($table, $key);
        }
        foreach ($indexes as $index) {
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }

        // Make sure the database has updated the activeparamid and contextid in the catquiz_items table.
        $sql = <<<SQL
            WITH RECURSIVE globalscale (scaleid, globalid, contextid) AS (
              SELECT id, id, contextid
                FROM {local_catquiz_catscales}
                WHERE parentid=0
              UNION ALL
              SELECT ccs.id, gs.globalid, gs.contextid
                FROM globalscale gs
                INNER JOIN {local_catquiz_catscales} as ccs ON ccs.parentid = gs.scaleid
            )
            SELECT ROW_NUMBER() OVER (ORDER BY lci.id, gs.contextid), lci.id as itemid, gs.contextid as contextid
              FROM globalscale gs
              JOIN {local_catquiz_items} lci ON lci.catscaleid = gs.scaleid
              JOIN {local_catquiz_itemparams} lcip ON lcip.itemid = lci.id
              ORDER BY lci.id, lcip.contextid
        SQL;

        $sqlresult = $DB->get_records_sql($sql);

        foreach ($sqlresult as $row) {
            $itemid = $row->itemid;
            $contextid = $row->contextid;

            if (!$itemid) {
                continue;
            }

            $sql = "SELECT id
                FROM {local_catquiz_itemparams} lcip
                WHERE itemid = $itemid AND contextid = $contextid
                ORDER BY status DESC
                LIMIT 1";

            if ($lcip = $DB->get_record_sql($sql)) {
                $updaterecord = new stdclass();
                $updaterecord->id = $itemid;
                $updaterecord->contextid = $contextid;
                $updaterecord->activeparamid = $lcip->id;
                $DB->update_record('local_catquiz_items', $updaterecord);
            }
        }

        $table = new xmldb_table('local_catquiz_itemparams');
        $fields = [];
        $keys = [];
        $indexes = [];
        $fields[] = new xmldb_field('guessing', XMLDB_TYPE_NUMBER, '10,4');
        $fields[] = new xmldb_field('json', XMLDB_TYPE_TEXT, "medium");
        $keys[] = new xmldb_key('contextid', XMLDB_KEY_FOREIGN, ['contextid'], 'local_catquiz_catcontext', explode(',', 'id'));
        $indexes[] = new xmldb_index('uniqueitemparam', XMLDB_INDEX_NOTUNIQUE, explode(',', 'itemid,contextid,model'));
        $indexes[] = new xmldb_index('itemid', XMLDB_INDEX_NOTUNIQUE, explode(',', 'itemid'));
        $indexes[] = new xmldb_index('contextid', XMLDB_INDEX_NOTUNIQUE, explode(',', 'contextid'));

        // Conditionally launch add fields, keys and indexes.
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        foreach ($keys as $key) {
            $dbman->add_key($table, $key);
        }
        foreach ($indexes as $index) {
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }

        $table = new xmldb_table('local_catquiz_catscales');
        $fields = [];
        $keys = [];
        $indexes = [];
        $fields[] = new xmldb_field('label', XMLDB_TYPE_CHAR, '255');
        $keys[] = new xmldb_key('contextid', XMLDB_KEY_FOREIGN, ['contextid'], 'local_catquiz_catcontext', explode(',', 'id'));
        $indexes[] = new xmldb_index('parentid', XMLDB_INDEX_NOTUNIQUE, explode(',', 'parentid'));
        $indexes[] = new xmldb_index('contextid', XMLDB_INDEX_NOTUNIQUE, explode(',', 'contextid'));

        // Conditionally launch add fields, keys and indexes.
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        foreach ($keys as $key) {
            $dbman->add_key($table, $key);
        }
        foreach ($indexes as $index) {
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }

        $table = new xmldb_table('local_catquiz_catcontext');
        $fields = [];
        $keys = [];
        $indexes = [];
        $fields[] = new xmldb_field('parentid', XMLDB_TYPE_INTEGER, '10');
        $keys[] = new xmldb_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', explode(',', 'id'));
        $indexes[] = new xmldb_index('parentid', XMLDB_INDEX_NOTUNIQUE, explode(',', 'parentid'));

        // Conditionally launch add fields, keys and indexes.
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        foreach ($keys as $key) {
            $dbman->add_key($table, $key);
        }
        foreach ($indexes as $index) {
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }

        $table = new xmldb_table('local_catquiz_personparams');
        $fields = [];
        $keys = [];
        $indexes = [];
        $fields[] = new xmldb_field('attemptid', XMLDB_TYPE_INTEGER, '10');
        $fields[] = new xmldb_field('standarderror', XMLDB_TYPE_NUMBER, '10,4');
        $keys[] = new xmldb_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', explode(',', 'id'));
        $keys[] = new xmldb_key('catscaleid', XMLDB_KEY_FOREIGN, ['catscaleid'], 'local_catquiz_catscales', explode(',', 'id'));
        $keys[] = new xmldb_key('contextid', XMLDB_KEY_FOREIGN, ['contextid'], 'local_catquiz_catcontext', explode(',', 'id'));
        $keys[] = new xmldb_key('attemptid', XMLDB_KEY_FOREIGN, ['attemptid'], 'local_catquiz_attempts', explode(',', 'id'));
        $indexes[] = new xmldb_index('userid', XMLDB_INDEX_NOTUNIQUE, explode(',', 'userid'));
        $indexes[] = new xmldb_index('catscaleid', XMLDB_INDEX_NOTUNIQUE, explode(',', 'catscaleid'));
        $indexes[] = new xmldb_index('contextid', XMLDB_INDEX_NOTUNIQUE, explode(',', 'contextid'));
        $indexes[] = new xmldb_index('attemptid', XMLDB_INDEX_NOTUNIQUE, explode(',', 'attemptid'));

        // Conditionally launch add fields, keys and indexes.
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        foreach ($keys as $key) {
            $dbman->add_key($table, $key);
        }
        foreach ($indexes as $index) {
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }

        $table = new xmldb_table('local_catquiz_subscriptions');
        $keys = [];
        $indexes = [];
        $keys[] = new xmldb_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', explode(',', 'id'));
        $keys[] = new xmldb_key('itemid', XMLDB_KEY_FOREIGN, ['itemid'], 'local_catquiz_items', explode(',', 'id'));
        $indexes[] = new xmldb_index('subscription', XMLDB_INDEX_NOTUNIQUE, explode(',', 'userid,itemid,area'));
        $indexes[] = new xmldb_index('userid', XMLDB_INDEX_NOTUNIQUE, explode(',', 'userid'));
        $indexes[] = new xmldb_index('itemid', XMLDB_INDEX_NOTUNIQUE, explode(',', 'itemid'));

        // Conditionally launch add fields, keys and indexes.
        foreach ($keys as $key) {
            $dbman->add_key($table, $key);
        }
        foreach ($indexes as $index) {
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }

        $table = new xmldb_table('local_catquiz_tests');
        $keys = [];
        $indexes = [];
        $keys[] = new xmldb_key('catscaleid', XMLDB_KEY_FOREIGN, ['catscaleid'], 'local_catquiz_catscales', explode(',', 'id'));
        $keys[] = new xmldb_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', explode(',', 'id'));
        $indexes[] = new xmldb_index('component', XMLDB_INDEX_NOTUNIQUE, explode(',', 'componentid,component'));
        $indexes[] = new xmldb_index('catscaleid', XMLDB_INDEX_NOTUNIQUE, explode(',', 'catscaleid'));
        $indexes[] = new xmldb_index('courseid', XMLDB_INDEX_NOTUNIQUE, explode(',', 'courseid'));

        // Conditionally launch add fields, keys and indexes.
        foreach ($keys as $key) {
            $dbman->add_key($table, $key);
        }
        foreach ($indexes as $index) {
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }

        $table = new xmldb_table('local_catquiz_catcontext');
        $keys = [];
        $indexes = [];
        $keys[] = new xmldb_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', explode(',', 'id'));
        $indexes[] = new xmldb_index('parentid', XMLDB_INDEX_NOTUNIQUE, explode(',', 'parentid'));

        // Conditionally launch add fields, keys and indexes.
        foreach ($keys as $key) {
            $dbman->add_key($table, $key);
        }
        foreach ($indexes as $index) {
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }

        $table = new xmldb_table('local_catquiz_attempts');
        $keys = [];
        $indexes = [];
        $keys[] = new xmldb_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', explode(',', 'id'));
        $keys[] = new xmldb_key('catscaleid', XMLDB_KEY_FOREIGN, ['scaleid'], 'local_catquiz_catscales', explode(',', 'id'));
        $keys[] = new xmldb_key('contextid', XMLDB_KEY_FOREIGN, ['contextid'], 'local_catquiz_catcontext', explode(',', 'id'));
        $keys[] = new xmldb_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', explode(',', 'id'));
        $indexes[] = new xmldb_index('userid', XMLDB_INDEX_NOTUNIQUE, explode(',', 'userid'));
        $indexes[] = new xmldb_index('catscaleid', XMLDB_INDEX_NOTUNIQUE, explode(',', 'scaleid'));
        $indexes[] = new xmldb_index('contextid', XMLDB_INDEX_NOTUNIQUE, explode(',', 'contextid'));
        $indexes[] = new xmldb_index('courseid', XMLDB_INDEX_NOTUNIQUE, explode(',', 'courseid'));
        $indexes[] = new xmldb_index('attemptid', XMLDB_INDEX_NOTUNIQUE, explode(',', 'attemptid'));
        $indexes[] = new xmldb_index('instanceid', XMLDB_INDEX_NOTUNIQUE, explode(',', 'instanceid'));

        // Conditionally launch add fields, keys and indexes.
        foreach ($keys as $key) {
            $dbman->add_key($table, $key);
        }
        foreach ($indexes as $index) {
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }

        $table = new xmldb_table('local_catquiz_progress');
        $keys = [];
        $indexes = [];
        $keys[] = new xmldb_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', explode(',', 'id'));
        $keys[] = new xmldb_key('attemptid', XMLDB_KEY_FOREIGN, ['attemptid'], 'local_catquiz_attempts', explode(',', 'id'));
        $indexes[] = new xmldb_index('userid', XMLDB_INDEX_NOTUNIQUE, explode(',', 'userid'));
        $indexes[] = new xmldb_index('attemptid', XMLDB_INDEX_NOTUNIQUE, explode(',', 'attemptid'));

        // Conditionally launch add fields, keys and indexes.
        foreach ($keys as $key) {
            $dbman->add_key($table, $key);
        }
        foreach ($indexes as $index) {
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }

        // Catquiz savepoint reached.
        upgrade_plugin_savepoint(true, 2024080800, 'local', 'catquiz');
    }

    if ($oldversion < 2024081200) {
        // Also add 'component' and 'eventname' as index to the log table for improving performance.
        $table = new xmldb_table('logstore_standard_log');
        $indexes = [];
        $indexes[] = new xmldb_index('component', XMLDB_INDEX_NOTUNIQUE, ['component']);
        $indexes[] = new xmldb_index('eventname', XMLDB_INDEX_NOTUNIQUE, ['eventname']);

        // Conditionally launch add fields, keys and indexes.
        foreach ($indexes as $index) {
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }

        $table = new xmldb_table('local_catquiz_attempts');
        $indexes = [];
        $indexes[] = new xmldb_index('endtime', XMLDB_INDEX_NOTUNIQUE, ['endtime']);
        $indexes[] = new xmldb_index('timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);

        // Conditionally launch add fields, keys and indexes.
        foreach ($indexes as $index) {
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }

        // Catquiz savepoint reached.
        upgrade_plugin_savepoint(true, 2024081200, 'local', 'catquiz');
    }

    if ($oldversion < 2024081300) {
        /* Adding all necessary duplicates for different catcontexts to catquiz_items table
         * according to catquiz_itemparams and updating catquiz_item_params accordingly.
         */

        // Duplicating catquiz_items entrys if needed.
        $sql = "SELECT MAX(id) id, itemid, contextid, MAX(timemodified) timemodified
            FROM {local_catquiz_itemparams}
            GROUP BY itemid, contextid";

        $sqlresult = $DB->get_records_sql($sql);

        foreach ($sqlresult as $lcip) {
            // Load catquiz_items infomation.
            $sql = "SELECT *
                FROM {local_catquiz_items}
                WHERE id = " . $lcip->itemid;

            if (!($lci = $DB->get_record_sql($sql))) {
                continue;
            }

            if (($lci->contextid !== $lcip->contextid) && ($lci->contextid)) {
                $lci->contextid = $lcip->contextid;
                $DB->insert_record('local_catquiz_items', $lci);
            }
        }

        // Set all catquiz_itemparams entrys if pointing correctly to.
        $sql = "SELECT "
                . $DB->sql_concat('lcip.id', "'-'", 'lci1.id', "'-'", 'lci2.id') . " uniquerowid,
                lcip.id id,
                lci1.id itemidold,
                lci2.id itemidnew
            FROM {local_catquiz_itemparams} lcip
            JOIN {local_catquiz_items} lci1 ON lci1.id = lcip.itemid
            JOIN {local_catquiz_items} lci2 ON lci2.componentid = lci1.componentid
              AND lci2.componentname = lci1.componentname AND lci2.contextid = lcip.contextid
              AND lci2.id <> lci1.id";

        $sqlresult = $DB->get_records_sql($sql);

        foreach ($sqlresult as $lcip) {
            if ($lcip->itemidold !== $lcip->itemidnew) {
                if (!$lcip->id || !$lcip->itemidnew) {
                    continue;
                }

                $updaterecord = new stdclass();
                $updaterecord->id = $lcip->id;
                $updaterecord->itemid = $lcip->itemidnew;
                $DB->update_record('local_catquiz_itemparams', $updaterecord);
            }
        }

        // Reset all active paramids in all new catquiz_items entries.
        $sql = "SELECT lci.id id, MAX(lcip.id) activeparamid
                FROM {local_catquiz_items} lci
                  JOIN (
                    SELECT itemid, MAX(status) status
                    FROM {local_catquiz_itemparams} GROUP BY itemid
                  ) activestatus ON activestatus.itemid = lci.id
                  JOIN {local_catquiz_itemparams} lcip
                    ON lcip.itemid = lci.id
                    AND lcip.contextid = lci.contextid
                    AND lcip.status = activestatus.status
                 GROUP BY lci.id";

        $sqlresult = $DB->get_records_sql($sql);

        foreach ($sqlresult as $lci) {
            if (!$lci->id) {
                continue;
            }

            $updaterecord = new stdclass();
            $updaterecord->id = $lci->id;
            $updaterecord->activeparamid = $lci->activeparamid;
            $DB->update_record('local_catquiz_items', $updaterecord);
        }

        // Catquiz savepoint reached.
        upgrade_plugin_savepoint(true, 2024081300, 'local', 'catquiz');
    }

    if ($oldversion < 2024092700) {
        if ($DB->get_dbfamily() === 'mysql') {
            $sql = <<<ENDSQL
UPDATE {local_catquiz_tests}
    SET json = REGEXP_REPLACE(json,
    :regexp,
    :replace)
ENDSQL;
        } else if ($DB->get_dbfamily() === 'postgres') {
            $sql = <<<ENDSQL
UPDATE {local_catquiz_tests}
    SET json = REGEXP_REPLACE(json,
    :regexp,
    :replace,'g')
ENDSQL;
        } else {
            require_once($CFG->libdir . '/moodlelib.php');
            $errormessage = "DB type " . $DB->get_dbtype() . " does not support regular expressions for database operations.";
            $errormessage .= "You may comment out line" . (__LINE__ + 1) . " in " . __FILE__ . " in order to proceed,
                but you may lose all text feedbacks in catquiz tests.";
            echo $OUTPUT->notification($errormessage, 'error');
        }

        $DB->execute(
            $sql,
            ['regexp' =>
            '\'"(feedbackeditor_[^"]+)":\{"text":"(([^"\\\\]*(\\\\.[^"\\\\]*)*))"(,(?:"format":"[0-9]+","itemid":"[0-9]+")?)?\}\'',
            'replace' => '\'"\\1":"\\2","\\1trust":false,"\\1format":"1"\'',
            ]
        );

        // Catquiz savepoint reached.
        upgrade_plugin_savepoint(true, 2024092700, 'local', 'catquiz');
    }

    // This is a bit unconventional. The table already exists with old, long
    // names on a moodle instance that supports longer table names but can't be
    // created on a different instance that has stricter naming rules.
    if ($oldversion < 2025012001) {
        // Check if old table exists first.
        if ($dbman->table_exists('local_catquiz_question_hashmap')) {
            // Rename the table.
            $dbman->rename_table(
                new xmldb_table('local_catquiz_question_hashmap'),
                'local_catquiz_qhashmap'
            );
        } else {
            // Define table local_catquiz_qhashmap.
            $table = new xmldb_table('local_catquiz_qhashmap');

            // Add fields.
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('questionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('questionhash', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
            $table->add_field('hashdata', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            // Add keys.
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('questionid', XMLDB_KEY_FOREIGN, ['questionid'], 'question', ['id']);

            // Add indexes.
            $table->add_index('questionhash', XMLDB_INDEX_NOTUNIQUE, ['questionhash']);

            // Create the table.
            if (!$dbman->table_exists($table)) {
                $dbman->create_table($table);
            }
        }

        if ($dbman->table_exists('local_catquiz_remote_responses')) {
            $dbman->rename_table(
                new xmldb_table('local_catquiz_remote_responses'),
                'local_catquiz_rresponses'
            );
        } else {
            // Define table local_catquiz_rresponses.
            $table = new xmldb_table('local_catquiz_rresponses');

            // Add fields.
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('questionhash', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
            $table->add_field('remoteuserid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('response', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('sourceurl', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timeprocessed', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('processinginfo', XMLDB_TYPE_TEXT, null, null, null, null, null);

            // Add keys.
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

            // Add indexes.
            $table->add_index('questionhash_sourceurl', XMLDB_INDEX_NOTUNIQUE, ['questionhash', 'sourceurl']);
            $table->add_index('timeprocessed', XMLDB_INDEX_NOTUNIQUE, ['timeprocessed']);

            // Create the table.
            if (!$dbman->table_exists($table)) {
                $dbman->create_table($table);
            }
        }

        upgrade_plugin_savepoint(true, 2025012001, 'local', 'catquiz');
    }

    if ($oldversion < 2025012002) {
        // Define field contextid to be added to local_catquiz_tests.
        $table = new xmldb_table('local_catquiz_tests');
        $field = new xmldb_field('contextid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'catscaleid');

        // Conditionally launch add field contextid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // For each existing test, set the contextid that corresponds to the main scale of that test.
        $tests = $DB->get_records('local_catquiz_tests');

        foreach ($tests as $test) {
            if (empty($test->json)) {
                continue;
            }

            $testconfig = json_decode($test->json);
            if (!$testconfig || !isset($testconfig->catquiz_catscales)) {
                continue;
            }

            $scaleid = $testconfig->catquiz_catscales;
            if (!$scaleid) {
                continue;
            }

            // Get the contextid from the catscales table.
            if ($scale = $DB->get_record('local_catquiz_catscales', ['id' => $scaleid])) {
                if (!empty($scale->contextid)) {
                    $test->contextid = $scale->contextid;
                    $DB->update_record('local_catquiz_tests', $test);
                }
            }
        }

        // Catquiz savepoint reached.
        upgrade_plugin_savepoint(true, 2025012002, 'local', 'catquiz');
    }

    if ($oldversion < 2025012003) {
        // Rename field remoteuserid on table local_catquiz_rresponses to attempthash.
        $table = new xmldb_table('local_catquiz_rresponses');
        $field = new xmldb_field('remoteuserid');
        $field->set_attributes(XMLDB_TYPE_INTEGER, 10);

        // Launch rename field attempthash.
        if (!$dbman->field_exists($table, 'attempthash')) {
            $dbman->rename_field($table, $field, 'attempthash');
        }

        // Catquiz savepoint reached.
        upgrade_plugin_savepoint(true, 2025012003, 'local', 'catquiz');
    }

    if ($oldversion < 2025012004) {
        // Define index questionhash (notunique) to be added to local_catquiz_rresponses.
        $table = new xmldb_table('local_catquiz_rresponses');
        $index = new xmldb_index('questionhash', XMLDB_INDEX_NOTUNIQUE, ['questionhash']);

        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }

        $dbman->add_index($table, $index);

        // Catquiz savepoint reached.
        upgrade_plugin_savepoint(true, 2025012004, 'local', 'catquiz');
    }

    if ($oldversion < 2025012100) {
        // Define table local_catquiz_sync_event to be created.
        $table = new xmldb_table('local_catquiz_sync_event');

        // Adding fields to table local_catquiz_sync_event.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('contextid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('catscaleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('num_fetched_params', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table local_catquiz_sync_event.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Adding indexes to table local_catquiz_sync_event.
        $table->add_index('catscaleid', XMLDB_INDEX_NOTUNIQUE, ['catscaleid']);
        $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);

        // Conditionally launch create table for local_catquiz_sync_event.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Catquiz savepoint reached.
        upgrade_plugin_savepoint(true, 2025012100, 'local', 'catquiz');
    }

    // In case there are CAT scales without a label, set the label to the lowercase scale name.
    if ($oldversion < 2025012702) {
        $scales = $DB->get_records('local_catquiz_catscales');
        foreach ($scales as $scale) {
            if (!$scale->label) {
                $scale->label = strtolower($scale->name);
                $DB->update_record('local_catquiz_catscales', $scale);
            }
        }
        // Catquiz savepoint reached.
        upgrade_plugin_savepoint(true, 2025012702, 'local', 'catquiz');
    }

    if ($oldversion < 2026052700) {
        // Defensive repair: some systems miss legacy columns expected by mod_adaptivequiz.
        $table = new xmldb_table('adaptivequiz_attempt');

        $field = new xmldb_field(
            'difficultysum',
            XMLDB_TYPE_NUMBER,
            '10, 7',
            null,
            XMLDB_NOTNULL,
            null,
            '0.0',
            'questionsattempted'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field(
            'standarderror',
            XMLDB_TYPE_NUMBER,
            '10, 5',
            null,
            XMLDB_NOTNULL,
            null,
            '0.0',
            'difficultysum'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field(
            'measure',
            XMLDB_TYPE_NUMBER,
            '10, 5',
            null,
            XMLDB_NOTNULL,
            null,
            '0.0',
            'standarderror'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Catquiz savepoint reached.
        upgrade_plugin_savepoint(true, 2026052700, 'local', 'catquiz');
    }

    if ($oldversion < 2026081714) {
        // Issue #44: make the scheduled recalculation safe on existing installs.
        // Disable it and switch it to a quarterly cadence, but only when the admin
        // has not customised it (so deliberate admin settings are preserved).
        $task = \core\task\manager::get_scheduled_task(
            \local_catquiz\task\recalculate_cat_model_params::class
        );
        if ($task !== false && !$task->is_customised()) {
            $task->set_disabled(true);
            $task->set_minute('R');
            $task->set_hour('0');
            $task->set_day('1');
            $task->set_month('*/3');
            $task->set_day_of_week('*');
            \core\task\manager::configure_scheduled_task($task);
        }

        // Catquiz savepoint reached.
        upgrade_plugin_savepoint(true, 2026081714, 'local', 'catquiz');
    }

    if ($oldversion < 2026082100) {
        // Issue #5: harden the identity of a local CAT attempt so that at most
        // one row exists per adaptive quiz attempt. First repair historical
        // duplicates (keep the most recent row per attemptid), then replace the
        // non-unique index with a unique one.
        $duplicates = $DB->get_records_sql(
            "SELECT attemptid, MAX(id) AS keepid, COUNT(*) AS cnt
               FROM {local_catquiz_attempts}
              WHERE attemptid IS NOT NULL
           GROUP BY attemptid
             HAVING COUNT(*) > 1"
        );
        foreach ($duplicates as $dup) {
            $DB->delete_records_select(
                'local_catquiz_attempts',
                'attemptid = :attemptid AND id <> :keepid',
                ['attemptid' => $dup->attemptid, 'keepid' => $dup->keepid]
            );
        }

        $table = new xmldb_table('local_catquiz_attempts');
        $oldindex = new xmldb_index('attemptid', XMLDB_INDEX_NOTUNIQUE, ['attemptid']);
        if ($dbman->index_exists($table, $oldindex)) {
            $dbman->drop_index($table, $oldindex);
        }
        $newindex = new xmldb_index('attemptid', XMLDB_INDEX_UNIQUE, ['attemptid']);
        if (!$dbman->index_exists($table, $newindex)) {
            $dbman->add_index($table, $newindex);
        }

        // Catquiz savepoint reached.
        upgrade_plugin_savepoint(true, 2026082100, 'local', 'catquiz');
    }

    if ($oldversion < 2026082104) {
        // Issue #9: per-attempt, per-scale result history. One row per finalised
        // attempt and successfully tested scale; written only by the finaliser
        // after validation. Additive and idempotent.
        $table = new xmldb_table('local_catquiz_attemptscale');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('catattemptid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('contextid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('catscaleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('score', XMLDB_TYPE_NUMBER, '10, 4', null, null, null, null);
            $table->add_field('standarderror', XMLDB_TYPE_NUMBER, '10, 4', null, null, null, null);
            $table->add_field('n', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('fraction', XMLDB_TYPE_NUMBER, '10, 4', null, null, null, null);
            $table->add_field('isprimary', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('isvalid', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('resultsource', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('validationstatus', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('catattemptid', XMLDB_KEY_FOREIGN, ['catattemptid'], 'local_catquiz_attempts', ['id']);
            $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_key('catscaleid', XMLDB_KEY_FOREIGN, ['catscaleid'], 'local_catquiz_catscales', ['id']);
            $table->add_key('contextid', XMLDB_KEY_FOREIGN, ['contextid'], 'local_catquiz_catcontext', ['id']);
            $table->add_key('catattemptid_catscaleid', XMLDB_KEY_UNIQUE, ['catattemptid', 'catscaleid']);

            $table->add_index('userid_contextid_catscaleid', XMLDB_INDEX_NOTUNIQUE, ['userid', 'contextid', 'catscaleid']);
            $table->add_index('isvalid', XMLDB_INDEX_NOTUNIQUE, ['isvalid']);
            $table->add_index('isprimary', XMLDB_INDEX_NOTUNIQUE, ['isprimary']);

            $dbman->create_table($table);
        }

        // Catquiz savepoint reached.
        upgrade_plugin_savepoint(true, 2026082104, 'local', 'catquiz');
    }

    if ($oldversion < 2026082803) {
        // Issue #25: composite and unique indexes for the hot access patterns.
        //
        // Order matters: every unique index is preceded by a deduplication step,
        // because adding a unique index to a table that already contains duplicates
        // fails the whole upgrade. Each step reports what it removed via mtrace, so
        // the cleanup is auditable rather than silent.

        // 1. The index named "timecreated" actually indexed instanceid, which merely
        // duplicated the separate instanceid index and left time-range filters
        // unindexed. Drop the misnamed one and create it on the intended field.
        $table = new xmldb_table('local_catquiz_attempts');
        $wrongindex = new xmldb_index('timecreated', XMLDB_INDEX_NOTUNIQUE, ['instanceid']);
        if ($dbman->index_exists($table, $wrongindex)) {
            $dbman->drop_index($table, $wrongindex);
        }
        $rightindex = new xmldb_index('timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
        if (!$dbman->index_exists($table, $rightindex)) {
            $dbman->add_index($table, $rightindex);
        }

        // 2. Statistics access pattern: context + scale + user.
        $statsindex = new xmldb_index(
            'contextid_scaleid_userid_attemptid',
            XMLDB_INDEX_NOTUNIQUE,
            ['contextid', 'scaleid', 'userid', 'attemptid']
        );
        if (!$dbman->index_exists($table, $statsindex)) {
            $dbman->add_index($table, $statsindex);
        }

        // 3. One person parameter per user, context and scale. The saving code in
        // model_person_param_list keys existing rows by userid and therefore already
        // assumed this; duplicates would silently make it update the wrong row.
        local_catquiz_upgrade_remove_duplicates(
            'local_catquiz_personparams',
            ['userid', 'contextid', 'catscaleid']
        );
        $table = new xmldb_table('local_catquiz_personparams');
        $ppindex = new xmldb_index('userid_contextid_catscaleid', XMLDB_INDEX_UNIQUE, ['userid', 'contextid', 'catscaleid']);
        if (!$dbman->index_exists($table, $ppindex)) {
            $dbman->add_index($table, $ppindex);
        }

        // 4. Exactly one progress row per attempt.
        local_catquiz_upgrade_remove_duplicates('local_catquiz_progress', ['attemptid']);
        $table = new xmldb_table('local_catquiz_progress');
        $oldprogress = new xmldb_index('attemptid', XMLDB_INDEX_NOTUNIQUE, ['attemptid']);
        if ($dbman->index_exists($table, $oldprogress)) {
            $dbman->drop_index($table, $oldprogress);
        }
        $newprogress = new xmldb_index('attemptid', XMLDB_INDEX_UNIQUE, ['attemptid']);
        if (!$dbman->index_exists($table, $newprogress)) {
            $dbman->add_index($table, $newprogress);
        }

        // 5. Item lookup by scale and component, and the join to the active parameter.
        $table = new xmldb_table('local_catquiz_items');
        foreach (
            [
                'catscaleid_componentname_componentid' => ['catscaleid', 'componentname', 'componentid'],
                'catscaleid_activeparamid' => ['catscaleid', 'activeparamid'],
            ] as $name => $fields
        ) {
            $index = new xmldb_index($name, XMLDB_INDEX_NOTUNIQUE, $fields);
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }

        // Catquiz savepoint reached.
        upgrade_plugin_savepoint(true, 2026082803, 'local', 'catquiz');
    }

    if ($oldversion < 2026082805) {
        // Issue #25: drop the index declarations that a foreign key already covers.
        //
        // XMLDB does not create real foreign key constraints here; it creates an
        // index on the referencing column. Where install.xml additionally declared
        // an <INDEX> on that same column, every row change had to maintain two
        // physically identical indexes. That is pure write cost on exactly the
        // tables written on every single answer (attempts, progress).
        //
        // The declarations are gone from install.xml, so new installations are
        // already correct. Existing installations still carry both, and the
        // duplicate must be dropped by name: $dbman->drop_index() resolves an index
        // by its columns and would happily drop whichever it finds first, including
        // the unique one we must keep on progress.attemptid.
        $duplicates = [
            ['local_catquiz_catscales', ['contextid'], false],
            ['local_catquiz_subscriptions', ['itemid'], false],
            ['local_catquiz_tests', ['catscaleid'], false],
            ['local_catquiz_tests', ['courseid'], false],
            ['local_catquiz_items', ['catscaleid'], false],
            ['local_catquiz_items', ['contextid'], false],
            ['local_catquiz_items', ['activeparamid'], false],
            ['local_catquiz_itemparams', ['contextid'], false],
            ['local_catquiz_personparams', ['userid'], false],
            ['local_catquiz_personparams', ['catscaleid'], false],
            ['local_catquiz_personparams', ['contextid'], false],
            ['local_catquiz_personparams', ['attemptid'], false],
            ['local_catquiz_attempts', ['userid'], false],
            ['local_catquiz_attempts', ['scaleid'], false],
            ['local_catquiz_attempts', ['contextid'], false],
            ['local_catquiz_attempts', ['courseid'], false],
            ['local_catquiz_progress', ['userid'], false],
            // The attemptid column of progress carries a uniqueness guarantee, now
            // expressed by a foreign-unique key. Keep the unique index, drop the
            // plain one.
            ['local_catquiz_progress', ['attemptid'], true],
        ];

        $dropped = 0;
        foreach ($duplicates as [$tablename, $columns, $keepunique]) {
            $dropped += local_catquiz_upgrade_drop_duplicate_indexes(
                $tablename,
                $columns,
                $keepunique
            );
        }
        if ($dropped > 0) {
            mtrace("local_catquiz: removed $dropped redundant index(es).");
        }

        // Catquiz savepoint reached.
        upgrade_plugin_savepoint(true, 2026082805, 'local', 'catquiz');
    }

    if ($oldversion < 2026083000) {
        // Issue #54: persist whether the stored parameters are usable for their
        // model. Without a column the backend cannot filter or sort on the state -
        // it is derived in PHP from the model contract, and an ORDER BY on something
        // that does not exist in the database is not an option.
        $table = new xmldb_table('local_catquiz_itemparams');
        $field = new xmldb_field('usable', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'status');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $index = new xmldb_index('contextid_usable', XMLDB_INDEX_NOTUNIQUE, ['contextid', 'usable']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Backfill from the same rule the runtime uses, in batches: the parameter
        // table grows with items times contexts times models, so loading it whole
        // would defeat the point of the exercise.
        local_catquiz_upgrade_backfill_usable();

        upgrade_plugin_savepoint(true, 2026083000, 'local', 'catquiz');
    }

    if ($oldversion < 2026090207) {
        // Issue #65: remove the second, unread copy of the hub credentials. The
        // remote settings form used to write central_host and central_token under
        // local_catquiz, while everything that contacts a hub reads them from
        // catquizcentralhub_client. Nothing consumed the copy - it only kept a host
        // and a token on disk outside the reach of the client plugin's kill-switch.
        //
        // Carried over rather than dropped, so an installation that only ever used
        // the old form keeps working; then removed from the old place.
        foreach (['central_host', 'central_token'] as $name) {
            $legacy = get_config('local_catquiz', $name);
            if ($legacy === false || $legacy === '') {
                continue;
            }
            if (!get_config('catquizcentralhub_client', $name)) {
                set_config($name, $legacy, 'catquizcentralhub_client');
            }
            unset_config($name, 'local_catquiz');
        }

        upgrade_plugin_savepoint(true, 2026090207, 'local', 'catquiz');
    }

    return true;
}

/**
 * Recomputes the usable flag for every stored item parameter.
 *
 * Used by the issue #54 upgrade and by the consistency check. Returns the number of
 * rows whose stored flag did not match the rule - zero means backend and runtime
 * agree.
 *
 * @param bool $dryrun When true, only count the mismatches instead of fixing them.
 * @return int Number of mismatching rows.
 */
function local_catquiz_upgrade_backfill_usable(bool $dryrun = false): int {
    global $DB;

    $mismatches = 0;
    $batch = 500;
    $lastid = 0;

    while (true) {
        $records = $DB->get_records_select(
            'local_catquiz_itemparams',
            'id > :lastid',
            ['lastid' => $lastid],
            'id ASC',
            'id, model, difficulty, discrimination, guessing, json, usable',
            0,
            $batch
        );
        if (empty($records)) {
            break;
        }

        foreach ($records as $record) {
            $lastid = (int) $record->id;
            $expected = empty(\local_catquiz\local\model\model_strategy::validate_item_parameters($record)) ? 1 : 0;
            if ((int) $record->usable === $expected) {
                continue;
            }
            $mismatches++;
            if (!$dryrun) {
                $DB->set_field('local_catquiz_itemparams', 'usable', $expected, ['id' => $record->id]);
            }
        }
    }

    return $mismatches;
}

/**
 * Drops physically identical indexes on a column, leaving exactly one.
 *
 * Issue #25: several columns were covered both by a foreign key (which XMLDB
 * implements as an index) and by an explicit <INDEX> declaration, so two identical
 * indexes existed and both had to be maintained on every write.
 *
 * Moodle's $dbman->drop_index() resolves an index by its columns only and returns
 * the first match, which makes it unsafe here: on a column whose duplicates differ
 * in uniqueness it might drop the unique one. This helper therefore resolves the
 * concrete index names itself and drops all but the one to keep.
 *
 * @param string $tablename Table name without prefix.
 * @param string[] $columns Columns the index covers.
 * @param bool $keepunique Keep the unique index rather than an arbitrary one.
 * @return int Number of dropped indexes.
 */
function local_catquiz_upgrade_drop_duplicate_indexes(
    string $tablename,
    array $columns,
    bool $keepunique = false
): int {
    global $DB;

    $dbman = $DB->get_manager();
    if (!$dbman->table_exists(new xmldb_table($tablename))) {
        return 0;
    }

    $matching = [];
    foreach ($DB->get_indexes($tablename) as $name => $info) {
        $indexcolumns = array_values($info['columns']);
        if ($indexcolumns === array_values($columns)) {
            $matching[$name] = !empty($info['unique']);
        }
    }

    if (count($matching) < 2) {
        return 0;
    }

    // Decide which one survives before dropping anything.
    $keep = null;
    if ($keepunique) {
        foreach ($matching as $name => $isunique) {
            if ($isunique) {
                $keep = $name;
                break;
            }
        }
        if ($keep === null) {
            // No unique index present: the guarantee this column relies on is
            // missing, so do not touch anything and say so.
            mtrace(sprintf(
                'local_catquiz: %s(%s) has no unique index - left untouched.',
                $tablename,
                implode(',', $columns)
            ));
            return 0;
        }
    } else {
        $keep = array_key_first($matching);
    }

    $prefixed = $DB->get_prefix() . $tablename;
    $mysql = $DB->get_dbfamily() === 'mysql';
    $dropped = 0;
    foreach (array_keys($matching) as $name) {
        if ($name === $keep) {
            continue;
        }
        $sql = $mysql
            ? "DROP INDEX $name ON $prefixed"
            : "DROP INDEX $name";
        $DB->change_database_structure($sql);
        $dropped++;
    }

    return $dropped;
}

/**
 * Removes duplicate rows before a unique index is added, keeping the newest row.
 *
 * Issue #25: adding a unique index to a table that already holds duplicates aborts
 * the whole upgrade with a database error that says nothing about which data caused
 * it. This helper removes the duplicates first and reports per group what it deleted,
 * so an administrator can trace the cleanup afterwards instead of guessing.
 *
 * The row with the highest id wins: for both affected tables the later row is the
 * more recently written state.
 *
 * @param string $table Table name without prefix.
 * @param string[] $fields Fields that together must be unique.
 * @return int Number of deleted rows.
 */
function local_catquiz_upgrade_remove_duplicates(string $table, array $fields): int {
    global $DB;

    $fieldlist = implode(', ', $fields);

    // GROUP BY treats two NULLs as the same value, a unique index does not: both
    // PostgreSQL and MariaDB allow any number of rows that carry NULL in an indexed
    // column. Grouping alone would therefore delete rows that the index would have
    // accepted - real data loss for no gain, and contextid (personparams) as well as
    // attemptid (progress) are nullable. Skip any group in which one of the fields
    // is NULL; such rows can never violate the constraint.
    $notnull = [];
    foreach ($fields as $field) {
        $notnull[] = "$field IS NOT NULL";
    }
    $where = implode(' AND ', $notnull);

    $sql = "SELECT $fieldlist, COUNT(*) AS cnt, MAX(id) AS keepid
              FROM {" . $table . "}
             WHERE $where
          GROUP BY $fieldlist
            HAVING COUNT(*) > 1";

    $groups = $DB->get_recordset_sql($sql);
    $deleted = 0;
    foreach ($groups as $group) {
        // NULL groups were already excluded by the query above, so every field of a
        // returned group carries a real value.
        $conditions = [];
        $params = [];
        foreach ($fields as $field) {
            $conditions[] = "$field = :$field";
            $params[$field] = $group->{$field};
        }
        $params['keepid'] = $group->keepid;
        $where = implode(' AND ', $conditions) . ' AND id <> :keepid';
        $count = $DB->count_records_select($table, $where, $params);
        if ($count > 0) {
            $DB->delete_records_select($table, $where, $params);
            $deleted += $count;
            mtrace(sprintf(
                'local_catquiz: removed %d duplicate row(s) from %s for %s, kept id %d.',
                $count,
                $table,
                json_encode(array_intersect_key((array) $group, array_flip($fields))),
                $group->keepid
            ));
        }
    }
    $groups->close();

    // Nothing is reported when there is nothing to clean up; the caller decides
    // whether the number matters.
    return $deleted;
}
