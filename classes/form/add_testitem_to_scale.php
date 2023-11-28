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
use local_catquiz\catquiz;
use local_catquiz\catscale;
use moodle_url;

/**
 * Dynamic form.
 * @copyright Wunderbyte GmbH <info@wunderbyte.at>
 * @package   local_catquiz
 * @author    Georg Maißer
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_testitem_to_scale extends dynamic_form {

    /**
     * {@inheritdoc}
     * @see moodleform::definition()
     */
    public function definition() {

        $mform = $this->_form;
        $data = (object) $this->_ajaxformdata;

        $catscaleid = $data->catscaleid ?? optional_param('scaleid', 0, PARAM_INT);

        if (isset($catscaleid)) {
            $mform->addElement('hidden', 'catscaleid', $catscaleid);
        }
        if (isset($data->checkedids)) {
            $mform->addElement('hidden', 'checkedids', $data->checkedids);
        }

        $mform->addElement('header', 'addtestitemtitle_catquiz_body', get_string('addtestitembody', 'local_catquiz'));

        $checkedids = explode(",", $data->checkedids);
        $questions = catquiz::get_questions_by_ids($checkedids);
        foreach ($questions as $question) {
            $questiontext = $question->questiontext;
            $mform->addElement('static', 'questiontext_' . $question->id, $question->id, $questiontext);

            // Check if question has an assigned scale and compare to newscale.
            if (catscale::is_assigned_to_parent_scale($catscaleid, $question->id)
            || catscale::is_assigned_to_subscale($catscaleid, $question->id)) {
                $mform->addElement('hidden', 'itemalreadyinotherscale_' . $question->id, $question->id);
            }

        }
        $mform->addElement('advcheckbox', 'validateitemsscaleid', "", 0, ['class' => 'hidden']);
    }

    /**
     * Check access for dynamic submission.
     *
     * @return void
     */
    protected function check_access_for_dynamic_submission(): void {

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

        if ($data['validateitemsscaleid'] != 1) {
            $errors['validateitemsscaleid'] = get_string('pleasecheckorcancel');
        }
        $mform = $this->_form;

        $itemsalreadyassigned = [];
        foreach ($data as $key => $value) {
            if (strpos($key, 'itemalreadyinotherscale') === 0) {
                $itemsalreadyassigned[] = $value;
            }
        }
        if (count($itemsalreadyassigned) > 0) {
            $itemids = implode(', ', $itemsalreadyassigned);
            $messagetext = get_string('moveitemtootherscale', 'local_catquiz', $itemids);
            $mform->addElement('advcheckbox', 'validateitemsscaleid', $messagetext, 0);
        }

        return $errors;
    }
}
