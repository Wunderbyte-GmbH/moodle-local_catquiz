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
 * Restore plugin class for local_catquiz.
 *
 * @package    local_catquiz
 * @copyright  2024 Your Name <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restore plugin class for local_catquiz.
 */
class restore_local_catquiz_plugin extends restore_local_plugin {
    /**
     * Define the structure for the local_catquiz plugin within a module.
     *
     * @return array Array of restore_path_element objects.
     */
    protected function define_module_plugin_structure() {
        $paths = [
            new restore_path_element(
                'local_catquiz_setting',
                $this->get_pathfor('/catquiz_settings'),
            ),
        ];

        return $paths;
    }

    /**
     * Process the local_catquiz_setting data.
     *
     * @param array $data The data to process.
     * @return void
     */
    public function process_local_catquiz_setting($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Remove the id as we're creating a new record.
        unset($data->id);

        // Map the old adaptivequiz id to the new one.
        $data->componentid = $this->get_new_parentid('adaptivequiz');

        // Insert the new record.
        $newid = $DB->insert_record('local_catquiz_settings', $data);

        // Store mapping for potential cross-references.
        $this->set_mapping('local_catquiz_setting', $oldid, $newid);
    }
}
