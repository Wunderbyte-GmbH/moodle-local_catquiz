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

use cache_helper;
use Exception;
use local_catquiz\catquiz;
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
     * @var array<float>
     */
    private array $parameters;


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
     * @var string $id The item id, e.g. question id
     */
    private string $id;

    private static $models = [];

    /**
     * Set parameters for class instance.
     *
     * @param string $id
     * @param string $modelname
     * @param array $metadata
     * @param int $status
     * @param ?stdClass $record Optional. If given, parameters are extracted from this object.
     *
     */
    public function __construct(
        string $id,
        string $modelname,
        array $metadata = [],
        int $status = LOCAL_CATQUIZ_STATUS_NOT_CALCULATED,
        ?stdClass $record = null) {
        $this->id = $id;
        $this->modelname = $modelname;
        $this->metadata = $metadata;
        $this->status = $status;

        if (!$record) {
            return;
        }

        if (!self::$models) {
                self::$models = model_strategy::get_installed_models();
        }

        $params = self::$models[$modelname]::get_parameters_from_record($record);
        $this->set_parameters($params);
    }

    /**
     * Creates a new instance from a DB record
     *
     * @param stdClass $record
     * @return self
     */
    public static function from_record(stdClass $record) {
        $instance = new self($record->id, $record->model, [], $record->status, $record);
        return $instance;
    }

    /**
     * Get params array
     *
     * @return array
     *
     */
    public function get_params_array(): array {
        return $this->parameters;
    }

    /**
     * Returns the item id (e.g. question id).
     *
     * @return string
     */
    public function get_id(): string {
        return $this->id;
    }

    /**
     * Return name of model.
     *
     * @return string
     *
     */
    public function get_model_name(): string {
        return $this->modelname;
    }

    /**
     * Return difficulty.
     *
     * @return float
     *
     */
    public function get_difficulty(): float {
        return $this->parameters['difficulty'];
    }

    /**
     * Set parameters.
     *
     * @param array $parameters
     *
     * @return self
     *
     */
    public function set_parameters(array $parameters): self {
        $this->parameters = $parameters;
        return $this;
    }

    /**
     * Set difficulty.
     *
     * @param float $difficulty
     *
     * @return self
     *
     */
    public function set_difficulty(float $difficulty): self {
        $this->parameters['difficulty'] = $difficulty;
        return $this;
    }

    /**
     * Set metadata
     *
     * @param array $metadata
     *
     * @return self
     *
     */
    public function set_metadata(array $metadata): self {
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * Return metadata.
     *
     * @return array
     *
     */
    public function get_metadata(): array {
        return $this->metadata;
    }

    /**
     * Set status.
     *
     * @param int $status
     *
     * @return self
     *
     */
    public function set_status(int $status): self {
        $this->status = $status;
        return $this;
    }

    /**
     * Return status.
     *
     * @return int
     *
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
}
