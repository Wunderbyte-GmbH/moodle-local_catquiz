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

use cache_helper;
use local_catquiz\event\context_created;
use local_catquiz\event\context_updated;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_person_param_list;
use local_catquiz\local\model\model_responses;
use local_catquiz\local\model\model_strategy;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * Class catcontext
 *
 * Defines a set items and persons defined by different criteria such as:
 *  - Time (start date and end date)
 *
 * @author Georg Maißer
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catcontext {

    /**
     * $id
     *
     * @var int
     */
    public ?int $id = null;

    /**
     * $name
     *
     * @var string
     */
    public string $name = '';

    /**
     * $description
     *
     * @var string
     */
    private string $description = '';

    /**
     * $description format
     *
     * @var int
     */
    private int $descriptionformat = 1;

    /**
     * $json
     *
     * @var string
     */
    private string $json = '';

    /**
     * $starttimestamp
     *
     * @var int
     */
    private int $starttimestamp = 0;

    /**
     * $endtimestamp
     *
     * @var int
     */
    private int $endtimestamp = 0;

    /**
     * $usermodified
     *
     * @var int
     */
    private int $usermodified = 0;

    /**
     * $timecreated
     *
     * @var int
     */
    private int $timecreated = 0;

    /**
     * $timemodified
     *
     * @var int
     */
    private int $timemodified = 0;

    /**
     * $timecalculated
     *
     * The last time that item parameters and person abilities were calculated
     * for this context
     *
     * @var int
     */
    private int $timecalculated = 0;

    /**
     *
     * Provide sigleton context instances
     *
     * @var array
     */
    private static $catcontexts = [];


    /**
     * Catcontext constructor.
     * @param ?stdClass $newrecord
     */
    public function __construct(?stdClass $newrecord = null) {

        global $DB;

        if (empty($this->timecreated)) {
            $this->timecreated = time();
        }

        if ($newrecord && isset($newrecord->id)) {
            // If we have a new record.
            if ($oldrecord = $DB->get_record('local_catquiz_catcontext', ['id' => $newrecord->id])) {
                $this->apply_values($oldrecord);
            }
        }

        if ($newrecord) {
            $this->apply_values($newrecord);
        }
    }
    /**
     * Get a context via scaleid.
     * We create scale-based contexts for uploaded items without context assigned.+
     * This is to check if a context was already created for this scale.
     *
     * @param int $scaleid
     * @return catcontext|null
     */
    public static function get_instance(int $scaleid) {
        if (empty(self::$catcontexts[$scaleid])) {
            return null;
        } else {
            return self::$catcontexts[$scaleid];
        }
    }

    /**
     * Store generated context in singleton array.
     *
     * @param catcontext $catcontext
     * @param int $scaleid
     *
     * @return bool
     *
     */
    public static function store_context_as_singleton(catcontext $catcontext, int $scaleid) {
        self::$catcontexts[$scaleid] = $catcontext;
        return true;
    }

    /**
     * Load from DB
     *
     * @param int $contextid
     *
     * @return self
     *
     */
    public static function load_from_db(int $contextid): self {
        global $DB;
        $record = $DB->get_record('local_catquiz_catcontext', ['id' => $contextid]);
        return new self($record);
    }

    /**
     * Load all contexts from DB.
     *
     * @return array
     *
     */
    public static function return_all_catcontexts(): array {
        global $DB;
        // TODO Caching.
        $records = $DB->get_records('local_catquiz_catcontext');
        return $records;
    }

    /**
     * Create response from DB.
     *
     * @param int $contextid
     * @param array $catscaleids
     * @param int|null $testitemid
     * @param int|null $userid
     *
     * @return array
     *
     */
    public static function getresponsedatafromdb(
            int $contextid,
            array $catscaleids,
            ?int $testitemid = null,
            ?int $userid = null): array {
        global $DB;

        list ($sql, $params) = catquiz::get_sql_for_model_input($contextid, $catscaleids, $testitemid, $userid);
        $data = $DB->get_records_sql($sql, $params);
        $inputdata = self::db_to_modelinput($data);
        return $inputdata;
    }

    /**
     * Returns data in the following format
     *
     * "1" => Array( //userid
     *     "comp1" => Array( // component
     *         "1" => Array( //questionid
     *             "fraction" => 0,
     *             "max_fraction" => 1,
     *             "min_fraction" => 0,
     *             "qtype" => "truefalse",
     *             "timestamp" => 1646955326
     *         ),
     *         "2" => Array(
     *             "fraction" => 0,
     *             "max_fraction" => 1,
     *             "min_fraction" => 0,
     *             "qtype" => "truefalse",
     *             "timestamp" => 1646955332
     *         ),
     *         "3" => Array(
     *             "fraction" => 1,
     *             "max_fraction" => 1,
     *             "min_fraction" => 0,
     *             "qtype" => "truefalse",
     *             "timestamp" => 1646955338
     *
     * @param mixed $data
     *
     * @return array
     */
    private static function db_to_modelinput($data): array {
        $modelinput = [];
        // Check: use only most recent answer for each question.

        foreach ($data as $row) {
            $entry = [
                'fraction' => $row->fraction,
                'max_fraction' => $row->maxfraction,
                'min_fraction' => $row->minfraction,
                'qtype' => $row->qtype,
                'timestamp' => $row->timecreated,
                'id' => $row->id,
            ];

            if (!array_key_exists($row->userid, $modelinput)) {
                $modelinput[$row->userid] = ["component" => []];
            }

            if (! array_key_exists($row->questionid, $modelinput[$row->userid]['component'])) {
                $modelinput[$row->userid]['component'][$row->questionid] = $entry;
                continue;
            }

            // If we are here, there is already an entry. Only update it if this answer is newer than the last one.
            if ($row->id > $modelinput[$row->userid]['component'][$row->questionid]['id']) {
                $modelinput[$row->userid]['component'][$row->questionid] = $entry;
            }
        }
        return $modelinput;
    }

    /**
     * Save or update catcontext class.
     *
     * @param ?stdClass $newrecord
     * @return void
     */
    public function save_or_update(?stdClass $newrecord = null) {

        global $DB, $USER;

        if ($newrecord) {
            $this->apply_values($newrecord);
        }

        $this->timemodified = time();
        $this->usermodified = $USER->id;

        if (!empty($this->id)) {
            $DB->update_record('local_catquiz_catcontext', $this->return_as_class());

            // Trigger context updated event.
            $event = context_updated::create([
                'objectid' => $this->id,
                'context' => \context_system::instance(),
                'other' => [
                    'contextname' => $this->name,
                    'contextid' => $this->id,
                    'contextobjectcallback' => 'local_catquiz\local\classes\catcontext::return_as_class',
                ],
                ]);
            $event->trigger();

        } else {
            $this->id = $DB->insert_record('local_catquiz_catcontext', $this->return_as_class());

            // Trigger context created event.
            $event = context_created::create([
                'objectid' => $this->id,
                'context' => \context_system::instance(),
                'other' => [
                    'contextname' => $this->name,
                    'contextid' => $this->id,
                    'contextobjectcallback' => 'local_catquiz\local\classes\catcontext::return_as_class',
                ],
                ]);
            $event->trigger();
        }
        cache_helper::purge_by_event('changesincatcontexts');
    }

    /**
     * Return all the values of this class as stdClass.
     *
     * @return stdClass
     */
    public function return_as_class() {

        $record = (object)[
            'name' => $this->name,
            'description' => $this->description,
            'descriptionformat' => $this->descriptionformat,
            'starttimestamp' => $this->starttimestamp,
            'endtimestamp' => $this->endtimestamp,
            'json' => $this->json,
            'usermodified' => $this->usermodified,
            'timecreated' => $this->timecreated,
            'timemodified' => $this->timemodified,
            'timecalculated' => $this->timecalculated,
        ];

        // Only if the id is not empty, we add the id key.
        if (!empty($this->id)) {
            $record->id = $this->id;
        }

        return $record;
    }

    /**
     * Apply values from record.
     *
     * @param stdClass $record
     * @return void
     */
    public function apply_values(stdClass $record) {
        $this->id = $record->id ?? $this->id ?? null;
        $this->name = $record->name ?? $this->name ?? '';
        $this->description = $record->description ?? $this->description ?? '';
        $this->descriptionformat = $record->descriptionformat ?? $this->descriptionformat ?? 1;
        $this->starttimestamp = $record->starttimestamp ?? $this->starttimestamp ?? 0;
        $this->endtimestamp = $record->endtimestamp ?? $this->endtimestamp ?? 0;
        $this->json = $record->json ?? $this->json ?? '';
        $this->usermodified = $record->usermodified ?? $this->usermodified ?? 0;
        $this->timecreated = $record->timecreated ?? $this->timecreated ?? time();
        $this->timemodified = $record->timemodified ?? $this->timemodified ?? time();
        $this->timecalculated = $record->timecalculated ?? $this->timecalculated ?? 0;
    }

    /**
     * Get_strategy.
     *
     * @param int $catscaleid
     *
     * @return model_strategy
     *
     */
    public function get_strategy(int $catscaleid): model_strategy {
        $catscaleids = [$catscaleid, ...catscale::get_subscale_ids($catscaleid)];
        $responsedata = self::getresponsedatafromdb($this->id, $catscaleids);
        $responses = (new model_responses())->setdata($responsedata);
        $options = json_decode($this->json, true) ?? [];
        $savedabilities = model_person_param_list::load_from_db($this->id, $catscaleids);
        $installedmodels = model_strategy::get_installed_models();
        $olditemparams = [];
        foreach (array_keys($installedmodels) as $model) {
            $olditemparams[$model] = model_item_param_list::load_from_db($this->id, $model, $catscaleids);
        }
        return new model_strategy($responses, $options, $savedabilities, $olditemparams);
    }

    /**
     * Add a default context that contains all test items.
     *
     * @return void
     *
     */
    public function create_default_context() {
        global $DB;
        $context = $DB->get_record_sql(
            "SELECT * FROM {local_catquiz_catcontext} WHERE json LIKE :default",
            [
                'default' => '%"default":true%',
            ]
        );
        if (!$context) {
            $json = new stdClass();
            $json->default = true;
            $context = (object) [
                'name' => get_string('defaultcontextname', 'local_catquiz'),
                'description' => get_string('defaultcontextdescription', 'local_catquiz'),
                'descriptionformat' => 1,
                'starttimestamp' => 0,
                'endtimestamp' => PHP_INT_MAX,
                'json' => json_encode($json),
            ];
            $this->save_or_update($context);
        }
    }

    /**
     * Get name.
     *
     * @return string
     *
     */
    public function getname(): string {
        return $this->name;
    }

    /**
     * Returns the time when the items of this context have last been updated.
     * @return int
     */
    public function gettimecalculated(): int {
        return $this->timecalculated;
    }
}
