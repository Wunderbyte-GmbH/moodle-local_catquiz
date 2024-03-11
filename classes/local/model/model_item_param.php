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

    /**
     * Set parameters for class instance.
     *
     * @param string $id
     * @param string $modelname
     * @param array $metadata
     * @param int $status
     *
     */
    public function __construct(
        string $id,
        string $modelname,
        array $metadata = [],
        int $status = LOCAL_CATQUIZ_STATUS_NOT_CALCULATED) {
        $this->id = $id;
        $this->modelname = $modelname;
        $this->metadata = $metadata;
        $this->status = $status;
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
     * Update params in DB.
     *
     * @param int $id
     * @param int $componentid
     * @param string $model
     * @param int $contextid
     * @param stdClass $newrecord
     *
     * @return void
     *
     * @throws Exception
     *
     */
    public static function update_in_db(
        int $id,
        int $componentid,
        string $model,
        int $contextid,
        stdClass $newrecord
    ) {
        global $DB;

        if (intval($newrecord->status) === LOCAL_CATQUIZ_STATUS_CONFIRMED_MANUALLY) {
            // Only one model can be the selected one. Set the status of all...
            // ... other models back to 0.
            $existingitems = $DB->get_record(
                'local_catquiz_itemparams',
                [
                    'componentid' => $componentid,
                    'contextid' => $contextid,
                    'status' => LOCAL_CATQUIZ_STATUS_CONFIRMED_MANUALLY,
                ]
            );
            // Get item params for other models.
            $otheritems = array_filter(
                $existingitems,
                function($i) use ($model) {
                    return $i->model !== $model;
                }
            );
            foreach ($otheritems as $otheritem) {
                $otheritem->status = LOCAL_CATQUIZ_STATUS_NOT_CALCULATED;
                $DB->update_record('local_catquiz_itemparams', $otheritem, true);
            }
        }

        $dbrecord = $DB->get_record(
            'local_catquiz_itemparams',
            [
                'id' => $id,
            ]
        );
        if (!$dbrecord) {
            throw new Exception('Can not update record because it does not exist');
        }
        foreach ($newrecord as $property => $value) {
            // Some properties should not be updated.
            if (in_array($property, ['id', 'componentid'])) {
                continue;
            }
            $dbrecord->$property = $value;
        }
        $DB->update_record(
            'local_catquiz_itemparams',
            $dbrecord
        );
        cache_helper::purge_by_event('changesintestitems');
    }
}
