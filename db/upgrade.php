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

require_once(__DIR__.'/upgradelib.php');

/**
 * Execute local_catquiz upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_catquiz_upgrade($oldversion) {
    global $DB;

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

    return true;
}
