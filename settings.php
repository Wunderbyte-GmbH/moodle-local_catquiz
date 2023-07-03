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
 * Plugin administration pages are defined here.
 *
 * @package     local_catquiz
 * @category    admin
 * @copyright   2022 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$componentname = 'local_catquiz';

// Default for users that have site config.
if ($hassiteconfig) {
    $settings = new admin_settingpage($componentname . '_settings',  get_string('pluginname', 'local_catquiz'));
    $ADMIN->add('localplugins', $settings);

    foreach (core_plugin_manager::instance()->get_plugins_of_type('catmodel') as $plugin) {
            $plugin->load_settings($ADMIN, 'localplugins', $hassiteconfig);
    }

    $catscalelink = new moodle_url('/local/catquiz/manage_catscales.php');
    $actionlink = new action_link($catscalelink, get_string('catquizsettings', 'local_catquiz'));
    $settingsling = ['link' => $OUTPUT->render($actionlink)];
    $settings->add(
            new admin_setting_heading(
                    'local_catquiz/catscales',
                    get_string('catscales', 'local_catquiz'),
                    get_string('catscales:information', 'local_catquiz', $settingsling),
            )
    );
}
