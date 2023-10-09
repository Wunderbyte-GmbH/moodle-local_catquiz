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
        $mform->addElement('hidden', 'testitemid');
        $mform->setType('testitemid', PARAM_INT);
        $mform->addElement('hidden', 'contextid');
        $mform->setType('contextid', PARAM_INT);
        $mform->addElement('hidden', 'componentname');
        $mform->setType('componentname', PARAM_TEXT);

        $models = model_strategy::get_installed_models();

        $options = [
            STATUS_EXCLUDED_MANUALLY => get_string('itemstatus_-5', 'local_catquiz'),
            STATUS_CALCULATED => get_string('itemstatus_1', 'local_catquiz'),
            STATUS_CONFIRMED_MANUALLY => get_string('itemstatus_5', 'local_catquiz'),
            STATUS_UPDATED_MANUALLY => get_string('itemstatus_4', 'local_catquiz'),
            STATUS_NOT_CALCULATED => get_string('itemstatus_0', 'local_catquiz'),
        ];

        foreach (array_keys($models) as $model) {
            $group = [];
            $id = sprintf('override_%s', $model);
            $select = $mform->createElement('select', sprintf('%s_select', $id), $model, $options, ['multiple' => false]);
            $difficulty = $mform->createElement('static', sprintf('%s_difficulty', $id), 'mylabel', 'static text');
            $group[] = $select;
            $group[] = $difficulty;
            $mform->addGroup($group, $id, get_string('pluginname', sprintf('catmodel_%s', $model)),
            );
        }
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
     * Submission data can be accessed as: $this->get_data()
     *
     * @return object
     */
    public function process_dynamic_submission(): object {
        global $DB;
        $data = $this->get_data();

        $formitemparams = [];
        $models = model_strategy::get_installed_models();
        foreach (array_keys($models) as $model) {
            $fieldname = sprintf('override_%s', $model);
            $obj = new stdClass;
            $obj->status = $data->$fieldname[sprintf('%s_select', $fieldname)];
            $formitemparams[$model] = $obj;
        }

        $saveditemparams = $this->get_item_params(
            $data->testitemid,
            $data->contextid
        );

        $toupdate = [];
        $toinsert = [];
        foreach (array_keys($models) as $model) {
            if ($formitemparams[$model]->status === $saveditemparams[$model]->status) {
                // Status did not change: nothing to do.
                continue;
            }

            if (intval($formitemparams[$model]->status) === STATUS_CALCULATED) {
                continue;
            }

            if (array_key_exists($model, $saveditemparams)) {
                $toupdate[] = [
                    'status' => $formitemparams[$model]->status,
                    'id' => $saveditemparams[$model]->id,
                ];
            } else {
                // If this item did not exist in the first place and the item status is set to "not calculated", don't do anything.
                if (
                    !array_key_exists($model, $saveditemparams)
                    && intval($formitemparams[$model]->status) === STATUS_NOT_CALCULATED) {
                    continue;
                }
                $toinsert[] = [
                    'status' => $formitemparams[$model]->status,
                    'model' => $model,
                ];
            }

            // There can only be one model with this status, so we have to make
            // sure all other models that have this status are set back to 0.
            if (intval($formitemparams[$model]->status) === STATUS_CONFIRMED_MANUALLY) {
                foreach (array_keys($models) as $m) {
                    if ($m === $model) {
                        // Do not check our current model.
                        continue;
                    }
                    if (intval($formitemparams[$m]->status) !== STATUS_CONFIRMED_MANUALLY) {
                        // Ignore models with other status.
                        continue;
                    }
                    // Reset back to 0.
                    $defaultstatus = strval(STATUS_NOT_CALCULATED);
                    $formitemparams[$m]->status = $defaultstatus;
                    $fieldname = sprintf('override_%s', $m);
                    $data->{$fieldname[sprintf('%s_select', $fieldname)]} = $defaultstatus;
                    $this->set_data($data);
                    $toupdate[] = [
                        'status' => $formitemparams[$m]->status,
                        'id' => $saveditemparams[$m]->id,
                    ];
                }
            }
        }

        foreach ($toupdate as $updated) {
            $DB->update_record(
                'local_catquiz_itemparams',
                (object) $updated
            );
            // Trigger status changed event
            $event = testitemstatus_updated::create([
            'objectid' => $updated['id'],
            'context' => \context_system::instance(),
            'other' => [
                'status' => $updated['status'],
                'testitemid' => $updated['id'],
                // More information needed to display link in event_log_table
                // 'catscaleid' => ,
                // 'context' => ,
                // 'component' => ,
            ]
            ]);
            $event->trigger();
        }

        foreach ($toinsert as $new) {
            $new['componentid'] = $data->testitemid;
            $new['contextid'] = $data->contextid;
            $new['componentname'] = $data->componentname ?: self::DEFAULT_COMPONENT_NAME;
            $new['timecreated'] = time();
            $DB->insert_record(
                'local_catquiz_itemparams',
                (object) $new
            );

            // Trigger status changed event
            $event = testitemstatus_updated::create([
            'objectid' => $new['id'],
            'context' => \context_system::instance(),
            'other' => [
                'status' => $new['status'],
                'testitemid' => $new['id'],
                // More information needed to display link in event_log_table
                // 'catscaleid' => ,
                // 'context' => ,
                // 'component' => ,
            ]
            ]);
            $event->trigger();
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
     */
    public function set_data_for_dynamic_submission(): void {
        $data = (object) $this->_ajaxformdata;
        $models = model_strategy::get_installed_models();

        if (empty($data->contextid)) {
            $data->contextid = required_param('contextid', PARAM_INT);
        }
        if (empty($data->testitemid)) {
            $data->testitemid = required_param('id', PARAM_INT);
        }

        foreach (array_keys($models) as $model) {
            $field = sprintf('override_%s', $model);
            $itemparamsbymodel = $this->get_item_params($data->testitemid, $data->contextid);
            if (array_key_exists($model, $itemparamsbymodel)) {
                $modelparams = $itemparamsbymodel[$model];
                $modelstatus = $modelparams->status;
                $modeldifficulty = $modelparams->difficulty;
                if (empty($data->componentname)) {
                    $data->componentname = $modelparams->componentname;
                }
            } else { // Set default data if there are no calculated data for the given model.
                $modelstatus = STATUS_NOT_CALCULATED;
                $modeldifficulty = '-';
            }
            $difficultytext = sprintf(
                '%s: %s',
                get_string('itemdifficulty', 'local_catquiz'),
                $modeldifficulty
            );
            $data->$field = [sprintf('%s_select', $field) => $modelstatus, sprintf('%s_difficulty', $field) => $difficultytext];
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
        $errors = array();

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
