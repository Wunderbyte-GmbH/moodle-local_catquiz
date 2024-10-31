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
use dml_exception;
use local_catquiz\catquiz;
use local_catquiz\event\testitemstatus_updated;
use local_catquiz\local\model\model_item_param;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_strategy;
use moodle_url;
use MoodleQuickForm;
use stdClass;

/**
 * Dynamic form.
 * @copyright Wunderbyte GmbH <info@wunderbyte.at>
 * @package   local_catquiz
 * @author    Georg Maißer
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class item_model_override_selector extends dynamic_form {

    /**
     * DEFAULT_COMPONENT_NAME
     *
     * @var string
     */
    const DEFAULT_COMPONENT_NAME = 'question';

    /**
     * {@inheritdoc}
     * @see moodleform::definition()
     */
    public function definition() {

        $mform = $this->_form;
        $data = (object) $this->_ajaxformdata;
        $editmode = !empty($data->editing) && $data->editing != "false";

        // Set only the most basic fields.
        // Most of the form is rendered in the definition_after_data() methods.

        $mform->addElement('hidden', 'testitemid');
        $mform->setType('testitemid', PARAM_INT);
        $mform->addElement('hidden', 'contextid');
        $mform->setType('contextid', PARAM_INT);
        $mform->addElement('hidden', 'componentname');
        $mform->setType('componentname', PARAM_TEXT);
        $mform->addElement('hidden', 'editing', $editmode);
        $mform->setType('editing', PARAM_BOOL);
        $mform->registerNoSubmitButton('edititemparams');
        $mform->registerNoSubmitButton('noedititemparams');
        $mform->registerNoSubmitButton('additemparams');
        // Hidden field to store temporary fields data for new params.
        $mform->addElement('hidden', 'temporaryfields', '[]');
        $mform->setType('temporaryfields', PARAM_RAW);
        // Hidden field to store temporary fields data for deleted params.
        $mform->addElement('hidden', 'deletedparams', '[]');
        $mform->setType('deletedparams', PARAM_RAW);
    }

    /**
     * Set up the form depending on current values.
     */
    public function definition_after_data() {
        $form = $this->_form;
        $data = (object) $this->_ajaxformdata;
        $editmode = !empty($data->editing) && $data->editing != "false";
        if ($editmode) {
            $this->render_edit_form($this->_form, (object) []);
            return;
        }

        $item = $form->_defaultValues['item'];
        foreach ($form->_defaultValues['itemparams'] as $model => $param) {
            $class = "itemparam";
            if ($param->get_id() === intval($item->activeparamid)) {
                $class .= " activeparam";
            }
            $form->addElement('html', '<div class="'.$class.'">');
            $form->addElement('html', '<h3>'.get_string('pluginname', sprintf('catmodel_%s', $param->get_model_name())).'</h3>');
            $statusstring = get_string(sprintf('itemstatus_%d', $param->get_status()), 'local_catquiz');
            $form->addElement(
                'html',
                sprintf(
                    '<div><span class="label status">%s: : </span><span class="value">%s</span></div>',
                    get_string('status', 'core'),
                    $statusstring
                )
            );
            foreach ($param->get_static_param_array($param) as $key => $val) {
                $paramfield = sprintf('<div><span class="label">%s</span>: <span class="value">%s</span></div>', $key, $val);
                $form->addElement('html', $paramfield);
            }
            $form->addElement('html', '</div>');
        }

        $this->_form->registerNoSubmitButton('edititemparams');
        $this->_form->addElement('submit', 'edititemparams', get_string('edit'),
            ['data-action' => 'edititemparams']);
        $this->_form->disable_form_change_checker();
    }

    /**
     * Adds form fields to edit the item parameters for the given question.
     *
     * The form is passed via reference.
     *
     * @param MoodleQuickForm $mform
     * @param stdClass $data
     * @return void
     */
    private function render_edit_form(MoodleQuickForm &$mform, stdClass $data): void {
        $models = model_strategy::get_installed_models();
        $options = [
            LOCAL_CATQUIZ_STATUS_NOT_CALCULATED => get_string('itemstatus_0', 'local_catquiz'),
            LOCAL_CATQUIZ_STATUS_CALCULATED => get_string('itemstatus_1', 'local_catquiz'),
            LOCAL_CATQUIZ_STATUS_EXCLUDED_MANUALLY => get_string('itemstatus_-5', 'local_catquiz'),
            LOCAL_CATQUIZ_STATUS_UPDATED_MANUALLY => get_string('itemstatus_4', 'local_catquiz'),
            LOCAL_CATQUIZ_STATUS_CONFIRMED_MANUALLY => get_string('itemstatus_5', 'local_catquiz'),
        ];

        $selectactive = $mform->createElement(
            'select',
            'active_model',
            get_string('activemodel', 'local_catquiz'),
            array_combine(
                array_keys($models), array_map(fn($m) => get_string('pluginname', sprintf('catmodel_%s', $m)), array_keys($models))
            ),
            ['multiple' => false]
        );
        $mform->addElement($selectactive);

        foreach ($mform->_defaultValues['itemparams'] as $model => $param) {
            $id = sprintf('override_%s', $model);
            $select = $mform->createElement(
                'select',
                sprintf('%s_select', $id),
                get_string('pluginname', sprintf('catmodel_%s', $model)),
                $options,
                ['multiple' => false]
            );
            $mform->addElement($select);

            // Add the model specific parametrs was $id group.
            $param->add_form_fields($mform, $id);

            $mform->hideIf($id, sprintf('override_%s_select', $model), 'in', [
                LOCAL_CATQUIZ_STATUS_NOT_CALCULATED,
                LOCAL_CATQUIZ_STATUS_EXCLUDED_MANUALLY,
            ]);
            $mform->disabledIf($id, sprintf('override_%s_select', $model), 'eq', LOCAL_CATQUIZ_STATUS_CALCULATED);
        }

        $mform->registerNoSubmitButton('noedititemparams');
        $mform->addElement('submit', 'noedititemparams', get_string('noedit', 'local_catquiz'),
            ['data-action' => 'edititemparams']);
        $this->add_action_buttons(false);
        $mform->disable_form_change_checker();
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
     * Here we get the values set in the form.
     *
     * @return object
     */
    public function process_dynamic_submission(): object {
        $data = $this->get_data();

        if (!empty($data->editing)) {
            if ($data->editing == "false") {
                $data->editing = false;
            } else {
                $data->editing = true;
            }
        }

        $selectedmodel = $data->active_model;

        // Set data for each model in array.
        $formitemparams = [];
        foreach ($this->_form->_defaultValues['itemparams'] as $model => $param) {
            $fieldname = sprintf('override_%s', $model);
            $rec = $param->form_array_to_record($data->$fieldname);
            $statusstring = sprintf('%s_select', $fieldname);
            $rec->status = $data->$statusstring;
            $rec->componentid = $data->testitemid;
            $rec->model = $model;
            $item = $this->_form->_defaultValues['item'];
            $rec->itemid = $item->id;
            $rec->contextid = $item->contextid;
            $rec->componentname = $item->componentname;
            if ($param->get_id()) {
                $defaultobj = $param->to_record();
                $rec->id = $param->get_id();
                $rec->timecreated = $defaultobj->timecreated;
            }
            $formitemparams[$model] = model_item_param::from_record($rec);
        }

         // Handle adding new param fields.
        if (!empty($data->temporaryfields)) {
            $tempfields = json_decode($data->temporaryfields);
            foreach ($tempfields as $model => $values) {
                $param = $formitemparams[$model];
                foreach ($values as $newparam) {
                    $param->add_new_param($newparam);
                }
                $param->save();
            }
        }

         // Handle deleting param fields.
        if (!empty($data->deletedparams)) {
            /* Deletedparams will be converted to an array with the following structure:
             * [
             *   {
             *     model: "grm",
             *     index: "3"
             *   }, ...
             * ]
             */
            $deletedparams = json_decode($data->deletedparams);
            foreach ($deletedparams as $paramfield) {
                $model = $paramfield->model;
                $index = $paramfield->index;
                $param = $formitemparams[$model];
                $param->drop_field_at($index);
                $param->save();
            }
        }

        foreach ($formitemparams as $model => $param) {
            // If the parameter was not changed, skip it.
            $defaultparam = $this->_form->_defaultValues['itemparams']->offsetGet($model);
            if ($param->get_params_array() == $defaultparam->get_params_array()
                && $param->get_status() == $defaultparam->get_status()
            ) {
                if ($model == $selectedmodel) {
                    $this->update_item_activeparam($param);
                }
                continue;
            }
            $param->save();
            if ($param->get_status() !== $defaultparam->get_status()) {
                $this->trigger_status_updated_event($param);
            }

            if ($model == $selectedmodel) {
                $this->update_item_activeparam($param);
            }
        }
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
     * @param int $contextid
     *
     * @return void
     *
     */
    public function set_data_for_dynamic_submission($contextid = 0): void {

        $data = (object) $this->_ajaxformdata;
        $models = model_strategy::get_installed_models();

        if (!empty($data->editing)) {
            if ($data->editing == "false") {
                $data->editing = false;
            } else {
                $data->editing = true;
            }
        }

        if (empty($data->contextid)) {
            $data->contextid = $contextid ?? required_param('contextid', PARAM_INT);
        }
        if (empty($data->testitemid)) {
            $data->testitemid = required_param('id', PARAM_INT);
        }
        if (empty($data->componentname)) {
            $data->componentname = optional_param('component', 'question', PARAM_TEXT);
        }
        // Get data from db.
        $itemparamsbymodel = $this->get_item_params($data->testitemid, $data->contextid);
        foreach (array_keys($models) as $model) {
            $field = sprintf('override_%s', $model);
            $modelparams = [];
            if ($itemparamsbymodel->offsetExists($model)) {
                $itemparam = $itemparamsbymodel->offsetGet($model);
                $modelparams = $itemparam->get_params_array();
                $modelstatus = $itemparam->get_status();
            } else { // Set default data if there are no calculated data for the given model.
                $modelstatus = LOCAL_CATQUIZ_STATUS_NOT_CALCULATED;
            }
            // We make a difference between the status as int and status as localized string.
            $statusint = $modelstatus;
            $dataarray = [];
            // In editing mode we want to display a string for status.
            if (empty($data->editing)) {
                if (isset($modelparams->model)
                    && $modelparams->model == $model
                    && isset($modelparams->status)) {
                    $status = $modelparams->status;
                } else {
                    $status = $statusint;
                }
                // For editing mode, status needs to be the int, not the string.
                $string = sprintf('itemstatus_%s', $status);
                $modelstatus = get_string($string, 'local_catquiz');
                $dataarray = [
                    sprintf('%s_select', $field) => $modelstatus,
                    sprintf('%s_status', $field) => $modelstatus,
                ];
            } else {
                // In display mode we need the integer value for the select.
                if (is_numeric($modelstatus)) {
                    $string = sprintf('itemstatus_%s', $statusint);
                    $modelstatus = get_string($string, 'local_catquiz');
                }
                $modelselectname = sprintf('%s_select', $field);
                $data->$modelselectname = $statusint;
                $dataarray = [
                    sprintf('%s_status', $field) => $modelstatus,
                ];
            }

            foreach ($modelparams as $name => $value) {
                if (is_array($value)) {
                    $value = $this->param_array_to_string($value);
                }
                $dataarray[sprintf('%s_%slabel', $field, $name)] = get_string($name, 'local_catquiz') . ":";
                $dataarray[sprintf('%s_%s', $field, $name)] = $value;
            }

            $data->$field = $dataarray;
        }
        if (!isset($data->itemid)) {
            $data->item = catquiz::get_item($data->contextid, $data->testitemid, $data->componentname);
        }
        $data->active_model = null;
        foreach (array_keys($models) as $model) {
            $field = sprintf('override_%s', $model);
            if (!$param = $itemparamsbymodel->offsetGet($model) ?? null) {
                $param = new model_item_param($data->testitemid, $model);
                $param->set_default_parameters();
                $itemparamsbymodel->add($param, true);
            }
            $data->$field = array_merge($data->$field, $param->get_parameter_fields());
            $selectfield = sprintf('%s_select', $field);
            $data->$selectfield = $param->get_status();
            if ($param->get_id() == $data->item->activeparamid) {
                $data->active_model = $param->get_model_name();
            }
        }
        $data->itemparams = $itemparamsbymodel;
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
     * Get item params.
     *
     * @param mixed $testitemid
     * @param mixed $contextid
     *
     * @return array
     *
     */
    private function get_item_params($testitemid, $contextid) {
        return model_item_param_list::get_by_questionid($contextid, $testitemid);
    }

    /**
     * Convert the params array to a string
     *
     * @param array $params
     * @return string
     */
    private function param_array_to_string(array $params) {
        $symbol = ': ';
        $glue = '; ';
        return implode(
            $glue,
            array_map(
                fn ($k, $v) => $k . $symbol . $v,
                array_keys($params),
                array_values($params)
            )
        );
    }

    /**
     * Set the given parameter as the active item paramter for the item of the form.
     *
     * @param mixed $param
     * @return void
     * @throws dml_exception
     */
    private function update_item_activeparam($param) {
        $item = $this->_form->_defaultValues['item'];
        if ($param->get_id() && $item->activeparamid != $param->get_id()) {
            $item->activeparamid = $param->get_id();
            catquiz::update_item($item);
        }
    }

    /**
     * Trigger an event that the itemparam status changed
     *
     * @param model_item_param $param
     * @return void
     */
    private function trigger_status_updated_event(model_item_param $param): void {
        $event = testitemstatus_updated::create([
            'objectid' => $param->get_id(),
            'context' => context_system::instance(),
            'other' => [
                'status' => $param->get_status(),
                'testitemid' => $param->get_id(),
            ],
        ]);
        $event->trigger();
        cache_helper::purge_by_event('changesintestitems');
    }
}
