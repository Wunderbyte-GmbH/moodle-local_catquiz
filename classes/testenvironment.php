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
        if (
            !$record = $this->get_record(
                $newrecord->id ?? 0,
                $newrecord->componentid ?? 0,
                $newrecord->component ?? ''
            )
        ) {
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

        // Every save path refuses what no attempt could meet, whatever the pool (issue #60).
        self::assert_consistent($this->json ?? '');

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
     * Whether a test environment is a template: one without an instance of its component (issue #89).
     *
     * @param int $id
     * @return bool
     */
    public static function is_template(int $id): bool {
        global $DB;
        return $id > 0 && $DB->record_exists_select('local_catquiz_tests', 'id = :id AND componentid = 0', ['id' => $id]);
    }

    /**
     * Whether a name is taken by another template (issue #89).
     *
     * The rule: names of templates are unique, compared trimmed and without regard to case. An empty name is
     * always taken - a template needs one.
     *
     * @param string $name
     * @param int $exceptid The template being renamed, which may keep its own name.
     * @return bool
     */
    public static function template_name_taken(string $name, int $exceptid = 0): bool {
        global $DB;
        $wanted = \core_text::strtolower(trim($name));
        if ($wanted === '') {
            return true;
        }
        $records = $DB->get_records_select(
            'local_catquiz_tests',
            'componentid = 0 AND id <> :id',
            ['id' => $exceptid],
            '',
            'id, name'
        );
        foreach ($records as $record) {
            if (\core_text::strtolower(trim((string) $record->name)) === $wanted) {
                return true;
            }
        }
        return false;
    }

    /**
     * Refuses settings with a hard error that does not depend on the item pool (issue #60).
     *
     * The form checks everything, the pool included; a template, an import or a webservice saves before the
     * pool may be filled, and the start of an attempt checks the pool for every path (catquiz_handler::preflight).
     * What depends on the settings alone - minimum above maximum, a time limit without a time - is refused here.
     *
     * @param string $json The settings as stored.
     * @throws moodle_exception settingsinconsistent, with every reason.
     */
    public static function assert_consistent(string $json): void {
        $settings = json_decode($json);
        if (!$settings instanceof stdClass) {
            return;
        }
        $errors = \local_catquiz\local\validation\settings_validator::structural_errors($settings);
        if ($errors) {
            throw new moodle_exception('settingsinconsistent', 'local_catquiz', '', implode(' ', array_column($errors, 'message')));
        }
    }

    /**
     * Overwrites exactly the given template with these settings (issue #89: "Vorlage aktualisieren").
     *
     * The template keeps its id, its status and its creation time; the name is changed only when one is given.
     * Before, a template was updated or copied depending on whether the typed name matched its old one.
     *
     * @param int $templateid
     * @param stdClass $settings The settings as the activity stores them.
     * @param int $catscaleid
     * @param string $name A new name, or '' to keep the old one.
     * @return int The id of the template.
     * @throws moodle_exception When the id is no template or the name is taken.
     */
    public static function update_template(int $templateid, stdClass $settings, int $catscaleid, string $name = ''): int {
        if (!self::is_template($templateid)) {
            throw new moodle_exception('templatenotfound', 'local_catquiz');
        }
        $name = trim($name);
        if ($name !== '' && self::template_name_taken($name, $templateid)) {
            throw new moodle_exception('templatenametaken', 'local_catquiz', '', $name);
        }
        $data = (object) [
            'id' => $templateid,
            'json' => json_encode($settings),
            'catscaleid' => $catscaleid,
        ];
        if ($name !== '') {
            $data->name = $name;
        }
        $test = new self($data);
        $test->save_or_update();
        cache_helper::purge_by_event('changesintestenvironments');
        return $templateid;
    }

    /**
     * Saves these settings as a new template (issue #89: "Als neue Vorlage speichern").
     *
     * The source - the activity, or the template it was loaded from - is not changed. The copy gets its own id,
     * no instance, no course, no parent, the status "active" and its own creation time.
     *
     * @param stdClass $settings
     * @param int $catscaleid
     * @param string $name
     * @param string $component
     * @return int The id of the new template.
     * @throws moodle_exception When the name is empty or taken.
     */
    public static function create_template(
        stdClass $settings,
        int $catscaleid,
        string $name,
        string $component = 'mod_adaptivequiz'
    ): int {
        global $DB;
        $name = trim($name);
        if (self::template_name_taken($name)) {
            throw new moodle_exception('templatenametaken', 'local_catquiz', '', $name);
        }
        self::assert_consistent(json_encode($settings));
        $now = time();
        $id = (int) $DB->insert_record('local_catquiz_tests', (object) [
            'parentid' => 0,
            'componentid' => 0,
            'component' => $component,
            'catscaleid' => $catscaleid,
            'contextid' => $catscaleid
                ? $DB->get_field('local_catquiz_catscales', 'contextid', ['id' => $catscaleid])
                : null,
            'courseid' => 0,
            'name' => $name,
            'description' => '',
            'descriptionformat' => FORMAT_HTML,
            'json' => json_encode($settings),
            'status' => LOCAL_CATQUIZ_STATUS_TEST_ACTIVE,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        cache_helper::purge_by_event('changesintestenvironments');
        return $id;
    }

    /**
     * Copies the files of the written feedback from one place to another (issue #89).
     *
     * The feedback editors of an activity keep their files in the activity's context, item id = instance; a
     * template keeps them in the system context, item id = template id - where the form takes them from when
     * the template is loaded. Saving a template from an activity copies them over, replacing what the
     * template had; the source keeps its files.
     *
     * @param int $fromcontextid
     * @param int $fromitemid
     * @param int $tocontextid
     * @param int $toitemid
     * @return int The number of files copied.
     */
    public static function copy_feedback_files(int $fromcontextid, int $fromitemid, int $tocontextid, int $toitemid): int {
        global $DB;
        $fs = get_file_storage();
        $areasof = fn(int $contextid, int $itemid) => $DB->get_fieldset_sql(
            "SELECT DISTINCT filearea
               FROM {files}
              WHERE contextid = :contextid AND component = 'local_catquiz' AND itemid = :itemid
                AND " . $DB->sql_like('filearea', ':area'),
            ['contextid' => $contextid, 'itemid' => $itemid, 'area' => 'feedback_files_%']
        );
        foreach ($areasof($tocontextid, $toitemid) as $area) {
            $fs->delete_area_files($tocontextid, 'local_catquiz', $area, $toitemid);
        }
        $copied = 0;
        foreach ($areasof($fromcontextid, $fromitemid) as $area) {
            foreach ($fs->get_area_files($fromcontextid, 'local_catquiz', $area, $fromitemid, 'id', false) as $file) {
                $fs->create_file_from_storedfile(['contextid' => $tocontextid, 'itemid' => $toitemid], $file);
                $copied++;
            }
        }
        return $copied;
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
        // The form shows the outer limits of the feedback ranges as the global scale has them now (issue #86).
        $aligned = (object) $jsonobject;
        if (\local_catquiz\local\feedback_limits::align($aligned)) {
            $jsonobject = (array) $aligned;
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
                if (!$cm && is_string($value) && is_array($formdefaultvalues[$key] ?? null)) {
                    $formdefaultvalues[$key]['text'] = $value;
                    continue;
                }
                // Fallback for old fields stored as array.
                if (is_array($value) && array_key_exists('text', $value)) {
                    $jsonobject[$key] = $value['text'];
                }

                /* Issue #51: the files are copied into a draft area when the editor is shown - for the one
                   scale being edited (feedbackclass::editor_with_draft()). Copied here for every editor,
                   a large scale tree made hundreds of draft areas on every load of the settings. Item id
                   0 marks a value whose files are still where they were saved. */
                if ($options) {
                    $text = is_string($jsonobject[$key]) ? $jsonobject[$key] : '';
                    $formdefaultvalues[$key] = $text;
                    $formdefaultvalues[$key . '_editor'] = ['text' => $text, 'format' => FORMAT_HTML, 'itemid' => 0];
                    $formdefaultvalues[$key . 'format'] = FORMAT_HTML;
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
        // Kept before it is overwritten: the contextid below follows a change of the main scale, and
        // the change can only be seen against the value stored before this save (issue #127).
        $previousscaleid = (int) ($record->catscaleid ?? 0);
        $record->catscaleid = $this->catscaleid ?? $record->catscaleid ?? 0;
        $record->name = $this->name ?? $record->name;
        $record->description = $this->description ?? $record->description;
        $record->descriptionformat = $this->descriptionformat ?? $record->descriptionformat;
        $record->json = $this->json ?? $record->json;
        // Issue #89: visible, availability and lang are no columns of local_catquiz_tests ("visibile" was
        // written here, a typo that changed nothing). They are not written.
        $record->status = $this->status ?? $record->status;
        $record->parentid = $this->parentid ?? $record->parentid ?? 0;
        $record->courseid = $this->courseid ?? $record->courseid;

        // Set the contextid only if this is a new test OR the main scale was changed. New test: no
        // contextid yet. Scale changed: the scale now differs from the one stored before this save.
        // The check used to compare the scale with itself - it had been overwritten above - so a
        // change of scale left the test in the context of the old one.
        if (
            !property_exists($record, 'contextid')
            || !$record->contextid
            || ($record->catscaleid && $previousscaleid && (int) $record->catscaleid !== $previousscaleid)
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
     * The declared return type is stdClass, but json_decode() returns null for an
     * empty or malformed json column - which used to surface as a bare TypeError
     * deep inside the call stack ("Return value must be of type stdClass, null
     * returned"), pointing at this method rather than at the real problem: there is
     * no configured test environment for that component/instance. Fail with a
     * message that names the cause instead.
     *
     * @return stdClass
     */
    public function return_settings(): stdClass {
        $settings = json_decode($this->json ?? '');
        if (!$settings instanceof stdClass) {
            throw new moodle_exception(
                'error:notestenvironmentsettings',
                'local_catquiz',
                '',
                (object) [
                    'component' => $this->component ?? '',
                    'componentid' => $this->componentid ?? 0,
                ]
            );
        }
        // The outer limits of the feedback ranges are those of the global scale now (issue #86).
        \local_catquiz\local\feedback_limits::align($settings);

        return $settings;
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
        bool $includecoursenames = false
    ) {
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
        // Issue #52: a stored setting may name a scale that has been deleted since; it is not active.
        if ($activescales) {
            [$existsql, $existparams] = $DB->get_in_or_equal($activescales, SQL_PARAMS_NAMED, 'existscale');
            $existing = $DB->get_fieldset_select('local_catquiz_catscales', 'id', "id $existsql", $existparams);
            $activescales = array_map('intval', $existing);
            sort($activescales);
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
