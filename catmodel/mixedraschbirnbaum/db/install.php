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
 * Code to be executed after the plugin's database scheme has been installed is defined here.
 *
 * @package     catmodel_mixedraschbirnbaum
 * @category    upgrade
 * @copyright   2023 Georg Maißer Wunderbyte GmbH<info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Custom code to be run on installing the plugin.
 */
function xmldb_catmodel_mixedraschbirnbaum_install() {
    global $DB;
    $dbman = $DB->get_manager();

    // Define field guessing to be added to local_catquiz_itemparams.
    $table = new xmldb_table('local_catquiz_itemparams');
    $field = new xmldb_field('guessing', XMLDB_TYPE_NUMBER, '10, 4', null, null, null, '0', 'discrimination');

    // Conditionally launch add field guessing.
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }

    return true;
}
