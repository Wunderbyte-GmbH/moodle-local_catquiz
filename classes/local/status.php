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
 * Сlass status.
 *
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\local;

/**
 * Contains status codes, that can be part of a local_catquiz\local\result.
 *
 * The value of each error constant should have a translation string entry so
 * that it can be automatically translated by the result class.
 *
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class status {

    /**
     * OK
     *
     * @var string
     */
    const OK = 'statusok';
    /**
     * ERROR_GENERAL
     *
     * @var string
     */
    const ERROR_GENERAL = 'error';
    /**
     * ERROR_NO_REMAINING_QUESTIONS
     *
     * @var string
     */
    const ERROR_NO_REMAINING_QUESTIONS = 'noremainingquestions';
    /**
     * ERROR_TESTITEM_ALREADY_IN_RELATED_SCALE
     *
     * @var string
     */
    const ERROR_TESTITEM_ALREADY_IN_RELATED_SCALE = 'testiteminrelatedscale';
    /**
     * ERROR_FETCH_NEXT_QUESTION
     *
     * @var string
     */
    const ERROR_FETCH_NEXT_QUESTION = 'errorfetchnextquestion';
    /**
     * ERROR_REACHED_MAXIMUM_QUESTIONS
     *
     * @var string
     */
    const ERROR_REACHED_MAXIMUM_QUESTIONS = 'reachedmaximumquestions';
    /**
     * ABORT_PERSONABILITY_NOT_CHANGED
     *
     * @var string
     */
    const ABORT_PERSONABILITY_NOT_CHANGED = 'abortpersonabilitynotchanged';
    /**
     * ERROR_EMPTY_FIRST_QUESTION_LIST
     *
     * @var string
     */
    const ERROR_EMPTY_FIRST_QUESTION_LIST = 'emptyfirstquestionlist';

    /**
     * ERROR_NO_ITEMS
     *
     * There are no item params for the given context/catscale.
     *
     * @var string
     */
    const ERROR_NO_ITEMS = 'errornoitems';

    /**
     * The maximum attempt time was exceeded.
     *
     * @var string
     */
    const EXCEEDED_MAX_ATTEMPT_TIME = 'exceededmaxattempttime';

    /**
     * Indicates that the attempt was closed automatically.
     *
     * @var string
     */
    const CLOSED_BY_TIMELIMIT = 'attemptclosedbytimelimit';

    /**
     * An undefined status
     *
     * @var string
     */
    const STATUS_UNDEFINED = 'statusundefined';

    /**
     * Stores the mapping of status string to integer value.
     *
     * @var array
     */
    private static array $mapping = [
        self::STATUS_UNDEFINED => -1,
        self::OK => 0,
        self::ERROR_NO_REMAINING_QUESTIONS => 1,
        self::ERROR_TESTITEM_ALREADY_IN_RELATED_SCALE => 2,
        self::ERROR_FETCH_NEXT_QUESTION => 3,
        self::ERROR_REACHED_MAXIMUM_QUESTIONS => 4,
        self::ABORT_PERSONABILITY_NOT_CHANGED => 5,
        self::ERROR_EMPTY_FIRST_QUESTION_LIST => 6,
        self::ERROR_NO_ITEMS => 7,
        self::EXCEEDED_MAX_ATTEMPT_TIME => 8,
        self::CLOSED_BY_TIMELIMIT => 9,
    ];

    /**
     * Returns the available status values as integers.
     *
     * @return array
     */
    public static function get_all_ints(): array {
        return array_values(self::$mapping);
    }

    /**
     * Helper to store the reverse mapping of integer value to string.
     *
     * This will be filled dynamically when it is used in the to_string method.
     *
     * @var array
     */
    private static array $reversemapping = [];

    /**
     * Assigns each status an int value that can be saved in the attempts database table
     *
     * @param string $status
     *
     * @return int
     */
    public static function to_int(string $status): int {
        if (!array_key_exists($status, self::$mapping)) {
            return -1;
        }
        return self::$mapping[$status];
    }

    /**
     * Returns the string for the status number.
     *
     * @param int $status
     * @throws \moodle_exception
     * @return string
     */
    public static function to_string(int $status): string {
        if (array_key_exists($status, self::$reversemapping)) {
            return get_string(self::$reversemapping[$status], 'local_catquiz');
        }
        if (!$string = array_search($status, self::$mapping)) {
            $string = 'undefined';
        }
        self::$reversemapping[$status] = $string;
        return get_string($string, 'local_catquiz');
    }
}
