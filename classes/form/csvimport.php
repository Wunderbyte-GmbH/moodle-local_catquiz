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
use local_catquiz\import\fileparser;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("$CFG->libdir/formslib.php");
require_once($CFG->dirroot . "/local/catquiz/lib.php");

use context;
use context_system;
use core_form\dynamic_form;
use moodleform;
use local_catquiz\local\csvparser;
use moodle_url;
use stdClass;
use core_text;
use csv_import_reader;
use local_catquiz\import\csvsettings;

/**
 * Dynamic form.
 * @copyright Wunderbyte GmbH <info@wunderbyte.at>
 * @package   local_catquiz
 * @author    Wunderbyte Gmbh
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class csvimport extends dynamic_form {

    /**
     * {@inheritdoc}
     * @see moodleform::definition()
     */
    public function definition() {

        global $CFG, $DB, $PAGE;

        $mform = $this->_form;
        $data = (object) $this->_ajaxformdata;

        if (isset($data->id)) {
            $mform->addElement('hidden', 'id', $data->id);
            $mform->setType('id', PARAM_INT);
        }
        $mform->addElement(
            'filepicker', 
            'csvfile', 
            get_string('importcsv', 'local_catquiz'), 
            null, 
            [
                'maxbytes' => $CFG->maxbytes,
                'accepted_types' => 'csv',
            ]
        );
        $mform->addRule('csvfile', null, 'required', null, 'client');
        $choices = $this->get_delimiter_list();
        $mform->addElement('select', 'delimiter_name', get_string('csvdelimiter', 'tool_uploaduser'), $choices);
        if (array_key_exists('cfg', $choices)) {
            $mform->setDefault('delimiter_name', 'cfg');
        } else if (get_string('listsep', 'langconfig') == ';') {
            $mform->setDefault('delimiter_name', 'semicolon');
        } else {
            $mform->setDefault('delimiter_name', 'comma');
        }

        $choices = core_text::get_encodings();
        $mform->addElement('select', 'encoding', get_string('encoding', 'tool_uploaduser'), $choices);
        $mform->setDefault('encoding', 'UTF-8');

        $mform->addElement('text', 'dateparseformat', get_string('dateparseformat', 'booking'));
        $mform->setType('dateparseformat', PARAM_NOTAGS);
        $mform->setDefault('dateparseformat', get_string('defaultdateformat', 'booking'));
        $mform->addRule('dateparseformat', null, 'required', null, 'client');
        $mform->addHelpButton('dateparseformat', 'dateparseformat', 'mod_booking');

        $buttonarray = array();
        $buttonarray[] = $mform->createElement('submit', 'submitbutton', get_string('submit'));
        $buttonarray[] = $mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', ' ', false);
    }

    public static function call_settings_class() {

        $columnsassociative = array(
            'userid' => array(
                'columnname' => get_string('id'),
                'mandatory' => true,
                'format' => PARAM_INT,
                'default' => false,
                
            ),
            'starttime' => array(
                'mandatory' => false,
                'format' => 'int',
                'type' => 'date',
            ),
        );

        $columnssequential = [
            array(
            'name' => 'userid',
            'columnname' => get_string('id'),
            'mandatory' => true,
            'format' => 'string',
            'transform' => fn($x) => get_string($x, 'local_catquiz'), 
            ), 
            array (
            'name' => 'starttime',
            'mandatory' => false,
            'format' => 'int',
            'type' => 'date',
            'defaultvalue' => 1685015874,
            )
            ];
        $settings = new csvsettings($columnssequential);
        return $settings;
    }
    
    /**
     * Get list of cvs delimiters
     *
     * @return array suitable for selection box
     */
    public static function get_delimiter_list() {
        global $CFG;
        $delimiters = array('comma'=>',', 'semicolon'=>';', 'colon'=>':', 'tab'=>'\\t');
        if (isset($CFG->CSV_DELIMITER) and strlen($CFG->CSV_DELIMITER) === 1 and !in_array($CFG->CSV_DELIMITER, $delimiters)) {
            $delimiters['cfg'] = $CFG->CSV_DELIMITER;
        }
        return $delimiters;
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
        $content = $this->get_file_content('csvfile');
        $settings = $this->call_settings_class(); // todo transfer real $content to parser
        
        if(!empty($data->delimiter_name)) {
            $settings->set_delimiter($data->delimiter_name);
        }
        if(!empty($data->encoding)) {
            $settings->set_encoding($data->encoding);
        }
        if(!empty($data->dateparseformat)) {
            $settings->set_dateformat($data->dateparseformat);
        }

        $parser = new fileparser($content, $settings);
        return $settings;
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
     * @param stdClass $data
     * @param array $files
     * @return array $errors
     */
    public function validation($data, $files): array {
        $errors = array();

        return $errors;
    }
}
