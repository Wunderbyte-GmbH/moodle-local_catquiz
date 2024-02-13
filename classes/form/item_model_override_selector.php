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

use context;
use context_system;
use core_form\dynamic_form;
use local_catquiz\catquiz;
use local_catquiz\event\testitemstatus_updated;
use local_catquiz\local\model\model_item_param;
use local_catquiz\local\model\model_item_param_list;
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

        global $CFG, $DB, $PAGE;

        $mform = $this->_form;
        $data = (object) $this->_ajaxformdata;

        if (!empty($data->editing)) {
            if ($data->editing == "false") {
                $data->editing = false;
            } else {
                $data->editing = true;
            }
        }
        $mform->addElement('hidden', 'testitemid');
        $mform->setType('testitemid', PARAM_INT);
        $mform->addElement('hidden', 'contextid');
        $mform->setType('contextid', PARAM_INT);
        $mform->addElement('hidden', 'componentname');
        $mform->setType('componentname', PARAM_TEXT);
        $mform->addElement('hidden', 'editing', $data->editing ?? false);
        $mform->setType('editing', PARAM_BOOL);

        $models = model_strategy::get_installed_models();

        $options = [
            LOCAL_CATQUIZ_STATUS_NOT_CALCULATED => get_string('itemstatus_0', 'local_catquiz'),
            LOCAL_CATQUIZ_STATUS_CALCULATED => get_string('itemstatus_1', 'local_catquiz'),
            LOCAL_CATQUIZ_STATUS_EXCLUDED_MANUALLY => get_string('itemstatus_-5', 'local_catquiz'),
            LOCAL_CATQUIZ_STATUS_UPDATED_MANUALLY => get_string('itemstatus_4', 'local_catquiz'),
            LOCAL_CATQUIZ_STATUS_CONFIRMED_MANUALLY => get_string('itemstatus_5', 'local_catquiz'),
        ];

        foreach (array_keys($models) as $model) {
            $paramnames = $models[$model]::get_parameter_names();
            $group = [];
            $id = sprintf('override_%s', $model);
            if (!empty($data->editing)) {
                $select = $mform->createElement('select', sprintf('%s_select', $id), $model, $options, ['multiple' => false]);
            } else {
                $select = $mform->createElement('static', sprintf('%s_select', $id), $model);
            }

            $group[] = $select;
            foreach ($paramnames as $paramname) {
                $this->add_element_to_group($paramname, $id, $group, $mform, $data->editing ?? false);
            }
            $mform->addGroup($group, $id, get_string('pluginname', sprintf('catmodel_%s', $model)),
            );
        }

        if (!empty($data->editing)) {
            $mform->registerNoSubmitButton('noedititemparams');
            $mform->addElement('submit', 'noedititemparams', get_string('noedit', 'local_catquiz'),
             ['data-action' => 'edititemparams']);

            $this->add_action_buttons(false);
        } else {
            $mform->registerNoSubmitButton('edititemparams');
            $mform->addElement('submit', 'edititemparams', get_string('edit'),
             ['data-action' => 'edititemparams']);
        }

        $mform->disable_form_change_checker();
    }

    /**
     * Add element to group.
     *
     * @param string $name
     * @param string $id
     * @param array $group
     * @param mixed $mform
     * @param bool $editing
     *
     * @return mixed
     *
     */
    private function add_element_to_group(string $name, string $id, array &$group, &$mform, bool $editing = false) {

        $type = ($editing) ? 'text' : 'static';

        $label = $mform->createElement('static', sprintf('%s_%slabel', $id, $name), 'mylabel', '');
        $value = $mform->createElement($type, sprintf('%s_%s', $id, $name), 'mylabel', '');

        if ($editing) {
            $value->setType(sprintf('%s_%s', $id, $name), PARAM_FLOAT);
        };
        $group[] = $label;
        $group[] = $value;
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
        global $DB;
        $data = $this->get_data();
        if (!empty($data->editing)) {
            if ($data->editing == "false") {
                $data->editing = false;
            } else {
                $data->editing = true;
            }
        }

        // Set data for each model in array.
        $formitemparams = [];
        $models = model_strategy::get_installed_models();
        foreach (array_keys($models) as $model) {
            $modelparams = $models[$model]::get_parameter_names();
            $fieldname = sprintf('override_%s', $model);
            $obj = new stdClass;
            $statusstring = sprintf('%s_select', $fieldname);
            $array = $data->$fieldname;
            $obj->status = $array[$statusstring];
            foreach (array_values($modelparams) as $modelparam) {
                $this->generate_model_fields($modelparam, $fieldname, $obj, $data);
            }
            $formitemparams[$model] = $obj;
        }
        $allformitems = $formitemparams;
        // Fetch record from db.
        $saveditemparams = $this->get_item_params(
            $data->testitemid,
            $data->contextid
        );

        $toupdate = [];
        $toinsert = [];
        foreach (array_keys($models) as $model) {
            // Check if model already exists in db.
            if (isset($saveditemparams[$model])) {
                // Check for each model if there is a change.
                foreach ($formitemparams[$model] as $key => $value) {
                    if ( isset($saveditemparams[$model]) &&
                        (property_exists($saveditemparams[$model], $key)
                        && $saveditemparams[$model]->$key == $value)) {
                        // If nothing is changed, we unset the values.
                        unset($formitemparams[$model]->$key);
                    }
                    // Exisiting identical models need no further treatment.
                    if (count((array)$formitemparams[$model]) == 0) {
                        unset($formitemparams[$model]);
                        continue;
                    }
                }
            }
            if (!isset($formitemparams[$model])) {
                continue;
            }
            // If status is unchanged (and therefore deleted from the array)...
            // ...change must be within values, so we set the new status to manually updated.
            if (!isset($formitemparams[$model]->status)) {
                $formitemparams[$model]->status = LOCAL_CATQUIZ_STATUS_UPDATED_MANUALLY;
            }

            // If the model exists already in the db, we proceed with updating.
            if (array_key_exists($model, $saveditemparams)) {
                $update = [];
                $update['id'] = $saveditemparams[$model]->id;
                $this->update_item('status', $update, $formitemparams, $model);
                $this->update_item('difficulty', $update, $formitemparams, $model);
                $this->update_item('discrimination', $update, $formitemparams, $model);
                $this->update_item('guessing', $update, $formitemparams, $model);
                $toupdate[] = $update;
                $status = $formitemparams[$model]->status;
            } else {
                foreach ($formitemparams[$model] as $key => $value) {
                    // If all param fields are empty, no insert into db except for status manually excluded.
                    if ($value === ""
                        || ($key == "status" && $value != LOCAL_CATQUIZ_STATUS_EXCLUDED_MANUALLY)) {
                        $empty = true;
                    } else {
                        $empty = false;
                    }
                }
                if ($empty) {
                    continue;
                }
                $status = ($formitemparams[$model]->status == LOCAL_CATQUIZ_STATUS_NOT_CALCULATED)
                    ? LOCAL_CATQUIZ_STATUS_CONFIRMED_MANUALLY : $formitemparams[$model]->status;
                // If it's new, we prepare the insert.
                $toinsert[] = [
                    'status' => $status,
                    'model' => $model,
                    'difficulty' => $formitemparams[$model]->difficulty ?? "",
                    'discrimination' => $formitemparams[$model]->discrimination ?? "",
                    'guessing' => $formitemparams[$model]->guessing ?? "",
                ];
            }

            // There can only be one model with this status, so we have to make
            // sure all other models that have this status are set back to 0.
            if (intval($status) === LOCAL_CATQUIZ_STATUS_CONFIRMED_MANUALLY) {
                foreach (array_keys($models) as $m) {
                    if ($m === $model) {
                        // Do not check our current model.
                        continue;
                    }
                    if (intval($allformitems[$m]->status) !== LOCAL_CATQUIZ_STATUS_CONFIRMED_MANUALLY) {
                        // Ignore models with other status.
                        continue;
                    }
                    // Reset back to 0.
                    $defaultstatus = strval(LOCAL_CATQUIZ_STATUS_NOT_CALCULATED);
                    $allformitems[$m]->status = $defaultstatus;
                    $fieldname = sprintf('override_%s', $m);
                    $string = sprintf('%s_select', $fieldname);
                    $data->$fieldname[$string] = $defaultstatus;
                    $this->set_data($data);
                    $toupdate[] = [
                        'status' => $allformitems[$m]->status,
                        'id' => $saveditemparams[$m]->id,
                        'timemodified' => time(),
                    ];
                }
            }
        }

        foreach ($toupdate as $updated) {
            $DB->update_record(
                'local_catquiz_itemparams',
                (object) $updated
            );
            // Trigger status changed event.
            $event = testitemstatus_updated::create([
            'objectid' => $updated['id'],
            'context' => context_system::instance(),
            'other' => [
                'status' => $updated['status'],
                'testitemid' => $updated['id'],
            ],
            ]);
            $event->trigger();
        }

        foreach ($toinsert as $new) {
            $new['componentid'] = $data->testitemid;
            $new['contextid'] = $data->contextid;
            $new['componentname'] = $data->componentname ?: self::DEFAULT_COMPONENT_NAME;
            $new['timecreated'] = time();
            $new['timemodified'] = time();
            $DB->insert_record(
                'local_catquiz_itemparams',
                (object) $new
            );

            // Trigger status changed event.
            $event = testitemstatus_updated::create([
            'objectid' => $new['id'],
            'context' => context_system::instance(),
            'other' => [
                'status' => $new['status'],
                'testitemid' => $new['id'],
            ],
            ]);
            $event->trigger();
        }

        return $data;
    }
    /**
     * Create fields corresponding to model.
     *
     * @param string $paramname
     * @param string $fieldname
     * @param stdClass $obj
     * @param stdClass $data
     * @return void
     */
    private function generate_model_fields(string $paramname, string $fieldname, stdClass &$obj, stdClass $data) {
        $param = sprintf('%s_'.$paramname, $fieldname);
        $array = $data->$fieldname;
        $obj->$paramname = $array[$param];
    }
    /**
     * Copy changed values = existing params.
     *
     * @param string $key
     * @param array $update
     * @param array $formitemparams
     * @param string $model
     *
     * @return void
     */
    private function update_item(string $key, array &$update, array $formitemparams, string $model):void {
        if (isset($formitemparams[$model]->$key)) {
            $update[$key] = $formitemparams[$model]->$key;
        }
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
        $models = model_strategy::get_installed_models();

        if (!empty($data->editing)) {
            if ($data->editing == "false") {
                $data->editing = false;
            } else {
                $data->editing = true;
            }
        }

        if (empty($data->contextid)) {
            $data->contextid = required_param('contextid', PARAM_INT);
        }
        if (empty($data->testitemid)) {
            $data->testitemid = required_param('id', PARAM_INT);
        }
        // Get data from db.
        $itemparamsbymodel = $this->get_item_params($data->testitemid, $data->contextid);
        foreach (array_keys($models) as $model) {
            $field = sprintf('override_%s', $model);
            $specificmodelparams = $models[$model]::get_parameter_names();
            $values = [];
            if (array_key_exists($model, $itemparamsbymodel)) {
                $modelparams = $itemparamsbymodel[$model];
                $modelstatus = $modelparams->status;
                foreach ($specificmodelparams as $givenparam) {
                    if (isset ($modelparams->$givenparam)) {
                        $values[$givenparam] = $modelparams->$givenparam;
                    }
                }
                if (empty($data->componentname)) {
                    $data->componentname = $modelparams->componentname;
                }
            } else { // Set default data if there are no calculated data for the given model.
                $modelstatus = LOCAL_CATQUIZ_STATUS_NOT_CALCULATED;
                // Initial load.
                foreach ($specificmodelparams as $givenparam) {
                        $values[$givenparam] = null;
                }
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
                $dataarray = [
                    sprintf('%s_select', $field) => $statusint,
                    sprintf('%s_status', $field) => $modelstatus,
                ];
            }

            foreach ($values as $name => $value) {
                if ($name === 'difficulty') {
                    $dataarray[sprintf('%s_difficultylabel', $field)] = get_string('itemdifficulty', 'local_catquiz') . ":";
                    $dataarray[sprintf('%s_difficulty', $field)] = $value;
                    continue;
                } else if ($name === 'guessing') {
                    $dataarray[sprintf('%s_guessinglabel', $field)] = get_string('guessing', 'local_catquiz') . ":";
                    $dataarray[sprintf('%s_guessing', $field)] = $value;
                    continue;
                } else if ($name === 'discrimination') {
                    $dataarray[sprintf('%s_discriminationlabel', $field)] = get_string('discrimination', 'local_catquiz') . ":";
                    $dataarray[sprintf('%s_discrimination', $field)] = $value;
                    continue;
                }
            }

            $data->$field = $dataarray;
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
     * @param stdClass $data
     * @param array $files
     * @return array $errors
     */
    public function validation($data, $files): array {
        $errors = [];

        $models = model_strategy::get_installed_models();
        $counter = [];
        foreach ($models as $modelname => $location) {
            $modelparams = $location::get_parameter_names();
            $field = sprintf('override_%s', $modelname);
            $selectkey = sprintf('%s_select', $field);

            // Values can not be empty with certain status.
            if ($data[$field][$selectkey] == LOCAL_CATQUIZ_STATUS_CALCULATED
                || $data[$field][$selectkey] == LOCAL_CATQUIZ_STATUS_UPDATED_MANUALLY
                || $data[$field][$selectkey] == LOCAL_CATQUIZ_STATUS_CONFIRMED_MANUALLY) {

                $empty = true;
                foreach ($data[$field] as $key => $value) {
                    foreach (array_values($modelparams) as $param) {
                        if (strpos($key, $param) == false || $value == "") {
                            continue;
                        }
                        $empty = false;
                    }
                }
                if ($empty) {
                    $errors[$field] = get_string("validateform:changevaluesorstatus", 'local_catquiz');
                }
            }
            // There can only be 1 status confirmed manually.
            if ($data[$field][$selectkey] == LOCAL_CATQUIZ_STATUS_CONFIRMED_MANUALLY) {
                $counter[$field] = $selectkey;
            }
        }
        if (count($counter) > 1) {
            foreach ($counter as $field => $selectkey) {
                $errors[$field] = get_string("validateform:onlyoneconfirmedstatusallowed", 'local_catquiz');
            }
        }
        return $errors;
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
        global $DB;

        list($sql, $params) = catquiz::get_sql_for_item_params(
            $testitemid,
            $contextid
        );
        $itemparams = $DB->get_records_sql($sql, $params);
        $itemparamsbymodel = [];
        foreach ($itemparams as $itemparam) {
            $itemparamsbymodel[$itemparam->model] = $itemparam;
        }
        return $itemparamsbymodel;
    }
}
