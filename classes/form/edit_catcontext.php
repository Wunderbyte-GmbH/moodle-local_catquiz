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

namespace local_catquiz\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("$CFG->libdir/formslib.php");
require_once($CFG->dirroot . "/local/catquiz/lib.php");

use cache_helper;
use context;
use context_system;
use core_form\dynamic_form;
use local_catquiz\catcontext;
use local_catquiz\local\model\model_strategy;
use moodle_url;
use stdClass;

/**
 * Dynamic form.
 * @copyright Wunderbyte GmbH <info@wunderbyte.at>
 * @package   local_catquiz
 * @author    Georg Maißer
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_catcontext extends dynamic_form {

    /**
     * {@inheritdoc}
     * @see moodleform::definition()
     */
    public function definition() {

        $mform = $this->_form;
        $data = (object) $this->_ajaxformdata;

        if (isset($data->id)) {
            $mform->addElement('hidden', 'id', $data->id);
        }

        $mform->addElement('hidden', 'default', $data->default);

        $mform->addElement('header', 'edit_catquiz', get_string('managecatcontexts', 'local_catquiz'));

        // Add a text field for the catcontext name.
        $mform->addElement('text', 'name', get_string('name'));
        $mform->setType('name', PARAM_TEXT);

        // Add a textarea field for the catscale description.
        $mform->addElement('editor', 'description', get_string('description'), 'wrap="virtual" rows="5" cols="50"');
        $mform->setType('description', PARAM_CLEANHTML);

        $mform->addElement('date_time_selector', 'starttimestamp',
                get_string("starttimestamp", "local_catquiz"));
        $mform->setType('starttimestamp', PARAM_INT);

        $mform->addElement('date_time_selector', 'endtimestamp',
                get_string("endtimestamp", "local_catquiz"));
        $mform->setType('endtimestamp', PARAM_INT);

        model_strategy::handle_mform($mform);
    }

    /**
     * Check access for dynamic submission.
     *
     * @return void
     */
    protected function check_access_for_dynamic_submission(): void {
        require_capability('local/catquiz:manage_catscales', $this->get_context_for_dynamic_submission());
    }

    /**
     * Process the form submission, used if form was submitted via AJAX
     *
     * This method can return scalar values or arrays that can be json-encoded, they will be passed to the caller JS.
     *
     * Submission data can be accessed as: $this->get_data()
     *
     * @return object
     */
    public function process_dynamic_submission(): object {
        $data = $this->get_data();

        if (isset($data->id)) {

            $data->descriptionformat = $data->description['format'];
            $data->description = $data->description['text'];
            $data->json = json_encode(
                [
                    'max_iterations' => $data->max_iterations,
                    'default' => $data->default ? true : false,
                    'strategy' => ['model_override' => $data->model_override],
                ]);

            $catcontext = new catcontext($data);

            $catcontext->save_or_update();

        }

        cache_helper::purge_by_event('changesincatcontexts');

        return $data;
    }

    /**
     * Load in existing data as form defaults
     *
     * Can be overridden to retrieve existing values from db by entity id and also
     * to preprocess editor and filemanager elements
     *
     * Example:
     *     $this->set_data(get_entity($this->_ajaxformdata['cmid']));
     */
    public function set_data_for_dynamic_submission(): void {
        $data = (object) $this->_ajaxformdata;
        if (!empty($data->id)) {

            $record = (object)[
                'id' => $data->id,
            ];
            $catcontext = new catcontext($record);
            $storeddata = (array)$catcontext->return_as_class();

            $storeddata['description'] = [
                'format' => $storeddata['descriptionformat'],
                'text' => $storeddata['description'],
            ];

            $jsonobj = json_decode($storeddata['json']);
            $storeddata['max_iterations'] = $jsonobj->max_iterations ?? '';
            $storeddata['model_override'] = $jsonobj->strategy->model_override ?? '';
            $storeddata['default'] = $jsonobj->default ?? false;

            foreach ($storeddata as $key => $value) {
                $data->$key = $value;
            }

        }

        $this->set_data($data);
    }

    /**
     * Returns form context
     *
     * If context depends on the form data, it is available in $this->_ajaxformdata or
     * by calling $this->optional_param()
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {

        return context_system::instance();
    }

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX
     *
     * This is used in the form elements sensitive to the page url, such as Atto autosave in 'editor'
     *
     * If the form has arguments (such as 'id' of the element being edited), the URL should
     * also have respective argument.
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {

        // We don't need it, as we only use it in modal.
        return new moodle_url('/');
    }

    /**
     * Validate form.
     *
     * @param array $data
     * @param array $files
     * @return array $errors
     */
    public function validation($data, $files): array {
        $errors = [];
        model_strategy::validation($data, $files, $errors);
        return $errors;
    }
}
