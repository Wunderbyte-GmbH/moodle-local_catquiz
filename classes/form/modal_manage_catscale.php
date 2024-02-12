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

use context;
use context_system;
use core_form\dynamic_form;
use local_catquiz\data\dataapi;
use local_catquiz\data\catscale_structure;
use moodle_url;
use stdClass;

/**
 * Dynamic form.
 * @copyright Wunderbyte GmbH <info@wunderbyte.at>
 * @package   local_catquiz
 * @author    David Bogner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class modal_manage_catscale extends dynamic_form {

    /**
     * {@inheritdoc}
     * @see moodleform::definition()
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'create_catscale', get_string('createnewcatscale', 'local_catquiz'));

        // Add a text field for the catscale name.
        $mform->addElement('text', 'name', get_string('name'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', PARAM_ALPHAEXT, 'client');

        // Add a textarea field for the catscale description.
        $mform->addElement('textarea', 'description', get_string('description'), 'wrap="virtual" rows="5" cols="50"');
        $mform->setType('description', PARAM_CLEANHTML);

        // Add a textarea field for the catscale description.
        $mform->addElement('hidden', 'id', '');
        $mform->setType('id', PARAM_INT);

        // Add a select field for the parent ID.
        $parents = ['0' => get_string('none')];
        $catscales = dataapi::get_all_catscales();
        foreach ($catscales as $catscale) {
            $parents[$catscale->id] = $catscale->name;
        }

        $mform->registerNoSubmitButton('btn_changeparentid');
        $buttonargs = ['style' => 'visibility:hidden;'];
        $categoryselect = [
            $mform->createElement('autocomplete', 'parentid', get_string('parent', 'local_catquiz'), $parents),
            $mform->createElement('submit',
                'btn_changeparentid',
                get_string('chooseparent', 'local_catquiz'),
                $buttonargs),
        ];

        $mform->addGroup($categoryselect, 'chooseparent', get_string('chooseparent', 'local_catquiz'), '', false);
        $mform->setType('chooseparent', PARAM_NOTAGS);
        $mform->setType('parentid', PARAM_INT);

        // Add a select field for the context.
        $defaultcontext = get_string('defaultcontext', 'local_catquiz');
        $contextoptions = [0 => $defaultcontext];
        $catcontexts = dataapi::get_all_catcontexts();
        foreach ($catcontexts as $context) {
            $contextoptions[$context->id] = $context->name;
        }
        $mform->addElement('autocomplete', 'contextid', get_string('choosecontextid', 'local_catquiz'), $contextoptions);
        $mform->hideIf('contextid', 'parentid', 'neq', '0'); // Selector only for parent scales.

        $mform->addElement('text', 'catquiz_minscalevalue', get_string('minabilityscalevalue', 'local_catquiz'));

        $mform->addElement('text', 'catquiz_maxscalevalue', get_string('maxabilityscalevalue', 'local_catquiz'));

        $mform->hideIf('catquiz_minscalevalue', 'parentid', 'neq', '0'); // Hide group when not parent.
        $mform->hideIf('catquiz_maxscalevalue', 'parentid', 'neq', '0'); // Hide group when not parent.
        $mform->setType('catquiz_maxscalevalue', PARAM_FLOAT);
        $mform->setDefault('catquiz_maxscalevalue', LOCAL_CATQUIZ_PERSONABILITY_UPPER_LIMIT);
        $mform->addHelpButton('catquiz_maxscalevalue', 'maxabilityscalevalue', 'local_catquiz');
        $mform->setType('catquiz_minscalevalue', PARAM_FLOAT);
        $mform->setDefault('catquiz_minscalevalue', LOCAL_CATQUIZ_PERSONABILITY_LOWER_LIMIT);
        $mform->addHelpButton('catquiz_minscalevalue', 'minabilityscalevalue', 'local_catquiz');

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
        $data->timecreated = time();
        $data->timemodified = time();
        $catscale = new catscale_structure((array) $data);
        if ($data->id > 0) {
            dataapi::update_catscale($catscale);
            $catscaleid = $data->id;
        } else {
            $catscaleid = dataapi::create_catscale($catscale);
        }
        $data->id = $catscaleid;
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
            if (!empty($rec = dataapi::get_catscale_by_id($data->id))) {
                $data = $rec;
                $data->minmaxgroup["catquiz_minscalevalue"] = $rec->minscalevalue;
                $data->minmaxgroup["catquiz_maxscalevalue"] = $rec->maxscalevalue;
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
        if (dataapi::name_exists($data['name']) && $data['id'] === 0) {
            $errors['name'] = get_string('catscalesname_exists', 'local_catquiz');
        }
        if (isset($data["minmaxgroup"]["catquiz_minscalevalue"])) {
            if ($data["minmaxgroup"]["catquiz_minscalevalue"] > $data["minmaxgroup"]["catquiz_maxscalevalue"]) {
                $errors['minmaxgroup'] = get_string('errorminscalevalue', 'local_catquiz');
            }
            if (!is_numeric($data["minmaxgroup"]["catquiz_minscalevalue"])) {
                $errors["minmaxgroup"] = get_string('errorhastobefloat', 'local_catquiz');
            }
            if (!is_numeric($data["minmaxgroup"]["catquiz_maxscalevalue"])) {
                $errors["minmaxgroup"] = get_string('errorhastobefloat', 'local_catquiz');
            }
        }

        return $errors;
    }

    /**
     * {@inheritDoc}
     * @see moodleform::get_data()
     */
    public function get_data() {

        $data = parent::get_data();
        if (!empty($data->minmaxgroup["catquiz_minscalevalue"])) {
            $data->minscalevalue = $data->minmaxgroup["catquiz_minscalevalue"];
            $data->maxscalevalue = $data->minmaxgroup["catquiz_maxscalevalue"];
        }
        return $data;
    }
}
