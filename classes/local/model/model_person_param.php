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

/**
 * This class holds a single item param object.
 *
 * This is one of the return values from a model param estimation.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class model_person_param implements \ArrayAccess {

    // For some items, the model returns -INF or INF as difficulty.
    // However, we expect it to be numeric, so we encode those values as -1000 and 1000.
    /**
     * MODEL_NEG_INF
     *
     * @var int
     */
    const MODEL_NEG_INF = -1000;
    /**
     * MODEL_POS_INF
     *
     * @var int
     */
    const MODEL_POS_INF = 1000;
    /**
     * The ID of the user
     *
     * @var string
     */
    private string $id;

    /**
     * @var float ability
     */
    private float $ability = 0;

    /**
     * Supported parameters
     * @var array<string>
     */
    private array $params;

    /**
     * @var int $catscaleid
     */
    private int $catscaleid;

    /**
     * Instantiate parameter.
     *
     * @param string $id
     *
     */
    public function __construct(string $id) {
        $this->id = $id;
        $this->params = ['ability'];
    }

    /**
     * OffsetExists.
     *
     * @param mixed $offset
     *
     * @return bool
     *
     */
    public function offsetExists($offset): bool {
        return in_array($offset, $this->params);
    }

    /**
     * OffsetGet.
     *
     * @param mixed $offset
     *
     * @return mixed
     *
     */
    public function offsetGet($offset): mixed {
        if (! $this->offsetExists($offset)) {
            return null;
        }
        return $this->$offset;
    }

    /**
     * OffsetSet.
     *
     * @param mixed $offset
     * @param mixed $value
     *
     * @return void
     *
     */
    public function offsetSet($offset, $value): void {
        if (! $this->offsetExists($offset)) {
            return;
        }
        $this->$offset = $value;
    }

    /**
     * OffsetUnset.
     *
     * @param mixed $offset
     *
     * @return void
     *
     */
    public function offsetUnset($offset): void {
    }

    /**
     * Return ID.
     *
     * @return string
     */
    public function get_id(): string {
        return $this->id;
    }

    /**
     * Return ability.
     *
     * @return float
     *
     */
    public function get_ability(): float {
        return $this->ability;
    }

    /**
     * Set ability.
     *
     * @param float $ability
     *
     * @return self
     *
     */
    public function set_ability(float $ability): self {
        $this->ability = $ability;
        return $this;
    }

    /**
     * Get the catscale ID of this parameter
     *
     * @return int
     */
    public function get_catscaleid(): int {
        return $this->catscaleid;
    }

    /**
     * Set the catscale ID of this parameter
     *
     * @param int $catscaleid
     * @return self
     */
    public function set_catscaleid(int $catscaleid): self {
        $this->catscaleid = $catscaleid;
        return $this;
    }

    /**
     * To array.
     *
     * @return array
     *
     */
    public function to_array(): array {
        return ['ability' => $this->ability];
    }
}
