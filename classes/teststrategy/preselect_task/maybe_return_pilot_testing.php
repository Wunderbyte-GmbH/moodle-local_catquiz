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
 * Class maybe_return_pilot.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\preselect_task;

use local_catquiz\wb_middleware;

/**
 * Randomly returns a pilot question according to the `pilot_ratio` parameter
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class maybe_return_pilot_testing extends maybe_return_pilot implements wb_middleware {

    /**
     * This value is used to return a pilot question.
     */
    private const PILOT = 0;

    /**
     * This value is used to return a question with item params.
     */
    private const NO_PILOT = 100;

    /**
     * Indicates for each question number whether it should be a pilot or not.
     * @var int[]
     */
    private $pseudorandom = [
        self::NO_PILOT,
        self::NO_PILOT,
        self::PILOT,
        self::NO_PILOT,
        self::PILOT,
        self::NO_PILOT,
        self::NO_PILOT,
        self::NO_PILOT,
        self::PILOT,
        self::PILOT,
        self::PILOT,
        self::PILOT,
        self::PILOT,
        self::PILOT,
        self::PILOT,
        self::NO_PILOT,
        self::NO_PILOT,
        self::NO_PILOT,
        self::PILOT,
        self::PILOT,
        self::NO_PILOT,
        self::PILOT,
        self::NO_PILOT,
        self::PILOT,
        self::PILOT,
        self::NO_PILOT,
        self::NO_PILOT,
        self::NO_PILOT,
        self::PILOT,
        self::PILOT,
        self::NO_PILOT,
        self::NO_PILOT,
        self::NO_PILOT,
        self::PILOT,
        self::PILOT,
    ];

    /**
     * Returns a bool indicating whether a pilot question should be returned.
     *
     * @return bool
     */
    protected function should_return_pilot(): bool {
        $rand = $this->pseudorandom[$this->context['questionsattempted']] ?? 100;
        $shouldreturnpilot = $rand <= $this->context['pilot_ratio'];
        return $shouldreturnpilot;
    }
}
