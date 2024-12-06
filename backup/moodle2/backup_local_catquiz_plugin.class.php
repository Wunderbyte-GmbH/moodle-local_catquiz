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
 * Backup plugin for local_catquiz.
 *
 * @package    local_catquiz
 * @copyright  2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\backup;

use backup;
use backup_activity_task;
use backup_local_plugin;
use backup_nested_element;

/**
 * Class backup_local_catquiz_plugin handles backup functionality for the local_catquiz plugin.
 */
class backup_local_catquiz_plugin extends backup_activity_task {
        /**
         * Define the structure for backing up local_catquiz data.
         *
         * @return backup_nested_element The plugin element containing the backup structure.
         */
    protected function define_module_plugin_structure() {
        $plugin = $this->get_plugin_element();

        // Create a wrapper element for our plugin's data.
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());
        $plugin->add_child($pluginwrapper);

        // Define the structure for our settings.
        $settings = new backup_nested_element('catquiz_settings', ['id'], [
            'componentid',
            'catscaleid',
            'courseid',
            'name',
            'description',
            'descriptionformat',
            'json',
            'status',
            'contextid',
        ]);

        $pluginwrapper->add_child($settings);

        // Set the source table where your settings are stored.
        $settings->set_source_table('local_catquiz_tests', [
            'componentid' => backup::VAR_ACTIVITYID,
        ]);

        return $plugin;
    }
}
