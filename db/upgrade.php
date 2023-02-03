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

    return true;
}
