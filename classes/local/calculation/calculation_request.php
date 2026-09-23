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

use InvalidArgumentException;

/**
 * Stable request contract for the central calculation service (issue #43).
 *
 * Carries scaleid, contextid, mode, trigger and requestedby. The same request
 * object is used by the scheduled task, the ad-hoc task and any manual action,
 * so the central IRT engine never needs a workflow-specific public interface.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class calculation_request {
    /** @var int */
    private int $scaleid;

    /** @var int */
    private int $contextid;

    /** @var string */
    private string $mode;

    /** @var string */
    private string $trigger;

    /** @var int */
    private int $requestedby;

    /**
     * Builds and validates a request.
     *
     * @param int $scaleid CAT scale id (> 0).
     * @param int $contextid Source context id (> 0).
     * @param string $mode One of calculation_mode::*.
     * @param string $trigger One of calculation_trigger::*.
     * @param int $requestedby User id that requested the run (0 for system/cron).
     */
    public function __construct(int $scaleid, int $contextid, string $mode, string $trigger, int $requestedby = 0) {
        if ($scaleid <= 0) {
            throw new InvalidArgumentException('calculation_request: scaleid must be a positive integer.');
        }
        if ($contextid <= 0) {
            throw new InvalidArgumentException('calculation_request: contextid must be a positive integer.');
        }
        if (!calculation_mode::is_valid($mode)) {
            throw new InvalidArgumentException("calculation_request: invalid mode '{$mode}'.");
        }
        if (!calculation_trigger::is_valid($trigger)) {
            throw new InvalidArgumentException("calculation_request: invalid trigger '{$trigger}'.");
        }
        // A scheduled trigger may only ever run the incremental mode (issue #44/#43):
        // a disruptive recalculation must be a deliberate manual action.
        if (
            $trigger === calculation_trigger::SCHEDULED
            && $mode !== calculation_mode::INCREMENTAL_RECALCULATION
        ) {
            throw new InvalidArgumentException(
                'calculation_request: a scheduled trigger may only run the incremental mode.'
            );
        }
        $this->scaleid = $scaleid;
        $this->contextid = $contextid;
        $this->mode = $mode;
        $this->trigger = $trigger;
        $this->requestedby = $requestedby;
    }

    /**
     * Scale id.
     *
     * @return int
     */
    public function get_scaleid(): int {
        return $this->scaleid;
    }

    /**
     * Source context id.
     *
     * @return int
     */
    public function get_contextid(): int {
        return $this->contextid;
    }

    /**
     * Calculation mode.
     *
     * @return string
     */
    public function get_mode(): string {
        return $this->mode;
    }

    /**
     * Trigger.
     *
     * @return string
     */
    public function get_trigger(): string {
        return $this->trigger;
    }

    /**
     * Requesting user id.
     *
     * @return int
     */
    public function get_requestedby(): int {
        return $this->requestedby;
    }

    /**
     * Serialises the request for ad-hoc task custom data.
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'scaleid' => $this->scaleid,
            'contextid' => $this->contextid,
            'mode' => $this->mode,
            'trigger' => $this->trigger,
            'requestedby' => $this->requestedby,
        ];
    }

    /**
     * Rebuilds a request from serialised custom data.
     *
     * @param array $data
     * @return self
     */
    public static function from_array(array $data): self {
        return new self(
            (int) ($data['scaleid'] ?? 0),
            (int) ($data['contextid'] ?? 0),
            (string) ($data['mode'] ?? ''),
            (string) ($data['trigger'] ?? ''),
            (int) ($data['requestedby'] ?? 0)
        );
    }
}
