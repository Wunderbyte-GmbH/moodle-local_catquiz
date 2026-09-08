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

namespace local_catquiz\local\calculation;

/**
 * Uniform result contract for both calculation modes (issue #43).
 *
 * Both incremental and disruptive runs return the same shape, so the CAT
 * management area, the task console and the persistent summary can treat them
 * identically. For incremental runs source contextid == target contextid; for a
 * successfully completed disruptive run source contextid != target contextid.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class calculation_result {
    /** @var string Run finished successfully. */
    public const STATUS_SUCCESS = 'success';

    /** @var string Nothing to do (no new responses). */
    public const STATUS_SKIPPED = 'skipped';

    /** @var string Run failed; no active context was replaced. */
    public const STATUS_ERROR = 'error';

    /** @var array Backing data. */
    private array $data;

    /**
     * Constructor.
     *
     * @param string $mode
     * @param int $scaleid
     * @param int $sourcecontextid
     */
    public function __construct(string $mode, int $scaleid, int $sourcecontextid) {
        $this->data = [
            'mode' => $mode,
            'scaleid' => $scaleid,
            'sourcecontextid' => $sourcecontextid,
            'targetcontextid' => $sourcecontextid,
            'starttime' => time(),
            'endtime' => null,
            'status' => self::STATUS_SKIPPED,
            'numresponses' => 0,
            'numpersons' => 0,
            'numitems' => 0,
            'changeditems' => 0,
            'modelchanges' => [],
            'criteriabefore' => [],
            'criteriaafter' => [],
            'iterations' => 0,
            'convergencereason' => '',
            'warnings' => [],
            'errors' => [],
        ];
    }

    /**
     * Sets a field.
     *
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function set(string $key, $value): self {
        $this->data[$key] = $value;
        return $this;
    }

    /**
     * Gets a field.
     *
     * @param string $key
     * @return mixed
     */
    public function get(string $key) {
        return $this->data[$key] ?? null;
    }

    /**
     * Marks the run finished with a status and end time.
     *
     * @param string $status
     * @return self
     */
    public function finish(string $status): self {
        $this->data['status'] = $status;
        $this->data['endtime'] = time();
        return $this;
    }

    /**
     * Adds a warning.
     *
     * @param string $message
     * @return self
     */
    public function add_warning(string $message): self {
        $this->data['warnings'][] = $message;
        return $this;
    }

    /**
     * Adds an error.
     *
     * @param string $message
     * @return self
     */
    public function add_error(string $message): self {
        $this->data['errors'][] = $message;
        return $this;
    }

    /**
     * Status.
     *
     * @return string
     */
    public function get_status(): string {
        return $this->data['status'];
    }

    /**
     * Serialises the whole result.
     *
     * @return array
     */
    public function to_array(): array {
        return $this->data;
    }

    /**
     * Rebuilds a result from an array (e.g. persisted JSON).
     *
     * @param array $data
     * @return self
     */
    public static function from_array(array $data): self {
        $result = new self(
            (string) ($data['mode'] ?? ''),
            (int) ($data['scaleid'] ?? 0),
            (int) ($data['sourcecontextid'] ?? 0)
        );
        $result->data = array_merge($result->data, $data);
        return $result;
    }

    /**
     * A short human-readable one-line summary for the task/cron console.
     *
     * @return string
     */
    public function to_console_line(): string {
        return sprintf(
            'catquiz %s: scale %d, %s -> %s, status=%s, changed items=%d, iterations=%d%s',
            $this->data['mode'],
            $this->data['scaleid'],
            $this->data['sourcecontextid'],
            $this->data['targetcontextid'],
            $this->data['status'],
            $this->data['changeditems'],
            $this->data['iterations'],
            $this->data['convergencereason'] !== '' ? ', ' . $this->data['convergencereason'] : ''
        );
    }
}
