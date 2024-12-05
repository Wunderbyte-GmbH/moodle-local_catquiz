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
 * Settings form for remote configuration.
 *
 * @package    local_catquiz
 * @copyright  2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\form;

use core_form\dynamic_form;

/**
 * Form class for remote settings configuration.
 *
 * This allows configuration of settings related to remote calculations.
 */
class remote_settings_form extends dynamic_form {
    /**
     * Define the form elements.
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('text', 'central_host', get_string('central_host', 'local_catquiz'));
        $mform->setType('central_host', PARAM_URL);
        $mform->addHelpButton('central_host', 'central_host', 'local_catquiz');

        $mform->addElement('text', 'central_token', get_string('central_token', 'local_catquiz'));
        $mform->setType('central_token', PARAM_TEXT);
        $mform->addHelpButton('central_token', 'central_token', 'local_catquiz');

        $this->add_action_buttons(true);

        // Load initial data.
        $config = get_config('local_catquiz');
        $this->set_data((array)$config);
    }

    /**
     * Process the form submission.
     *
     * @return array Returns array with success status.
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        if ($data) {
            foreach ($data as $name => $value) {
                if ($name !== 'submitbutton') {
                    set_config($name, $value, 'local_catquiz');
                }
            }
            return ['success' => true];
        }
        return ['success' => false];
    }

    /**
     * Get the context for the dynamic submission.
     *
     * @return \context Returns the system context.
     */
    protected function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }

    /**
     * Check if the user has the required capabilities.
     */
    protected function check_access_for_dynamic_submission(): void {
        require_capability('moodle/site:config', $this->get_context_for_dynamic_submission());
    }

    /**
     * Set the default data for the form.
     */
    public function set_data_for_dynamic_submission(): void {
        $config = get_config('local_catquiz');
        $this->set_data($config);
    }

    /**
     * Get the page URL for the dynamic submission.
     *
     * @return \moodle_url Returns the URL for the form submission.
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        global $PAGE;
        return $PAGE->url;
    }
}
