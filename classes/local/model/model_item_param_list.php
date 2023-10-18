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
 * @copyright 2023 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\local\model;
use ArrayAccess;
use ArrayIterator;
use coding_exception;
use Countable;
use ddl_exception;
use dml_exception;
use IteratorAggregate;
use local_catquiz\catcontext;
use local_catquiz\catquiz;
use local_catquiz\catscale;
use local_catquiz\data\catscale_structure;
use local_catquiz\data\dataapi;
use local_catquiz\event\testiteminscale_added;
use moodle_exception;
use stdClass;
use Traversable;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/catquiz/lib.php');
/**
 * This class holds a list of item param objects.
 *
 * This is one of the return values from a model param estimation.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class model_item_param_list implements ArrayAccess, IteratorAggregate, Countable {
    /**
     * @var array<model_item_param>
     */
    public array $itemparams;

    /**
     * Set parameters for class instance.
     */
    public function __construct() {
        $this->itemparams = [];
    }

    /**
     * Return count of parameters.
     *
     * @return int
     *
     */
    public function count(): int {
        return count($this->itemparams);
    }

    /**
     * Try to load existing item params from the DB.
     * If none are found, it returns an empty list.
     *
     * @param int $contextid
     * @param string $modelname
     * @param array $catscaleids
     *
     * @return self
     *
     */
    public static function load_from_db(int $contextid, string $modelname, array $catscaleids = []): self {
        global $DB;
        $models = model_strategy::get_installed_models();

        if ($catscaleids) {
            $itemrows = catquiz::get_itemparams($contextid, $catscaleids, $modelname);
        } else {
            $itemrows = $DB->get_records(
                'local_catquiz_itemparams',
                [
                    'contextid' => $contextid,
                    'model' => $modelname,
                ],
            );
        }
        $itemparameters = new model_item_param_list();
        foreach ($itemrows as $r) {
            // Skip NaN values here.
            if ($r->difficulty === "NaN") {
                continue;
            }
            $i = new model_item_param($r->componentid, $modelname, [], $r->status);
            $parameternames = $models[$modelname]::get_parameter_names();
            $params = [];
            foreach ($parameternames as $paramname) {
                $params[$paramname] = $r->$paramname;
            }
            $i->set_parameters($params);
            $itemparameters->add($i);
        }

        return $itemparameters;
    }

    /**
     * Return Iterator.
     *
     * @return Traversable
     *
     */
    public function getiterator(): Traversable {
        return new ArrayIterator($this->itemparams);
    }

    /**
     * Add a parameter
     *
     * @param model_item_param $itemparam
     *
     * @return self
     *
     */
    public function add(model_item_param $itemparam) {
        $this->itemparams[$itemparam->get_id()] = $itemparam;
        return $this;
    }

    /**
     * Set parameter by offset.
     *
     * @param mixed $offset
     * @param mixed $value
     *
     * @return void
     *
     */
    public function offsetSet($offset, $value): void {
        if (is_null($offset)) {
            $this->itemparams[] = $value;
        } else {
            $this->itemparams[$offset] = $value;
        }
    }

    /**
     * Check if offset exists.
     *
     * @param mixed $offset
     *
     * @return bool
     *
     */
    public function offsetExists($offset): bool {
        return isset($this->itemparams[$offset]);
    }

    /**
     * Unset offse.
     *
     * @param mixed $offset
     *
     * @return void
     *
     */
    public function offsetUnset($offset): void {
        unset($this->itemparams[$offset]);
    }

    /**
     * Return parameter by offset.
     *
     * @param mixed $offset
     *
     * @return model_item_param|null
     *
     */
    public function offsetGet($offset): ?model_item_param {
        return isset($this->itemparams[$offset]) ? $this->itemparams[$offset] : null;
    }

    /**
     * Returns the item difficulties as a float array.
     *
     * @param bool $sorted
     *
     * @return array
     *
     */
    public function get_values($sorted = false): array {
        $data = array_map(
            function (model_item_param $param) {
                return $param->get_difficulty();
            },
            $this->itemparams
        );

        $data = array_filter($data, function ($a) {
            return is_finite($a) && abs($a) != model_item_param::MAX;
        });
        if ($sorted) {
            sort($data);
        }
        return $data;
    }

    /**
     * Return parameters as array.
     *
     * @return array
     *
     */
    public function as_array(): array {
        $data = [];
        foreach ($this->itemparams as $i) {
            $data[$i->get_id()] = $i;
        }
        return $data;
    }

    /**
     * Save parameters to DB.
     *
     * @param int $contextid
     *
     * @return void
     *
     */
    public function save_to_db(int $contextid) {

        global $DB;

        // Get existing records for the given contextid from the DB, so that we know
        // whether we should create a new item param or update an existing one.
        $existingparamsrows = $DB->get_records(
            'local_catquiz_itemparams',
            ['contextid' => $contextid]
        );
        $existingparams = [];
        foreach ($existingparamsrows as $r) {
            $existingparams[$r->componentid][$r->model] = $r;
        };

        $records = array_map(
            function ($param) use ($contextid) {
                $record = [
                    'componentid' => $param->get_id(),
                    'componentname' => 'question',
                    'model' => $param->get_model_name(),
                    'contextid' => $contextid,
                    'status' => $param->get_status(),
                ];
                foreach ($param->get_params_array() as $paramname => $value) {
                    if (abs($value) > model_item_param::MAX) {
                        $value = $value < 0 ? model_item_param::MIN : model_item_param::MAX;
                    }
                    $record[$paramname] = $value;
                }

                return $record;
            },
            $this->itemparams
        );

        $updatedrecords = [];
        $newrecords = [];
        $now = time();
        $models = model_strategy::get_installed_models();
        foreach ($records as $record) {
            // Do not save or update items that have a NAN as one of their parameter's values.
            $parameternames = $models[$record['model']]::get_parameter_names();
            foreach ($parameternames as $parametername) {
                if (is_nan($record[$parametername])) {
                    continue;
                }
            }

            $isexistingparam = array_key_exists($record['componentid'], $existingparams)
                && array_key_exists($record['model'], $existingparams[$record['componentid']]);
            // If record already exists, update it. Otherwise, insert a new record to the DB.
            if ($isexistingparam) {
                $record['id'] = $existingparams[$record['componentid']][$record['model']]->id;
                $record['timemodified'] = $now;
                $updatedrecords[] = $record;
            } else {
                $record['timecreated'] = $now;
                $record['timemodified'] = $now;
                $newrecords[] = $record;
            }
        }

        if (!empty($newrecords)) {
            $DB->insert_records('local_catquiz_itemparams', $newrecords);

        }

        foreach ($updatedrecords as $r) {
            $DB->update_record('local_catquiz_itemparams', $r, true);
        }
    }

    /**
     * Check if record exists to update, if not, insert.
     * Some logic to provide the correct values if missing.
     * @param array $newrecord
     * @return array
     * @throws dml_exception
     */
    public static function save_or_update_testitem_in_db(array $newrecord) {
        global $DB;

        // If we have a label we look it up and identify the testitemid and use it for further matching.
        // Matching works this way:
        // - identify idnumber = label in qbe.
        // - We get back ALL the versions, so an array of questionids.
        // - Not sure which one to update, so we throw an error when there are more than one.
        // Way to fix this error: make sure that there is only one version of a question used in a catscale.

        $returnarray = [
            'success' => 0,
            'message' => 'Callback could not be executed',
        ];

        // Scale logic is in this function: get scale id and update in table.
        if ($label = $newrecord['label'] ?? false) {
            $sql = "SELECT qv.questionid, qv.questionbankentryid as qbeid
            FROM {question_bank_entries} qbe

            JOIN {question_versions} qv
            ON qbe.id = qv.questionbankentryid

            LEFT JOIN {local_catquiz_items} lci
            ON lci.componentid=qv.questionid

            WHERE qbe.idnumber LIKE :label
            GROUP BY qv.questionid, qv.questionbankentryid";

            // We check if we find entries.
            $records = $DB->get_records_sql($sql, ['label' => $label]);

            if (!count($records) > 0) {
                return [
                    'success' => 0, // Update not successful.
                    'message' => get_string('labelidnotfound', 'local_catquiz', $newrecord['label']),
                 ];
            } else if (count($records) > 1) {
                return [
                    'success' => 0, // Update not successful.
                    'message' => get_string('labelidnotunique', 'local_catquiz', $newrecord['label']),
                 ];
            }

            $record = reset($records);
            unset($newrecord['label']);
            $newrecord['componentid'] = $record->questionid;

            // We call the same function again, now with the componentid and without the label id.
            $returnarray = self::save_or_update_testitem_in_db($newrecord);

            return $returnarray;
        }
        // We only run this once we have the component id.
        $newrecord = self::update_in_scale($newrecord);

        // Assign corresponding context.
        self::assign_catcontext($newrecord);

        if (empty($newrecord['model'])) {
            return [
                'success' => 2, // Update successful, but errors triggered.
                'message' => get_string('dataincomplete', 'local_catquiz', ['id' => $newrecord['componentid'], 'field' => 'model']),
                'recordid' => $newrecord['componentid'],
             ];
        }

        if (isset($newrecord['id'])) {
            $record = $DB->get_record("local_catquiz_itemparams", [
                'id' => $newrecord['id'],
            ]);
        } else {
            $record = $DB->get_record("local_catquiz_itemparams", [
                'componentid' => $newrecord['componentid'],
                'componentname' => $newrecord['componentname'],
                'model' => $newrecord['model'],
                'contextid' => $newrecord['contextid'],
            ]);
        }

        $now = time();
        $newrecord['timemodified'] = empty($newrecord['timemodified']) ? $now : $newrecord['timemodified'];
        if (isset($record->timecreated) && $record->timecreated != "0") {
            $newrecord['timecreated'] = !empty($newrecord['timecreated']) ? $newrecord['timecreated'] : $record->timecreated;
        } else {
            $newrecord['timecreated'] = !empty($newrecord['timecreated']) ? $newrecord['timecreated'] : $now;
        }

        $newrecord['status'] = !empty($newrecord['status']) ? $newrecord['status'] : STATUS_UPDATED_MANUALLY;
        if (!$record) {
            // Make sure the record to insert has no id.
            unset($newrecord['id']);
            $id = $DB->insert_record('local_catquiz_itemparams', $newrecord);

        } else {
            $newrecord['id'] = $record->id;
            if ($DB->update_record('local_catquiz_itemparams', (object) $newrecord, true)) {
                $id = $newrecord['id'];
            }
        }

        return [
            'success' => 1, // Update successfully.
            'message' => get_string('success', 'core'),
            'recordid' => $id,
         ];
    }

    /**
     * Gets scaleid and updates scaleid of record.
     * @param array $newrecord
     * @return array
     */
    private static function update_in_scale(array $newrecord) {
        global $DB;

        // If at this point, the scale is still empty, we need to create it.
        self::create_scales_for_new_record($newrecord);

        // If we don't know the catscaleid we get it via the catscalename.
        if (empty($newrecord['catscaleid']) && !empty($newrecord['catscalename'])) {
            $sql = "SELECT id
                    FROM {local_catquiz_catscales}
                    WHERE name = :name";
            $catscaleid = $DB->get_field_sql($sql, ['name' => $newrecord['catscalename']]);

            if (!empty($catscaleid)) {
                $newrecord['catscaleid'] = $catscaleid;
            }
        }

        if (empty($newrecord['catscaleid'])) {
            throw new moodle_exception('nocatscaleid', 'local_catquiz');
        }

        $scalerecord = $DB->get_record("local_catquiz_items", [
            'componentid' => $newrecord['componentid'],
            'catscaleid' => $newrecord['catscaleid'],
        ]);

        if (!$scalerecord) {
            $columnstoinclude = ['componentname', 'componentid', 'catscaleid', 'lastupdated', 'status'];
            $recordforquery = $newrecord;
            foreach ($recordforquery as $key => $value) {
                if (!in_array($key, $columnstoinclude, true)) {
                    unset($recordforquery[$key]);
                }
                // If no activity status is given, set to active by default.
                if ($key == "status" && empty($value)) {
                    $recordforquery["status"] = 0;
                }
                if ($key == "lastupdated" && empty($value)) {
                    $recordforquery["lastupdated"] = time();
                }
            }
            $DB->insert_record('local_catquiz_items', $recordforquery);

            // Trigger event.
            $event = testiteminscale_added::create([
                'objectid' => $newrecord['componentid'],
                'context' => \context_system::instance(),
                'other' => [
                    'testitemid' => $newrecord['componentid'],
                    'catscaleid' => $newrecord['catscaleid'],
                    'context' => $newrecord['contextid'],
                    'component' => $newrecord['componentname'],
                ],
                ]);
            $event->trigger();
        }
        return $newrecord;
    }

    /**
     * This function creates the catscale structure on the fly.
     * @param array $newrecord
     * @return void
     * @throws dml_exception
     * @throws coding_exception
     * @throws ddl_exception
     */
    private static function create_scales_for_new_record(array &$newrecord) {

        global $DB;

        // First check if there are parents.
        $parents = [];
        if (!empty($newrecord['parentscalenames'])) {
            $newrecord['parentscalenames'] .= "|" . $newrecord['catscalename'];
            $parents = explode('|', $newrecord['parentscalenames']);
            // Make sure there are no spaces around.
            $parents = array_map(fn($a) => trim($a), $parents);
        } else if (!empty($newrecord['catscalename'])) {
            $parents[] = $newrecord['catscalename'];
        }

        $catscaleid = 0;

        foreach ($parents as $parent) {

            // Check if the scale exists.
            // We also need to look at the parentids.
            $searcharray = [
                'name' => $parent,
            ];

            if ($record = $DB->get_record('local_catquiz_catscales', $searcharray)) {
                $catscaleid = $record->id;
                continue;
            }

            $catscale = new catscale_structure([
                'name' => $parent,
                'parentid' => $catscaleid,
                'description' => '',
                'timecreated' => time(),
                'timemodified' => time(),
            ]);

            $catscaleid = dataapi::create_catscale($catscale);

        }
    }
    /**
     * This function checks if the context-param is empty.
     * If it's empty and a scale is given, a new context is created.
     * @param array $newrecord
     * @return void
     */
    private static function assign_catcontext(&$newrecord) {
        global $DB;
        // Check if context if empty. If so, create new context for this scale.
        // Make sure, the following lines with the same scale use the same context.
        if (!empty($newrecord['contextid'])) {
            return;
        }
        if (empty($newrecord['catscaleid'])) {
            return;
        }
        // We get the id and the name of the parent catscale.
        if (!empty(catscale::get_ancestors($newrecord['catscaleid'], 3)['catscaleids'])) {
            $parentscales = catscale::get_ancestors($newrecord['catscaleid'], 3);
            $scaleid = intval($parentscales['catscaleids'][0]);
            $scalename = $parentscales['catscalenames'][0];
        } else {
            $scaleid = intval($newrecord['catscaleid']);
            $scalename = $newrecord['catscalename'];
        }

        // Check if context was created with this import for this scale.
        // If so, use this context.
        // Else: create context and store in singleton.
        if (empty(catcontext::get_instance($scaleid))) {

            $defaultcontext = catquiz::get_default_context_object();
            $timestring = userdate(time(), get_string('strftimedatetimeshort', 'core_langconfig'));
            $usertime = str_replace(' ', '', $timestring);

            $data = new stdClass;
            $data->name = get_string('uploadcontext', 'local_catquiz', [
                'scalename' => $scalename,
                'usertime' => $usertime,
                ]);
            $data->starttimestamp = $defaultcontext->starttimestamp;
            $data->endtimestamp = $defaultcontext->endtimestamp;
            $data->description = get_string('autocontextdescription', 'local_catquiz', $scalename);

            $context = new catcontext($data);
            $context->save_or_update($data);
            catcontext::store_context_as_singleton($context, $scaleid);
            $catcontext = $context;
        } else {
            $catcontext = catcontext::get_instance($scaleid);
        };

        $newrecord['contextid'] = $catcontext->id;
    }

}
