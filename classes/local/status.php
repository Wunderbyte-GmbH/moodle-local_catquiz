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
    const OK = 'ok';
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
     * Assigns each status an int value that can be saved in the attempts database table
     *
     * @param string $status
     *
     * @return int
     */
    public static function to_int(string $status): int {
        switch ($status) {
            case self::OK:
                return 0;
            case self::ERROR_NO_REMAINING_QUESTIONS:
                return 1;
            case self::ERROR_TESTITEM_ALREADY_IN_RELATED_SCALE:
                return 2;
            case self::ERROR_FETCH_NEXT_QUESTION:
                return 3;
            case self::ERROR_REACHED_MAXIMUM_QUESTIONS:
                return 4;
            case self::ABORT_PERSONABILITY_NOT_CHANGED:
                return 5;
            case self::ERROR_EMPTY_FIRST_QUESTION_LIST:
                return 6;
            case self::ERROR_NO_ITEMS:
                return 7;
            case self::EXCEEDED_MAX_ATTEMPT_TIME:
                return 8;
            default:
                return -1;
        }
    }
}
