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
 * @copyright 2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\local\model;
use ArrayAccess;
use ArrayIterator;
use cache;
use cache_helper;
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
use local_catquiz\remote\hash\question_hasher;
use moodle_exception;
use stdClass;
use Traversable;
use UnexpectedValueException;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/catquiz/lib.php');
/**
 * This class holds a list of item param objects.
 *
 * This is one of the return values from a model param estimation.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class model_item_param_list implements ArrayAccess, Countable, IteratorAggregate {
    /**
     * @var bool Indicates if the list uses question hashes instead of IDs.
     */
    private bool $useshashes = false;

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
     * Returns an item parameter list for the given arguments.
     *
     * @param int $contextid
     * @param ?string $modelname
     * @param array $catscaleids
     * @return model_item_param_list
     * @throws coding_exception
     */
    public static function get(int $contextid, ?string $modelname = null, array $catscaleids = []): self {
        // Try to get the item params from the cache.
        $cache = cache::make('local_catquiz', 'catquiz_item_params');
        $selectedscaleshash = hash('crc32', implode('_', $catscaleids));
        $cachekey = sprintf('itemparams_%s_%s_%s', $contextid, $modelname, $selectedscaleshash);
        if ($itemparamlist = $cache->get($cachekey)) {
            return $itemparamlist;
        }

        if (!$itemparamlist = self::load_from_db($contextid, $modelname, $catscaleids)) {
            return new model_item_param_list();
        }
        $cache->set($cachekey, $itemparamlist);
        return $itemparamlist;
    }

    /**
     * Retrieve item parameters based on the context ID and question ID.
     *
     * @param int $contextid The context ID to search within.
     * @param int $questionid The ID of the question for which to retrieve parameters.
     * @return model_item_param_list The item parameters retrieved from the cache or database.
     */
    public static function get_by_questionid(int $contextid, $questionid): model_item_param_list {
        // Try to get the item params from the cache.
        $cache = cache::make('local_catquiz', 'catquiz_item_params');
        $cachekey = sprintf('params_%d_%d', $contextid, $questionid);
        if ($itemparamlist = $cache->get($cachekey)) {
            return $itemparamlist;
        }

        if (!$itemparamlist = self::load_from_db_by_questionid($contextid, $questionid)) {
            return new model_item_param_list();
        }
        $cache->set($cachekey, $itemparamlist);
        return $itemparamlist;
    }

    /**
     * Try to load existing item params from the DB.
     * If none are found, it returns an empty list.
     *
     * @param int $contextid
     * @param ?string $modelname
     * @param array $catscaleids
     *
     * @return self
     *
     */
    public static function load_from_db(int $contextid, ?string $modelname = null, array $catscaleids = []): self {
        $itemrows = catquiz::get_itemparams($contextid, $catscaleids, $modelname);
        $itemparameters = new model_item_param_list();
        foreach ($itemrows as $r) {
            // Skip NaN values here.
            if ($r->difficulty === "NaN") {
                continue;
            }
            $i = model_item_param::from_record($r);
            $itemparameters->add($i);
        }

        return $itemparameters;
    }

    /**
     * Try to load existing item params from the DB.
     * If none are found, it returns an empty list.
     *
     * @param int $contextid
     * @param int $questionid
     *
     * @return self
     *
     */
    public static function load_from_db_by_questionid(int $contextid, int $questionid): self {
        global $DB;

        $itemrows = $DB->get_records(
            'local_catquiz_itemparams',
            [
                'contextid' => $contextid,
                'componentid' => $questionid,
            ],
        );
        $itemparameters = new model_item_param_list();
        foreach ($itemrows as $r) {
            // Skip NaN values here.
            if ($r->difficulty === "NaN") {
                continue;
            }
            $i = model_item_param::from_record($r);
            $itemparameters->add($i, true);
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
     * @param bool $bymodel Index itemparams by model name instead of component id.
     *
     * @return self
     *
     */
    public function add(model_item_param $itemparam, bool $bymodel = false) {
        if ($bymodel) {
            $this->itemparams[$itemparam->get_model_name()] = $itemparam;
            return $this;
        }

        $this->itemparams[$itemparam->get_componentid()] = $itemparam;
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
     * Unset offset.
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
     * Returns the item IDs.
     *
     * @return array
     */
    public function get_item_ids(): array {
        return array_keys($this->itemparams);
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
            $data[$i->get_componentid()] = $i;
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

        // Do not save or update items that have a NAN as one of their parameter's values.
        $validparams = array_filter(
            $this->itemparams,
            fn ($i) => $i->is_valid()
        );
        $records = array_map(
            fn ($i) => $i->set_contextid($contextid)->to_record(),
            $validparams
        );

        $updatedrecords = [];
        $newrecords = [];
        $now = time();
        foreach ($records as $record) {
            $isexistingparam = array_key_exists($record->componentid, $existingparams)
                && array_key_exists($record->model, $existingparams[$record->componentid]);
            // If record already exists, update it. Otherwise, insert a new record to the DB.
            if ($isexistingparam) {
                $record->id = $existingparams[$record->componentid][$record->model]->id;
                $record->timemodified = $now;
                $updatedrecords[] = $record;
            } else {
                $record->timecreated = $now;
                $record->timemodified = $now;
                $newrecords[] = $record;
            }
        }

        if (!empty($newrecords)) {
            $DB->insert_records('local_catquiz_itemparams', $newrecords);

        }

        foreach ($updatedrecords as $r) {
            $DB->update_record('local_catquiz_itemparams', $r, true);
        }
        cache_helper::purge_by_event('changesinitemparams');
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
            $sql = "SELECT qv.questionid, qv.questionbankentryid AS qbeid
            FROM {question_bank_entries} qbe

            JOIN {question_versions} qv
            ON qbe.id = qv.questionbankentryid

            LEFT JOIN {local_catquiz_items} lci
            ON lci.componentid = qv.questionid

            WHERE qbe.idnumber LIKE :label
            GROUP BY qv.questionid, qv.questionbankentryid
            ORDER BY qv.questionid ASC";

            // We check if we find entries.
            $records = $DB->get_records_sql($sql, ['label' => $label]);

            if (!count($records) > 0) {
                return [
                    'success' => 0, // Update not successful.
                    'message' => get_string('labelidnotfound', 'local_catquiz', $newrecord['label']),
                 ];
            } else if (count($records) > 1) {
                // If we are here, it means there exists more than one question version.
                // We want to only assign the most recent version to the scale, so:
                // 1. Ensure that the scale does not contain older items
                // 2. Continue as if this was a new question (add it to the scale).

                $new = array_pop($records);

                // 1. Remove old question versions from scale:
                if ($catscale = $DB->get_record('local_catquiz_catscales', ['name' => $newrecord['catscalename']])) {
                    foreach ($records as $r) {
                        catscale::remove_testitem_from_scale($catscale->id, $r->questionid);
                    }
                    $newrecord['warning'] = 'Removed older question versions from scale';
                }

                // 2. Continue with the most recent version of the question:
                unset($newrecord['label']);
                $newrecord['componentid'] = $new->questionid;
                $returnarray = self::save_or_update_testitem_in_db($newrecord);
                return $returnarray;
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

        if (isset($newrecord['error'])) {
            return [
                'success' => 0, // Update not successful.
                'message' => $newrecord['error'],
             ];
        }

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

        $newrecord['status'] = !empty($newrecord['status']) ? $newrecord['status'] : LOCAL_CATQUIZ_STATUS_UPDATED_MANUALLY;
        $itemparam = model_item_param::from_record((object) $newrecord);
        if ($record) {
            $itemparam->set_id($record->id);
        }
        $itemparam->save();
        // Ensure that the item points to the itemparam with the highest status.
        catquiz::set_active_itemparam($itemparam->get_itemid());
        cache_helper::purge_by_event('changesinitemparams');
        if (!empty($newrecord['warning'])) {
            return [
                'success' => 2, // Update successfull with warning.
                'message' => $newrecord['warning'],
                'recordid' => $itemparam->get_id(),
             ];
        } else {
            return [
                'success' => 1, // Update successfully.
                'message' => get_string('success', 'core'),
                'recordid' => $itemparam->get_id(),
             ];

        }
    }

    /**
     * Return item override.
     *
     * If the item has a manual override in one of the given lists, return that one.
     *
     * @param string $itemid
     * @param array $itemparamlists Contains model_item_param_list elements.
     *
     * @return ?model_item_param
     */
    public static function get_item_override(string $itemid, array $itemparamlists): ?model_item_param {
        $items = [];
        foreach ($itemparamlists as $itemparams) {
            if (! $itemparams[$itemid]) {
                continue;
            }
            $items[] = $itemparams[$itemid];
        }

        if (! $items) {
            return null;
        }

        $manuallyconfirmed = array_filter($items, fn ($ip) =>  $ip->get_status() === LOCAL_CATQUIZ_STATUS_CONFIRMED_MANUALLY);
        if ($manuallyconfirmed) {
            return reset($manuallyconfirmed);
        }

        $manuallyupdated = array_filter($items, fn ($ip) => $ip->get_status() === LOCAL_CATQUIZ_STATUS_UPDATED_MANUALLY);
        if ($manuallyupdated) {
            return reset($manuallyupdated);
        }

        return null;
    }

    /**
     * Return IDs of item params that are confirmed
     *
     * @return array
     */
    public function confirmed(): array {
        $ids = array_filter(
            array_map(
                fn ($ip) => $ip->get_id(),
                $this->itemparams
            ),
            fn ($id) => $this->itemparams[$id]->get_status() > LOCAL_CATQUIZ_STATUS_CALCULATED
        );
        return $ids;
    }

    /**
     * Return the stored item params without the ones identified by the given IDs.
     *
     * @param array $itemids
     * @param bool $clone
     * @return model_item_param_list
     */
    public function without(array $itemids, bool $clone = true): self {
        if ($clone) {
            $obj = clone($this);
            return $obj->without($itemids, false);
        }
        $this->itemparams = array_filter($this->itemparams, fn ($ip) => !in_array($ip->get_id(), $itemids));
        return $this;
    }

    /**
     * Only keep itemparams that are associated with the given component IDs.
     *
     * @param array $itemids
     * @param bool $clone
     * @return model_item_param_list
     */
    public function filter_by_componentids(array $itemids, bool $clone = true): self {
        if ($clone) {
            $obj = clone $this;
            return $obj->filter_by_componentids($itemids, false);
        }
        $this->itemparams = array_filter($this->itemparams, fn ($ip) => in_array($ip->get_componentid(), $itemids));
        return $this;
    }

    /**
     * Sets the list to use question hashes instead of IDs.
     *
     * @return self
     */
    public function use_hashes(): self {
        $this->useshashes = true;
        return $this;
    }

    /**
     * Convert question hashes to IDs.
     * @return self
     */
    public function convert_hashes_to_ids(): self {
        if (!$this->useshashes) {
            return $this;
        }

        foreach ($this->itemparams as $itemparam) {
            $questionid = question_hasher::get_questionid_from_hash($itemparam->get_componentid());
            if ($questionid) {
                $itemparam->set_componentid($questionid);
            }
        }
        $this->useshashes = false;
        return $this;
    }

    /**
     * Gets scaleid and updates scaleid of record.
     * @param array $newrecord
     * @return array
     */
    private static function update_in_scale(array $newrecord) {
        global $DB;
        // If we don't know the catscaleid we get it via the catscalename.
        if (empty($newrecord['catscaleid']) && !empty($newrecord['catscalename'])) {
            $sql = "SELECT *
                    FROM {local_catquiz_catscales}
                    WHERE name = :name";
            // Check if catscale with this name exisits.
            // Check if parentscales are given in newrecord and if they match with the path of the found scale.
            $match = null;
            $catscales = $DB->get_records_sql($sql, ['name' => $newrecord['catscalename']]);
            if (array_key_exists('parentscalenames', $newrecord)) {
                $ancestorsnewrecord = array_reverse(explode('|', $newrecord['parentscalenames']));
                // Check if any of the matching scales also has a matching list of ancestors.
                foreach ($catscales as $candidatescale) {
                    $ancestorsfoundscale = catscale::get_ancestors($candidatescale->id, 2);
                    if ($ancestorsfoundscale == $ancestorsnewrecord) {
                        $match = $candidatescale;
                        break;
                    }
                }
            }
            if (!empty($newrecord['parentscalenames'])) {
                if ($match) {
                    // Same path, so we match.
                    $catscaleid = $match->id;
                    $newrecord['catscaleid'] = $catscaleid;

                    if (empty($newrecord['catscaleid'])) {
                        throw new moodle_exception('nocatscaleid', 'local_catquiz');
                    }
                    if (
                        catscale::is_assigned_to_parent_scale($catscaleid, $newrecord['componentid'])
                        || catscale::is_assigned_to_subscale($catscaleid, $newrecord['componentid'])
                    ) {
                        $infodata = new stdClass();
                        $infodata->newscalename = catscale::get_link_to_catscale($newrecord['catscaleid']);
                        $infodata->componentid = $newrecord['componentid'];
                        $newrecord['error'] = get_string('itemassignedtoparentorsubscale', 'local_catquiz', $infodata);
                        return $newrecord;
                    }
                } else {
                    // If at this point, the scale is still empty, we need to create and use it.
                    self::create_scales_for_new_record($newrecord);
                }
            } else if ($newrecord['parentscalenames'] == "0") {
                // To enable import to parent scales, set 'parentscalenames' to "0".
                self::create_scales_for_new_record($newrecord, false);
            } else if (!isset($newrecord['parentscalenames']) || $newrecord['parentscalenames'] === "") {
                $newrecord['error'] = get_string('noparentsgiven', 'local_catquiz', $newrecord);
                return $newrecord;
            } else {
                self::create_scales_for_new_record($newrecord);
            }
        } else {
            self::create_scales_for_new_record($newrecord);
        }
        if (isset($newrecord['error'])) {
            return $newrecord;
        }

        // Assign corresponding context.
        self::assign_catcontext($newrecord);

        // See if a new context was created. If so, duplicate the existing items for this scale and assign the new context.
        $allscales = catscale::get_ancestors($newrecord['catscaleid']);
        $globalscaleid = end($allscales);
        $contextid = $newrecord['contextid'] ?? null;
        if (!$contextid && $context = catcontext::get_instance($globalscaleid)) {
            $contextid = $context->id;
        }        // See if the item already exists.
        $scalerecord = $DB->get_record("local_catquiz_items", [
            'componentid' => $newrecord['componentid'],
            'catscaleid' => (string) $newrecord['catscaleid'],
            'contextid' => $contextid,
        ]);

        // Check if item is in scale otherwise add it.
        if (!$scalerecord) {
            $columnstoinclude = ['componentname', 'componentid', 'catscaleid', 'lastupdated', 'contextid'];
            $recordforquery = $newrecord;
            foreach ($recordforquery as $key => $value) {
                if (!in_array($key, $columnstoinclude, true)) {
                    unset($recordforquery[$key]);
                }
                // Default value for status is active.
                $recordforquery["status"] = LOCAL_CATQUIZ_TESTITEM_STATUS_ACTIVE;

                if ($key == "lastupdated" && empty($value)) {
                    $recordforquery["lastupdated"] = time();
                }
            }
            $itemid = $DB->insert_record('local_catquiz_items', $recordforquery, true);

            // Trigger event.
            $event = testiteminscale_added::create([
                'objectid' => $newrecord['componentid'],
                'context' => \context_system::instance(),
                'other' => [
                    'testitemid' => $newrecord['componentid'],
                    'catscaleid' => $newrecord['catscaleid'],
                    'context' => $newrecord['contextid'] ?? '',
                    'component' => $newrecord['componentname'],
                ],
                ]);
            $event->trigger();
        }

        $newrecord['itemid'] = $itemid ?? $scalerecord->id;
        return $newrecord;
    }

    /**
     * This function creates the catscale structure on the fly.
     * @param array $newrecord
     * @param bool $createparents
     * @return void
     * @throws dml_exception
     * @throws coding_exception
     * @throws ddl_exception
     */
    private static function create_scales_for_new_record(array &$newrecord, bool $createparents = true) {

        global $DB;

        // Matching via scaleid is primary. If scale isn't found, write error.
        if (isset($newrecord['catscaleid']) && !empty($newrecord['catscaleid'])) {
            $record = $DB->get_record('local_catquiz_catscales', ['id' => $newrecord['catscaleid']]);
            if ($record) {
                return;
            }
            $newrecord['error'] = get_string('catscaleidnotmatching', 'local_catquiz', $newrecord);
            return;
        }

        if (!$createparents) {
            unset($newrecord['parentscalenames']);
        }
        // First check if there are parents.
        $parents = [];
        if (!empty($newrecord['parentscalenames'])) {
            $newrecord['parentscalenames'] .= "|" . $newrecord['catscalename'];
            $parents = explode('|', $newrecord['parentscalenames']);
            // Make sure there are no spaces around.
            $parents = array_map(fn($a) => trim($a), $parents);
            $parentsgiven = true;
        } else if (!empty($newrecord['catscalename'])) {
            $parents[] = $newrecord['catscalename'];
            $parentsgiven = false;
        }

        $catscaleid = 0;
        $matching = true;
        // Store the records that were found to avoid duplications.
        $records = [];
        $matchesparent = fn ($record, $parent) => !$parent || $record->parentid == $parent->id;

        foreach ($parents as $parent) {
            // Check if the scale exists.
            // We also need to look at the parentids.
            $searcharray = [
                'name' => $parent,
            ];
            // Case where no parents are given, we know we want to create new root scale.
            if ($parentsgiven) {
                $record = array_filter(
                    $DB->get_records('local_catquiz_catscales', $searcharray),
                    fn ($r) => $matchesparent($r, end($records))
                );
                if (count($record) > 1) {
                    throw new \Exception("Multiple matching parent scales found.");
                }
                $record = end($record);
                if ($record
                    && $matching
                    && !in_array($record, $records)
                ) {
                    $catscaleid = $record->id;
                    $records[] = $record;
                    $newrecord['catscaleid'] = $catscaleid;
                    continue;
                } else {
                    $matching = false;
                }
            }

            // For new rootscales, add min & max scalevalue.
            if ($catscaleid == 0
                && (isset($newrecord['minability'])
                    || isset($newrecord['maxability']))) {
                if (isset($newrecord['minability']) && (float) $newrecord['minability'] <= 0) {
                    $minscalevalue = $newrecord['minability'];
                }
                if (isset($newrecord['maxability']) && (float) $newrecord['maxability'] >= 0) {
                    $maxscalevalue = $newrecord['maxability'];
                }
                $catscale = new catscale_structure([
                    'name' => $parent,
                    'parentid' => $catscaleid,
                    'description' => '',
                    'timecreated' => time(),
                    'timemodified' => time(),
                    'minscalevalue' => $minscalevalue ?? LOCAL_CATQUIZ_PERSONABILITY_LOWER_LIMIT,
                    'maxscalevalue' => $maxscalevalue ?? LOCAL_CATQUIZ_PERSONABILITY_UPPER_LIMIT,
                    'contextid' => !empty($newrecord['contextid']) ? $newrecord['contextid'] : null,
                ]);
            } else {
                $catscale = new catscale_structure([
                    'name' => $parent,
                    'parentid' => $catscaleid,
                    'description' => '',
                    'timecreated' => time(),
                    'timemodified' => time(),
                ]);
            }

            $catscaleid = dataapi::create_catscale($catscale);
            if ($parent == $newrecord['catscalename'] || !$parentsgiven) {
                $newrecord['catscaleid'] = $catscaleid;
            }

        }
    }
    /**
     * This function checks if the context-param is empty.
     * If it's empty and a scale is given, a new context is created.
     * @param array $newrecord
     * @return void
     */
    private static function assign_catcontext(&$newrecord) {
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
            $scaleid = end($parentscales['catscaleids']);
            $scalename = end($parentscales['catscalenames']);
        } else {
            $scaleid = intval($newrecord['catscaleid']);
            $scalename = $newrecord['catscalename'];
        }

        // Check if context was created with this import for this scale.
        // If so, use this context.
        // Else: create context and store in singleton.
        if (!$catcontext = catcontext::get_instance($scaleid)) {
            // This will happen only once during an import.
            $catcontext = dataapi::create_new_context_for_scale($scaleid, $scalename);
        }

        $newrecord['contextid'] = $catcontext->id;
    }

    /**
     * Converts the given data to a model_item_param_list
     *
     * @param array $data
     * @return model_item_param_list
     * @throws UnexpectedValueException
     */
    public static function from_array(array $data) {
        $items = new self();
        $models = model_strategy::get_installed_models();
        foreach ($data as $d) {
            if (
                ! property_exists($d, 'id')
                || ! property_exists($d, 'model')
                || ! property_exists($d, 'status')
            ) {
                throw new UnexpectedValueException('Some property is missing from the given data');
            }

            $i = model_item_param::from_record($d);
            $items->add($i);
        }
        return $items;
    }

    /**
     * Filter the items to those that appear in the given responses object.
     *
     * @param model_responses $responses
     * @return model_item_param_list
     */
    public function filter_for_responses(model_responses $responses): self {
        foreach (array_keys($this->itemparams) as $itemid) {
            if (!in_array($itemid, $responses->get_item_ids())) {
                $this->offsetUnset($itemid);
            }
        }
        return $this;
    }

    /**
     * Returns the item params as CSV rows
     *
     * @param bool $header
     * @param int $precision
     * @return array
     */
    public function as_csv(bool $header = false, int $precision = 3): array {
        if ($header) {
            $rows[] = implode(
                ';',
                array_merge(
                    array_keys(reset($this->itemparams)->get_params_array()),
                    ['model']
                )
            );
        }
        foreach ($this->itemparams as $ip) {
            $rows[] = implode(';', array_merge([$ip->get_componentid()], $ip->get_params_array(), [$ip->get_model_name()]));
        }
        return $rows;
    }
}
