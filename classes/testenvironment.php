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
 * Entities Class to display list of entity records.
 *
 * @package local_catquiz
 * @author Thomas Winkler
 * @copyright 2021 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use moodle_exception;
use stdClass;

require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * Class testenvironment
 *
 * @author Georg Maißer
 * @copyright 2022 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class testenvironment {

    /**
     * $id
     *
     * @var integer
     */
    public string $id;

    /**
     * $name
     *
     * @var string
     */
    private string $name;

    /**
     * $description
     *
     * @var string
     */
    private string $description;

    /**
     * $description format
     *
     * @var integer
     */
    private int $descriptionformat;

    /**
     * $json
     *
     * @var string
     */
    private string $json;

    /**
     * $component
     *
     * @var string
     */
    private string $component;

    /**
     * @var integer
     */
    private ?int $catscaleid;

    /**
     * $componentid
     *
     * @var integer
     */
    private int $componentid;

    /**
     * $visible
     *
     * @var integer
     */
    private int $visible;

    /**
     * $availability
     *
     * @var string
     */
    private string $availability;

    /**
     * $lang
     *
     * @var string
     */
    private string $lang;

    /**
     * $status
     *
     * @var integer
     */
    private int $status;

    /**
     * $parentid
     *
     * @var integer
     */
    private int $parentid;

    private int $courseid;

    /**
     * Testenvironment constructor.
     * @param stdClass $newrecord
     */
    public function __construct(
        stdClass $newrecord) {

        // If we have just the id, but not the component..
        // We probably just want to fetch the right information.
        if (!$record = $this->get_record(
            $newrecord->id ?? 0,
            $newrecord->componentid ?? 0,
            $newrecord->component ?? '')) {

            // If we don't get a record, we just pass on the new record as record.
            $record = $newrecord;
        } else {
            // If we have found a record in DB, we still want to update our record with the new values.
            foreach ($newrecord as $key => $value) {
                $record->{$key} = $value;
            }
        }

        $this->id = $record->id ?? 0;

        $this->componentid = $record->componentid ?? 0;
        $this->component = $record->component ?? '';
        $this->catscaleid = $record->catscaleid ?? null;
        $this->name = $record->name ?? get_string('newcustomtest', 'local_catquiz');
        $this->description = $record->description ?? '';
        $this->descriptionformat = $record->descriptionformat ?? 1;
        $this->json = $record->json ?? '';
        $this->visible = $record->visible ?? STATUS_TEST_INVISIBLE;
        $this->availability = $record->availability ?? '';
        $this->lang = $record->lang ?? '';
        $this->status = $record->status ?? STATUS_TEST_ACTIVE;
        $this->parentid = $record->parentid ?? 0;
        $this->courseid = $record->courseid ?? 0;
    }

    /**
     * Public function to set the name of the curent testenvironment.
     *
     * @return void
     */
    public function set_name_in_json() {

        // We get the current json.
        $object = json_decode($this->json);

        // Change the current name
        $object->testenvironment_name = $this->name;

        // And set it back as json.
        $this->json = json_encode($object);

    }

    /**
     * Function to update DB record with values from instantiated class.
     *
     * @param string $templatename
     * @return int
     */
    public function save_or_update($templatename = '') {
        global $DB;

        // If we find the exact record, it might still be the case that we want to save a copy of a template.
        if ($record = $this->get_record($this->id, $this->componentid, $this->component)) {

            if (empty($templatename) || ($record->name == $templatename)) {
                $this->update_object($record);
                $DB->update_record('local_catquiz_tests', $record);

                return $this->id;
            }
            // If the name of the record is different, we want to insert a new record.
            unset($record->id);

        } else {
            // Create a new entry in DB.
            $record = new stdClass();
        }

        $this->update_object($record);

        // In case of a templatename, we pass this on.
        if (!empty($templatename)) {
            $record->name = $templatename;
        }

        if ($id = $DB->insert_record('local_catquiz_tests', $record)) {
            $this->id = $id;
        } else {
            throw new moodle_exception('updatetestfailed', 'local_catquiz');
        }

        return $this->id;

    }

    /**
     * Function to save current instance as template.
     *
     * @param int $templateid
     * @param string $templatename
     * @return void
     */
    public function save_as_template(int $templateidid, string $templatename) {

        // We use negative componentids for templates.
        // Therefore, we need to retrieve them.

        // We make this template as a copy of an actual test.
        // So we just change the test for the durartion of the template saving.
        $name = $this->name;
        $componentid = $this->componentid;
        $id = $this->id;

        $this->name = $templatename;
        $this->componentid = 0;
        $this->id = $templateidid;

        $parentid = $this->save_or_update($templatename);

        // After Saving as template, we revert to the original name.
        $this->id = $id;
        $this->name = $name;
        $this->componentid = $componentid;
        $this->parentid = $parentid;
    }

    /**
     * This function will recreate the saved array in order to
     *
     * @param array $formdefaultvalues
     * @return void
     */
    public function apply_jsonsaved_values(array &$formdefaultvalues) {

        if (!$jsonobject = json_decode($this->json, null, 512, JSON_OBJECT_AS_ARRAY)) {
            return;
        }

        foreach($jsonobject as $key => $value) {

            // Never overwrite a few values.
            if (in_array($key, [
                'id',
                'instance',
                'name',
                'coursemodule',
                'module',
                'course',
                'cmidnumber',
                'groupingid',
                'availabilityconditionsjson',
                'completion',
                'completionexpected',
                'add', // Check value.
                'update', // Check value.
                'return', // Check value.
                'sr', // Check value.
                'competencies',
                'competency_rule',
                'override_grade',
                'submitbutton2',
                'completionpassgrade',
                'completiongradeitemnumber',
                'conditiongradegroup',
                'conditionfieldgroup',
                'downloadcontent',
                'timemodified',

                ])) {
                continue;
            }

            $formdefaultvalues[$key] = $value;
        }
    }

    /**
     * Return class properties as array.
     *
     * @return array
     */
    public function return_as_array() {

        $returnarray = [];
        foreach ($this as $key => $value) {
            $returnarray[$key] = $value;
        }

        return $returnarray;
    }

    /**
     * Function to update a $record by the properties of the instantiated class.
     *
     * @param stdClass $recordtoupdate
     * @return void
     */
    private function update_object(stdClass &$record) {

        // If we have the record, we update everything, if there are new values. if not, we leave the old ones.
        $record->componentid = $this->componentid ?? $record->componentid;
        $record->component = $this->component ?? $record->component;
        $record->catscaleid = $this->catscaleid ?? $record->catscaleid;
        $record->name = $this->name ?? $record->name;
        $record->description = $this->description ?? $record->description;
        $record->descriptionformat = $this->descriptionformat ?? $record->descriptionformat;
        $record->json = $this->json ?? $record->json;
        $record->visibile = $this->visible ?? $record->visible;
        $record->availability = $this->availability ?? $record->availability;
        $record->lang = $this->lang ?? $record->lang;
        $record->status = $this->status ?? $record->status;
        $record->parentid = $this->parentid ?? $record->parentid ?? 0;
        $record->courseid = $this->courseid ?? $record->courseid;

        $now = time();

        $record->timemodified = $now;
        $record->timecreated = $record->timecreated ?? $now;
    }

    /**
     * Get test record either by id or by combination of componentid & component.
     *
     * @param integer $id
     * @param integer $componentid
     * @param string $component
     * @return ?stdClass
     */
    private function get_record(int $id = 0, int $componentid = 0, string $component = '') {

        global $DB;

        if (!empty($id)) {
            $params = [
                'id' => $id,
            ];

        } else if (!empty($componentid) && !empty($component)) {
            $params = [
                'componentid' => $componentid,
                'component' => $component,
            ];
        } else {
            return null;
        }

        if (!$record = $DB->get_record('local_catquiz_tests', $params)) {
            return null;
        }
        return $record;
    }

    /**
     * Returns true if test is in force mode
     *
     * @return boolean
     */
    public function status_force():bool {

        return $this->status === STATUS_TEST_FORCE ? true : false;
    }

    /**
     * Returns an array of all or filtered test environments.
     * @param string $component
     * @return array
     */
    public static function get_environments_as_array(
            string $component = 'mod_adaptivequiz',
            bool $onlytemplates = true) {
        global $DB;

        $returnarray = [];

        $params = [
            'component' => $component,
        ];

        if ($onlytemplates) {
            $params['componentid'] = 0;
        }

        if (!$records = $DB->get_records('local_catquiz_tests', $params)) {
            return $returnarray;
        }

        foreach ($records as $record) {
            $returnarray[$record->id] = $record->name;
        }

        return $returnarray;
    }

    /**
     * Delete Testenvironment.
     *
     * @param integer $id
     * @return bool
     */
    public static function delete_testenvironment(int $id) {
        global $DB;

        $returnvalue = false;

        if ($DB->delete_records('local_catquiz_tests', ['id' => $id])) {
            $returnvalue = true;
        }

        return $returnvalue;
    }
}
