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


    return true;
}
