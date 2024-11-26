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

use cache;
use cache_helper;
use context_module;
use context_system;
use moodle_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();
define('LOCAL_CATQUIZ_TESTENVIRONMENT_ALL', 0);
define('LOCAL_CATQUIZ_TESTENVIRONMENT_ONLYTEMPLATES', 1);
define('LOCAL_CATQUIZ_TESTENVIRONMENT_NOTEMPLATES', 2);
define('LOCAL_CATQUIZ_TESTENVIRONMENT_ONLYACTIVETEMPLATES', 3);

require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * Class testenvironment.
 *
 * @author Georg Maißer
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class testenvironment {

    /**
     * $id
     *
     * @var int
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
     * @var int
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
     * @var int
     */
    private ?int $catscaleid;

    /**
     * $componentid
     *
     * @var int
     */
    private int $componentid;

    /**
     * $visible
     *
     * @var int
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
     * @var int
     */
    private int $status;

    /**
     * $parentid
     *
     * @var int
     */
    private int $parentid;

    /**
     * $courseid
     *
     * @var int
     */
    private int $courseid;

    /**
     * The context ID that was assigned to the scale when the test was created.
     *
     * @var ?int $contextid
     */
    private ?int $contextid;

    /**
     * Testenvironment constructor.
     * @param stdClass $newrecord
     */
    public function __construct(stdClass $newrecord) {

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
        $this->visible = $record->visible ?? LOCAL_CATQUIZ_STATUS_TEST_INVISIBLE;
        $this->availability = $record->availability ?? '';
        $this->lang = $record->lang ?? '';
        $this->status = $record->status ?? LOCAL_CATQUIZ_STATUS_TEST_ACTIVE;
        $this->parentid = $record->parentid ?? 0;
        $this->courseid = $record->courseid ?? 0;
        $this->contextid = $record->contextid ?? null;
    }

    /**
     * Public function to set the name of the curent testenvironment.
     *
     * @return void
     */
    public function set_name_in_json() {

        // We get the current json.
        $object = json_decode($this->json);

        // Change the current name.
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
                cache_helper::purge_by_event('changesinquizsettings');

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
     * This function will recreate the saved array in order to
     *
     * @param array $formdefaultvalues
     *
     * @return void
     */
    public function apply_jsonsaved_values(array &$formdefaultvalues) {

        if (!$jsonobject = json_decode($this->json, null, 512, JSON_OBJECT_AS_ARRAY)) {
            return;
        }

        $options = [];
        if ($cm = get_coursemodule_from_instance('adaptivequiz', $this->componentid)) {
            $context = context_module::instance($cm->id);
            $options = [
                'trusttext' => true,
                'subdirs' => true,
                'context' => $context,
                'maxfiles' => EDITOR_UNLIMITED_FILES,
                'noclean' => true,
            ];
        }

        if (($formdefaultvalues['triggered_button'] ?? null) === "reloadTestForm") {
            $clearfields = [
                'catquiz_subscalecheckbox_',
            ];
            foreach ($formdefaultvalues as $key => $val) {
                foreach ($clearfields as $field) {
                    if (preg_match("/^$field/", $key)) {
                        unset($formdefaultvalues[$key]);
                    }
                }
            }
        }

        foreach ($jsonobject as $key => $value) {

            // Never overwrite a few values.
            if (
                in_array($key, [
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
                ])
            ) {
                continue;
            }
            if (preg_match('/^feedbackeditor_scaleid_(\d+)_(\d+)$/', $key, $matches)) {
                // If the form is reloaded from a template, set the text from the json.
                if (!$cm && is_string($value) && is_array($formdefaultvalues[$key])) {
                    $formdefaultvalues[$key]['text'] = $value;
                    continue;
                }
                // Fallback for old fields stored as array.
                if (is_array($value) && array_key_exists('text', $value)) {
                    $jsonobject[$key] = $value['text'];
                }

                $scaleid = intval($matches[1]);
                $rangeid = intval($matches[2]);
                $filearea = sprintf('feedback_files_%d_%d', $scaleid, $rangeid);
                $jsonobject[$key . 'format'] = 1;
                $field = sprintf('feedbackeditor_scaleid_%d_%d', $scaleid, $rangeid);
                if ($options) {
                    $data = (object) file_prepare_standard_editor(
                        (object) $jsonobject,
                        $field,
                        $options,
                        $context,
                        'local_catquiz',
                        $filearea,
                        intval($this->componentid)
                    );
                    $formdefaultvalues[$key] = $data->$key;
                    $formdefaultvalues[$key . '_editor'] = $data->{$key . '_editor'};
                    $formdefaultvalues[$key . 'format'] = $data->{$key . 'format'};
                    $draftitemid = file_get_submitted_draft_itemid($field);
                    file_prepare_draft_area(
                        $draftitemid,
                        $context->id,
                        'local_catquiz',
                        $filearea,
                        intval($this->componentid)
                    );
                    continue;
                }
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
     * @param stdClass $record
     *
     * @return [type]
     *
     */
    private function update_object(stdClass &$record) {
        global $DB;

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

        // Set the contextid only if this is a new test OR the scale was changed.
        // New test: $record->contextid is empty. Scale changed: $record->contextid != $this->contextid.
        if (
            !$record->contextid
            || ($this->catscaleid && $record->catscaleid && $this->catscaleid != $record->catscaleid)
        ) {
            $record->contextid = $DB->get_field('local_catquiz_catscales', 'contextid', ['id' => $record->catscaleid]);
        }

        $now = time();

        $record->timemodified = $now;
        $record->timecreated = $record->timecreated ?? $now;
    }

    /**
     * Get test record either by id or by combination of componentid & component.
     *
     * @param int $id
     * @param int $componentid
     * @param string $component
     *
     * @return mixed
     *
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
    public function status_force(): bool {

        return $this->status === LOCAL_CATQUIZ_STATUS_TEST_FORCE ? true : false;
    }

    /**
     * Returns settings saved as JSON.
     *
     * @return stdClass
     */
    public function return_settings(): stdClass {
        return json_decode($this->json);
    }

    /**
     * Returns an array of all or filtered test environments.
     *
     * @param string $component
     * @param int $componentid // Overrides the onlytemplate setting and returns the exact test by id.
     * @param int $onlytemplates
     *
     * @return array
     *
     */
    public static function get_environments_as_array(
            string $component = 'mod_adaptivequiz',
            int $componentid = 0,
            int $onlytemplates = LOCAL_CATQUIZ_TESTENVIRONMENT_ONLYTEMPLATES
            ) {
        global $DB;

        $returnarray = [];

        $records = self::get_environments($component, $componentid, $onlytemplates);

        foreach ($records as $record) {
            $returnarray[$record->id] = $record->name;
        }

        return $returnarray;
    }


    /**
     * Returns all or filtered test environments.
     *
     * @param string $component
     * @param int $componentid // Overrides the onlytemplate setting and returns the exact test by id.
     * @param int $onlytemplates
     * @param bool $includecoursenames
     *
     * @return array
     *
     */
    public static function get_environments(
        string $component = 'mod_adaptivequiz',
        int $componentid = 0,
        int $onlytemplates = LOCAL_CATQUIZ_TESTENVIRONMENT_ONLYTEMPLATES,
        bool $includecoursenames = false) {
        global $DB;

        $returnarray = [];

        $params = [
            'component' => $component,
            'componentid' => $componentid,
        ];
        $equal = '';
        switch ($onlytemplates) {
            case LOCAL_CATQUIZ_TESTENVIRONMENT_ALL:
                break;
            case LOCAL_CATQUIZ_TESTENVIRONMENT_ONLYTEMPLATES:
                $equal = " WHERE componentid = :componentid";
                break;
            case LOCAL_CATQUIZ_TESTENVIRONMENT_NOTEMPLATES:
                $equal = "WHERE componentid <> :componentid";
                break;
            case LOCAL_CATQUIZ_TESTENVIRONMENT_ONLYACTIVETEMPLATES:
                $equal = "WHERE status > 0 AND componentid = :componentid";
                break;
        }

        $sql = "SELECT *
                FROM {local_catquiz_tests}
                $equal";

        if ($includecoursenames) {
            $sql = "SELECT cat.*, c.fullname
                FROM {local_catquiz_tests} cat
                LEFT JOIN {course} c ON c.id = cat.courseid
                $equal";
        }

        if (!$records = $DB->get_records_sql($sql, $params)) {
            return $returnarray;
        } else {
            return $records;
        }
    }

    /**
     * Delete Testenvironment.
     *
     * @param int $id
     *
     * @return bool
     *
     */
    public static function delete_testenvironment(int $id) {
        global $DB;

        $returnvalue = false;

        if ($DB->delete_records('local_catquiz_tests', ['id' => $id])) {
            $returnvalue = true;
        }

        return $returnvalue;
    }

    /**
     * Returns the active scales for the given test id
     *
     * @param int $id The test id
     *
     * @return array
     */
    public static function get_active_scales(int $id): array {
        global $DB;
        $cache = cache::make('local_catquiz', 'cattest_active_scales');
        if ($activescales = $cache->get($id)) {
            return $activescales;
        }

        $record = $DB->get_record('local_catquiz_tests', ['id' => $id], 'json');
        $json = json_decode($record->json);
        $activescales = [];
        foreach ($json as $property => $value) {
            if (!preg_match('/^catquiz_subscalecheckbox_(\d+)/', $property, $matches)) {
                continue;
            }
            // If it is not checked, it is not active.
            if ($value === "0") {
                continue;
            }
            $activescales[] = intval($matches[1]);
        }

        [$insql, $inparams] = $DB->get_in_or_equal(
            $activescales,
            SQL_PARAMS_NAMED,
            'incatscales'
        );
        $cache->set($id, $activescales);
        return $activescales;
    }

    /**
     * Returns the number of items for the given test
     *
     * @param int $id The test id
     * @return int
     */
    public static function get_num_items_for_test(int $id): int {
        global $DB;
        $activescales = self::get_active_scales($id);
        // The cache key consists of the active scales, so that we can re-use the same cache if multiple tests have the same set of
        // active scales. Scales have to be separated so that active scales 1 and 2 are not considered the same as active scale 12.
        // Update: Since the cash key might get too long, we use the hashed value as key.
        $hashedkey = hash('crc32', implode('_', $activescales));
        $cache = cache::make('local_catquiz', 'catscales_num_items');
        if ($numquestions = $cache->get($hashedkey)) {
            return $numquestions;
        }

        [$insql, $inparams] = $DB->get_in_or_equal(
            $activescales,
            SQL_PARAMS_NAMED,
            'incatscales'
        );
        $numquestions = $DB->count_records_select('local_catquiz_items', "catscaleid $insql", $inparams);
        $cache->set($hashedkey, $numquestions);
        return $numquestions;
    }

    /**
     * Retrieves the context ID.
     *
     * @return int|null The context ID or null if not set.
     */
    public function get_contextid() {
        return $this->contextid;
    }
}
