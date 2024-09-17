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

use local_catquiz\catquiz;
use local_catquiz\local\model\model_model;
use MoodleQuickForm;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * This class holds a single item param object
 *
 * This is one of the return values from a model param estimation.
 */
class model_item_param {

    // For some items, the model returns -INF or INF as difficulty.
    // However, we expect it to be numeric, so we encode those values as -1000 and 1000.
    /**
     * MIN
     *
     * @var int
     */
    const MIN = -1000;

    /**
     * MAX
     *
     * @var int
     */
    const MAX = 1000;

    /**
     * The component name
     *
     * @var string
     */
    const COMPONENTNAME = 'question';

    /**
     * Holds installed model classes
     * @var array<model_model>
     */
    private static array $models = [];

    /**
     * @var ?array<float>
     */
    private ?array $parameters = null;


    /**
     * @var int status
     */
    private int $status = 0;

    /**
     * @var string model name
     */
    private string $modelname;

    /**
     * Models that create items are free to use this field to store some metadata
     * @var array
     */
    private array $metadata;

    /**
     * The component id, e.g. question id
     *
     * @var string $componentid
     */
    private string $componentid;

    /**
     * The item ID
     *
     * @var int $itemid
     */
    private $itemid;

    /**
     * The ID of the itemparam in the database.
     *
     * @var ?int $id
     */
    private ?int $id;

    /**
     * The contextid
     *
     * This should be set when the itemparam is saved.
     * @var ?int $contextid
     */
    private ?int $contextid = null;

    /**
     * The time the item was created
     *
     * @var ?int $timecreated
     */
    private ?int $timecreated = null;

    /**
     * The time the item was modified
     *
     * @var ?int $timemodified
     */
    private ?int $timemodified = null;

    /**
     * Can hold additional item parameters
     *
     * @var ?string $json
     */
    private ?string $json = null;

    /**
     * Set parameters for class instance.
     *
     * @param string $componentid
     * @param string $modelname
     * @param array $metadata
     * @param int $status
     * @param ?stdClass $record Optional. If given, parameters are extracted from this object.
     *
     */
    public function __construct(
        string $componentid,
        string $modelname,
        array $metadata = [],
        int $status = LOCAL_CATQUIZ_STATUS_NOT_CALCULATED,
        ?stdClass $record = null) {
        $this->componentid = $componentid;
        $this->modelname = $modelname;
        $this->metadata = $metadata;
        $this->status = $status;
        $this->parameters = null;

        if (!$record) {
            return;
        }

        $params = $this->get_model_object()::get_parameters_from_record($record);
        $this->set_parameters($params);
        $this->itemid = $record->itemid;
        $this->id = $record->id ?? null;
        $this->contextid = $record->contextid ?? null;
        $this->timecreated = $record->timecreated ?? null;
        $this->timemodified = $record->timemodified ?? null;
        $this->json = $record->json ?? null;
    }

    /**
     * Creates a new instance from a DB record
     *
     * @param stdClass $record
     * @return self
     */
    public static function from_record(stdClass $record) {
        $instance = new self($record->componentid, $record->model, [], $record->status, $record);
        return $instance;
    }

    /**
     * Converts the instance to an object
     *
     * @return stdClass
     */
    public function to_record() {
        $record = (object) [
            'componentname' => self::COMPONENTNAME,
            'componentid' => $this->componentid,
            'contextid' => $this->contextid,
            'model' => $this->modelname,
            'status' => $this->status,
            'timecreated' => $this->timecreated ?? time(),
            'timemodified' => $this->timemodified,
            'itemid' => $this->itemid,
            'difficulty' => $this->parameters['difficulty'],
            'discrimination' => $this->parameters['discrimination'] ?? 0.0,
            'json' => $this->json,
        ];
        if ($this->id) {
            $record->id = $this->id;
        }

        $record = $this->get_model_object()::add_parameters_to_record($record, $this->get_params_array());

        // Sanitize parameters.
        foreach (['difficulty', 'discrimination'] as $paramname) {
            $record->$paramname = round($this->enforce_min_max_range(floatval($record->$paramname)), 4);
        }
        return $record;
    }

    /**
     * Get params array
     *
     * @return ?array
     */
    public function get_params_array(): ?array {
        return $this->parameters;
    }

    /**
     * Return the ID
     *
     * @return int
     */
    public function get_id(): int {
        return $this->id;
    }

    /**
     * Set the ID
     *
     * @param int $id
     * @return self
     */
    public function set_id(int $id): self {
        $this->id = $id;
        return $this;
    }

    /**
     * Return the ID of the associated item
     *
     * @return int
     */
    public function get_itemid(): int {
        return $this->itemid;
    }

    /**
     * Returns the component ID (e.g. question id).
     *
     * @return string
     */
    public function get_componentid(): string {
        return $this->componentid;
    }

    /**
     * Return name of model.
     *
     * @return string
     */
    public function get_model_name(): string {
        return $this->modelname;
    }

    /**
     * Returns the difficulty as a single float value
     *
     * For some models (e.g. grmgeneralized), this is an aggregate, because there, the difficulty is represented as a float of
     * values.
     *
     * @return float
     */
    public function get_difficulty(): float {
        return $this->get_model_object()::get_difficulty($this->parameters);
    }

    /**
     * Set parameters.
     *
     * @param array $parameters
     * @return self
     */
    public function set_parameters(array $parameters): self {
        $this->parameters = $parameters;
        return $this;
    }

    /**
     * Set difficulty.
     *
     * @param float $difficulty
     * @return self
     */
    public function set_difficulty(float $difficulty): self {
        $this->parameters['difficulty'] = $difficulty;
        return $this;
    }

    /**
     * Set status.
     *
     * @param int $status
     * @return self
     */
    public function set_status(int $status): self {
        $this->status = $status;
        return $this;
    }

    /**
     * Set the context ID
     *
     * @param int $contextid
     * @return self
     */
    public function set_contextid(int $contextid): self {
        $this->contextid = $contextid;
        return $this;
    }

    /**
     * Return status.
     *
     * @return int
     */
    public function get_status(): int {
        return $this->status;
    }

    /**
     * Get the item param with the given ID.
     *
     * @param int $id
     * @return ?self
     */
    public static function get(int $id): ?self {
        if (!$record = catquiz::get_item_param($id)) {
            return null;
        }
        return self::from_record($record);
    }

    /**
     * Checks if this itemparam can be saved to the database
     *
     * @return bool
     */
    public function is_valid(): bool {
        // Let the model decide if this is a valid parameter.
        return $this->get_model_object()::is_valid($this);
    }

    /**
     * Saves the itemparam to the database.
     *
     * If it was already saved, it is updated. Otherwise, a new itemparam is inserted.
     *
     * @return self
     */
    public function save(): self {
        $record = $this->to_record();
        if ($this->id) {
            catquiz::update_item_param($record);
            return $this;
        }
        $this->id = catquiz::save_item_param($record);
        return $this;
    }

    /**
     * Returns the model class
     *
     * @return model_model
     */
    private function get_model_object() {
        if (!self::$models) {
                self::$models = model_strategy::get_installed_models();
                foreach (self::$models as $modelname => $modelclass) {
                    self::$models[$modelname] = model_model::get_instance($modelname);
                }
        }
        return self::$models[$this->modelname];
    }

    /**
     * Ensures that the given value is in a valid range
     *
     * @param float $value
     * @return float
     */
    private function enforce_min_max_range(float $value) {
        if (abs($value) > self::MAX) {
            $value = $value < 0 ? self::MIN : self::MAX;
        }
        return $value;
    }

    public function add_form_fields(MoodleQuickForm $form, string $groupid): void {
        $model = $this->get_model_object();
        $model->definition_after_data_callback($form, $this, $groupid);
    }

    /**
     * Returns parameters as flat array.
     * 
     * @return array
     */
    public function get_parameter_fields(): array {
        return $this->get_model_object()->get_parameter_fields($this);
    }
}
